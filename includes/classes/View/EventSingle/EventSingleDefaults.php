<?php



namespace Mj\Member\Classes\View\EventSingle;



if (!defined('ABSPATH')) {

    exit;

}



final class EventSingleDefaults

{

    /**

     * Apply the default casting rules that legacy templates expect.

     *

     * @param array<string,mixed> $state

     * @param array<string,mixed> $event

     */

    public static function apply(array &$state, array $event): void

    {

        self::ensureIntegers($state, $event);

        self::ensureBooleans($state);

        self::ensureStrings($state);

        self::ensureArrays($state);

        self::ensureNumericAmounts($state);

        self::ensureOptionalFilters($state);

    }



    /**

     * @param array<string,mixed> $state

     * @param array<string,mixed> $event

     */

    private static function ensureIntegers(array &$state, array $event): void

    {

        $defaults = array(

            'event_id' => isset($event['id']) ? (int) $event['id'] : 0,

            'event_capacity_total' => 0,

            'animateurs_count' => 0,

            'occurrence_stage_start_ts' => 0,

            'occurrence_stage_end_ts' => 0,

            'occurrence_reference_count' => 0,

            'occurrence_remaining' => 0,

        );



        foreach ($defaults as $key => $fallback) {

            if (!array_key_exists($key, $state) || $state[$key] === null) {

                $state[$key] = $fallback;

                continue;

            }



            $state[$key] = (int) $state[$key];

        }

    }



    /**

     * @param array<string,mixed> $state

     */

    private static function ensureBooleans(array &$state): void

    {

        $defaults = array(

            'is_stage_event' => false,

            'registration_payment_required' => false,

            'registration_show_price' => false,

            'registration_is_free_participation' => false,

            'registration_price_is_zero_numeric' => false,

            'registration_price_is_free' => false,

            'location_has_card' => false,

            'event_has_multiple_occurrences' => false,

        );



        foreach ($defaults as $key => $fallback) {

            if (!array_key_exists($key, $state) || $state[$key] === null) {

                $state[$key] = $fallback;

                continue;

            }



            $state[$key] = (bool) $state[$key];

        }

    }



    /**

     * @param array<string,mixed> $state

     */

    private static function ensureStrings(array &$state): void

    {

        $defaults = array(

            'status_key' => '',

            'active_status' => '',

            'status_label' => '',

            'title' => '',

            'event_type_key' => '',

            'type_label' => '',

            'date_label' => '',

            'deadline_label' => '',

            'price_label' => '',

            'age_label' => '',

            'location_label' => '',

            'location_address' => '',

            'location_description' => '',

            'location_map' => '',

            'location_map_link' => '',

            'location_cover' => '',

            'description' => '',

            'excerpt' => '',

            'registration_url' => '',

            'article_permalink' => '',

            'cover_url' => '',

            'cover_thumb' => '',

            'registration_price_label' => '',

            'registration_deadline_label' => '',

            'registration_price_candidate' => '',

            'registration_price_plain' => '',

            'registration_price_plain_lower' => '',

            'registration_free_participation_message' => '',

            'occurrence_stage_period_label' => '',

            'occurrence_display_time' => '',

            'occurrence_next' => '',

            'occurrence_next_label' => '',

            'display_date_label' => '',

            'accent' => '',

            'contrast' => '',

            'surface' => '',

            'border' => '',

            'highlight' => '',

            'description_html' => '',

            'excerpt_html' => '',

            'contact_form_page_url' => '',

            'contact_recipient_prefix' => '',

            'location_display_title' => '',

            'location_display_cover' => '',

            'location_display_map' => '',

            'location_display_map_link' => '',

            'location_address_display' => '',

            'location_description_html' => '',

            'location_notes_html' => '',

            'occurrence_schedule_summary' => '',

            'occurrence_stage_time_range' => '',

        );



        foreach ($defaults as $key => $fallback) {

            if (!array_key_exists($key, $state) || $state[$key] === null) {

                $state[$key] = $fallback;

                continue;

            }



            if (is_array($state[$key])) {

                continue;

            }



            $state[$key] = (string) $state[$key];

        }

    }



    /**

     * @param array<string,mixed> $state

     */

    private static function ensureArrays(array &$state): void

    {

        $defaults = array(

            'animateur_items' => array(),

            'location_context' => array(),

            'location_types' => array(),

            'occurrence_preview' => array(),

            'occurrence_items' => array(),

            'occurrence_reference_items' => array(),

            'weekday_order_map' => array(),

            'time_range_map' => array(),

            'registration_occurrence_catalog' => array(),

            'palette' => array(),

        );



        foreach ($defaults as $key => $fallback) {

            if (!array_key_exists($key, $state) || !is_array($state[$key])) {

                $state[$key] = $fallback;

            }

        }

    }



    /**

     * @param array<string,mixed> $state

     */

    private static function ensureNumericAmounts(array &$state): void

    {

        if (!isset($state['registration_price_amount']) || !is_numeric($state['registration_price_amount'])) {

            $state['registration_price_amount'] = 0;

            return;

        }



        $state['registration_price_amount'] = 0 + $state['registration_price_amount'];

    }



    /**

     * @param array<string,mixed> $state

     */

    private static function ensureOptionalFilters(array &$state): void

    {

        if (!array_key_exists('document_title_filter', $state)) {

            $state['document_title_filter'] = null;

        }



        if (!array_key_exists('document_title_parts_filter', $state)) {

            $state['document_title_parts_filter'] = null;

        }

    }

}

