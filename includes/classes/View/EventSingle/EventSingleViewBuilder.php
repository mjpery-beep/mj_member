<?php

namespace Mj\Member\Classes\View\EventSingle;

use Mj\Member\Core\AssetsManager;
use function __;
use function array_key_exists;
use function array_values;
use function esc_url_raw;
use function is_array;
use function is_numeric;
use function is_string;
use function mb_strtolower;
use function mj_member_event_build_attr_string;
use function number_format_i18n;
use function sanitize_key;
use function sanitize_text_field;
use function trim;
use function wp_json_encode;

if (!defined('ABSPATH')) {
    exit;
}

final class EventSingleViewBuilder
{
    /** @var array<string,mixed> */
    private array $context = array();

    /** @var array<string,mixed> */
    private array $state = array();

    private ?object $currentMember = null;

    private int $currentMemberId = 0;

    private EventSingleContextHydrator $hydrator;

    /** @var array<string,mixed> */
    private array $registrationConfig = array();

    /** @var array<int,array<string,mixed>> */
    private array $registrationParticipants = array();

    /** @var array<string,string> */
    private array $registrationStatusLabels = array();

    /** @var array<int,bool> */
    private array $allowedMemberLookup = array();

    public function build(array $context = array()): array
    {
        $this->context = $context;
        $this->state = $context;
        $this->state['context'] = $context;

        $this->initializeMembers();
        $this->hydrateCore();
        $this->prepareEventMeta();
        $this->prepareRegistrationPrice();
        $this->normalizeParticipants();
        $this->applyElementorPreview();
        $this->presentOccurrences();
        $this->ensureInteractivityFlags();
        $this->synchronizeRegistrationState();
        $this->preparePhotos();
        $this->prepareAssets();
        $this->prepareRegistrationButton();

        EventSingleDefaults::apply($this->state, $this->state['event']);

        return $this->collectResult();
    }

    private function initializeMembers(): void
    {
        $currentMember = $this->state['current_member'] ?? null;
        $currentMemberId = array_key_exists('current_member_id', $this->state)
            ? (int) $this->state['current_member_id']
            : 0;

        if ($currentMemberId === 0 && $currentMember && isset($currentMember->id)) {
            $currentMemberId = (int) $currentMember->id;
        }

        $this->currentMember = is_object($currentMember) ? $currentMember : null;
        $this->currentMemberId = $currentMemberId;
        $this->state['current_member'] = $this->currentMember;
        $this->state['current_member_id'] = $this->currentMemberId;
    }

    private function hydrateCore(): void
    {
        $this->hydrator = new EventSingleContextHydrator($this->context);

        $event = $this->hydrator->eventArray();
        $eventId = array_key_exists('event_id', $this->state) ? (int) $this->state['event_id'] : 0;
        if ($eventId <= 0) {
            $eventId = isset($event['id']) ? (int) $event['id'] : 0;
        } else {
            $event['id'] = $eventId;
        }

        $this->state['event'] = $event;
        $this->state['event_id'] = $eventId;

        $registration = $this->hydrator->registrationContext();
        $this->state['registration'] = $registration;

        $animateursContext = $this->hydrator->animateursContext();
        $animateurItems = isset($animateursContext['items']) && is_array($animateursContext['items'])
            ? $animateursContext['items']
            : array();
        $animateursCount = isset($animateursContext['count'])
            ? (int) $animateursContext['count']
            : count($animateurItems);

        $this->state['animateur_items'] = $animateurItems;
        $this->state['animateurs_count'] = $animateursCount;

        $locationContext = $this->hydrator->locationContext();
        $this->state['location_context'] = $locationContext;
        $this->state['location_has_card'] = !empty($locationContext);

        $this->state['location_display_title'] = $this->stringOverride('location_display_title', $locationContext['title'] ?? '');
        $this->state['location_display_cover'] = $this->stringOverride('location_display_cover', $locationContext['cover_url'] ?? '');
        $this->state['location_display_map'] = $this->stringOverride('location_display_map', $locationContext['map_embed'] ?? '');
        $this->state['location_display_map_link'] = $this->stringOverride('location_display_map_link', $locationContext['map_link'] ?? '');
        $this->state['location_address_display'] = $this->stringOverride('location_address_display', $locationContext['address'] ?? '');
        $this->state['location_description_html'] = $this->stringOverride('location_description_html', $locationContext['description_html'] ?? '');
        $this->state['location_notes_html'] = $this->stringOverride('location_notes_html', $locationContext['notes_html'] ?? '');
        $this->state['location_types'] = isset($this->state['location_types']) && is_array($this->state['location_types'])
            ? $this->state['location_types']
            : (isset($locationContext['types']) && is_array($locationContext['types']) ? $locationContext['types'] : array());

        $this->registrationConfig = isset($this->state['registration_config']) && is_array($this->state['registration_config'])
            ? $this->state['registration_config']
            : (isset($registration['config']) && is_array($registration['config']) ? $registration['config'] : array());

        $this->registrationParticipants = isset($this->state['registration_participants']) && is_array($this->state['registration_participants'])
            ? $this->state['registration_participants']
            : (isset($registration['participants']) && is_array($registration['participants']) ? $registration['participants'] : array());
        $this->state['registration_participants'] = $this->registrationParticipants;

        $this->registrationStatusLabels = isset($this->state['registration_status_labels']) && is_array($this->state['registration_status_labels'])
            ? $this->state['registration_status_labels']
            : (isset($registration['status_labels']) && is_array($registration['status_labels']) ? $registration['status_labels'] : array());
        $this->state['registration_status_labels'] = $this->registrationStatusLabels;

        $statusLabels = isset($this->state['status_labels']) && is_array($this->state['status_labels'])
            ? $this->state['status_labels']
            : $this->registrationStatusLabels;
        $this->state['status_labels'] = $statusLabels;

        $this->state['registration_is_open'] = $this->resolveBoolean('registration_is_open', !empty($registration['is_open']));
        $this->state['registration_requires_login'] = $this->resolveBoolean('registration_requires_login', !empty($registration['requires_login']));
        $this->state['registration_has_participants'] = $this->resolveBoolean('registration_has_participants', !empty($registration['has_participants']));
        $this->state['registration_all_registered'] = $this->resolveBoolean('registration_all_registered', !empty($registration['all_registered']));

        $this->state['registration_registered_count'] = $this->resolveInt('registration_registered_count', $registration['registered_count'] ?? 0);
        $this->state['registration_available_count'] = $this->resolveInt('registration_available_count', $registration['available_count'] ?? 0);
        $this->state['registration_total_count'] = $this->resolveInt('registration_total_count', $registration['total_count'] ?? 0);

        $this->state['registration_config_json'] = $this->resolveString('registration_config_json', $registration['config_json'] ?? '');
        $this->state['registration_cta_label'] = $this->resolveString('registration_cta_label', $registration['cta_label'] ?? '');
        $this->state['registration_cta_registered'] = $this->resolveString('registration_cta_registered', $registration['cta_registered'] ?? '');
        $this->state['occurrence_schedule_summary'] = $this->resolveString('occurrence_schedule_summary', $event['occurrence_schedule_summary'] ?? '');

        $this->state['registration_needs_script'] = $this->resolveBoolean('registration_needs_script', !empty($registration['needs_script']));
        $this->state['registration_has_interactive'] = $this->resolveBoolean('registration_has_interactive', !empty($registration['has_interactive']));

        $configArray = $this->registrationConfig;
        if (!array_key_exists('registration_is_free_participation', $this->state)) {
            $this->state['registration_is_free_participation'] = !empty($registration['is_free_participation'])
                || !empty($registration['free_participation'])
                || !empty($configArray['isFreeParticipation'])
                || !empty($configArray['freeParticipation']);
        } else {
            $this->state['registration_is_free_participation'] = (bool) $this->state['registration_is_free_participation'];
        }

        if (!array_key_exists('registration_free_participation_message', $this->state) || $this->state['registration_free_participation_message'] === '') {
            if (!empty($registration['free_participation_message']) && is_string($registration['free_participation_message'])) {
                $this->state['registration_free_participation_message'] = (string) $registration['free_participation_message'];
            } elseif (!empty($configArray['freeParticipationMessage']) && is_string($configArray['freeParticipationMessage'])) {
                $this->state['registration_free_participation_message'] = (string) $configArray['freeParticipationMessage'];
            } else {
                $this->state['registration_free_participation_message'] = '';
            }
        } else {
            $this->state['registration_free_participation_message'] = (string) $this->state['registration_free_participation_message'];
        }

        $occurrenceCatalog = isset($this->state['registration_occurrence_catalog']) && is_array($this->state['registration_occurrence_catalog'])
            ? $this->state['registration_occurrence_catalog']
            : array();
        if (empty($occurrenceCatalog) && isset($registration['occurrence_catalog']) && is_array($registration['occurrence_catalog'])) {
            $occurrenceCatalog = $registration['occurrence_catalog'];
        } elseif (empty($occurrenceCatalog) && isset($this->registrationConfig['occurrenceCatalog']) && is_array($this->registrationConfig['occurrenceCatalog'])) {
            $occurrenceCatalog = $this->registrationConfig['occurrenceCatalog'];
        }
        $this->state['registration_occurrence_catalog'] = $occurrenceCatalog;

        $occurrenceSelection = isset($this->state['occurrence_selection']) && is_array($this->state['occurrence_selection'])
            ? $this->state['occurrence_selection']
            : array();
        if (empty($occurrenceSelection) && isset($registration['occurrences']) && is_array($registration['occurrences'])) {
            $occurrenceSelection = $registration['occurrences'];
        } elseif (empty($occurrenceSelection) && isset($this->registrationConfig['occurrences']) && is_array($this->registrationConfig['occurrences'])) {
            $occurrenceSelection = $this->registrationConfig['occurrences'];
        }
        if (!empty($occurrenceSelection) && is_array($occurrenceSelection)) {
            $occurrenceSelection = array_values($occurrenceSelection);
        }
        $this->state['occurrence_selection'] = $occurrenceSelection;

        $occurrenceAssignments = isset($this->state['occurrence_assignments']) && is_array($this->state['occurrence_assignments'])
            ? $this->state['occurrence_assignments']
            : array();
        if (empty($occurrenceAssignments) && isset($registration['assignments']) && is_array($registration['assignments'])) {
            $occurrenceAssignments = $registration['assignments'];
        } elseif (empty($occurrenceAssignments) && isset($this->registrationConfig['assignments']) && is_array($this->registrationConfig['assignments'])) {
            $occurrenceAssignments = $this->registrationConfig['assignments'];
        }
        if (!is_array($occurrenceAssignments)) {
            $occurrenceAssignments = array();
        }
        if (!isset($occurrenceAssignments['mode']) || !is_string($occurrenceAssignments['mode'])) {
            $occurrenceAssignments['mode'] = isset($occurrenceAssignments['mode']) ? (string) $occurrenceAssignments['mode'] : 'all';
        }
        if (!isset($occurrenceAssignments['occurrences']) || !is_array($occurrenceAssignments['occurrences'])) {
            $occurrenceAssignments['occurrences'] = array();
        }
        $this->state['occurrence_assignments'] = $occurrenceAssignments;

        if (array_key_exists('allow_occurrence_selection', $this->state)) {
            $allowOccurrenceSelection = (bool) $this->state['allow_occurrence_selection'];
        } elseif (isset($registration['allow_occurrence_selection'])) {
            $allowOccurrenceSelection = !empty($registration['allow_occurrence_selection']);
        } elseif (isset($this->registrationConfig['allowOccurrenceSelection'])) {
            $allowOccurrenceSelection = !empty($this->registrationConfig['allowOccurrenceSelection']);
        } else {
            $allowOccurrenceSelection = false;
        }
        $this->state['allow_occurrence_selection'] = $allowOccurrenceSelection;

        $selectionMode = isset($this->state['occurrence_selection_mode']) && !is_array($this->state['occurrence_selection_mode'])
            ? (string) $this->state['occurrence_selection_mode']
            : '';
        if ($selectionMode === '' && isset($registration['occurrence_selection_mode']) && !is_array($registration['occurrence_selection_mode'])) {
            $selectionMode = (string) $registration['occurrence_selection_mode'];
        } elseif ($selectionMode === '' && isset($this->registrationConfig['occurrenceSelectionMode']) && !is_array($this->registrationConfig['occurrenceSelectionMode'])) {
            $selectionMode = (string) $this->registrationConfig['occurrenceSelectionMode'];
        }
        $this->state['occurrence_selection_mode'] = $selectionMode;

        if (!isset($registration['config']) || !is_array($registration['config'])) {
            $registration['config'] = $this->registrationConfig;
        } else {
            $registration['config'] = array_replace($registration['config'], $this->registrationConfig);
        }
        $this->registrationConfig = $registration['config'];

        $encodedConfig = wp_json_encode($registration['config']);
        if (is_string($encodedConfig)) {
            $this->state['registration_config_json'] = $encodedConfig;
            $registration['config_json'] = $encodedConfig;
        } else {
            $this->state['registration_config_json'] = '';
            $registration['config_json'] = '';
        }

        $this->state['registration'] = $registration;

        $this->state['cover_url'] = $this->resolveString('cover_url', $event['cover_url'] ?? '');
        $this->state['cover_thumb'] = $this->resolveString('cover_thumb', $event['cover_thumb'] ?? '');
    }

    private function prepareEventMeta(): void
    {
        $event = isset($this->state['event']) && is_array($this->state['event'])
            ? $this->state['event']
            : array();

        $existingCapacity = $this->state['event_capacity_total'] ?? null;
        $capacity = $existingCapacity !== null ? (int) $existingCapacity : (isset($event['capacity_total']) ? (int) $event['capacity_total'] : 0);
        if ($capacity < 0) {
            $capacity = 0;
        }
        $this->state['event_capacity_total'] = $capacity;

        $ageLabel = '';
        if (array_key_exists('age_label', $this->state) && !is_array($this->state['age_label'])) {
            $ageLabel = (string) $this->state['age_label'];
        }

        if ($ageLabel === '') {
            $ageMin = isset($event['age_min']) ? (int) $event['age_min'] : 0;
            $ageMax = isset($event['age_max']) ? (int) $event['age_max'] : 0;

            if ($ageMin > 0 || $ageMax > 0) {
                if ($ageMin > 0 && $ageMax > 0) {
                    $ageLabel = sprintf(__('Âges : %d - %d ans', 'mj-member'), $ageMin, $ageMax);
                } elseif ($ageMin > 0) {
                    $ageLabel = sprintf(__('À partir de %d ans', 'mj-member'), $ageMin);
                } else {
                    $ageLabel = sprintf(__('Jusqu’à %d ans', 'mj-member'), $ageMax);
                }
            }
        }

        $this->state['age_label'] = $ageLabel;

        $locationContext = isset($this->state['location_context']) && is_array($this->state['location_context'])
            ? $this->state['location_context']
            : array();

        $locationDisplayTitle = isset($this->state['location_display_title']) && !is_array($this->state['location_display_title'])
            ? (string) $this->state['location_display_title']
            : '';
        $locationAddressDisplay = isset($this->state['location_address_display']) && !is_array($this->state['location_address_display'])
            ? (string) $this->state['location_address_display']
            : '';

        $locationLabel = isset($this->state['location_label']) && !is_array($this->state['location_label'])
            ? (string) $this->state['location_label']
            : '';

        if ($locationLabel === '') {
            $locationLabel = $locationDisplayTitle !== ''
                ? $locationDisplayTitle
                : (isset($locationContext['title']) ? (string) $locationContext['title'] : '');

            if ($locationLabel === '' && $locationAddressDisplay !== '') {
                $locationLabel = $locationAddressDisplay;
            }
        }

        $this->state['location_label'] = $locationLabel;

        if (!array_key_exists('location_address', $this->state) || $this->state['location_address'] === null || $this->state['location_address'] === '') {
            $this->state['location_address'] = $locationAddressDisplay;
        }

        $mapEmbedRaw = isset($this->state['location_display_map']) && !is_array($this->state['location_display_map'])
            ? (string) $this->state['location_display_map']
            : '';
        if ($mapEmbedRaw === '' && isset($locationContext['map_embed'])) {
            $mapEmbedRaw = (string) $locationContext['map_embed'];
        }

        $mapLinkRaw = isset($this->state['location_display_map_link']) && !is_array($this->state['location_display_map_link'])
            ? (string) $this->state['location_display_map_link']
            : '';
        if ($mapLinkRaw === '' && isset($locationContext['map_link'])) {
            $mapLinkRaw = (string) $locationContext['map_link'];
        }

        if ($mapLinkRaw === '' && $mapEmbedRaw !== '') {
            $mapLinkCandidate = $mapEmbedRaw;
            if (strpos($mapLinkCandidate, 'output=embed') !== false) {
                $mapLinkCandidate = str_replace(array('&output=embed', '?output=embed'), '', $mapLinkCandidate);
            }
            $mapLinkRaw = $mapLinkCandidate;
        }

        $mapEmbed = $mapEmbedRaw;
        if ($mapEmbed !== '') {
            $sanitizedEmbed = esc_url_raw($mapEmbedRaw);
            if ($sanitizedEmbed !== '') {
                $mapEmbed = $sanitizedEmbed;
            }
        }

        $mapLink = $mapLinkRaw;
        if ($mapLink !== '') {
            $sanitizedLink = esc_url_raw($mapLinkRaw);
            if ($sanitizedLink !== '') {
                $mapLink = $sanitizedLink;
            }
        }

        $this->state['location_display_map'] = $mapEmbed;
        $this->state['location_map'] = $mapEmbed;
        $this->state['location_display_map_link'] = $mapLink;
        $this->state['location_map_link'] = $mapLink;
    }

    private function prepareRegistrationPrice(): void
    {
        $registration = isset($this->state['registration']) && is_array($this->state['registration'])
            ? $this->state['registration']
            : array();
        $event = isset($this->state['event']) && is_array($this->state['event'])
            ? $this->state['event']
            : array();

        $priceAmount = 0.0;
        if (array_key_exists('registration_price_amount', $this->state) && is_numeric($this->state['registration_price_amount'])) {
            $priceAmount = (float) $this->state['registration_price_amount'];
        } elseif (isset($registration['price_amount']) && is_numeric($registration['price_amount'])) {
            $priceAmount = (float) $registration['price_amount'];
        } elseif (isset($event['price']) && is_numeric($event['price'])) {
            $priceAmount = (float) $event['price'];
        }

        $priceLabel = '';
        if (array_key_exists('registration_price_label', $this->state) && !is_array($this->state['registration_price_label'])) {
            $priceLabel = (string) $this->state['registration_price_label'];
        } elseif (isset($registration['price_label']) && is_string($registration['price_label'])) {
            $priceLabel = (string) $registration['price_label'];
        } elseif (isset($event['price_label']) && is_string($event['price_label'])) {
            $priceLabel = (string) $event['price_label'];
        }

        $priceCurrency = '';
        if (array_key_exists('registration_price_currency', $this->state) && !is_array($this->state['registration_price_currency'])) {
            $priceCurrency = (string) $this->state['registration_price_currency'];
        } elseif (isset($registration['price_currency']) && is_string($registration['price_currency'])) {
            $priceCurrency = (string) $registration['price_currency'];
        } elseif (isset($event['price_currency']) && is_string($event['price_currency'])) {
            $priceCurrency = (string) $event['price_currency'];
        }

        $currencyDisplay = '';
        $precisionAmount = abs($priceAmount - (float) ((int) $priceAmount)) > 0.00001
            ? number_format_i18n($priceAmount, 2)
            : number_format_i18n($priceAmount, 0);

        if ($priceAmount > 0) {
            if ($priceLabel === '') {
                $priceLabel = sprintf(__('Tarif : %s €', 'mj-member'), $precisionAmount);
            }
            $currencyDisplay = sprintf('%s €', $precisionAmount);
        } else {
            if ($priceLabel === '') {
                $priceLabel = __('Tarif : Gratuit', 'mj-member');
            }
            $currencyDisplay = __('Gratuit', 'mj-member');
        }

        $priceCandidate = '';
        if (array_key_exists('registration_price_candidate', $this->state) && !is_array($this->state['registration_price_candidate'])) {
            $priceCandidate = trim((string) $this->state['registration_price_candidate']);
        } elseif (isset($registration['price_candidate']) && is_string($registration['price_candidate'])) {
            $priceCandidate = trim((string) $registration['price_candidate']);
        }

        if ($priceCandidate === '') {
            $priceCandidate = trim(preg_replace('/^\s*Tarif\s*:\s*/i', '', $priceLabel) ?? '');
        }
        if ($priceCandidate === '') {
            $priceCandidate = $currencyDisplay;
        }

        $pricePlain = '';
        if (array_key_exists('registration_price_plain', $this->state) && !is_array($this->state['registration_price_plain'])) {
            $pricePlain = trim((string) $this->state['registration_price_plain']);
        } elseif (isset($registration['price_plain']) && is_string($registration['price_plain'])) {
            $pricePlain = trim((string) $registration['price_plain']);
        }

        if ($pricePlain === '') {
            $pricePlain = $priceCandidate;
        }

        $pricePlainLower = $pricePlain;
        if ($pricePlainLower !== '') {
            $pricePlainLower = function_exists('mb_strtolower')
                ? mb_strtolower($pricePlainLower)
                : strtolower($pricePlainLower);
        }

        $isZeroNumeric = abs($priceAmount) < 0.00001;
        $isFree = $isZeroNumeric || !empty($this->state['registration_is_free_participation']);

        $paymentRequired = array_key_exists('registration_payment_required', $this->state)
            ? (bool) $this->state['registration_payment_required']
            : (!empty($registration['payment_required']) || $priceAmount > 0);

        $showPrice = array_key_exists('registration_show_price', $this->state)
            ? (bool) $this->state['registration_show_price']
            : ($priceCandidate !== '');

        $this->state['registration_price_amount'] = $priceAmount;
        $this->state['registration_price_label'] = $priceLabel;
        $this->state['registration_price_candidate'] = $priceCandidate;
        $this->state['registration_price_plain'] = $pricePlain;
        $this->state['registration_price_plain_lower'] = $pricePlainLower;
        $this->state['registration_price_is_zero_numeric'] = $isZeroNumeric;
        $this->state['registration_price_is_free'] = $isFree;
        $this->state['registration_show_price'] = $showPrice;
        $this->state['registration_payment_required'] = $paymentRequired;
        $this->state['registration_price_currency'] = $priceCurrency;

        $registration['price_amount'] = $priceAmount;
        $registration['price_label'] = $priceLabel;
        $registration['price_candidate'] = $priceCandidate;
        $registration['price_plain'] = $pricePlain;
        $registration['payment_required'] = $paymentRequired;
        $registration['price_currency'] = $priceCurrency;

        $this->state['registration'] = $registration;

        if (!empty($this->registrationConfig)) {
            $this->registrationConfig['priceAmount'] = $priceAmount;
            $this->registrationConfig['priceLabel'] = $priceLabel;
            $this->registrationConfig['paymentRequired'] = $paymentRequired;
            $this->registrationConfig['noteMaxLength'] = (int) ($this->state['registration_note_max_length'] ?? 400);
            $this->registrationConfig['priceCurrency'] = $priceCurrency;
            $eventTitle = isset($event['title']) && !is_array($event['title']) ? (string) $event['title'] : '';
            if ($eventTitle !== '') {
                $this->registrationConfig['eventTitle'] = $eventTitle;
            }
        }
    }

    private function normalizeParticipants(): void
    {
        $normalizer = new RegistrationParticipantsNormalizer();
        $result = $normalizer->normalize(
            $this->registrationParticipants,
            $this->registrationStatusLabels,
            $this->state['registration'],
            $this->registrationConfig,
            $this->currentMember,
            $this->currentMemberId
        );

        $this->state['registration_can_manage_children'] = $result['registration_can_manage_children'];
        $this->allowedMemberLookup = $result['allowed_member_lookup'];
        $this->state['registration_note_max_length'] = $result['registration_note_max_length'];
        $this->state['registration_form_participants'] = $result['registration_form_participants'];
        $this->state['registration_form_available_count'] = $result['registration_form_available_count'];
        $this->state['registration_has_ineligible'] = $result['registration_has_ineligible'];

        if ($result['registration_has_ineligible']) {
            $this->state['registration']['has_ineligible'] = true;
        }
    }

    private function applyElementorPreview(): void
    {
        $isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
        $this->state['is_elementor_preview'] = $isPreview;

        if (!$isPreview) {
            return;
        }

        $factory = new PreviewContextFactory();
        $payload = array(
            'is_preview' => true,
            'registration' => $this->state['registration'],
            'registration_config' => $this->registrationConfig,
            'registration_config_json' => $this->state['registration_config_json'],
            'registration_participants' => $this->registrationParticipants,
            'registration_form_participants' => $this->state['registration_form_participants'],
            'registration_has_ineligible' => $this->state['registration_has_ineligible'],
            'registration_total_count' => $this->state['registration_total_count'],
            'registration_registered_count' => $this->state['registration_registered_count'],
            'registration_available_count' => $this->state['registration_available_count'],
            'registration_form_available_count' => $this->state['registration_form_available_count'],
            'registration_has_participants' => $this->state['registration_has_participants'],
            'registration_all_registered' => $this->state['registration_all_registered'],
            'registration_is_open' => $this->state['registration_is_open'],
            'registration_requires_login' => $this->state['registration_requires_login'],
            'registration_occurrence_catalog' => $this->state['registration_occurrence_catalog'],
            'occurrence_selection' => $this->state['occurrence_selection'],
            'occurrence_assignments' => $this->state['occurrence_assignments'],
            'allow_occurrence_selection' => $this->state['allow_occurrence_selection'],
            'occurrence_selection_mode' => $this->state['occurrence_selection_mode'],
            'registration_has_interactive' => $this->state['registration_has_interactive'],
        );

        $payload = $factory->apply($payload);

        $this->state['registration'] = $payload['registration'];
        $this->registrationConfig = $payload['registration_config'];
        $this->state['registration_config_json'] = $payload['registration_config_json'];
        $this->registrationParticipants = $payload['registration_participants'];
        $this->state['registration_participants'] = $this->registrationParticipants;
        $this->state['registration_form_participants'] = $payload['registration_form_participants'];
        $this->state['registration_total_count'] = $payload['registration_total_count'];
        $this->state['registration_available_count'] = $payload['registration_available_count'];
        $this->state['registration_registered_count'] = $payload['registration_registered_count'];
        $this->state['registration_has_participants'] = $payload['registration_has_participants'];
        $this->state['registration_all_registered'] = $payload['registration_all_registered'];
        $this->state['registration_form_available_count'] = $payload['registration_form_available_count'];
        $this->state['registration_has_ineligible'] = $payload['registration_has_ineligible'];
        $this->state['registration_occurrence_catalog'] = $payload['registration_occurrence_catalog'];
        $this->state['occurrence_selection'] = $payload['occurrence_selection'];
        $this->state['occurrence_assignments'] = $payload['occurrence_assignments'];
        $this->state['allow_occurrence_selection'] = $payload['allow_occurrence_selection'];
        $this->state['occurrence_selection_mode'] = $payload['occurrence_selection_mode'];
        $this->state['registration_is_open'] = $payload['registration_is_open'];
        $this->state['registration_requires_login'] = $payload['registration_requires_login'];

        if (!empty($payload['registration_has_interactive'])) {
            $this->state['registration_has_interactive'] = true;
        }
    }

    private function presentOccurrences(): void
    {
        $presenter = new EventOccurrencePresenter();
        $payload = array(
            'event' => $this->state['event'],
            'registration' => $this->state['registration'],
            'registration_config' => $this->registrationConfig,
            'registration_config_json' => $this->state['registration_config_json'],
            'occurrence_schedule_summary' => $this->state['occurrence_schedule_summary'],
            'occurrence_selection' => $this->state['occurrence_selection'],
            'registration_occurrence_catalog' => $this->state['registration_occurrence_catalog'],
            'occurrence_preview' => $this->state['occurrence_preview'] ?? array(),
            'occurrence_items' => $this->state['occurrence_items'] ?? array(),
            'occurrence_remaining' => $this->state['occurrence_remaining'] ?? 0,
            'occurrence_next' => $this->state['occurrence_next'] ?? '',
            'occurrence_next_label' => $this->state['occurrence_next_label'] ?? '',
            'registration_participants' => $this->registrationParticipants,
            'allowed_member_lookup' => $this->allowedMemberLookup,
            'registration_status_labels' => $this->registrationStatusLabels,
            'registration_has_ineligible' => $this->state['registration_has_ineligible'],
            'event_has_multiple_occurrences' => $this->state['event_has_multiple_occurrences'] ?? false,
            'current_member_id' => $this->currentMemberId,
        );

        $payload = $presenter->present($payload);

        $this->state['registration'] = $payload['registration'];
        $this->registrationConfig = $payload['registration_config'];
        $this->state['registration_config_json'] = $payload['registration_config_json'];
        $this->state['occurrence_schedule_summary'] = $payload['occurrence_schedule_summary'];
        $this->state['occurrence_preview'] = $payload['occurrence_preview'];
        $this->state['occurrence_items'] = $payload['occurrence_items'];
        $this->state['occurrence_remaining'] = $payload['occurrence_remaining'];
        $this->state['occurrence_next'] = $payload['occurrence_next'];
        $this->state['occurrence_next_label'] = $payload['occurrence_next_label'];
        $this->state['event_has_multiple_occurrences'] = $payload['event_has_multiple_occurrences'];
        $this->state['registration_reservations'] = $payload['registration_reservations'];
        $this->state['registration_has_reservations'] = $payload['registration_has_reservations'];
        $this->state['registration_has_ineligible'] = $payload['registration_has_ineligible'];
    }

    private function preparePhotos(): void
    {
        $photoContext = isset($this->context['photos']) && is_array($this->context['photos']) ? $this->context['photos'] : array();
        $photoItems = isset($photoContext['items']) && is_array($photoContext['items']) ? $photoContext['items'] : array();

        $this->state['photo_context'] = $photoContext;
        $this->state['photo_items'] = $photoItems;
        $this->state['photo_has_items'] = !empty($photoItems);
        $this->state['photo_can_upload'] = !empty($photoContext['can_upload']);
        $this->state['photo_is_unlimited'] = !empty($photoContext['is_unlimited']);

        $uploadLimit = isset($photoContext['upload_limit']) ? (int) $photoContext['upload_limit'] : 3;
        if (!empty($this->state['photo_is_unlimited'])) {
            $uploadLimit = 0;
        }
        $this->state['photo_upload_limit'] = $uploadLimit;

        $memberUploaded = isset($photoContext['member_uploaded']) ? (int) $photoContext['member_uploaded'] : 0;
        $this->state['photo_member_uploaded'] = $memberUploaded;

        if (!empty($this->state['photo_is_unlimited'])) {
            $this->state['photo_member_remaining'] = null;
        } else {
            $remaining = isset($photoContext['member_remaining']) ? (int) $photoContext['member_remaining'] : max(0, $uploadLimit - $memberUploaded);
            $this->state['photo_member_remaining'] = $remaining;
        }

        $this->state['photo_total'] = isset($photoContext['count']) ? (int) $photoContext['count'] : count($photoItems);
        $this->state['photo_reason'] = isset($photoContext['reason']) ? (string) $photoContext['reason'] : '';

        $pendingContext = isset($photoContext['member_pending']) && is_array($photoContext['member_pending']) ? $photoContext['member_pending'] : array();
        $pendingItems = isset($pendingContext['items']) && is_array($pendingContext['items']) ? $pendingContext['items'] : array();

        $this->state['photo_member_pending_context'] = $pendingContext;
        $this->state['photo_member_pending_items'] = $pendingItems;
        $this->state['photo_member_pending_has'] = !empty($pendingContext['has_items']);
        $this->state['photo_member_pending_count'] = isset($pendingContext['count']) ? (int) $pendingContext['count'] : count($pendingItems);

        $photoNotice = null;
        if (isset($_GET['mj_event_photo'])) {
            $candidate = sanitize_key((string) $_GET['mj_event_photo']);
            if ($candidate !== '' && function_exists('mj_member_event_photos_get_notice')) {
                $photoNotice = mj_member_event_photos_get_notice($candidate);
            }
        }
        $this->state['photo_notice'] = $photoNotice;
    }

    private function prepareAssets(): void
    {
        if (!class_exists(AssetsManager::class)) {
            return;
        }

        AssetsManager::requirePackage('event-single');
        AssetsManager::requirePackage('event-photos');

        if (
            !empty($this->state['registration']['needs_script'])
            || !empty($this->state['registration']['has_interactive'])
            || !empty($this->state['registration_needs_script'])
            || !empty($this->state['registration_has_interactive'])
        ) {
            AssetsManager::requirePackage('events-widget', array('template' => 'event-single'));
        }
    }

    private function prepareRegistrationButton(): void
    {
        $eventId = isset($this->state['event']['id']) ? (int) $this->state['event']['id'] : 0;
        $attributes = array(
            'data-event-id' => $eventId > 0 ? $eventId : null,
            'data-cta-label' => $this->state['registration_cta_label'],
            'data-cta-registered-label' => $this->state['registration_cta_registered'],
        );

        if ($this->state['occurrence_schedule_summary'] !== '') {
            $attributes['data-occurrence-summary'] = $this->state['occurrence_schedule_summary'];
        }

        if ($this->state['registration_config_json'] !== '') {
            $attributes['data-registration'] = $this->state['registration_config_json'];
        }

        if (!empty($this->state['registration_requires_login'])) {
            $attributes['data-requires-login'] = '1';
        }

        $this->state['registration_button_attr_string'] = function_exists('mj_member_event_build_attr_string')
            ? mj_member_event_build_attr_string($attributes)
            : '';
    }

    private function collectResult(): array
    {
        $keys = array(
            'context',
            'event',
            'event_id',
            'registration',
            'registration_config_json',
            'status_labels',
            'status_key',
            'active_status',
            'status_label',
            'title',
            'event_type_key',
            'type_label',
            'is_stage_event',
            'date_label',
            'deadline_label',
            'price_label',
            'age_label',
            'location_label',
            'location_address',
            'location_description',
            'location_map',
            'location_map_link',
            'location_cover',
            'description',
            'excerpt',
            'registration_url',
            'article_permalink',
            'cover_url',
            'cover_thumb',
            'registration_is_open',
            'registration_requires_login',
            'registration_needs_script',
            'registration_all_registered',
            'registration_has_participants',
            'registration_cta_label',
            'registration_cta_registered',
            'registration_has_interactive',
            'registration_can_manage_children',
            'registration_price_amount',
            'registration_price_label',
            'registration_payment_required',
            'registration_deadline_label',
            'registration_total_count',
            'registration_registered_count',
            'registration_available_count',
            'event_capacity_total',
            'registration_price_candidate',
            'registration_price_plain',
            'registration_price_plain_lower',
            'registration_price_is_zero_numeric',
            'registration_price_is_free',
            'registration_show_price',
            'registration_is_free_participation',
            'registration_free_participation_message',
            'registration_has_ineligible',
            'animateur_items',
            'animateurs_count',
            'contact_form_page_url',
            'contact_recipient_prefix',
            'location_context',
            'location_types',
            'location_display_title',
            'location_display_cover',
            'location_display_map',
            'location_display_map_link',
            'location_address_display',
            'location_description_html',
            'location_notes_html',
            'location_has_card',
            'occurrence_preview',
            'occurrence_selection',
            'occurrence_assignments',
            'occurrence_selection_mode',
            'allow_occurrence_selection',
            'occurrence_items',
            'occurrence_next',
            'occurrence_next_label',
            'occurrence_remaining',
            'event_has_multiple_occurrences',
            'occurrence_schedule_summary',
            'occurrence_stage_period_label',
            'occurrence_reference_items',
            'occurrence_display_time',
            'occurrence_stage_start_ts',
            'occurrence_stage_end_ts',
            'occurrence_stage_time_range',
            'weekday_order_map',
            'time_range_map',
            'occurrence_reference_count',
            'display_date_label',
            'document_title_filter',
            'document_title_parts_filter',
            'palette',
            'accent',
            'contrast',
            'surface',
            'border',
            'highlight',
            'description_html',
            'excerpt_html',
            'registration_participants',
            'registration_status_labels',
            'registration_occurrence_catalog',
            'registration_reservations',
            'registration_has_reservations',
            'registration_form_participants',
            'registration_form_available_count',
            'registration_note_max_length',
            'photo_context',
            'photo_items',
            'photo_has_items',
            'photo_can_upload',
            'photo_is_unlimited',
            'photo_upload_limit',
            'photo_member_uploaded',
            'photo_member_remaining',
            'photo_total',
            'photo_reason',
            'photo_member_pending_context',
            'photo_member_pending_items',
            'photo_member_pending_has',
            'photo_member_pending_count',
            'photo_notice',
            'registration_button_attr_string',
        );

        $result = array();
        foreach ($keys as $key) {
            $result[$key] = $this->state[$key] ?? null;
        }

        return $result;
    }

    private function ensureInteractivityFlags(): void
    {
        $registration = isset($this->state['registration']) && is_array($this->state['registration'])
            ? $this->state['registration']
            : array();

        $needsScript = !empty($this->state['registration_needs_script'])
            || !empty($registration['needs_script']);

        if (!$needsScript) {
            $needsScript = !empty($this->state['registration_form_participants'])
                || !empty($this->state['registration_reservations']);
        }

        if ($needsScript) {
            $this->state['registration_needs_script'] = true;
            $registration['needs_script'] = true;
            $this->state['registration'] = $registration;
        }

        $hasInteractive = !empty($this->state['registration_has_interactive'])
            || !empty($registration['has_interactive']);

        if (!$hasInteractive) {
            $hasInteractive = !empty($this->state['registration_form_participants']);
        }

        if ($hasInteractive) {
            $this->state['registration_has_interactive'] = true;
            $registration['has_interactive'] = true;
        }

        $this->state['registration'] = $registration;
    }

    private function synchronizeRegistrationState(): void
    {
        if (!empty($this->registrationParticipants)) {
            $this->registrationConfig['participants'] = $this->registrationParticipants;
        }

        $ineligibleCount = 0;
        foreach ($this->registrationParticipants as $participantEntry) {
            if (!is_array($participantEntry)) {
                continue;
            }
            $eligible = true;
            if (array_key_exists('eligible', $participantEntry)) {
                $eligible = !empty($participantEntry['eligible']);
            } elseif (array_key_exists('isEligible', $participantEntry)) {
                $eligible = !empty($participantEntry['isEligible']);
            }
            if (!$eligible) {
                $ineligibleCount++;
            }
        }

        $this->registrationConfig['hasParticipants'] = !empty($this->state['registration_has_participants']);
        $this->registrationConfig['hasAvailableParticipants'] = ($this->state['registration_form_available_count'] ?? 0) > 0;
        $this->registrationConfig['hasIneligibleParticipants'] = !empty($this->state['registration_has_ineligible']);
        $this->registrationConfig['allRegistered'] = !empty($this->state['registration_all_registered']);
        $this->registrationConfig['ineligibleCount'] = $ineligibleCount;
        $this->registrationConfig['registeredCount'] = (int) ($this->state['registration_registered_count'] ?? 0);
        $this->registrationConfig['availableCount'] = (int) ($this->state['registration_available_count'] ?? 0);
        $this->registrationConfig['totalCount'] = (int) ($this->state['registration_total_count'] ?? 0);
        $this->registrationConfig['occurrences'] = $this->state['occurrence_selection'] ?? array();
        $this->registrationConfig['assignments'] = $this->state['occurrence_assignments'] ?? array();
        $this->registrationConfig['allowOccurrenceSelection'] = !empty($this->state['allow_occurrence_selection']);
        $this->registrationConfig['occurrenceSelectionMode'] = $this->state['occurrence_selection_mode'] ?? '';
        $this->registrationConfig['hasOccurrences'] = !empty($this->state['occurrence_selection']);
        $this->registrationConfig['priceAmount'] = $this->state['registration_price_amount'] ?? 0;
        $this->registrationConfig['priceLabel'] = $this->state['registration_price_label'] ?? '';
        $this->registrationConfig['paymentRequired'] = !empty($this->state['registration_payment_required']);
        $this->registrationConfig['noteMaxLength'] = (int) ($this->state['registration_note_max_length'] ?? 400);

        if (!empty($this->registrationConfig)) {
            $encoded = wp_json_encode($this->registrationConfig);
            if (is_string($encoded)) {
                $this->state['registration_config_json'] = $encoded;
            } else {
                $this->state['registration_config_json'] = '';
            }
        } else {
            $this->state['registration_config_json'] = '';
        }

        $registration = isset($this->state['registration']) && is_array($this->state['registration'])
            ? $this->state['registration']
            : array();

        $registration['config'] = $this->registrationConfig;
        $registration['config_json'] = $this->state['registration_config_json'] ?? '';
        $registration['is_open'] = !empty($this->state['registration_is_open']);
        $registration['requires_login'] = !empty($this->state['registration_requires_login']);
        $registration['has_participants'] = !empty($this->state['registration_has_participants']);
        $registration['all_registered'] = !empty($this->state['registration_all_registered']);
        $registration['registered_count'] = (int) ($this->state['registration_registered_count'] ?? 0);
        $registration['available_count'] = (int) ($this->state['registration_available_count'] ?? 0);
        $registration['total_count'] = (int) ($this->state['registration_total_count'] ?? 0);
        $registration['has_ineligible'] = !empty($this->state['registration_has_ineligible']);
        $registration['participants'] = $this->registrationParticipants;
        $registration['status_labels'] = $this->registrationStatusLabels;
        $registration['form_participants'] = $this->state['registration_form_participants'] ?? array();
        $registration['form_available_count'] = (int) ($this->state['registration_form_available_count'] ?? 0);
        $registration['note_max_length'] = (int) ($this->state['registration_note_max_length'] ?? 400);
        $registration['has_interactive'] = !empty($this->state['registration_has_interactive']);
        $registration['needs_script'] = !empty($this->state['registration_needs_script']);
        $registration['occurrences'] = $this->state['occurrence_selection'] ?? array();
        $registration['assignments'] = $this->state['occurrence_assignments'] ?? array();
        $registration['occurrence_selection_mode'] = $this->state['occurrence_selection_mode'] ?? '';
        $registration['allow_occurrence_selection'] = !empty($this->state['allow_occurrence_selection']);
        $registration['occurrence_catalog'] = $this->state['registration_occurrence_catalog'] ?? array();
        $registration['price_amount'] = $this->state['registration_price_amount'] ?? 0;
        $registration['price_label'] = $this->state['registration_price_label'] ?? '';
        $registration['payment_required'] = !empty($this->state['registration_payment_required']);

        $this->state['registration'] = $registration;
        $this->state['registration_config'] = $this->registrationConfig;
    }

    private function stringOverride(string $key, ?string $fallback): string
    {
        if (isset($this->state[$key]) && (string) $this->state[$key] !== '') {
            return (string) $this->state[$key];
        }

        return (string) ($fallback ?? '');
    }

    private function resolveBoolean(string $key, bool $fallback): bool
    {
        if (array_key_exists($key, $this->state)) {
            return (bool) $this->state[$key];
        }

        return $fallback;
    }

    private function resolveInt(string $key, int $fallback): int
    {
        if (array_key_exists($key, $this->state) && $this->state[$key] !== null) {
            return (int) $this->state[$key];
        }

        return (int) $fallback;
    }

    private function resolveString(string $key, string $fallback): string
    {
        if (array_key_exists($key, $this->state) && !is_array($this->state[$key])) {
            return (string) $this->state[$key];
        }

        return (string) $fallback;
    }
}
