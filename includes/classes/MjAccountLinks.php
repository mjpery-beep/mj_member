<?php

namespace Mj\Member\Classes;

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralise la récupération et la mise en forme des liens "Mon compte".
 */
class MjAccountLinks {
    /**
     * Retourne la configuration par défaut des liens "Mon compte".
     */
    public static function getDefaultSettings(): array {
        $default_account_section = array('section' => 'profile');
        $contact_capability = Config::contactCapability() ?: 'mj_manage_contact_messages';

        $default_links = array(
            'profile' => array(
                'label' => __('Mes données personnelles', 'mj-member'),
                'slug' => 'mon-compte',
                'query' => $default_account_section,
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'photos' => array(
                'label' => __('Mes photos', 'mj-member'),
                'slug' => 'mes-photos',
                'query' => array('section' => 'photos'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'photo_grimlins' => array(
                'label' => __('Créer mon avatar Grimlins', 'mj-member'),
                'slug' => 'avatar-grimlins',
                'query' => array(),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'registrations' => array(
                'label' => __('Mes inscriptions', 'mj-member'),
                'slug' => 'inscriptions',
                'query' => array('section' => 'registrations'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'animateur_events' => array(
                'label' => __('Gestion des événements', 'mj-member'),
                'slug' => 'animateurs',
                'query' => array('section' => 'animateur_events'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => MjRoles::ANIMATEUR,
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'contact_messages' => array(
                'label' => __('Messages', 'mj-member'),
                'slug' => 'messages',
                'query' => array('section' => 'contact_messages'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'requires_capability' => $contact_capability,
                'icon_id' => 0,
            ),
            'hours_encode' => array(
                'label' => __('Encodage des Heures de Travail', 'mj-member'),
                'slug' => 'encodage-des-heures-de-travail',
                'query' => array(),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'hours_team',
                'editable_label' => true,
                'type' => 'standard',
                'requires_capability' => Config::hoursCapability(),
                'icon_id' => 0,
            ),
            'todos' => array(
                'label' => __('Mes tâches', 'mj-member'),
                'slug' => 'mes-taches',
                'query' => array('section' => 'todos'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'hours_team',
                'editable_label' => true,
                'type' => 'standard',
                'requires_capability' => Config::todosCapability(),
                'icon_id' => 0,
            ),
            'idea_box' => array(
                'label' => __('Boîte à idées', 'mj-member'),
                'slug' => 'boite-a-idees',
                'query' => array('section' => 'idea_box'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'payments' => array(
                'label' => __('Mes paiements', 'mj-member'),
                'slug' => 'paiements',
                'query' => array('section' => 'payments'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'documents' => array(
                'label' => __('Mes documents', 'mj-member'),
                'slug' => 'documents',
                'query' => array('section' => 'documents'),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => true,
                'type' => 'standard',
                'icon_id' => 0,
            ),
            'logout' => array(
                'label' => __('Déconnexion', 'mj-member'),
                'slug' => '',
                'query' => array(),
                'enabled' => true,
                'page_id' => 0,
                'visibility' => 'all',
                'editable_label' => false,
                'type' => 'logout',
                'icon_id' => 0,
            ),
        );

        return apply_filters('mj_member_login_component_default_account_links', $default_links);
    }

    /**
     * Retourne la configuration active en tenant compte des réglages en base.
     */
    public static function getSettings(): array {
        $defaults = self::getDefaultSettings();
        $saved = get_option('mj_account_links_settings', array());

        if (!is_array($saved)) {
            $saved = array();
        }

        $position = 0;
        foreach ($defaults as $key => $config) {
            $saved_row = isset($saved[$key]) && is_array($saved[$key]) ? $saved[$key] : array();

            $defaults[$key]['enabled'] = isset($saved_row['enabled']) ? (bool) $saved_row['enabled'] : (!empty($config['enabled']));

            if (!empty($config['editable_label'])) {
                $label = isset($saved_row['label']) ? sanitize_text_field($saved_row['label']) : '';
                if ($label !== '') {
                    $defaults[$key]['label'] = $label;
                }
            }

            // Récupérer la page cible : d'abord par ID, sinon par slug (portabilité entre sites)
            $page_id = isset($saved_row['page_id']) ? (int) $saved_row['page_id'] : 0;
            $page_slug = isset($saved_row['page_slug']) ? sanitize_title($saved_row['page_slug']) : '';

            // Vérifier si la page existe par ID
            if ($page_id > 0) {
                $page_exists = get_post($page_id);
                if (!$page_exists || $page_exists->post_type !== 'page') {
                    $page_id = 0; // Page introuvable par ID
                }
            }

            // Si pas de page trouvée par ID mais qu'on a un slug, chercher par slug
            if ($page_id === 0 && $page_slug !== '') {
                $page_by_slug = get_page_by_path($page_slug);
                if ($page_by_slug && $page_by_slug->post_type === 'page') {
                    $page_id = (int) $page_by_slug->ID;
                }
            }

            $defaults[$key]['page_id'] = $page_id;
            $defaults[$key]['page_slug'] = $page_slug;

            $icon_id = isset($saved_row['icon_id']) ? (int) $saved_row['icon_id'] : 0;
            $defaults[$key]['icon_id'] = $icon_id > 0 ? $icon_id : 0;

            // Récupérer la position si sauvegardée, sinon utiliser l'ordre par défaut
            $defaults[$key]['position'] = isset($saved_row['position']) ? (int) $saved_row['position'] : $position;
            $position++;
        }

        // Trier par position
        uasort($defaults, static function ($a, $b) {
            return ($a['position'] ?? 999) <=> ($b['position'] ?? 999);
        });

        return $defaults;
    }

    /**
     * Construit la liste des cibles à suivre pour compter les messages non lus.
     */
    public static function buildUnreadTargets(int $memberId, string $memberRole): array {
        if (!class_exists('MjContactMessages')) {
            return array();
        }

        $memberId = (int) $memberId;
        $memberRole = sanitize_key($memberRole);

        if ($memberId <= 0) {
            return array();
        }

        $targets = array();
        $append = static function (string $type, $reference = null) use (&$targets) {
            $key = $type . '|' . ($reference === null ? 'null' : (string) (int) $reference);
            if (isset($targets[$key])) {
                return;
            }

            $entry = array('type' => $type);
            if ($reference !== null) {
                $entry['reference'] = (int) $reference;
            }
            $targets[$key] = $entry;
        };

        // Utiliser MjRoles pour les vérifications
        $isAnimateur = MjRoles::isAnimateur($memberRole);
        $isCoordinateur = MjRoles::isCoordinateur($memberRole);

        if ($isAnimateur || $isCoordinateur) {
            $append(\MjContactMessages::TARGET_ANIMATEUR, $memberId);
            $append(\MjContactMessages::TARGET_ANIMATEUR, 0);
        }

        if ($isCoordinateur) {
            $append(\MjContactMessages::TARGET_COORDINATEUR, $memberId);
            $append(\MjContactMessages::TARGET_COORDINATEUR, 0);
        }

        return array_values($targets);
    }

    /**
     * Calcule le nombre de messages non lus.
     */
    public static function getUnreadCount(int $userId = 0, array $overrides = array()): int {
        if (!class_exists('MjContactMessages')) {
            return 0;
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            $userId = get_current_user_id();
        }

        if ($userId <= 0) {
            return 0;
        }

        $args = wp_parse_args($overrides, array(
            'include_all_targets' => true,
            'extra_targets' => array(),
            'skip_capability_check' => false,
        ));

        $contactCapability = Config::contactCapability();
        if (!$args['skip_capability_check'] && $contactCapability !== '') {
            if (!user_can($userId, $contactCapability)) {
                return 0;
            }
        }

        $count = (int) \MjContactMessages::count_unread_for_user($userId, $args);

        /**
         * Filtre le décompte des messages non lus affiché dans les liens de compte.
         */
        return (int) apply_filters('mj_member_account_links_unread_total', $count, $userId, $args);
    }

    /**
     * Retourne la liste des liens formatés pour l'affichage.
     */
    public static function getLinks(string $accountBase, array $args = array()): array {
        $accountBase = esc_url_raw($accountBase);
        $logoutRedirect = apply_filters('mj_member_login_component_logout_redirect', home_url('/'), $args);

        $currentUser = is_user_logged_in() ? wp_get_current_user() : null;
        $currentMember = null;
        $memberRole = '';
        $isAnimateur = false;
        $isCoordinateur = false;
        $isBenevole = false;

        if ($currentUser instanceof WP_User && function_exists('mj_member_get_member_for_user')) {
            $currentMember = mj_member_get_member_for_user($currentUser->ID);
        }

        if (!$currentMember && function_exists('mj_member_get_current_member')) {
            $currentMember = mj_member_get_current_member();
        }

        $currentMemberId = 0;
        if ($currentMember && isset($currentMember->id)) {
            $currentMemberId = (int) $currentMember->id;
        }

        // Utiliser MjRoles pour les constantes de rôles
        $animateurRole = MjRoles::ANIMATEUR;
        $coordinateurRole = MjRoles::COORDINATEUR;
        $benevoleRole = MjRoles::BENEVOLE;
        $youthRole = MjRoles::JEUNE;

        if ($currentMember && isset($currentMember->role)) {
            $memberRole = sanitize_key((string) $currentMember->role);
            $isAnimateur = MjRoles::isAnimateur($memberRole);
            $isCoordinateur = MjRoles::isCoordinateur($memberRole);
            $isBenevole = MjRoles::isBenevole($memberRole);
        }

        $isYoungMember = MjRoles::isJeune($memberRole);

        $unreadExtraTargets = self::buildUnreadTargets($currentMemberId, $memberRole);

        $currentUserEmail = ($currentUser instanceof WP_User && !empty($currentUser->user_email))
            ? sanitize_email($currentUser->user_email)
            : '';
        $allowContactOwnerView = $currentMemberId > 0 || $currentUserEmail !== '';

        $configuredLinks = self::getSettings();
        $previewMode = !empty($args['preview_mode']);
        if (!$previewMode && function_exists('mj_member_login_component_is_preview_mode')) {
            $previewMode = mj_member_login_component_is_preview_mode();
        }

        $contactCapability = Config::contactCapability();
        $unreadContactCount = array_key_exists('unread_contact_count', $args) ? (int) $args['unread_contact_count'] : null;

        if ($unreadContactCount === null) {
            $unreadContactCount = self::computeUnreadCount(
                $currentUser,
                $contactCapability,
                $allowContactOwnerView,
                $previewMode,
                $currentMemberId,
                $currentUserEmail,
                $unreadExtraTargets,
                $args
            );
        }

        $unreadContactCount = max(0, (int) $unreadContactCount);

        $links = array();
        foreach ($configuredLinks as $key => $config) {
            $enabled = isset($config['enabled']) ? (bool) $config['enabled'] : true;
            if (!$enabled) {
                continue;
            }

            $requiredCapability = isset($config['requires_capability']) ? (string) $config['requires_capability'] : '';
            if ($requiredCapability !== '') {
                $hasCapability = current_user_can($requiredCapability);
                $ownerOverride = ($key === 'contact_messages' && $allowContactOwnerView);

                if (!$previewMode && !$hasCapability && !$ownerOverride) {
                    continue;
                }
            }

            $visibility = isset($config['visibility']) ? $config['visibility'] : 'all';
            // Vérifier la visibilité basée sur les rôles
            if ($visibility === MjRoles::ANIMATEUR && !$isAnimateur) {
                continue;
            }

            if ($visibility === 'hours_team' && !$isAnimateur && !$isCoordinateur && !$isBenevole) {
                continue;
            }

            if ($key === 'registrations' && !$previewMode && !$isYoungMember) {
                continue;
            }

            $label = isset($config['label']) ? trim((string) $config['label']) : '';
            if ($label === '') {
                continue;
            }

            $type = isset($config['type']) ? $config['type'] : 'standard';
            $icon_id = isset($config['icon_id']) ? (int) $config['icon_id'] : 0;
            $icon_payload = array();
            if ($icon_id > 0 && function_exists('mj_member_account_menu_build_icon_payload_from_attachment')) {
                $icon_payload = \mj_member_account_menu_build_icon_payload_from_attachment($icon_id);
            }

            if ($type === 'logout') {
                $links[] = array(
                    'key' => sanitize_key($key),
                    'label' => $label,
                    'url' => esc_url_raw(wp_logout_url($logoutRedirect)),
                    'is_logout' => true,
                    'badge' => 0,
                    'icon_id' => $icon_id,
                    'icon' => $icon_payload,
                );
                continue;
            }

            $pageId = isset($config['page_id']) ? (int) $config['page_id'] : 0;
            $query = isset($config['query']) && is_array($config['query']) ? $config['query'] : array();

            $normalizedQuery = array();
            foreach ($query as $queryKey => $queryValue) {
                $normalizedKey = sanitize_key((string) $queryKey);
                if ($normalizedKey === '' || (!is_string($queryValue) && !is_numeric($queryValue))) {
                    continue;
                }
                $normalizedQuery[$normalizedKey] = is_string($queryValue)
                    ? sanitize_text_field($queryValue)
                    : $queryValue;
            }

            if (!isset($normalizedQuery['section'])) {
                $sectionKey = sanitize_key($key);
                if ($sectionKey !== '') {
                    $normalizedQuery['section'] = $sectionKey;
                }
            }

            $url = '';
            if ($pageId > 0) {
                $permalink = get_permalink($pageId);
                if (!empty($permalink)) {
                    $url = esc_url_raw(add_query_arg($normalizedQuery, $permalink));
                }
            }

            if ($url === '') {
                $slug = isset($config['slug']) ? (string) $config['slug'] : '';
                $url = self::resolveAccountLink($slug, $accountBase, $normalizedQuery);
            }

            $badge = 0;
            if ($key === 'contact_messages') {
                $badge = $unreadContactCount;
                $cleanLabel = trim(preg_replace('/\s*\(\d+\+?\)\s*$/', '', $label));
                if ($cleanLabel !== '') {
                    $label = $cleanLabel;
                }
            }

            $links[] = array(
                'key' => sanitize_key($key),
                'label' => $label,
                'url' => $url,
                'is_logout' => false,
                'badge' => $badge,
                'icon_id' => $icon_id,
                'icon' => $icon_payload,
            );
        }

        $links = apply_filters('mj_member_login_component_account_links', $links, $currentUser, $args, $accountBase);

        $sanitized = array();
        foreach ($links as $link) {
            if (empty($link['label']) || empty($link['url'])) {
                continue;
            }
            $icon_payload = array();
            if (function_exists('mj_member_account_menu_sanitize_icon_payload')) {
                $icon_payload = \mj_member_account_menu_sanitize_icon_payload(isset($link['icon']) ? $link['icon'] : array());
            }
            $sanitized[] = array(
                'key' => isset($link['key']) ? sanitize_key($link['key']) : '',
                'label' => wp_strip_all_tags($link['label']),
                'url' => esc_url($link['url']),
                'is_logout' => !empty($link['is_logout']),
                'badge' => isset($link['badge']) ? (int) $link['badge'] : 0,
                'icon_id' => isset($link['icon_id']) ? (int) $link['icon_id'] : 0,
                'icon' => $icon_payload,
            );
        }

        if (empty($sanitized)) {
            $sanitized[] = array(
                'key' => 'account',
                'label' => __('Mon compte', 'mj-member'),
                'url' => esc_url($accountBase),
                'is_logout' => false,
                'badge' => 0,
                'icon_id' => 0,
                'icon' => function_exists('mj_member_account_menu_sanitize_icon_payload')
                    ? \mj_member_account_menu_sanitize_icon_payload(array())
                    : array('id' => 0, 'url' => '', 'preview_url' => '', 'alt' => '', 'html' => '', 'type' => ''),
            );
        }

        return $sanitized;
    }

    private static function computeUnreadCount(
        ?WP_User $currentUser,
        string $contactCapability,
        bool $allowContactOwnerView,
        bool $previewMode,
        int $currentMemberId,
        string $currentUserEmail,
        array $extraTargets,
        array $args
    ): int {
        if ($currentUser instanceof WP_User) {
            $userHasCapability = ($contactCapability === '' || current_user_can($contactCapability));
            $shouldAllowOwner = $allowContactOwnerView && !$userHasCapability && $contactCapability !== '';

            if ($userHasCapability || $previewMode || $shouldAllowOwner) {
                $helperArgs = array();

                if ($previewMode || $shouldAllowOwner) {
                    $helperArgs['skip_capability_check'] = true;
                }

                if ($shouldAllowOwner) {
                    if ($currentMemberId > 0) {
                        $helperArgs['member_id'] = $currentMemberId;
                    }
                    if ($currentUserEmail !== '') {
                        $helperArgs['sender_email'] = $currentUserEmail;
                    }
                }

                if (!empty($extraTargets)) {
                    $helperArgs['extra_targets'] = $extraTargets;
                }

                return self::getUnreadCount($currentUser->ID, $helperArgs);
            }

            return 0;
        }

        if ($previewMode) {
            return (int) apply_filters('mj_member_contact_messages_preview_unread_total', 2, $args);
        }

        return 0;
    }

    private static function resolveAccountLink(string $path, string $fallback, array $query = array()): string {
        $url = '';

        if ($path !== '') {
            $page = get_page_by_path(ltrim($path, '/'));
            if ($page) {
                $url = get_permalink($page);
            }
        }

        if ($url === '') {
            $url = $fallback;
        }

        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        return esc_url_raw($url);
    }
}
