<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_event_first_non_empty')) {
    function mj_member_event_first_non_empty($candidates) {
        if (!is_array($candidates)) {
            return '';
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $candidate !== null) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('mj_member_event_build_attr_string')) {
    function mj_member_event_build_attr_string(array $attributes) {
        $buffer = '';

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($value === true) {
                $value = '1';
            } elseif ($value === false) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $buffer .= ' ' . esc_attr($key) . '="' . esc_attr((string) $value) . '"';
        }

        return $buffer;
    }
}

$context = isset($GLOBALS['mj_member_event_context']) && is_array($GLOBALS['mj_member_event_context'])
    ? $GLOBALS['mj_member_event_context']
    : array();

$event_input = isset($context['event']) && is_array($context['event']) ? $context['event'] : array();
$event_defaults = array(
    'id' => 0,
    'title' => '',
    'type' => '',
    'type_label' => '',
    'status' => '',
    'date_label' => '',
    'deadline_label' => '',
    'price_label' => '',
    'age_label' => '',
    'location' => '',
    'location_address' => '',
    'location_description' => '',
    'location_map' => '',
    'location_map_link' => '',
    'description' => '',
    'excerpt' => '',
    'registration_url' => '',
    'article_permalink' => '',
    'cover_url' => '',
    'cover_thumb' => '',
    'article_cover_url' => '',
    'article_cover_thumb' => '',
    'palette' => array(),
    'accent_color' => '',
);
$event = wp_parse_args($event_input, $event_defaults);
$event['palette'] = is_array($event['palette']) ? $event['palette'] : array();

$registration_input = isset($context['registration']) && is_array($context['registration']) ? $context['registration'] : array();
$registration_defaults = array(
    'payload' => array(),
    'is_open' => false,
    'requires_login' => false,
    'needs_script' => false,
    'all_registered' => false,
    'has_participants' => false,
    'cta_label' => '',
    'cta_registered_label' => '',
);
$registration = wp_parse_args($registration_input, $registration_defaults);
$registration_payload = is_array($registration['payload']) ? $registration['payload'] : array();
$registration_payload_json = '';
if (!empty($registration_payload)) {
    $encoded_registration = wp_json_encode($registration_payload);
    $registration_payload_json = is_string($encoded_registration) ? $encoded_registration : '';
}

$status_labels = class_exists('MjEvents_CRUD') ? MjEvents_CRUD::get_status_labels() : array();
$status_key = $event['status'] !== '' ? sanitize_key($event['status']) : '';
$active_status = defined('MjEvents_CRUD::STATUS_ACTIVE') ? MjEvents_CRUD::STATUS_ACTIVE : 'active';
$status_label = ($status_key !== '' && isset($status_labels[$status_key])) ? $status_labels[$status_key] : '';

$title = $event['title'];
$event_type_key = isset($event['type']) ? sanitize_key($event['type']) : '';
$type_label = $event['type_label'];
$is_stage_event = ($event_type_key === 'stage');
if (!$is_stage_event && $type_label !== '') {
    $type_label_lower = function_exists('mb_strtolower') ? mb_strtolower($type_label, 'UTF-8') : strtolower($type_label);
    $is_stage_event = $type_label_lower === 'stage';
}
$date_label = $event['date_label'];
$deadline_label = $event['deadline_label'];
$price_label = $event['price_label'];
$age_label = $event['age_label'];
$location_label = $event['location'];
$location_address = $event['location_address'];
$location_description = $event['location_description'];
$location_map = $event['location_map'];
$location_map_link = $event['location_map_link'];
$location_cover = isset($event['location_cover']) ? $event['location_cover'] : '';
$description = $event['description'];
$excerpt = $event['excerpt'];
$registration_url = $event['registration_url'];
$article_permalink = $event['article_permalink'];
$cover_url = mj_member_event_first_non_empty(array($event['cover_url'], $event['article_cover_url']));
$cover_thumb = mj_member_event_first_non_empty(array($event['cover_thumb'], $event['article_cover_thumb']));

$registration_is_open = !empty($registration['is_open']);
$registration_requires_login = !empty($registration['requires_login']);
$registration_needs_script = !empty($registration['needs_script']);
$registration_all_registered = !empty($registration['all_registered']);
$registration_has_participants = !empty($registration['has_participants']);
$registration_cta_label = $registration['cta_label'] !== '' ? $registration['cta_label'] : __("S'inscrire", 'mj-member');
$registration_cta_registered = $registration['cta_registered_label'] !== '' ? $registration['cta_registered_label'] : __('Déjà inscrit', 'mj-member');
$registration_has_interactive = $registration_is_open && ($registration_payload_json !== '' || $registration_requires_login);

$registration_price_amount = isset($registration['price_amount']) ? (float) $registration['price_amount'] : (isset($event['price']) ? (float) $event['price'] : 0.0);
$registration_price_label = $registration['price_label'] !== '' ? $registration['price_label'] : $price_label;
$registration_payment_required = !empty($registration['payment_required']) || $registration_price_amount > 0;
$registration_deadline_label = $registration['deadline_label'] !== '' ? $registration['deadline_label'] : $deadline_label;
$registration_total_count = isset($registration['total_count']) ? (int) $registration['total_count'] : (is_array($registration['participants']) ? count($registration['participants']) : 0);
$registration_registered_count = isset($registration['registered_count']) ? (int) $registration['registered_count'] : 0;
$registration_available_count = isset($registration['available_count']) ? (int) $registration['available_count'] : 0;
$registration_price_candidate = $registration_price_label !== '' ? $registration_price_label : $price_label;
if ($registration_price_candidate === '' && $registration_price_amount > 0) {
    $registration_price_candidate = sprintf(__('%s €', 'mj-member'), number_format_i18n($registration_price_amount, 2));
}
$registration_price_plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($registration_price_candidate)));
$registration_price_plain_lower = function_exists('mb_strtolower') ? mb_strtolower($registration_price_plain, 'UTF-8') : strtolower($registration_price_plain);
$registration_price_is_zero_numeric = $registration_price_plain !== '' && preg_match('/^0+(?:[\.,]0+)?\s*(?:€|eur|euros)?$/i', $registration_price_plain);
$registration_price_is_free = ($registration_price_amount <= 0) && (
    $registration_price_plain === ''
    || $registration_price_is_zero_numeric
    || strpos($registration_price_plain_lower, 'gratuit') !== false
    || strpos($registration_price_plain_lower, 'offert') !== false
    || strpos($registration_price_plain_lower, 'free') !== false
);
$registration_show_price = $registration_price_plain !== '' && !$registration_price_is_free;

$animateurs_context = isset($context['animateurs']) && is_array($context['animateurs']) ? $context['animateurs'] : array();
$animateur_items = isset($animateurs_context['items']) && is_array($animateurs_context['items']) ? $animateurs_context['items'] : array();
$animateurs_count = count($animateur_items);

$location_context_raw = isset($context['location']) && is_array($context['location']) ? $context['location'] : array();
$location_defaults = array(
    'name' => '',
    'label' => '',
    'address' => '',
    'address_line' => '',
    'postal_code' => '',
    'city' => '',
    'country' => '',
    'notes' => '',
    'description' => '',
    'map' => '',
    'map_link' => '',
    'cover' => '',
    'types' => array(),
    'address_components' => array(),
);
$location_context = wp_parse_args($location_context_raw, $location_defaults);
$location_types = is_array($location_context['types']) ? array_values(array_filter($location_context['types'], static function ($label) {
    return is_string($label) && trim($label) !== '';
})) : array();
$location_display_title = $location_context['label'] !== '' ? $location_context['label'] : $location_label;
$location_display_cover = $location_context['cover'] !== '' ? $location_context['cover'] : ($location_cover !== '' ? $location_cover : $cover_thumb);
$location_display_map = $location_context['map'] !== '' ? $location_context['map'] : $location_map;
$location_display_map_link = $location_context['map_link'] !== '' ? $location_context['map_link'] : $location_map_link;

$location_address_display = $location_context['address'];
if ($location_address_display === '') {
    $address_parts = array();
    if ($location_context['address_line'] !== '') {
        $address_parts[] = $location_context['address_line'];
    }
    $city_line = trim($location_context['postal_code'] . ' ' . $location_context['city']);
    if ($city_line !== '') {
        $address_parts[] = $city_line;
    }
    if ($location_context['country'] !== '') {
        $address_parts[] = $location_context['country'];
    }
    if (!empty($address_parts)) {
        $location_address_display = implode(', ', array_filter($address_parts));
    }
}

$location_description_text = $location_context['description'] !== '' ? $location_context['description'] : $location_description;
$location_notes_text = $location_context['notes'];
$location_description_html = $location_description_text !== '' ? nl2br(esc_html($location_description_text)) : '';
$location_notes_html = '';
if ($location_notes_text !== '' && $location_notes_text !== $location_description_text) {
    $location_notes_html = nl2br(esc_html($location_notes_text));
}
$location_has_card = $event['has_location'] || $location_address_display !== '' || $location_description_html !== '' || $location_notes_html !== '' || $location_display_map !== '' || !empty($location_types) || $location_display_cover !== '';

$occurrence_preview = function_exists('mj_member_prepare_event_occurrences_preview')
    ? mj_member_prepare_event_occurrences_preview(
        $event,
        array(
            'max' => 6,
            'include_past' => false,
            'fetch_limit' => 18,
        )
    )
    : array();

$occurrence_defaults = array(
    'items' => array(),
    'next' => null,
    'remaining' => 0,
    'has_multiple' => false,
);
$occurrence_preview = wp_parse_args(is_array($occurrence_preview) ? $occurrence_preview : array(), $occurrence_defaults);
$occurrence_items = is_array($occurrence_preview['items']) ? $occurrence_preview['items'] : array();
$occurrence_next = is_array($occurrence_preview['next']) ? $occurrence_preview['next'] : null;
$occurrence_next_label = ($occurrence_next && !empty($occurrence_next['label'])) ? $occurrence_next['label'] : '';
$occurrence_remaining = (int) $occurrence_preview['remaining'];
$event_has_multiple_occurrences = !empty($occurrence_preview['has_multiple']);
$occurrence_schedule_summary = '';
$occurrence_stage_period_label = '';
$occurrence_reference_items = array();
if (!empty($occurrence_items)) {
    foreach ($occurrence_items as $occurrence_item) {
        if (is_array($occurrence_item) && empty($occurrence_item['isPast'])) {
            $occurrence_reference_items[] = $occurrence_item;
        }
    }
}
if (empty($occurrence_reference_items) && !empty($occurrence_items)) {
    foreach ($occurrence_items as $occurrence_item) {
        if (is_array($occurrence_item)) {
            $occurrence_reference_items[] = $occurrence_item;
        }
    }
}

$weekday_order_map = array();
$time_range_map = array();
$occurrence_reference_count = 0;
$occurrence_display_time = false;
$occurrence_stage_start_ts = null;
$occurrence_stage_end_ts = null;
$occurrence_stage_time_range = null;

if (!empty($occurrence_reference_items)) {
    foreach ($occurrence_reference_items as $occurrence_item) {
        if (!is_array($occurrence_item)) {
            continue;
        }

        $timestamp = isset($occurrence_item['timestamp']) ? (int) $occurrence_item['timestamp'] : 0;
        if ($timestamp <= 0) {
            $start_value = isset($occurrence_item['start']) ? (string) $occurrence_item['start'] : '';
            if ($start_value !== '') {
                $timestamp = strtotime($start_value);
            }
        }

        if ($timestamp <= 0) {
            continue;
        }

        $occurrence_reference_count++;
        $weekday_key = (int) wp_date('w', $timestamp);
        $weekday_label = wp_date('l', $timestamp);
        if ($weekday_label !== '') {
            $weekday_order_map[$weekday_key] = $weekday_label;
        }

        $start_time_label = wp_date('H\hi', $timestamp);
        $end_value = isset($occurrence_item['end']) ? (string) $occurrence_item['end'] : '';
        $end_timestamp = $end_value !== '' ? strtotime($end_value) : 0;
        $end_time_label = ($end_timestamp > 0) ? wp_date('H\hi', $end_timestamp) : '';

        if ($start_time_label !== '') {
            $range_key = $start_time_label . '|' . $end_time_label;
            $time_range_map[$range_key] = array(
                'start' => $start_time_label,
                'end' => $end_time_label,
            );
        }

        if (empty($occurrence_item['isPast'])) {
            if ($occurrence_stage_start_ts === null || $timestamp < $occurrence_stage_start_ts) {
                $occurrence_stage_start_ts = $timestamp;
            }
            if ($occurrence_stage_end_ts === null || $timestamp > $occurrence_stage_end_ts) {
                $occurrence_stage_end_ts = $timestamp;
            }
        }
    }

    if ($occurrence_reference_count <= 1) {
        $occurrence_display_time = true;
    } else {
        $occurrence_display_time = count($time_range_map) > 1;
    }

    if (!empty($weekday_order_map)) {
        ksort($weekday_order_map, SORT_NUMERIC);
        $weekday_tokens = array();
        $weekday_labels_sorted = array_values($weekday_order_map);
        $weekday_keys_sorted = array_keys($weekday_order_map);
        foreach ($weekday_labels_sorted as $weekday_label) {
            if (function_exists('mb_strtolower')) {
                $weekday_lower = mb_strtolower($weekday_label, 'UTF-8');
            } else {
                $weekday_lower = strtolower($weekday_label);
            }
            $last_char = function_exists('mb_substr') ? mb_substr($weekday_lower, -1, 1, 'UTF-8') : substr($weekday_lower, -1);
            if ($last_char !== 's') {
                $weekday_lower .= 's';
            }
            $weekday_tokens[] = $weekday_lower;
        }

        $weekday_first_label_stage = '';
        $weekday_last_label_stage = '';
        $weekday_first_label_stage_lower = '';
        $weekday_last_label_stage_lower = '';
        if ($is_stage_event && count($weekday_labels_sorted) >= 2) {
            $is_contiguous_range = true;
            for ($i = 1, $limit = count($weekday_keys_sorted); $i < $limit; $i++) {
                if ($weekday_keys_sorted[$i] !== $weekday_keys_sorted[$i - 1] + 1) {
                    $is_contiguous_range = false;
                    break;
                }
            }
            if ($is_contiguous_range) {
                $weekday_first_label_stage = $weekday_labels_sorted[0];
                $weekday_last_label_stage = $weekday_labels_sorted[count($weekday_labels_sorted) - 1];
                if (function_exists('mb_strtolower')) {
                    $weekday_first_label_stage_lower = mb_strtolower($weekday_first_label_stage, 'UTF-8');
                    $weekday_last_label_stage_lower = mb_strtolower($weekday_last_label_stage, 'UTF-8');
                } else {
                    $weekday_first_label_stage_lower = strtolower($weekday_first_label_stage);
                    $weekday_last_label_stage_lower = strtolower($weekday_last_label_stage);
                }
            }
        }

        $weekday_single_label_lower = '';
        if (!empty($weekday_labels_sorted)) {
            $weekday_single_label = $weekday_labels_sorted[0];
            if (function_exists('mb_strtolower')) {
                $weekday_single_label_lower = mb_strtolower($weekday_single_label, 'UTF-8');
            } else {
                $weekday_single_label_lower = strtolower($weekday_single_label);
            }
        }

        if (!empty($weekday_tokens)) {
            $weekday_phrase = '';
            if (count($weekday_tokens) === 1) {
                $weekday_phrase = $weekday_tokens[0];
            } else {
                $last_weekday = array_pop($weekday_tokens);
                $weekday_phrase = implode(', ', $weekday_tokens) . ' et ' . $last_weekday;
            }

            $time_range_values = null;
            $time_phrase = '';
            if (count($time_range_map) === 1) {
                $time_range_values = reset($time_range_map);
                $start_label = isset($time_range_values['start']) ? trim($time_range_values['start']) : '';
                $end_label = isset($time_range_values['end']) ? trim($time_range_values['end']) : '';

                if ($start_label !== '') {
                    if ($end_label !== '' && $end_label !== $start_label) {
                        $time_phrase = sprintf(__('de %1$s à %2$s', 'mj-member'), $start_label, $end_label);
                    } else {
                        $time_phrase = sprintf(__('à %s', 'mj-member'), $start_label);
                    }
                }
            }

            $single_reference = ($occurrence_reference_count === 1 || !$event_has_multiple_occurrences) && count($weekday_labels_sorted) === 1 && !$is_stage_event;

            if ($weekday_first_label_stage_lower !== '' && $weekday_last_label_stage_lower !== '') {
                if ($time_phrase !== '') {
                    $occurrence_schedule_summary = sprintf(__('Du %1$s au %2$s %3$s', 'mj-member'), $weekday_first_label_stage_lower, $weekday_last_label_stage_lower, $time_phrase);
                } else {
                    $occurrence_schedule_summary = sprintf(__('Du %1$s au %2$s', 'mj-member'), $weekday_first_label_stage_lower, $weekday_last_label_stage_lower);
                }
            } elseif ($single_reference && $weekday_single_label_lower !== '') {
                if ($time_phrase !== '') {
                    $occurrence_schedule_summary = sprintf(__('Ce %1$s %2$s', 'mj-member'), $weekday_single_label_lower, $time_phrase);
                } else {
                    $occurrence_schedule_summary = sprintf(__('Ce %s', 'mj-member'), $weekday_single_label_lower);
                }
            } elseif ($time_phrase !== '') {
                $occurrence_schedule_summary = sprintf(__('Tous les %1$s %2$s', 'mj-member'), $weekday_phrase, $time_phrase);
            } else {
                $occurrence_schedule_summary = sprintf(__('Tous les %s', 'mj-member'), $weekday_phrase);
            }
        }
            if ($is_stage_event && $occurrence_stage_start_ts !== null && $occurrence_stage_end_ts !== null) {
                $stage_start_label = wp_date('l j F', $occurrence_stage_start_ts);
                $stage_end_label = wp_date('l j F', $occurrence_stage_end_ts);
                if (function_exists('mb_strtolower')) {
                    $stage_start_label = mb_strtolower($stage_start_label, 'UTF-8');
                    $stage_end_label = mb_strtolower($stage_end_label, 'UTF-8');
                } else {
                    $stage_start_label = strtolower($stage_start_label);
                    $stage_end_label = strtolower($stage_end_label);
                }

                $stage_time_phrase = '';
                if (is_array($time_range_values)) {
                    $stage_time_start = isset($time_range_values['start']) ? trim($time_range_values['start']) : '';
                    $stage_time_end = isset($time_range_values['end']) ? trim($time_range_values['end']) : '';

                    if ($stage_time_start !== '') {
                        if (preg_match('/^(\d{1,2})h(\d{2})$/', $stage_time_start, $matches_start)) {
                            $hour_value = (int) $matches_start[1];
                            $minute_value = $matches_start[2];
                            $stage_time_start = $hour_value . 'h' . ($minute_value === '00' ? '' : $minute_value);
                        }
                    }

                    if ($stage_time_end !== '') {
                        if (preg_match('/^(\d{1,2})h(\d{2})$/', $stage_time_end, $matches_end)) {
                            $hour_value_end = (int) $matches_end[1];
                            $minute_value_end = $matches_end[2];
                            $stage_time_end = $hour_value_end . 'h' . ($minute_value_end === '00' ? '' : $minute_value_end);
                        }
                    }

                    if ($stage_time_start !== '') {
                        if ($stage_time_end !== '' && $stage_time_end !== $stage_time_start) {
                            $stage_time_phrase = sprintf(__('de %1$s à %2$s', 'mj-member'), $stage_time_start, $stage_time_end);
                        } else {
                            $stage_time_phrase = sprintf(__('à %s', 'mj-member'), $stage_time_start);
                        }
                    }
                }

                if ($occurrence_stage_end_ts > $occurrence_stage_start_ts) {
                    if ($stage_time_phrase !== '') {
                        $occurrence_stage_period_label = sprintf(__('Commence le %1$s au %2$s %3$s', 'mj-member'), $stage_start_label, $stage_end_label, $stage_time_phrase);
                    } else {
                        $occurrence_stage_period_label = sprintf(__('Commence le %1$s au %2$s', 'mj-member'), $stage_start_label, $stage_end_label);
                    }
                } else {
                    if ($stage_time_phrase !== '') {
                        $occurrence_stage_period_label = sprintf(__('Commence le %1$s %2$s', 'mj-member'), $stage_start_label, $stage_time_phrase);
                    } else {
                        $occurrence_stage_period_label = sprintf(__('Commence le %s', 'mj-member'), $stage_start_label);
                    }
                }
            }
    }
}

$display_date_label = $date_label;
if ($is_stage_event && $occurrence_stage_period_label !== '') {
    $display_date_label = $occurrence_stage_period_label;
} elseif ($event_has_multiple_occurrences && $occurrence_next_label !== '') {
    $display_date_label = !empty($occurrence_next['isToday'])
        ? sprintf(__("Aujourd'hui : %s", 'mj-member'), $occurrence_next_label)
        : sprintf(__('Prochaine occurrence : %s', 'mj-member'), $occurrence_next_label);
}

$document_title_filter = null;
$document_title_parts_filter = null;
if ($title !== '') {
    $document_title_value = $title;
    if ($display_date_label !== '') {
        $document_title_value .= ' – ' . $display_date_label;
    }

    $document_title_parts_filter = static function ($parts) use ($document_title_value) {
        if (is_array($parts)) {
            $parts['title'] = $document_title_value;
        }
        return $parts;
    };

    $document_title_filter = static function () use ($document_title_value) {
        return $document_title_value;
    };

    add_filter('document_title_parts', $document_title_parts_filter, 20, 1);
    add_filter('pre_get_document_title', $document_title_filter, 20, 0);
}

$palette_defaults = array(
    'base' => '',
    'contrast' => '',
    'surface' => '',
    'border' => '',
    'highlight' => '',
);
$palette = wp_parse_args($event['palette'], $palette_defaults);
$accent = $palette['base'] !== '' ? $palette['base'] : ($event['accent_color'] !== '' ? $event['accent_color'] : '#2563EB');
$contrast = $palette['contrast'] !== '' ? $palette['contrast'] : '#FFFFFF';
$surface = $palette['surface'] !== '' ? $palette['surface'] : '#F8FAFC';
$border = $palette['border'] !== '' ? $palette['border'] : '#E2E8F0';
$highlight = $palette['highlight'] !== '' ? $palette['highlight'] : '#E5ECFD';

$description_html = $description !== '' ? wpautop($description) : '';
$excerpt_html = $excerpt !== '' ? wpautop($excerpt) : '';
if ($description_html !== '' && $excerpt_html !== '') {
    $description_plain_normalized = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($description_html)));
    $excerpt_plain_normalized = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($excerpt_html)));
    if ($description_plain_normalized !== '' && $description_plain_normalized === $excerpt_plain_normalized) {
        $excerpt_html = '';
    }
}

if ($registration_needs_script) {
    wp_enqueue_script('mj-member-events-widget');
    if (function_exists('mj_member_ensure_events_widget_localized')) {
        mj_member_ensure_events_widget_localized();
    }
}

$registration_button_attributes = array(
    'data-event-id' => $event['id'] ? (int) $event['id'] : null,
    'data-cta-label' => $registration_cta_label,
    'data-cta-registered-label' => $registration_cta_registered,
);
if ($occurrence_schedule_summary !== '') {
    $registration_button_attributes['data-occurrence-summary'] = $occurrence_schedule_summary;
}
if ($registration_payload_json !== '') {
    $registration_button_attributes['data-registration'] = $registration_payload_json;
} elseif ($registration_requires_login) {
    $registration_button_attributes['data-requires-login'] = '1';
}
$registration_button_attr_string = mj_member_event_build_attr_string($registration_button_attributes);

get_header();

if ($registration_has_interactive && function_exists('mj_member_output_events_widget_styles')) {
    mj_member_output_events_widget_styles();
}
?>

<style>
.mj-member-event-single{--mj-event-accent: <?php echo esc_attr($accent); ?>;--mj-event-contrast: <?php echo esc_attr($contrast); ?>;--mj-event-surface: <?php echo esc_attr($surface); ?>;--mj-event-border: <?php echo esc_attr($border); ?>;--mj-event-highlight: <?php echo esc_attr($highlight); ?>;display:flex;flex-direction:column;gap:40px;padding:40px 0;background:#f8fafc;}
.mj-member-event-single__wrapper{width:min(1100px,90vw);margin:0 auto;display:flex;flex-direction:column;gap:36px;}
.mj-member-event-single__hero{background:var(--mj-event-accent);color:var(--mj-event-contrast);border-radius:18px;padding:36px 44px;position:relative;overflow:hidden;display:flex;flex-direction:column;gap:20px;}
.mj-member-event-single__hero::after{content:'';position:absolute;inset:auto -80px -140px auto;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,0.12);}
.mj-member-event-single__badges{display:flex;flex-wrap:wrap;gap:10px;z-index:1;}
.mj-member-event-single__badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;background:rgba(255,255,255,0.18);font-weight:600;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;}
.mj-member-event-single__badge--schedule{background:rgba(15,23,42,0.15);color:var(--mj-event-contrast);}
.mj-member-event-single__status{background:rgba(15,23,42,0.15);}
.mj-member-event-single__title{margin:0;font-size:2.4rem;font-weight:700;line-height:1.15;z-index:1;}
.mj-member-event-single__date{margin:0;font-size:1.05rem;font-weight:500;opacity:0.92;z-index:1;}
.mj-member-event-single__hero-cover{position:absolute;right:32px;bottom:-24px;width:260px;height:260px;border-radius:22px;overflow:hidden;box-shadow:0 18px 48px rgba(15,23,42,0.3);display:none;}
.mj-member-event-single__hero-cover img{width:100%;height:100%;object-fit:cover;display:block;}
@media(min-width:900px){.mj-member-event-single__hero{padding-right:320px;}.mj-member-event-single__hero-cover{display:block;}}

.mj-member-event-single__body{display:flex;flex-direction:column;gap:24px;}
@media(min-width:960px){.mj-member-event-single__body{display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:flex-start;}}
.mj-member-event-single__main{display:flex;flex-direction:column;gap:24px;}
.mj-member-event-single__sidebar{display:flex;flex-direction:column;gap:24px;}

.mj-member-event-single__card{background:#ffffff;border-radius:18px;padding:28px 32px;border:1px solid #e2e8f0;box-shadow:0 14px 36px rgba(15,23,42,0.08);}
.mj-member-event-single__card h2{margin-top:0;font-size:1.4rem;font-weight:700;color:#0f172a;}

.mj-member-event-single__description{display:flex;flex-direction:column;gap:18px;font-size:1rem;line-height:1.65;color:#1f2937;}
.mj-member-event-single__excerpt{padding:14px 18px;border-left:4px solid var(--mj-event-accent);background:var(--mj-event-highlight);border-radius:12px;color:#1f2937;font-size:0.98rem;}

.mj-member-event-single__registration{display:flex;flex-direction:column;gap:18px;}
.mj-member-event-single__registration-header{display:flex;align-items:center;justify-content:space-between;gap:12px;}
.mj-member-event-single__registration-title{margin:0;font-size:1.4rem;font-weight:700;color:#0f172a;}
.mj-member-event-single__registration-status{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-weight:600;font-size:0.86rem;}
.mj-member-event-single__registration-status.is-open{background:rgba(16,185,129,0.12);color:#047857;}
.mj-member-event-single__registration-status.is-closed{background:rgba(239,68,68,0.15);color:#b91c1c;}

.mj-member-event-single__registration-actions{display:flex;flex-direction:column;gap:14px;}
.mj-member-event-single__registration-actions .mj-member-events__cta{padding:12px 26px;border-radius:12px;background:var(--mj-event-contrast);color:var(--mj-event-accent);border-color:var(--mj-event-contrast);font-weight:700;transition:background 0.2s ease,color 0.2s ease,box-shadow 0.2s ease;}
.mj-member-event-single__registration-actions .mj-member-events__cta:hover{background:rgba(255,255,255,0.92);color:var(--mj-event-accent);box-shadow:0 12px 32px rgba(15,23,42,0.18);}
.mj-member-event-single__registration-actions .mj-member-events__cta.is-registered{background:rgba(15,23,42,0.85);border-color:rgba(15,23,42,0.85);color:#ffffff;}
.mj-member-event-single__registration-actions .mj-member-events__signup{background:rgba(15,23,42,0.04);border-color:rgba(15,23,42,0.12);color:#0f172a;box-shadow:none;}
.mj-member-event-single__registration-actions .mj-member-events__signup-title{color:#0f172a;}
.mj-member-event-single__registration-actions .mj-member-events__signup-toggle{border-color:rgba(15,23,42,0.15);color:#b91c1c;}
.mj-member-event-single__registration-actions .mj-member-events__signup-feedback{color:#0f172a;}
.mj-member-event-single__registration-actions .mj-member-events__signup-feedback.is-error{color:#b91c1c;}
.mj-member-event-single__registration-price{margin:0;font-size:0.9rem;color:#0f172a;font-weight:600;display:flex;align-items:center;gap:8px;}
.mj-member-event-single__registration-price-label{font-size:0.75rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.05em;}
.mj-member-event-single__registration-price-value{font-size:1.05rem;font-weight:700;color:#0f172a;}
.mj-member-event-single__registration-occurrences{display:flex;flex-direction:column;gap:10px;padding:16px;border-radius:14px;background:var(--mj-event-surface);border:1px solid var(--mj-event-border);}
.mj-member-event-single__registration-occurrences-title{margin:0;font-size:0.82rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.05em;}
.mj-member-event-single__registration-occurrences-summary{margin:0;font-size:0.95rem;color:#1f2937;}
.mj-member-event-single__registration-occurrences .mj-member-event-single__occurrences{margin:4px 0 0;}
.mj-member-events__signup-occurrences{display:flex;flex-direction:column;gap:14px;margin-bottom:16px;padding:18px;border:1px solid var(--mj-event-border);border-radius:16px;background:var(--mj-event-surface);}
.mj-member-events__signup-occurrences legend{margin:0;font-size:0.95rem;font-weight:700;color:#0f172a;}
.mj-member-events__signup-occurrence-summary{margin:4px 0 4px;font-size:0.92rem;color:#475569;}
.mj-member-events__signup-occurrence-columns{display:flex;flex-direction:column;gap:14px;}
@media(min-width:720px){.mj-member-events__signup-occurrence-columns{flex-direction:row;align-items:stretch;}}
.mj-member-events__signup-occurrence-section{flex:1;display:flex;flex-direction:column;gap:10px;padding:12px 14px;border-radius:14px;background:rgba(255,255,255,0.65);border:1px solid rgba(15,23,42,0.08);}
.mj-member-events__signup-occurrence-section.is-empty{background:rgba(241,245,249,0.55);}
.mj-member-events__signup-occurrence-heading{margin:0;font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#475569;}
.mj-member-events__signup-occurrence-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;}
.mj-member-events__signup-occurrence-item{margin:0;display:flex;flex-direction:column;gap:6px;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,0.08);background:#ffffff;box-shadow:0 6px 14px rgba(15,23,42,0.04);transition:transform 0.15s ease,box-shadow 0.15s ease;}
.mj-member-events__signup-occurrence-item.is-assigned{border-color:rgba(37,99,235,0.25);background:rgba(59,130,246,0.1);box-shadow:0 8px 20px rgba(37,99,235,0.16);}
.mj-member-events__signup-occurrence-item.is-assigned .mj-member-events__signup-occurrence-label{color:#0f172a;font-weight:600;}
.mj-member-events__signup-occurrence-item.is-assigned .mj-member-events__signup-occurrence-toggle{border-color:rgba(37,99,235,0.35);color:#1d4ed8;}
.mj-member-events__signup-occurrence-item.is-assigned .mj-member-events__signup-occurrence-toggle:hover{background:rgba(37,99,235,0.12);color:#1d4ed8;}
.mj-member-events__signup-occurrence-item.is-available:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(15,23,42,0.08);}
.mj-member-events__signup-occurrence-label{display:flex;align-items:center;gap:10px;font-size:0.9rem;color:#1f2937;}
.mj-member-events__signup-occurrence-label.is-disabled{color:#64748b;}
.mj-member-events__signup-occurrence-label input[type="checkbox"]{width:18px;height:18px;border-radius:6px;border:1px solid var(--mj-event-border);box-shadow:0 0 0 1px rgba(15,23,42,0.08) inset;}
.mj-member-events__signup-occurrence-label input[type="checkbox"][hidden]{display:none;}
.mj-member-events__signup-occurrence-label input[type="checkbox"]:disabled{opacity:0.45;}
.mj-member-events__signup-occurrence-badge{padding:2px 8px;border-radius:999px;background:rgba(15,23,42,0.08);color:#475569;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;}
.mj-member-events__signup-occurrence-empty{display:none;margin:0;font-size:0.85rem;color:#64748b;}
.mj-member-events__signup-occurrence-section.is-empty .mj-member-events__signup-occurrence-empty{display:block;}
.mj-member-events__signup-occurrence-actions{display:flex;align-items:center;gap:10px;margin:0;}
.mj-member-events__signup-occurrence-actions[hidden]{display:none;}
.mj-member-events__signup-occurrence-toggle{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:10px;border:1px solid var(--mj-event-border);background:rgba(255,255,255,0.9);color:#0f172a;font-size:0.83rem;font-weight:600;cursor:pointer;transition:background 0.2s ease,color 0.2s ease,border-color 0.2s ease;}
.mj-member-events__signup-occurrence-toggle:hover{background:var(--mj-event-accent);color:var(--mj-event-contrast);}
.mj-member-events__signup-occurrence-toggle[hidden]{display:none;}
.mj-member-events__signup-occurrence-help{margin:2px 0 0;font-size:0.82rem;color:#475569;}

.mj-member-event-single__article-link{display:flex;flex-direction:column;gap:12px;}
.mj-member-event-single__article-link a{align-self:flex-start;display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:12px;border:1px solid var(--mj-event-border);color:var(--mj-event-accent);font-weight:600;text-decoration:none;transition:border-color 0.2s ease,background 0.2s ease;}
.mj-member-event-single__article-link a:hover{border-color:var(--mj-event-accent);background:var(--mj-event-highlight);}

.mj-member-event-single__registration-links{display:flex;flex-wrap:wrap;gap:12px;}
.mj-member-event-single__registration-link{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:12px;border:1px solid var(--mj-event-border);color:var(--mj-event-accent);font-weight:600;text-decoration:none;transition:border-color 0.2s ease,background 0.2s ease;}
.mj-member-event-single__registration-link:hover{border-color:var(--mj-event-accent);background:var(--mj-event-highlight);}

.mj-member-event-single__registration-note{margin:0;font-size:0.9rem;color:#475569;}
.mj-member-event-single__registration-meta{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;font-size:0.95rem;color:#1f2937;}
.mj-member-event-single__registration-meta li{display:flex;flex-direction:column;gap:4px;}
.mj-member-event-single__registration-meta strong{font-weight:600;color:#0f172a;margin:0;}

.mj-member-event-single__details{display:flex;flex-direction:column;gap:16px;}
.mj-member-event-single__detail{display:flex;flex-direction:column;gap:4px;padding:14px 16px;border-radius:14px;background:var(--mj-event-surface);border:1px solid var(--mj-event-border);}
.mj-member-event-single__detail-label{margin:0;font-size:0.8rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:0.06em;}
.mj-member-event-single__detail-value{margin:0;font-size:1.02rem;font-weight:600;color:#0f172a;}
.mj-member-event-single__occurrences{margin:8px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;}
.mj-member-event-single__occurrence{display:flex;align-items:flex-start;gap:8px;font-size:0.95rem;color:#475569;}
.mj-member-event-single__occurrence-prefix{font-weight:600;color:#0f172a;}
.mj-member-event-single__occurrence.is-today .mj-member-event-single__occurrence-label{color:#0f172a;}
.mj-member-event-single__occurrence--more{font-style:italic;color:#475569;}
.mj-member-event-single__occurrence-time{margin-left:6px;font-size:0.9rem;color:#475569;white-space:nowrap;}

.mj-member-event-single__animateurs{display:flex;flex-direction:column;gap:18px;}
.mj-member-event-single__animateurs h2{margin:0;font-size:1.4rem;font-weight:700;color:#0f172a;}
.mj-member-event-single__animateurs-list{display:grid;gap:16px;grid-template-columns:1fr;}
@media(min-width:640px){.mj-member-event-single__animateurs-list{grid-template-columns:repeat(auto-fit,minmax(220px,1fr));}}
.mj-member-event-single__animateur{display:flex;gap:18px;align-items:flex-start;padding:18px;border:1px solid #e2e8f0;border-radius:16px;background:var(--mj-event-surface);}
.mj-member-event-single__animateur.is-primary{border-color:rgba(37,99,235,0.35);background:var(--mj-event-surface);box-shadow:0 0 0 1px rgba(37,99,235,0.08) inset;}
.mj-member-event-single__animateur-avatar{flex:0 0 80px;width:80px;height:80px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--mj-event-accent);color:var(--mj-event-contrast);font-weight:700;font-size:1.3rem;text-transform:uppercase;}
.mj-member-event-single__animateur-avatar img{width:100%;height:100%;object-fit:cover;}
.mj-member-event-single__animateur-content{flex:1;display:flex;flex-direction:column;gap:6px;}
.mj-member-event-single__animateur-name{margin:0;font-size:1.1rem;font-weight:700;color:#0f172a;}
.mj-member-event-single__animateur-role{margin:0;font-size:0.9rem;color:#475569;display:flex;align-items:center;gap:8px;}
.mj-member-event-single__animateur-role-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;background:rgba(15,23,42,0.08);color:#475569;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;}
.mj-member-event-single__animateur-bio{margin:0;font-size:0.92rem;color:#1f2937;line-height:1.5;}
.mj-member-event-single__animateur-contact{margin:6px 0 0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:14px;font-size:0.9rem;}
.mj-member-event-single__animateur-contact a{display:inline-flex;align-items:center;gap:6px;color:var(--mj-event-accent);font-weight:600;text-decoration:none;}
.mj-member-event-single__animateur-contact a:hover{text-decoration:underline;}
.mj-member-event-single__animateur-contact-icon{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:rgba(37,99,235,0.12);color:var(--mj-event-accent);font-size:0.8rem;line-height:1;font-weight:700;}

.mj-member-event-single__location{display:flex;flex-direction:column;gap:16px;}
.mj-member-event-single__location-header{display:flex;align-items:center;gap:16px;}
.mj-member-event-single__location-cover{flex:0 0 64px;width:64px;height:64px;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;background:#f1f5f9;display:flex;align-items:center;justify-content:center;}
.mj-member-event-single__location-cover img{width:100%;height:100%;object-fit:cover;}
.mj-member-event-single__location-title{margin:0;font-size:1.1rem;font-weight:700;color:#0f172a;}
.mj-member-event-single__location-address{margin:0;font-size:0.95rem;color:#1f2937;}
.mj-member-event-single__location-types{display:flex;flex-wrap:wrap;gap:8px;}
.mj-member-event-single__location-type{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:var(--mj-event-highlight);color:#1d4ed8;font-size:0.8rem;font-weight:600;letter-spacing:0.02em;}
.mj-member-event-single__location-notes{margin:0;font-size:0.93rem;color:#475569;line-height:1.6;}
.mj-member-event-single__location-map{margin-top:8px;border-radius:18px;overflow:hidden;border:1px solid #dbeafe;background:#ffffff;box-shadow:0 10px 30px rgba(15,23,42,0.08);}
.mj-member-event-single__location-map iframe{width:100%;height:300px;border:0;display:block;}
.mj-member-event-single__location-map-actions{display:flex;justify-content:flex-end;padding:10px 12px;background:#f8fafc;border-top:1px solid #e2e8f0;}
.mj-member-event-single__location-map-link{font-size:0.9rem;font-weight:600;color:var(--mj-event-accent);text-decoration:none;}
.mj-member-event-single__location-map-link:hover{text-decoration:underline;}
</style>

<main class="mj-member-event-single">
    <div class="mj-member-event-single__wrapper">
        <section class="mj-member-event-single__hero">
            <div class="mj-member-event-single__badges">
                <?php if ($type_label !== '') : ?>
                <span class="mj-member-event-single__badge"><?php echo esc_html($type_label); ?></span>
                <?php endif; ?>
                <?php if ($occurrence_schedule_summary !== '') : ?>
                <span class="mj-member-event-single__badge mj-member-event-single__badge--schedule"><?php echo esc_html($occurrence_schedule_summary); ?></span>
                <?php endif; ?>
                <?php if ($status_label !== '' && $status_key !== $active_status) : ?>
                <span class="mj-member-event-single__badge mj-member-event-single__status"><?php echo esc_html($status_label); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="mj-member-event-single__title"><?php echo esc_html($title); ?></h1>
            <?php if ($display_date_label !== '') : ?>
            <p class="mj-member-event-single__date"><?php echo esc_html($display_date_label); ?></p>
            <?php endif; ?>
            <?php if ($cover_thumb !== '') : ?>
            <div class="mj-member-event-single__hero-cover">
                <img src="<?php echo esc_url($cover_thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
            </div>
            <?php endif; ?>
        </section>

        <section class="mj-member-event-single__body">
            <div class="mj-member-event-single__main">
                <article class="mj-member-event-single__card mj-member-event-single__description">
                    <?php if ($excerpt_html !== '') : ?>
                    <div class="mj-member-event-single__excerpt"><?php echo wp_kses_post($excerpt_html); ?></div>
                    <?php endif; ?>
                    <?php if ($description_html !== '') : ?>
                    <?php echo wp_kses_post($description_html); ?>
                    <?php else : ?>
                    <p><?php echo esc_html__('Les informations detaillees seront bientot disponibles.', 'mj-member'); ?></p>
                    <?php endif; ?>
                </article>

                <?php if ($animateurs_count > 0) : ?>
                <section class="mj-member-event-single__card mj-member-event-single__animateurs">
                    <h2><?php echo esc_html__('Animateurs responsables', 'mj-member'); ?></h2>
                    <div class="mj-member-event-single__animateurs-list">
                        <?php foreach ($animateur_items as $animateur_item) :
                            if (!is_array($animateur_item)) {
                                continue;
                            }
                            $animateur_classes = array('mj-member-event-single__animateur');
                            if (!empty($animateur_item['is_primary'])) {
                                $animateur_classes[] = 'is-primary';
                            }
                            $animateur_initials = !empty($animateur_item['initials']) ? $animateur_item['initials'] : '#';
                            $animateur_name = isset($animateur_item['full_name']) ? $animateur_item['full_name'] : '';
                            $animateur_role_label = isset($animateur_item['role_label']) ? $animateur_item['role_label'] : '';
                            $animateur_email = isset($animateur_item['email']) ? $animateur_item['email'] : '';
                            $animateur_phone = isset($animateur_item['phone']) ? $animateur_item['phone'] : '';
                            $animateur_bio = isset($animateur_item['bio']) ? $animateur_item['bio'] : '';
                            $animateur_avatar_url = isset($animateur_item['avatar_url']) ? $animateur_item['avatar_url'] : '';
                            $animateur_avatar_alt = isset($animateur_item['avatar_alt']) ? $animateur_item['avatar_alt'] : ($animateur_name !== '' ? sprintf(__('Portrait de %s', 'mj-member'), $animateur_name) : __('Portrait animateur', 'mj-member'));
                        ?>
                        <article class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $animateur_classes))); ?>">
                            <span class="mj-member-event-single__animateur-avatar">
                                <?php if ($animateur_avatar_url !== '') : ?>
                                <img src="<?php echo esc_url($animateur_avatar_url); ?>" alt="<?php echo esc_attr($animateur_avatar_alt); ?>" loading="lazy" />
                                <?php else : ?>
                                <?php echo esc_html($animateur_initials); ?>
                                <?php endif; ?>
                            </span>
                            <div class="mj-member-event-single__animateur-content">
                                <?php if ($animateur_name !== '') : ?>
                                <p class="mj-member-event-single__animateur-name"><?php echo esc_html($animateur_name); ?></p>
                                <?php endif; ?>
                                <?php if ($animateur_bio !== '') : ?>
                                <p class="mj-member-event-single__animateur-bio"><?php echo esc_html($animateur_bio); ?></p>
                                <?php endif; ?>
                                <?php if ($animateur_email !== '' || $animateur_phone !== '') : ?>
                                <ul class="mj-member-event-single__animateur-contact">
                                    <?php if ($animateur_email !== '') : ?>
                                    <li>
                                        <a href="mailto:<?php echo esc_attr($animateur_email); ?>">
                                            <span class="mj-member-event-single__animateur-contact-icon">@</span>
                                            <span class="screen-reader-text"><?php echo esc_html__('Envoyer un email', 'mj-member'); ?></span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($animateur_phone !== '') : ?>
                                    <li>
                                        <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $animateur_phone)); ?>">
                                            <span class="mj-member-event-single__animateur-contact-icon">☎</span>
                                            <span class="screen-reader-text"><?php echo esc_html__('Appeler', 'mj-member'); ?></span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                <?php if ($location_has_card) : ?>
                <section class="mj-member-event-single__card mj-member-event-single__location">
                    <h2><?php echo esc_html__('Lieu', 'mj-member'); ?></h2>
                    <?php if ($location_display_cover !== '') : ?>
                    <div class="mj-member-event-single__location-header">
                        <span class="mj-member-event-single__location-cover">
                            <img src="<?php echo esc_url($location_display_cover); ?>" alt="<?php echo esc_attr($location_display_title !== '' ? $location_display_title : __('Lieu de l\'evenement', 'mj-member')); ?>" loading="lazy" />
                        </span>
                        <div>
                            <?php if ($location_display_title !== '') : ?>
                            <p class="mj-member-event-single__location-title"><?php echo esc_html($location_display_title); ?></p>
                            <?php endif; ?>
                            <?php if ($location_address_display !== '') : ?>
                            <p class="mj-member-event-single__location-address"><?php echo esc_html($location_address_display); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif ($location_display_title !== '' || $location_address_display !== '') : ?>
                    <div class="mj-member-event-single__location-header" style="gap:8px;">
                        <div>
                            <?php if ($location_display_title !== '') : ?>
                            <p class="mj-member-event-single__location-title"><?php echo esc_html($location_display_title); ?></p>
                            <?php endif; ?>
                            <?php if ($location_address_display !== '') : ?>
                            <p class="mj-member-event-single__location-address"><?php echo esc_html($location_address_display); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($location_types)) : ?>
                    <div class="mj-member-event-single__location-types">
                        <?php foreach ($location_types as $location_type_label) : ?>
                        <span class="mj-member-event-single__location-type"><?php echo esc_html($location_type_label); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($location_description_html !== '') : ?>
                    <p class="mj-member-event-single__location-notes"><?php echo $location_description_html; ?></p>
                    <?php endif; ?>
                    <?php if ($location_notes_html !== '') : ?>
                    <p class="mj-member-event-single__location-notes"><?php echo $location_notes_html; ?></p>
                    <?php endif; ?>

                    <?php if ($location_display_map !== '') : ?>
                    <div class="mj-member-event-single__location-map">
                        <iframe src="<?php echo esc_url($location_display_map); ?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <?php if ($location_display_map_link !== '') : ?>
                        <div class="mj-member-event-single__location-map-actions">
                            <a class="mj-member-event-single__location-map-link" href="<?php echo esc_url($location_display_map_link); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Ouvrir dans Google Maps', 'mj-member'); ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
                <?php if ($article_permalink !== '') : ?>
                <section class="mj-member-event-single__card mj-member-event-single__article-link">
                    <a href="<?php echo esc_url($article_permalink); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html__("Voir l'article associe", 'mj-member'); ?>
                    </a>
                </section>
                <?php endif; ?>
            </div>

            <aside class="mj-member-event-single__sidebar">
                <section class="mj-member-event-single__card mj-member-event-single__registration">
                    <div class="mj-member-event-single__registration-header">
                        <h2 class="mj-member-event-single__registration-title"><?php echo esc_html__('Inscriptions', 'mj-member'); ?></h2>
                        <span class="mj-member-event-single__registration-status <?php echo $registration_is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $registration_is_open ? esc_html__('Ouvertes', 'mj-member') : esc_html__('Clôturées', 'mj-member'); ?></span>
                    </div>
                    <?php if ($registration_show_price) : ?>
                    <p class="mj-member-event-single__registration-price">
                        <span class="mj-member-event-single__registration-price-label"><?php echo esc_html__('Tarif', 'mj-member'); ?></span>
                        <span class="mj-member-event-single__registration-price-value"><?php echo esc_html($registration_price_candidate); ?></span>
                    </p>
                    <?php endif; ?>
                    <div class="mj-member-event-single__registration-actions">
                        <?php if ($registration_has_interactive) : ?>
                        <div class="mj-member-event-single__registration-interactive mj-member-events__item">
                            <button type="button" class="mj-member-events__cta is-skin-solid"<?php echo $registration_button_attr_string; ?>><?php echo esc_html($registration_cta_label); ?></button>
                            <?php if ($registration_all_registered && $registration_has_participants) : ?>
                            <p class="mj-member-event-single__registration-note"><?php echo esc_html__('Tous vos profils disponibles sont deja inscrits pour cet evenement.', 'mj-member'); ?></p>
                            <?php elseif ($registration_requires_login) : ?>
                            <p class="mj-member-event-single__registration-note"><?php echo esc_html__('Connecte-toi pour continuer.', 'mj-member'); ?></p>
                            <?php endif; ?>
                            <div class="mj-member-events__signup" hidden></div>
                            <div class="mj-member-events__feedback" aria-live="polite"></div>
                            <?php if ($registration_url !== '') : ?>
                            <noscript>
                                <div class="mj-member-event-single__registration-links">
                                    <a class="mj-member-event-single__registration-link" href="<?php echo esc_url($registration_url); ?>"><?php echo esc_html($registration_cta_label); ?></a>
                                </div>
                            </noscript>
                            <?php endif; ?>
                        </div>
                        <?php else : ?>
                        <div class="mj-member-event-single__registration-static">
                            <?php if ($registration_is_open && $registration_url !== '') : ?>
                            <div class="mj-member-event-single__registration-links">
                                <a class="mj-member-event-single__registration-link" href="<?php echo esc_url($registration_url); ?>"><?php echo esc_html($registration_cta_label); ?></a>
                            </div>
                            <?php elseif (!$registration_is_open) : ?>
                            <p class="mj-member-event-single__registration-note"><?php echo esc_html__('Les inscriptions sont cloturees pour cet evenement.', 'mj-member'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($event_has_multiple_occurrences && !empty($occurrence_items)) : ?>
                    <div class="mj-member-event-single__registration-occurrences">
                        <p class="mj-member-event-single__registration-occurrences-title"><?php echo esc_html__('Occurrences à venir', 'mj-member'); ?></p>
                        <?php if ($occurrence_schedule_summary !== '') : ?>
                        <p class="mj-member-event-single__registration-occurrences-summary"><?php echo esc_html($occurrence_schedule_summary); ?></p>
                        <?php endif; ?>
                        <ul class="mj-member-event-single__occurrences">
                            <?php foreach ($occurrence_items as $index => $occurrence_item) :
                                if (!is_array($occurrence_item)) {
                                    continue;
                                }
                                $is_occurrence_today = !empty($occurrence_item['isToday']);
                                $occurrence_prefix = $index === 0
                                    ? ($is_occurrence_today ? __("Aujourd'hui :", 'mj-member') : __('Prochaine :', 'mj-member'))
                                    : ($is_occurrence_today ? __("Aujourd'hui :", 'mj-member') : __('Ensuite :', 'mj-member'));
                                $occurrence_classes = array('mj-member-event-single__occurrence');
                                if ($is_occurrence_today) {
                                    $occurrence_classes[] = 'is-today';
                                }
                                $occurrence_timestamp = isset($occurrence_item['timestamp']) ? (int) $occurrence_item['timestamp'] : 0;
                                $occurrence_label_display = isset($occurrence_item['label']) ? $occurrence_item['label'] : '';
                                if ($occurrence_timestamp > 0) {
                                    $weekday_label = wp_date('l', $occurrence_timestamp);
                                    $date_label = wp_date(get_option('date_format', 'd/m/Y'), $occurrence_timestamp);
                                    $occurrence_label_display = trim($weekday_label . ' ' . $date_label);
                                }
                                if ($occurrence_label_display === '') {
                                    $occurrence_label_display = isset($occurrence_item['start']) ? $occurrence_item['start'] : '';
                                }
                                if ($occurrence_label_display !== '') {
                                    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
                                        $first_char = mb_substr($occurrence_label_display, 0, 1, 'UTF-8');
                                        $remaining_chars = mb_substr($occurrence_label_display, 1, null, 'UTF-8');
                                        $occurrence_label_display = mb_strtoupper($first_char, 'UTF-8') . $remaining_chars;
                                    } else {
                                        $occurrence_label_display = ucfirst($occurrence_label_display);
                                    }
                                }
                                if (!$occurrence_display_time) {
                                    $occurrence_label_display = preg_replace('/\s+(\d{1,2}[:h]\d{2})$/', '', $occurrence_label_display);
                                }
                                $occurrence_time_display = '';
                                if ($occurrence_display_time) {
                                    if ($occurrence_timestamp > 0) {
                                        $occurrence_time_display = wp_date(get_option('time_format', 'H:i'), $occurrence_timestamp);
                                    } else {
                                        $start_candidate = isset($occurrence_item['start']) ? (string) $occurrence_item['start'] : '';
                                        if ($start_candidate !== '' && preg_match('/(\d{1,2})[:h](\d{2})/', $start_candidate, $time_matches)) {
                                            $hour = str_pad((string) $time_matches[1], 2, '0', STR_PAD_LEFT);
                                            $occurrence_time_display = $hour . ':' . $time_matches[2];
                                        }
                                    }
                                }
                            ?>
                            <li class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $occurrence_classes))); ?>">
                                <span class="mj-member-event-single__occurrence-prefix"><?php echo esc_html($occurrence_prefix); ?></span>
                                <span class="mj-member-event-single__occurrence-label">
                                    <?php echo esc_html(trim($occurrence_label_display)); ?>
                                    <?php if ($occurrence_display_time && $occurrence_time_display !== '') : ?>
                                    <span class="mj-member-event-single__occurrence-time">· <?php echo esc_html($occurrence_time_display); ?></span>
                                    <?php endif; ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                            <?php if ($occurrence_remaining > 0) : ?>
                            <li class="mj-member-event-single__occurrence mj-member-event-single__occurrence--more">
                                <span class="mj-member-event-single__occurrence-label"><?php echo esc_html(sprintf(_n('+ %d autre date', '+ %d autres dates', $occurrence_remaining, 'mj-member'), $occurrence_remaining)); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <ul class="mj-member-event-single__registration-meta">
                        <?php if ($registration_deadline_label !== '') : ?>
                        <li>
                            <strong><?php echo esc_html__("Clôture des inscriptions", 'mj-member'); ?></strong>
                            <span><?php echo esc_html($registration_deadline_label); ?></span>
                        </li>
                        <?php endif; ?>
                        <?php if ($registration_has_participants) : ?>
                        <li>
                            <strong><?php echo esc_html__('Profils déjà inscrits', 'mj-member'); ?></strong>
                            <span><?php echo esc_html(sprintf(_n('%d participant confirmé', '%d participants confirmés', $registration_registered_count, 'mj-member'), $registration_registered_count)); ?></span>
                        </li>
                        <?php if ($registration_available_count > 0) : ?>
                        <li>
                            <strong><?php echo esc_html__('Profils disponibles', 'mj-member'); ?></strong>
                            <span><?php echo esc_html(sprintf(_n('%d profil à inscrire', '%d profils à inscrire', $registration_available_count, 'mj-member'), $registration_available_count)); ?></span>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($registration_total_count > 0) : ?>
                        <li>
                            <strong><?php echo esc_html__('Profils gérés', 'mj-member'); ?></strong>
                            <span><?php echo esc_html(sprintf(_n('%d profil lié', '%d profils liés', $registration_total_count, 'mj-member'), $registration_total_count)); ?></span>
                        </li>
                        <?php endif; ?>
                        <li>
                            <strong><?php echo esc_html__('Paiement', 'mj-member'); ?></strong>
                            <span>
                                <?php if ($registration_payment_required) : ?>
                                    <?php echo esc_html__('Paiement sécurisé requis après validation.', 'mj-member'); ?>
                                    <?php if ($registration_price_amount > 0) : ?>
                                    <?php echo esc_html(sprintf(__('Montant : %s €', 'mj-member'), number_format_i18n($registration_price_amount, 2))); ?>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php echo esc_html__('Gratuit', 'mj-member'); ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    </ul>
                </section>

                <section class="mj-member-event-single__card mj-member-event-single__details">
                    <?php if ($deadline_label !== '') : ?>
                    <div class="mj-member-event-single__detail">
                        <span class="mj-member-event-single__detail-label"><?php echo esc_html__("Inscriptions jusqu'au", 'mj-member'); ?></span>
                        <span class="mj-member-event-single__detail-value"><?php echo esc_html($deadline_label); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($age_label !== '') : ?>
                    <div class="mj-member-event-single__detail">
                        <span class="mj-member-event-single__detail-label"><?php echo esc_html__('Public', 'mj-member'); ?></span>
                        <span class="mj-member-event-single__detail-value"><?php echo esc_html($age_label); ?></span>
                    </div>
                    <?php endif; ?>
                </section>

            </aside>
        </section>

        <?php if ($cover_url !== '' && $cover_thumb === '') : ?>
        <figure class="mj-member-event-single__card" style="padding:0;overflow:hidden;">
            <img src="<?php echo esc_url($cover_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" style="display:block;width:100%;height:auto;" />
        </figure>
        <?php endif; ?>
    </div>
</main>

<?php
get_footer();
if ($document_title_parts_filter !== null) {
    remove_filter('document_title_parts', $document_title_parts_filter, 20);
}
if ($document_title_filter !== null) {
    remove_filter('pre_get_document_title', $document_title_filter, 20);
}
unset($GLOBALS['mj_member_event_context']);
?>
