<?php

namespace Mj\Member\Classes;

use Mj\Member\Classes\Value\MemberData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjSms extends MjTools {
    public static function is_test_mode_enabled() {
        return get_option('mj_sms_test_mode', '0') === '1';
    }

    /**
     * Build placeholder map for a member using the email helper.
     *
     * @param object $member
     * @param array  $context
     * @return array<string,string>
     */
    protected static function resolve_placeholders($member, array $context = array()) {
        if (!class_exists('MjMail')) {
            return array();
        }

        $placeholders = MjMail::get_placeholders($member, $context);
        return is_array($placeholders) ? $placeholders : array();
    }

    /**
     * Replace placeholders in the raw message.
     *
     * @param string $message
     * @param object $member
     * @param array  $context
     * @return string
     */
    public static function build_message($message, $member, array $context = array()) {
        $message = (string) $message;
        if ($message === '') {
            return '';
        }

        $placeholders = self::resolve_placeholders($member, $context);
        if (!empty($placeholders)) {
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);
        }

        $message = wp_strip_all_tags($message, true);
        $message = preg_replace('/[\r\n\t]+/', ' ', $message);
        $message = preg_replace('/\s{2,}/', ' ', $message);

        return trim((string) $message);
    }

    /**
     * Normalise a phone number for SMS usage.
     *
     * @param string $raw
     * @return string
     */
    protected static function normalize_number($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $has_plus = strpos($raw, '+') === 0;
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === '') {
            return '';
        }

        if ($has_plus) {
            $normalized = '+' . $digits;
        } elseif (strpos($digits, '0') === 0 && strlen($digits) >= 9) {
            // Local number: replace leading 0 with default country dial code.
            $country_code = apply_filters('mj_member_default_phone_country_code', '32');
            $normalized = '+' . $country_code . substr($digits, 1);
        } else {
            $normalized = $digits;
        }

        if (strlen(preg_replace('/[^0-9]/', '', $normalized)) < 6) {
            return '';
        }

        return $normalized;
    }

    /**
     * Collect SMS targets for a member.
     *
     * @param object $member
     * @param array  $context
     * @return string[]
     */
    public static function collect_targets($member, array $context = array()) {
        $targets = array();
        if (!is_object($member)) {
            return $targets;
        }

        $primary = isset($member->phone) ? $member->phone : '';
        $normalized = self::normalize_number($primary);
        if ($normalized !== '') {
            $targets[] = $normalized;
        }

        if (!empty($context['extra_numbers']) && is_array($context['extra_numbers'])) {
            foreach ($context['extra_numbers'] as $extra) {
                $candidate = self::normalize_number($extra);
                if ($candidate !== '') {
                    $targets[] = $candidate;
                }
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * Determine whether SMS delivery is allowed for a member.
     *
     * @param object $member
     * @return bool
     */
    public static function is_allowed($member) {
        if (!is_object($member)) {
            return false;
        }

        if ($member instanceof MemberData) {
            $flag = $member->get('sms_opt_in');
            return $flag === null ? true : ((int) $flag === 1);
        }

        if (property_exists($member, 'sms_opt_in')) {
            return (int) $member->sms_opt_in === 1;
        }

        return true;
    }

    /**
     * Send an SMS message to a member via the configured gateway filters.
     *
     * @param object $member
     * @param string $message
     * @param array  $context
    * @return array{
    *     success:bool,
    *     phones:string[],
    *     test_mode:bool,
    *     message:string,
    *     error:string,
    *     rendered_message:string,
    *     error_details:string[]
    * }
     */
    public static function send_to_member($member, $message, array $context = array()) {
        $result = array(
            'success' => false,
            'phones' => array(),
            'test_mode' => false,
            'message' => '',
            'error' => '',
            'rendered_message' => '',
            'error_details' => array(),
        );

        if (!is_object($member) || !isset($member->id)) {
            $result['error'] = __('Destinataire SMS invalide.', 'mj-member');
            return $result;
        }

        if (!self::is_allowed($member)) {
            $result['error'] = __('Ce membre a refusé la réception de SMS.', 'mj-member');
            return $result;
        }

        $message = self::build_message($message, $member, $context);
        if ($message === '') {
            $result['error'] = __('Le contenu du SMS est vide.', 'mj-member');
            return $result;
        }

        $result['rendered_message'] = $message;

        $phones = self::collect_targets($member, $context);
        if (empty($phones)) {
            $result['error'] = __('Aucun numéro de téléphone valide pour ce membre.', 'mj-member');
            return $result;
        }

        $result['phones'] = $phones;

        if (self::is_test_mode_enabled()) {
            $result['success'] = true;
            $result['test_mode'] = true;
            $result['message'] = __('Mode test SMS actif : envoi simulé (aucun SMS sortant).', 'mj-member');
            do_action('mj_member_sms_simulated', $phones, $message, $member, $context, $result);
            return $result;
        }

        /**
         * Permet aux extensions de gérer l'envoi de SMS.
         * Retour attendu :
         *  - bool (succès / échec)
        *  - array { success:bool, test_mode?:bool, message?:string, error?:string }
         *  - WP_Error en cas d'échec
         *  - null pour simuler l'envoi (mode test).
         */
        $gateway_response = apply_filters('mj_member_sms_send', null, $phones, $message, $member, $context);

        if (is_wp_error($gateway_response)) {
            $result['error'] = $gateway_response->get_error_message();
            $result['error_details'] = array($result['error']);
            return $result;
        }

        if (is_array($gateway_response)) {
            $result['success'] = !empty($gateway_response['success']);
            $result['test_mode'] = !empty($gateway_response['test_mode']);
            if (!empty($gateway_response['message'])) {
                $result['message'] = (string) $gateway_response['message'];
            }
            if (!$result['success'] && !empty($gateway_response['error'])) {
                $result['error'] = (string) $gateway_response['error'];
            }
            if (!$result['success'] && !empty($gateway_response['errors']) && is_array($gateway_response['errors'])) {
                $result['error_details'] = array_map('strval', $gateway_response['errors']);
            } elseif (!$result['success'] && !empty($gateway_response['failures']) && is_array($gateway_response['failures'])) {
                foreach ($gateway_response['failures'] as $failure) {
                    if (is_array($failure) && !empty($failure['error'])) {
                        $result['error_details'][] = (string) $failure['error'];
                    }
                }
            }
        } elseif (is_bool($gateway_response)) {
            $result['success'] = $gateway_response;
        } elseif ($gateway_response === null) {
            $result['success'] = true;
            $result['test_mode'] = true;
            $result['message'] = __('SMS simulé (aucun envoi réel).', 'mj-member');
        } else {
            $result['success'] = true;
        }

        if ($result['success']) {
            if ($result['message'] === '') {
                $result['message'] = __('SMS envoyé.', 'mj-member');
            }
            do_action('mj_member_sms_sent', $phones, $message, $member, $context, $result);
        } else {
            if ($result['error'] === '') {
                $result['error'] = __('Impossible d’envoyer le SMS.', 'mj-member');
            }
            if (empty($result['error_details'])) {
                $result['error_details'] = array($result['error']);
            }
        }

        return $result;
    }

    public static function filter_default_gateway($gateway_response, $phones, $message, $member, $context = array()) {
        if ($gateway_response !== null) {
            return $gateway_response;
        }

        if (!is_array($phones) || empty($phones)) {
            return $gateway_response;
        }

        $provider = get_option('mj_sms_provider', 'disabled');
        if ($provider === 'disabled') {
            return new WP_Error('mj_sms_disabled', __('Aucun fournisseur SMS n’est configuré.', 'mj-member'));
        }

        if ($provider === 'twilio') {
            $account_sid = trim((string) get_option('mj_sms_twilio_sid', ''));
            $auth_token = trim((string) get_option('mj_sms_twilio_token', ''));
            $from_number = trim((string) get_option('mj_sms_twilio_from', ''));

            if ($account_sid === '' || $auth_token === '' || $from_number === '') {
                return new WP_Error('mj_sms_missing_twilio_keys', __('Les identifiants Twilio sont incomplets.', 'mj-member'));
            }

            if (!class_exists('MjSmsTwilio')) {
                return new WP_Error('mj_sms_missing_twilio_client', __('Le client Twilio est introuvable.', 'mj-member'));
            }

            $twilio = new MjSmsTwilio($account_sid, $auth_token, $from_number);
            $failures = array();
            $failure_messages = array();

            foreach ($phones as $phone) {
                $sent = $twilio->send($phone, $message);
                $success = false;
                $detail = '';

                if (is_wp_error($sent)) {
                    $detail = $sent->get_error_message();
                    $data = $sent->get_error_data();
                    if (is_array($data) && !empty($data['status'])) {
                        $detail .= sprintf(' [HTTP %s]', $data['status']);
                    }
                } elseif (is_array($sent)) {
                    $success = !empty($sent['success']);
                    if (!$success && !empty($sent['error'])) {
                        $detail = (string) $sent['error'];
                    }
                } else {
                    $success = (bool) $sent;
                }

                if ($success) {
                    continue;
                }

                if ($detail === '') {
                    $detail = __('Erreur Twilio inconnue.', 'mj-member');
                }

                $failures[] = array(
                    'phone' => $phone,
                    'error' => $detail,
                );
                $failure_messages[] = sprintf('%s : %s', $phone, $detail);
            }

            if (empty($failures)) {
                return array(
                    'success' => true,
                    'message' => __('SMS envoyé via Twilio.', 'mj-member'),
                );
            }

            $failure_messages = array_values(array_unique($failure_messages));
            return array(
                'success' => false,
                'error' => implode(' | ', $failure_messages),
                'errors' => $failure_messages,
                'failures' => $failures,
            );
        }

        if ($provider === 'textbelt') {
            $api_key = trim((string) get_option('mj_sms_textbelt_api_key', ''));
            if ($api_key === '') {
                return new WP_Error('mj_sms_missing_key', __('La clé API Textbelt est manquante.', 'mj-member'));
            }

            $success = true;
            $errors = array();

            foreach ($phones as $phone) {
                $response = wp_remote_post('https://textbelt.com/text', array(
                    'timeout' => 15,
                    'body' => array(
                        'phone' => $phone,
                        'message' => $message,
                        'key' => $api_key,
                    ),
                ));

                if (is_wp_error($response)) {
                    $success = false;
                    $errors[] = $response->get_error_message();
                    continue;
                }

                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $parsed = json_decode($body, true);

                if ($code !== 200 || !is_array($parsed) || empty($parsed['success'])) {
                    $success = false;
                    if (is_array($parsed) && !empty($parsed['error'])) {
                        $errors[] = (string) $parsed['error'];
                    } else {
                        $errors[] = sprintf(__('Erreur %d renvoyée par Textbelt.', 'mj-member'), $code);
                    }
                }
            }

            if ($success) {
                return array(
                    'success' => true,
                    'message' => __('SMS envoyé via Textbelt.', 'mj-member'),
                );
            }

            $errors = array_filter(array_unique($errors));
            $error_message = !empty($errors)
                ? implode(' ', $errors)
                : __('Impossible d’envoyer le SMS via Textbelt.', 'mj-member');

            return array(
                'success' => false,
                'error' => $error_message,
            );
        }

        return $gateway_response;
    }
}

add_filter('mj_member_sms_send', array('MjSms', 'filter_default_gateway'), 10, 5);

\class_alias(__NAMESPACE__ . '\\MjSms', 'MjSms');
