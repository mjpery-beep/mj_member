<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD for event location links (many-to-many with location type)
 * 
 * Location types:
 * - departure: Lieu de départ
 * - activity: Lieu d'activité  
 * - return: Lieu de retour
 * - other: Autre
 */
class MjEventLocationLinks {
    const TABLE = 'mj_event_location_links';

    const TYPE_DEPARTURE = 'departure';
    const TYPE_ACTIVITY = 'activity';
    const TYPE_RETURN = 'return';
    const TYPE_OTHER = 'other';

    /**
     * @return array<string,string>
     */
    public static function get_type_labels(): array {
        return array(
            self::TYPE_DEPARTURE => __('Lieu de départ', 'mj-member'),
            self::TYPE_ACTIVITY => __("Lieu d'activité", 'mj-member'),
            self::TYPE_RETURN => __('Lieu de retour', 'mj-member'),
            self::TYPE_OTHER => __('Autre', 'mj-member'),
        );
    }

    /**
     * @return array<string>
     */
    public static function get_valid_types(): array {
        return array(
            self::TYPE_DEPARTURE,
            self::TYPE_ACTIVITY,
            self::TYPE_RETURN,
            self::TYPE_OTHER,
        );
    }

    public static function is_ready(): bool {
        self::ensure_schema();
        if (!function_exists('mj_member_get_event_location_links_table_name') || !function_exists('mj_member_table_exists')) {
            return false;
        }

        $table = mj_member_get_event_location_links_table_name();
        return mj_member_table_exists($table);
    }

    public static function get_table_name(): string {
        return function_exists('mj_member_get_event_location_links_table_name') 
            ? mj_member_get_event_location_links_table_name() 
            : '';
    }

    /**
     * Get all location links for an event
     * 
     * @param int $event_id
     * @return array<int, array{id: int, event_id: int, location_id: int, location_type: string, sort_order: int}>
     */
    public static function get_by_event(int $event_id): array {
        $event_id = (int) $event_id;
        if ($event_id <= 0 || !self::is_ready()) {
            return array();
        }

        global $wpdb;
        $table = self::get_table_name();
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id = %d ORDER BY sort_order ASC, id ASC",
            $event_id
        ), ARRAY_A);

        if (!is_array($results)) {
            return array();
        }

        return array_map(function($row) {
            return array(
                'id' => (int) $row['id'],
                'event_id' => (int) $row['event_id'],
                'location_id' => (int) $row['location_id'],
                'location_type' => sanitize_key($row['location_type']),
                'custom_label' => isset($row['custom_label']) ? (string) $row['custom_label'] : '',
                'meeting_time' => isset($row['meeting_time']) ? (string) $row['meeting_time'] : '',
                'meeting_time_end' => isset($row['meeting_time_end']) ? (string) $row['meeting_time_end'] : '',
                'sort_order' => (int) $row['sort_order'],
            );
        }, $results);
    }

    /**
     * Get location links with full location data for an event
     * 
     * @param int $event_id
     * @return array<int, array>
     */
    public static function get_with_locations(int $event_id): array {
        $links = self::get_by_event($event_id);
        if (empty($links)) {
            return array();
        }

        $type_labels = self::get_type_labels();

        return array_map(function($link) use ($type_labels) {
            $location = null;
            if (class_exists(MjEventLocations::class)) {
                $loc = MjEventLocations::find($link['location_id']);
                if ($loc) {
                    $location = array(
                        'id' => $loc->id,
                        'name' => $loc->name,
                        'address' => $loc->address_line ?? '',
                        'address_line' => $loc->address_line ?? '',
                        'street' => $loc->address_line ?? '',
                        'postal_code' => $loc->postal_code ?? '',
                        'city' => $loc->city ?? '',
                        'country' => $loc->country ?? '',
                        'latitude' => $loc->latitude ?? null,
                        'longitude' => $loc->longitude ?? null,
                        'notes' => $loc->notes ?? '',
                        'cover_id' => $loc->cover_id ?? null,
                    );
                }
            }

            // Use custom_label for "other" type if available
            $customLabel = isset($link['custom_label']) ? (string) $link['custom_label'] : '';
            $meetingTime = isset($link['meeting_time']) ? (string) $link['meeting_time'] : '';
            $meetingTimeEnd = isset($link['meeting_time_end']) ? (string) $link['meeting_time_end'] : '';
            $typeLabel = $type_labels[$link['location_type']] ?? $link['location_type'];
            if ($link['location_type'] === 'other' && $customLabel !== '') {
                $typeLabel = $customLabel;
            }

            return array(
                'id' => $link['id'],
                'locationId' => $link['location_id'],
                'locationType' => $link['location_type'],
                'locationTypeLabel' => $typeLabel,
                'customLabel' => $customLabel,
                'meetingTime' => $meetingTime,
                'meetingTimeEnd' => $meetingTimeEnd,
                'sortOrder' => $link['sort_order'],
                'location' => $location,
            );
        }, $links);
    }

    /**
     * Get location IDs for an event
     * 
     * @param int $event_id
     * @return array<int>
     */
    public static function get_ids_by_event(int $event_id): array {
        $links = self::get_by_event($event_id);
        return array_map(function($link) {
            return $link['location_id'];
        }, $links);
    }

    /**
     * Sync location links for an event
     * 
     * @param int $event_id
     * @param array<int, array{location_id: int, location_type: string, sort_order?: int}> $location_links
     */
    public static function sync_for_event(int $event_id, array $location_links): void {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return;
        }

        self::ensure_schema();

        if (!self::is_ready()) {
            return;
        }

        global $wpdb;
        $table = self::get_table_name();
        $valid_types = self::get_valid_types();

        // Delete existing links
        $wpdb->delete($table, array('event_id' => $event_id), array('%d'));

        // Insert new links
        $sort = 0;
        foreach ($location_links as $link) {
            $location_id = isset($link['location_id']) ? (int) $link['location_id'] : 0;
            $location_type = isset($link['location_type']) ? sanitize_key($link['location_type']) : self::TYPE_ACTIVITY;

            if ($location_id <= 0) {
                continue;
            }

            if (!in_array($location_type, $valid_types, true)) {
                $location_type = self::TYPE_ACTIVITY;
            }

            $sort_order = isset($link['sort_order']) ? (int) $link['sort_order'] : $sort;
            $custom_label = isset($link['custom_label']) ? sanitize_textarea_field($link['custom_label']) : null;
            $meeting_time = isset($link['meeting_time']) ? sanitize_text_field($link['meeting_time']) : null;
            $meeting_time_end = isset($link['meeting_time_end']) ? sanitize_text_field($link['meeting_time_end']) : null;

            $wpdb->insert(
                $table,
                array(
                    'event_id' => $event_id,
                    'location_id' => $location_id,
                    'location_type' => $location_type,
                    'custom_label' => $custom_label,
                    'meeting_time' => $meeting_time,
                    'meeting_time_end' => $meeting_time_end,
                    'sort_order' => $sort_order,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s')
            );

            $sort++;
        }

        // Sync primary location_id column with first activity or first link
        self::sync_primary_column($event_id, $location_links);
    }

    /**
     * Sync the legacy location_id column on events table
     */
    private static function sync_primary_column(int $event_id, array $location_links): void {
        if (!function_exists('mj_member_column_exists') || !function_exists('mj_member_get_events_table_name')) {
            return;
        }

        global $wpdb;
        $events_table = mj_member_get_events_table_name();

        if (!mj_member_table_exists($events_table) || !mj_member_column_exists($events_table, 'location_id')) {
            return;
        }

        // Find primary location: first activity type, or first link, or 0
        $primary_id = 0;
        foreach ($location_links as $link) {
            $location_id = isset($link['location_id']) ? (int) $link['location_id'] : 0;
            $location_type = isset($link['location_type']) ? sanitize_key($link['location_type']) : '';
            
            if ($location_id > 0 && $primary_id === 0) {
                $primary_id = $location_id;
            }
            
            if ($location_id > 0 && $location_type === self::TYPE_ACTIVITY) {
                $primary_id = $location_id;
                break;
            }
        }

        $wpdb->update(
            $events_table,
            array('location_id' => $primary_id),
            array('id' => $event_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Add a single location link
     * 
     * @param int $event_id
     * @param int $location_id
     * @param string $location_type
     * @param int $sort_order
     * @return int|false Insert ID or false on failure
     */
    public static function add_link(int $event_id, int $location_id, string $location_type = self::TYPE_ACTIVITY, int $sort_order = 0) {
        if ($event_id <= 0 || $location_id <= 0 || !self::is_ready()) {
            return false;
        }

        $valid_types = self::get_valid_types();
        if (!in_array($location_type, $valid_types, true)) {
            $location_type = self::TYPE_ACTIVITY;
        }

        global $wpdb;
        $table = self::get_table_name();

        $inserted = $wpdb->insert(
            $table,
            array(
                'event_id' => $event_id,
                'location_id' => $location_id,
                'location_type' => $location_type,
                'sort_order' => $sort_order,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%d', '%s')
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Remove a specific link
     * 
     * @param int $link_id
     * @return bool
     */
    public static function remove_link(int $link_id): bool {
        if ($link_id <= 0 || !self::is_ready()) {
            return false;
        }

        global $wpdb;
        $table = self::get_table_name();

        return (bool) $wpdb->delete($table, array('id' => $link_id), array('%d'));
    }

    /**
     * Delete all links for an event
     * 
     * @param int $event_id
     * @return int Number of deleted rows
     */
    public static function delete_for_event(int $event_id): int {
        if ($event_id <= 0 || !self::is_ready()) {
            return 0;
        }

        global $wpdb;
        $table = self::get_table_name();

        return (int) $wpdb->delete($table, array('event_id' => $event_id), array('%d'));
    }

    /**
     * Get events using a specific location
     * 
     * @param int $location_id
     * @return array<int>
     */
    public static function get_event_ids_for_location(int $location_id): array {
        if ($location_id <= 0 || !self::is_ready()) {
            return array();
        }

        global $wpdb;
        $table = self::get_table_name();

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT event_id FROM {$table} WHERE location_id = %d",
            $location_id
        ));

        return array_map('intval', $results ?: array());
    }

    /**
     * Migrate legacy location_id from events table to links table
     * 
     * @param int $event_id Optional specific event to migrate
     * @return int Number of migrated events
     */
    public static function migrate_legacy_locations(int $event_id = 0): int {
        if (!self::is_ready()) {
            return 0;
        }

        if (!function_exists('mj_member_column_exists') || !function_exists('mj_member_get_events_table_name')) {
            return 0;
        }

        global $wpdb;
        $events_table = mj_member_get_events_table_name();
        $links_table = self::get_table_name();

        if (!mj_member_table_exists($events_table) || !mj_member_column_exists($events_table, 'location_id')) {
            return 0;
        }

        // Get events with location_id but no links
        $query = "SELECT e.id, e.location_id 
                  FROM {$events_table} e 
                  LEFT JOIN {$links_table} l ON e.id = l.event_id 
                  WHERE e.location_id > 0 AND l.id IS NULL";

        if ($event_id > 0) {
            $query .= $wpdb->prepare(" AND e.id = %d", $event_id);
        }

        $events = $wpdb->get_results($query);
        $migrated = 0;

        foreach ($events as $event) {
            $inserted = $wpdb->insert(
                $links_table,
                array(
                    'event_id' => (int) $event->id,
                    'location_id' => (int) $event->location_id,
                    'location_type' => self::TYPE_ACTIVITY,
                    'sort_order' => 0,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%d', '%s')
            );

            if ($inserted) {
                $migrated++;
            }
        }

        return $migrated;
    }

    /**
     * Ensure the table schema exists
     */
    public static function ensure_schema(): void {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (function_exists('mj_member_ensure_event_location_links_table')) {
            mj_member_ensure_event_location_links_table();
        }
    }
}
