<?php

use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_account_menu_normalize_classes')) {
    function mj_member_account_menu_normalize_classes($classes) {
        if (is_string($classes)) {
            $classes = preg_split('/\s+/', $classes);
        }

        if (!is_array($classes)) {
            return array();
        }

        $normalized = array();
        foreach ($classes as $class) {
            $class = sanitize_html_class((string) $class);
            if ($class === '') {
                continue;
            }
            $normalized[] = $class;
        }

        return array_values(array_unique($normalized));
    }
}

if (!function_exists('mj_member_account_menu_finalize_entry')) {
    function mj_member_account_menu_finalize_entry(array $entry) {
        $children = array();
        if (!empty($entry['children']) && is_array($entry['children'])) {
            foreach ($entry['children'] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $children[] = mj_member_account_menu_finalize_entry($child);
            }
        }

        if (isset($entry['parent_id'])) {
            unset($entry['parent_id']);
        }

        $classes = isset($entry['classes']) ? mj_member_account_menu_normalize_classes($entry['classes']) : array();
        $title = isset($entry['title']) ? wp_strip_all_tags((string) $entry['title']) : '';
        $url = isset($entry['url']) ? (string) $entry['url'] : '';
        $target = isset($entry['target']) && $entry['target'] === '_blank' ? '_blank' : '_self';

        $is_current = in_array('current-menu-item', $classes, true) || in_array('current_page_item', $classes, true);
        $is_ancestor = in_array('current-menu-ancestor', $classes, true) || in_array('current-menu-parent', $classes, true);

        $icon_data = array();
        if (!empty($entry['icon'])) {
            $icon_data = mj_member_account_menu_normalize_icon($entry['icon']);
        } else {
            $icon_data = mj_member_account_menu_normalize_icon(array());
        }

        return array(
            'id' => isset($entry['id']) ? (int) $entry['id'] : 0,
            'title' => $title,
            'url' => $url !== '' ? esc_url_raw($url) : '#',
            'target' => $target,
            'classes' => $classes,
            'children' => $children,
            'has_children' => !empty($children),
            'is_current' => $is_current,
            'is_ancestor' => $is_ancestor,
            'icon' => $icon_data,
            'has_icon' => !empty($icon_data['html']),
        );
    }
}

if (!function_exists('mj_member_account_menu_build_tree')) {
    function mj_member_account_menu_build_tree(array $raw_items) {
        $items_by_id = array();

        foreach ($raw_items as $item) {
            if (!is_object($item)) {
                continue;
            }

            $id = (int) $item->ID;

            $items_by_id[$id] = array(
                'id' => $id,
                'title' => isset($item->title) ? $item->title : '',
                'url' => isset($item->url) ? $item->url : '',
                'target' => !empty($item->target) && $item->target === '_blank' ? '_blank' : '_self',
                'classes' => isset($item->classes) ? $item->classes : array(),
                'parent_id' => isset($item->menu_item_parent) ? (int) $item->menu_item_parent : 0,
                'children' => array(),
                'icon' => function_exists('mj_member_account_menu_get_icon_payload') ? mj_member_account_menu_get_icon_payload($id) : array(),
            );
        }

        $tree = array();

        foreach ($items_by_id as $id => &$entry) {
            $parent_id = isset($entry['parent_id']) ? (int) $entry['parent_id'] : 0;

            if ($parent_id > 0 && isset($items_by_id[$parent_id])) {
                $items_by_id[$parent_id]['children'][] =& $entry;
                continue;
            }

            $tree[] =& $entry;
        }

        unset($entry);

        $final = array();
        foreach ($tree as $entry) {
            $final[] = mj_member_account_menu_finalize_entry($entry);
        }

        return $final;
    }
}

if (!function_exists('mj_member_account_menu_normalize_preview_items')) {
    function mj_member_account_menu_normalize_preview_items(array $items, $return_raw = false) {
        $raw = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = isset($item['title']) ? wp_strip_all_tags((string) $item['title']) : '';
            if ($title === '') {
                continue;
            }

            $entry = array(
                'id' => isset($item['id']) ? (int) $item['id'] : 0,
                'title' => $title,
                'url' => isset($item['url']) ? (string) $item['url'] : '#',
                'target' => isset($item['target']) && $item['target'] === '_blank' ? '_blank' : '_self',
                'classes' => array(),
                'children' => array(),
                'icon' => array(),
            );

            if (!empty($item['children']) && is_array($item['children'])) {
                $entry['children'] = mj_member_account_menu_normalize_preview_items($item['children'], true);
            }

            $raw[] = $entry;
        }

        if ($return_raw) {
            return $raw;
        }

        $final = array();
        foreach ($raw as $entry) {
            $final[] = mj_member_account_menu_finalize_entry($entry);
        }

        return $final;
    }
}

if (!function_exists('mj_member_account_menu_normalize_icon')) {
    function mj_member_account_menu_normalize_icon($icon) {
        $normalized = array(
            'id' => 0,
            'url' => '',
            'alt' => '',
            'html' => '',
            'type' => '',
        );

        if (!is_array($icon) || empty($icon['type']) || $icon['type'] !== 'image') {
            return $normalized;
        }

        $allowed_tags = function_exists('mj_member_login_component_allowed_icon_tags')
            ? mj_member_login_component_allowed_icon_tags()
            : array(
                'img' => array(
                    'src' => true,
                    'alt' => true,
                    'class' => true,
                    'loading' => true,
                    'decoding' => true,
                    'width' => true,
                    'height' => true,
                    'srcset' => true,
                    'sizes' => true,
                ),
            );

        $normalized['id'] = isset($icon['id']) ? (int) $icon['id'] : 0;
        $normalized['url'] = isset($icon['url']) ? esc_url_raw((string) $icon['url']) : '';
        $normalized['alt'] = isset($icon['alt']) ? sanitize_text_field((string) $icon['alt']) : '';
        $normalized['type'] = 'image';

        if (!empty($icon['html'])) {
            $normalized['html'] = wp_kses((string) $icon['html'], $allowed_tags);
        } elseif ($normalized['url'] !== '') {
            $alt_attr = $normalized['alt'] !== '' ? esc_attr($normalized['alt']) : '';
            $normalized['html'] = sprintf(
                '<img src="%s" alt="%s" class="mj-member-account-menu__item-icon-image" loading="lazy" />',
                esc_url($normalized['url']),
                $alt_attr
            );
        }

        if ($normalized['html'] === '') {
            $normalized['type'] = '';
        }

        return $normalized;
    }
}

if (!function_exists('mj_member_account_menu_default_preview_items')) {
    function mj_member_account_menu_default_preview_items() {
        return array(
            array(
                'title' => __('Tableau de bord', 'mj-member'),
                'url' => '#',
            ),
            array(
                'title' => __('Mon compte', 'mj-member'),
                'url' => '#',
                'children' => array(
                    array('title' => __('Mes informations', 'mj-member'), 'url' => '#'),
                    array('title' => __('Mes réservations', 'mj-member'), 'url' => '#'),
                    array('title' => __('Notifications', 'mj-member'), 'url' => '#'),
                ),
            ),
            array(
                'title' => __('Contact', 'mj-member'),
                'url' => '#',
            ),
        );
    }
}

if (!function_exists('mj_member_account_menu_prepare_items')) {
    function mj_member_account_menu_prepare_items($menu_id, array $preview_items, $is_preview) {
        $items = array();

        if ($menu_id > 0 && wp_get_nav_menu_object($menu_id)) {
            $raw_items = wp_get_nav_menu_items($menu_id, array('order' => 'ASC'));
            if (!empty($raw_items)) {
                $items = mj_member_account_menu_build_tree($raw_items);
            }
        }

        if (empty($items) && !empty($preview_items)) {
            $items = mj_member_account_menu_normalize_preview_items($preview_items);
        }

        if (empty($items) && $is_preview) {
            $items = mj_member_account_menu_normalize_preview_items(mj_member_account_menu_default_preview_items());
        }

        return $items;
    }
}

if (!function_exists('mj_member_account_menu_render_modal_items')) {
    function mj_member_account_menu_render_modal_items(array $items, $depth = 0, &$context = null) {
        if (!is_array($context)) {
            $context = array(
                'toggle_prefix' => 'mj-member-account-sublist-',
                'toggle_index' => 0,
                'is_preview' => false,
                'show_submenus' => true,
            );
        }

        if (!array_key_exists('show_submenus', $context)) {
            $context['show_submenus'] = true;
        }

        $show_submenus = !empty($context['show_submenus']);

        $output = '';

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['title'])) {
                continue;
            }

            $has_children = $show_submenus && !empty($item['has_children']) && !empty($item['children']);

            $item_classes = array(
                'mj-member-login-component__account-item',
                'mj-member-account-menu__item',
                'mj-member-account-menu__item--level-' . (int) $depth,
            );

            if ($has_children) {
                $item_classes[] = 'mj-member-account-menu__item--has-children';
            }
            else {
                $item_classes[] = 'mj-member-account-menu__item--no-children';
            }

            if (!empty($item['is_current'])) {
                $item_classes[] = 'is-current';
            }

            if (!empty($item['is_ancestor'])) {
                $item_classes[] = 'is-ancestor';
            }

            $link_classes = array(
                'mj-member-login-component__account-link',
                'mj-member-account-menu__link',
            );

            if ($has_children) {
                $link_classes[] = 'mj-member-account-menu__link--has-children';
            }

            if (!empty($item['is_current'])) {
                $link_classes[] = 'is-current';
            }

            $url = isset($item['url']) ? $item['url'] : '#';
            $target = isset($item['target']) && $item['target'] === '_blank' ? '_blank' : '_self';
            $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';

            $toggle_id = '';
            $should_expand = false;

            if ($has_children) {
                $context['toggle_index'] = isset($context['toggle_index']) ? (int) $context['toggle_index'] + 1 : 1;
                $toggle_id = $context['toggle_prefix'] . $context['toggle_index'];
                $should_expand = !empty($item['is_current']) || !empty($item['is_ancestor']);

                if (!$should_expand && !empty($context['is_preview']) && (int) $context['toggle_index'] === 1) {
                    $should_expand = true;
                }

                if ($should_expand) {
                    $item_classes[] = 'is-expanded';
                }
            }

            $output .= '<li class="' . esc_attr(implode(' ', $item_classes)) . '">';
            $output .= '<div class="mj-member-account-menu__item-inner">';
            $output .= '<a class="' . esc_attr(implode(' ', $link_classes)) . '" href="' . esc_url($url !== '' ? $url : '#') . '" target="' . esc_attr($target) . '"';
            if ($rel !== '') {
                $output .= $rel;
            }
            if (!empty($item['is_current'])) {
                $output .= ' aria-current="page"';
            }
            $output .= '>';
            $output .= '<span class="mj-member-login-component__account-main">';
            if (!empty($item['icon']['html'])) {
                $output .= '<span class="mj-member-account-menu__item-icon">' . $item['icon']['html'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            $output .= '<span class="mj-member-login-component__account-label">' . esc_html($item['title']) . '</span>';
            $output .= '</span>';
            if (!$has_children) {
                $output .= '<span class="mj-member-login-component__account-icon" aria-hidden="true">&rsaquo;</span>';
            }
            $output .= '</a>';

            if ($has_children) {
                /* translators: %s is the menu item title. */
                $sr_text = sprintf(__('Afficher les sous-liens de %s', 'mj-member'), $item['title']);
                $output .= '<button type="button" class="mj-member-account-menu__expander" data-mj-account-toggle aria-controls="' . esc_attr($toggle_id) . '" aria-expanded="' . ($should_expand ? 'true' : 'false') . '">';
                $output .= '<span class="screen-reader-text">' . esc_html($sr_text) . '</span>';
                $output .= '<span class="mj-member-account-menu__expander-icon" aria-hidden="true">&#9662;</span>';
                $output .= '</button>';
            }

            $output .= '</div>';

            if ($has_children) {
                $sublist_classes = array(
                    'mj-member-account-menu__sublist',
                    'mj-member-account-menu__sublist--level-' . (int) $depth,
                );

                if ($should_expand) {
                    $sublist_classes[] = 'is-open';
                }

                $output .= '<ul id="' . esc_attr($toggle_id) . '" class="' . esc_attr(implode(' ', $sublist_classes)) . '" data-mj-account-sublist aria-hidden="' . ($should_expand ? 'false' : 'true') . '">';
                $output .= mj_member_account_menu_render_modal_items($item['children'], $depth + 1, $context);
                $output .= '</ul>';
            }

            $output .= '</li>';
        }

        return $output;
    }
}

if (!function_exists('mj_member_account_menu_render_desktop_items')) {
    function mj_member_account_menu_render_desktop_items(array $items, $depth = 0, $show_submenus = true) {
        $output = '';

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['title'])) {
                continue;
            }

            $has_children = $show_submenus && !empty($item['has_children']) && !empty($item['children']);

            $item_classes = array(
                'mj-member-account-menu__item',
                'mj-member-account-menu__item--level-' . (int) $depth,
            );

            if ($has_children) {
                $item_classes[] = 'mj-member-account-menu__item--has-children';
            }

            if (!empty($item['is_current'])) {
                $item_classes[] = 'is-current';
            }

            if (!empty($item['is_ancestor'])) {
                $item_classes[] = 'is-ancestor';
            }

            if (!empty($item['classes']) && is_array($item['classes'])) {
                foreach ($item['classes'] as $class) {
                    $item_classes[] = 'menu-item-' . sanitize_html_class($class);
                }
            }

            $link_classes = array('mj-member-account-menu__link');
            if ($has_children) {
                $link_classes[] = 'mj-member-account-menu__link--has-children';
            }
            if (!empty($item['is_current'])) {
                $link_classes[] = 'is-current';
            }

            $url = isset($item['url']) ? $item['url'] : '#';
            $target = isset($item['target']) && $item['target'] === '_blank' ? '_blank' : '_self';
            $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';

            $link_attributes = array(
                'class="' . esc_attr(implode(' ', $link_classes)) . '"',
                'href="' . esc_url($url !== '' ? $url : '#') . '"',
                'target="' . esc_attr($target) . '"',
            );

            if ($rel !== '') {
                $link_attributes[] = $rel;
            }

            if (!empty($item['is_current'])) {
                $link_attributes[] = 'aria-current="page"';
            }

            if ($has_children) {
                $link_attributes[] = 'aria-haspopup="true"';
                $link_attributes[] = 'aria-expanded="false"';
            }

            $output .= '<li class="' . esc_attr(implode(' ', $item_classes)) . '">';
            $output .= '<a ' . implode(' ', $link_attributes) . '>';
            $output .= '<span class="mj-member-account-menu__link-content">';
            if (!empty($item['icon']['html'])) {
                $output .= '<span class="mj-member-account-menu__item-icon">' . $item['icon']['html'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            $output .= '<span class="mj-member-account-menu__link-label">' . esc_html($item['title']) . '</span>';
            $output .= '</span>';
            if ($has_children) {
                $output .= '<span class="mj-member-account-menu__caret" aria-hidden="true">&#9662;</span>';
            }
            $output .= '</a>';

            if ($has_children) {
                $output .= '<ul class="mj-member-account-menu__submenu">';
                $output .= mj_member_account_menu_render_desktop_items($item['children'], $depth + 1, $show_submenus);
                $output .= '</ul>';
            }

            $output .= '</li>';
        }

        return $output;
    }
}

if (!function_exists('mj_member_render_account_menu_component')) {
    function mj_member_render_account_menu_component(array $args = array()) {
        AssetsManager::requirePackage('login-component');

        $defaults = array(
            'menu_id' => 0,
            'button_label' => __('Menu', 'mj-member'),
            'panel_title' => __('Mon espace MJ', 'mj-member'),
            'panel_description' => '',
            'icon_html' => '',
            'mobile_only' => true,
            'preview_items' => array(),
            'layout' => 'modal',
            'desktop_alignment' => 'right',
            'show_submenus' => true,
            'event_button' => array(),
        );
        $args = wp_parse_args($args, $defaults);

        if ($args['layout'] === 'both') {
            $desktop_args = $args;
            $desktop_args['layout'] = 'desktop';
            $desktop_args['mobile_only'] = false;

            $modal_args = $args;
            $modal_args['layout'] = 'modal';
            $modal_args['mobile_only'] = !empty($args['mobile_only']);

            return mj_member_render_account_menu_component($desktop_args) . mj_member_render_account_menu_component($modal_args);
        }

        $allowed_icon_tags = function_exists('mj_member_login_component_allowed_icon_tags')
            ? mj_member_login_component_allowed_icon_tags()
            : array();

        $event_button_settings = array();
        if (isset($args['event_button']) && is_array($args['event_button'])) {
            $event_button_settings = $args['event_button'];
        }

        $manage_events_button_settings = array();
        if (isset($args['manage_events_button']) && is_array($args['manage_events_button'])) {
            $manage_events_button_settings = $args['manage_events_button'];
        }

        $event_button_label = isset($event_button_settings['label']) ? trim((string) $event_button_settings['label']) : '';
        $event_button_url = isset($event_button_settings['url']) ? esc_url_raw((string) $event_button_settings['url']) : '';
        $event_button_is_external = !empty($event_button_settings['is_external']);
        $event_button_nofollow = !empty($event_button_settings['nofollow']);
        $event_button_icon_html = '';
        if (!empty($event_button_settings['icon_html'])) {
            $event_button_icon_html = wp_kses((string) $event_button_settings['icon_html'], $allowed_icon_tags);
        }
        $event_button_icon_type = 'icon';
        if (!empty($event_button_settings['icon_type']) && $event_button_settings['icon_type'] === 'image') {
            $event_button_icon_type = 'image';
        }

        $manage_events_icon_url_setting = '';
        if (!empty($manage_events_button_settings['icon_url'])) {
            $manage_events_icon_url_setting = esc_url_raw((string) $manage_events_button_settings['icon_url']);
        }

        $manage_events_url_setting = '';
        if (!empty($manage_events_button_settings['url'])) {
            $manage_events_url_setting = esc_url_raw((string) $manage_events_button_settings['url']);
        }

        $layout = $args['layout'] === 'desktop' ? 'desktop' : 'modal';
        $desktop_alignment = in_array($args['desktop_alignment'], array('left', 'center', 'right'), true)
            ? $args['desktop_alignment']
            : 'right';

        $icon_html = '';
        if (!empty($args['icon_html'])) {
            $icon_html = wp_kses((string) $args['icon_html'], $allowed_icon_tags);
        }

        $is_preview = false;
        if (function_exists('mj_member_login_component_is_preview_mode')) {
            $is_preview = mj_member_login_component_is_preview_mode();
        } elseif (defined('ELEMENTOR_VERSION') && is_admin()) {
            $is_preview = true;
        }

        $event_button_href = $event_button_url;
        if ($event_button_href === '' && $is_preview && $event_button_label !== '') {
            $event_button_href = '#';
        }

        $event_button_rel = '';
        $event_button_markup = '';
        $has_event_button = $event_button_label !== '' && $event_button_href !== '';

        $has_manage_events_button = false;
        $manage_events_button_markup = '';

        $manage_events_roles = array(
            MjRoles::ANIMATEUR,
            MjRoles::COORDINATEUR,
            MjRoles::BENEVOLE,
        );

        $current_member_role = '';
        if (function_exists('mj_member_get_current_member')) {
            $current_member = mj_member_get_current_member();
            if (is_object($current_member) && isset($current_member->role)) {
                $current_member_role = sanitize_key((string) $current_member->role);
            } elseif (is_array($current_member) && isset($current_member['role'])) {
                $current_member_role = sanitize_key((string) $current_member['role']);
            }
        }

        if ($current_member_role !== '') {
            $normalized_role = MjRoles::normalize($current_member_role);
            if (in_array($normalized_role, $manage_events_roles, true)) {
                $has_manage_events_button = true;
            }
        }

        if ($is_preview) {
            $has_manage_events_button = true;
        }

        if ($has_event_button) {
            $rel_parts = array();
            if ($event_button_is_external) {
                $rel_parts[] = 'noopener';
                $rel_parts[] = 'noreferrer';
            }
            if ($event_button_nofollow) {
                $rel_parts[] = 'nofollow';
            }
            if (!empty($rel_parts)) {
                $event_button_rel = implode(' ', array_unique($rel_parts));
            }

            ob_start();
            ?>
            <a
                class="mj-member-account-menu__event-button"
                href="<?php echo esc_url($event_button_href); ?>"
                <?php if ($event_button_is_external) : ?>target="_blank"<?php endif; ?>
                <?php if ($event_button_rel !== '') : ?>rel="<?php echo esc_attr($event_button_rel); ?>"<?php endif; ?>
                aria-label="<?php echo esc_attr($event_button_label); ?>"
            >
                <?php if ($event_button_icon_html !== '') : ?>
                    <?php
                    $event_button_icon_classes = array('mj-member-account-menu__event-button-icon');
                    if ($event_button_icon_type === 'image') {
                        $event_button_icon_classes[] = 'mj-member-account-menu__event-button-icon--image';
                    }
                    ?>
                    <span class="<?php echo esc_attr(implode(' ', $event_button_icon_classes)); ?>"<?php if ($event_button_icon_type !== 'image') : ?> aria-hidden="true"<?php endif; ?>>
                        <?php echo $event_button_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                <?php endif; ?>
                <span class="mj-member-account-menu__event-button-label"><?php echo esc_html($event_button_label); ?></span>
            </a>
            <?php
            $event_button_markup = trim((string) ob_get_clean());

            if ($event_button_markup === '') {
                $has_event_button = false;
            }
        }

        if ($has_manage_events_button) {
            $default_manage_events_url = $manage_events_url_setting !== ''
                ? $manage_events_url_setting
                : home_url('/gestion-evenement/');
            $manage_events_url = (string) apply_filters('mj_member_account_menu_manage_events_url', $default_manage_events_url);
            if ($manage_events_url === '') {
                $has_manage_events_button = false;
            } elseif ($manage_events_url === '#' && !$is_preview) {
                $has_manage_events_button = false;
            }

            if ($has_manage_events_button) {
                $manage_events_label = apply_filters('mj_member_account_menu_manage_events_label', __('Gestion des événements', 'mj-member'));
                $manage_events_icon_url = $manage_events_icon_url_setting !== ''
                    ? $manage_events_icon_url_setting
                    : (string) apply_filters('mj_member_account_menu_manage_events_icon_url', 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Windows_Settings_icon.svg');

                if (!is_string($manage_events_icon_url)) {
                    $manage_events_icon_url = '';
                }

                ob_start();
                ?>
                <a
                    class="mj-member-account-menu__manage-events-button"
                    href="<?php echo esc_url($manage_events_url); ?>"
                    aria-label="<?php echo esc_attr($manage_events_label); ?>"
                >
                    <?php if ($manage_events_icon_url !== '') : ?>
                        <span class="mj-member-account-menu__manage-events-button-icon" aria-hidden="true">
                            <img src="<?php echo esc_url($manage_events_icon_url); ?>" alt="" class="mj-member-account-menu__manage-events-button-image" loading="lazy" decoding="async" />
                        </span>
                    <?php endif; ?>
                </a>
                <?php
                $manage_events_button_markup = trim((string) ob_get_clean());

                if ($manage_events_button_markup === '') {
                    $has_manage_events_button = false;
                }
            }
        }

        $menu_items = mj_member_account_menu_prepare_items(
            (int) $args['menu_id'],
            is_array($args['preview_items']) ? $args['preview_items'] : array(),
            $is_preview
        );

        $show_submenus = !empty($args['show_submenus']);

        $wrapper_classes = array('mj-member-account-menu', 'mj-member-account-menu--layout-' . $layout);
        if ($has_event_button) {
            $wrapper_classes[] = 'mj-member-account-menu--has-event-button';
        }
        if ($has_manage_events_button) {
            $wrapper_classes[] = 'mj-member-account-menu--has-manage-events-button';
        }
        $wrapper_attributes = array();

        if ($layout === 'modal') {
            array_unshift($wrapper_classes, 'mj-member-login-component');
            if (!empty($args['mobile_only'])) {
                $wrapper_classes[] = 'mj-member-account-menu--mobile-only';
            }
        }

        if (!$show_submenus) {
            $wrapper_classes[] = 'mj-member-account-menu--no-submenus';
        }

        $wrapper_attributes[] = 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"';
        $wrapper_attributes[] = 'data-component="account-menu-' . esc_attr($layout) . '"';

        if ($layout === 'modal') {
            $wrapper_attributes[] = 'data-mj-member-login';
        }

        if ($is_preview) {
            $wrapper_attributes[] = 'data-preview="1"';
        }

        $wrapper_attributes[] = 'data-layout="' . esc_attr($layout) . '"';
        $wrapper_attributes[] = 'data-show-submenus="' . ($show_submenus ? '1' : '0') . '"';
        $wrapper_attributes[] = 'data-has-event-button="' . ($has_event_button ? '1' : '0') . '"';
        $wrapper_attributes[] = 'data-has-manage-events-button="' . ($has_manage_events_button ? '1' : '0') . '"';

        if ($layout === 'desktop') {
            $nav_classes = array(
                'mj-member-account-menu__nav',
                'mj-member-account-menu__nav--align-' . $desktop_alignment,
            );

            ob_start();
            ?>
            <div <?php echo implode(' ', $wrapper_attributes); ?>>
                <div class="mj-member-account-menu__actions">
                    <nav class="<?php echo esc_attr(implode(' ', $nav_classes)); ?>" role="navigation" aria-label="<?php echo esc_attr__('Navigation du compte', 'mj-member'); ?>">
                        <?php if (!empty($menu_items)) : ?>
                            <ul class="mj-member-account-menu__list">
                                <?php echo mj_member_account_menu_render_desktop_items($menu_items, 0, $show_submenus); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </ul>
                        <?php else : ?>
                            <p class="mj-member-account-menu__empty"><?php esc_html_e('Sélectionnez un menu WordPress dans les options du widget.', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </nav>
                    <?php if ($has_event_button && $event_button_markup !== '') : ?>
                        <?php echo $event_button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                    <?php if ($has_manage_events_button && $manage_events_button_markup !== '') : ?>
                        <?php echo $manage_events_button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        $button_label = is_string($args['button_label']) && $args['button_label'] !== ''
            ? $args['button_label']
            : $defaults['button_label'];
        $panel_title = is_string($args['panel_title']) && $args['panel_title'] !== ''
            ? $args['panel_title']
            : $defaults['panel_title'];
        $panel_description = is_string($args['panel_description']) ? $args['panel_description'] : '';

        $component_id = function_exists('wp_unique_id') ? wp_unique_id('mj-member-account-menu-') : uniqid('mj-member-account-menu-');
        $panel_id = $component_id . '-panel';
        $title_id = $panel_id . '-title';
        $description_id = $panel_id . '-description';

        $toggle_context = array(
            'toggle_prefix' => $component_id . '-section-',
            'toggle_index' => 0,
            'is_preview' => $is_preview,
            'show_submenus' => $show_submenus,
        );

        $trigger_classes = array('mj-member-login-component__trigger', 'mj-member-account-menu__trigger');
        if ($icon_html !== '') {
            $trigger_classes[] = 'mj-member-login-component__trigger--has-icon';
        }

        $panel_classes = array('mj-member-login-component__panel', 'mj-member-account-menu__panel');
        $panel_should_open = $is_preview;
        if ($panel_should_open) {
            $panel_classes[] = 'is-active';
        }

        ob_start();
        ?>
        <div <?php echo implode(' ', $wrapper_attributes); ?>>
            <div class="mj-member-account-menu__actions">
                <button
                    type="button"
                    class="<?php echo esc_attr(implode(' ', $trigger_classes)); ?>"
                    data-mj-login-trigger
                    data-login-state="logged-out"
                    data-target="<?php echo esc_attr($panel_id); ?>"
                    aria-controls="<?php echo esc_attr($panel_id); ?>"
                    aria-expanded="<?php echo $panel_should_open ? 'true' : 'false'; ?>"
                    aria-haspopup="dialog">
                    <?php if ($icon_html !== '') : ?>
                        <span class="mj-member-login-component__trigger-icon" aria-hidden="true"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <span class="mj-member-login-component__trigger-label"><?php echo esc_html($button_label); ?></span>
                </button>
                <?php if ($has_event_button && $event_button_markup !== '') : ?>
                    <?php echo $event_button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
                <?php if ($has_manage_events_button && $manage_events_button_markup !== '') : ?>
                    <?php echo $manage_events_button_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </div>
            <div
                id="<?php echo esc_attr($panel_id); ?>"
                class="<?php echo esc_attr(implode(' ', $panel_classes)); ?>"
                data-mj-login-panel
                role="dialog"
                aria-modal="false"
                aria-labelledby="<?php echo esc_attr($title_id); ?>"
                aria-hidden="<?php echo $panel_should_open ? 'false' : 'true'; ?>"
                <?php if (!$panel_should_open) : ?>tabindex="-1"<?php endif; ?>>
                <div class="mj-member-login-component__panel-arrow" aria-hidden="true"></div>
                <div class="mj-member-login-component__panel-inner" role="document">
                    <button type="button" class="mj-member-login-component__close" data-mj-login-close aria-label="<?php esc_attr_e('Fermer le panneau de menu', 'mj-member'); ?>"></button>
                    <div class="mj-member-account-menu__header">
                        <?php if ($panel_title !== '') : ?>
                            <h2 id="<?php echo esc_attr($title_id); ?>" class="mj-member-login-component__title"><?php echo esc_html($panel_title); ?></h2>
                        <?php endif; ?>
                        <?php if ($panel_description !== '') : ?>
                            <p id="<?php echo esc_attr($description_id); ?>" class="mj-member-login-component__description"><?php echo esc_html($panel_description); ?></p>
                        <?php endif; ?>
                    </div>
                    <nav class="mj-member-account-menu__nav" aria-labelledby="<?php echo esc_attr($title_id); ?>">
                        <?php if (!empty($menu_items)) : ?>
                            <ul class="mj-member-login-component__account-list mj-member-account-menu__list">
                                <?php echo mj_member_account_menu_render_modal_items($menu_items, 0, $toggle_context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </ul>
                        <?php else : ?>
                            <p class="mj-member-account-menu__empty"><?php esc_html_e('Sélectionnez un menu WordPress dans les options du widget.', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('mj_member_render_account_menu_mobile_component')) {
    function mj_member_render_account_menu_mobile_component(array $args = array()) {
        $args['layout'] = 'modal';
        return mj_member_render_account_menu_component($args);
    }
}
