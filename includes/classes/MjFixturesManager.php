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

            $file = $slug . '.json';
            $payload = array('slug' => $slug, 'generated_at' => current_time('mysql'));
            $ok = @file_put_contents($dir . $file, wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($ok === false) {
                $errors[] = sprintf(__('Impossible d\'ecrire %s.', 'mj-member'), $file);
                continue;
            }

            $manifest['tables'][$slug] = array('table' => $slug, 'file' => $file, 'rows' => 1, 'kind' => 'source');
            $saved[] = array('slug' => $slug, 'table' => $slug, 'file' => $file, 'rows' => 1);
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

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::sanitizeSourceSlugs($selectedSlugs, true) as $slug) {
            if (in_array($slug, self::TABLE_SLUGS, true)) {
                $table = $wpdb->prefix . $slug;
                $file = $dir . $slug . '.jsonl';
                if (!self::tableExists($table) || !is_readable($file)) {
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
            if (is_readable($file)) {
                $restored[] = array('slug' => $slug, 'table' => $slug, 'file' => basename($file), 'rows' => 1);
            }
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

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
