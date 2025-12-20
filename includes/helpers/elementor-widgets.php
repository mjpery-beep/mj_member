<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
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
