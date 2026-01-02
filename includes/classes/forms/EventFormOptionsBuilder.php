<?php

namespace Mj\Member\Classes\Forms;

use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Value\EventLocationData;

if (!defined('ABSPATH')) {
    exit;
}

final class EventFormOptionsBuilder
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function build(array $context): array
    {
        $statusLabels = isset($context['status_labels']) && is_array($context['status_labels']) ? $context['status_labels'] : array();
        $typeLabels = isset($context['type_labels']) && is_array($context['type_labels']) ? $context['type_labels'] : array();
        $typeColors = isset($context['type_colors']) && is_array($context['type_colors']) ? $context['type_colors'] : array();
        $currentType = isset($context['current_type']) ? sanitize_key((string) $context['current_type']) : '';
        $scheduleWeekdays = isset($context['schedule_weekdays']) && is_array($context['schedule_weekdays']) ? $context['schedule_weekdays'] : array();
        $scheduleMonthOrdinals = isset($context['schedule_month_ordinals']) && is_array($context['schedule_month_ordinals']) ? $context['schedule_month_ordinals'] : array();
        $accentDefault = isset($context['accent_default_color']) ? (string) $context['accent_default_color'] : '';

        if ($accentDefault === '') {
            if ($currentType !== '' && isset($typeColors[$currentType])) {
                $accentDefault = $typeColors[$currentType];
            } elseif (method_exists(MjEvents::class, 'get_default_color_for_type')) {
                $candidate = MjEvents::get_default_color_for_type($currentType);
                if (function_exists('mj_member_admin_normalize_hex_color')) {
                    $accentDefault = mj_member_admin_normalize_hex_color($candidate);
                } else {
                    $accentDefault = (string) $candidate;
                }
            }
        }

        $typeChoiceAttributes = array();
        foreach ($typeColors as $typeKey => $colorValue) {
            $typeKey = sanitize_key((string) $typeKey);
            if ($typeKey === '' || $colorValue === '') {
                continue;
            }
            $typeChoiceAttributes[$typeKey] = array('data-default-color' => (string) $colorValue);
        }

        $categoryMap = array();
        if (!empty($context['article_categories']) && is_array($context['article_categories'])) {
            foreach ($context['article_categories'] as $categoryItem) {
                if (!is_object($categoryItem) || !isset($categoryItem->term_id)) {
                    continue;
                }
                $categoryMap[(int) $categoryItem->term_id] = $categoryItem->name;
            }
        }

        $articleMap = array();
        $articleAttr = array();
        if (!empty($context['articles']) && is_array($context['articles'])) {
            foreach ($context['articles'] as $articleItem) {
                if (!is_object($articleItem) || !isset($articleItem->ID)) {
                    continue;
                }
                $postId = (int) $articleItem->ID;
                $articleMap[$postId] = get_the_title($articleItem);
                $link = get_permalink($postId);
                $thumbId = get_post_thumbnail_id($postId);
                $thumbSrc = $thumbId ? wp_get_attachment_image_url($thumbId, 'medium') : '';
                $articleAttr[$postId] = array(
                    'data-link' => $link ? (string) $link : '',
                    'data-image-id' => $thumbId ? (string) (int) $thumbId : '',
                    'data-image-src' => $thumbSrc ? (string) $thumbSrc : '',
                );
            }
        }

        $locationMap = array();
        $locationAttr = array();
        if (!empty($context['locations']) && is_array($context['locations'])) {
            foreach ($context['locations'] as $locationItem) {
                if ($locationItem instanceof EventLocationData) {
                    $locationData = $locationItem->toArray();
                } elseif (is_object($locationItem)) {
                    $locationData = get_object_vars($locationItem);
                } else {
                    $locationData = (array) $locationItem;
                }
                $locationId = isset($locationData['id']) ? (int) $locationData['id'] : 0;
                if ($locationId <= 0) {
                    continue;
                }
                $city = isset($locationData['city']) ? (string) $locationData['city'] : '';
                $label = isset($locationData['name']) ? (string) $locationData['name'] : 'Lieu #' . $locationId;
                if ($city !== '') {
                    $label .= ' (' . $city . ')';
                }
                $locationMap[$locationId] = $label;
                $iconValue = isset($locationData['icon']) ? sanitize_text_field((string) $locationData['icon']) : '';
                $coverId = isset($locationData['cover_id']) ? (int) $locationData['cover_id'] : 0;
                $coverSrc = '';
                if ($coverId > 0 && function_exists('wp_get_attachment_image_url')) {
                    $coverCandidate = wp_get_attachment_image_url($coverId, 'medium');
                    if (is_string($coverCandidate)) {
                        $coverSrc = $coverCandidate;
                    }
                }
                $coverAdminUrl = add_query_arg(
                    array(
                        'page' => 'mj_locations',
                        'action' => 'edit',
                        'location' => $locationId,
                    ),
                    admin_url('admin.php')
                );
                $locationAttr[$locationId] = array(
                    'data-address' => MjEventLocations::format_address($locationData),
                    'data-map' => MjEventLocations::build_map_embed_src($locationData),
                    'data-notes' => isset($locationData['notes']) ? (string) $locationData['notes'] : '',
                    'data-city' => $city,
                    'data-country' => isset($locationData['country']) ? (string) $locationData['country'] : '',
                    'data-icon' => $iconValue,
                    'data-cover-id' => $coverId > 0 ? (string) $coverId : '',
                    'data-cover-src' => $coverSrc !== '' ? esc_url_raw($coverSrc) : '',
                    'data-cover-admin' => esc_url_raw($coverAdminUrl),
                );
            }
        }

        $animateurMap = array();
        $animateurAttr = array();
        if (!empty($context['animateurs']) && is_array($context['animateurs'])) {
            foreach ($context['animateurs'] as $animateurItem) {
                if (!is_object($animateurItem) || !isset($animateurItem->id)) {
                    continue;
                }
                $animateurId = (int) $animateurItem->id;
                if ($animateurId <= 0) {
                    continue;
                }
                $first = isset($animateurItem->first_name) ? $animateurItem->first_name : '';
                $last = isset($animateurItem->last_name) ? $animateurItem->last_name : '';
                $display = trim($first . ' ' . $last);
                if ($display === '') {
                    $display = 'Animateur #' . $animateurId;
                }
                $animateurMap[$animateurId] = $display;
                if (!empty($animateurItem->email) && is_email($animateurItem->email)) {
                    $animateurAttr[$animateurId] = array('data-email' => (string) $animateurItem->email);
                }
            }
        }

        $volunteerMap = array();
        $volunteerAttr = array();
        if (!empty($context['volunteers']) && is_array($context['volunteers'])) {
            foreach ($context['volunteers'] as $volunteerItem) {
                if (!is_object($volunteerItem) || !isset($volunteerItem->id)) {
                    continue;
                }
                $volunteerId = (int) $volunteerItem->id;
                if ($volunteerId <= 0) {
                    continue;
                }
                $first = isset($volunteerItem->first_name) ? $volunteerItem->first_name : '';
                $last = isset($volunteerItem->last_name) ? $volunteerItem->last_name : '';
                $display = trim($first . ' ' . $last);
                if ($display === '') {
                    $display = 'Bénévole #' . $volunteerId;
                }
                $volunteerMap[$volunteerId] = $display;
                if (!empty($volunteerItem->email) && is_email($volunteerItem->email)) {
                    $volunteerAttr[$volunteerId] = array('data-email' => (string) $volunteerItem->email);
                }
            }
        }

        return array(
            'status_choices' => $statusLabels,
            'type_choices' => $typeLabels,
            'type_choice_attributes' => $typeChoiceAttributes,
            'accent_default_color' => $accentDefault,
            'article_categories' => $categoryMap,
            'articles' => $articleMap,
            'article_choice_attributes' => $articleAttr,
            'locations' => $locationMap,
            'location_choice_attributes' => $locationAttr,
            'animateurs' => $animateurMap,
            'animateur_choice_attributes' => $animateurAttr,
            'volunteers' => $volunteerMap,
            'volunteer_choice_attributes' => $volunteerAttr,
            'schedule_weekdays' => $scheduleWeekdays,
            'schedule_month_ordinals' => $scheduleMonthOrdinals,
        );
    }
}
