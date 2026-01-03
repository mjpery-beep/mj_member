<?php
/**
 * Security protections for MJ Member Plugin
 * Protège les données sensibles d'être exposées
 */

// Empêcher l'accès direct au fichier
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bloquer l'export des options WordPress via l'API REST
 * Les clés Stripe ne doivent JAMAIS être accessibles au frontend
 */
add_filter('rest_prepare_wp_option', 'mj_rest_prepare_wp_option', 10, 2);
function mj_rest_prepare_wp_option($response, $post) {
    $option_name = $response->data['option_name'] ?? '';
    
    // Bloquer les options sensibles
    $blocked_options = array(
        'mj_stripe_secret_key',
        'mj_stripe_secret_key_encrypted',
        'mj_stripe_publishable_key',
        'mj_smtp_settings'
    );
    
    if (in_array($option_name, $blocked_options)) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'forbidden',
                'Vous n\'avez pas permission d\'accéder à cette option',
                array('status' => 403)
            );
        }
    }
    
    return $response;
}

/**
 * Nettoyer les données AJAX avant de les envoyer au frontend
 * JAMAIS envoyer de données sensibles via wp_send_json_success()
 */
add_filter('wp_send_json', 'mj_sanitize_json_response', 10, 2);
function mj_sanitize_json_response($response, $status_header) {
    if (is_array($response)) {
        // Supprimer les clés sensibles si elles existent
        $sensitive_keys = array(
            'mj_stripe_secret_key',
            'mj_stripe_secret_key_encrypted',
            'stripe_secret_key',
            'secret_key'
        );
        
        foreach ($sensitive_keys as $key) {
            if (isset($response['data'][$key])) {
                unset($response['data'][$key]);
                error_log("⚠️ SÉCURITÉ: Tentative de leak de données sensibles: $key");
            }
        }
    }
    return $response;
}

/**
 * Ajouter des headers de sécurité supplémentaires
 */
add_action('wp_headers', 'mj_add_security_headers');
function mj_add_security_headers($headers) {
    // Ces headers aident à prévenir les attaques XSS et autres
    $headers['X-Content-Type-Options'] = 'nosniff';
    $headers['X-Frame-Options'] = 'SAMEORIGIN';
    $headers['X-XSS-Protection'] = '1; mode=block';
    return $headers;
}

/**
 * Vérifier que les clés Stripe ne sont JAMAIS loggées accidentellement
 * (Active seulement en développement)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('shutdown', 'mj_check_for_exposed_keys');
    function mj_check_for_exposed_keys() {
        // Vérifier les logs WordPress pour les clés exposées
        // Cette fonction est surtout pour le développement
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $debug_log = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($debug_log)) {
                $file_size = filesize($debug_log);
                if ($file_size === false) {
                    return;
                }

                $slice_start = $file_size > 2000 ? $file_size - 2000 : 0;
                $bytes_to_read = $file_size > 0 ? $file_size - $slice_start : 0;

                if ($bytes_to_read < 1) {
                    return;
                }

                $last_lines = file_get_contents($debug_log, false, null, $slice_start, $bytes_to_read);
                if ($last_lines && preg_match('/sk_live_|sk_test_/', $last_lines)) {
                    error_log('⚠️ SÉCURITÉ CRITIQUE: Une clé Stripe peut avoir été exposée dans le debug log!');
                }
            }
        }
    }
}

/**
 * Initialiser les protections au chargement du plugin
 */
add_action('plugins_loaded', 'mj_init_security');
function mj_init_security() {
    // Vérifier que les options sensibles ne sont pas exposées via l'API REST
    register_rest_field('options', 'mj_stripe_secret_key', array(
        'get_callback' => function() {
            return new WP_Error('forbidden', 'N/A', array('status' => 403));
        },
        'schema' => array(
            'type' => 'string',
            'context' => array(),
        ),
    ));
}
?>
