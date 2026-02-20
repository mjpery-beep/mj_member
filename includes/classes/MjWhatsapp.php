<?php

namespace Mj\Member\Classes;

use Mj\Member\Classes\Value\MemberData;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjWhatsapp extends MjTools {
    public static function is_test_mode_enabled() {
        return get_option('mj_whatsapp_test_mode', '0') === '1';
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
     * Normalise a phone number for WhatsApp usage.
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
     * Collect WhatsApp targets for a member.
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

        // Si le membre n'a pas de numéro, chercher celui du tuteur (guardian)
        if (empty($targets) && !empty($member->guardian_id) && class_exists('Mj\Member\Classes\Crud\MjMembers')) {
            $guardian = \Mj\Member\Classes\Crud\MjMembers::getById((int) $member->guardian_id);
            if ($guardian && !empty($guardian->phone)) {
                $guardian_normalized = self::normalize_number($guardian->phone);
                if ($guardian_normalized !== '') {
                    $targets[] = $guardian_normalized;
                }
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * Check if a member is allowed to receive WhatsApp messages.
     *
     * @param object $member
     * @return bool
     */
    public static function is_allowed($member) {
        if (!is_object($member)) {
            return false;
        }

        if (isset($member->whatsapp_opt_in)) {
            return (bool) $member->whatsapp_opt_in;
        }

        if (isset($member->whatsappOptIn)) {
            return (bool) $member->whatsappOptIn;
        }

        return true;
    }

    /**
     * Send a WhatsApp message to a member via the configured gateway filters.
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
            $result['error'] = __('Destinataire WhatsApp invalide.', 'mj-member');
            return $result;
        }

        if (!self::is_allowed($member)) {
            $result['error'] = __('Ce membre a refusé la réception de messages WhatsApp.', 'mj-member');
            return $result;
        }

        $message = self::build_message($message, $member, $context);
        if ($message === '') {
            $result['error'] = __('Le contenu du message WhatsApp est vide.', 'mj-member');
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
            $result['message'] = __('Mode test WhatsApp actif : envoi simulé (aucun message sortant).', 'mj-member');
            do_action('mj_member_whatsapp_simulated', $phones, $message, $member, $context, $result);
            return $result;
        }

        $delivery = apply_filters('mj_member_whatsapp_send', null, $phones, $message, $member, $context, $result);

        if (is_wp_error($delivery)) {
            $result['error'] = $delivery->get_error_message();
            $error_details = $delivery->get_error_data();
            if (is_array($error_details)) {
                $result['error_details'] = $error_details;
            } else {
                $result['error_details'] = array($result['error']);
            }
            return $result;
        }

        if (is_array($delivery)) {
            $result = array_merge($result, $delivery);
            if ($result['message'] === '') {
                $result['message'] = __('Message WhatsApp envoyé.', 'mj-member');
            }
            do_action('mj_member_whatsapp_sent', $phones, $message, $member, $context, $result);
        } else {
            if ($result['message'] === '') {
                $result['message'] = __('Message WhatsApp envoyé.', 'mj-member');
            }
            do_action('mj_member_whatsapp_sent', $phones, $message, $member, $context, $result);
        }

        return $result;
    }

    /**
     * Send WhatsApp to multiple members via gateway filters.
     *
     * @param array  $members
     * @param string $message
     * @param array  $context
     * @return array{
     *     success:bool,
     *     sent:int,
     *     failed:int,
     *     skipped:int,
     *     results:array,
     *     errors:string[],
     *     message:string
     * }
     */
    public static function send_bulk($members, $message, array $context = array()) {
        $summary = array(
            'success' => true,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'results' => array(),
            'errors' => array(),
            'message' => '',
        );

        if (!is_array($members) || empty($members)) {
            $summary['message'] = __('Aucun destinataire sélectionné.', 'mj-member');
            return $summary;
        }

        foreach ($members as $member) {
            if (!is_object($member) || !isset($member->id)) {
                continue;
            }

            $delivery = self::send_to_member($member, $message, $context);

            $member_id = (int) $member->id;
            $label = isset($member->first_name) || isset($member->last_name)
                ? trim((string) ($member->last_name ?? '') . ' ' . (string) ($member->first_name ?? ''))
                : 'Membre #' . $member_id;

            $summary['results'][] = array(
                'member_id' => $member_id,
                'label' => $label,
                'status' => $delivery['success'] ? 'sent' : 'failed',
                'phones' => $delivery['phones'],
                'message' => $delivery['message'],
                'error' => $delivery['error'],
            );

            if ($delivery['success']) {
                $summary['sent']++;
            } else {
                if (!$delivery['error']) {
                    $summary['skipped']++;
                } else {
                    $summary['failed']++;
                    $summary['errors'][] = $label . ': ' . $delivery['error'];
                }
            }
        }

        $summary['success'] = $summary['failed'] === 0;
        $summary['message'] = sprintf(
            __('%1$d envoyé(s), %2$d échec(s), %3$d ignoré(s).', 'mj-member'),
            $summary['sent'],
            $summary['failed'],
            $summary['skipped']
        );

        return $summary;
    }

    /**
     * Default gateway for WhatsApp via Twilio.
     *
     * @param mixed  $gateway_response null if not handled by another filter
     * @param array  $phones
     * @param string $message
     * @param object $member
     * @param array  $context
     * @param array  $result
     * @return array|WP_Error|null
     */
    public static function filter_default_gateway($gateway_response, $phones, $message, $member, array $context = array(), array $result = array()) {
        if ($gateway_response !== null) {
            return $gateway_response;
        }

        if (!is_array($phones) || empty($phones)) {
            return $gateway_response;
        }

        $provider = get_option('mj_sms_provider', 'disabled');
        if ($provider === 'disabled') {
            return new WP_Error('mj_whatsapp_provider_disabled', __('Le fournisseur WhatsApp n\'est pas configuré.', 'mj-member'));
        }

        if ($provider === 'twilio') {
            $account_sid = trim((string) get_option('mj_sms_twilio_sid', ''));
            $auth_token = trim((string) get_option('mj_sms_twilio_token', ''));
            $whatsapp_from = trim((string) get_option('mj_whatsapp_twilio_from', ''));
            $from = $whatsapp_from !== '' ? $whatsapp_from : trim((string) get_option('mj_sms_twilio_from', ''));
            $from = self::normalize_number($from);

            if ($account_sid === '' || $auth_token === '' || $from === '') {
                return new WP_Error('mj_whatsapp_twilio_config', __('Configuration Twilio WhatsApp incomplète.', 'mj-member'));
            }

            $success = true;
            $errors = array();
            $failure_details = array();

            foreach ($phones as $phone) {
                $phone = trim((string) $phone);
                if ($phone === '') {
                    continue;
                }

                $response = wp_remote_post('https://api.twilio.com/2010-04-01/Accounts/' . $account_sid . '/Messages.json', array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token),
                    ),
                    'body' => array(
                        'From' => 'whatsapp:' . $from,
                        'To' => 'whatsapp:' . $phone,
                        'Body' => $message,
                    ),
                ));

                if (is_wp_error($response)) {
                    $success = false;
                    $error_msg = $response->get_error_message();
                    $errors[] = $error_msg;
                    $failure_details[$phone] = $error_msg;
                    continue;
                }

                $body = wp_remote_retrieve_body($response);
                $response_code = wp_remote_retrieve_response_code($response);

                if ($response_code < 200 || $response_code >= 300) {
                    $success = false;
                    $error_details = json_decode($body, true);
                    $error_msg = isset($error_details['message']) ? (string) $error_details['message'] : 'Erreur Twilio';
                    $errors[] = $error_msg;
                    $failure_details[$phone] = $error_msg;
                }
            }

            if (empty($errors)) {
                return array(
                    'success' => true,
                    'message' => __('Message WhatsApp envoyé via Twilio.', 'mj-member'),
                );
            }

            $errors = array_filter(array_unique($errors));
            return array(
                'success' => false,
                'error' => implode(' | ', $errors),
                'errors' => $errors,
                'failure_details' => $failure_details,
            );
        }

        return $gateway_response;
    }
}

add_filter('mj_member_whatsapp_send', array('Mj\Member\Classes\MjWhatsapp', 'filter_default_gateway'), 10, 6);

\class_alias(__NAMESPACE__ . '\\MjWhatsapp', 'MjWhatsapp');
