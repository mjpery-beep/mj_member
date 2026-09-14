<?php

namespace Mj\Member\Module {
    use Mj\Member\Core\Contracts\ModuleInterface;

    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Renvoie HTTP 410 Gone pour les anciennes URLs spam indexées par Google.
     *
     * Deux mécanismes complémentaires :
     * - Liste figée (includes/data/gone-urls.php) interceptée dès `parse_request`,
     *   avant toute résolution de requête WordPress (le plus rapide pour les URLs
     *   connues et les plus fréquemment crawlées).
     * - Filet générique sur `template_redirect` : tout 404 WordPress devient un 410.
     *   Couvre automatiquement les nouvelles variantes de spam (numériques, italiennes...)
     *   sans jamais avoir à retoucher la liste ci-dessus.
     */
    final class GoneUrlsModule implements ModuleInterface
    {
        public function register(): void
        {
            // Assez tôt pour court-circuiter avant tout rendu de template.
            add_action('parse_request', 'mj_member_gone_urls_maybe_send_410', 0);

            // Filet de sécurité : n'importe quel 404 WordPress devient un 410.
            add_action('template_redirect', 'mj_member_gone_urls_maybe_send_410_on_404');
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

    if (!function_exists('mj_member_gone_urls_is_bypassed_context')) {
        function mj_member_gone_urls_is_bypassed_context(): bool
        {
            return is_admin()
                || wp_doing_ajax()
                || (defined('DOING_CRON') && DOING_CRON)
                || (defined('REST_REQUEST') && REST_REQUEST);
        }
    }

    if (!function_exists('mj_member_gone_urls_request_method')) {
        function mj_member_gone_urls_request_method(): string
        {
            return isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        }
    }

    if (!function_exists('mj_member_gone_urls_send_410')) {
        function mj_member_gone_urls_send_410(string $method): void
        {
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

    if (!function_exists('mj_member_gone_urls_maybe_send_410')) {
        function mj_member_gone_urls_maybe_send_410(): void
        {
            if (mj_member_gone_urls_is_bypassed_context()) {
                return;
            }

            $method = mj_member_gone_urls_request_method();
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

            mj_member_gone_urls_send_410($method);
        }
    }

    if (!function_exists('mj_member_gone_urls_maybe_send_410_on_404')) {
        /**
         * Filet générique : dès que WordPress a lui-même déterminé qu'une requête
         * ne correspond à aucun contenu réel, on renvoie 410 au lieu du 404 par défaut.
         * Aucune liste à maintenir : couvre toute variante de spam, présente ou future.
         */
        function mj_member_gone_urls_maybe_send_410_on_404(): void
        {
            if (!is_404() || mj_member_gone_urls_is_bypassed_context()) {
                return;
            }

            $method = mj_member_gone_urls_request_method();
            if ($method !== 'GET' && $method !== 'HEAD') {
                return;
            }

            mj_member_gone_urls_send_410($method);
        }
    }
}
