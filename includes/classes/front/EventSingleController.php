<?php



namespace Mj\Member\Classes\Front;



use Mj\Member\Classes\View\EventSingle\EventSingleViewBuilder;

use Mj\Member\Core\AssetsManager;



if (!defined('ABSPATH')) {

    exit;

}



if (!class_exists(Controller::class)) {

    require_once __DIR__ . '/Controller.php';

}



class EventSingleController extends Controller

{

    private const TITLE_FILTER_PRIORITY = 9999;



    /**

     * @var callable|null

     */

    private $documentTitleFilter = null;



    /**

     * @var callable|null

     */

    private $documentTitlePartsFilter = null;



    /**

     * @var callable|null

     */

    private $documentWpTitleFilter = null;



    /**

     * Build avec overrides pour rétrocompatibilité

     *

     * @param array<string, mixed> $overrides

     * @return array{model: array<string, mixed>, view: array<string, mixed>, localization: array<string, mixed>, legacy: array<string, mixed>}

     */

    public function build(array $overrides = array()): array

    {

        if (!empty($overrides)) {

            $this->context = array_merge($this->context, $overrides);

        }



        $payload = parent::build();



        $this->installDocumentTitleFilters($payload['legacy'], $payload['model']);



        return $payload;

    }



    public function teardown(): void

    {

        if ($this->documentWpTitleFilter !== null) {

            remove_filter('wp_title', $this->documentWpTitleFilter, self::TITLE_FILTER_PRIORITY);

            $this->documentWpTitleFilter = null;

        }



        if ($this->documentTitlePartsFilter !== null) {

            remove_filter('document_title_parts', $this->documentTitlePartsFilter, self::TITLE_FILTER_PRIORITY);

            $this->documentTitlePartsFilter = null;

        }



        if ($this->documentTitleFilter !== null) {

            remove_filter('pre_get_document_title', $this->documentTitleFilter, self::TITLE_FILTER_PRIORITY);

            $this->documentTitleFilter = null;

        }

    }



    /**

     * @return array<string, mixed>

     */

    protected function buildLegacyPayload(): array

    {

        $builder = new EventSingleViewBuilder();

        $payload = $builder->build($this->context);



        return is_array($payload) ? $payload : array();

    }



    /**

     * @param array<string, mixed> $legacy

     * @return array<string, mixed>

     */

    protected function buildModel(array $legacy): array

    {

        return array(

            'event' => isset($legacy['event']) && is_array($legacy['event']) ? $legacy['event'] : array(),

            'registration' => isset($legacy['registration']) && is_array($legacy['registration']) ? $legacy['registration'] : array(),

            'photos' => array(

                'items' => isset($legacy['photo_items']) && is_array($legacy['photo_items']) ? $legacy['photo_items'] : array(),

                'count' => isset($legacy['photo_total']) ? (int) $legacy['photo_total'] : 0,

            ),

        );

    }



    /**

     * @param array<string, mixed> $legacy

     * @param array<string, mixed> $model

     * @return array<string, mixed>

     */

    protected function buildView(array $legacy, array $model): array

    {

        return array(

            'page' => $this->buildPageView($legacy, $model),

            'partials' => $this->buildPartialsView($legacy, $model),

        );

    }



    /**

     * @param array<string, mixed> $legacy

     * @param array<string, mixed> $model

     * @return array<string, mixed>

     */

    protected function buildLocalization(array $legacy, array $model): array

    {

        return array(

            'ajax' => array(

                'nonce' => wp_create_nonce('mj-member-event-register'),

                'url' => admin_url('admin-ajax.php'),

            ),

            'event' => array(

                'id' => isset($legacy['event_id']) ? (int) $legacy['event_id'] : 0,

                'title' => isset($legacy['title']) ? (string) $legacy['title'] : '',

            ),

        );

    }



    /**

     * @param array<string, mixed> $legacy

     * @param array<string, mixed> $model

     * @return array<string, mixed>

     */

    private function buildPageView(array $legacy, array $model): array

    {

        $palette = array(

            '--mj-event-accent' => isset($legacy['accent']) ? $legacy['accent'] : '',

            '--mj-event-contrast' => isset($legacy['contrast']) ? $legacy['contrast'] : '',

            '--mj-event-surface' => isset($legacy['surface']) ? $legacy['surface'] : '',

            '--mj-event-border' => isset($legacy['border']) ? $legacy['border'] : '',

            '--mj-event-highlight' => isset($legacy['highlight']) ? $legacy['highlight'] : '',

        );



        $styleTokens = array();

        foreach ($palette as $cssVar => $value) {

            if ($value === '' || $value === null) {

                continue;

            }



            $styleTokens[] = $cssVar . ':' . $value;

        }



        $title = isset($legacy['event_title_display']) ? (string) $legacy['event_title_display'] : '';

        if ($title === '' && isset($model['event']['title'])) {

            $title = (string) $model['event']['title'];

        }



        return array(

            'attributes' => array(

                'class' => 'mj-member-event-single',

                'style' => !empty($styleTokens) ? implode(';', $styleTokens) . ';' : '',

            ),

            'event_title' => $title,

            'display_date' => isset($legacy['display_date_label']) ? (string) $legacy['display_date_label'] : '',

        );

    }



    /**

     * @param array<string, mixed> $legacy

     * @param array<string, mixed> $model

     * @return array<string, array<string, mixed>>

     */

    private function buildPartialsView(array $legacy, array $model): array

    {

        $title = $this->extractString($legacy, 'event_title_display', '', 

            $this->extractString($model['event'] ?? array(), 'title'));



        $statusKey = $this->extractString($legacy, 'status_key');

        $statusLabel = $this->extractString($legacy, 'status_label');

        $activeStatus = $this->extractString($legacy, 'active_status');

        $schedule = $this->extractString($legacy, 'occurrence_schedule_summary', 'schedule');

        $coverUrl = $this->extractString($legacy, 'cover_thumb');

        $coverAlt = $title !== '' ? $title : $this->extractString($legacy, 'title');



        $descriptionHtml = $this->extractString($legacy, 'description_html', 'description');

        $resourceUrl = $this->extractString($legacy, 'article_permalink');



        $locationHasCard = $this->extractBool($legacy, 'location_has_card');

        $locationTitle = $this->extractString($legacy, 'location_display_title');

        $locationAddress = $this->extractString($legacy, 'location_address_display');

        $locationCover = $this->extractString($legacy, 'location_display_cover');

        $locationCoverAlt = $locationTitle !== '' ? $locationTitle : __('Lieu de l\'evenement', 'mj-member');



        $locationTypes = $this->normalizeList(

            $this->extractArray($legacy, 'location_types'),

            function($item) { return is_string($item) ? $item : (string) $item; }

        );



        $locationDescription = $this->extractString($legacy, 'location_description_html', 'location_description');

        $locationNotes = $this->extractString($legacy, 'location_notes_html', 'location_notes');

        $locationMap = $this->extractString($legacy, 'location_display_map');

        $locationMapLink = $this->extractString($legacy, 'location_display_map_link');



        $photoNotice = null;

        if (!empty($legacy['photo_notice']) && is_array($legacy['photo_notice'])) {

            $noticeType = $this->extractString($legacy['photo_notice'], 'type', '', 'info');

            $noticeMessage = $this->extractString($legacy['photo_notice'], 'message');

            $photoNotice = $this->buildNotice($noticeType, $noticeMessage);

        }



        $pendingItems = $this->normalizeList(

            $this->extractArray($legacy, 'photo_member_pending_items'),

            function($item) {

                return array(

                    'thumb' => $this->extractString($item, 'thumb'),

                    'display' => $this->extractString($item, 'display'),

                    'full' => $this->extractString($item, 'full'),

                    'caption' => $this->extractString($item, 'caption'),

                    'submitted' => $this->extractString($item, 'submitted'),

                );

            }

        );



        $photoItems = $this->normalizeList(

            $this->extractArray($legacy, 'photo_items'),

            function($item) {

                return array(

                    'full' => $this->extractString($item, 'full'),

                    'url' => $this->extractString($item, 'url'),

                    'thumb' => $this->extractString($item, 'thumb'),

                    'caption' => $this->extractString($item, 'caption'),

                );

            }

        );



        $photoMemberRemaining = array_key_exists('photo_member_remaining', $legacy) && $legacy['photo_member_remaining'] !== null

            ? (int) $legacy['photo_member_remaining']

            : null;



        $occurrenceItems = array();

        $occurrenceRawItems = $this->extractArray($legacy, 'occurrence_items');

        if (!empty($occurrenceRawItems)) {

            foreach ($occurrenceRawItems as $occurrenceRaw) {

                if (!is_array($occurrenceRaw)) {

                    continue;

                }



                $occurrenceItems[] = array(

                    'label' => isset($occurrenceRaw['label']) ? sanitize_text_field((string) $occurrenceRaw['label']) : '',

                    'start' => isset($occurrenceRaw['start']) ? (string) $occurrenceRaw['start'] : '',

                    'end' => isset($occurrenceRaw['end']) ? (string) $occurrenceRaw['end'] : '',

                    'timestamp' => isset($occurrenceRaw['timestamp']) ? (int) $occurrenceRaw['timestamp'] : 0,

                    'is_past' => !empty($occurrenceRaw['isPast']) || !empty($occurrenceRaw['is_past']),

                    'is_today' => !empty($occurrenceRaw['isToday']) || !empty($occurrenceRaw['is_today']),

                );

            }

        }



        $occurrenceRemaining = $this->extractInt($legacy, 'occurrence_remaining');

        $occurrenceNextLabel = $this->extractString($legacy, 'occurrence_next_label');

        $occurrenceStageLabel = $this->extractString($legacy, 'occurrence_stage_period_label');

        $occurrenceStageTime = $this->extractString($legacy, 'occurrence_stage_time_range');

        $hasMultipleOccurrences = $this->extractBool($legacy, 'event_has_multiple_occurrences');



        return array(

            'hero' => array(

                'type_label' => $this->extractString($legacy, 'type_label'),

                'schedule_summary' => $schedule,

                'schedule_component' => $this->extractString($legacy, 'schedule_component'),

                'status_label' => $statusLabel,

                'status_key' => $statusKey,

                'active_status' => $activeStatus,

                'title' => $title,

                'date_label' => $this->extractString($legacy, 'display_date_label'),

                'cover_url' => $coverUrl,

                'cover_alt' => $coverAlt,

            ),

            'description' => array(

                'content_html' => $descriptionHtml,

                'resource_url' => $resourceUrl,

                'resource_label' => $this->extractString($legacy, 'article_label'),

            ),

            'registration' => $this->buildRegistrationView($legacy),

            'location' => array(

                'has_card' => $locationHasCard,

                'title' => $locationTitle,

                'address' => $locationAddress,

                'cover_url' => $locationCover,

                'cover_alt' => $locationCoverAlt,

                'types' => $locationTypes,

                'description_html' => $locationDescription,

                'notes_html' => $locationNotes,

                'map_embed' => $locationMap,

                'map_link' => $locationMapLink,

            ),

            'photos' => array(

                'notice' => $photoNotice,

                'has_items' => $this->extractBool($legacy, 'photo_has_items'),

                'items' => $photoItems,

                'total' => $this->extractInt($legacy, 'photo_total'),

                'can_upload' => $this->extractBool($legacy, 'photo_can_upload'),

                'is_unlimited' => $this->extractBool($legacy, 'photo_is_unlimited'),

                'member_uploaded' => $this->extractInt($legacy, 'photo_member_uploaded'),

                'member_remaining' => $photoMemberRemaining,

                'reason' => $this->extractString($legacy, 'photo_reason'),

                'pending' => array(

                    'has' => $this->extractBool($legacy, 'photo_member_pending_has'),

                    'count' => $this->extractInt($legacy, 'photo_member_pending_count'),

                    'items' => $pendingItems,

                ),

                'event_title' => $title,

            ),

            'sidebar' => array(

                'deadline_label' => $this->extractString($legacy, 'registration_deadline_label', 'deadline_label'),

                'audience_label' => $this->extractString($legacy, 'age_label'),

                'price_label' => $this->extractString($legacy, 'registration_price_label', 'price_label'),

                'capacity_total' => $this->extractInt($legacy, 'event_capacity_total'),

                'next_label' => $occurrenceNextLabel,

                'remaining_count' => max(0, $occurrenceRemaining),

                'occurrence_items' => $occurrenceItems,

                'has_multiple_occurrences' => $hasMultipleOccurrences,

                'stage_label' => $occurrenceStageLabel,

                'stage_time_range' => $occurrenceStageTime,

            ),

            'animateurs' => $this->buildAnimateursView($legacy),

        );

    }



    /**

     * @param array<string, mixed> $legacy

     * @return array<string, mixed>

     */

    private function buildRegistrationView(array $legacy): array

    {

        $eventId = 0;

        if (isset($legacy['event_id'])) {

            $eventId = (int) $legacy['event_id'];

        } elseif (isset($legacy['event']['id'])) {

            $eventId = (int) $legacy['event']['id'];

        }



        $formParticipants = array();

        if (!empty($legacy['registration_form_participants']) && is_array($legacy['registration_form_participants'])) {

            $formParticipants = $legacy['registration_form_participants'];

        }



        $reservations = array();

        if (!empty($legacy['registration_reservations']) && is_array($legacy['registration_reservations'])) {

            $reservations = $legacy['registration_reservations'];

        }



        $ajax = array(

            'url' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce('mj-member-event-register'),

        );



        $formAvailableCount = isset($legacy['registration_form_available_count'])

            ? (int) $legacy['registration_form_available_count']

            : 0;



        $noteMaxLength = isset($legacy['registration_note_max_length'])

            ? (int) $legacy['registration_note_max_length']

            : 400;



        $participants = $this->prepareRegistrationParticipants(

            $formParticipants,

            !empty($legacy['registration_all_registered']),

            $formAvailableCount

        );



        $confirmationFieldId = 'mj-member-event-confirmation-' . $eventId;

        $confirmationLabel = !empty($legacy['registration_payment_required'])

            ? __('Je confirme ma participation et je finaliserai le paiement via la page suivante.', 'mj-member')

            : __('Je confirme ma participation et le récapitulatif reçu me convient.', 'mj-member');



        $confirmationHint = !empty($legacy['registration_payment_required'])

            ? __('La page de paiement s’ouvrira automatiquement après validation. Tu recevras aussi le lien par email.', 'mj-member')

            : __('Tu recevras un email récapitulatif dès la validation.', 'mj-member');



        return array(

            'is_open' => !empty($legacy['registration_is_open']),

            'show_price' => !empty($legacy['registration_show_price']),

            'price_display' => isset($legacy['registration_price_candidate']) ? (string) $legacy['registration_price_candidate'] : '',

            'has_interactive' => !empty($legacy['registration_has_interactive']),

            'requires_login' => !empty($legacy['registration_requires_login']),

            'form_participants' => $formParticipants,

            'participants' => $participants,

            'all_registered' => !empty($legacy['registration_all_registered']),

            'has_participants' => !empty($legacy['registration_has_participants']),

            'config_json' => isset($legacy['registration_config_json']) ? (string) $legacy['registration_config_json'] : '',

            'can_manage_children' => !empty($legacy['registration_can_manage_children']),

            'note_max_length' => $noteMaxLength,

            'note_field_id' => 'mj-member-event-note-' . $eventId,

            'event_id' => $eventId,

            'form_available_count' => $formAvailableCount,

            'cta_label' => isset($legacy['registration_cta_label']) ? (string) $legacy['registration_cta_label'] : '',

            'cta_registered_label' => isset($legacy['registration_cta_registered']) ? (string) $legacy['registration_cta_registered'] : '',

            'url' => isset($legacy['registration_url']) ? (string) $legacy['registration_url'] : '',

            'payment_required' => !empty($legacy['registration_payment_required']),

            'has_reservations' => !empty($legacy['registration_has_reservations']),

            'reservations' => $reservations,

            'capacity_total' => isset($legacy['event_capacity_total']) ? (int) $legacy['event_capacity_total'] : 0,

            'is_free_participation' => !empty($legacy['registration_is_free_participation']),

            'free_participation_message' => isset($legacy['registration_free_participation_message'])

                ? (string) $legacy['registration_free_participation_message']

                : '',

            'ajax' => $ajax,

            'confirmation' => array(

                'field_id' => $confirmationFieldId,

                'label' => $confirmationLabel,

                'hint' => $confirmationHint,

            ),

            'submit_disabled' => $formAvailableCount === 0,

        );

    }



    /**

     * Build animateurs (instructors/staff) view data.

     *

     * @param array<string, mixed> $legacy

     * @return array<string, mixed>

     */

    private function buildAnimateursView(array $legacy): array

    {

        $items = array();

        if (!empty($legacy['animateur_items']) && is_array($legacy['animateur_items'])) {

            foreach ($legacy['animateur_items'] as $animateurItem) {

                if (!is_array($animateurItem)) {

                    continue;

                }



                $items[] = array(

                    'id' => isset($animateurItem['id']) ? (int) $animateurItem['id'] : 0,

                    'name' => isset($animateurItem['full_name']) ? (string) $animateurItem['full_name'] : '',

                    'role_label' => isset($animateurItem['role_label']) ? (string) $animateurItem['role_label'] : '',

                    'email' => isset($animateurItem['email']) ? (string) $animateurItem['email'] : '',

                    'phone' => isset($animateurItem['phone']) ? (string) $animateurItem['phone'] : '',

                    'phone_uri' => isset($animateurItem['phone']) && $animateurItem['phone'] !== ''

                        ? preg_replace('/[^0-9+]/', '', (string) $animateurItem['phone'])

                        : '',

                    'whatsapp_link' => isset($animateurItem['whatsapp_link']) ? (string) $animateurItem['whatsapp_link'] : '',

                    'avatar_url' => isset($animateurItem['avatar_url']) ? (string) $animateurItem['avatar_url'] : '',

                    'avatar_alt' => isset($animateurItem['avatar_alt']) ? (string) $animateurItem['avatar_alt'] : '',

                    'initials' => isset($animateurItem['initials']) ? (string) $animateurItem['initials'] : '',

                    'is_primary' => !empty($animateurItem['is_primary']),

                );

            }

        }



        $eventId = 0;

        if (isset($legacy['event_id'])) {

            $eventId = (int) $legacy['event_id'];

        } elseif (isset($legacy['event']['id'])) {

            $eventId = (int) $legacy['event']['id'];

        }



        $contactFormUrl = isset($legacy['contact_form_page_url']) ? (string) $legacy['contact_form_page_url'] : '';

        $contactRecipientPrefix = isset($legacy['contact_recipient_prefix']) ? (string) $legacy['contact_recipient_prefix'] : '';



        if (!empty($items)) {

            foreach ($items as $index => $item) {

                $items[$index]['contact_form_url'] = '';

            }

        }



        if (!empty($items) && $contactFormUrl !== '') {

            foreach ($items as $index => $item) {

                $memberId = isset($item['id']) ? (int) $item['id'] : 0;

                if ($memberId <= 0) {

                    continue;

                }



                $recipient = $contactRecipientPrefix !== ''

                    ? $contactRecipientPrefix . ':' . $memberId

                    : ':' . $memberId;



                $query = array('recipient' => $recipient);

                if ($eventId > 0) {

                    $query['event'] = $eventId;

                }



                $items[$index]['contact_form_url'] = add_query_arg($query, $contactFormUrl);

            }

        }



        return array(

            'has_items' => !empty($items),

            'count' => count($items),

            'items' => $items,

            'event_id' => $eventId,

        );

    }



    /**

     * @param array<int, array<string,mixed>> $participants

     * @return array<string,mixed>

     */

    private function prepareRegistrationParticipants(array $participants, bool $allRegistered, int $availableCount): array

    {

        $entries = array();

        $primary = null;

        $firstAvailableSelected = false;



        if (!empty($participants)) {

            foreach ($participants as $participant) {

                $participantId = isset($participant['id']) ? (int) $participant['id'] : 0;

                if ($participantId <= 0) {

                    continue;

                }



                $participantName = isset($participant['name']) ? (string) $participant['name'] : ('#' . $participantId);

                $participantStatusLabel = isset($participant['status_label']) ? (string) $participant['status_label'] : '';

                $participantStatusClass = isset($participant['status_class']) ? (string) $participant['status_class'] : '';

                $participantIsRegistered = !empty($participant['is_registered']);

                $participantEligible = !array_key_exists('eligible', $participant) || !empty($participant['eligible']);



                $participantReasons = array();

                if (!empty($participant['ineligible_reasons']) && is_array($participant['ineligible_reasons'])) {

                    $participantReasons = $participant['ineligible_reasons'];

                } elseif (!empty($participant['ineligibleReasons']) && is_array($participant['ineligibleReasons'])) {

                    $participantReasons = $participant['ineligibleReasons'];

                }



                if (!empty($participantReasons)) {

                    $participantReasons = array_values(array_map('sanitize_text_field', $participantReasons));

                }



                $participantEligibilityLabel = '';

                if (!$participantEligible) {

                    if (!empty($participant['eligibility_label'])) {

                        $participantEligibilityLabel = (string) $participant['eligibility_label'];

                    } else {

                        $participantEligibilityLabel = __('Conditions non respectées', 'mj-member');

                    }

                    $participantStatusClass = trim($participantStatusClass . ' is-ineligible');

                }



                $participantStatusText = '';

                if ($participantIsRegistered && $participantStatusLabel !== '') {

                    $participantStatusText = $participantStatusLabel;

                } elseif (!$participantEligible && $participantEligibilityLabel !== '') {

                    $participantStatusText = $participantEligibilityLabel;

                }



                $inputDisabled = $participantIsRegistered || !$participantEligible;

                $shouldCheck = false;

                if (!$participantIsRegistered && $participantEligible && !$firstAvailableSelected) {

                    $shouldCheck = true;

                    $firstAvailableSelected = true;

                }



                $entries[] = array(

                    'id' => $participantId,

                    'name' => $participantName,

                    'status_class' => $participantStatusClass,

                    'status_text' => $participantStatusText,

                    'status_label' => $participantStatusLabel,

                    'is_registered' => $participantIsRegistered,

                    'eligible' => $participantEligible,

                    'reasons' => $participantReasons,

                    'input_disabled' => $inputDisabled,

                    'should_check' => $shouldCheck,

                );



                if ($shouldCheck && $primary === null) {

                    $primary = array(

                        'id' => $participantId,

                        'name' => $participantName,

                    );

                }

            }

        }



        if ($primary === null && !empty($entries)) {

            $fallback = $entries[0];

            if (!empty($fallback['id'])) {

                $primary = array(

                    'id' => (int) $fallback['id'],

                    'name' => isset($fallback['name']) ? (string) $fallback['name'] : '',

                );

            }

        }



        $showSelector = count($entries) > 1;

        $emptyMessage = $allRegistered

            ? __('Tous vos profils sont déjà inscrits.', 'mj-member')

            : __('Aucun profil éligible n’est disponible pour cette inscription.', 'mj-member');



        return array(

            'entries' => $entries,

            'primary' => $primary,

            'show_selector' => $showSelector,

            'empty_message' => $emptyMessage,

            'empty_hidden' => $availableCount > 0,

        );

    }



    /**

     * Enregistre les assets nécessaires

     */

    protected function enqueueAssets(): void

    {

        $legacy = $this->payload['legacy'] ?? array();

        

        AssetsManager::requirePackage('event-single');



        if (!empty($legacy['registration_needs_script']) || !empty($legacy['registration_has_interactive'])) {

            AssetsManager::requirePackage('events-widget', array('template' => 'event-single'));

        }



        if (!empty($legacy['photo_has_items']) || !empty($legacy['photo_can_upload'])) {

            AssetsManager::requirePackage('event-photos');

        }

    }



    /**

     * @param array<string, mixed> $legacy

     */

    private function installDocumentTitleFilters(array $legacy, array $model = array()): void

    {

        $title = isset($legacy['event_title_display']) ? (string) $legacy['event_title_display'] : '';

        if ($title === '' && isset($legacy['title'])) {

            $title = (string) $legacy['title'];

        }



        if ($title === '' && !empty($legacy['event']) && is_array($legacy['event']) && isset($legacy['event']['title'])) {

            $title = (string) $legacy['event']['title'];

        }



        if ($title === '' && !empty($model) && is_array($model)) {

            if (isset($model['event']) && is_array($model['event']) && isset($model['event']['title'])) {

                $title = (string) $model['event']['title'];

            }

        }



        if ($title === '') {

            return;

        }



        $date = isset($legacy['display_date_label']) ? (string) $legacy['display_date_label'] : '';

        $siteTitle = (string) get_bloginfo('name');



        $primaryTokens = array_filter(array($title, $date), static function ($value) {

            return $value !== '' && $value !== null;

        });

        $primaryTitle = implode(' – ', $primaryTokens);

        if ($primaryTitle === '') {

            $primaryTitle = $title;

        }



        $documentTokens = array($primaryTitle);

        if ($siteTitle !== '' && stripos($primaryTitle, $siteTitle) === false) {

            $documentTokens[] = $siteTitle;

        }



        $documentTitle = implode(' – ', array_filter($documentTokens, static function ($value) {

            return $value !== '' && $value !== null;

        }));



        $partsFilter = static function ($parts) use ($primaryTitle, $siteTitle) {

            if (!is_array($parts)) {

                return $parts;

            }



            $parts['title'] = $primaryTitle !== '' ? $primaryTitle : $siteTitle;



            if ($siteTitle !== '') {

                $parts['site'] = $siteTitle;

            }



            return $parts;

        };



        $titleFilter = static function () use ($documentTitle) {

            return $documentTitle;

        };



        $legacyWpTitleFilter = static function ($currentTitle, $sep = '', $sepLocation = '') use ($documentTitle) {

            if ($documentTitle === '') {

                return $currentTitle;

            }



            return $documentTitle;

        };



        if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {

            add_filter('document_title_parts', $partsFilter, self::TITLE_FILTER_PRIORITY, 1);

            add_filter('pre_get_document_title', $titleFilter, self::TITLE_FILTER_PRIORITY, 0);

            add_filter('wp_title', $legacyWpTitleFilter, self::TITLE_FILTER_PRIORITY, 3);



            $this->documentTitlePartsFilter = $partsFilter;

            $this->documentTitleFilter = $titleFilter;

            $this->documentWpTitleFilter = $legacyWpTitleFilter;

        }

    }

}

