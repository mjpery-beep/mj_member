<?php

namespace Mj\Member\Module {
    use Mj\Member\Core\Contracts\ModuleInterface;

    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Renvoie HTTP 410 Gone pour une liste figée d'anciennes URLs produit
     * (import supprimé) afin que Google les déréférence définitivement.
     *
     * La liste des chemins vit dans includes/data/gone-urls.php.
     */
    final class GoneUrlsModule implements ModuleInterface
    {
        public function register(): void
        {
            // Assez tôt pour court-circuiter avant tout rendu de template.
            add_action('parse_request', 'mj_member_gone_urls_maybe_send_410', 0);
        }
    }
}

namespace {
    if (!defined('ABSPATH')) {
        exit;
    }

    if (!function_exists('mj_member_gone_urls_list')) {
        /**
         * Chemins (sans slash final) qui doivent renvoyer 410.
         *
         * @return string[]
         */
        function mj_member_gone_urls_list(): array
        {
            static $list = null;

            if ($list === null) {
                $file = plugin_dir_path(__FILE__) . 'data/gone-urls.php';
                $data = is_readable($file) ? require $file : array();
                $list = is_array($data) ? array_fill_keys($data, true) : array();
            }

            return $list;
        }
    }

    if (!function_exists('mj_member_gone_urls_maybe_send_410')) {
        function mj_member_gone_urls_maybe_send_410(): void
        {
            if (is_admin()
                || wp_doing_ajax()
                || (defined('DOING_CRON') && DOING_CRON)
                || (defined('REST_REQUEST') && REST_REQUEST)
            ) {
                return;
            }

            $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
            if ($method !== 'GET' && $method !== 'HEAD') {
                return;
            }

            $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            if ($uri === '') {
                return;
            }

            $path = (string) parse_url($uri, PHP_URL_PATH);
            $path = rawurldecode($path);
            $path = '/' . trim($path, '/');
            if ($path === '/') {
                return;
            }

            $list = mj_member_gone_urls_list();
            if (!isset($list[$path])) {
                return;
            }

            if (!headers_sent()) {
                status_header(410);
                nocache_headers();
                header('Content-Type: text/html; charset=UTF-8');
                header('X-Robots-Tag: noindex', true);
            }

            if ($method !== 'HEAD') {
                echo "<!doctype html>\n"
                    . "<html lang=\"fr\"><head><meta charset=\"UTF-8\">"
                    . "<meta name=\"robots\" content=\"noindex\">"
                    . "<title>Page supprimee (410)</title></head>"
                    . "<body><h1>Page supprimee</h1>"
                    . "<p>Cette page n'existe plus et ne sera pas restauree.</p></body></html>";
            }

            exit;
        }
    }
}
