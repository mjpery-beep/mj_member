<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['__mj_test_actions'] = $GLOBALS['__mj_test_filters'] = $GLOBALS['__mj_test_shortcodes'] = array();
$GLOBALS['__mj_scheduled_events'] = array();
$GLOBALS['__mj_current_time'] = time();

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        protected $errors = array();

        public function __construct($code = '', $message = '', $data = null)
        {
            if ($code !== '') {
                $this->add($code, $message, $data);
            }
        }

        public function add($code, $message, $data = null): void
        {
            if (!isset($this->errors[$code])) {
                $this->errors[$code] = array();
            }
            $this->errors[$code][] = array('message' => (string) $message, 'data' => $data);
        }

        public function get_error_code(): string
        {
            return key($this->errors) ?: '';
        }

        public function get_error_message($code = ''): string
        {
            if ($code === '') {
                $code = $this->get_error_code();
            }

            if ($code === '' || empty($this->errors[$code])) {
                return '';
            }

            return (string) $this->errors[$code][0]['message'];
        }
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        global $__mj_test_actions;
        $__mj_test_actions[$hook][$priority][] = $callback;
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        global $__mj_test_filters;
        $__mj_test_filters[$hook][$priority][] = $callback;
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        global $__mj_test_filters;
        if (!isset($__mj_test_filters[$hook])) {
            return $value;
        }

        foreach ($__mj_test_filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    $value = call_user_func($callback, $value);
                }
            }
        }

        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args)
    {
        global $__mj_test_actions;
        if (empty($__mj_test_actions[$hook])) {
            return;
        }

        foreach ($__mj_test_actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    call_user_func_array($callback, $args);
                }
            }
        }
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback)
    {
        global $__mj_test_shortcodes;
        $__mj_test_shortcodes[$tag] = $callback;
        return true;
    }
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '')
    {
        $atts = (array) $atts;
        $out = array();
        foreach ($pairs as $name => $default) {
            $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
        }
        return $out;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        if (is_array($value) || is_object($value)) {
            return '';
        }
        $value = (string) $value;
        return trim($value);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
        return $key;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        $email = filter_var((string) $email, FILTER_SANITIZE_EMAIL);
        return $email ?: '';
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql($value) {
        if (is_array($value)) {
            return array_map('esc_sql', $value);
        }

        $value = (string) $value;

        return addslashes($value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('remove_query_arg')) {
    function remove_query_arg($key, $url = '')
    {
        return $url !== '' ? $url : ''; // Simplified stub for tests
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url = '')
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $url = add_query_arg($k, $v, $url);
            }
            return $url;
        }

        $query = $key . '=' . rawurlencode((string) $value);
        if ($url === '') {
            return '?' . $query;
        }

        $glue = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $glue . $query;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($url)
    {
        $GLOBALS['__mj_last_redirect'] = $url;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook)
    {
        $events = $GLOBALS['__mj_scheduled_events'] ?? array();
        if (empty($events[$hook])) {
            return false;
        }
        sort($events[$hook]);
        return reset($events[$hook]);
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook)
    {
        $GLOBALS['__mj_scheduled_events'][$hook][] = $timestamp;
        return true;
    }
}

if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event($timestamp, $hook)
    {
        if (!isset($GLOBALS['__mj_scheduled_events'][$hook])) {
            return;
        }

        $GLOBALS['__mj_scheduled_events'][$hook] = array_values(array_filter(
            $GLOBALS['__mj_scheduled_events'][$hook],
            static function ($scheduled) use ($timestamp) {
                return (int) $scheduled !== (int) $timestamp;
            }
        ));
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (!is_array($args)) {
            $args = array();
        }
        return array_merge($defaults, $args);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql')
    {
        $now = isset($GLOBALS['__mj_current_time']) ? (int) $GLOBALS['__mj_current_time'] : time();
        if ($type === 'timestamp') {
            return $now;
        }

        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s', $now);
        }

        return $now;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 0;
    }
}

if (!class_exists('WP_Session_Tokens')) {
    class WP_Session_Tokens
    {
        public static function get_instance($user_id)
        {
            return new self();
        }

        public function destroy($token)
        {
            // no-op stub for tests
        }
    }
}

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';

        public function esc_like($text)
        {
            return addcslashes((string) $text, '%_');
        }
    };
}

require_once dirname(__DIR__) . '/includes/classes/value/MemberData.php';
require_once dirname(__DIR__) . '/includes/classes/MjTools.php';
require_once dirname(__DIR__) . '/includes/classes/crud/CrudQueryBuilder.php';
require_once dirname(__DIR__) . '/includes/classes/crud/MjMembers.php';
