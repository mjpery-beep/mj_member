<?php

namespace Mj\Member\Classes\Sms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Twilio WhatsApp Provider
 * 
 * Envoie les messages WhatsApp via l'API Twilio.
 * Twilio supporte nativement WhatsApp en utilisant le format "whatsapp:+numero".
 */
class MjWhatsappTwilio {
    private $account_sid;
    private $auth_token;
    private $from;

    public function __construct($account_sid, $auth_token, $from) {
        $this->account_sid = $account_sid;
        $this->auth_token = $auth_token;
        // Enlever le préfixe "whatsapp:" s'il existe
        $from = trim((string) $from);
        if (strpos($from, 'whatsapp:') === 0) {
            $from = substr($from, 9);
        }
        $this->from = $from;
    }

    /**
     * Envoyer un message WhatsApp via Twilio.
     *
     * @param array  $phones
     * @param string $message
     * @return array|WP_Error
     */
    public function send($phones, $message) {
        if (!is_array($phones) || empty($phones)) {
            return new WP_Error('invalid_phones', __('Aucun numéro de téléphone fourni.', 'mj-member'));
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->account_sid . '/Messages.json';
        $auth = base64_encode($this->account_sid . ':' . $this->auth_token);

        $success = true;
        $errors = array();
        $failures = array();

        foreach ($phones as $phone) {
            $phone = trim((string) $phone);
            if ($phone === '') {
                continue;
            }

            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                ),
                'body' => array(
                    'From' => 'whatsapp:' . $this->from,
                    'To' => 'whatsapp:' . $phone,
                    'Body' => $message,
                ),
            ));

            if (is_wp_error($response)) {
                $success = false;
                $error_msg = $response->get_error_message();
                $errors[] = $error_msg;
                $failures[$phone] = $error_msg;
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $response_code = wp_remote_retrieve_response_code($response);

            if ($response_code < 200 || $response_code >= 300) {
                $success = false;
                $error_details = json_decode($body, true);
                $error_msg = isset($error_details['message']) ? (string) $error_details['message'] : 'API Error';
                $errors[] = $error_msg;
                $failures[$phone] = $error_msg;
            }
        }

        if ($success) {
            return array(
                'success' => true,
                'message' => __('Message WhatsApp envoyé via Twilio.', 'mj-member'),
            );
        }

        $failure_messages = array_values(array_unique($errors));
        return array(
            'success' => false,
            'error' => implode(' | ', $failure_messages),
            'errors' => $failure_messages,
            'failures' => $failures,
        );
    }
}
