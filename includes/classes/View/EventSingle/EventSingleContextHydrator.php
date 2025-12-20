<?php

namespace Mj\Member\Classes\View\EventSingle;

use Mj\Member\Classes\Crud\MjEventAnimateurs;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Value\EventData;
use Mj\Member\Classes\Value\EventLocationData;
use function array_key_exists;
use function array_replace;
use function esc_url_raw;
use function is_array;
use function is_email;
use function is_object;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function trim;
use function wp_get_attachment_image_url;

if (!defined('ABSPATH')) {
    exit;
}

final class EventSingleContextHydrator
{
    /**
     * @var array<string,mixed>
     */
    private array $context;

    private ?EventData $event = null;

    private ?EventLocationData $location = null;

    /**
     * @var array<string,mixed>|null
     */
    private ?array $registration = null;

    /**
     * @var array<string,mixed>|null
     */
    private ?array $animateurs = null;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(array $context)
    {
        $this->context = $context;
    }

    public function event(): EventData
    {
        $this->ensureEvent();
        return $this->event ?? EventData::fromArray(array());
    }

    /**
     * @return array<string,mixed>
     */
    public function eventArray(): array
    {
        $event = $this->event()->toArray();
        if (isset($this->context['event']) && is_array($this->context['event'])) {
            $event = array_replace($this->context['event'], $event);
        }

        return $event;
    }

    /**
     * @return array<string,mixed>
     */
    public function registrationContext(): array
    {
        if ($this->registration !== null) {
            return $this->registration;
        }

        $registration = array();
        if (isset($this->context['registration']) && is_array($this->context['registration'])) {
            $registration = $this->context['registration'];
        }

        if (function_exists('mj_member_build_event_registration_context')) {
            $generated = mj_member_build_event_registration_context($this->eventArray());
            if (is_array($generated)) {
                $registration = array_replace($registration, $generated);
            }
        }

        if (!array_key_exists('is_free_participation', $registration)) {
            $registration['is_free_participation'] = !empty($registration['free_participation']);
        }

        if (!array_key_exists('event_id', $registration)) {
            $registration['event_id'] = (int) $this->event()->get('id', 0);
        }

        $this->registration = $registration;
        return $this->registration;
    }

    /**
     * @return array<string,mixed>
     */
    public function locationContext(): array
    {
        $this->ensureLocation();
        $locationContext = array();
        if (isset($this->context['location']) && is_array($this->context['location'])) {
            $locationContext = $this->context['location'];
        }

        if ($this->location instanceof EventLocationData) {
            $locationArray = $this->location->toArray();
            $addressPieces = array_filter(array(
                isset($locationArray['address_line']) ? (string) $locationArray['address_line'] : '',
                trim(
                    sprintf(
                        '%s %s',
                        isset($locationArray['postal_code']) ? (string) $locationArray['postal_code'] : '',
                        isset($locationArray['city']) ? (string) $locationArray['city'] : ''
                    )
                ),
            ));

            $computed = array(
                'id' => isset($locationArray['id']) ? (int) $locationArray['id'] : 0,
                'title' => isset($locationArray['name']) ? (string) $locationArray['name'] : '',
                'address_line' => isset($locationArray['address_line']) ? (string) $locationArray['address_line'] : '',
                'postal_code' => isset($locationArray['postal_code']) ? (string) $locationArray['postal_code'] : '',
                'city' => isset($locationArray['city']) ? (string) $locationArray['city'] : '',
                'country' => isset($locationArray['country']) ? (string) $locationArray['country'] : '',
                'address' => !empty($addressPieces) ? implode(', ', $addressPieces) : ($locationContext['address'] ?? ''),
                'cover_id' => isset($locationArray['cover_id']) ? (int) $locationArray['cover_id'] : 0,
                'cover_url' => $locationContext['cover_url'] ?? '',
                'map_link' => $locationContext['map_link'] ?? '',
                'map_embed' => $locationContext['map_embed'] ?? '',
            );

            if ($computed['cover_url'] === '' && $computed['cover_id'] > 0) {
                $cover = wp_get_attachment_image_url($computed['cover_id'], 'large');
                if (!empty($cover)) {
                    $computed['cover_url'] = $cover;
                }
            }

            if ($computed['map_link'] === '') {
                $mapQuery = '';
                if (!empty($locationArray['map_query'])) {
                    $mapQuery = (string) $locationArray['map_query'];
                } elseif ($computed['address'] !== '') {
                    $mapQuery = $computed['address'];
                }

                if ($mapQuery !== '') {
                    $computed['map_link'] = 'https://maps.google.com/?q=' . rawurlencode($mapQuery);
                }
            }

            if ($computed['map_embed'] === '') {
                $mapEmbedCandidate = MjEventLocations::build_map_embed_src($locationArray);
                if (!empty($mapEmbedCandidate)) {
                    $computed['map_embed'] = esc_url_raw($mapEmbedCandidate);
                    if ($computed['map_link'] === '') {
                        $mapLink = $mapEmbedCandidate;
                        if (strpos($mapLink, 'output=embed') !== false) {
                            $mapLink = str_replace(array('&output=embed', '?output=embed'), '', $mapLink);
                        }
                        $computed['map_link'] = esc_url_raw($mapLink);
                    }
                }
            } elseif ($computed['map_embed'] !== '') {
                $computed['map_embed'] = esc_url_raw($computed['map_embed']);
            }

            if ($computed['map_link'] !== '') {
                $computed['map_link'] = esc_url_raw($computed['map_link']);
            }

            if ($computed['title'] === '' && isset($locationContext['title'])) {
                $computed['title'] = (string) $locationContext['title'];
            }

            $locationContext = array_replace($computed, $locationContext);
        }

        if (!isset($locationContext['types']) || !is_array($locationContext['types'])) {
            $locationContext['types'] = array();
        }

        return $locationContext;
    }

    /**
     * @return array<string,mixed>
     */
    public function animateursContext(): array
    {
        if ($this->animateurs !== null) {
            return $this->animateurs;
        }

        $contextAnimateurs = array();
        if (isset($this->context['animateurs']) && is_array($this->context['animateurs'])) {
            $contextAnimateurs = $this->context['animateurs'];
        }

        $items = array();
        $eventId = (int) $this->event()->get('id', 0);

        if ($eventId > 0 && class_exists(MjEventAnimateurs::class)) {
            $rows = MjEventAnimateurs::get_members_by_event($eventId);
            if (is_array($rows) && !empty($rows)) {
                $roleLabels = class_exists(MjMembers::class) && method_exists(MjMembers::class, 'getRoleLabels')
                    ? MjMembers::getRoleLabels()
                    : array();

                foreach ($rows as $index => $row) {
                    if (!is_object($row)) {
                        continue;
                    }

                    $memberId = isset($row->id) ? (int) $row->id : 0;
                    $firstName = !empty($row->first_name) ? sanitize_text_field((string) $row->first_name) : '';
                    $lastName = !empty($row->last_name) ? sanitize_text_field((string) $row->last_name) : '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    if ($fullName === '') {
                        if (!empty($row->nickname)) {
                            $fullName = sanitize_text_field((string) $row->nickname);
                        } elseif ($memberId > 0) {
                            $fullName = sprintf(__('Membre #%d', 'mj-member'), $memberId);
                        } else {
                            $fullName = \Mj\Member\Classes\MjRoles::getRoleLabel(\Mj\Member\Classes\MjRoles::ANIMATEUR);
                        }
                    }

                    $roleKey = isset($row->role) ? sanitize_key((string) $row->role) : '';
                    $roleLabel = $roleKey !== '' && isset($roleLabels[$roleKey])
                        ? $roleLabels[$roleKey]
                        : ($roleKey !== '' ? ucfirst($roleKey) : '');

                    $email = !empty($row->email) && is_email($row->email) ? sanitize_email((string) $row->email) : '';
                    $phone = !empty($row->phone) ? sanitize_text_field((string) $row->phone) : '';

                    $whatsappLink = '';
                    $whatsappOptIn = true;
                    if (isset($row->whatsapp_opt_in)) {
                        $whatsappOptIn = ((int) $row->whatsapp_opt_in) !== 0;
                    }
                    if ($whatsappOptIn && $phone !== '') {
                        $number = preg_replace('/\D+/', '', $phone);
                        if ($number !== '') {
                            $whatsappLink = esc_url_raw('https://wa.me/' . $number);
                        }
                    }

                    $avatarUrl = '';
                    if (!empty($row->photo_id)) {
                        $avatarUrl = wp_get_attachment_image_url((int) $row->photo_id, 'medium') ?: '';
                    }
                    if ($avatarUrl === '' && !empty($row->wp_user_id)) {
                        $avatarUrl = get_avatar_url((int) $row->wp_user_id, array('size' => 256));
                    }
                    if ($avatarUrl === '' && $email !== '') {
                        $avatarUrl = get_avatar_url($email, array('size' => 256));
                    }

                    $initials = '';
                    if ($firstName !== '') {
                        $initials .= function_exists('mb_substr') ? mb_substr($firstName, 0, 1) : substr($firstName, 0, 1);
                    }
                    if ($lastName !== '') {
                        $initials .= function_exists('mb_substr') ? mb_substr($lastName, 0, 1) : substr($lastName, 0, 1);
                    }
                    if ($initials === '' && $fullName !== '') {
                        $initials = function_exists('mb_substr') ? mb_substr($fullName, 0, 1) : substr($fullName, 0, 1);
                    }
                    if (function_exists('mb_strtoupper')) {
                        $initials = mb_strtoupper($initials);
                    } else {
                        $initials = strtoupper($initials);
                    }

                    $items[] = array(
                        'id' => $memberId,
                        'full_name' => $fullName,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'role_label' => $roleLabel,
                        'role' => $roleKey,
                        'email' => $email,
                        'phone' => $phone,
                        'whatsapp_link' => $whatsappLink,
                        'avatar_url' => $avatarUrl,
                        'avatar_alt' => sprintf(__('Portrait de %s', 'mj-member'), $fullName),
                        'initials' => $initials,
                        'is_primary' => ((int) $index === 0),
                    );
                }
            }
        }

        if (!empty($items)) {
            $this->animateurs = array(
                'items' => $items,
                'count' => count($items),
                'has_items' => true,
            );
        } else {
            $fallbackItems = isset($contextAnimateurs['items']) && is_array($contextAnimateurs['items']) ? $contextAnimateurs['items'] : array();
            $this->animateurs = array(
                'items' => $fallbackItems,
                'count' => isset($contextAnimateurs['count']) ? (int) $contextAnimateurs['count'] : count($fallbackItems),
                'has_items' => !empty($fallbackItems),
            );
        }

        return $this->animateurs;
    }

    private function ensureEvent(): void
    {
        if ($this->event !== null) {
            return;
        }

        $contextEvent = array();
        if (isset($this->context['event']) && is_array($this->context['event'])) {
            $contextEvent = $this->context['event'];
        }

        $this->event = !empty($contextEvent)
            ? EventData::fromArray($contextEvent)
            : EventData::fromArray(array());

        if (isset($this->context['record'])) {
            $recordEvent = EventData::fromRow($this->context['record']);
            if ($recordEvent->get('id')) {
                $this->event = $recordEvent;
            }
        }

        $eventId = (int) $this->event->get('id', 0);
        if ($eventId <= 0 && isset($this->context['event_id'])) {
            $eventId = (int) $this->context['event_id'];
        }
        if ($eventId <= 0 && isset($contextEvent['id'])) {
            $eventId = (int) $contextEvent['id'];
        }

        if ($eventId > 0) {
            $resolved = MjEvents::find($eventId);
            if ($resolved instanceof EventData) {
                $this->event = $resolved;
                return;
            }
        }

        $slugCandidates = array();
        if (isset($this->context['slug'])) {
            $slugCandidates[] = $this->context['slug'];
        }
        if (isset($this->context['event_slug'])) {
            $slugCandidates[] = $this->context['event_slug'];
        }
        if (isset($contextEvent['slug'])) {
            $slugCandidates[] = $contextEvent['slug'];
        }

        foreach ($slugCandidates as $slugCandidate) {
            $slugCandidate = (string) $slugCandidate;
            if ($slugCandidate === '') {
                continue;
            }

            $resolved = MjEvents::find_by_slug($slugCandidate);
            if ($resolved instanceof EventData) {
                $this->event = $resolved;
                return;
            }
        }
    }

    private function ensureLocation(): void
    {
        if ($this->location !== null) {
            return;
        }

        $locationId = (int) $this->event()->get('location_id', 0);
        if ($locationId <= 0 && isset($this->context['location']) && is_array($this->context['location'])) {
            $candidate = $this->context['location'];
            if (isset($candidate['id'])) {
                $locationId = (int) $candidate['id'];
            }
        }

        if ($locationId > 0 && class_exists(MjEventLocations::class)) {
            $location = MjEventLocations::find($locationId);
            if ($location instanceof EventLocationData) {
                $this->location = $location;
                return;
            }
        }

        if (isset($this->context['location']) && is_array($this->context['location'])) {
            $this->location = EventLocationData::fromArray($this->context['location']);
        } else {
            $this->location = null;
        }
    }
}
