<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_normalize_widget_debug_zone_list')) {
    function mj_member_normalize_widget_debug_zone_list($value): array {
        if (!is_array($value)) {
            return array();
        }

        $normalized = array();
        foreach ($value as $zone) {
            $zone_key = sanitize_key((string) $zone);
            if ($zone_key === '') {
                continue;
            }

            $normalized[$zone_key] = $zone_key;
        }

        return array_values($normalized);
    }
}

if (!function_exists('mj_member_get_widget_debug_preferences')) {
    function mj_member_get_widget_debug_preferences(int $user_id = 0): array {
        $user_id = $user_id > 0 ? $user_id : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        if ($user_id <= 0) {
            return array(
                'zones' => array(),
                'adminbar' => array(),
            );
        }

        $zones = get_user_meta($user_id, 'mj_member_debug_widget_zones', true);
        $adminbar = get_user_meta($user_id, 'mj_member_debug_widget_zones_adminbar', true);

        return array(
            'zones' => mj_member_normalize_widget_debug_zone_list($zones),
            'adminbar' => mj_member_normalize_widget_debug_zone_list($adminbar),
        );
    }
}

if (!function_exists('mj_member_save_widget_debug_preferences')) {
    function mj_member_save_widget_debug_preferences(array $zones, array $adminbar_zones, int $user_id = 0): array {
        $user_id = $user_id > 0 ? $user_id : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        if ($user_id <= 0) {
            return mj_member_get_widget_debug_preferences(0);
        }

        $zones = mj_member_normalize_widget_debug_zone_list($zones);
        $adminbar_zones = mj_member_normalize_widget_debug_zone_list($adminbar_zones);

        update_user_meta($user_id, 'mj_member_debug_widget_zones', $zones);
        update_user_meta($user_id, 'mj_member_debug_widget_zones_adminbar', $adminbar_zones);

        return array(
            'zones' => $zones,
            'adminbar' => $adminbar_zones,
        );
    }
}

if (!function_exists('mj_member_is_widget_debug_zone_enabled')) {
    function mj_member_is_widget_debug_zone_enabled(string $zone): bool {
        $preferences = mj_member_get_widget_debug_preferences();

        return in_array(sanitize_key($zone), $preferences['zones'], true);
    }
}

if (!function_exists('mj_member_is_widget_debug_zone_adminbar_enabled')) {
    function mj_member_is_widget_debug_zone_adminbar_enabled(string $zone): bool {
        $preferences = mj_member_get_widget_debug_preferences();

        return in_array(sanitize_key($zone), $preferences['adminbar'], true);
    }
}

if (!function_exists('mj_member_get_widget_debug_zone_label')) {
    function mj_member_get_widget_debug_zone_label(string $zone): string {
        $zone = sanitize_key($zone);

        return match ($zone) {
            'elementor' => __('Widget Elementor', 'mj-member'),
            'mj-member' => __('Widget mj-member', 'mj-member'),
            'supertool' => __('Widget supertool', 'mj-member'),
            default => $zone,
        };
    }
}

if (!function_exists('mj_member_render_widget_debug_badge')) {
    function mj_member_render_widget_debug_badge(string $widget_name, string $widget_slug, string $zone_label, string $zone_key): void {
        if (!mj_member_is_widget_debug_zone_enabled($zone_key)) {
            return;
        }

        $badge_class = 'mj-widget-debug-badge mj-widget-debug-badge--' . sanitize_key($zone_key);

        echo '<div class="' . esc_attr($badge_class) . '" data-widget-zone="' . esc_attr($zone_key) . '" data-widget-slug="' . esc_attr($widget_slug) . '" style="display:inline-flex; flex-direction:column; gap:2px; align-items:flex-start; margin:0 0 8px; padding:6px 10px; border-radius:0; color:#fff; font-size:11px; line-height:1.25; box-shadow:none;">';
        echo '<div style="display:flex; align-items:center; gap:8px; width:100%;">';
        echo '<strong style="font-size:11px; font-weight:700;">' . esc_html($widget_name) . '</strong>';
        echo '<button type="button" class="mj-widget-debug-badge-remove" data-widget-zone="' . esc_attr($zone_key) . '" aria-label="' . esc_attr__('Retirer le badge debug', 'mj-member') . '" style="margin-left:auto; border:0; background:transparent; color:#fff; font-size:14px; line-height:1; cursor:pointer; padding:0; opacity:0.9;">×</button>';
        echo '</div>';
        echo '<span style="opacity:0.82;">' . esc_html($widget_slug) . '</span>';
        echo '</div>';
    }
}

if (!function_exists('mj_member_apply_widget_debug_frame')) {
    function mj_member_apply_widget_debug_frame($widget, string $zone_key): void {
        if (!mj_member_is_widget_debug_zone_enabled($zone_key)) {
            return;
        }

        if (!is_object($widget) || !method_exists($widget, 'add_render_attribute')) {
            return;
        }

        $widget->add_render_attribute('_wrapper', 'class', 'mj-widget-debug-frame');
        $widget->add_render_attribute('_wrapper', 'data-widget-debug-zone', $zone_key);
    }
}

if (!function_exists('mj_member_register_widget_debug_hooks')) {
    function mj_member_register_widget_debug_hooks(): void {
        add_action('elementor/frontend/widget/before_render', static function ($widget) {
            if (is_admin()) {
                return;
            }

            if (!is_object($widget) || !method_exists($widget, 'get_name') || !method_exists($widget, 'get_title')) {
                return;
            }

            $widget_name = (string) $widget->get_title();
            $widget_slug = sanitize_key((string) $widget->get_name());
            if ($widget_slug === '') {
                return;
            }

            if (str_starts_with($widget_slug, 'mj-member-')) {
                mj_member_apply_widget_debug_frame($widget, 'mj-member');
                mj_member_render_widget_debug_badge($widget_name, $widget_slug, mj_member_get_widget_debug_zone_label('mj-member'), 'mj-member');
                return;
            }

            if (str_starts_with($widget_slug, 'supertool')) {
                mj_member_apply_widget_debug_frame($widget, 'supertool');
                mj_member_render_widget_debug_badge($widget_name, $widget_slug, mj_member_get_widget_debug_zone_label('supertool'), 'supertool');
                return;
            }

            mj_member_apply_widget_debug_frame($widget, 'elementor');
            mj_member_render_widget_debug_badge($widget_name, $widget_slug, mj_member_get_widget_debug_zone_label('elementor'), 'elementor');
        }, 10, 1);

        add_action('admin_bar_menu', static function ($wp_admin_bar) {
            if (!is_user_logged_in() || !is_admin_bar_showing() || !current_user_can(Config::capability())) {
                return;
            }

            $preferences = mj_member_get_widget_debug_preferences();
            $adminbar_zones = isset($preferences['adminbar']) && is_array($preferences['adminbar']) ? $preferences['adminbar'] : array();
            if (empty($adminbar_zones)) {
                return;
            }

            $wp_admin_bar->add_node(array(
                'id' => 'mj-member-widget-debug',
                'title' => '🛠️ Debug widgets',
                'meta' => array(
                    'class' => 'mj-member-widget-debug-menu',
                    'title' => __('Debug des widgets du front', 'mj-member'),
                ),
            ));

            foreach ($adminbar_zones as $zone_key) {
                $zone_key = sanitize_key((string) $zone_key);
                if ($zone_key === '') {
                    continue;
                }

                $is_enabled = in_array($zone_key, $preferences['zones'], true);
                $title = ($is_enabled ? '☑ ' : '☐ ') . mj_member_get_widget_debug_zone_label($zone_key);
                $wp_admin_bar->add_node(array(
                    'id' => 'mj-member-widget-debug-' . $zone_key,
                    'parent' => 'mj-member-widget-debug',
                    'title' => esc_html($title),
                    'href' => '#',
                    'meta' => array(
                        'class' => 'mj-member-widget-debug-toggle',
                        'title' => esc_attr__('Basculer l’affichage du badge sur le front', 'mj-member'),
                    ),
                ));
            }
        }, 999);

        add_action('wp_ajax_mj_member_widget_debug_toggle', 'mj_member_ajax_widget_debug_toggle');
        add_action('wp_head', 'mj_member_widget_debug_admin_bar_assets');
        add_action('admin_head', 'mj_member_widget_debug_admin_bar_assets');
    }
}

mj_member_register_widget_debug_hooks();

if (!function_exists('mj_member_ajax_widget_debug_toggle')) {
    function mj_member_ajax_widget_debug_toggle(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 401);
        }

        check_ajax_referer('mj_member_widget_debug_toggle', 'nonce');

        $zone = isset($_POST['zone']) ? sanitize_key(wp_unslash((string) $_POST['zone'])) : '';
        if (!in_array($zone, array('elementor', 'mj-member', 'supertool'), true)) {
            wp_send_json_error(array('message' => __('Zone debug invalide.', 'mj-member')), 400);
        }

        $preferences = mj_member_get_widget_debug_preferences();
        $zones = isset($preferences['zones']) && is_array($preferences['zones']) ? $preferences['zones'] : array();
        $adminbar_zones = isset($preferences['adminbar']) && is_array($preferences['adminbar']) ? $preferences['adminbar'] : array();

        if (in_array($zone, $zones, true)) {
            $zones = array_values(array_diff($zones, array($zone)));
        } else {
            $zones[] = $zone;
        }

        if (!in_array($zone, $adminbar_zones, true)) {
            $adminbar_zones[] = $zone;
        }

        $updated = mj_member_save_widget_debug_preferences($zones, $adminbar_zones);

        wp_send_json_success(array(
            'zones' => $updated['zones'],
            'adminbar' => $updated['adminbar'],
            'enabled' => in_array($zone, $updated['zones'], true),
            'zone' => $zone,
        ));
    }
}

if (!function_exists('mj_member_widget_debug_admin_bar_assets')) {
    function mj_member_widget_debug_admin_bar_assets(): void {
        if (!is_user_logged_in() || !is_admin_bar_showing() || !current_user_can(Config::capability())) {
            return;
        }

        $nonce = wp_create_nonce('mj_member_widget_debug_toggle');
        $ajax_url = admin_url('admin-ajax.php');
        echo '<style>
#wpadminbar .mj-member-widget-debug-menu > .ab-item { font-weight: 600; }
#wpadminbar .mj-member-widget-debug-toggle .ab-item { white-space: nowrap; }
#wpadminbar .mj-widget-debug-badge--elementor { background: rgba(37, 99, 235, 0.18) !important; }
#wpadminbar .mj-widget-debug-badge--mj-member { background: rgba(5, 150, 105, 0.18) !important; }
#wpadminbar .mj-widget-debug-badge--supertool { background: rgba(217, 119, 6, 0.18) !important; }
#wpadminbar .mj-widget-debug-frame {
    border: 1px dashed rgba(15, 23, 42, 0.45);
    border-radius: 0 !important;
    background: rgba(15, 23, 42, 0.02);
    box-sizing: border-box;
}
#wpadminbar .mj-widget-debug-badge {
    border-radius: 0 !important;
    background: rgba(15, 23, 42, 0.08) !important;
    box-shadow: none !important;
}
#wpadminbar .mj-widget-debug-badge-remove {
    appearance: none;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font: inherit;
    line-height: 1;
    padding: 0;
}
@media screen and (max-width: 782px) {
    #wpadminbar li#wp-admin-bar-mj-member-widget-debug { display: list-item !important; }
}
</style>';
        echo '<script>(function(){
            var ajaxUrl = ' . wp_json_encode($ajax_url) . ';
            var nonce = ' . wp_json_encode($nonce) . ';

            function sendToggle(zone) {
                var body = new URLSearchParams();
                body.set("action", "mj_member_widget_debug_toggle");
                body.set("nonce", nonce);
                body.set("zone", zone);

                return fetch(ajaxUrl, {
                    method: "POST",
                    credentials: "same-origin",
                    body: body
                }).then(function(response) {
                    return response.json();
                });
            }

            document.addEventListener("click", function(event) {
                var badgeButton = event.target && event.target.closest ? event.target.closest(".mj-widget-debug-badge-remove") : null;
                var link = event.target && event.target.closest ? event.target.closest("#wpadminbar .mj-member-widget-debug-toggle > a") : null;
                var target = badgeButton || link;
                if (!target) {
                    return;
                }

                var zone = "";
                if (badgeButton) {
                    zone = badgeButton.getAttribute("data-widget-zone") || "";
                } else {
                    var parent = link.parentElement;
                    var id = parent ? parent.id : "";
                    zone = id.replace("wp-admin-bar-mj-member-widget-debug-", "");
                }

                if (!link) {
                    // keep going for badge button clicks
                }

                event.preventDefault();
                if (!zone) {
                    return;
                }

                target.setAttribute("aria-busy", "true");
                sendToggle(zone).then(function(result) {
                    if (!result || !result.success) {
                        throw new Error((result && result.data && result.data.message) ? result.data.message : "Erreur");
                    }

                    window.location.reload();
                }).catch(function(error) {
                    window.alert(error && error.message ? error.message : "Erreur lors de la mise à jour.");
                }).finally(function() {
                    target.removeAttribute("aria-busy");
                });
            }, true);
        })();</script>';
    }
}

if (!function_exists('mj_member_get_elementor_widget_custom_titles')) {
    /**
     * Return custom widget titles saved from plugin settings.
     *
     * @return array<string, string>
     */
    function mj_member_get_elementor_widget_custom_titles() {
        $saved_titles = get_option('mj_member_widget_titles', array());
        if (!is_array($saved_titles)) {
            return array();
        }

        $normalized = array();
        foreach ($saved_titles as $slug => $title) {
            $safe_slug = sanitize_key((string) $slug);
            if ($safe_slug === '') {
                continue;
            }

            $safe_title = sanitize_text_field((string) $title);
            if ($safe_title === '') {
                continue;
            }

            $normalized[$safe_slug] = $safe_title;
        }

        return $normalized;
    }
}

if (!function_exists('mj_member_get_elementor_widget_custom_title')) {
    /**
     * Return the custom title for a widget slug when available.
     *
     * @param string $widget_slug
     * @param string $fallback_title
     * @return string
     */
    function mj_member_get_elementor_widget_custom_title($widget_slug, $fallback_title = '') {
        $slug = sanitize_key((string) $widget_slug);
        if ($slug === '') {
            return (string) $fallback_title;
        }

        $titles = mj_member_get_elementor_widget_custom_titles();
        if (isset($titles[$slug]) && $titles[$slug] !== '') {
            return $titles[$slug];
        }

        return (string) $fallback_title;
    }
}

if (!function_exists('mj_member_get_elementor_widgets_catalog')) {
    /**
     * Build metadata for all Elementor widgets shipped with the plugin.
     *
     * @return array<int, array<string, mixed>>
     */
    function mj_member_get_elementor_widgets_catalog() {
        if (!function_exists('mj_member_get_elementor_widgets_map')) {
            return array();
        }

        $widgets_map = mj_member_get_elementor_widgets_map();
        if (!is_array($widgets_map) || empty($widgets_map)) {
            return array();
        }

        $elementor_available = class_exists('\\Elementor\\Widget_Base');
        $catalog = array();

        foreach ($widgets_map as $class_name => $relative_path) {
            $record = array(
                'class' => $class_name,
                'path' => $relative_path,
                'loaded' => false,
                'title' => $class_name,
                'slug' => '',
                'categories' => array(),
                'keywords' => array(),
                'icon' => '',
                'scripts' => array(),
                'styles' => array(),
                'help_url' => '',
                'description' => '',
                'description_parts' => array(),
                'error' => '',
            );

            if (function_exists('mj_member_load_elementor_widget_class')) {
                $record['loaded'] = mj_member_load_elementor_widget_class($class_name, $relative_path);
            } else {
                $record['loaded'] = class_exists($class_name, false);
                if (!$record['loaded']) {
                    $absolute_path = rtrim(Config::path(), '/\\') . '/' . ltrim($relative_path, '/\\');
                    if (is_readable($absolute_path)) {
                        require_once $absolute_path;
                        $record['loaded'] = class_exists($class_name, false);
                    }
                }
            }

            if ($elementor_available && $record['loaded'] && class_exists($class_name)) {
                try {
                    $instance = new $class_name();

                    if (method_exists($instance, 'get_title')) {
                        $record['title'] = (string) $instance->get_title();
                    }

                    if (method_exists($instance, 'get_name')) {
                        $record['slug'] = (string) $instance->get_name();
                    }

                    if (!empty($record['slug'])) {
                        $record['title'] = mj_member_get_elementor_widget_custom_title($record['slug'], (string) $record['title']);
                    }

                    if (method_exists($instance, 'get_categories')) {
                        $categories = $instance->get_categories();
                        if (is_array($categories)) {
                            $record['categories'] = array_values(array_unique(array_filter(array_map('strval', $categories))));
                        }
                    }

                    if (method_exists($instance, 'get_keywords')) {
                        $keywords = $instance->get_keywords();
                        if (is_array($keywords)) {
                            $record['keywords'] = array_values(array_unique(array_filter(array_map('strval', $keywords))));
                        }
                    }

                    if (method_exists($instance, 'get_icon')) {
                        $icon_value = $instance->get_icon();
                        if (is_string($icon_value)) {
                            $record['icon'] = trim($icon_value);
                        }
                    }

                    if (method_exists($instance, 'get_script_depends')) {
                        $scripts = $instance->get_script_depends();
                        if (is_array($scripts)) {
                            $record['scripts'] = array_values(array_unique(array_filter(array_map('strval', $scripts))));
                        }
                    }

                    if (method_exists($instance, 'get_style_depends')) {
                        $styles = $instance->get_style_depends();
                        if (is_array($styles)) {
                            $record['styles'] = array_values(array_unique(array_filter(array_map('strval', $styles))));
                        }
                    }

                    if (method_exists($instance, 'get_help_url')) {
                        $help_url = $instance->get_help_url();
                        if (is_string($help_url)) {
                            $record['help_url'] = trim($help_url);
                        }
                    }
                } catch (\Throwable $throwable) {
                    $record['error'] = $throwable->getMessage();
                }
            }

            $description_parts = mj_member_get_elementor_widget_description_parts($record);
            $record['description_parts'] = $description_parts;
            $record['description'] = trim(implode(' ', $description_parts));

            $catalog[] = $record;
        }

        usort($catalog, static function ($left, $right) {
            $leftTitle = isset($left['title']) ? (string) $left['title'] : '';
            $rightTitle = isset($right['title']) ? (string) $right['title'] : '';

            $comparison = strcasecmp($leftTitle, $rightTitle);
            if ($comparison !== 0) {
                return $comparison;
            }

            return strcasecmp((string) $left['class'], (string) $right['class']);
        });

        return $catalog;
    }
}

if (!function_exists('mj_member_get_elementor_widgets_overview')) {
    /**
     * Provide a small summary derived from the catalog.
     *
     * @return array<string, int>
     */
    function mj_member_get_elementor_widgets_overview() {
        $catalog = mj_member_get_elementor_widgets_catalog();
        if (empty($catalog)) {
            return array(
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'with_errors' => 0,
            );
        }

        $active = 0;
        $inactive = 0;
        $with_errors = 0;

        foreach ($catalog as $record) {
            $is_loaded = !empty($record['loaded']);
            if ($is_loaded) {
                $active++;
                if (!empty($record['error'])) {
                    $with_errors++;
                }
                continue;
            }

            $inactive++;
        }

        return array(
            'total' => count($catalog),
            'active' => $active,
            'inactive' => $inactive,
            'with_errors' => $with_errors,
        );
    }
}

if (!function_exists('mj_member_get_elementor_widget_description_parts')) {
    /**
     * Create a descriptive summary for a widget entry.
     *
     * @param array<string, mixed> $record
     * @return array<int, string>
     */
    function mj_member_get_elementor_widget_description_parts(array $record) {
        $parts = array();

        $is_loaded = !empty($record['loaded']);
        $error_message = isset($record['error']) ? $record['error'] : '';

        if (!$is_loaded) {
            $parts[] = __('Classe non chargée. Vérifiez le fichier du widget.', 'mj-member');
        }

        if ($error_message !== '') {
            $clean_error = wp_strip_all_tags($error_message);
            if (function_exists('mb_strlen') && mb_strlen($clean_error, 'UTF-8') > 160) {
                $clean_error = mb_substr($clean_error, 0, 157, 'UTF-8') . '...';
            } elseif (strlen($clean_error) > 160) {
                $clean_error = substr($clean_error, 0, 157) . '...';
            }
            if ($clean_error !== '') {
                $parts[] = sprintf(
                    __('Dernière erreur : %s', 'mj-member'),
                    $clean_error
                );
            }
        }

        if (!empty($record['slug'])) {
            $parts[] = sprintf(
                __('Identifiant Elementor : %s', 'mj-member'),
                (string) $record['slug']
            );
        }

        if (!empty($record['categories'])) {
            $parts[] = sprintf(
                __('Catégories : %s', 'mj-member'),
                implode(', ', array_map('strval', $record['categories']))
            );
        }

        if (!empty($record['keywords'])) {
            $parts[] = sprintf(
                __('Mots-clés : %s', 'mj-member'),
                implode(', ', array_map('strval', $record['keywords']))
            );
        }

        if (!empty($record['scripts'])) {
            $scripts = implode(', ', array_map('strval', $record['scripts']));
            $parts[] = sprintf(
                _n(
                    'Script requis : %s',
                    'Scripts requis : %s',
                    count($record['scripts']),
                    'mj-member'
                ),
                $scripts
            );
        }

        if (!empty($record['styles'])) {
            $styles = implode(', ', array_map('strval', $record['styles']));
            $parts[] = sprintf(
                _n(
                    'Style requis : %s',
                    'Styles requis : %s',
                    count($record['styles']),
                    'mj-member'
                ),
                $styles
            );
        }

        if (!empty($record['help_url'])) {
            $parts[] = sprintf(
                __('Documentation : %s', 'mj-member'),
                (string) $record['help_url']
            );
        }

        if (empty($parts)) {
            $parts[] = __('Aucune information supplémentaire disponible.', 'mj-member');
        }

        return array_values(array_filter(array_map('trim', $parts)));
    }
}

if (!function_exists('mj_member_extend_elementor_horizontal_position_controls')) {
    /**
     * Add a center option to Elementor horizontal fixed/absolute positioning controls.
     *
     * @param \Elementor\Controls_Stack $element
     * @param string                     $position_control_name
     * @return void
     */
    function mj_member_extend_elementor_horizontal_position_controls($element, $position_control_name = '_position') {
        if (!is_object($element) || !method_exists($element, 'update_control') || !method_exists($element, 'add_responsive_control')) {
            return;
        }

        $left = esc_html__('Left', 'elementor');
        $right = esc_html__('Right', 'elementor');
        $start = is_rtl() ? $right : $left;
        $end = !is_rtl() ? $right : $left;
        $position_condition_key = $position_control_name . '!';

        $element->update_control(
            '_offset_orientation_h',
            array(
                'options' => array(
                    'start' => array(
                        'title' => $start,
                        'icon' => 'eicon-h-align-left',
                    ),
                    'center' => array(
                        'title' => esc_html__('Center', 'elementor'),
                        'icon' => 'eicon-h-align-center',
                    ),
                    'end' => array(
                        'title' => $end,
                        'icon' => 'eicon-h-align-right',
                    ),
                ),
                'prefix_class' => 'mj-member-offset-h-',
            )
        );

        $element->update_control(
            '_offset_x',
            array(
                'condition' => array(
                    '_offset_orientation_h!' => array('end', 'center'),
                    $position_condition_key => '',
                ),
            )
        );

        $element->update_control(
            '_offset_x_end',
            array(
                'condition' => array(
                    '_offset_orientation_h' => 'end',
                    $position_condition_key => '',
                ),
            )
        );

        $element->add_responsive_control(
            '_offset_x_center',
            array(
                'label' => esc_html__('Offset', 'elementor'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => -1000,
                        'max' => 1000,
                    ),
                    '%' => array(
                        'min' => -200,
                        'max' => 200,
                    ),
                    'vw' => array(
                        'min' => -200,
                        'max' => 200,
                    ),
                    'vh' => array(
                        'min' => -200,
                        'max' => 200,
                    ),
                ),
                'default' => array(
                    'size' => 0,
                ),
                'size_units' => array('px', '%', 'em', 'rem', 'vw', 'vh', 'custom'),
                'selectors' => array(
                    '{{WRAPPER}}' => '--mj-member-offset-x-center: {{SIZE}}{{UNIT}};',
                ),
                'condition' => array(
                    '_offset_orientation_h' => 'center',
                    $position_condition_key => '',
                ),
            ),
            array(
                'position' => array(
                    'at' => 'after',
                    'of' => '_offset_x_end',
                ),
            )
        );
    }
}

if (!function_exists('mj_member_register_elementor_horizontal_center_control')) {
    /**
     * Register the injected center option for widgets and containers.
     *
     * @return void
     */
    function mj_member_register_elementor_horizontal_center_control() {
        add_action(
            'elementor/element/common/_section_style/before_section_end',
            static function ($element) {
                mj_member_extend_elementor_horizontal_position_controls($element, '_position');
            },
            20
        );

        add_action(
            'elementor/element/container/section_layout/before_section_end',
            static function ($element) {
                mj_member_extend_elementor_horizontal_position_controls($element, 'position');
            },
            20
        );
    }
}

if (!function_exists('mj_member_apply_elementor_horizontal_center_class')) {
    /**
     * Apply an active helper class only when centered custom positioning is enabled.
     *
     * @param object $element
     * @return void
     */
    function mj_member_apply_elementor_horizontal_center_class($element) {
        if (!is_object($element) || !method_exists($element, 'get_settings') || !method_exists($element, 'add_render_attribute')) {
            return;
        }

        $settings = $element->get_settings();
        if (!is_array($settings)) {
            return;
        }

        $position = '';
        if (isset($settings['_position'])) {
            $position = (string) $settings['_position'];
        } elseif (isset($settings['position'])) {
            $position = (string) $settings['position'];
        }

        if (!in_array($position, array('absolute', 'fixed'), true)) {
            return;
        }

        if (!isset($settings['_offset_orientation_h']) || $settings['_offset_orientation_h'] !== 'center') {
            return;
        }

        $element->add_render_attribute('_wrapper', 'class', 'mj-member-offset-h-center-active');
    }
}

if (!function_exists('mj_member_enqueue_elementor_horizontal_center_styles')) {
    /**
     * Enqueue shared CSS for the centered horizontal fixed/absolute position helper.
     *
     * @return void
     */
    function mj_member_enqueue_elementor_horizontal_center_styles() {
        $css = '.mj-member-offset-h-center.mj-member-offset-h-center-active{' .
            'left:calc(50% + var(--mj-member-offset-x-center, 0px)) !important;' .
            'right:auto !important;' .
            'transform:translateX(-50%) !important;' .
        '}';

        wp_add_inline_style('elementor-frontend', $css);
    }
}

add_action('elementor/init', 'mj_member_register_elementor_horizontal_center_control');
add_action('elementor/frontend/before_render', 'mj_member_apply_elementor_horizontal_center_class');
add_action('wp_enqueue_scripts', 'mj_member_enqueue_elementor_horizontal_center_styles', 50);
