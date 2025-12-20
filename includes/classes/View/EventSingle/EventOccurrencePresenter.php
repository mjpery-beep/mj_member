<?php

namespace Mj\Member\Classes\View\EventSingle;

use DateTime;
use MjEventRegistrations;
use function __;
use function _n;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function current_time;
use function date_i18n;
use function get_option;
use function is_array;
use function is_numeric;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;
use function mj_member_get_event_recurring_summary;
use function sprintf;
use function strtotime;
use function ucfirst;
use function wp_date;
use function wp_json_encode;

if (!defined('ABSPATH')) {
    exit;
}

final class EventOccurrencePresenter
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function present(array $payload): array
    {
        $event = $payload['event'] ?? array();
        $registration = $payload['registration'] ?? array();
        $registrationConfig = $payload['registration_config'] ?? array();
        $registrationConfigJson = isset($payload['registration_config_json']) ? (string) $payload['registration_config_json'] : '';
        $occurrenceScheduleSummary = isset($payload['occurrence_schedule_summary']) ? (string) $payload['occurrence_schedule_summary'] : '';
        $occurrenceSelection = $payload['occurrence_selection'] ?? array();
        $registrationOccurrenceCatalog = $payload['registration_occurrence_catalog'] ?? array();
        $occurrencePreview = $payload['occurrence_preview'] ?? array();
        $occurrenceItems = $payload['occurrence_items'] ?? array();
        $occurrenceRemaining = isset($payload['occurrence_remaining']) ? (int) $payload['occurrence_remaining'] : 0;
        $occurrenceNext = isset($payload['occurrence_next']) ? (string) $payload['occurrence_next'] : '';
        $occurrenceNextLabel = isset($payload['occurrence_next_label']) ? (string) $payload['occurrence_next_label'] : '';
        $registrationParticipants = $payload['registration_participants'] ?? array();
        $allowedMemberLookup = $payload['allowed_member_lookup'] ?? array();
        $registrationStatusLabels = $payload['registration_status_labels'] ?? array();
        $registrationHasIneligible = !empty($payload['registration_has_ineligible']);

        $occurrenceData = $this->buildOccurrenceSummary(
            $occurrenceScheduleSummary,
            $occurrenceSelection,
            $registration,
            $registrationOccurrenceCatalog,
            $event
        );

        $occurrenceScheduleSummary = $occurrenceData['summary'];
        $registrationConfig = $occurrenceData['registration_config'];
        $registrationConfigJson = $occurrenceData['registration_config_json'];
        $registration = $occurrenceData['registration'];

        $occurrencePreview = $this->ensureOccurrencePreview($occurrencePreview, $event);
        $occurrenceItems = $this->ensureOccurrenceItems($occurrenceItems, $occurrencePreview);
        $occurrenceRemaining = $this->resolveOccurrenceRemaining($occurrenceRemaining, $occurrencePreview);

        $nextData = $this->resolveNextOccurrence($occurrenceNext, $occurrenceNextLabel, $occurrencePreview, $occurrenceItems);
        $occurrenceNext = $nextData['occurrence_next'];
        $occurrenceNextLabel = $nextData['occurrence_next_label'];
        $nextNormalized = $nextData['next_entry'];

        $eventHasMultipleOccurrences = $this->determineMultipleOccurrences($payload, $occurrencePreview, $occurrenceItems, $occurrenceRemaining);

        $occurrencePreview = array(
            'items' => $occurrenceItems,
            'remaining' => $occurrenceRemaining,
            'has_multiple' => $eventHasMultipleOccurrences,
            'next' => $nextNormalized,
        );

        $reservationData = $this->buildReservations(
            $registrationParticipants,
            $allowedMemberLookup,
            $registrationStatusLabels,
            $registrationOccurrenceCatalog,
            isset($payload['current_member_id']) ? (int) $payload['current_member_id'] : 0
        );

        if ($reservationData['registration_has_ineligible']) {
            $registrationHasIneligible = true;
        }

        $payload['registration'] = $registration;
        $payload['registration_config'] = $registrationConfig;
        $payload['registration_config_json'] = $registrationConfigJson;
        $payload['occurrence_schedule_summary'] = $occurrenceScheduleSummary;
        $payload['occurrence_preview'] = $occurrencePreview;
        $payload['occurrence_items'] = $occurrenceItems;
        $payload['occurrence_remaining'] = $occurrenceRemaining;
        $payload['occurrence_next'] = $occurrenceNext;
        $payload['occurrence_next_label'] = $occurrenceNextLabel;
        $payload['event_has_multiple_occurrences'] = $eventHasMultipleOccurrences;
        $payload['registration_reservations'] = $reservationData['reservations'];
        $payload['registration_has_reservations'] = $reservationData['has_reservations'];
        $payload['registration_has_ineligible'] = $registrationHasIneligible;

        return $payload;
    }

    /**
     * @param array<string,mixed> $registration
     * @param array<string,mixed> $registrationOccurrenceCatalog
     * @param array<string,mixed> $event
     * @return array{
     *     summary:string,
     *     registration_config:array<string,mixed>,
     *     registration_config_json:string,
     *     registration:array<string,mixed>
     * }
     */
    private function buildOccurrenceSummary(
        string $occurrenceScheduleSummary,
        array $occurrenceSelection,
        array $registration,
        array $registrationOccurrenceCatalog,
        array $event
    ): array {
        $registrationConfig = isset($registration['config']) && is_array($registration['config']) ? $registration['config'] : array();
        $registrationConfigJson = isset($registration['config_json']) ? (string) $registration['config_json'] : '';

        if ($occurrenceScheduleSummary === '') {
            $occurrenceEntries = $this->collectOccurrenceEntries($occurrenceSelection, $registration, $registrationOccurrenceCatalog);
            $occurrenceScheduleSummary = $this->resolveScheduleSummary($occurrenceEntries, $event);
        }

        if ($occurrenceScheduleSummary !== '') {
            $occurrenceScheduleSummary = sanitize_text_field($occurrenceScheduleSummary);
            $registrationConfig['occurrence_summary'] = $occurrenceScheduleSummary;
            $registrationConfig['occurrenceSummary'] = $occurrenceScheduleSummary;
            $registrationConfig['occurrenceSummaryText'] = $occurrenceScheduleSummary;

            $registration['occurrence_summary'] = $occurrenceScheduleSummary;
            $registration['occurrenceSummary'] = $occurrenceScheduleSummary;
            $registration['occurrenceSummaryText'] = $occurrenceScheduleSummary;
            $registration['config'] = $registrationConfig;

            $encoded = wp_json_encode($registrationConfig);
            if (is_string($encoded)) {
                $registrationConfigJson = $encoded;
            }
        }

        return array(
            'summary' => $occurrenceScheduleSummary,
            'registration_config' => $registrationConfig,
            'registration_config_json' => $registrationConfigJson,
            'registration' => $registration,
        );
    }

    /**
     * @param array<int,array<string,mixed>> $occurrenceSelection
     * @param array<string,mixed> $registration
     * @param array<string,mixed> $registrationOccurrenceCatalog
     * @return array<int,array<string,mixed>>
     */
    private function collectOccurrenceEntries(
        array $occurrenceSelection,
        array $registration,
        array $registrationOccurrenceCatalog
    ): array {
        if (!empty($occurrenceSelection)) {
            return $occurrenceSelection;
        }

        if (!empty($registration['occurrences']) && is_array($registration['occurrences'])) {
            return $registration['occurrences'];
        }

        if (!empty($registrationOccurrenceCatalog) && is_array($registrationOccurrenceCatalog)) {
            return array_values($registrationOccurrenceCatalog);
        }

        return array();
    }

    /**
     * @param array<int,array<string,mixed>> $occurrenceEntries
     * @param array<string,mixed> $event
     */
    private function resolveScheduleSummary(array $occurrenceEntries, array $event): string
    {
        if (!empty($event) && function_exists('mj_member_get_event_recurring_summary')) {
            $recurringSummary = mj_member_get_event_recurring_summary($event);
            if ($recurringSummary !== '') {
                return sanitize_text_field($recurringSummary);
            }
        }

        $bestLabel = '';
        $remainingCount = 0;

        if (!empty($occurrenceEntries)) {
            $nowTs = current_time('timestamp');
            $dateFormat = get_option('date_format', 'd/m/Y');
            $timeFormat = get_option('time_format', 'H:i');
            $future = array();
            $past = array();

            foreach ($occurrenceEntries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $timestamp = 0;
                if (isset($entry['timestamp']) && is_numeric($entry['timestamp'])) {
                    $timestamp = (int) $entry['timestamp'];
                } elseif (!empty($entry['start'])) {
                    $timestamp = strtotime((string) $entry['start']);
                }

                $label = '';
                if (!empty($entry['label'])) {
                    $label = sanitize_text_field((string) $entry['label']);
                }

                if ($label === '' && !empty($entry['start'])) {
                    $timestampForFormat = $timestamp > 0 ? $timestamp : strtotime((string) $entry['start']);
                    if ($timestampForFormat) {
                        $label = sprintf(
                            __('Le %1$s à %2$s', 'mj-member'),
                            wp_date($dateFormat, $timestampForFormat),
                            wp_date($timeFormat, $timestampForFormat)
                        );
                    } else {
                        $label = sanitize_text_field((string) $entry['start']);
                    }
                }

                if ($label === '') {
                    continue;
                }

                $normalized = array(
                    'label' => $label,
                    'timestamp' => $timestamp > 0 ? $timestamp : null,
                );

                if ($timestamp > $nowTs) {
                    $future[] = $normalized;
                } else {
                    $past[] = $normalized;
                }
            }

            $sorter = static function (array &$list): void {
                usort(
                    $list,
                    static function ($left, $right): int {
                        $leftTs = isset($left['timestamp']) && $left['timestamp'] ? (int) $left['timestamp'] : PHP_INT_MAX;
                        $rightTs = isset($right['timestamp']) && $right['timestamp'] ? (int) $right['timestamp'] : PHP_INT_MAX;

                        if ($leftTs === $rightTs) {
                            return strcasecmp((string) $left['label'], (string) $right['label']);
                        }

                        return ($leftTs < $rightTs) ? -1 : 1;
                    }
                );
            };

            if (!empty($future)) {
                $sorter($future);
                $bestLabel = $future[0]['label'];
                $remainingCount = max(0, count($future) - 1);
            } elseif (!empty($past)) {
                $sorter($past);
                $bestLabel = $past[0]['label'];
                $remainingCount = 0;
            }
        }

        if ($bestLabel === '') {
            $startRaw = isset($event['start_date']) ? (string) $event['start_date'] : '';
            $endRaw = isset($event['end_date']) ? (string) $event['end_date'] : '';
            if ($startRaw !== '') {
                $startTs = strtotime($startRaw);
                $endTs = ($endRaw !== '') ? strtotime($endRaw) : false;

                if ($startTs) {
                    $dateFormat = get_option('date_format', 'd/m/Y');
                    $timeFormat = get_option('time_format', 'H:i');
                    $dateLabel = wp_date($dateFormat, $startTs);
                    $timeLabel = wp_date($timeFormat, $startTs);

                    if ($endTs && wp_date('Y-m-d', $startTs) === wp_date('Y-m-d', $endTs)) {
                        $endTime = wp_date($timeFormat, $endTs);
                        $bestLabel = sprintf(
                            __('Le %1$s de %2$s à %3$s', 'mj-member'),
                            $dateLabel,
                            $timeLabel,
                            $endTime
                        );
                    } elseif ($endTs) {
                        $endDate = wp_date($dateFormat, $endTs);
                        $bestLabel = sprintf(
                            __('Du %1$s au %2$s', 'mj-member'),
                            $dateLabel,
                            $endDate
                        );
                    } else {
                        $bestLabel = sprintf(
                            __('Le %1$s à %2$s', 'mj-member'),
                            $dateLabel,
                            $timeLabel
                        );
                    }
                }
            }
        }

        if ($bestLabel === '') {
            return '';
        }

        if ($remainingCount > 0) {
            return sprintf(
                _n(
                    '%1$s - %2$d autre créneau',
                    '%1$s - %2$d autres créneaux',
                    $remainingCount,
                    'mj-member'
                ),
                $bestLabel,
                $remainingCount
            );
        }

        return $bestLabel;
    }

    /**
     * @param array<string,mixed> $occurrencePreview
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    private function ensureOccurrencePreview(array $occurrencePreview, array $event): array
    {
        if (!empty($occurrencePreview)) {
            return $occurrencePreview;
        }

        if (function_exists('mj_member_prepare_event_occurrences_preview')) {
            $candidate = mj_member_prepare_event_occurrences_preview($event, array(
                'max' => 4,
                'include_past' => false,
            ));

            if (is_array($candidate) && !empty($candidate)) {
                return $candidate;
            }
        }

        return array();
    }

    /**
     * @param array<int,array<string,mixed>> $occurrenceItems
     * @param array<string,mixed> $occurrencePreview
     * @return array<int,array<string,mixed>>
     */
    private function ensureOccurrenceItems(array $occurrenceItems, array $occurrencePreview): array
    {
        if (!empty($occurrenceItems)) {
            return $occurrenceItems;
        }

        if (empty($occurrencePreview['items']) || !is_array($occurrencePreview['items'])) {
            return array();
        }

        $items = array();
        foreach ($occurrencePreview['items'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $startValue = isset($entry['start']) ? (string) $entry['start'] : '';
            $endValue = isset($entry['end']) ? (string) $entry['end'] : $startValue;
            $timestampValue = 0;
            if (isset($entry['timestamp']) && is_numeric($entry['timestamp'])) {
                $timestampValue = (int) $entry['timestamp'];
            } elseif ($startValue !== '') {
                $timestampCandidate = strtotime($startValue);
                if ($timestampCandidate) {
                    $timestampValue = (int) $timestampCandidate;
                }
            }

            $items[] = array(
                'label' => isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '',
                'start' => $startValue,
                'end' => $endValue,
                'timestamp' => $timestampValue,
                'isPast' => !empty($entry['isPast']),
                'isToday' => !empty($entry['isToday']),
            );
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $occurrencePreview
     */
    private function resolveOccurrenceRemaining(int $occurrenceRemaining, array $occurrencePreview): int
    {
        if ((!$occurrenceRemaining || $occurrenceRemaining < 0) && isset($occurrencePreview['remaining'])) {
            return max(0, (int) $occurrencePreview['remaining']);
        }

        return max(0, $occurrenceRemaining);
    }

    /**
     * @param array<string,mixed> $occurrencePreview
     * @param array<int,array<string,mixed>> $occurrenceItems
     * @return array{
     *     occurrence_next:string,
     *     occurrence_next_label:string,
     *     next_entry:array<string,mixed>
     * }
     */
    private function resolveNextOccurrence(
        string $occurrenceNext,
        string $occurrenceNextLabel,
        array $occurrencePreview,
        array $occurrenceItems
    ): array {
        $nextEntry = array();
        if (!empty($occurrencePreview['next']) && is_array($occurrencePreview['next'])) {
            $nextEntry = $occurrencePreview['next'];
        } elseif (!empty($occurrenceItems)) {
            $nextEntry = $occurrenceItems[0];
        }

        $nextNormalized = array();
        if (!empty($nextEntry) && is_array($nextEntry)) {
            $labelCandidate = isset($nextEntry['label']) ? sanitize_text_field((string) $nextEntry['label']) : '';
            $startCandidate = isset($nextEntry['start']) ? (string) $nextEntry['start'] : '';
            $timestampCandidate = 0;
            if (isset($nextEntry['timestamp']) && is_numeric($nextEntry['timestamp'])) {
                $timestampCandidate = (int) $nextEntry['timestamp'];
            } elseif ($startCandidate !== '') {
                $timestampCandidate = strtotime($startCandidate) ?: 0;
            }

            if ($occurrenceNextLabel === '' && $labelCandidate !== '') {
                $occurrenceNextLabel = $labelCandidate;
            }

            if ($occurrenceNext === '' && $startCandidate !== '') {
                $occurrenceNext = $startCandidate;
            }

            $nextNormalized = array(
                'label' => $labelCandidate,
                'start' => $startCandidate,
                'timestamp' => $timestampCandidate,
            );
        }

        return array(
            'occurrence_next' => $occurrenceNext,
            'occurrence_next_label' => $occurrenceNextLabel,
            'next_entry' => $nextNormalized,
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $occurrencePreview
     * @param array<int,array<string,mixed>> $occurrenceItems
     */
    private function determineMultipleOccurrences(
        array $payload,
        array $occurrencePreview,
        array $occurrenceItems,
        int $occurrenceRemaining
    ): bool {
        if (isset($payload['event_has_multiple_occurrences']) && is_numeric($payload['event_has_multiple_occurrences'])) {
            $candidate = (bool) $payload['event_has_multiple_occurrences'];
            if ($candidate) {
                return true;
            }
        }

        if ($occurrenceRemaining > 0 || count($occurrenceItems) > 1) {
            return true;
        }

        if (!empty($occurrencePreview['has_multiple'])) {
            return true;
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $registrationParticipants
     * @param array<int,bool> $allowedMemberLookup
     * @param array<string,string> $registrationStatusLabels
     * @param array<string,mixed> $registrationOccurrenceCatalog
     * @return array{
     *     reservations:array<int,array<string,mixed>>,
     *     has_reservations:bool,
     *     registration_has_ineligible:bool
     * }
     */
    private function buildReservations(
        array $registrationParticipants,
        array $allowedMemberLookup,
        array $registrationStatusLabels,
        array $registrationOccurrenceCatalog,
        int $currentMemberId
    ): array {
        if (empty($registrationParticipants)) {
            return array(
                'reservations' => array(),
                'has_reservations' => false,
                'registration_has_ineligible' => false,
            );
        }

        $cancelledStatusKey = '';
        if (class_exists(MjEventRegistrations::class) && defined('MjEventRegistrations::STATUS_CANCELLED')) {
            $cancelledStatusKey = sanitize_key((string) MjEventRegistrations::STATUS_CANCELLED);
        }

        $reservations = array();
        $registrationHasIneligible = false;

        foreach ($registrationParticipants as $participantEntry) {
            if (!is_array($participantEntry)) {
                continue;
            }

            $participantMemberId = 0;
            if (isset($participantEntry['member_id'])) {
                $participantMemberId = (int) $participantEntry['member_id'];
            } elseif (isset($participantEntry['memberId'])) {
                $participantMemberId = (int) $participantEntry['memberId'];
            } elseif (isset($participantEntry['id'])) {
                $participantMemberId = (int) $participantEntry['id'];
            }

            $guardianId = 0;
            if (isset($participantEntry['guardian_id'])) {
                $guardianId = (int) $participantEntry['guardian_id'];
            } elseif (isset($participantEntry['guardianId'])) {
                $guardianId = (int) $participantEntry['guardianId'];
            }

            $participantType = isset($participantEntry['type']) ? sanitize_key((string) $participantEntry['type']) : '';
            $ownsParticipant = $this->participantOwned($participantEntry, $participantMemberId, $guardianId, $allowedMemberLookup, $participantType, $currentMemberId);

            if (!$ownsParticipant) {
                continue;
            }

            $statusKey = '';
            if (!empty($participantEntry['registrationStatus'])) {
                $statusKey = sanitize_key((string) $participantEntry['registrationStatus']);
            } elseif (!empty($participantEntry['status'])) {
                $statusKey = sanitize_key((string) $participantEntry['status']);
            } elseif (!empty($participantEntry['statut'])) {
                $statusKey = sanitize_key((string) $participantEntry['statut']);
            }

            if ($statusKey !== '' && $cancelledStatusKey !== '' && $statusKey === $cancelledStatusKey) {
                continue;
            }

            $registrationId = 0;
            if (isset($participantEntry['registrationId'])) {
                $registrationId = (int) $participantEntry['registrationId'];
            } elseif (isset($participantEntry['registration_id'])) {
                $registrationId = (int) $participantEntry['registration_id'];
            }

            $isRegistered = !empty($participantEntry['isRegistered']) || $registrationId > 0 || $statusKey !== '';
            if (!$isRegistered) {
                continue;
            }

            $participantName = RegistrationParticipantsNormalizer::resolveParticipantName($participantEntry, $participantMemberId);
            $statusLabel = RegistrationParticipantsNormalizer::resolveStatusLabel($participantEntry, $statusKey, $registrationStatusLabels);
            $statusClass = $statusKey !== '' ? 'is-status-' . sanitize_html_class($statusKey) : '';

            $createdLabel = '';
            $createdRaw = '';
            if (!empty($participantEntry['registrationCreatedAt'])) {
                $createdRaw = (string) $participantEntry['registrationCreatedAt'];
            } elseif (!empty($participantEntry['created_at'])) {
                $createdRaw = (string) $participantEntry['created_at'];
            }
            if ($createdRaw !== '') {
                $timestamp = strtotime($createdRaw);
                if ($timestamp) {
                    $createdLabel = date_i18n(get_option('date_format'), $timestamp);
                }
            }

            $occurrenceTexts = $this->resolveReservationOccurrences($participantEntry, $registrationOccurrenceCatalog);
            if (!empty($participantEntry['eligible']) && !$participantEntry['eligible']) {
                $registrationHasIneligible = true;
            }

            $reservations[] = array(
                'name' => $participantName,
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'status_key' => $statusKey,
                'created_label' => $createdLabel,
                'created_raw' => $createdRaw,
                'occurrences' => $occurrenceTexts,
                'member_id' => $participantMemberId,
                'registration_id' => $registrationId,
                'can_cancel' => $ownsParticipant && $registrationId > 0,
            );
        }

        return array(
            'reservations' => $reservations,
            'has_reservations' => !empty($reservations),
            'registration_has_ineligible' => $registrationHasIneligible,
        );
    }

    /**
     * @param array<string,mixed> $participantEntry
     * @param array<int,bool> $allowedMemberLookup
     */
    private function participantOwned(
        array $participantEntry,
        int $participantMemberId,
        int $guardianId,
        array $allowedMemberLookup,
        string $participantType,
        int $currentMemberId
    ): bool {
        if (!empty($allowedMemberLookup)) {
            if ($participantMemberId > 0 && isset($allowedMemberLookup[$participantMemberId])) {
                return true;
            }

            if ($guardianId > 0 && isset($allowedMemberLookup[$guardianId])) {
                return true;
            }

            if (!empty($participantEntry['isSelf']) && $currentMemberId > 0) {
                return true;
            }

            return false;
        }

        return !empty($participantEntry['isSelf']) || ($participantType === 'child');
    }

    /**
     * @param array<string,mixed> $participantEntry
     * @param array<string,mixed> $registrationOccurrenceCatalog
     * @return array<int,string>
     */
    private function resolveReservationOccurrences(array $participantEntry, array $registrationOccurrenceCatalog): array
    {
        $occurrenceTexts = array();
        $assignments = array();
        if (isset($participantEntry['occurrenceAssignments']) && is_array($participantEntry['occurrenceAssignments'])) {
            $assignments = $participantEntry['occurrenceAssignments'];
        } elseif (isset($participantEntry['occurrence_assignments']) && is_array($participantEntry['occurrence_assignments'])) {
            $assignments = $participantEntry['occurrence_assignments'];
        }

        $assignmentsMode = isset($assignments['mode']) ? sanitize_key((string) $assignments['mode']) : 'all';
        $assignedValues = isset($assignments['occurrences']) && is_array($assignments['occurrences']) ? $assignments['occurrences'] : array();

        if ($assignmentsMode === 'custom' && !empty($assignedValues)) {
            foreach ($assignedValues as $assignedSlug) {
                $assignedSlugKey = sanitize_key((string) $assignedSlug);
                if ($assignedSlugKey === '' || !isset($registrationOccurrenceCatalog[$assignedSlugKey])) {
                    continue;
                }
                $occurrenceEntry = $registrationOccurrenceCatalog[$assignedSlugKey];
                if (!empty($occurrenceEntry['label'])) {
                    $occurrenceTexts[] = sanitize_text_field((string) $occurrenceEntry['label']);
                }
            }
        }

        if (empty($occurrenceTexts)) {
            if ($assignmentsMode === 'custom') {
                $occurrenceTexts[] = __('Occurrences à confirmer', 'mj-member');
            } else {
                if (count($registrationOccurrenceCatalog) === 1) {
                    $single = reset($registrationOccurrenceCatalog);
                    if (is_array($single) && !empty($single['label'])) {
                        $occurrenceTexts[] = sanitize_text_field((string) $single['label']);
                    }
                    reset($registrationOccurrenceCatalog);
                }
            }
        } else {
            $occurrenceTexts = array_values(array_map('sanitize_text_field', array_unique($occurrenceTexts)));
        }

        return $occurrenceTexts;
    }
}
