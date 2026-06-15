<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjFixturesManager
{
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
        'mj_trophies' => 'Trophées',
        'mj_levels' => 'Niveaux',
        'mj_action_types' => 'Actions',
        'mj_event_locations' => 'Lieux',
        'mj_members' => 'Membres',
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
            $exists = self::tableExists($table);
            $status[] = array(
                'slug' => $slug,
                'label' => self::TABLE_LABELS[$slug] ?? $slug,
                'table' => $table,
                'exists' => $exists,
                'fixture_file' => $slug . '.jsonl',
            );
        }

        return $status;
    }

    public static function createFixtures(array $selectedSlugs = array()): array
    {
        global $wpdb;

        $dir = self::getFixturesDir();
        if (!wp_mkdir_p($dir)) {
            return array(
                'success' => false,
                'saved' => array(),
                'errors' => array(__('Impossible de créer le dossier data/fixtures.', 'mj-member')),
            );
        }

        $saved = array();
        $errors = array();
        $manifest = array(
            'generated_at' => current_time('mysql'),
            'tables' => array(),
            'format' => 'jsonl',
        );

        $targetSlugs = self::normalizeSelectedSlugs($selectedSlugs);

        foreach ($targetSlugs as $slug) {
            $table = $wpdb->prefix . $slug;
            if (!self::tableExists($table)) {
                continue;
            }

            $filepath = $dir . $slug . '.jsonl';
            $handle = @fopen($filepath, 'wb');
            if ($handle === false) {
                $errors[] = sprintf(__('Impossible d\'écrire %s.', 'mj-member'), basename($filepath));
                continue;
            }

            $rowCount = 0;
            $offset = 0;
            $chunk = 500;

            while (true) {
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $chunk, $offset), ARRAY_A);
                if (empty($rows)) {
                    break;
                }

                foreach ($rows as $row) {
                    $line = wp_json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($line === false) {
                        $errors[] = sprintf(__('Ligne JSON invalide pour la table %s.', 'mj-member'), $table);
                        continue;
                    }

                    @fwrite($handle, $line . "\n");
                    $rowCount++;
                }

                $offset += count($rows);
                if (count($rows) < $chunk) {
                    break;
                }
            }

            @fclose($handle);

            $manifest['tables'][$slug] = array(
                'table' => $table,
                'file' => basename($filepath),
                'rows' => $rowCount,
            );

            $saved[] = array(
                'slug' => $slug,
                'table' => $table,
                'file' => basename($filepath),
                'rows' => $rowCount,
            );
        }

        $manifestFile = $dir . 'fixtures.manifest.json';
        $manifestJson = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($manifestJson === false || @file_put_contents($manifestFile, $manifestJson) === false) {
            $errors[] = __('Impossible d\'écrire le manifest des fixtures.', 'mj-member');
        }

        return array(
            'success' => empty($errors),
            'saved' => $saved,
            'errors' => $errors,
            'manifest' => $manifest,
        );
    }

    public static function restoreFixtures(array $selectedSlugs = array(), array $cleanBeforeSlugs = array()): array
    {
        global $wpdb;

        $dir = self::getFixturesDir();
        if (!is_dir($dir)) {
            return array(
                'success' => false,
                'restored' => array(),
                'errors' => array(__('Le dossier data/fixtures est introuvable.', 'mj-member')),
            );
        }

        $restored = array();
        $errors = array();

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        $targetSlugs = self::normalizeSelectedSlugs($selectedSlugs);
        $cleanBeforeLookup = array_fill_keys(self::normalizeSelectedSlugs($cleanBeforeSlugs), true);

        foreach ($targetSlugs as $slug) {
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

            $mustCleanBefore = !empty($cleanBeforeLookup[$slug]);
            if ($mustCleanBefore) {
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

            $inserted = 0;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $row = json_decode($line, true);
                if (!is_array($row)) {
                    $errors[] = sprintf(__('Ligne JSON invalide dans %s.', 'mj-member'), basename($file));
                    continue;
                }

                $insertData = array();
                foreach ($columns as $column) {
                    if (!array_key_exists($column, $row)) {
                        continue;
                    }

                    $value = $row[$column];
                    if (is_array($value) || is_object($value)) {
                        $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    $insertData[$column] = $value;
                }

                if (empty($insertData)) {
                    continue;
                }

                $ok = $mustCleanBefore
                    ? $wpdb->insert($table, $insertData)
                    : $wpdb->replace($table, $insertData);
                if ($ok === false) {
                    $errors[] = sprintf(__('Insertion échouée dans %s.', 'mj-member'), $table);
                    continue;
                }

                $inserted++;
            }

            @fclose($handle);

            $restored[] = array(
                'slug' => $slug,
                'table' => $table,
                'file' => basename($file),
                'rows' => $inserted,
            );
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        return array(
            'success' => empty($errors),
            'restored' => $restored,
            'errors' => $errors,
        );
    }

    public static function listFixtureFiles(): array
    {
        $dir = self::getFixturesDir();
        $list = array();

        if (!is_dir($dir)) {
            return $list;
        }

        $manifest = self::getManifest();
        $rowsBySlug = array();
        if (!empty($manifest['tables']) && is_array($manifest['tables'])) {
            foreach ($manifest['tables'] as $slug => $tableMeta) {
                $rowsBySlug[(string) $slug] = isset($tableMeta['rows']) ? (int) $tableMeta['rows'] : 0;
            }
        }

        foreach (glob($dir . '*.jsonl') ?: array() as $filePath) {
            $slug = basename($filePath, '.jsonl');
            $list[] = array(
                'filename' => basename($filePath),
                'slug' => $slug,
                'size' => (int) filesize($filePath),
                'modified' => (int) filemtime($filePath),
                'rows' => $rowsBySlug[$slug] ?? null,
            );
        }

        usort($list, static function (array $a, array $b): int {
            return strcmp($a['filename'], $b['filename']);
        });

        return $list;
    }

    public static function getManifest(): array
    {
        $manifestPath = self::getFixturesDir() . 'fixtures.manifest.json';
        if (!is_readable($manifestPath)) {
            return array();
        }

        $content = @file_get_contents($manifestPath);
        if (!is_string($content) || $content === '') {
            return array();
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : array();
    }

    public static function importArchive(array $uploadedFile)
    {
        if (empty($uploadedFile['tmp_name']) || !is_uploaded_file((string) $uploadedFile['tmp_name'])) {
            return new WP_Error('fixtures_upload_missing', __('Aucun fichier importé.', 'mj-member'));
        }

        $name = isset($uploadedFile['name']) ? (string) $uploadedFile['name'] : '';
        if ($name === '' || strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            return new WP_Error('fixtures_upload_extension', __('Le fichier doit être une archive ZIP.', 'mj-member'));
        }

        if (!class_exists('ZipArchive')) {
            return new WP_Error('fixtures_zip_missing', __('ZipArchive est indisponible sur ce serveur.', 'mj-member'));
        }

        $zip = new \ZipArchive();
        $opened = $zip->open((string) $uploadedFile['tmp_name']);
        if ($opened !== true) {
            return new WP_Error('fixtures_zip_open', __('Impossible d\'ouvrir l\'archive ZIP.', 'mj-member'));
        }

        $targetDir = self::getFixturesDir();
        if (!wp_mkdir_p($targetDir)) {
            $zip->close();
            return new WP_Error('fixtures_dir_create', __('Impossible de créer le dossier data/fixtures.', 'mj-member'));
        }

        $allowedFiles = array('fixtures.manifest.json');
        foreach (self::TABLE_SLUGS as $slug) {
            $allowedFiles[] = $slug . '.jsonl';
        }

        $imported = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (!is_string($entryName) || $entryName === '') {
                continue;
            }

            $entryName = str_replace('\\', '/', $entryName);
            $basename = basename($entryName);
            if (!in_array($basename, $allowedFiles, true)) {
                continue;
            }

            $stream = $zip->getStream($entryName);
            if (!is_resource($stream)) {
                continue;
            }

            $destination = $targetDir . $basename;
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
            $imported[] = $basename;
        }

        $zip->close();

        return array(
            'success' => !empty($imported),
            'files' => $imported,
        );
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

        $timestamp = gmdate('Ymd-His');
        $filename = 'mj-member-fixtures-' . $timestamp . '.zip';
        $tmpFile = wp_tempnam($filename);
        if (!is_string($tmpFile) || $tmpFile === '') {
            return new WP_Error('fixtures_tmp_failed', __('Impossible de créer le fichier temporaire d\'export.', 'mj-member'));
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('fixtures_zip_create', __('Impossible de créer l\'archive ZIP.', 'mj-member'));
        }

        $files = self::listFixtureFiles();
        foreach ($files as $file) {
            $path = $dir . $file['filename'];
            if (is_readable($path)) {
                $zip->addFile($path, $file['filename']);
            }
        }

        $manifestPath = $dir . 'fixtures.manifest.json';
        if (is_readable($manifestPath)) {
            $zip->addFile($manifestPath, 'fixtures.manifest.json');
        }

        $zip->close();

        return array(
            'path' => $tmpFile,
            'filename' => $filename,
            'mime' => 'application/zip',
        );
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

    private static function normalizeSelectedSlugs(array $selectedSlugs): array
    {
        if (empty($selectedSlugs)) {
            return self::TABLE_SLUGS;
        }

        $normalized = array();
        foreach ($selectedSlugs as $slug) {
            $cleanSlug = sanitize_key((string) $slug);
            if ($cleanSlug === '' || !in_array($cleanSlug, self::TABLE_SLUGS, true)) {
                continue;
            }
            $normalized[] = $cleanSlug;
        }

        if (empty($normalized)) {
            return self::TABLE_SLUGS;
        }

        return array_values(array_unique($normalized));
    }
}
