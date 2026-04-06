<?php
namespace Mj\Member\Module {
    use Mj\Member\Core\Contracts\ModuleInterface;
    if (!defined('ABSPATH')) { exit; }

    final class PhotoGrimlinsModule implements ModuleInterface {
        public function register(): void {
            add_action('wp_ajax_mj_member_generate_grimlins', 'mj_member_photo_grimlins_ajax_generate');
            add_action('wp_ajax_nopriv_mj_member_generate_grimlins', 'mj_member_photo_grimlins_ajax_generate');
            add_action('wp_ajax_mj_member_apply_grimlins_avatar', 'mj_member_photo_grimlins_ajax_apply_avatar');
            add_action('wp_ajax_mj_member_delete_grimlins_avatar', 'mj_member_photo_grimlins_ajax_delete_avatar');
            add_action('wp_ajax_mj_member_photo_grimlins_search_young', 'mj_member_photo_grimlins_ajax_search_young');
            add_filter('wp_get_attachment_url', 'mj_member_photo_grimlins_override_attachment_url', 20, 2);
            add_filter('wp_get_attachment_image_src', 'mj_member_photo_grimlins_override_image_src', 20, 3);
            add_filter('wp_calculate_image_srcset', 'mj_member_photo_grimlins_override_srcset', 20, 5);
        }
    }
}

namespace {
    if (!defined('ABSPATH')) { exit; }

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjOpenAIClient;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

if (!function_exists('mj_member_photo_grimlins_default_prompt')) {
    function mj_member_photo_grimlins_default_prompt(): string
    {
        return __('Transforme cette personne en version "Grimlins" fun et stylisée, avec un rendu illustratif détaillé, sans éléments effrayants.', 'mj-member');
    }
}

if (!function_exists('mj_member_photo_grimlins_get_prompt')) {
    function mj_member_photo_grimlins_get_prompt(): string
    {
        $stored = get_option('mj_member_photo_grimlins_prompt', '');
        $candidate = is_string($stored) && $stored !== '' ? $stored : mj_member_photo_grimlins_default_prompt();

        return apply_filters('mj_member_photo_grimlins_prompt', $candidate);
    }
}

if (!function_exists('mj_member_photo_grimlins_is_enabled')) {
    function mj_member_photo_grimlins_is_enabled(): bool
    {
        return Config::openAiApiKey() !== '';
    }
}

if (!function_exists('mj_member_photo_grimlins_count_user_generations')) {
    function mj_member_photo_grimlins_count_user_generations(int $user_id, int $limit_hint = 0): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $posts_per_page = $limit_hint > 0 ? $limit_hint + 1 : 10;

        $query = new \WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => array('private', 'inherit'),
            'author' => $user_id,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $posts_per_page,
            'meta_query' => array(
                array(
                    'key' => '_mj_member_photo_grimlins',
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        return !empty($query->posts) ? count($query->posts) : 0;
    }
}

if (!function_exists('mj_member_photo_grimlins_count_member_generations')) {
    function mj_member_photo_grimlins_count_member_generations(int $user_id, int $member_id, int $limit_hint = 0): int
    {
        if ($user_id <= 0 || $member_id <= 0) {
            return 0;
        }

        $posts_per_page = $limit_hint > 0 ? $limit_hint + 1 : 10;

        $query = new \WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => array('private', 'inherit'),
            'author' => $user_id,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $posts_per_page,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_mj_member_photo_grimlins',
                    'compare' => 'EXISTS',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_mj_member_photo_grimlins_member',
                        'value' => (string) $member_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_mj_member_photo_grimlins_member_id',
                        'value' => (string) $member_id,
                        'compare' => '=',
                    ),
                ),
            ),
        ));

        return !empty($query->posts) ? count($query->posts) : 0;
    }
}

if (!function_exists('mj_member_photo_grimlins_format_generation_item')) {
    /**
     * @param array{size?:string,current_attachment_id?:int,user_id?:int,member_id?:int} $args
     */
    function mj_member_photo_grimlins_format_generation_item(int $attachment_id, array $args = array()): ?array
    {
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return null;
        }

        $defaults = array(
            'size' => 'medium',
            'current_attachment_id' => 0,
            'user_id' => 0,
            'member_id' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $thumbnail = wp_get_attachment_image_src($attachment_id, $args['size']);
        $full = wp_get_attachment_image_src($attachment_id, 'full');
        $download_url = wp_get_attachment_url($attachment_id);

        $created_iso = '';
        if (!empty($attachment->post_date_gmt) && $attachment->post_date_gmt !== '0000-00-00 00:00:00') {
            $created_iso = get_date_from_gmt($attachment->post_date_gmt, DATE_ATOM);
        } elseif (!empty($attachment->post_date) && $attachment->post_date !== '0000-00-00 00:00:00') {
            $created_iso = get_date_from_gmt(get_gmt_from_date($attachment->post_date), DATE_ATOM);
        }

        $created_label = !empty($attachment->post_date) && $attachment->post_date !== '0000-00-00 00:00:00'
            ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $attachment->post_date)
            : '';
        $session = get_post_meta($attachment_id, '_mj_member_photo_grimlins_session', true);
        $user_id = (int) $args['user_id'];

        $item = array(
            'id' => (int) $attachment_id,
            'url' => $full && !empty($full[0]) ? esc_url_raw($full[0]) : ($download_url ? esc_url_raw($download_url) : ''),
            'thumbnail' => $thumbnail && !empty($thumbnail[0]) ? esc_url_raw($thumbnail[0]) : ($download_url ? esc_url_raw($download_url) : ''),
            'downloadUrl' => $download_url ? esc_url_raw($download_url) : '',
            'downloadName' => sanitize_file_name(sprintf('grimlins-%d-%s.png', $user_id > 0 ? $user_id : get_current_user_id(), $session !== '' ? $session : $attachment_id)),
            'createdAt' => $created_iso,
            'createdLabel' => $created_label,
            'isCurrent' => (int) $args['current_attachment_id'] > 0 && (int) $attachment_id === (int) $args['current_attachment_id'],
            'canApply' => true,
            'session' => is_string($session) ? $session : '',
            'linkedMemberId' => (int) get_post_meta($attachment_id, '_mj_member_photo_grimlins_member', true),
        );

        if (!empty($args['member_id'])) {
            $linked_member = (int) $item['linkedMemberId'];
            $item['canApply'] = $linked_member === 0 || $linked_member === (int) $args['member_id'];
        }

        /** @var array $filtered */
        $filtered = apply_filters('mj_member_photo_grimlins_history_item', $item, $attachment_id, $args);

        return $filtered;
    }
}

if (!function_exists('mj_member_photo_grimlins_list_member_generations')) {
    /**
     * @param array{limit?:int,size?:string,current_attachment_id?:int,user_id?:int,member_id?:int} $args
     */
    function mj_member_photo_grimlins_list_member_generations(int $user_id, array $args = array()): array
    {
        if ($user_id <= 0) {
            return array();
        }

        $defaults = array(
            'limit' => 0,
            'size' => 'medium',
            'current_attachment_id' => 0,
            'user_id' => $user_id,
            'member_id' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $posts_per_page = (int) $args['limit'] > 0 ? (int) $args['limit'] : 20;

        $query = new \WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => array('private', 'inherit'),
            'author' => $user_id,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $posts_per_page,
            'meta_query' => array(
                array(
                    'key' => '_mj_member_photo_grimlins',
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        $items = array();
        if (!empty($query->posts) && is_array($query->posts)) {
            foreach ($query->posts as $attachment_id) {
                $formatted = mj_member_photo_grimlins_format_generation_item((int) $attachment_id, $args);
                if ($formatted) {
                    $items[] = $formatted;
                }
            }
        }

        return $items;
    }
}

if (!function_exists('mj_member_photo_grimlins_list_linked_member_generations')) {
    /**
     * @param array{limit?:int,size?:string,current_attachment_id?:int,member_id?:int} $args
     */
    function mj_member_photo_grimlins_list_linked_member_generations(int $member_id, array $args = array()): array
    {
        if ($member_id <= 0) {
            return array();
        }

        $defaults = array(
            'limit' => 0,
            'size' => 'medium',
            'current_attachment_id' => 0,
            'member_id' => $member_id,
        );

        $args = wp_parse_args($args, $defaults);
        $posts_per_page = (int) $args['limit'] > 0 ? (int) $args['limit'] : 20;

        $query = new \WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => array('private', 'inherit'),
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $posts_per_page,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_mj_member_photo_grimlins',
                    'compare' => 'EXISTS',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_mj_member_photo_grimlins_member',
                        'value' => (string) $member_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_mj_member_photo_grimlins_member_id',
                        'value' => (string) $member_id,
                        'compare' => '=',
                    ),
                ),
            ),
        ));

        $items = array();
        if (!empty($query->posts) && is_array($query->posts)) {
            foreach ($query->posts as $attachment_id) {
                $formatted = mj_member_photo_grimlins_format_generation_item((int) $attachment_id, $args);
                if ($formatted) {
                    $items[] = $formatted;
                }
            }
        }

        return $items;
    }
}

if (!function_exists('mj_member_photo_grimlins_localize')) {
    function mj_member_photo_grimlins_localize(): void
    {
        static $localized = false;
        if ($localized) {
            return;
        }

        if (!wp_script_is('mj-member-photo-grimlins', 'enqueued')) {
            return;
        }

        $maxSize = (int) apply_filters('mj_member_photo_grimlins_max_size', 5 * MB_IN_BYTES);
        $allowedMimes = apply_filters('mj_member_photo_grimlins_allowed_mimes', array('image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'));
        $cameraEnabled = (bool) apply_filters('mj_member_photo_grimlins_enable_camera', true, get_current_user_id());

        $can_apply_avatar = false;
        $can_delete_avatar = false;
        $current_member = null;
        $member_photo_id = 0;
        $member_history = array();
        $histories_by_member = array();
        $member_history_limit = 0;
        $member_history_count = 0;

        $history_size = apply_filters('mj_member_photo_grimlins_history_size', 'medium');

        if (is_user_logged_in() && function_exists('mj_member_get_current_member')) {
            $candidate_member = mj_member_get_current_member();
            if ($candidate_member && !empty($candidate_member->id)) {
                $current_member = $candidate_member;
                $can_apply_avatar = true;
                $can_delete_avatar = MjRoles::isAnimateurOrCoordinateur((string) ($candidate_member->role ?? ''));
                $member_photo_id = !empty($candidate_member->photo_id) ? (int) $candidate_member->photo_id : 0;
            }
        }

        $member_history_limit = (int) apply_filters('mj_member_photo_grimlins_member_limit', 3, $current_member, get_current_user_id());

        if ($can_apply_avatar) {
            $member_history = mj_member_photo_grimlins_list_member_generations(
                get_current_user_id(),
                array(
                    'limit' => $member_history_limit > 0 ? $member_history_limit : 20,
                    'size' => $history_size,
                    'current_attachment_id' => $member_photo_id,
                    'user_id' => get_current_user_id(),
                    'member_id' => $current_member ? (int) $current_member->id : 0,
                )
            );
            $self_member_id = $current_member ? (int) $current_member->id : 0;
            $self_history = array_values(array_filter($member_history, static function ($item) use ($self_member_id) {
                if (!is_array($item)) {
                    return false;
                }
                $linked = isset($item['linkedMemberId']) ? (int) $item['linkedMemberId'] : 0;
                return $linked === 0 || ($self_member_id > 0 && $linked === $self_member_id);
            }));

            if (empty($self_history) && $self_member_id > 0) {
                $self_history = mj_member_photo_grimlins_list_linked_member_generations(
                    $self_member_id,
                    array(
                        'limit' => $member_history_limit > 0 ? $member_history_limit : 20,
                        'size' => $history_size,
                        'current_attachment_id' => $member_photo_id,
                        'member_id' => $self_member_id,
                    )
                );
            }

            $member_history = $self_history;
            $member_history_count = count($member_history);
            $histories_by_member['0'] = $member_history;

            if ($current_member && function_exists('mj_member_get_guardian_children')) {
                $children = mj_member_get_guardian_children($current_member);
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if (!$child || !is_object($child) || empty($child->id)) {
                            continue;
                        }

                        $child_member_id = (int) $child->id;
                        $child_photo_id = !empty($child->photo_id) ? (int) $child->photo_id : 0;

                        $histories_by_member[(string) $child_member_id] = mj_member_photo_grimlins_list_member_generations(
                            get_current_user_id(),
                            array(
                                'limit' => $member_history_limit > 0 ? $member_history_limit : 20,
                                'size' => $history_size,
                                'current_attachment_id' => $child_photo_id,
                                'user_id' => get_current_user_id(),
                                'member_id' => $child_member_id,
                            )
                        );

                        $histories_by_member[(string) $child_member_id] = array_values(array_filter(
                            $histories_by_member[(string) $child_member_id],
                            static function ($item) use ($child_member_id) {
                                if (!is_array($item)) {
                                    return false;
                                }
                                return isset($item['linkedMemberId']) && (int) $item['linkedMemberId'] === $child_member_id;
                            }
                        ));
                    }
                }
            }
        }

        wp_localize_script('mj-member-photo-grimlins', 'mjMemberPhotoGrimlins', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_member_photo_grimlins'),
            'searchYoungNonce' => wp_create_nonce('mj_member_photo_grimlins_search_young'),
            'maxSize' => $maxSize,
            'allowedMimes' => array_values($allowedMimes),
            'enabled' => mj_member_photo_grimlins_is_enabled(),
            'cameraEnabled' => $cameraEnabled,
            'canApplyAvatar' => $can_apply_avatar,
            'applyAvatarNonce' => $can_apply_avatar ? wp_create_nonce('mj_member_photo_grimlins_apply_avatar') : '',
            'canDeleteAvatar' => $can_delete_avatar,
            'deleteAvatarNonce' => $can_delete_avatar ? wp_create_nonce('mj_member_photo_grimlins_delete_avatar') : '',
            'memberLimit' => $member_history_limit,
            'history' => $member_history,
            'historiesByMember' => $histories_by_member,
            'historyCount' => $member_history_count,
            'i18n' => array(
                'buttonGenerate' => __('Générer mon Grimlins', 'mj-member'),
                'buttonRetry' => __('Réessayer', 'mj-member'),
                'buttonDownload' => __('Télécharger', 'mj-member'),
                'buttonCamera' => __('Prendre une photo', 'mj-member'),
                'cameraCapture' => __('Capturer', 'mj-member'),
                'cameraCancel' => __('Annuler', 'mj-member'),
                'cameraReady' => __('Caméra prête. Appuie sur "Capturer".', 'mj-member'),
                'cameraStarting' => __('Initialisation de la caméra…', 'mj-member'),
                'cameraError' => __('Impossible d’accéder à la caméra.', 'mj-member'),
                'cameraPermission' => __('Autorise l’accès à la caméra pour prendre une photo.', 'mj-member'),
                'cameraPermissionDenied' => __('Permission caméra refusée. Autorise la caméra dans ton navigateur ou téléverse une photo.', 'mj-member'),
                'loading' => __('Transformation en cours…', 'mj-member'),
                'ready' => __('Ton avatar Grimlins est prêt !', 'mj-member'),
                'missingFile' => __('Sélectionne une photo avant de lancer la génération.', 'mj-member'),
                'fileTooLarge' => __('Le fichier dépasse la limite autorisée.', 'mj-member'),
                'mimeNotAllowed' => __('Ce format de fichier ne peut pas être transformé.', 'mj-member'),
                'disabled' => __('La génération est momentanément désactivée. Contacte la MJ pour plus d’informations.', 'mj-member'),
                'genericError' => __('La génération a échoué. Réessaie dans un instant.', 'mj-member'),
                'cameraUnavailable' => __('Impossible d’ouvrir la caméra sur cet appareil.', 'mj-member'),
                'cameraNotSupported' => __('La capture caméra n’est pas prise en charge par ce navigateur.', 'mj-member'),
                'applyAvatarPending' => __('Mise à jour de ton avatar…', 'mj-member'),
                'applyAvatarSuccess' => __('Avatar mis à jour !', 'mj-member'),
                'applyAvatarError' => __('Impossible de mettre à jour ton avatar.', 'mj-member'),
                'applyAvatarUnauthorized' => __('Tu dois être connecté pour mettre à jour ton avatar.', 'mj-member'),
                'historyTitle' => __('Mes avatars Grimlins', 'mj-member'),
                'historyEmpty' => __('Commence par générer un premier avatar Grimlins.', 'mj-member'),
                'historyDownload' => __('Télécharger', 'mj-member'),
                'historyApply' => __('Utiliser cet avatar', 'mj-member'),
                'historyDelete' => __('Supprimer', 'mj-member'),
                'historyDeleteConfirm' => __('Supprimer définitivement cet avatar Grimlins ?', 'mj-member'),
                'historyDeletePending' => __('Suppression en cours…', 'mj-member'),
                'historyDeleteSuccess' => __('Avatar supprimé.', 'mj-member'),
                'historyDeleteError' => __('Impossible de supprimer cet avatar.', 'mj-member'),
                'historyCurrent' => __('Avatar actuel', 'mj-member'),
                'historyForMember' => __('Avatars de %name%', 'mj-member'),
                'historyLimitCounter' => __('%count% / %limit% avatars utilisés', 'mj-member'),
                'historyLimitReached' => __('Tu as atteint la limite de créations disponibles.', 'mj-member'),
                'youngSearchLoading' => __('Recherche des jeunes…', 'mj-member'),
                'youngSearchNoResult' => __('Aucun jeune trouvé.', 'mj-member'),
                'youngSearchError' => __('Impossible de charger la recherche des jeunes.', 'mj-member'),
                'youngSearchPick' => __('Choisir', 'mj-member'),
            ),
        ));

        $localized = true;
    }
}

if (!function_exists('mj_member_photo_grimlins_ajax_generate')) {
    function mj_member_photo_grimlins_ajax_generate(): void
    {
        if (!check_ajax_referer('mj_member_photo_grimlins', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Requête invalide. Recharge la page puis réessaie.', 'mj-member')), 403);
        }

        $access_scope_raw = isset($_POST['accessScope']) ? sanitize_key((string) wp_unslash($_POST['accessScope'])) : '';
        $access_scope = $access_scope_raw === 'public' ? 'public' : 'members';
        $access_nonce = isset($_POST['accessNonce']) ? sanitize_text_field(wp_unslash($_POST['accessNonce'])) : '';

        $expected_scope_action = 'mj_member_photo_grimlins_scope_' . $access_scope;
        if ($access_nonce === '' || !wp_verify_nonce($access_nonce, $expected_scope_action)) {
            wp_send_json_error(array('message' => __('La session a expiré. Merci de recharger la page.', 'mj-member')), 400);
        }

        $enforce_limit = ($access_scope !== 'public');
        $is_logged_in = is_user_logged_in();

        $history_size = apply_filters('mj_member_photo_grimlins_history_size', 'medium');

        $member = null;
        if ($is_logged_in && function_exists('mj_member_get_current_member')) {
            $member_candidate = mj_member_get_current_member();
            if ($member_candidate && !empty($member_candidate->id)) {
                $member = $member_candidate;
            }
        }

        $target_member = $member;
        $target_member_id = isset($_POST['targetMemberId']) ? (int) wp_unslash($_POST['targetMemberId']) : 0;

        if ($target_member_id > 0 && $member && $target_member_id !== (int) $member->id) {
            $member_role = isset($member->role) ? (string) $member->role : '';
            $is_staff_selector = MjRoles::isAnimateurOrCoordinateur($member_role);

            $child_target = MjMembers::getById($target_member_id);
            if (!$child_target || empty($child_target->id)) {
                wp_send_json_error(array('message' => __('Membre cible introuvable.', 'mj-member')), 404);
            }

            if ($is_staff_selector) {
                if (!MjRoles::isJeune((string) ($child_target->role ?? ''))) {
                    wp_send_json_error(array('message' => __('Seuls les jeunes peuvent être sélectionnés.', 'mj-member')), 403);
                }
            } else {
                if (!function_exists('mj_member_get_guardian_children')) {
                    wp_send_json_error(array('message' => __('Tu ne peux pas générer un avatar pour ce membre.', 'mj-member')), 403);
                }

                $allowed_children = mj_member_get_guardian_children($member);
                $allowed_target = false;
                if (is_array($allowed_children)) {
                    foreach ($allowed_children as $allowed_child) {
                        if ($allowed_child && is_object($allowed_child) && (int) ($allowed_child->id ?? 0) === $target_member_id) {
                            $allowed_target = true;
                            break;
                        }
                    }
                }

                if (!$allowed_target) {
                    wp_send_json_error(array('message' => __('Ce jeune ne t\'est pas rattaché.', 'mj-member')), 403);
                }
            }

            $target_member = $child_target;
        }

        $target_member_id = $target_member && !empty($target_member->id) ? (int) $target_member->id : 0;
        $member_photo_id = $target_member && !empty($target_member->photo_id) ? (int) $target_member->photo_id : 0;

        if ($enforce_limit && !$is_logged_in) {
            wp_send_json_error(array('message' => __('Connecte-toi pour utiliser cette fonctionnalité.', 'mj-member')), 401);
        }

        if (!mj_member_photo_grimlins_is_enabled()) {
            wp_send_json_error(array('message' => __('La génération est momentanément indisponible.', 'mj-member')), 503);
        }

        if ($is_logged_in && !current_user_can('read')) {
            wp_send_json_error(array('message' => __('Tu ne disposes pas des droits nécessaires.', 'mj-member')), 403);
        }
        $enforce_limit = (bool) apply_filters('mj_member_photo_grimlins_enforce_member_limit', $enforce_limit, $member, get_current_user_id());

        $limit = $enforce_limit
            ? (int) apply_filters('mj_member_photo_grimlins_member_limit', 3, $target_member, get_current_user_id())
            : 0;
        $existing_generations = 0;

        if ($enforce_limit && $limit > 0 && $target_member_id > 0) {
            if ($member && $target_member_id !== (int) $member->id) {
                $existing_generations = mj_member_photo_grimlins_count_member_generations(get_current_user_id(), $target_member_id, $limit);
            } else {
                $existing_generations = mj_member_photo_grimlins_count_user_generations(get_current_user_id(), $limit);
            }

            if ($existing_generations >= $limit) {
                $message = apply_filters(
                    'mj_member_photo_grimlins_member_limit_message',
                    sprintf(
                        __('Tu as déjà généré %d avatars Grimlins. Supprime-en un pour en créer un nouveau.', 'mj-member'),
                        $limit
                    ),
                    $target_member,
                    $existing_generations,
                    $limit
                );

                wp_send_json_error(array('message' => $message, 'limit' => $limit), 429);
            }
        }

        if (!isset($_FILES['source'])) {
            wp_send_json_error(array('message' => __('Aucune photo reçue.', 'mj-member')), 400);
        }

        $maxSize = (int) apply_filters('mj_member_photo_grimlins_max_size', 5 * MB_IN_BYTES);
        $allowedMimes = apply_filters('mj_member_photo_grimlins_allowed_mimes', array('image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'));
        $file = $_FILES['source'];

        if (!empty($file['error'])) {
            wp_send_json_error(array('message' => __('Téléversement interrompu. Vérifie la taille du fichier.', 'mj-member')), 400);
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($maxSize > 0 && $size > $maxSize) {
            wp_send_json_error(array('message' => __('Le fichier dépasse la limite autorisée.', 'mj-member')), 413);
        }

        $type = isset($file['type']) ? (string) $file['type'] : '';
        if ($type === '' || !in_array($type, $allowedMimes, true)) {
            wp_send_json_error(array('message' => __('Ce format de fichier ne peut pas être transformé.', 'mj-member')), 415);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $uploaded = wp_handle_upload($file, array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'heic' => 'image/heic',
                'heif' => 'image/heif',
            ),
        ));

        if (!is_array($uploaded) || !empty($uploaded['error'])) {
            $message = isset($uploaded['error']) ? (string) $uploaded['error'] : __('Échec du téléversement.', 'mj-member');
            wp_send_json_error(array('message' => $message), 500);
        }

        $sourcePath = isset($uploaded['file']) ? $uploaded['file'] : '';
        if ($sourcePath === '' || !file_exists($sourcePath)) {
            wp_send_json_error(array('message' => __('Fichier source introuvable après téléversement.', 'mj-member')), 500);
        }

        $client = new MjOpenAIClient();
        $prompt = mj_member_photo_grimlins_get_prompt();
        $result = $client->generateGrimlinsImage($sourcePath, array('prompt' => $prompt));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        $storageDir = apply_filters('mj_member_photo_grimlins_storage_dir', trailingslashit(Config::path()) . 'user-grimlins');
        $storageUrl = apply_filters('mj_member_photo_grimlins_storage_url', trailingslashit(Config::url()) . 'user-grimlins/');

        $timestamp = gmdate('Ymd-His');
        $userId = get_current_user_id();
        $sessionSlug = sanitize_file_name(apply_filters(
            'mj_member_photo_grimlins_session_slug',
            'grimlins-' . $timestamp . '-' . $userId . '-' . wp_generate_uuid4(),
            $timestamp,
            $userId
        ));

        $sessionDir = apply_filters('mj_member_photo_grimlins_session_dir', trailingslashit($storageDir) . $sessionSlug, $sessionSlug, $timestamp, $userId);
        $sessionUrl = apply_filters('mj_member_photo_grimlins_session_url', trailingslashit($storageUrl) . $sessionSlug . '/', $sessionSlug, $timestamp, $userId);

        if (!wp_mkdir_p($storageDir) || !wp_mkdir_p($sessionDir)) {
            wp_send_json_error(array('message' => __('Impossible de préparer le dossier de stockage.', 'mj-member')), 500);
        }

        $sourceInfo = pathinfo($sourcePath);
        $sourceExtension = isset($sourceInfo['extension']) ? strtolower($sourceInfo['extension']) : 'jpg';
        $sourceExtension = $sourceExtension !== '' ? $sourceExtension : 'jpg';
        $sourceFilename = sanitize_file_name('photo-original.' . $sourceExtension);
        $sourceDestPath = trailingslashit($sessionDir) . $sourceFilename;

        if (!copy($sourcePath, $sourceDestPath)) {
            wp_send_json_error(array('message' => __('Impossible de conserver la photo envoyée.', 'mj-member')), 500);
        }

        @chmod($sourceDestPath, 0644);

        // Supprime la photo temporaire WordPress.
        if (file_exists($sourcePath)) {
            wp_delete_file($sourcePath);
        }

        $base64 = isset($result['base64']) ? (string) $result['base64'] : '';
        if ($base64 === '') {
            wp_send_json_error(array('message' => __('Image générée invalide.', 'mj-member')), 500);
        }

        $binary = base64_decode($base64);
        if ($binary === false) {
            wp_send_json_error(array('message' => __('Impossible de décoder l’image générée.', 'mj-member')), 500);
        }

        $resultFilename = sanitize_file_name('photo-grimlins.png');
        $resultPath = trailingslashit($sessionDir) . $resultFilename;

        $written = file_put_contents($resultPath, $binary);
        if ($written === false) {
            wp_send_json_error(array('message' => __('Impossible de sauvegarder le rendu.', 'mj-member')), 500);
        }

        @chmod($resultPath, 0644);

        $filetype = wp_check_filetype($resultPath, null);
        if (!is_array($filetype) || empty($filetype['type'])) {
            $filetype = array('type' => 'image/png');
        }

        $imageUrl = trailingslashit($sessionUrl) . rawurlencode($resultFilename);

        $attachment = array(
            'post_title' => sprintf(__('Avatar Grimlins de %s', 'mj-member'), wp_get_current_user()->display_name ?: __('profil MJ', 'mj-member')),
            'post_mime_type' => $filetype['type'],
            'post_status' => 'private',
            'post_author' => get_current_user_id(),
            'guid' => $imageUrl,
        );

        $attachmentId = wp_insert_attachment($attachment, $resultPath);
        if (!is_wp_error($attachmentId) && $attachmentId > 0) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $upload_dir_filter = static function ($dirs) use ($storageDir, $storageUrl, $sessionDir, $sessionUrl, $sessionSlug) {
                $dirs['path'] = untrailingslashit($sessionDir);
                $dirs['basedir'] = untrailingslashit($storageDir);
                $dirs['subdir'] = '/' . ltrim($sessionSlug, '/');
                $dirs['url'] = untrailingslashit($sessionUrl);
                $dirs['baseurl'] = untrailingslashit($storageUrl);
                $dirs['error'] = false;

                return $dirs;
            };

            add_filter('upload_dir', $upload_dir_filter, 20);

            $metadata = wp_generate_attachment_metadata($attachmentId, $resultPath);

            remove_filter('upload_dir', $upload_dir_filter, 20);

            if (!is_wp_error($metadata) && !empty($metadata)) {
                $metadata['file'] = ltrim($sessionSlug . '/' . $resultFilename, '/');
                wp_update_attachment_metadata($attachmentId, $metadata);
            }

            update_post_meta($attachmentId, '_wp_attached_file', ltrim($sessionSlug . '/' . $resultFilename, '/'));
            update_post_meta($attachmentId, '_mj_member_photo_grimlins', 1);
            update_post_meta($attachmentId, '_mj_member_photo_grimlins_original', $sourceFilename);
            update_post_meta($attachmentId, '_mj_member_photo_grimlins_session', $sessionSlug);
            if ($target_member_id > 0) {
                update_post_meta($attachmentId, '_mj_member_photo_grimlins_member', $target_member_id);
                update_post_meta($attachmentId, '_mj_member_photo_grimlins_member_id', $target_member_id);
            }
        }

        $downloadName = 'grimlins-' . ($userId > 0 ? $userId : 'guest') . '.png';

        $history = array();
        $history_count = 0;
        $history_limit = $limit;

        if ($is_logged_in) {
            $history_limit = $limit;
            $history = mj_member_photo_grimlins_list_member_generations(
                $userId,
                array(
                    'limit' => $history_limit > 0 ? $history_limit : 20,
                    'size' => $history_size,
                    'current_attachment_id' => $member_photo_id,
                    'user_id' => $userId,
                    'member_id' => $target_member_id,
                )
            );
            $history_count = count($history);
        }

        $history_item = null;
        if (!is_wp_error($attachmentId) && $attachmentId > 0) {
            $history_item = mj_member_photo_grimlins_format_generation_item(
                (int) $attachmentId,
                array(
                    'size' => $history_size,
                    'current_attachment_id' => $member_photo_id,
                    'user_id' => $userId,
                    'member_id' => $target_member_id,
                )
            );
        }

        $payload = array(
            'imageUrl' => $imageUrl,
            'downloadName' => $downloadName,
            'attachmentId' => !is_wp_error($attachmentId) ? (int) $attachmentId : 0,
            'model' => isset($result['model']) ? (string) $result['model'] : '',
            'prompt' => $prompt,
            'history' => $history,
            'historyItem' => $history_item,
            'historyCount' => $history_count,
            'targetMemberId' => $target_member_id,
            'memberLimit' => $limit,
            'limitReached' => $limit > 0 && $history_count >= $limit,
        );

        wp_send_json_success($payload);
    }

}

if (!function_exists('mj_member_photo_grimlins_ajax_apply_avatar')) {
    function mj_member_photo_grimlins_ajax_apply_avatar(): void
    {
        if (!check_ajax_referer('mj_member_photo_grimlins_apply_avatar', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Requête invalide. Recharge la page puis réessaie.', 'mj-member')), 403);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Connecte-toi pour utiliser cette fonctionnalité.', 'mj-member')), 401);
        }

        if (!function_exists('mj_member_get_current_member')) {
            wp_send_json_error(array('message' => __('Profil membre introuvable.', 'mj-member')), 400);
        }

        $member = mj_member_get_current_member();
        if (!$member || empty($member->id)) {
            wp_send_json_error(array('message' => __('Profil membre introuvable.', 'mj-member')), 400);
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Tu ne disposes pas des droits nécessaires.', 'mj-member')), 403);
        }

        if (!class_exists(MjMembers::class)) {
            wp_send_json_error(array('message' => __('Gestion des membres indisponible.', 'mj-member')), 500);
        }

        $attachment_id = isset($_POST['attachmentId']) ? (int) $_POST['attachmentId'] : 0;
        if ($attachment_id <= 0) {
            wp_send_json_error(array('message' => __('Sélectionne un avatar à appliquer.', 'mj-member')), 400);
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_status === 'trash') {
            wp_send_json_error(array('message' => __('Image introuvable.', 'mj-member')), 404);
        }

        $current_user_id = get_current_user_id();
        $has_grimlins_flag = (bool) get_post_meta($attachment_id, '_mj_member_photo_grimlins', true);
        $session_slug = (string) get_post_meta($attachment_id, '_mj_member_photo_grimlins_session', true);
        $owns_attachment = ((int) $attachment->post_author === $current_user_id);
        $owns_session = $session_slug !== '' && strpos($session_slug, '-' . $current_user_id . '-') !== false;

        if (!$has_grimlins_flag || (!$owns_attachment && !$owns_session)) {
            wp_send_json_error(array('message' => __('Cet avatar ne peut pas être utilisé.', 'mj-member')), 403);
        }

        $target_member_id = isset($_POST['targetMemberId']) ? (int) $_POST['targetMemberId'] : 0;
        $member_to_update = $member;

        if ($target_member_id > 0 && $target_member_id !== (int) $member->id) {
            $member_role = isset($member->role) ? (string) $member->role : '';
            $is_staff_selector = MjRoles::isAnimateurOrCoordinateur($member_role);

            $target_member = MjMembers::getById($target_member_id);
            if (!$target_member || empty($target_member->id)) {
                wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')), 404);
            }

            if ($is_staff_selector) {
                if (!MjRoles::isJeune((string) ($target_member->role ?? ''))) {
                    wp_send_json_error(array('message' => __('Seuls les jeunes peuvent être sélectionnés.', 'mj-member')), 403);
                }
            } else {
                if (!function_exists('mj_member_get_guardian_children')) {
                    wp_send_json_error(array('message' => __('Tu ne peux pas modifier cet avatar.', 'mj-member')), 403);
                }

                $allowed_children = mj_member_get_guardian_children($member);
                $can_update_child = false;
                if (is_array($allowed_children)) {
                    foreach ($allowed_children as $allowed_child) {
                        if ($allowed_child && is_object($allowed_child) && (int) ($allowed_child->id ?? 0) === $target_member_id) {
                            $can_update_child = true;
                            break;
                        }
                    }
                }

                if (!$can_update_child) {
                    wp_send_json_error(array('message' => __('Ce membre ne t\'est pas rattaché.', 'mj-member')), 403);
                }
            }

            $member_to_update = $target_member;
        }

        $update = MjMembers::update((int) $member_to_update->id, array('photo_id' => $attachment_id));
        if (is_wp_error($update)) {
            wp_send_json_error(array('message' => $update->get_error_message()), 500);
        }

        update_post_meta($attachment_id, '_mj_member_photo_grimlins_member', (int) $member_to_update->id);

        do_action('mj_member_grimlins_avatar_applied', $member_to_update, $attachment_id);

        wp_send_json_success(array(
            'message' => __('Avatar mis à jour pour ton profil.', 'mj-member'),
            'attachmentId' => $attachment_id,
            'imageUrl' => wp_get_attachment_url($attachment_id),
            'nonce' => wp_create_nonce('mj_member_photo_grimlins_apply_avatar'),
        ));
    }

}

if (!function_exists('mj_member_photo_grimlins_ajax_delete_avatar')) {
    function mj_member_photo_grimlins_ajax_delete_avatar(): void
    {
        if (!check_ajax_referer('mj_member_photo_grimlins_delete_avatar', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Requête invalide. Recharge la page puis réessaie.', 'mj-member')), 403);
        }

        if (!is_user_logged_in() || !function_exists('mj_member_get_current_member')) {
            wp_send_json_error(array('message' => __('Connecte-toi pour utiliser cette fonctionnalité.', 'mj-member')), 401);
        }

        $member = mj_member_get_current_member();
        if (!$member || empty($member->id)) {
            wp_send_json_error(array('message' => __('Profil membre introuvable.', 'mj-member')), 400);
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Tu ne disposes pas des droits nécessaires.', 'mj-member')), 403);
        }

        if (!MjRoles::isAnimateurOrCoordinateur((string) ($member->role ?? ''))) {
            wp_send_json_error(array('message' => __('Seuls les animateurs peuvent supprimer des avatars.', 'mj-member')), 403);
        }

        $attachment_id = isset($_POST['attachmentId']) ? (int) wp_unslash($_POST['attachmentId']) : 0;
        if ($attachment_id <= 0) {
            wp_send_json_error(array('message' => __('Avatar introuvable.', 'mj-member')), 400);
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_status === 'trash') {
            wp_send_json_error(array('message' => __('Avatar introuvable.', 'mj-member')), 404);
        }

        $is_grimlins = (bool) get_post_meta($attachment_id, '_mj_member_photo_grimlins', true);
        if (!$is_grimlins) {
            wp_send_json_error(array('message' => __('Cet avatar ne peut pas être supprimé.', 'mj-member')), 403);
        }

        $current_user_id = get_current_user_id();
        $session_slug = (string) get_post_meta($attachment_id, '_mj_member_photo_grimlins_session', true);
        $owns_attachment = ((int) $attachment->post_author === $current_user_id);
        $owns_session = $session_slug !== '' && strpos($session_slug, '-' . $current_user_id . '-') !== false;
        if (!$owns_attachment && !$owns_session) {
            wp_send_json_error(array('message' => __('Tu ne peux pas supprimer cet avatar.', 'mj-member')), 403);
        }

        if (class_exists(MjMembers::class)) {
            $linked_member_id = (int) get_post_meta($attachment_id, '_mj_member_photo_grimlins_member', true);
            if ($linked_member_id > 0) {
                $linked_member = MjMembers::getById($linked_member_id);
                if ($linked_member && (int) ($linked_member->photo_id ?? 0) === $attachment_id) {
                    MjMembers::update($linked_member_id, array('photo_id' => 0));
                }
            }
        }

        $deleted = wp_delete_attachment($attachment_id, true);
        if (!$deleted) {
            wp_send_json_error(array('message' => __('Impossible de supprimer cet avatar.', 'mj-member')), 500);
        }

        wp_send_json_success(array(
            'message' => __('Avatar supprimé.', 'mj-member'),
            'attachmentId' => $attachment_id,
            'nonce' => wp_create_nonce('mj_member_photo_grimlins_delete_avatar'),
        ));
    }

}

if (!function_exists('mj_member_photo_grimlins_ajax_search_young')) {
    function mj_member_photo_grimlins_ajax_search_young(): void
    {
        if (!check_ajax_referer('mj_member_photo_grimlins_search_young', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Requête invalide. Recharge la page puis réessaie.', 'mj-member')), 403);
        }

        if (!is_user_logged_in() || !function_exists('mj_member_get_current_member')) {
            wp_send_json_error(array('message' => __('Connecte-toi pour utiliser cette fonctionnalité.', 'mj-member')), 401);
        }

        $member = mj_member_get_current_member();
        if (!$member || empty($member->id)) {
            wp_send_json_error(array('message' => __('Profil membre introuvable.', 'mj-member')), 400);
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Tu ne disposes pas des droits nécessaires.', 'mj-member')), 403);
        }

        $member_role = isset($member->role) ? (string) $member->role : '';
        if (!MjRoles::isAnimateurOrCoordinateur($member_role)) {
            wp_send_json_error(array('message' => __('Tu ne peux pas rechercher un jeune ici.', 'mj-member')), 403);
        }

        $search = isset($_POST['search']) ? sanitize_text_field((string) wp_unslash($_POST['search'])) : '';
        $limit = isset($_POST['limit']) ? (int) wp_unslash($_POST['limit']) : 20;
        $limit = max(5, min(40, $limit));

        $rows = MjMembers::get_all(array(
            'search' => $search,
            'filters' => array(
                'role' => MjRoles::JEUNE,
                'status' => MjMembers::STATUS_ACTIVE,
            ),
            'limit' => $limit,
            'offset' => 0,
            'orderby' => 'last_name',
            'order' => 'ASC',
        ));

        $items = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!$row || !is_object($row) || empty($row->id)) {
                    continue;
                }

                $photo_id = max((int) ($row->photo_id ?? 0), (int) ($row->avatar_id ?? 0));
                $photo_url = '';
                if ($photo_id > 0) {
                    $photo_url = (string) wp_get_attachment_image_url($photo_id, 'thumbnail');
                    if ($photo_url === '') {
                        $photo_url = (string) wp_get_attachment_url($photo_id);
                    }
                }
                if ($photo_url === '' && !empty($row->email)) {
                    $photo_url = (string) get_avatar_url((string) $row->email, array('size' => 64));
                }

                $full_name = trim(sprintf('%s %s', (string) ($row->first_name ?? ''), (string) ($row->last_name ?? '')));
                if ($full_name === '') {
                    $full_name = sprintf(__('Jeune #%d', 'mj-member'), (int) $row->id);
                }

                $initials = strtoupper(substr((string) ($row->first_name ?? ''), 0, 1));
                if ($initials === '') {
                    $initials = strtoupper(substr((string) ($row->last_name ?? ''), 0, 1));
                }
                if ($initials === '') {
                    $initials = 'J';
                }

                $items[] = array(
                    'id' => (int) $row->id,
                    'label' => $full_name,
                    'photoId' => $photo_id,
                    'photoUrl' => $photo_url,
                    'initials' => $initials,
                );
            }
        }

        wp_send_json_success(array('items' => $items));
    }

}

if (!function_exists('mj_member_photo_grimlins_get_storage_context')) {
    /**
     * @return array{dir:string,url:string}
     */
    function mj_member_photo_grimlins_get_storage_context(): array
    {
        $storageDir = apply_filters('mj_member_photo_grimlins_storage_dir', trailingslashit(Config::path()) . 'user-grimlins');
        $storageUrl = apply_filters('mj_member_photo_grimlins_storage_url', trailingslashit(Config::url()) . 'user-grimlins/');

        return array(
            'dir' => trailingslashit($storageDir),
            'url' => trailingslashit($storageUrl),
        );
    }
}

if (!function_exists('mj_member_photo_grimlins_build_file_url')) {
    function mj_member_photo_grimlins_build_file_url($attachment_id, string $file): string
    {
        if ($file === '') {
            return '';
        }

        $context = mj_member_photo_grimlins_get_storage_context();
        $relative = ltrim($file, '/');

        return trailingslashit($context['url']) . $relative;
    }
}

if (!function_exists('mj_member_photo_grimlins_override_attachment_url')) {
    function mj_member_photo_grimlins_override_attachment_url($url, $post_id)
    {
        if ((int) $post_id <= 0) {
            return $url;
        }

        if (!get_post_meta($post_id, '_mj_member_photo_grimlins', true)) {
            return $url;
        }

        $file = get_post_meta($post_id, '_wp_attached_file', true);
        if (!is_string($file) || $file === '') {
            return $url;
        }

        $override = mj_member_photo_grimlins_build_file_url($post_id, $file);

        return $override !== '' ? $override : $url;
    }

}

if (!function_exists('mj_member_photo_grimlins_override_image_src')) {
    function mj_member_photo_grimlins_override_image_src($image, $attachment_id, $size)
    {
        if (!is_array($image) || empty($image[0])) {
            return $image;
        }

        if (!get_post_meta($attachment_id, '_mj_member_photo_grimlins', true)) {
            return $image;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!is_array($metadata) || empty($metadata['file'])) {
            return $image;
        }

        $baseFile = $metadata['file'];
        $dir = trim(dirname($baseFile), './');
        $filename = basename($baseFile);

        if ($size && isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            if (is_string($size) && isset($metadata['sizes'][$size]) && !empty($metadata['sizes'][$size]['file'])) {
                $filename = $metadata['sizes'][$size]['file'];
            } elseif (is_array($size) && count($size) === 2) {
                foreach ($metadata['sizes'] as $data) {
                    if (!is_array($data) || empty($data['file']) || !isset($data['width'], $data['height'])) {
                        continue;
                    }
                    if ((int) $data['width'] === (int) $size[0] && (int) $data['height'] === (int) $size[1]) {
                        $filename = $data['file'];
                        break;
                    }
                }
            }
        }

        $targetPath = $dir !== '' && $dir !== '.' ? $dir . '/' . $filename : $filename;
        $override = mj_member_photo_grimlins_build_file_url($attachment_id, $targetPath);
        if ($override !== '') {
            $image[0] = $override;
        }

        return $image;
    }

}

if (!function_exists('mj_member_photo_grimlins_override_srcset')) {
    function mj_member_photo_grimlins_override_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!get_post_meta($attachment_id, '_mj_member_photo_grimlins', true)) {
            return $sources;
        }

        if (!is_array($sources)) {
            return $sources;
        }

        if (!is_array($image_meta) || empty($image_meta['file'])) {
            return $sources;
        }

        $base_dir = trim(dirname($image_meta['file']), './');

        foreach ($sources as $descriptor => $source) {
            if (!is_array($source)) {
                continue;
            }
            $file = '';

            if (isset($source['descriptor']) && is_string($source['descriptor']) && isset($image_meta['sizes'][$source['descriptor']]['file'])) {
                $file = $image_meta['sizes'][$source['descriptor']]['file'];
            } elseif (isset($source['value']) && is_string($source['value']) && isset($image_meta['sizes'][$source['value']]['file'])) {
                $file = $image_meta['sizes'][$source['value']]['file'];
            }

            if ($file === '' && isset($image_meta['sizes']) && is_array($image_meta['sizes'])) {
                foreach ($image_meta['sizes'] as $data) {
                    if (!is_array($data) || empty($data['file']) || !isset($data['width'])) {
                        continue;
                    }
                    if (isset($source['value']) && is_numeric($source['value']) && (int) $data['width'] === (int) $source['value']) {
                        $file = $data['file'];
                        break;
                    }
                }
            }

            if ($file === '') {
                $file = basename(isset($source['url']) ? $source['url'] : $image_src);
            }

            $relative = $base_dir !== '' ? $base_dir . '/' . ltrim($file, '/') : $file;
            $override = mj_member_photo_grimlins_build_file_url($attachment_id, $relative);
            if ($override !== '' && isset($source['url'])) {
                $sources[$descriptor]['url'] = $override;
            }
        }

        return $sources;
    }

}

// Localization is triggered explicitly when the asset package is required.

// Localization is triggered explicitly when the asset package is required.
} // end namespace
