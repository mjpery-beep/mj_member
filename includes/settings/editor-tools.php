<?php

if (!defined('ABSPATH')) {
    exit;
}

// Register TinyMCE table plugin from CDN.
add_filter('mce_external_plugins', 'mj_member_add_tinymce_table_plugin');
function mj_member_add_tinymce_table_plugin($plugins) {
    $plugins['table'] = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/plugins/table/plugin.min.js';
    $plugins['code'] = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/plugins/code/plugin.min.js';
    return $plugins;
}

// Add lineheight button to TinyMCE (only on MJ settings page).
add_action('admin_print_footer_scripts', 'mj_member_tinymce_lineheight_inline_plugin', 99);
function mj_member_tinymce_lineheight_inline_plugin() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'mj_settings') === false) {
        return;
    }
    ?>
    <script type="text/javascript">
    (function() {
        if (typeof tinymce !== 'undefined' && !tinymce.PluginManager.get('lineheight')) {
            tinymce.PluginManager.add('lineheight', function(editor) {
                var lineHeights = ['1', '1.2', '1.4', '1.5', '1.6', '1.8', '2', '2.5', '3'];
                var menuItems = lineHeights.map(function(lh) {
                    return {
                        text: lh,
                        onclick: function() {
                            editor.formatter.toggle('lineheight', { value: lh });
                        }
                    };
                });
                editor.addButton('lineheightselect', {
                    type: 'menubutton',
                    text: 'Interligne',
                    icon: false,
                    menu: menuItems
                });
                editor.on('init', function() {
                    editor.formatter.register('lineheight', {
                        selector: 'p,h1,h2,h3,h4,h5,h6,td,th,li,div,span',
                        styles: { 'line-height': '%value' }
                    });
                });
            });
        }
    })();
    </script>
    <?php
}

/**
 * Render basic Markdown to safe HTML for admin help panels.
 * Supports headings, paragraphs, links, bold, inline code, ordered/unordered lists and fenced code blocks.
 */
function mj_member_render_markdown_help($markdown) {
    if (!is_string($markdown) || trim($markdown) === '') {
        return '';
    }

    $normalize_inline = static function ($text) {
        $escaped = esc_html((string) $text);

        // Inline code first so markdown markers inside code are preserved.
        $escaped = preg_replace_callback('/`([^`]+)`/', static function ($matches) {
            return '<code>' . $matches[1] . '</code>';
        }, $escaped);

        $escaped = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/i', static function ($matches) {
            $label = $matches[1];
            $url = esc_url($matches[2]);
            if ($url === '') {
                return $label;
            }
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        }, $escaped);

        $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped);

        return $escaped;
    };

    $markdown = str_replace(array("\r\n", "\r"), "\n", $markdown);
    $lines = explode("\n", $markdown);

    $html = array();
    $paragraph = array();
    $in_ul = false;
    $in_ol = false;
    $in_code = false;
    $code_lines = array();

    $flush_paragraph = static function () use (&$html, &$paragraph, $normalize_inline) {
        if (empty($paragraph)) {
            return;
        }
        $text = trim(implode(' ', $paragraph));
        if ($text !== '') {
            $html[] = '<p>' . $normalize_inline($text) . '</p>';
        }
        $paragraph = array();
    };

    $close_lists = static function () use (&$html, &$in_ul, &$in_ol) {
        if ($in_ul) {
            $html[] = '</ul>';
            $in_ul = false;
        }
        if ($in_ol) {
            $html[] = '</ol>';
            $in_ol = false;
        }
    };

    foreach ($lines as $raw_line) {
        $line = rtrim((string) $raw_line);

        if (preg_match('/^```/', trim($line))) {
            if ($in_code) {
                $html[] = '<pre><code>' . esc_html(implode("\n", $code_lines)) . '</code></pre>';
                $code_lines = array();
                $in_code = false;
            } else {
                $flush_paragraph();
                $close_lists();
                $in_code = true;
            }
            continue;
        }

        if ($in_code) {
            $code_lines[] = $raw_line;
            continue;
        }

        if (trim($line) === '') {
            $flush_paragraph();
            $close_lists();
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $heading_matches)) {
            $flush_paragraph();
            $close_lists();
            $level = strlen($heading_matches[1]);
            $text = $normalize_inline($heading_matches[2]);
            $html[] = '<h' . $level . '>' . $text . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^[-*+]\s+(.+)$/', $line, $ul_matches)) {
            $flush_paragraph();
            if ($in_ol) {
                $html[] = '</ol>';
                $in_ol = false;
            }
            if (!$in_ul) {
                $html[] = '<ul>';
                $in_ul = true;
            }
            $html[] = '<li>' . $normalize_inline($ul_matches[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $line, $ol_matches)) {
            $flush_paragraph();
            if ($in_ul) {
                $html[] = '</ul>';
                $in_ul = false;
            }
            if (!$in_ol) {
                $html[] = '<ol>';
                $in_ol = true;
            }
            $html[] = '<li>' . $normalize_inline($ol_matches[1]) . '</li>';
            continue;
        }

        $close_lists();
        $paragraph[] = $line;
    }

    if ($in_code) {
        $html[] = '<pre><code>' . esc_html(implode("\n", $code_lines)) . '</code></pre>';
    }

    $flush_paragraph();
    $close_lists();

    $allowed = wp_kses_allowed_html('post');
    $allowed['pre'] = array();
    $allowed['code'] = array();

    return wp_kses(implode("\n", $html), $allowed);
}
