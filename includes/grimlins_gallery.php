<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_grimlins_gallery_get_storage_paths')) {
    /**
     * Returns the storage directory and URL used for Grimlins sessions.
     *
     * @return array{dir:string,url:string}
     */
    function mj_member_grimlins_gallery_get_storage_paths(): array
    {
        $dir = apply_filters('mj_member_photo_grimlins_storage_dir', trailingslashit(Config::path()) . 'user-grimlins');
        $url = apply_filters('mj_member_photo_grimlins_storage_url', trailingslashit(Config::url()) . 'user-grimlins/');

        return array(
            'dir' => $dir,
            'url' => $url,
        );
    }
}

if (!function_exists('mj_member_grimlins_gallery_locate_file')) {
    /**
     * Locate a file matching the given basename within a Grimlins session folder.
     */
    function mj_member_grimlins_gallery_locate_file(string $session_path, string $basename): ?string
    {
        $pattern = trailingslashit($session_path) . $basename . '.*';
        $candidates = glob($pattern);
        if (empty($candidates)) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            if (is_dir($candidate) || !is_readable($candidate)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }
}

if (!function_exists('mj_member_grimlins_gallery_list_sessions')) {
    /**
     * @param array{limit?:int,order?:string} $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_grimlins_gallery_list_sessions(array $args = array()): array
    {
        $defaults = array(
            'limit' => 0,
            'order' => 'desc',
        );

        $args = wp_parse_args($args, $defaults);
        $limit = max(0, (int) ($args['limit'] ?? 0));
        $order = strtolower((string) ($args['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $paths = mj_member_grimlins_gallery_get_storage_paths();
        $storage_dir = $paths['dir'];
        $storage_url = $paths['url'];

        if (!is_dir($storage_dir) || !is_readable($storage_dir)) {
            return array();
        }

        $entries = @scandir($storage_dir);
        if (!is_array($entries) || empty($entries)) {
            return array();
        }

        $sessions = array();

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!is_string($entry) || $entry === '' || substr($entry, 0, 1) === '.') {
                continue;
            }

            $session_path = trailingslashit($storage_dir) . $entry;
            if (!is_dir($session_path) || !is_readable($session_path)) {
                continue;
            }

            $original_path = mj_member_grimlins_gallery_locate_file($session_path, 'photo-original');
            $result_path = mj_member_grimlins_gallery_locate_file($session_path, 'photo-grimlins');

            if (!$original_path && !$result_path) {
                continue;
            }

            $session_url_base = trailingslashit(trailingslashit($storage_url) . rawurlencode($entry));

            $created_at = 0;
            if ($result_path && file_exists($result_path)) {
                $created_at = max($created_at, (int) filemtime($result_path));
            }
            if ($original_path && file_exists($original_path)) {
                $created_at = max($created_at, (int) filemtime($original_path));
            }

            $sessions[] = array(
                'session' => $entry,
                'original_url' => $original_path ? esc_url_raw($session_url_base . rawurlencode(basename($original_path))) : '',
                'result_url' => $result_path ? esc_url_raw($session_url_base . rawurlencode(basename($result_path))) : '',
                'created_at' => $created_at,
                'created_label' => $created_at > 0 ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $created_at) : '',
            );
        }

        if (empty($sessions)) {
            return array();
        }

        usort($sessions, static function ($left, $right) use ($order) {
            $a = isset($left['created_at']) ? (int) $left['created_at'] : 0;
            $b = isset($right['created_at']) ? (int) $right['created_at'] : 0;

            if ($a === $b) {
                return 0;
            }

            if ($order === 'asc') {
                return ($a < $b) ? -1 : 1;
            }

            return ($a > $b) ? -1 : 1;
        });

        if ($limit > 0 && count($sessions) > $limit) {
            $sessions = array_slice($sessions, 0, $limit);
        }

        /**
         * @param array<int,array<string,mixed>> $sessions
         * @param array<string,mixed> $args
         */
        return apply_filters('mj_member_grimlins_gallery_sessions', $sessions, $args);
    }
}

if (!function_exists('mj_member_grimlins_gallery_sample_data')) {
    /**
     * Provides placeholder data for the Elementor preview.
     *
     * @return array<int,array<string,mixed>>
     */
    function mj_member_grimlins_gallery_sample_data(): array
    {
        $before_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><defs><linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f472b6"/><stop offset="100%" stop-color="#a855f7"/></linearGradient></defs><rect width="400" height="400" fill="url(#g1)"/><text x="50%" y="52%" text-anchor="middle" font-size="42" fill="#ffffff" font-family="Arial, sans-serif">Avant</text></svg>');
        $after_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><defs><linearGradient id="g2" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#22d3ee"/><stop offset="100%" stop-color="#2563eb"/></linearGradient></defs><rect width="400" height="400" fill="url(#g2)"/><text x="50%" y="52%" text-anchor="middle" font-size="42" fill="#ffffff" font-family="Arial, sans-serif">Après</text></svg>');

        $now = time();

        return array(
            array(
                'session' => 'preview-session-1',
                'original_url' => $before_svg,
                'result_url' => $after_svg,
                'created_at' => $now,
                'created_label' => __('Exemple de transformation', 'mj-member'),
            ),
            array(
                'session' => 'preview-session-2',
                'original_url' => $before_svg,
                'result_url' => $after_svg,
                'created_at' => $now - HOUR_IN_SECONDS,
                'created_label' => __('Deuxième transformation', 'mj-member'),
            ),
        );
    }
}

if (!function_exists('mj_member_grimlins_gallery_delete_session')) {
    /**
     * Deletes a Grimlins session directory from disk.
     */
    function mj_member_grimlins_gallery_delete_session(string $session): bool
    {
        if ($session === '' || strpos($session, '..') !== false || strpos($session, '/') !== false || strpos($session, '\\') !== false) {
            return false;
        }

        $paths = mj_member_grimlins_gallery_get_storage_paths();
        $storage_dir = $paths['dir'];

        if (!is_dir($storage_dir) || !is_readable($storage_dir)) {
            return false;
        }

        $session_path = trailingslashit($storage_dir) . $session;
        $session_path = wp_normalize_path($session_path);

        if (!is_dir($session_path)) {
            return false;
        }

        $real_storage = realpath($storage_dir);
        $real_session = realpath($session_path);

        if ($real_storage === false || $real_session === false || strpos($real_session, $real_storage) !== 0) {
            return false;
        }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($real_session, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

        foreach ($iterator as $file_info) {
            /** @var \SplFileInfo $file_info */
            if ($file_info->isDir()) {
                @rmdir($file_info->getPathname());
            } else {
                @unlink($file_info->getPathname());
            }
        }

        return @rmdir($real_session);
    }
}

if (!function_exists('mj_member_grimlins_gallery_handle_delete_session')) {
    function mj_member_grimlins_gallery_handle_delete_session(): void
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Action non autorisée.', 'mj-member')), 403);
        }

        check_ajax_referer('mj_member_grimlins_gallery', 'nonce');

        $session = isset($_POST['session']) ? sanitize_text_field(wp_unslash((string) $_POST['session'])) : '';

        if ($session === '') {
            wp_send_json_error(array('message' => __('Session invalide.', 'mj-member')), 400);
        }

        if (!mj_member_grimlins_gallery_delete_session($session)) {
            wp_send_json_error(array('message' => __('Impossible de supprimer ce dossier Grimlins.', 'mj-member')), 500);
        }

        wp_send_json_success(array('session' => $session));
    }
    add_action('wp_ajax_mj_member_delete_grimlins_session', 'mj_member_grimlins_gallery_handle_delete_session');
}

if (!function_exists('mj_member_grimlins_gallery_localize')) {
    function mj_member_grimlins_gallery_localize(): void
    {
        static $localized = false;
        if ($localized) {
            return;
        }

        if (!wp_script_is('mj-member-grimlins-gallery', 'enqueued')) {
            return;
        }

        wp_localize_script('mj-member-grimlins-gallery', 'mjMemberGrimlinsGallery', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mj_member_grimlins_gallery'),
            'i18n' => array(
                'confirmDelete' => __('Supprimer définitivement cette transformation ?', 'mj-member'),
                'deleteError' => __('Impossible de supprimer cette transformation Grimlins.', 'mj-member'),
            ),
        ));

        $localized = true;
    }
}
