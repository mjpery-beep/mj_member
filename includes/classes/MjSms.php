<?php

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

        $normalized = $has_plus ? '+' . ltrim($digits, '+') : $digits;
        if (strlen($normalized) < 6) {
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

        if (property_exists($member, 'sms_opt_in')) {
            return (bool) $member->sms_opt_in;
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
     *     error:string
     * }
     */
    public static function send_to_member($member, $message, array $context = array()) {
        $result = array(
            'success' => false,
            'phones' => array(),
            'test_mode' => false,
            'message' => '',
            'error' => '',
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
        }

        return $result;
    }

    public static function filter_default_gateway($gateway_response, $phones, $message, $member, $context) {
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

add_filter('mj_member_sms_send', array('MjSms', 'filter_default_gateway'), 10, 4);
