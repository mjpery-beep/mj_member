<?php

namespace Mj\Member\Classes\View\EventSingle;

use DateTime;
use function __;
use function array_map;
use function is_array;
use function is_string;
use function json_decode;
use function sanitize_key;
use function wp_date;
use function wp_json_encode;
use function wp_timezone;
use function get_option;

if (!defined('ABSPATH')) {
    exit;
}

final class PreviewContextFactory
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function apply(array $payload): array
    {
        if (empty($payload['is_preview'])) {
            return $payload;
        }

        $registration = $payload['registration'] ?? array();
        $registrationConfig = $payload['registration_config'] ?? array();
        $registrationConfigJson = isset($payload['registration_config_json']) ? (string) $payload['registration_config_json'] : '';
        $registrationParticipants = $payload['registration_participants'] ?? array();
        $registrationFormParticipants = $payload['registration_form_participants'] ?? array();
        $registrationHasIneligible = !empty($payload['registration_has_ineligible']);
        $registrationTotalCount = isset($payload['registration_total_count']) ? (int) $payload['registration_total_count'] : 0;
        $registrationRegisteredCount = isset($payload['registration_registered_count']) ? (int) $payload['registration_registered_count'] : 0;
        $registrationAvailableCount = isset($payload['registration_available_count']) ? (int) $payload['registration_available_count'] : 0;
        $registrationFormAvailableCount = isset($payload['registration_form_available_count']) ? (int) $payload['registration_form_available_count'] : 0;
        $registrationHasParticipants = !empty($payload['registration_has_participants']);
        $registrationAllRegistered = !empty($payload['registration_all_registered']);
        $registrationIsOpen = !empty($payload['registration_is_open']);
        $registrationRequiresLogin = !empty($payload['registration_requires_login']);
        $registrationOccurrenceCatalog = $payload['registration_occurrence_catalog'] ?? array();
        $occurrenceSelection = $payload['occurrence_selection'] ?? array();
        $occurrenceAssignments = $payload['occurrence_assignments'] ?? array();
        $allowOccurrenceSelection = !empty($payload['allow_occurrence_selection']);
        $occurrenceSelectionMode = isset($payload['occurrence_selection_mode']) ? (string) $payload['occurrence_selection_mode'] : '';

        if (!$registrationIsOpen) {
            $registrationIsOpen = true;
        }

        if ($registrationRequiresLogin) {
            $registrationRequiresLogin = false;
        }

        $previewConfig = array();
        if ($registrationConfigJson !== '') {
            $decoded = json_decode($registrationConfigJson, true);
            if (is_array($decoded)) {
                $previewConfig = $decoded;
            }
        } elseif (!empty($registrationConfig) && is_array($registrationConfig)) {
            $previewConfig = $registrationConfig;
        }

        if (empty($registrationFormParticipants)) {
            $previewParticipants = $this->buildPreviewParticipants();
            $registrationParticipants = $previewParticipants;
            $registrationFormParticipants = array_map(
                static function (array $entry): array {
                    return array(
                        'id' => isset($entry['id']) ? (int) $entry['id'] : 0,
                        'name' => isset($entry['name']) ? (string) $entry['name'] : '',
                        'is_registered' => false,
                        'status_label' => '',
                        'status_key' => '',
                        'status_class' => '',
                        'registration_id' => 0,
                        'eligible' => true,
                        'eligibility_label' => '',
                        'ineligible_reasons' => array(),
                        'ineligibleReasons' => array(),
                    );
                },
                $previewParticipants
            );

            $registrationTotalCount = count($previewParticipants);
            $registrationAvailableCount = $registrationTotalCount;
            $registrationRegisteredCount = 0;
            $registrationHasParticipants = true;
            $registrationAllRegistered = false;
            $registrationFormAvailableCount = count($registrationFormParticipants);
            $registrationHasIneligible = false;

            $previewConfig['participants'] = $previewParticipants;
            $previewConfig['hasParticipants'] = true;
            $previewConfig['hasAvailableParticipants'] = true;
            $previewConfig['allRegistered'] = false;
            $previewConfig['hasIneligibleParticipants'] = false;
            $previewConfig['ineligibleCount'] = 0;
        }

        if (empty($registrationOccurrenceCatalog) || !is_array($registrationOccurrenceCatalog)) {
            $previewOccurrences = $this->buildPreviewOccurrences();
            $registrationOccurrenceCatalog = array();
            foreach ($previewOccurrences as $entry) {
                $slug = isset($entry['slug']) ? (string) $entry['slug'] : ('occ_' . md5($entry['start'] ?? microtime()));
                $registrationOccurrenceCatalog[sanitize_key($slug)] = $entry;
            }

            $occurrenceSelection = array_values($registrationOccurrenceCatalog);
            $occurrenceAssignments = array(
                'mode' => 'custom',
                'occurrences' => array_map(
                    static fn (array $entry): string => isset($entry['slug']) ? (string) $entry['slug'] : '',
                    $occurrenceSelection
                ),
            );

            $allowOccurrenceSelection = true;
            $occurrenceSelectionMode = 'member_choice';

            $previewConfig['occurrences'] = $occurrenceSelection;
            $previewConfig['assignments'] = $occurrenceAssignments;
            $previewConfig['scheduleMode'] = isset($previewConfig['scheduleMode']) ? $previewConfig['scheduleMode'] : 'recurring';
            $previewConfig['hasOccurrences'] = true;
            $previewConfig['allowOccurrenceSelection'] = true;
            $previewConfig['occurrenceSelectionMode'] = 'member_choice';
        }

        if (!empty($previewConfig)) {
            $registrationConfigJson = wp_json_encode($previewConfig);
            if (is_string($registrationConfigJson)) {
                $registration['config'] = $previewConfig;
                $registrationConfig = $previewConfig;
            } else {
                $registrationConfigJson = '';
            }
        }

        $registration['is_open'] = $registrationIsOpen;
        $registration['requires_login'] = $registrationRequiresLogin;
        $registration['has_participants'] = $registrationHasParticipants;
        $registration['all_registered'] = $registrationAllRegistered;
        $registration['registered_count'] = $registrationRegisteredCount;
        $registration['available_count'] = $registrationAvailableCount;
        $registration['total_count'] = $registrationTotalCount;
        $registration['has_ineligible'] = $registrationHasIneligible;
        $registration['participants'] = $registrationParticipants;
        $registration['occurrences'] = $occurrenceSelection;
        $registration['assignments'] = $occurrenceAssignments;
        $registration['occurrence_selection_mode'] = $occurrenceSelectionMode;
        $registration['allow_occurrence_selection'] = $allowOccurrenceSelection;

        if ($registrationConfigJson !== '') {
            $payload['registration_has_interactive'] = true;
        }

        $payload['registration'] = $registration;
        $payload['registration_config'] = $registrationConfig;
        $payload['registration_config_json'] = $registrationConfigJson;
        $payload['registration_participants'] = $registrationParticipants;
        $payload['registration_form_participants'] = $registrationFormParticipants;
        $payload['registration_total_count'] = $registrationTotalCount;
        $payload['registration_available_count'] = $registrationAvailableCount;
        $payload['registration_registered_count'] = $registrationRegisteredCount;
        $payload['registration_has_participants'] = $registrationHasParticipants;
        $payload['registration_all_registered'] = $registrationAllRegistered;
        $payload['registration_form_available_count'] = $registrationFormAvailableCount;
        $payload['registration_has_ineligible'] = $registrationHasIneligible;
        $payload['registration_occurrence_catalog'] = $registrationOccurrenceCatalog;
        $payload['occurrence_selection'] = $occurrenceSelection;
        $payload['occurrence_assignments'] = $occurrenceAssignments;
        $payload['allow_occurrence_selection'] = $allowOccurrenceSelection;
        $payload['occurrence_selection_mode'] = $occurrenceSelectionMode;
        $payload['registration_is_open'] = $registrationIsOpen;
        $payload['registration_requires_login'] = $registrationRequiresLogin;

        return $payload;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildPreviewParticipants(): array
    {
        return array(
            array(
                'id' => 91001,
                'name' => __('Alex Démo', 'mj-member'),
                'label' => __('Alex Démo', 'mj-member'),
                'fullName' => __('Alex Démo', 'mj-member'),
                'first_name' => __('Alex', 'mj-member'),
                'last_name' => __('Démo', 'mj-member'),
                'registrationId' => 0,
                'registration_id' => 0,
                'registrationStatus' => '',
                'registrationStatusLabel' => '',
                'isRegistered' => false,
                'eligible' => true,
                'isEligible' => 1,
                'ineligibleReasons' => array(),
                'type' => 'jeune',
                'role' => 'jeune',
            ),
            array(
                'id' => 91002,
                'name' => __('Charlie Démo', 'mj-member'),
                'label' => __('Charlie Démo', 'mj-member'),
                'fullName' => __('Charlie Démo', 'mj-member'),
                'first_name' => __('Charlie', 'mj-member'),
                'last_name' => __('Démo', 'mj-member'),
                'registrationId' => 0,
                'registration_id' => 0,
                'registrationStatus' => '',
                'registrationStatusLabel' => '',
                'isRegistered' => false,
                'eligible' => true,
                'isEligible' => 1,
                'ineligibleReasons' => array(),
                'type' => 'jeune',
                'role' => 'jeune',
            ),
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildPreviewOccurrences(): array
    {
        $timezone = wp_timezone();
        $base = new DateTime('now', $timezone);
        $occurrences = array();

        for ($index = 0; $index < 3; $index++) {
            $occurrenceDate = clone $base;
            $occurrenceDate->modify('+' . (3 + ($index * 2)) . ' days');
            $occurrenceDate->setTime(18, 0, 0);

            $timestamp = $occurrenceDate->getTimestamp();
            $start = $occurrenceDate->format('Y-m-d H:i:s');
            $slug = 'preview-occurrence-' . ($index + 1);
            $label = sprintf(
                __('%s à %s', 'mj-member'),
                wp_date(get_option('date_format'), $timestamp),
                wp_date(get_option('time_format'), $timestamp)
            );

            $occurrences[] = array(
                'start' => $start,
                'slug' => $slug,
                'label' => $label,
                'timestamp' => $timestamp,
                'isPast' => false,
                'isToday' => false,
            );
        }

        return $occurrences;
    }
}
