<?php
if (!defined('ABSPATH')) exit;

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
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        return $code === 201;
    }
}
