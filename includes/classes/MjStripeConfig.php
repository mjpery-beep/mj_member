<?php

namespace Mj\Member\Classes;

use Exception;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration et gestion de l'intégration Stripe
 * SÉCURITÉ: Les clés secrètes ne doivent JAMAIS être exposées au frontend
 */

class MjStripeConfig {
    
    // Clés Stripe (à configurer dans les options WordPress)
    private static $publishable_key = null;
    private static $secret_key = null;
    private static $webhook_secret = null;
    private static $current_mode = null;
    private static $encryption_key = 'mj_stripe_encryption_v1'; // Salt pour le chiffrement simple

    /**
     * Normalise le mode Stripe (live|test)
     */
    private static function normalize_mode($mode = null) {
        return ($mode === 'test') ? 'test' : 'live';
    }

    /**
     * Récupère le mode configuré dans l'administration
     */
    private static function get_mode_option() {
        $option = get_option('mj_stripe_test_mode', '0');
        return ($option === '1' || $option === 1 || $option === true) ? 'test' : 'live';
    }

    /**
     * Helper exposé pour savoir si le mode test est actif
     */
    public static function is_test_mode() {
        return self::get_mode_option() === 'test';
    }

    private static function get_publishable_option_name($mode) {
        return ($mode === 'test') ? 'mj_stripe_test_publishable_key' : 'mj_stripe_publishable_key';
    }

    private static function get_secret_option_name($mode) {
        return ($mode === 'test') ? 'mj_stripe_test_secret_key_encrypted' : 'mj_stripe_secret_key_encrypted';
    }

    private static function get_legacy_secret_option_name($mode) {
        return ($mode === 'test') ? 'mj_stripe_test_secret_key' : 'mj_stripe_secret_key';
    }

    private static function get_webhook_option_name($mode) {
        return ($mode === 'test') ? 'mj_stripe_test_webhook_secret_encrypted' : 'mj_stripe_webhook_secret_encrypted';
    }

    private static function get_legacy_webhook_option_name($mode) {
        return ($mode === 'test') ? 'mj_stripe_test_webhook_secret' : 'mj_stripe_webhook_secret';
    }

    private static function get_endpoint_option_name($mode) {
        return ($mode === 'test') ? 'mj_stripe_test_webhook_endpoint_id' : 'mj_stripe_webhook_endpoint_id';
    }

    private static function get_secret_for_mode($mode) {
        $mode = self::normalize_mode($mode);
        if (self::get_mode_option() === $mode) {
            self::init();
            return self::$secret_key;
        }

        $encrypted = get_option(self::get_secret_option_name($mode), '');
        return empty($encrypted) ? '' : self::decrypt_key($encrypted);
    }

    private static function get_webhook_for_mode($mode) {
        $mode = self::normalize_mode($mode);
        if (self::get_mode_option() === $mode) {
            self::init();
            return self::$webhook_secret;
        }

        $encrypted = get_option(self::get_webhook_option_name($mode), '');
        return empty($encrypted) ? '' : self::decrypt_key($encrypted);
    }
    
    /**
     * Initialiser les clés Stripe
     */
    public static function init() {
        $mode = self::get_mode_option();
        self::$current_mode = $mode;

        $publishable_option = self::get_publishable_option_name($mode);
        self::$publishable_key = get_option($publishable_option, '');

        $encrypted_secret_option = self::get_secret_option_name($mode);
        $encrypted_secret = get_option($encrypted_secret_option, '');

        if (empty($encrypted_secret)) {
            $legacy_secret_option = self::get_legacy_secret_option_name($mode);
            $plaintext_secret = get_option($legacy_secret_option, '');
            if (!empty($plaintext_secret)) {
                $encrypted_secret = self::encrypt_key($plaintext_secret);
                if (!empty($encrypted_secret)) {
                    update_option($encrypted_secret_option, $encrypted_secret);
                }
                delete_option($legacy_secret_option);
            }
        }

        self::$secret_key = empty($encrypted_secret) ? '' : self::decrypt_key($encrypted_secret);

        $webhook_option = self::get_webhook_option_name($mode);
        $encrypted_webhook = get_option($webhook_option, '');

        if (empty($encrypted_webhook)) {
            $legacy_webhook_option = self::get_legacy_webhook_option_name($mode);
            $legacy_plain = get_option($legacy_webhook_option, '');
            if (!empty($legacy_plain)) {
                $encrypted_webhook = self::encrypt_key($legacy_plain);
                if (!empty($encrypted_webhook)) {
                    update_option($webhook_option, $encrypted_webhook);
                }
                delete_option($legacy_webhook_option);
            }
        }

        self::$webhook_secret = empty($encrypted_webhook) ? '' : self::decrypt_key($encrypted_webhook);
    }
    
    /**
     * Chiffrer une clé (simple XOR + base64 - pas crypto-fort mais dissuade la lecture accidentelle)
     */
    private static function encrypt_key($plaintext) {
        if (empty($plaintext)) return '';
        $salt = self::get_salt('auth');
        $key = hash_pbkdf2('sha256', $salt . self::$encryption_key, self::$encryption_key, 1000, 32, false);
        $iv = substr(hash('sha256', self::get_salt('nonce')), 0, 16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Déchiffrer une clé
     */
    private static function decrypt_key($ciphertext) {
        if (empty($ciphertext)) return '';
        try {
            $salt = self::get_salt('auth');
            $key = hash_pbkdf2('sha256', $salt . self::$encryption_key, self::$encryption_key, 1000, 32, false);
            $data = base64_decode($ciphertext);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $plaintext = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            return $plaintext !== false ? $plaintext : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Récupérer un salt de façon sûre (fallback si wp_salt() indisponible)
     */
    private static function get_salt($type = 'auth') {
        // Preferer wp_salt() si disponible
        if (function_exists('wp_salt')) {
            return wp_salt($type);
        }

        // Fallback vers les constantes définies dans wp-config.php
        if ($type === 'auth') {
            if (defined('AUTH_KEY') && AUTH_KEY) return AUTH_KEY;
            if (defined('SECURE_AUTH_KEY') && SECURE_AUTH_KEY) return SECURE_AUTH_KEY;
        }
        if ($type === 'nonce') {
            if (defined('NONCE_SALT') && NONCE_SALT) return NONCE_SALT;
            if (defined('LOGGED_IN_SALT') && LOGGED_IN_SALT) return LOGGED_IN_SALT;
        }

        // Ultimate fallback (deterministic but less secure)
        return 'mj_member_fallback_salt_v1';
    }
    
    /**
     * Vérifier que Stripe est configuré
     */
    public static function is_configured() {
        self::init();
        return !empty(self::$publishable_key) && !empty(self::$secret_key);
    }

    /**
     * Enregistrer une clé secrète (chiffre puis update_option)
     */
    public static function set_secret_key($plaintext, $mode = 'live') {
        if (empty($plaintext)) return false;
        // Ensure WP functions available
        if (!function_exists('update_option')) return false;
        $mode = self::normalize_mode($mode);
        $encrypted = self::encrypt_key($plaintext);
        if (empty($encrypted)) return false;
        $option_name = self::get_secret_option_name($mode);
        update_option($option_name, $encrypted);
        // Remove plaintext if present (legacy)
        delete_option(self::get_legacy_secret_option_name($mode));
        // Refresh in-memory value si on agit sur le mode courant
        if (self::get_mode_option() === $mode) {
            self::init();
        }
        return true;
    }

    /**
     * Enregistrer le webhook signing secret (chiffre puis update_option)
     */
    public static function set_webhook_secret($plaintext, $mode = 'live') {
        if (empty($plaintext)) return false;
        if (!function_exists('update_option')) return false;
        $mode = self::normalize_mode($mode);
        $encrypted = self::encrypt_key($plaintext);
        if (empty($encrypted)) return false;
        $option_name = self::get_webhook_option_name($mode);
        update_option($option_name, $encrypted);
        delete_option(self::get_legacy_webhook_option_name($mode));
        if (self::get_mode_option() === $mode) {
            self::init();
        }
        return true;
    }
    
    /**
     * Retourner la clé secrète (SEULEMENT côté serveur, JAMAIS au frontend)
     */
    public static function get_secret_key() {
        self::init();
        // Log a warning if accessed from a non-admin non-AJAX non-CLI context
        if (!is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) && php_sapi_name() !== 'cli') {
            error_log('⚠️ SÉCURITÉ: Accès à la clé secrète Stripe depuis un contexte non-admin. Assurez-vous que l\'appel est côté serveur.');
        }
        return self::$secret_key;
    }

    /**
     * Admin-only helper to récupérer une clé secrète pour un mode donné.
     */
    public static function get_admin_secret_key($mode = null) {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return '';
        }
        if (!function_exists('is_admin') || !is_admin()) {
            return '';
        }
        $mode = self::normalize_mode($mode ?? self::get_mode_option());
        return self::get_secret_for_mode($mode);
    }

    /**
     * Retourner le webhook signing secret (SEULEMENT côté serveur, JAMAIS au frontend)
     */
    public static function get_webhook_secret() {
        self::init();
        // Accessible côté serveur (utilisé par le endpoint webhook)
        return self::$webhook_secret;
    }

    /**
     * Admin-only helper to récupérer un webhook secret pour un mode donné.
     */
    public static function get_admin_webhook_secret($mode = null) {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return '';
        }
        if (!function_exists('is_admin') || !is_admin()) {
            return '';
        }
        $mode = self::normalize_mode($mode ?? self::get_mode_option());
        return self::get_webhook_for_mode($mode);
    }

    /**
     * Create a Stripe webhook endpoint and store its signing secret securely.
     * Returns array with 'id' on success.
     */
    public static function create_webhook_endpoint($url, $events = array('checkout.session.completed','payment_intent.succeeded'), $mode = null) {
        self::init();
        $mode = self::normalize_mode($mode ?? self::get_mode_option());
        $secret = self::get_secret_for_mode($mode);
        if (empty($secret)) {
            throw new Exception('Stripe API key not configured for mode ' . strtoupper($mode));
        }

        $post = array();
        $post[] = 'url=' . urlencode($url);
        foreach ($events as $e) {
            $post[] = 'enabled_events[]=' . urlencode($e);
        }
        $post_str = implode('&', $post);

        $ch = curl_init('https://api.stripe.com/v1/webhook_endpoints');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_str);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new Exception('Stripe API request failed: ' . $err);
        }
        $data = json_decode($resp, true);
        if ($http_code >= 400) {
            $msg = isset($data['error']) ? json_encode($data['error']) : $resp;
            throw new Exception('Stripe API error: ' . $msg);
        }

        $webhook_id = $data['id'] ?? null;
        $signing_secret = $data['secret'] ?? null; // Extract signing secret directly
        if (!$webhook_id || !$signing_secret) {
            throw new Exception('No webhook id or signing secret returned');
        }

        // Store the endpoint id and signing secret securely
        update_option(self::get_endpoint_option_name($mode), $webhook_id);
        self::set_webhook_secret($signing_secret, $mode);

        if (self::get_mode_option() === $mode) {
            self::init();
        }

        return array(
            'id' => $webhook_id,
            'mode' => $mode
        );
    }

    /**
     * Delete a Stripe webhook endpoint and remove stored data
     */
    public static function delete_webhook_endpoint($endpoint_id = '', $mode = null) {
        $mode = self::normalize_mode($mode ?? self::get_mode_option());
        if (empty($endpoint_id)) {
            $endpoint_id = get_option(self::get_endpoint_option_name($mode), '');
        }
        if (empty($endpoint_id)) {
            throw new Exception('No webhook endpoint id available');
        }

        $secret = self::get_secret_for_mode($mode);
        if (empty($secret)) {
            throw new Exception('Stripe API key not configured');
        }

        $ch = curl_init("https://api.stripe.com/v1/webhook_endpoints/{$endpoint_id}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $secret . ':');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new Exception('Stripe API request failed: ' . $err);
        }
        $data = json_decode($resp, true);
        if ($http_code >= 400) {
            $msg = isset($data['error']) ? json_encode($data['error']) : $resp;
            throw new Exception('Stripe API error: ' . $msg);
        }

        // Remove stored options (mode spécifique)
        delete_option(self::get_endpoint_option_name($mode));
        delete_option(self::get_webhook_option_name($mode));

        if (self::get_mode_option() === $mode) {
            self::init();
        }

        return array('mode' => $mode);
    }
    
    /**
     * Retourner la clé publique (SAFE pour le frontend)
     */
    public static function get_publishable_key() {
        self::init();
        return self::$publishable_key;
    }
    
    /**
     * Initialiser Stripe SDK
     */
    public static function get_stripe() {
        if (!self::is_configured()) {
            return null;
        }
        
        // Charger la librairie Stripe si elle n'est pas déjà chargée
        if (!class_exists('Stripe\\Stripe')) {
            // Essayer avec Composer
            $autoload_path = Config::path() . 'vendor/autoload.php';
            if (file_exists($autoload_path)) {
                require_once $autoload_path;
            } else {
                // Alternative: utiliser l'API REST de Stripe via cURL
                return null;
            }
        }
        
        \Stripe\Stripe::setApiKey(self::$secret_key);
        return true;
    }
}

// Ne pas initialiser immédiatement à l'inclusion du fichier
// Initialiser lorsque WordPress a chargé les hooks (éviter wp_salt() non défini)
if (function_exists('add_action')) {
    add_action('plugins_loaded', array('MjStripeConfig', 'init'));
}

\class_alias(__NAMESPACE__ . '\\MjStripeConfig', 'MjStripeConfig');