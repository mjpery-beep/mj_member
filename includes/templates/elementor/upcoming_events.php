<?php

if (!defined('ABSPATH')) {
    exit;
}

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();

$instance_id = isset($template_data['instance_id']) ? sanitize_html_class((string) $template_data['instance_id']) : wp_unique_id('mj-upcoming-events-');
$layout = isset($template_data['layout']) ? sanitize_key($template_data['layout']) : 'list';
if (!in_array($layout, array('list', 'grid', 'slider'), true)) {
    $layout = 'list';
}
$events = isset($template_data['events']) && is_array($template_data['events']) ? $template_data['events'] : array();
$title = isset($template_data['title']) ? $template_data['title'] : '';
$display_title = !empty($template_data['display_title']);
$empty_message = isset($template_data['empty_message']) ? $template_data['empty_message'] : '';
$columns = isset($template_data['columns']) ? (int) $template_data['columns'] : 3;
if ($columns < 2) {
    $columns = 2;
}
if ($columns > 5) {
    $columns = 5;
}
$items_per_column = isset($template_data['items_per_column']) ? (int) $template_data['items_per_column'] : 2;
if ($items_per_column <= 0) {
    $items_per_column = 1;
}
$max_items_limit = isset($template_data['max_items']) ? (int) $template_data['max_items'] : 0;
if ($max_items_limit <= 0) {
    $max_items_limit = count($events);
}
$max_items_requested = isset($template_data['max_items_requested']) ? (int) $template_data['max_items_requested'] : $max_items_limit;
$slides_per_view = isset($template_data['slides_per_view']) ? max(1, (int) $template_data['slides_per_view']) : 1;
$autoplay = !empty($template_data['autoplay']);
$autoplay_delay = isset($template_data['autoplay_delay']) ? max(2, (int) $template_data['autoplay_delay']) : 6;
$view_more = isset($template_data['view_more']) && is_array($template_data['view_more']) ? $template_data['view_more'] : array();

$root_classes = array('mj-upcoming-events', 'layout-' . $layout);
if (empty($events)) {
    $root_classes[] = 'is-empty';
}
if ($layout === 'slider') {
    $root_classes[] = 'is-slider';
}
$root_classes = array_map('sanitize_html_class', array_unique(array_filter($root_classes)));

$style_tokens = array();
if ($layout === 'grid') {
    $style_tokens[] = '--mj-upcoming-columns: ' . $columns;
    $style_tokens[] = '--mj-upcoming-rows: ' . $items_per_column;
}
if ($layout === 'slider') {
    $style_tokens[] = '--mj-upcoming-slides: ' . $slides_per_view;
}
$style_attribute = !empty($style_tokens) ? implode('; ', $style_tokens) : '';

$config = array(
    'layout' => $layout,
    'slidesPerView' => $slides_per_view,
    'autoplay' => $autoplay,
    'autoplayDelay' => max(2000, $autoplay_delay * 1000),
    'itemsPerColumn' => $items_per_column,
    'maxItems' => $max_items_limit,
);
$config_attribute = esc_attr(wp_json_encode($config));

$view_more_enabled = !empty($view_more['enabled']) && !empty($view_more['url']);
$view_more_label = isset($view_more['label']) ? $view_more['label'] : '';
$view_more_url = isset($view_more['url']) ? $view_more['url'] : '';
$view_more_target = isset($view_more['target']) ? $view_more['target'] : '';
$view_more_rel = isset($view_more['rel']) ? $view_more['rel'] : '';
$view_more_alignment = isset($view_more['alignment']) ? sanitize_key($view_more['alignment']) : 'center';
if (!in_array($view_more_alignment, array('left', 'center', 'right'), true)) {
    $view_more_alignment = 'center';
}
$footer_classes = array('mj-upcoming-events__footer', 'is-align-' . $view_more_alignment);
$footer_classes = array_map('sanitize_html_class', $footer_classes);

$contact_links = isset($template_data['contact_links']) && is_array($template_data['contact_links']) ? $template_data['contact_links'] : array();
$contact_links_enabled = !empty($contact_links['enabled']);
$contact_whatsapp = isset($contact_links['whatsapp']) && is_array($contact_links['whatsapp']) ? $contact_links['whatsapp'] : array();
$contact_email = isset($contact_links['email']) && is_array($contact_links['email']) ? $contact_links['email'] : array();

$contact_whatsapp_url = isset($contact_whatsapp['url']) ? esc_url($contact_whatsapp['url']) : '';
$contact_whatsapp_label = isset($contact_whatsapp['label']) ? $contact_whatsapp['label'] : '';
$contact_whatsapp_target = isset($contact_whatsapp['target']) && $contact_whatsapp['target'] === '_blank' ? '_blank' : '';
$contact_whatsapp_rel = isset($contact_whatsapp['rel']) ? $contact_whatsapp['rel'] : '';

$contact_email_url = isset($contact_email['url']) ? esc_url($contact_email['url']) : '';
$contact_email_label = isset($contact_email['label']) ? $contact_email['label'] : '';
$contact_email_target = isset($contact_email['target']) ? $contact_email['target'] : '';
$contact_email_rel = isset($contact_email['rel']) ? $contact_email['rel'] : '';

$root_attributes = array(
    'class="' . esc_attr(implode(' ', $root_classes)) . '"',
    'data-mj-upcoming-events="' . esc_attr($instance_id) . '"',
    'data-layout="' . esc_attr($layout) . '"',
    'data-config="' . $config_attribute . '"',
    'data-items-per-column="' . esc_attr($items_per_column) . '"',
    'data-max-items="' . esc_attr($max_items_limit) . '"',
    'data-max-items-requested="' . esc_attr($max_items_requested) . '"',
);
if ($style_attribute !== '') {
    $root_attributes[] = 'style="' . esc_attr($style_attribute) . '"';
}

$render_event_card = static function ($event, $args = array()) use ($contact_links_enabled, $contact_whatsapp_url, $contact_whatsapp_label, $contact_whatsapp_target, $contact_whatsapp_rel, $contact_email_url, $contact_email_label, $contact_email_target, $contact_email_rel) {
    $args = wp_parse_args(
        $args,
        array(
            'role' => '',
            'classes' => array(),
        )
    );

    $extra_classes = array();
    if (!empty($args['classes']) && is_array($args['classes'])) {
        foreach ($args['classes'] as $class_candidate) {
            if (!is_string($class_candidate) || trim($class_candidate) === '') {
                continue;
            }
            $extra_classes[] = sanitize_html_class($class_candidate);
        }
    }

    $item_classes = array_merge(array('mj-upcoming-events__item'), $extra_classes);
    $item_classes = array_unique(array_filter($item_classes));

    $style_tokens = array();
    if (!empty($event['accent_color'])) {
        $style_tokens[] = '--mj-upcoming-accent: ' . $event['accent_color'];
    }
    if (!empty($event['accent_overlay'])) {
        $style_tokens[] = '--mj-upcoming-accent-overlay: ' . $event['accent_overlay'];
    }

    $item_attributes = array('class="' . esc_attr(implode(' ', $item_classes)) . '"');
    if (!empty($style_tokens)) {
        $item_attributes[] = 'style="' . esc_attr(implode('; ', $style_tokens)) . '"';
    }
    if (!empty($args['role']) && is_string($args['role'])) {
        $item_attributes[] = 'role="' . esc_attr($args['role']) . '"';
    }

    $has_cover = !empty($event['cover_url']);
    $visual_classes = array('mj-upcoming-events__visual', $has_cover ? 'has-cover' : 'no-cover');
    $visual_classes = array_map('sanitize_html_class', array_unique(array_filter($visual_classes)));

    $visual_styles = array();
    if ($has_cover) {
        $visual_styles[] = 'background-image: url(' . esc_url($event['cover_url']) . ')';
    }

    $visual_attributes = array('class="' . esc_attr(implode(' ', $visual_classes)) . '"');
    if (!empty($visual_styles)) {
        $visual_attributes[] = 'style="' . esc_attr(implode('; ', $visual_styles)) . '"';
    }

    echo '<article ' . implode(' ', $item_attributes) . '>';

    echo '<div ' . implode(' ', $visual_attributes) . '>';
    if ($has_cover) {
        echo '<img class="mj-upcoming-events__visual-img" src="' . esc_url($event['cover_url']) . '" alt="' . esc_attr($event['title']) . '" loading="lazy" />';
    } else {
        echo '<div class="mj-upcoming-events__visual-fallback" aria-hidden="true"></div>';
    }
    if (!empty($event['type_label'])) {
        echo '<span class="mj-upcoming-events__badge">' . esc_html($event['type_label']) . '</span>';
    }
    echo '</div>';

    echo '<div class="mj-upcoming-events__body">';
    echo '<h4 class="mj-upcoming-events__item-title">';
    if (!empty($event['permalink'])) {
        echo '<a href="' . esc_url($event['permalink']) . '">' . esc_html($event['title']) . '</a>';
    } else {
        echo esc_html($event['title']);
    }
    echo '</h4>';

    $render_location_in_meta = !empty($event['location_label']) && empty($event['location_address']);
    $has_meta = !empty($event['date_label']) || !empty($event['time_label']) || $render_location_in_meta;

    if ($has_meta) {
        echo '<div class="mj-upcoming-events__meta">';
        if (!empty($event['date_label'])) {
            echo '<div class="mj-upcoming-events__meta-item is-date">';
            echo '<span class="mj-upcoming-events__meta-icon" aria-hidden="true">';
            echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
            echo '<rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5" />';
            echo '<path d="M8 3V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />';
            echo '<path d="M16 3V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />';
            echo '<path d="M3 11H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />';
            echo '</svg>';
            echo '</span>';
            echo '<span class="mj-upcoming-events__meta-text">' . esc_html($event['date_label']) . '</span>';
            echo '</div>';
        }

        if (!empty($event['time_label'])) {
            echo '<div class="mj-upcoming-events__meta-item is-time">';
            echo '<span class="mj-upcoming-events__meta-icon" aria-hidden="true">';
            echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
            echo '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />';
            echo '<path d="M12 7V12L15 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />';
            echo '</svg>';
            echo '</span>';
            echo '<span class="mj-upcoming-events__meta-text">' . esc_html($event['time_label']) . '</span>';
            echo '</div>';
        }

        if ($render_location_in_meta) {
            echo '<div class="mj-upcoming-events__meta-item is-location">';
            echo '<span class="mj-upcoming-events__meta-icon" aria-hidden="true">';
            echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
            echo '<path d="M12 3C8.134 3 5 6.13401 5 10C5 14.971 12 21 12 21C12 21 19 14.971 19 10C19 6.13401 15.866 3 12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />';
            echo '<circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.5" />';
            echo '</svg>';
            echo '</span>';
            echo '<span class="mj-upcoming-events__meta-text">' . esc_html($event['location_label']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    if (!empty($event['price_label'])) {
        echo '<div class="mj-upcoming-events__price">' . esc_html($event['price_label']) . '</div>';
    }

    if (!empty($event['excerpt'])) {
        echo '<p class="mj-upcoming-events__excerpt">' . esc_html($event['excerpt']) . '</p>';
    }

    $show_location_block = (!empty($event['location_label']) || !empty($event['location_address'])) && !empty($event['location_address']);
    if ($show_location_block) {
        echo '<div class="mj-upcoming-events__location">';
        echo '<span class="mj-upcoming-events__location-icon" aria-hidden="true">';
        if (!empty($event['location_initials'])) {
            echo esc_html($event['location_initials']);
        } else {
            echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
            echo '<path d="M12 3C8.134 3 5 6.13401 5 10C5 14.971 12 21 12 21C12 21 19 14.971 19 10C19 6.13401 15.866 3 12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />';
            echo '<circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.5" />';
            echo '</svg>';
        }
        echo '</span>';
        echo '<div class="mj-upcoming-events__location-text">';
        if (!empty($event['location_label'])) {
            echo '<span class="mj-upcoming-events__location-name">' . esc_html($event['location_label']) . '</span>';
        }
        if (!empty($event['location_address'])) {
            echo '<span class="mj-upcoming-events__location-address">' . esc_html($event['location_address']) . '</span>';
        }
        echo '</div>';
        echo '</div>';
    }

    if (!empty($event['permalink'])) {
        echo '<div class="mj-upcoming-events__cta">';
        echo '<a class="mj-upcoming-events__link" href="' . esc_url($event['permalink']) . '"><span>' . esc_html__('Voir l’événement', 'mj-member') . '</span><span class="mj-upcoming-events__link-arrow" aria-hidden="true">&rarr;</span></a>';
        echo '</div>';
    }

    if ($contact_links_enabled && ($contact_whatsapp_url !== '' || $contact_email_url !== '')) {
        echo '<div class="mj-upcoming-events__contact" aria-label="' . esc_attr__('Contact', 'mj-member') . '">';
        if ($contact_whatsapp_url !== '') {
            $label = $contact_whatsapp_label !== '' ? $contact_whatsapp_label : __('WhatsApp', 'mj-member');
            $target_attr = $contact_whatsapp_target === '_blank' ? ' target="_blank"' : '';
            $rel_attr = $contact_whatsapp_rel !== '' ? ' rel="' . esc_attr($contact_whatsapp_rel) . '"' : ($target_attr !== '' ? ' rel="noopener"' : '');
            echo '<a class="mj-upcoming-events__contact-link is-whatsapp" href="' . esc_url($contact_whatsapp_url) . '"' . $target_attr . $rel_attr . ' aria-label="' . esc_attr($label) . '">';
            echo '<span class="mj-upcoming-events__contact-icon" aria-hidden="true">'
                . '<svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" role="presentation">'
                . '<path d="M12 2.5a9.5 9.5 0 0 0-8.27 14.2l-1.03 3.77 3.88-1a9.5 9.5 0 1 0 5.42-16.97z" fill="currentColor" opacity="0.12" />'
                . '<path d="M12 4a8 8 0 0 0-7.1 11.65l-.58 2.16 2.22-.57A8 8 0 1 0 12 4zm4.27 9.31c-.22.63-1.28 1.18-1.8 1.26-.46.07-1.05.1-1.69-.1-.39-.12-.9-.29-1.54-.56-2.72-1.18-4.49-3.92-4.63-4.11-.13-.19-1.1-1.46-1.1-2.79 0-1.33.69-1.98.94-2.27.25-.29.55-.36.73-.36.19 0 .36.01.52.01.17 0 .39-.06.61.47.22.52.75 1.82.81 1.95.07.13.11.29.02.48-.08.19-.12.3-.24.46-.12.17-.26.38-.37.51-.12.12-.24.26-.1.51.13.25.57.93 1.23 1.51.84.75 1.55.99 1.8 1.1.25.11.39.09.52-.05.13-.13.61-.71.77-.95.16-.23.33-.19.55-.11.22.08 1.41.67 1.65.79.24.12.4.18.46.28.06.1.06.63-.16 1.26z" fill="currentColor" />'
                . '</svg>'
                . '</span>';
            echo '<span class="mj-upcoming-events__contact-label">' . esc_html($label) . '</span>';
            echo '</a>';
        }
        if ($contact_email_url !== '') {
            $label = $contact_email_label !== '' ? $contact_email_label : __('Envoyer un mail', 'mj-member');
            $target_attr = $contact_email_target === '_blank' ? ' target="_blank"' : '';
            $rel_attr = $contact_email_rel !== '' ? ' rel="' . esc_attr($contact_email_rel) . '"' : '';
            echo '<a class="mj-upcoming-events__contact-link is-email" href="' . esc_url($contact_email_url) . '"' . $target_attr . $rel_attr . ' aria-label="' . esc_attr($label) . '">';
            echo '<span class="mj-upcoming-events__contact-icon" aria-hidden="true">'
                . '<svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" role="presentation">'
                . '<path d="M3.5 6.5h17a1 1 0 0 1 1 1v9.8a1 1 0 0 1-1 1h-17a1 1 0 0 1-1-1V7.5a1 1 0 0 1 1-1z" fill="currentColor" opacity="0.12" />'
                . '<path d="M3.5 5h17c1.38 0 2.5 1.12 2.5 2.5v9.8c0 1.38-1.12 2.5-2.5 2.5h-17A2.5 2.5 0 0 1 1 17.3V7.5C1 6.12 2.12 5 3.5 5zm0 1.5a1 1 0 0 0-1 1v.36l8.54 5.34a2 2 0 0 0 2.12 0L21.5 7.86V7.5a1 1 0 0 0-1-1h-17zm17 11.8a1 1 0 0 0 1-1V9.53l-7.15 4.47a3.5 3.5 0 0 1-3.7 0L3.5 9.53v7.77a1 1 0 0 0 1 1h17z" fill="currentColor" />'
                . '</svg>'
                . '</span>';
            echo '<span class="mj-upcoming-events__contact-label">' . esc_html($label) . '</span>';
            echo '</a>';
        }
        echo '</div>';
    }

    echo '</div>';

    echo '</article>';
};

?>
<?php if (class_exists('\\Elementor\\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) : ?>
    <?php if (!defined('MJ_MEMBER_UPCOMING_EVENTS_EDITOR_STYLES')) : ?>
        <?php define('MJ_MEMBER_UPCOMING_EVENTS_EDITOR_STYLES', true); ?>
        <style>
            .mj-upcoming-events {
                --mj-upcoming-accent: #2563eb;
                --mj-upcoming-surface: #ffffff;
                --mj-upcoming-border: rgba(15, 23, 42, 0.08);
                --mj-upcoming-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
                --mj-upcoming-radius: 20px;
                --mj-upcoming-gap: 24px;
                --mj-upcoming-columns: 3;
                --mj-upcoming-slides: 1;
                display: flex;
                flex-direction: column;
                gap: 28px;
                color: #0f172a;
            }

            .mj-upcoming-events__title {
                margin: 0;
                font-size: 1.58rem;
                font-weight: 700;
                color: #0f172a;
                letter-spacing: -0.01em;
            }

            .mj-upcoming-events__empty {
                margin: 0;
                font-size: 1rem;
                color: #475569;
            }

            .mj-upcoming-events__list {
                display: flex;
                flex-direction: column;
                gap: var(--mj-upcoming-gap, 24px);
            }

            .mj-upcoming-events.layout-grid .mj-upcoming-events__list {
                display: grid;
                gap: var(--mj-upcoming-gap, 24px);
                grid-template-columns: repeat(var(--mj-upcoming-columns, 3), minmax(0, 1fr));
            }

            .mj-upcoming-events__item {
                position: relative;
                display: flex;
                flex-direction: column;
                min-height: 100%;
                border-radius: var(--mj-upcoming-radius, 20px);
                background: var(--mj-upcoming-surface, #ffffff);
                border: 1px solid var(--mj-upcoming-border, rgba(15, 23, 42, 0.08));
                box-shadow: var(--mj-upcoming-shadow, 0 16px 32px rgba(15, 23, 42, 0.12));
                overflow: hidden;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }

            .mj-upcoming-events__visual {
                position: relative;
                padding-bottom: 60%;
                background-color: rgba(15, 23, 42, 0.06);
                background-size: cover;
                background-position: center;
                overflow: hidden;
            }

            .mj-upcoming-events__visual-img {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .mj-upcoming-events__badge {
                position: absolute;
                top: 14px;
                right: 14px;
                display: inline-flex;
                align-items: center;
                padding: 6px 14px;
                border-radius: 999px;
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                color: #ffffff;
                background: var(--mj-upcoming-accent, #2563eb);
            }

            .mj-upcoming-events__body {
                display: flex;
                flex-direction: column;
                gap: 18px;
                padding: 26px 28px 30px;
            }

            .mj-upcoming-events__item-title {
                margin: 0;
                font-size: 1.22rem;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.3;
                letter-spacing: -0.01em;
            }

            .mj-upcoming-events__item-title a {
                color: inherit;
                text-decoration: none;
            }

            .mj-upcoming-events__meta {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .mj-upcoming-events__meta-item {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 0.98rem;
                color: #475569;
            }

            .mj-upcoming-events__price {
                font-size: 1rem;
                font-weight: 600;
                color: #0f172a;
            }

            .mj-upcoming-events__excerpt {
                margin: 0;
                font-size: 0.95rem;
                color: #475569;
            }

            .mj-upcoming-events__location {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(15, 23, 42, 0.05);
            }

            .mj-upcoming-events__location-text {
                display: flex;
                flex-direction: column;
                font-size: 0.9rem;
                color: #1f2937;
            }

            .mj-upcoming-events__cta {
                margin-top: auto;
            }

            .mj-upcoming-events__link {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-weight: 600;
                text-decoration: none;
                color: var(--mj-upcoming-accent, #2563eb);
            }

            .mj-upcoming-events__contact {
                display: flex;
                gap: 10px;
                margin-top: 16px;
            }

            .mj-upcoming-events__contact-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 14px;
                border-radius: 10px;
                background: rgba(37, 99, 235, 0.12);
                color: var(--mj-upcoming-accent, #2563eb);
                text-decoration: none;
                font-size: 0.9rem;
            }

            .mj-upcoming-events__footer {
                display: flex;
                justify-content: center;
                padding-top: 12px;
            }

            .mj-upcoming-events__view-more {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 12px 26px;
                border-radius: 999px;
                background: var(--mj-upcoming-accent, #2563eb);
                color: #ffffff;
                text-decoration: none;
                font-weight: 600;
            }
        </style>
    <?php endif; ?>
<?php endif; ?>
<div <?php echo implode(' ', $root_attributes); ?>>
    <?php if ($display_title && $title !== '') : ?>
        <h3 class="mj-upcoming-events__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if (empty($events)) : ?>
        <?php if ($empty_message !== '') : ?>
            <p class="mj-upcoming-events__empty"><?php echo esc_html($empty_message); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <?php if ($layout === 'slider') : ?>
            <div class="mj-upcoming-events__slider" data-upcoming-slider>
                <button type="button" class="mj-upcoming-events__nav-button is-prev" data-action="prev" aria-label="<?php echo esc_attr__('Afficher les événements précédents', 'mj-member'); ?>">&larr;</button>
                <div class="mj-upcoming-events__track" data-upcoming-track>
                    <?php foreach ($events as $event) {
                        $render_event_card($event);
                    } ?>
                </div>
                <button type="button" class="mj-upcoming-events__nav-button is-next" data-action="next" aria-label="<?php echo esc_attr__('Afficher les événements suivants', 'mj-member'); ?>">&rarr;</button>
            </div>
        <?php else : ?>
            <div class="mj-upcoming-events__list" role="list">
                <?php foreach ($events as $event) {
                    $render_event_card($event, array('role' => 'listitem'));
                } ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($view_more_enabled) : ?>
        <div class="<?php echo esc_attr(implode(' ', $footer_classes)); ?>">
            <a class="mj-upcoming-events__view-more" href="<?php echo esc_url($view_more_url); ?>"<?php echo $view_more_target !== '' ? ' target="' . esc_attr($view_more_target) . '"' : ''; ?><?php echo $view_more_rel !== '' ? ' rel="' . esc_attr($view_more_rel) . '"' : ''; ?>>
                <?php echo esc_html($view_more_label); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
