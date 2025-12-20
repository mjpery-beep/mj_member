<?php

namespace Mj\Member\Classes\View\EventSingle;

use function __;
use function array_key_exists;
use function is_array;
use function is_numeric;
use function sanitize_html_class;
use function sanitize_key;
use function sanitize_text_field;

if (!defined('ABSPATH')) {
    exit;
}

final class RegistrationParticipantsNormalizer
{
    /**
     * Normalize the participant-related data so templates can rely on consistent structures.
     *
     * @param array<int,array<string,mixed>> $registrationParticipants
     * @param array<string,string> $registrationStatusLabels
     * @param array<string,mixed> $registration
     * @param array<string,mixed> $registrationConfig
     * @param object|null $currentMember
     * @return array{
     *     registration_can_manage_children:bool,
     *     allowed_member_lookup:array<int,bool>,
     *     registration_note_max_length:int,
     *     registration_form_participants:array<int,array<string,mixed>>,
     *     registration_form_available_count:int,
     *     registration_has_ineligible:bool
     * }
     */
    public function normalize(
        array $registrationParticipants,
        array $registrationStatusLabels,
        array $registration,
        array $registrationConfig,
        ?object $currentMember,
        int $currentMemberId
    ): array {
        $allowedMemberLookup = array();
        if ($currentMemberId > 0) {
            $allowedMemberLookup[$currentMemberId] = true;
        }

        $registrationCanManageChildren = false;
        if (
            $currentMember
            && function_exists('mj_member_can_manage_children')
            && function_exists('mj_member_get_guardian_children')
            && mj_member_can_manage_children($currentMember)
        ) {
            $registrationCanManageChildren = true;
            $children = mj_member_get_guardian_children($currentMember);
            if (!empty($children) && is_array($children)) {
                foreach ($children as $childEntry) {
                    $childId = 0;
                    if (is_object($childEntry) && isset($childEntry->id)) {
                        $childId = (int) $childEntry->id;
                    } elseif (is_array($childEntry) && isset($childEntry['id'])) {
                        $childId = (int) $childEntry['id'];
                    }

                    if ($childId > 0) {
                        $allowedMemberLookup[$childId] = true;
                    }
                }
            }
        }

        $registrationNoteMaxLength = 400;
        if (isset($registrationConfig['noteMaxLength'])) {
            $candidate = (int) $registrationConfig['noteMaxLength'];
            if ($candidate > 0) {
                $registrationNoteMaxLength = $candidate;
            }
        }

        $registrationHasIneligible = !empty($registration['has_ineligible']);
        $registrationFormParticipants = array();
        $registrationFormAvailableCount = 0;

        if (!empty($registrationParticipants)) {
            foreach ($registrationParticipants as $participantEntry) {
                if (!is_array($participantEntry)) {
                    continue;
                }

                $participantId = self::resolveParticipantId($participantEntry);
                if ($participantId <= 0) {
                    continue;
                }

                $guardianId = self::resolveGuardianId($participantEntry);
                $participantType = isset($participantEntry['type']) ? sanitize_key((string) $participantEntry['type']) : '';

                $ownsParticipant = self::ownsParticipant(
                    $participantId,
                    $guardianId,
                    $participantEntry,
                    $allowedMemberLookup,
                    $participantType,
                    $currentMemberId
                );

                if (!$ownsParticipant) {
                    continue;
                }

                $participantName = self::resolveParticipantName($participantEntry, $participantId);
                $statusKey = self::resolveStatusKey($participantEntry);
                $registrationId = self::resolveRegistrationId($participantEntry);
                $isRegistered = !empty($participantEntry['isRegistered']) || $registrationId > 0 || $statusKey !== '';

                $statusLabel = self::resolveStatusLabel($participantEntry, $statusKey, $registrationStatusLabels);
                $statusClass = $statusKey !== '' ? 'is-status-' . sanitize_html_class($statusKey) : '';

                $participantEligible = self::isParticipantEligible($participantEntry);
                $participantReasons = self::resolveIneligibleReasons($participantEntry);

                if (!$participantEligible) {
                    $registrationHasIneligible = true;
                    $statusClass = trim($statusClass . ' is-ineligible');
                }

                $eligibilityLabel = !$participantEligible ? __('Conditions non respectées', 'mj-member') : '';

                $registrationFormParticipants[] = array(
                    'id' => $participantId,
                    'name' => $participantName,
                    'is_registered' => $isRegistered,
                    'status_label' => $statusLabel,
                    'status_key' => $statusKey,
                    'status_class' => $statusClass,
                    'registration_id' => $registrationId,
                    'eligible' => $participantEligible,
                    'eligibility_label' => $eligibilityLabel,
                    'ineligible_reasons' => $participantReasons,
                    'ineligibleReasons' => $participantReasons,
                );

                if (!$isRegistered && $participantEligible) {
                    $registrationFormAvailableCount++;
                }
            }
        }

        return array(
            'registration_can_manage_children' => $registrationCanManageChildren,
            'allowed_member_lookup' => $allowedMemberLookup,
            'registration_note_max_length' => $registrationNoteMaxLength,
            'registration_form_participants' => $registrationFormParticipants,
            'registration_form_available_count' => $registrationFormAvailableCount,
            'registration_has_ineligible' => $registrationHasIneligible,
        );
    }

    /**
     * @param array<string,mixed> $participantEntry
     */
    private static function resolveParticipantId(array $participantEntry): int
    {
        foreach (array('id', 'member_id', 'memberId') as $key) {
            if (isset($participantEntry[$key])) {
                $candidate = (int) $participantEntry[$key];
                if ($candidate > 0) {
                    return $candidate;
                }
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $participantEntry
     */
    private static function resolveGuardianId(array $participantEntry): int
    {
        if (isset($participantEntry['guardian_id'])) {
            return (int) $participantEntry['guardian_id'];
        }

        if (isset($participantEntry['guardianId'])) {
            return (int) $participantEntry['guardianId'];
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $participantEntry
     * @param array<int,bool> $allowedMemberLookup
     */
    private static function ownsParticipant(
        int $participantId,
        int $guardianId,
        array $participantEntry,
        array $allowedMemberLookup,
        string $participantType,
        int $currentMemberId
    ): bool {
        if (!empty($allowedMemberLookup)) {
            if ($participantId > 0 && isset($allowedMemberLookup[$participantId])) {
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
     */
    public static function resolveParticipantName(array $participantEntry, int $participantId): string
    {
        $name = isset($participantEntry['name']) ? trim((string) $participantEntry['name']) : '';

        if ($name === '' && !empty($participantEntry['label'])) {
            $name = trim((string) $participantEntry['label']);
        }

        if ($name === '' && !empty($participantEntry['fullName'])) {
            $name = trim((string) $participantEntry['fullName']);
        }

        if ($name === '' && (!empty($participantEntry['first_name']) || !empty($participantEntry['last_name']))) {
            $first = !empty($participantEntry['first_name']) ? trim((string) $participantEntry['first_name']) : '';
            $last = !empty($participantEntry['last_name']) ? trim((string) $participantEntry['last_name']) : '';
            $name = trim($first . ' ' . $last);
        }

        if ($name === '' && $participantId > 0) {
            $name = sprintf(__('Participant #%d', 'mj-member'), $participantId);
        }

        return $name === '' ? __('Participant', 'mj-member') : $name;
    }

    /**
     * @param array<string,mixed> $participantEntry
     */
    private static function resolveStatusKey(array $participantEntry): string
    {
        if (!empty($participantEntry['registrationStatus'])) {
            return sanitize_key((string) $participantEntry['registrationStatus']);
        }

        if (!empty($participantEntry['status'])) {
            return sanitize_key((string) $participantEntry['status']);
        }

        if (!empty($participantEntry['statut'])) {
            return sanitize_key((string) $participantEntry['statut']);
        }

        return '';
    }

    /**
     * @param array<string,mixed> $participantEntry
     */
    private static function resolveRegistrationId(array $participantEntry): int
    {
        if (isset($participantEntry['registrationId'])) {
            return (int) $participantEntry['registrationId'];
        }

        if (isset($participantEntry['registration_id'])) {
            return (int) $participantEntry['registration_id'];
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $participantEntry
     * @param array<string,string> $registrationStatusLabels
     */
    public static function resolveStatusLabel(array $participantEntry, string $statusKey, array $registrationStatusLabels): string
    {
        if ($statusKey !== '' && isset($registrationStatusLabels[$statusKey])) {
            return $registrationStatusLabels[$statusKey];
        }

        if (!empty($participantEntry['registrationStatusLabel'])) {
            return (string) $participantEntry['registrationStatusLabel'];
        }

        if (!empty($participantEntry['status_label'])) {
            return (string) $participantEntry['status_label'];
        }

        if (!empty($participantEntry['statusLabel'])) {
            return (string) $participantEntry['statusLabel'];
        }

        if ($statusKey !== '') {
            return ucfirst(str_replace('_', ' ', $statusKey));
        }

        return '';
    }

    /**
     * @param array<string,mixed> $participantEntry
     */
    private static function isParticipantEligible(array $participantEntry): bool
    {
        if (array_key_exists('eligible', $participantEntry)) {
            return !empty($participantEntry['eligible']);
        }

        if (array_key_exists('isEligible', $participantEntry)) {
            return !empty($participantEntry['isEligible']);
        }

        return true;
    }

    /**
     * @param array<string,mixed> $participantEntry
     * @return array<int,string>
     */
    private static function resolveIneligibleReasons(array $participantEntry): array
    {
        $reasons = array();

        if (!empty($participantEntry['ineligible_reasons']) && is_array($participantEntry['ineligible_reasons'])) {
            $reasons = $participantEntry['ineligible_reasons'];
        } elseif (!empty($participantEntry['ineligibleReasons']) && is_array($participantEntry['ineligibleReasons'])) {
            $reasons = $participantEntry['ineligibleReasons'];
        }

        if (empty($reasons)) {
            return array();
        }

        $reasons = array_unique($reasons);
        $reasons = array_filter($reasons, static fn ($entry) => is_string($entry) && $entry !== '');

        return array_values(array_map(static fn ($entry) => sanitize_text_field((string) $entry), $reasons));
    }
}
