<?php

namespace Mj\Member\Classes\Sms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjSmsTwilio {
    private $account_sid;
    private $auth_token;
    private $from;

    public function __construct($account_sid, $auth_token, $from) {
        $this->account_sid = $account_sid;
        $this->auth_token = $auth_token;
        $this->from = $from;
    }

    public function send($to, $message) {
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->account_sid . '/Messages.json';
        $data = [
            'From' => $this->from,
            'To' => $to,
            'Body' => $message
        ];
        $args = [
            'body' => $data,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->account_sid . ':' . $this->auth_token)
            ]
        ];
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 201) {
            return array('success' => true, 'status' => $code);
        }

        $body = wp_remote_retrieve_body($response);
        $error_message = '';

        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                if (!empty($decoded['message'])) {
                    $error_message = (string) $decoded['message'];
                }
                if ($error_message !== '' && !empty($decoded['code'])) {
                    $error_message .= sprintf(' (code %s)', $decoded['code']);
                }
            }
        }

        if ($error_message === '') {
            $error_message = sprintf(
                /* translators: %d is an HTTP status code returned by Twilio */
                __('Twilio a renvoyé le statut %d.', 'mj-member'),
                $code
            );
        }

        $error_data = array(
            'status' => $code,
            'body' => $body,
            'to' => $to,
        );

        return new WP_Error('twilio_sms_error', $error_message, $error_data);
    }
}

    \class_alias(__NAMESPACE__ . '\\MjSmsTwilio', 'MjSmsTwilio');
