<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjFixturesManager
{
    public const OPTION_CREATE_SELECTED = 'mj_fixtures_create_selected_sources';
    public const OPTION_RESTORE_SELECTED = 'mj_fixtures_restore_selected_sources';
    public const OPTION_RESTORE_CLEAN = 'mj_fixtures_restore_clean_before_sources';
    public const OPTION_USE_ON_INSTALL = 'mj_fixtures_use_on_install_sources';
    public const OPTION_IMAGE_IMPORT_SETTINGS = 'mj_fixtures_image_import_settings';
    public const OPTION_CONFIG_SELECTION = 'mj_fixtures_config_selection';

    private const TABLE_SLUGS = array(
        'mj_badges',
        'mj_trophies',
        'mj_levels',
        'mj_action_types',
        'mj_event_locations',
        'mj_members',
    );

    private const TABLE_LABELS = array(
        'mj_badges' => 'Badges',
        'mj_trophies' => 'Trophees',
        'mj_levels' => 'Niveaux',
        'mj_action_types' => 'Actions',
        'mj_event_locations' => 'Lieux',
        'mj_members' => 'Membres',
    );

    private const EXTRA_SOURCE_LABELS = array(
        'wp_pages' => 'Pages WordPress',
        'wp_posts' => 'Articles WordPress',
        'wp_media' => 'Medias WordPress',
        'wp_theme_settings' => 'Reglages theme',
        'supertool_data' => 'Donnees supertool-elementor',
        'config_mj_member' => 'Configuration mj-member',
        'config_supertool' => 'Configuration supertool-elementor',
    );

    private const EXTRA_SOURCE_GROUP = array(
        'wp_pages' => 'wordpress',
        'wp_posts' => 'wordpress',
        'wp_media' => 'wordpress',
        'wp_theme_settings' => 'wordpress',
        'supertool_data' => 'supertool',
        'config_mj_member' => 'configuration',
        'config_supertool' => 'configuration',
    );

    public static function getFixturesDir(): string
    {
        return trailingslashit(Config::path() . 'data/fixtures');
    }

    public static function getManagedTablesStatus(): array
    {
        global $wpdb;

        $status = array();
        foreach (self::TABLE_SLUGS as $slug) {
            $table = $wpdb->prefix . $slug;
            $status[] = array(
                'slug' => $slug,
                'label' => self::TABLE_LABELS[$slug] ?? $slug,
                'table' => $table,
                'exists' => self::tableExists($table),
                'fixture_file' => $slug . '.jsonl',
                'type' => 'table',
                'group' => 'mj-member',
            );
        }

        return $status;
    }

    public static function getAllSourcesStatus(): array
    {
        $items = self::getManagedTablesStatus();

        foreach (self::EXTRA_SOURCE_LABELS as $slug => $label) {
            $items[] = array(
                'slug' => $slug,
                'label' => $label,
                'table' => '',
                'exists' => true,
                'fixture_file' => $slug . '.json',
                'type' => 'source',
                'group' => self::EXTRA_SOURCE_GROUP[$slug] ?? 'other',
            );
        }

        return $items;
    }

    public static function getSavedSelection(string $optionKey, array $default = array()): array
    {
        $raw = get_option($optionKey, array());
        if (!is_array($raw)) {
            $raw = array();
        }

        $clean = self::sanitizeSourceSlugs($raw, false);
        if (empty($clean) && !empty($default)) {
            return self::sanitizeSourceSlugs($default, false);
        }

        return $clean;
    }

    public static function saveSelection(string $optionKey, array $selectedSlugs): void
    {
        update_option($optionKey, self::sanitizeSourceSlugs($selectedSlugs, false));
    }

    public static function getImageImportSettings(): array
    {
        $defaults = array(
            'overwrite_existing' => false,
            'image_quality' => 90,
            'max_file_size_mb' => 10,
        );

        $saved = get_option(self::OPTION_IMAGE_IMPORT_SETTINGS, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        return array(
            'overwrite_existing' => !empty($saved['overwrite_existing']),
            'image_quality' => max(20, min(100, (int) ($saved['image_quality'] ?? $defaults['image_quality']))),
            'max_file_size_mb' => max(1, min(250, (int) ($saved['max_file_size_mb'] ?? $defaults['max_file_size_mb']))),
        );
    }

    public static function saveImageImportSettings(array $settings): void
    {
        update_option(
            self::OPTION_IMAGE_IMPORT_SETTINGS,
            array(
                'overwrite_existing' => !empty($settings['overwrite_existing']),
                'image_quality' => max(20, min(100, (int) ($settings['image_quality'] ?? 90))),
                'max_file_size_mb' => max(1, min(250, (int) ($settings['max_file_size_mb'] ?? 10))),
            )
        );
    }

    public static function getConfigSelection(): array
    {
        $defaults = array(
            'mj_member' => array('mj_*'),
            'supertool' => array('mjet_*', 'elementor_cpt_support'),
        );

        $saved = get_option(self::OPTION_CONFIG_SELECTION, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        $mj = isset($saved['mj_member']) && is_array($saved['mj_member']) ? $saved['mj_member'] : $defaults['mj_member'];
        $st = isset($saved['supertool']) && is_array($saved['supertool']) ? $saved['supertool'] : $defaults['supertool'];

        return array(
            'mj_member' => array_values(array_unique(array_filter(array_map('sanitize_text_field', $mj), static function ($v) {
                return $v !== '';
            }))),
            'supertool' => array_values(array_unique(array_filter(array_map('sanitize_text_field', $st), static function ($v) {
                return $v !== '';
            }))),
        );
    }

    public static function saveConfigSelection(array $selection): void
    {
        update_option(
            self::OPTION_CONFIG_SELECTION,
            array(
                'mj_member' => isset($selection['mj_member']) && is_array($selection['mj_member'])
                    ? array_values(array_unique(array_map('sanitize_text_field', $selection['mj_member'])))
                    : array('mj_*'),
                'supertool' => isset($selection['supertool']) && is_array($selection['supertool'])
                    ? array_values(array_unique(array_map('sanitize_text_field', $selection['supertool'])))
                    : array('mjet_*', 'elementor_cpt_support'),
            )
        );
    }

    public static function createFixtures(array $selectedSlugs = array()): array
    {
        global $wpdb;

        $dir = self::getFixturesDir();
        if (!wp_mkdir_p($dir)) {
            return array('success' => false, 'saved' => array(), 'errors' => array(__('Impossible de creer le dossier data/fixtures.', 'mj-member')));
        }

        $saved = array();
        $errors = array();
        $manifest = array(
            'generated_at' => current_time('mysql'),
            'tables' => array(),
            'format' => 'jsonl+json',
        );

        foreach (self::sanitizeSourceSlugs($selectedSlugs, true) as $slug) {
            if (in_array($slug, self::TABLE_SLUGS, true)) {
                $table = $wpdb->prefix . $slug;
                if (!self::tableExists($table)) {
                    continue;
                }

                $file = $dir . $slug . '.jsonl';
                $handle = @fopen($file, 'wb');
                if ($handle === false) {
                    $errors[] = sprintf(__('Impossible d\'ecrire %s.', 'mj-member'), basename($file));
                    continue;
                }

                $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
                $count = 0;
                foreach ((array) $rows as $row) {
                    $line = wp_json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($line !== false) {
                        fwrite($handle, $line . "\n");
                        $count++;
                    }
                }
                fclose($handle);

                $manifest['tables'][$slug] = array('table' => $table, 'file' => basename($file), 'rows' => $count, 'kind' => 'table');
                $saved[] = array('slug' => $slug, 'table' => $table, 'file' => basename($file), 'rows' => $count);
                continue;
            }

            $result = self::createSimpleSourceFixture($slug, $dir);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }

            $file = (string) ($result['file'] ?? ($slug . '.json'));
            $rows = (int) ($result['rows'] ?? 0);
            $manifest['tables'][$slug] = array('table' => $slug, 'file' => $file, 'rows' => $rows, 'kind' => 'source');
            $saved[] = array('slug' => $slug, 'table' => $slug, 'file' => $file, 'rows' => $rows);
        }

        @file_put_contents($dir . 'fixtures.manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return array('success' => empty($errors), 'saved' => $saved, 'errors' => $errors, 'manifest' => $manifest);
    }

    public static function restoreFixtures(array $selectedSlugs = array(), array $cleanBeforeSlugs = array()): array
    {
        global $wpdb;

        $dir = self::getFixturesDir();
        if (!is_dir($dir)) {
            return array('success' => false, 'restored' => array(), 'errors' => array(__('Le dossier data/fixtures est introuvable.', 'mj-member')));
        }

        $restored = array();
        $errors = array();
        $cleanLookup = array_fill_keys(self::sanitizeSourceSlugs($cleanBeforeSlugs, false), true);
        $restoreTargets = self::sanitizeSourceSlugs($selectedSlugs, false);

        if (empty($restoreTargets)) {
            return array(
                'success' => false,
                'restored' => array(),
                'errors' => array(__('Aucune source selectionnee pour la restauration.', 'mj-member')),
            );
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($restoreTargets as $slug) {
            if (in_array($slug, self::TABLE_SLUGS, true)) {
                $table = $wpdb->prefix . $slug;
                $file = $dir . $slug . '.jsonl';
                if (!self::tableExists($table)) {
                    $errors[] = sprintf(__('Table introuvable pour la source %s.', 'mj-member'), $slug);
                    continue;
                }

                if (!is_readable($file)) {
                    $errors[] = sprintf(__('Fixture introuvable ou illisible: %s.', 'mj-member'), basename($file));
                    continue;
                }

                $columns = self::getTableColumns($table);
                if (empty($columns)) {
                    $errors[] = sprintf(__('Colonnes introuvables pour %s.', 'mj-member'), $table);
                    continue;
                }

                $mustClean = !empty($cleanLookup[$slug]);
                if ($mustClean) {
                    $truncated = $wpdb->query("TRUNCATE TABLE `{$table}`");
                    if ($truncated === false) {
                        $wpdb->query("DELETE FROM `{$table}`");
                    }
                }

                $handle = @fopen($file, 'rb');
                if ($handle === false) {
                    $errors[] = sprintf(__('Impossible de lire %s.', 'mj-member'), basename($file));
                    continue;
                }

                $count = 0;
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $row = json_decode($line, true);
                    if (!is_array($row)) {
                        continue;
                    }

                    $insertData = array();
                    foreach ($columns as $column) {
                        if (array_key_exists($column, $row)) {
                            $insertData[$column] = $row[$column];
                        }
                    }

                    if (empty($insertData)) {
                        continue;
                    }

                    $ok = $mustClean ? $wpdb->insert($table, $insertData) : $wpdb->replace($table, $insertData);
                    if ($ok !== false) {
                        $count++;
                    }
                }

                fclose($handle);
                $restored[] = array('slug' => $slug, 'table' => $table, 'file' => basename($file), 'rows' => $count);
                continue;
            }

            $file = $dir . $slug . '.json';
            $mustClean = !empty($cleanLookup[$slug]);
            $result = self::restoreSimpleSourceFixture($slug, $dir, $mustClean);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }

            $restored[] = array(
                'slug' => $slug,
                'table' => $slug,
                'file' => (string) ($result['file'] ?? basename($file)),
                'rows' => (int) ($result['rows'] ?? 0),
            );
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        if (empty($restored) && empty($errors)) {
            $errors[] = __('Aucune donnee restauree a partir des fixtures selectionnees.', 'mj-member');
        }

        return array('success' => empty($errors), 'restored' => $restored, 'errors' => $errors);
    }

    public static function restoreInstallEnabled(): array
    {
        $selected = self::getSavedSelection(self::OPTION_USE_ON_INSTALL, array());
        if (empty($selected)) {
            return array('success' => true, 'restored' => array(), 'errors' => array());
        }

        $clean = self::getSavedSelection(self::OPTION_RESTORE_CLEAN, array());
        return self::restoreFixtures($selected, $clean);
    }

    public static function listFixtureFiles(): array
    {
        $dir = self::getFixturesDir();
        if (!is_dir($dir)) {
            return array();
        }

        $list = array();
        foreach (array_merge(glob($dir . '*.jsonl') ?: array(), glob($dir . '*.json') ?: array()) as $path) {
            $list[] = array(
                'filename' => basename($path),
                'slug' => pathinfo($path, PATHINFO_FILENAME),
                'size' => (int) @filesize($path),
                'modified_at' => (int) @filemtime($path),
            );
        }

        usort($list, static function (array $a, array $b): int {
            return strcmp($a['filename'], $b['filename']);
        });

        return $list;
    }

    public static function getManifest(): array
    {
        $path = self::getFixturesDir() . 'fixtures.manifest.json';
        if (!is_readable($path)) {
            return array();
        }

        $content = @file_get_contents($path);
        $decoded = is_string($content) ? json_decode($content, true) : array();
        return is_array($decoded) ? $decoded : array();
    }

    public static function importArchive(array $uploadedFile)
    {
        if (empty($uploadedFile['tmp_name']) || !is_uploaded_file((string) $uploadedFile['tmp_name'])) {
            return new WP_Error('fixtures_upload_missing', __('Aucun fichier importe.', 'mj-member'));
        }

        $name = isset($uploadedFile['name']) ? (string) $uploadedFile['name'] : '';
        if ($name === '' || strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            return new WP_Error('fixtures_upload_extension', __('Le fichier doit etre une archive ZIP.', 'mj-member'));
        }

        if (!class_exists('ZipArchive')) {
            return new WP_Error('fixtures_zip_missing', __('ZipArchive est indisponible sur ce serveur.', 'mj-member'));
        }

        $zip = new \ZipArchive();
        if ($zip->open((string) $uploadedFile['tmp_name']) !== true) {
            return new WP_Error('fixtures_zip_open', __('Impossible d\'ouvrir l\'archive ZIP.', 'mj-member'));
        }

        $targetDir = self::getFixturesDir();
        if (!wp_mkdir_p($targetDir)) {
            $zip->close();
            return new WP_Error('fixtures_dir_create', __('Impossible de creer le dossier data/fixtures.', 'mj-member'));
        }

        $allowed = array('fixtures.manifest.json');
        foreach (self::allSourceSlugs() as $slug) {
            $allowed[] = $slug . '.jsonl';
            $allowed[] = $slug . '.json';
        }

        $imported = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (!is_string($entry) || $entry === '') {
                continue;
            }

            $entry = str_replace('\\', '/', $entry);
            if (strpos($entry, '..') !== false) {
                continue;
            }

            $base = basename($entry);
            if (!in_array($base, $allowed, true) && strpos($entry, 'media/') !== 0) {
                continue;
            }

            $stream = $zip->getStream($entry);
            if (!is_resource($stream)) {
                continue;
            }

            $destination = strpos($entry, 'media/') === 0
                ? $targetDir . ltrim($entry, '/\\')
                : $targetDir . $base;
            $destinationDir = dirname($destination);
            if (!is_dir($destinationDir)) {
                wp_mkdir_p($destinationDir);
            }

            $out = @fopen($destination, 'wb');
            if ($out === false) {
                fclose($stream);
                continue;
            }

            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    break;
                }
                fwrite($out, $chunk);
            }

            fclose($stream);
            fclose($out);
            $imported[] = strpos($entry, 'media/') === 0 ? $entry : $base;
        }

        $zip->close();

        return array('success' => !empty($imported), 'files' => $imported);
    }

    public static function buildExportArchive()
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('fixtures_zip_missing', __('ZipArchive est indisponible sur ce serveur.', 'mj-member'));
        }

        $dir = self::getFixturesDir();
        if (!is_dir($dir)) {
            return new WP_Error('fixtures_dir_missing', __('Le dossier data/fixtures est introuvable.', 'mj-member'));
        }

        $filename = 'mj-member-fixtures-' . gmdate('Ymd-His') . '.zip';
        $tmpFile = wp_tempnam($filename);
        if (!is_string($tmpFile) || $tmpFile === '') {
            return new WP_Error('fixtures_tmp_failed', __('Impossible de creer le fichier temporaire d\'export.', 'mj-member'));
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('fixtures_zip_create', __('Impossible de creer l\'archive ZIP.', 'mj-member'));
        }

        foreach (self::listFixtureFiles() as $file) {
            $path = $dir . $file['filename'];
            if (is_readable($path)) {
                $zip->addFile($path, $file['filename']);
            }
        }

        $mediaDir = $dir . 'media';
        if (is_dir($mediaDir)) {
            foreach (self::listFilesRecursive($mediaDir) as $path) {
                $relative = str_replace('\\', '/', ltrim(str_replace($dir, '', $path), '/\\'));
                $zip->addFile($path, $relative);
            }
        }

        $zip->close();

        return array('path' => $tmpFile, 'filename' => $filename, 'mime' => 'application/zip');
    }

    private static function createSimpleSourceFixture(string $slug, string $dir): array|WP_Error
    {
        $filename = $slug . '.json';
        $payload = array(
            'slug' => $slug,
            'generated_at' => current_time('mysql'),
            'items' => array(),
        );

        switch ($slug) {
            case 'wp_pages':
                $payload['items'] = self::collectPosts('page');
                break;
            case 'wp_posts':
                $payload['items'] = self::collectPosts('post');
                break;
            case 'wp_media':
                $payload['items'] = self::collectMedia($dir);
                break;
            case 'wp_theme_settings':
                $payload['items'] = array(
                    'stylesheet' => get_stylesheet(),
                    'theme_mods' => get_theme_mods(),
                    'custom_css' => wp_get_custom_css(),
                );
                break;
            case 'supertool_data':
                $payload['items'] = self::collectSupertoolData();
                break;
            case 'config_mj_member':
                $payload['items'] = self::collectOptionsByPatterns(self::getConfigSelection()['mj_member'] ?? array('mj_*'));
                break;
            case 'config_supertool':
                $payload['items'] = self::collectOptionsByPatterns(self::getConfigSelection()['supertool'] ?? array('mjet_*', 'elementor_cpt_support'));
                break;
            default:
                return new WP_Error('fixtures_source_unknown', __('Source de fixture inconnue.', 'mj-member'));
        }

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($dir . $filename, $json) === false) {
            return new WP_Error('fixtures_source_write', sprintf(__('Impossible d\'ecrire %s.', 'mj-member'), $filename));
        }

        $rows = is_array($payload['items']) ? count($payload['items']) : 1;
        return array('file' => $filename, 'rows' => $rows);
    }

    private static function restoreSimpleSourceFixture(string $slug, string $dir, bool $cleanBefore): array|WP_Error
    {
        $filename = $slug . '.json';
        $path = $dir . $filename;

        if (!is_readable($path)) {
            return new WP_Error('fixtures_source_missing', sprintf(__('Fixture introuvable ou illisible: %s.', 'mj-member'), $filename));
        }

        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return new WP_Error('fixtures_source_invalid', sprintf(__('%s invalide.', 'mj-member'), $filename));
        }

        $items = $decoded['items'] ?? array();

        switch ($slug) {
            case 'wp_pages':
                return self::restorePosts('page', $items, $cleanBefore, $filename);
            case 'wp_posts':
                return self::restorePosts('post', $items, $cleanBefore, $filename);
            case 'wp_media':
                return self::restoreMedia($dir, $items, $cleanBefore, $filename);
            case 'wp_theme_settings':
                return self::restoreThemeSettings($items, $cleanBefore, $filename);
            case 'supertool_data':
                return self::restoreSupertoolData($items, $cleanBefore, $filename);
            case 'config_mj_member':
            case 'config_supertool':
                return self::restoreOptions($items, $cleanBefore, $filename);
            default:
                return new WP_Error('fixtures_source_unknown', __('Source de fixture inconnue.', 'mj-member'));
        }
    }

    private static function collectPosts(string $postType): array
    {
        $posts = get_posts(array(
            'post_type' => $postType,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        $items = array();
        foreach ($posts as $post) {
            $items[] = array(
                'post' => array(
                    'post_name' => $post->post_name,
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_excerpt' => $post->post_excerpt,
                    'post_status' => $post->post_status,
                    'post_parent' => (int) $post->post_parent,
                    'menu_order' => (int) $post->menu_order,
                ),
                'meta' => get_post_meta((int) $post->ID),
            );
        }

        return $items;
    }

    private static function restorePosts(string $postType, $items, bool $cleanBefore, string $filename): array|WP_Error
    {
        if (!is_array($items)) {
            return new WP_Error('fixtures_posts_invalid', sprintf(__('%s invalide.', 'mj-member'), $filename));
        }

        if ($cleanBefore) {
            $ids = get_posts(array('post_type' => $postType, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
            foreach ($ids as $id) {
                wp_delete_post((int) $id, true);
            }
        }

        $count = 0;
        foreach ($items as $item) {
            if (empty($item['post']) || !is_array($item['post'])) {
                continue;
            }

            $post = $item['post'];
            $slug = sanitize_title((string) ($post['post_name'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $existing = get_page_by_path($slug, OBJECT, $postType);
            $payload = array(
                'post_type' => $postType,
                'post_name' => $slug,
                'post_title' => (string) ($post['post_title'] ?? ''),
                'post_content' => (string) ($post['post_content'] ?? ''),
                'post_excerpt' => (string) ($post['post_excerpt'] ?? ''),
                'post_status' => (string) ($post['post_status'] ?? 'draft'),
                'post_parent' => (int) ($post['post_parent'] ?? 0),
                'menu_order' => (int) ($post['menu_order'] ?? 0),
            );

            if ($existing && !$cleanBefore) {
                $payload['ID'] = (int) $existing->ID;
                $postId = wp_update_post($payload, true);
            } else {
                $postId = wp_insert_post($payload, true);
            }

            if (is_wp_error($postId) || (int) $postId <= 0) {
                continue;
            }

            if (!empty($item['meta']) && is_array($item['meta'])) {
                foreach ($item['meta'] as $metaKey => $metaValues) {
                    if (!is_array($metaValues)) {
                        continue;
                    }
                    delete_post_meta((int) $postId, (string) $metaKey);
                    foreach ($metaValues as $metaValue) {
                        add_post_meta((int) $postId, (string) $metaKey, maybe_unserialize($metaValue));
                    }
                }
            }

            $count++;
        }

        return array('file' => $filename, 'rows' => $count);
    }

    private static function collectMedia(string $fixturesDir): array
    {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        $settings = self::getImageImportSettings();
        $maxBytes = (int) $settings['max_file_size_mb'] * 1024 * 1024;
        $mediaDir = $fixturesDir . 'media/';
        wp_mkdir_p($mediaDir);

        $items = array();
        foreach ($attachments as $attachment) {
            $path = get_attached_file((int) $attachment->ID);
            if (!is_string($path) || !is_readable($path)) {
                continue;
            }

            $size = (int) @filesize($path);
            if ($size <= 0 || $size > $maxBytes) {
                continue;
            }

            $storedName = sanitize_file_name((string) $attachment->ID . '-' . basename($path));
            if (@copy($path, $mediaDir . $storedName) === false) {
                continue;
            }

            $items[] = array(
                'file' => 'media/' . $storedName,
                'post' => array(
                    'post_title' => $attachment->post_title,
                    'post_excerpt' => $attachment->post_excerpt,
                    'post_content' => $attachment->post_content,
                    'post_mime_type' => $attachment->post_mime_type,
                ),
                'meta' => get_post_meta((int) $attachment->ID),
            );
        }

        return $items;
    }

    private static function restoreMedia(string $fixturesDir, $items, bool $cleanBefore, string $filename): array|WP_Error
    {
        if (!is_array($items)) {
            return new WP_Error('fixtures_media_invalid', sprintf(__('%s invalide.', 'mj-member'), $filename));
        }

        if ($cleanBefore) {
            $ids = get_posts(array('post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
            foreach ($ids as $id) {
                wp_delete_attachment((int) $id, true);
            }
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overwrite = !empty(self::getImageImportSettings()['overwrite_existing']);
        $count = 0;

        foreach ($items as $item) {
            $rel = isset($item['file']) ? (string) $item['file'] : '';
            if ($rel === '') {
                continue;
            }

            $source = $fixturesDir . ltrim(str_replace('..', '', $rel), '/\\');
            if (!is_readable($source)) {
                continue;
            }

            $filenameOnly = sanitize_file_name(basename($source));
            if (!$overwrite && get_page_by_title($filenameOnly, OBJECT, 'attachment')) {
                continue;
            }

            $blob = @file_get_contents($source);
            if (!is_string($blob)) {
                continue;
            }

            $bits = wp_upload_bits($filenameOnly, null, $blob);
            if (!empty($bits['error'])) {
                continue;
            }

            $post = is_array($item['post'] ?? null) ? $item['post'] : array();
            $attachment = array(
                'post_title' => sanitize_text_field((string) ($post['post_title'] ?? $filenameOnly)),
                'post_excerpt' => sanitize_text_field((string) ($post['post_excerpt'] ?? '')),
                'post_content' => sanitize_textarea_field((string) ($post['post_content'] ?? '')),
                'post_mime_type' => sanitize_text_field((string) ($post['post_mime_type'] ?? 'application/octet-stream')),
                'post_status' => 'inherit',
                'guid' => $bits['url'],
            );

            $attachmentId = wp_insert_attachment($attachment, $bits['file']);
            if (is_wp_error($attachmentId) || (int) $attachmentId <= 0) {
                continue;
            }

            $meta = wp_generate_attachment_metadata((int) $attachmentId, $bits['file']);
            if (is_array($meta)) {
                wp_update_attachment_metadata((int) $attachmentId, $meta);
            }

            $count++;
        }

        return array('file' => $filename, 'rows' => $count);
    }

    private static function collectSupertoolData(): array
    {
        global $wpdb;

        $templates = self::collectPosts('mjet-template');
        $options = self::collectOptionsByPatterns(array('mjet_*', 'elementor_cpt_support'));

        $rows = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'mjet_%'", ARRAY_A);
        foreach ((array) $rows as $row) {
            $options[(string) $row['option_name']] = maybe_unserialize($row['option_value']);
        }

        return array(
            'templates' => $templates,
            'options' => $options,
        );
    }

    private static function restoreSupertoolData($items, bool $cleanBefore, string $filename): array|WP_Error
    {
        if (!is_array($items)) {
            return new WP_Error('fixtures_supertool_invalid', sprintf(__('%s invalide.', 'mj-member'), $filename));
        }

        $templates = isset($items['templates']) && is_array($items['templates']) ? $items['templates'] : array();
        $options = isset($items['options']) && is_array($items['options']) ? $items['options'] : array();

        $postsResult = self::restorePosts('mjet-template', $templates, $cleanBefore, $filename);
        if (is_wp_error($postsResult)) {
            return $postsResult;
        }

        foreach ($options as $key => $value) {
            if (!self::isSensitiveOption((string) $key)) {
                update_option((string) $key, $value);
            }
        }

        return array('file' => $filename, 'rows' => (int) ($postsResult['rows'] ?? 0));
    }

    private static function restoreThemeSettings($items, bool $cleanBefore, string $filename): array|WP_Error
    {
        if (!is_array($items)) {
            return new WP_Error('fixtures_theme_invalid', sprintf(__('%s invalide.', 'mj-member'), $filename));
        }

        if ($cleanBefore) {
            remove_theme_mods();
        }

        $stylesheet = sanitize_text_field((string) ($items['stylesheet'] ?? get_stylesheet()));
        $mods = isset($items['theme_mods']) && is_array($items['theme_mods']) ? $items['theme_mods'] : array();
        update_option('theme_mods_' . $stylesheet, $mods);

        if (isset($items['custom_css']) && is_string($items['custom_css'])) {
            wp_update_custom_css_post($items['custom_css']);
        }

        return array('file' => $filename, 'rows' => count($mods));
    }

    private static function collectOptionsByPatterns(array $patterns): array
    {
        global $wpdb;

        $result = array();
        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;
            if (strpos($pattern, '*') !== false) {
                $like = str_replace('*', '%', $pattern);
                $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like), ARRAY_A);
                foreach ((array) $rows as $row) {
                    $name = (string) $row['option_name'];
                    if (!self::isSensitiveOption($name)) {
                        $result[$name] = maybe_unserialize($row['option_value']);
                    }
                }
            } else {
                if (!self::isSensitiveOption($pattern)) {
                    $result[$pattern] = get_option($pattern, null);
                }
            }
        }

        return $result;
    }

    private static function restoreOptions($items, bool $cleanBefore, string $filename): array|WP_Error
    {
        if (!is_array($items)) {
            return new WP_Error('fixtures_config_invalid', sprintf(__('%s invalide.', 'mj-member'), $filename));
        }

        $count = 0;
        foreach ($items as $key => $value) {
            $key = (string) $key;
            if (self::isSensitiveOption($key)) {
                continue;
            }
            if ($cleanBefore) {
                delete_option($key);
            }
            update_option($key, $value);
            $count++;
        }

        return array('file' => $filename, 'rows' => $count);
    }

    private static function tableExists(string $table): bool
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return is_string($found) && $found === $table;
    }

    private static function getTableColumns(string $table): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }

        $columns = array();
        foreach ($rows as $row) {
            if (!empty($row['Field'])) {
                $columns[] = (string) $row['Field'];
            }
        }

        return $columns;
    }

    private static function sanitizeSourceSlugs(array $selectedSlugs, bool $fallbackAll = true): array
    {
        $all = self::allSourceSlugs();

        if (empty($selectedSlugs)) {
            return $fallbackAll ? $all : array();
        }

        $normalized = array();
        foreach ($selectedSlugs as $slug) {
            $slug = sanitize_key((string) $slug);
            if ($slug !== '' && in_array($slug, $all, true)) {
                $normalized[] = $slug;
            }
        }

        if (empty($normalized)) {
            return $fallbackAll ? $all : array();
        }

        return array_values(array_unique($normalized));
    }

    private static function allSourceSlugs(): array
    {
        return array_merge(self::TABLE_SLUGS, array_keys(self::EXTRA_SOURCE_LABELS));
    }

    private static function isSensitiveOption(string $optionName): bool
    {
        $needle = strtolower($optionName);

        return strpos($needle, 'secret') !== false
            || strpos($needle, 'password') !== false
            || strpos($needle, 'token') !== false
            || strpos($needle, 'api_key') !== false;
    }

    private static function listFilesRecursive(string $dir): array
    {
        $files = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }
}
