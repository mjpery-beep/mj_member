<?php
/**
 * Web Push Notifications – module principal.
 *
 * - Enregistre les endpoints AJAX pour subscribe / unsubscribe.
 * - Sert le Service Worker depuis une URL WordPress pour contourner le scope limitation.
 * - Se branche sur mj_member_record_notification pour déclencher le push.
 *
 * @package MjMember
 */

namespace Mj\Member\Module {
    use Mj\Member\Core\Contracts\ModuleInterface;
    if (!defined('ABSPATH')) { exit; }

    final class WebPushModule implements ModuleInterface {
        public function register(): void {
            add_action('init', 'mj_member_web_push_sw_rewrite', 5);
            add_filter('query_vars', 'mj_member_web_push_sw_query_var');
            add_action('template_redirect', 'mj_member_web_push_sw_template_redirect', 1);
            add_action('wp_ajax_mj_push_subscribe', 'mj_member_ajax_push_subscribe');
            add_action('wp_ajax_mj_push_unsubscribe', 'mj_member_ajax_push_unsubscribe');
            add_filter('mj_member_notification_recorded', 'mj_member_web_push_on_notification_recorded', 10, 3);
            add_action('mj_member_process_push_batch', 'mj_member_process_push_batch_handler');
            add_action('wp_ajax_mj_generate_vapid_keys', 'mj_member_ajax_generate_vapid_keys');
        }
    }
}

namespace {
    use Mj\Member\Core\Config;
    use Mj\Member\Classes\Crud\MjPushSubscriptions;
    use Mj\Member\Classes\Crud\MjMembers;
    use Mj\Member\Classes\MjWebPush;
    if (!defined('ABSPATH')) { exit; }

// ============================================================================
// Service Worker registration endpoint (rootscope obligatoire pour push)
// ============================================================================

if (!function_exists('mj_member_web_push_sw_rewrite')) {
    /**
     * Enregistre une rewrite rule pour servir le SW depuis la racine du site.
     */
    function mj_member_web_push_sw_rewrite(): void
    {
        add_rewrite_rule('^mj-sw\.js$', 'index.php?mj_push_sw=1', 'top');
    }
    add_action('init', 'mj_member_web_push_sw_rewrite', 5);
}

if (!function_exists('mj_member_web_push_sw_query_var')) {
    function mj_member_web_push_sw_query_var(array $vars): array
    {
        $vars[] = 'mj_push_sw';
        return $vars;
    }
    add_filter('query_vars', 'mj_member_web_push_sw_query_var');
}

if (!function_exists('mj_member_web_push_sw_template_redirect')) {
    /**
     * Si la requête est pour le Service Worker, on sert le fichier JS.
     */
    function mj_member_web_push_sw_template_redirect(): void
    {
        if (!get_query_var('mj_push_sw')) {
            return;
        }

        $sw_path = Config::path() . 'js/sw-push.js';
        if (!file_exists($sw_path)) {
            status_header(404);
            exit;
        }

        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        // @codingStandardsIgnoreLine
        readfile($sw_path);
        exit;
    }
    add_action('template_redirect', 'mj_member_web_push_sw_template_redirect', 1);
}

// ============================================================================
// AJAX: Subscribe (front – utilisateur connecté)
// ============================================================================

if (!function_exists('mj_member_ajax_push_subscribe')) {
    /**
     * Enregistre une souscription push pour l'utilisateur connecté.
     */
    function mj_member_ajax_push_subscribe(): void
    {
        check_ajax_referer('mj-push-subscribe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 401);
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        // L'endpoint FCM doit être conservé tel quel — esc_url_raw() peut
        // corrompre les tokens qui contiennent des caractères que WP re-encode.
        // On valide simplement que c'est bien une URL https.
        $raw_endpoint = isset($data['endpoint']) ? trim((string) $data['endpoint']) : '';
        $endpoint = '';
        if ($raw_endpoint !== '' && preg_match('#^https://#i', $raw_endpoint)) {
            $endpoint = $raw_endpoint;
        }

        $public_key = isset($data['public_key']) ? sanitize_text_field($data['public_key']) : '';
        $auth_token = isset($data['auth_token']) ? sanitize_text_field($data['auth_token']) : '';
        $content_encoding = isset($data['content_encoding']) ? sanitize_key($data['content_encoding']) : 'aesgcm';

        if ($endpoint === '' || $public_key === '' || $auth_token === '') {
            wp_send_json_error(array('message' => __('Données de souscription incomplètes.', 'mj-member')), 400);
        }

        $user_id = get_current_user_id();
        $member_id = null;

        if (class_exists(MjMembers::class)) {
            $member = MjMembers::getByWpUserId($user_id);
            if ($member && isset($member->id)) {
                $member_id = (int) $member->id;
            }
        }

        error_log(sprintf(
            '[MJ Push] AJAX subscribe: user_id=%d member_id=%s endpoint=%s... pk=%dc auth=%dc encoding=%s',
            $user_id,
            $member_id ?? 'null',
            substr($endpoint, 0, 90),
            strlen($public_key),
            strlen($auth_token),
            $content_encoding
        ));

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        // ── Vérifier si cet endpoint a été blacklisté (410 Gone récent) ──
        // Le navigateur renvoie le même endpoint périmé ; on doit forcer un re-subscribe frais.
        if (MjWebPush::is_endpoint_blacklisted($endpoint)) {
            error_log('[MJ Push] AJAX subscribe: endpoint BLACKLISTED (410 récent), demande refresh au client – endpoint=' . substr($endpoint, 0, 90));
            wp_send_json_success(array(
                'message'       => __('Abonnement expiré, re-souscription nécessaire.', 'mj-member'),
                'needs_refresh' => true,
            ));
        }

        // Vérifier si cet endpoint est déjà enregistré
        $existing = MjPushSubscriptions::find_by_endpoint($endpoint);
        if ($existing) {
            // Mettre à jour (membre a pu changer etc.)
            MjPushSubscriptions::update((int) $existing->id, array(
                'member_id'        => $member_id,
                'user_id'          => $user_id,
                'public_key'       => $public_key,
                'auth_token'       => $auth_token,
                'content_encoding' => $content_encoding,
                'user_agent'       => $user_agent,
            ));

            // Supprimer les anciens endpoints périmés de ce même user
            MjPushSubscriptions::delete_stale_for_user($user_id, (int) $existing->id);

            error_log('[MJ Push] AJAX subscribe: updated existing id=' . $existing->id);
            wp_send_json_success(array('message' => __('Abonnement mis à jour.', 'mj-member'), 'id' => (int) $existing->id));
        }

        // Nouvel endpoint : supprimer tous les anciens de cet user avant d'insérer
        MjPushSubscriptions::delete_all_for_user(null, $user_id);

        // Si le nouvel endpoint est différent d'un endpoint blacklisté, c'est un vrai refresh → lever la blacklist
        MjWebPush::unblacklist_endpoint($endpoint);

        $result = MjPushSubscriptions::create(array(
            'member_id'        => $member_id,
            'user_id'          => $user_id,
            'endpoint'         => $endpoint,
            'public_key'       => $public_key,
            'auth_token'       => $auth_token,
            'content_encoding' => $content_encoding,
            'user_agent'       => $user_agent,
        ));

        if (is_wp_error($result)) {
            error_log('[MJ Push] AJAX subscribe: create FAILED – ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        error_log('[MJ Push] AJAX subscribe: created new id=' . $result);
        wp_send_json_success(array('message' => __('Abonnement enregistré.', 'mj-member'), 'id' => $result));
    }

    add_action('wp_ajax_mj_push_subscribe', 'mj_member_ajax_push_subscribe');
}

// ============================================================================
// AJAX: Unsubscribe
// ============================================================================

if (!function_exists('mj_member_ajax_push_unsubscribe')) {
    /**
     * Supprime une souscription push pour l'utilisateur connecté.
     */
    function mj_member_ajax_push_unsubscribe(): void
    {
        check_ajax_referer('mj-push-subscribe', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 401);
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $raw_endpoint = isset($data['endpoint']) ? trim((string) $data['endpoint']) : '';
        $endpoint = '';
        if ($raw_endpoint !== '' && preg_match('#^https://#i', $raw_endpoint)) {
            $endpoint = $raw_endpoint;
        }
        if ($endpoint === '') {
            wp_send_json_error(array('message' => __('Endpoint manquant.', 'mj-member')), 400);
        }

        MjPushSubscriptions::delete_by_endpoint($endpoint);

        wp_send_json_success(array('message' => __('Abonnement supprimé.', 'mj-member')));
    }

    add_action('wp_ajax_mj_push_unsubscribe', 'mj_member_ajax_push_unsubscribe');
}

// ============================================================================
// Hook: Envoyer push après enregistrement d'une notification
// ============================================================================

if (!function_exists('mj_member_web_push_on_notification_recorded')) {
    /**
     * Filtre conservé pour rétro-compatibilité uniquement.
     *
     * L'envoi push est désormais géré directement dans
     * MjNotificationManager::dispatch_push() pour plus de fiabilité.
     * Ce filtre ne fait plus qu'un pass-through.
     */
    function mj_member_web_push_on_notification_recorded(array $result, array $notification_data, array $recipients): array
    {
        // Push désormais envoyé depuis MjNotificationManager::dispatch_push()
        return $result;
    }

    add_filter('mj_member_notification_recorded', 'mj_member_web_push_on_notification_recorded', 10, 3);
}

// ============================================================================
// Cron handler : envoie les push en arrière-plan
// ============================================================================

if (!function_exists('mj_member_process_push_batch_handler')) {
    function mj_member_process_push_batch_handler(string $batch_key): void
    {
        $data = get_transient($batch_key);
        delete_transient($batch_key);

        if (!is_array($data) || empty($data['payload'])) {
            return;
        }

        $payload = $data['payload'];

        if (!empty($data['member_ids'])) {
            MjWebPush::send_to_members((array) $data['member_ids'], $payload);
        }

        if (!empty($data['user_ids'])) {
            MjWebPush::send_to_users((array) $data['user_ids'], $payload);
        }
    }
    add_action('mj_member_process_push_batch', 'mj_member_process_push_batch_handler');
}

// ============================================================================
// AJAX Admin: Générer les clés VAPID
// ============================================================================

if (!function_exists('mj_member_ajax_generate_vapid_keys')) {
    function mj_member_ajax_generate_vapid_keys(): void
    {
        check_ajax_referer('mj-member-settings', 'nonce');

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(array('message' => __('Permission refusée.', 'mj-member')), 403);
        }

        if (!class_exists('Minishlink\WebPush\VAPID')) {
            wp_send_json_error(array('message' => __('La bibliothèque web-push n\'est pas installée. Exécutez composer install.', 'mj-member')), 500);
        }

        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            wp_send_json_success(array(
                'publicKey'  => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()), 500);
        }
    }
    add_action('wp_ajax_mj_generate_vapid_keys', 'mj_member_ajax_generate_vapid_keys');
}

// ============================================================================
// Flush rewrite pour le Service Worker quand le plugin est activé
// ============================================================================

if (!function_exists('mj_member_web_push_activation')) {
    function mj_member_web_push_activation(): void
    {
        mj_member_web_push_sw_rewrite();
        flush_rewrite_rules(false);
    }
    register_activation_hook(Config::mainFile(), 'mj_member_web_push_activation');
}
} // end namespace {
