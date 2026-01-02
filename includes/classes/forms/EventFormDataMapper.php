<?php

namespace Mj\Member\Classes\Forms;

use Mj\Member\Classes\Crud\MjEvents;
use function array_key_exists;
use function sanitize_text_field;

if (!defined('ABSPATH')) {
    exit;
}

final class EventFormDataMapper
{
    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function fromValues(array $values): array
    {
        return array(
            'event_title' => isset($values['title']) ? (string) $values['title'] : '',
            'event_status' => isset($values['status']) ? (string) $values['status'] : MjEvents::STATUS_DRAFT,
            'event_type' => isset($values['type']) ? (string) $values['type'] : MjEvents::TYPE_STAGE,
            'event_accent_color' => isset($values['accent_color']) ? (string) $values['accent_color'] : '',
            'event_article_cat' => isset($values['article_cat']) ? (int) $values['article_cat'] : 0,
            'event_article_id' => isset($values['article_id']) ? (int) $values['article_id'] : 0,
            'event_cover_id' => isset($values['cover_id']) ? (int) $values['cover_id'] : 0,
            'event_location_id' => isset($values['location_id']) ? (int) $values['location_id'] : 0,
            'event_animateur_ids' => isset($values['animateur_ids']) && is_array($values['animateur_ids']) ? array_map('intval', $values['animateur_ids']) : array(),
            'event_volunteer_ids' => isset($values['volunteer_ids']) && is_array($values['volunteer_ids']) ? array_map('intval', $values['volunteer_ids']) : array(),
            'event_allow_guardian_registration' => !empty($values['allow_guardian_registration']),
            'event_free_participation' => !empty($values['registration_is_free_participation']),
            'event_requires_validation' => array_key_exists('requires_validation', $values) ? !empty($values['requires_validation']) : true,
            'event_capacity_total' => isset($values['capacity_total']) ? (int) $values['capacity_total'] : 0,
            'event_capacity_waitlist' => isset($values['capacity_waitlist']) ? (int) $values['capacity_waitlist'] : 0,
            'event_capacity_notify_threshold' => isset($values['capacity_notify_threshold']) ? (int) $values['capacity_notify_threshold'] : 0,
            'event_age_min' => isset($values['age_min']) ? (int) $values['age_min'] : 0,
            'event_age_max' => isset($values['age_max']) ? (int) $values['age_max'] : 0,
            'event_schedule_mode' => isset($values['schedule_mode']) ? (string) $values['schedule_mode'] : 'fixed',
            'event_date_start' => isset($values['date_debut']) ? (string) $values['date_debut'] : '',
            'event_date_end' => isset($values['date_fin']) ? (string) $values['date_fin'] : '',
            'event_fixed_date' => isset($values['schedule_fixed_date']) ? (string) $values['schedule_fixed_date'] : '',
            'event_fixed_start_time' => isset($values['schedule_fixed_start_time']) ? (string) $values['schedule_fixed_start_time'] : '',
            'event_fixed_end_time' => isset($values['schedule_fixed_end_time']) ? (string) $values['schedule_fixed_end_time'] : '',
            'event_range_start' => isset($values['schedule_range_start']) ? (string) $values['schedule_range_start'] : '',
            'event_range_end' => isset($values['schedule_range_end']) ? (string) $values['schedule_range_end'] : '',
            'event_recurring_start_date' => isset($values['schedule_recurring_start_date']) ? (string) $values['schedule_recurring_start_date'] : '',
            'event_recurring_start_time' => isset($values['schedule_recurring_start_time']) ? (string) $values['schedule_recurring_start_time'] : '',
            'event_recurring_end_time' => isset($values['schedule_recurring_end_time']) ? (string) $values['schedule_recurring_end_time'] : '',
            'event_recurring_frequency' => isset($values['schedule_recurring_frequency']) ? (string) $values['schedule_recurring_frequency'] : 'weekly',
            'event_recurring_interval' => isset($values['schedule_recurring_interval']) ? (int) $values['schedule_recurring_interval'] : 1,
            'event_recurring_weekdays' => isset($values['schedule_recurring_weekdays']) && is_array($values['schedule_recurring_weekdays']) ? array_map('sanitize_key', $values['schedule_recurring_weekdays']) : array(),
            'event_recurring_month_ordinal' => isset($values['schedule_recurring_month_ordinal']) ? (string) $values['schedule_recurring_month_ordinal'] : 'first',
            'event_recurring_month_weekday' => isset($values['schedule_recurring_month_weekday']) ? (string) $values['schedule_recurring_month_weekday'] : 'saturday',
            'event_recurring_until' => isset($values['recurrence_until']) ? (string) $values['recurrence_until'] : '',
            'event_series_items' => isset($values['schedule_series_items']) ? wp_json_encode($values['schedule_series_items']) : '[]',
            'event_occurrence_selection_mode' => isset($values['occurrence_selection_mode']) ? (string) $values['occurrence_selection_mode'] : 'member_choice',
            'event_date_deadline' => isset($values['date_fin_inscription']) ? (string) $values['date_fin_inscription'] : '',
            'event_price' => isset($values['prix']) ? (float) $values['prix'] : 0.0,
            'event_description' => isset($values['description']) ? (string) $values['description'] : '',
        );
    }

    /**
     * @param array<string,mixed> $values
     * @param array<string,mixed> $formData
     * @return array<string,mixed>
     */
    public static function mergeIntoValues(array $values, array $formData): array
    {
        $values['title'] = isset($formData['event_title']) ? sanitize_text_field((string) $formData['event_title']) : $values['title'];
        $values['status'] = isset($formData['event_status']) ? sanitize_key((string) $formData['event_status']) : $values['status'];
        $values['type'] = isset($formData['event_type']) ? sanitize_key((string) $formData['event_type']) : $values['type'];
        $values['accent_color'] = isset($formData['event_accent_color']) ? (string) $formData['event_accent_color'] : $values['accent_color'];
        $values['article_cat'] = isset($formData['event_article_cat']) ? (int) $formData['event_article_cat'] : $values['article_cat'];
        $values['article_id'] = isset($formData['event_article_id']) ? (int) $formData['event_article_id'] : $values['article_id'];
        $values['cover_id'] = isset($formData['event_cover_id']) ? (int) $formData['event_cover_id'] : $values['cover_id'];
        $values['location_id'] = isset($formData['event_location_id']) ? (int) $formData['event_location_id'] : $values['location_id'];
        $values['animateur_ids'] = isset($formData['event_animateur_ids']) && is_array($formData['event_animateur_ids']) ? array_map('intval', $formData['event_animateur_ids']) : array();
        $values['volunteer_ids'] = isset($formData['event_volunteer_ids']) && is_array($formData['event_volunteer_ids']) ? array_map('intval', $formData['event_volunteer_ids']) : array();
        $values['allow_guardian_registration'] = !empty($formData['event_allow_guardian_registration']);
        $values['registration_is_free_participation'] = !empty($formData['event_free_participation']);
        $values['free_participation'] = !empty($formData['event_free_participation']);
        $values['requires_validation'] = !empty($formData['event_requires_validation']);
        $values['capacity_total'] = isset($formData['event_capacity_total']) ? (int) $formData['event_capacity_total'] : $values['capacity_total'];
        $values['capacity_waitlist'] = isset($formData['event_capacity_waitlist']) ? (int) $formData['event_capacity_waitlist'] : $values['capacity_waitlist'];
        $values['capacity_notify_threshold'] = isset($formData['event_capacity_notify_threshold']) ? (int) $formData['event_capacity_notify_threshold'] : $values['capacity_notify_threshold'];
        $values['age_min'] = isset($formData['event_age_min']) ? (int) $formData['event_age_min'] : $values['age_min'];
        $values['age_max'] = isset($formData['event_age_max']) ? (int) $formData['event_age_max'] : $values['age_max'];
        $values['schedule_mode'] = isset($formData['event_schedule_mode']) ? sanitize_key((string) $formData['event_schedule_mode']) : $values['schedule_mode'];
        $values['date_debut'] = isset($formData['event_date_start']) ? (string) $formData['event_date_start'] : $values['date_debut'];
        $values['date_fin'] = isset($formData['event_date_end']) ? (string) $formData['event_date_end'] : $values['date_fin'];
        $values['schedule_fixed_date'] = isset($formData['event_fixed_date']) ? (string) $formData['event_fixed_date'] : $values['schedule_fixed_date'];
        $values['schedule_fixed_start_time'] = isset($formData['event_fixed_start_time']) ? (string) $formData['event_fixed_start_time'] : $values['schedule_fixed_start_time'];
        $values['schedule_fixed_end_time'] = isset($formData['event_fixed_end_time']) ? (string) $formData['event_fixed_end_time'] : $values['schedule_fixed_end_time'];
        $values['schedule_range_start'] = isset($formData['event_range_start']) ? (string) $formData['event_range_start'] : $values['schedule_range_start'];
        $values['schedule_range_end'] = isset($formData['event_range_end']) ? (string) $formData['event_range_end'] : $values['schedule_range_end'];
        $values['schedule_recurring_start_date'] = isset($formData['event_recurring_start_date']) ? (string) $formData['event_recurring_start_date'] : $values['schedule_recurring_start_date'];
        $values['schedule_recurring_start_time'] = isset($formData['event_recurring_start_time']) ? (string) $formData['event_recurring_start_time'] : $values['schedule_recurring_start_time'];
        $values['schedule_recurring_end_time'] = isset($formData['event_recurring_end_time']) ? (string) $formData['event_recurring_end_time'] : $values['schedule_recurring_end_time'];
        $values['schedule_recurring_frequency'] = isset($formData['event_recurring_frequency']) ? sanitize_key((string) $formData['event_recurring_frequency']) : $values['schedule_recurring_frequency'];
        $values['schedule_recurring_interval'] = isset($formData['event_recurring_interval']) ? (int) $formData['event_recurring_interval'] : $values['schedule_recurring_interval'];
        $values['schedule_recurring_weekdays'] = isset($formData['event_recurring_weekdays']) && is_array($formData['event_recurring_weekdays']) ? array_map('sanitize_key', $formData['event_recurring_weekdays']) : $values['schedule_recurring_weekdays'];
        $values['schedule_recurring_month_ordinal'] = isset($formData['event_recurring_month_ordinal']) ? sanitize_key((string) $formData['event_recurring_month_ordinal']) : $values['schedule_recurring_month_ordinal'];
        $values['schedule_recurring_month_weekday'] = isset($formData['event_recurring_month_weekday']) ? sanitize_key((string) $formData['event_recurring_month_weekday']) : $values['schedule_recurring_month_weekday'];
        $values['recurrence_until'] = isset($formData['event_recurring_until']) ? (string) $formData['event_recurring_until'] : $values['recurrence_until'];
        if (isset($formData['event_series_items'])) {
            $decoded_series = json_decode((string) $formData['event_series_items'], true);
            $values['schedule_series_items'] = is_array($decoded_series) ? $decoded_series : array();
        }
        $values['occurrence_selection_mode'] = isset($formData['event_occurrence_selection_mode']) ? sanitize_key((string) $formData['event_occurrence_selection_mode']) : $values['occurrence_selection_mode'];
        $values['date_fin_inscription'] = isset($formData['event_date_deadline']) ? (string) $formData['event_date_deadline'] : $values['date_fin_inscription'];
        $values['prix'] = isset($formData['event_price']) ? (float) $formData['event_price'] : $values['prix'];
        $values['description'] = isset($formData['event_description']) ? (string) $formData['event_description'] : $values['description'];

        return $values;
    }
}
