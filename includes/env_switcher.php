<?php

namespace Mj\Member\Module {
    use Mj\Member\Core\Contracts\ModuleInterface;
    if (!defined('ABSPATH')) { exit; }

    final class EnvSwitcherModule implements ModuleInterface {
        public function register(): void {
            add_action('admin_bar_menu', 'mj_member_env_switcher_admin_bar', 100);
        }
    }
}

namespace {
    if (!defined('ABSPATH')) { exit; }

    /* ── Bouton "switch local/prod" dans la barre d'admin WP ────────────── */
    if (!function_exists('mj_member_env_switcher_admin_bar')) {
        function mj_member_env_switcher_admin_bar($wp_admin_bar) {
            if (!is_user_logged_in() || !is_admin_bar_showing() || !current_user_can('manage_options')) {
                return;
            }

            if (get_option('mj_member_env_switch_enabled', '0') !== '1') {
                return;
            }

            $local_url = untrailingslashit((string) get_option('mj_member_env_switch_local_url', 'http://localhost:8080'));
            $prod_url  = untrailingslashit((string) get_option('mj_member_env_switch_prod_url', ''));

            if ($local_url === '' || $prod_url === '') {
                return;
            }

            $local_host = wp_parse_url($local_url, PHP_URL_HOST);
            $current_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
            $current_host = strtolower(explode(':', $current_host)[0]);

            $on_local = is_string($local_host) && $current_host !== '' && strtolower($local_host) === $current_host;

            $target_base = $on_local ? $prod_url : $local_url;
            $label = $on_local
                ? __('🚀 Aller sur PROD', 'mj-member')
                : __('🏠 Aller sur LOCAL', 'mj-member');

            $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
            $target_url = $target_base . '/' . ltrim((string) $request_uri, '/');

            $wp_admin_bar->add_node(array(
                'id'    => 'mj-member-env-switch',
                'title' => esc_html($label),
                'href'  => esc_url($target_url),
                'meta'  => array(
                    'title'  => esc_attr__('Basculer entre les environnements local et production', 'mj-member'),
                    'class'  => 'mj-member-env-switch-bar',
                ),
            ));
        }
    }
}
