<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_account_menu_icon_meta_key')) {
    function mj_member_account_menu_icon_meta_key() {
        return '_mj_member_menu_icon_id';
    }
}

if (!function_exists('mj_member_account_menu_sanitize_icon_payload')) {
    function mj_member_account_menu_sanitize_icon_payload($payload) {
        $defaults = array(
            'id' => 0,
            'url' => '',
            'preview_url' => '',
            'alt' => '',
            'html' => '',
            'type' => '',
        );

        if (!is_array($payload)) {
            return $defaults;
        }

        $allowed_tags = function_exists('mj_member_login_component_allowed_icon_tags')
            ? mj_member_login_component_allowed_icon_tags()
            : array(
                'i' => array(
                    'class' => true,
                    'aria-hidden' => true,
                    'data-icon' => true,
                    'data-prefix' => true,
                    'data-fa-i2svg' => true,
                ),
                'span' => array(
                    'class' => true,
                    'aria-hidden' => true,
                ),
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
                'svg' => array(
                    'class' => true,
                    'aria-hidden' => true,
                    'xmlns' => true,
                    'xmlns:xlink' => true,
                    'width' => true,
                    'height' => true,
                    'viewBox' => true,
                    'role' => true,
                    'focusable' => true,
                    'fill' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                    'stroke-linecap' => true,
                    'stroke-linejoin' => true,
                    'preserveAspectRatio' => true,
                ),
                'path' => array(
                    'd' => true,
                    'fill' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                    'stroke-linecap' => true,
                    'stroke-linejoin' => true,
                ),
                'polygon' => array(
                    'points' => true,
                    'fill' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                    'stroke-linecap' => true,
                    'stroke-linejoin' => true,
                ),
                'polyline' => array(
                    'points' => true,
                    'fill' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                    'stroke-linecap' => true,
                    'stroke-linejoin' => true,
                ),
                'line' => array(
                    'x1' => true,
                    'x2' => true,
                    'y1' => true,
                    'y2' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                    'stroke-linecap' => true,
                    'stroke-linejoin' => true,
                ),
                'circle' => array(
                    'cx' => true,
                    'cy' => true,
                    'r' => true,
                    'fill' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                ),
                'ellipse' => array(
                    'cx' => true,
                    'cy' => true,
                    'rx' => true,
                    'ry' => true,
                    'fill' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                ),
                'rect' => array(
                    'x' => true,
                    'y' => true,
                    'width' => true,
                    'height' => true,
                    'fill' => true,
                    'stroke' => true,
                    'stroke-width' => true,
                    'stroke-linecap' => true,
                    'stroke-linejoin' => true,
                ),
            );

        $normalized = array(
            'id' => isset($payload['id']) ? (int) $payload['id'] : 0,
            'url' => isset($payload['url']) ? esc_url_raw((string) $payload['url']) : '',
            'preview_url' => isset($payload['preview_url']) ? esc_url_raw((string) $payload['preview_url']) : '',
            'alt' => isset($payload['alt']) ? sanitize_text_field((string) $payload['alt']) : '',
            'html' => '',
            'type' => isset($payload['type']) && $payload['type'] === 'image' ? 'image' : '',
        );

        if (!empty($payload['html']) && is_string($payload['html'])) {
            $normalized['html'] = wp_kses((string) $payload['html'], $allowed_tags);
        }

        if ($normalized['html'] === '') {
            $normalized['type'] = '';
        }

        return $normalized;
    }
}

if (!function_exists('mj_member_account_menu_build_icon_payload_from_attachment')) {
    function mj_member_account_menu_build_icon_payload_from_attachment($attachment_id) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return mj_member_account_menu_sanitize_icon_payload(array());
        }

        $image_url = wp_get_attachment_image_url($attachment_id, 'full');
        if (!is_string($image_url) || $image_url === '') {
            return mj_member_account_menu_sanitize_icon_payload(array());
        }

        $preview_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        if (!is_string($preview_url) || $preview_url === '') {
            $preview_url = $image_url;
        }

        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!is_string($alt_text)) {
            $alt_text = '';
        }
        $alt_text = trim($alt_text);

        $attributes = array(
            'class' => 'mj-member-account-menu__item-icon-image',
            'loading' => 'lazy',
        );

        if ($alt_text !== '') {
            $attributes['alt'] = $alt_text;
        } else {
            $attributes['alt'] = '';
        }

        $icon_html = '';
        if (function_exists('wp_get_attachment_image')) {
            $icon_html = wp_get_attachment_image($attachment_id, 'thumbnail', false, $attributes);
        }

        if (!is_string($icon_html) || trim($icon_html) === '') {
            $icon_html = sprintf(
                '<img src="%1$s" alt="%2$s" class="mj-member-account-menu__item-icon-image" loading="lazy" />',
                esc_url($image_url),
                esc_attr($alt_text)
            );
        }

        return mj_member_account_menu_sanitize_icon_payload(array(
            'id' => $attachment_id,
            'url' => $image_url,
            'preview_url' => $preview_url,
            'alt' => $alt_text,
            'html' => $icon_html,
            'type' => 'image',
        ));
    }
}

if (!function_exists('mj_member_account_menu_get_icon_payload')) {
    function mj_member_account_menu_get_icon_payload($menu_item_id) {
        $menu_item_id = (int) $menu_item_id;
        if ($menu_item_id <= 0) {
            return mj_member_account_menu_sanitize_icon_payload(array());
        }

        $meta_key = mj_member_account_menu_icon_meta_key();
        $attachment_id = (int) get_post_meta($menu_item_id, $meta_key, true);
        if ($attachment_id <= 0) {
            return mj_member_account_menu_sanitize_icon_payload(array());
        }

        return mj_member_account_menu_build_icon_payload_from_attachment($attachment_id);
    }
}

if (!function_exists('mj_member_account_menu_icon_enqueue_assets')) {
    function mj_member_account_menu_icon_enqueue_assets() {
        if (!function_exists('wp_enqueue_media')) {
            return;
        }

        static $localized = false;

        wp_enqueue_media();

        $handle = 'mj-member-menu-icons';
        $src = Config::url() . 'js/admin-menu-icons.js';

        wp_enqueue_script(
            $handle,
            $src,
            array('jquery', 'wp-util', 'media-editor'),
            Config::version(),
            true
        );

        if ($localized) {
            return;
        }

        wp_localize_script(
            $handle,
            'mjMemberMenuIcons',
            array(
                'choose' => __('Choisir une image', 'mj-member'),
                'replace' => __('Remplacer l’image', 'mj-member'),
                'remove' => __('Retirer l’image', 'mj-member'),
                'modalTitle' => __('Sélectionner une icône', 'mj-member'),
                'modalButton' => __('Utiliser cette image', 'mj-member'),
                'placeholder' => __('Aucune image', 'mj-member'),
            )
        );

        $localized = true;
    }
}

if (is_admin()) {
    add_filter('manage_nav-menus_columns', 'mj_member_account_menu_icon_add_column', 20);
    add_action('wp_nav_menu_item_custom_fields', 'mj_member_account_menu_icon_render_field', 10, 5);
    add_action('wp_update_nav_menu_item', 'mj_member_account_menu_icon_save', 10, 3);
    add_action('admin_enqueue_scripts', 'mj_member_account_menu_icon_admin_assets');
}

if (!function_exists('mj_member_account_menu_icon_add_column')) {
    function mj_member_account_menu_icon_add_column($columns) {
        if (!is_array($columns)) {
            $columns = array();
        }

        $columns['mj_member_menu_icon'] = __('Icône MJ Member', 'mj-member');

        return $columns;
    }
}

if (!function_exists('mj_member_account_menu_icon_render_field')) {
    function mj_member_account_menu_icon_render_field($item_id, $item, $depth, $args, $id) {
        $meta_key = mj_member_account_menu_icon_meta_key();
        $icon_id = (int) get_post_meta($item_id, $meta_key, true);
        $payload = $icon_id > 0 ? mj_member_account_menu_get_icon_payload($item_id) : array();

        $preview_url = '';
        $preview_markup = '<span class="mj-member-menu-icon-placeholder">' . esc_html__('Aucune image', 'mj-member') . '</span>';

        if (!empty($payload) && !empty($payload['preview_url'])) {
            $preview_url = $payload['preview_url'];
            $preview_markup = sprintf(
                '<img src="%1$s" alt="" class="mj-member-menu-icon-preview-image" />',
                esc_url($preview_url)
            );
        }

        $field_id = 'mj-member-menu-icon-' . $item_id;
        ?>
        <div class="mj-member-menu-icon-field description-wide">
            <span class="field-label"><?php esc_html_e('Icône MJ Member', 'mj-member'); ?></span>
            <div class="mj-member-menu-icon-control" data-mj-member-menu-icon>
                <div class="mj-member-menu-icon-preview" data-image-url="<?php echo esc_attr($preview_url); ?>">
                    <?php echo $preview_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="mj-member-menu-icon-actions">
                    <input type="hidden" id="<?php echo esc_attr($field_id); ?>" class="mj-member-menu-icon-input" name="mj_member_menu_icon_id[<?php echo esc_attr($item_id); ?>]" value="<?php echo esc_attr((string) $icon_id); ?>" />
                    <button type="button" class="button mj-member-menu-icon-select"><?php esc_html_e('Sélectionner une image', 'mj-member'); ?></button>
                    <button type="button" class="button-link-delete mj-member-menu-icon-remove"<?php echo $icon_id > 0 ? '' : ' style="display:none;"'; ?>><?php esc_html_e('Retirer', 'mj-member'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('mj_member_account_menu_icon_save')) {
    function mj_member_account_menu_icon_save($menu_id, $menu_item_db_id, $args) {
        if (!current_user_can('edit_theme_options')) {
            return;
        }

        $meta_key = mj_member_account_menu_icon_meta_key();
        $value = 0;

        if (isset($_POST['mj_member_menu_icon_id']) && is_array($_POST['mj_member_menu_icon_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $items = wp_unslash($_POST['mj_member_menu_icon_id']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if (isset($items[$menu_item_db_id])) {
                $value = (int) $items[$menu_item_db_id];
            }
        }

        if ($value > 0) {
            update_post_meta($menu_item_db_id, $meta_key, $value);
        } else {
            delete_post_meta($menu_item_db_id, $meta_key);
        }
    }
}

if (!function_exists('mj_member_account_menu_icon_admin_assets')) {
    function mj_member_account_menu_icon_admin_assets($hook) {
        $allowed_hooks = array('nav-menus.php');

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        mj_member_account_menu_icon_enqueue_assets();
    }
}
