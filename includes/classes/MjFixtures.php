<?php

namespace Mj\Member\Classes;

if (!defined('ABSPATH')) {
    exit;
}

final class MjFixtures
{
    /**
     * Tables metier incluses dans les fixtures.
     * Les noms sont sans prefixe WP.
     */
    private const TARGET_TABLES = array(
        'mj_badges',
        'mj_trophies',
        'mj_levels',
        'mj_action_types',
        'mj_event_locations',
        'mj_members',
    );

    public static function getFixturesPath(): string
    {
        return MJ_MEMBER_PATH . 'data/fixtures/';
    }

    public static function listFixtures(): array
    {
        $path = self::getFixturesPath();
        if (!is_dir($path)) {
            return array();
        }

        $files = glob($path . '*.json');
        if (!is_array($files)) {
            return array();
        }

        $items = array();
        foreach ($files as $filePath) {
            $items[] = array(
                'filename' => basename($filePath),
                'size' => (int) @filesize($filePath),
                'modified_at' => (int) @filemtime($filePath),
            );
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['modified_at'] ?? 0) <=> ($a['modified_at'] ?? 0);
        });

        return $items;
    }

    public static function createFixture(string $requestedName = ''): array
    {
        if (!self::ensureFixturesPath()) {
            return array(
                'success' => false,
                'message' => __('Impossible de creer le dossier data/fixtures.', 'mj-member'),
            );
        }

        global $wpdb;

        $tables = array();
        foreach (self::TARGET_TABLES as $tableKey) {
            $tableName = $wpdb->prefix . $tableKey;
            if (!self::tableExists($tableName)) {
                $tables[$tableKey] = array(
                    'table' => $tableName,
                    'rows' => array(),
                    'count' => 0,
                );
                continue;
            }

            $rows = $wpdb->get_results("SELECT * FROM `{$tableName}`", ARRAY_A);
            if (!is_array($rows)) {
                $rows = array();
            }

            $tables[$tableKey] = array(
                'table' => $tableName,
                'rows' => $rows,
                'count' => count($rows),
            );
        }

        $payload = array(
            'schema' => 'mj-member-fixtures-v1',
            'generated_at' => gmdate('c'),
            'site_url' => home_url('/'),
            'wp_prefix' => $wpdb->prefix,
            'tables' => $tables,
        );

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return array(
                'success' => false,
                'message' => __('Echec de serialisation JSON des fixtures.', 'mj-member'),
            );
        }

        $baseName = self::buildBaseFilename($requestedName);
        $filename = self::reserveFixtureFilename($baseName);
        $filePath = self::getFixturesPath() . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written = file_put_contents($filePath, $json);
        if ($written === false) {
            return array(
                'success' => false,
                'message' => __('Impossible d\'ecrire le fichier de fixtures.', 'mj-member'),
            );
        }

        return array(
            'success' => true,
            'filename' => $filename,
            'path' => $filePath,
            'table_count' => count(self::TARGET_TABLES),
        );
    }

    public static function restoreFixture(string $filename): array
    {
        $resolved = self::resolveFixturePath($filename);
        if (is_wp_error($resolved)) {
            return array(
                'success' => false,
                'message' => $resolved->get_error_message(),
            );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw = file_get_contents($resolved);
        if (!is_string($raw) || $raw === '') {
            return array(
                'success' => false,
                'message' => __('Fichier de fixtures vide ou illisible.', 'mj-member'),
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array(
                'success' => false,
                'message' => __('Le fichier de fixtures n\'est pas un JSON valide.', 'mj-member'),
            );
        }

        return self::importDecodedFixture($decoded);
    }

    public static function importUploadedFixture(array $file): array
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => __('Upload invalide pour le fichier fixtures.', 'mj-member'),
            );
        }

        $tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return array(
                'success' => false,
                'message' => __('Le fichier importe n\'est pas reconnu.', 'mj-member'),
            );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw = file_get_contents($tmpName);
        if (!is_string($raw) || $raw === '') {
            return array(
                'success' => false,
                'message' => __('Le fichier importe est vide ou illisible.', 'mj-member'),
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array(
                'success' => false,
                'message' => __('Le fichier importe n\'est pas un JSON valide.', 'mj-member'),
            );
        }

        if (self::ensureFixturesPath()) {
            $uploadedName = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';
            $baseName = $uploadedName !== '' ? pathinfo($uploadedName, PATHINFO_FILENAME) : 'imported-fixture';
            $targetName = self::reserveFixtureFilename(self::buildBaseFilename((string) $baseName));
            $targetPath = self::getFixturesPath() . $targetName;
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($targetPath, $raw);
        }

        return self::importDecodedFixture($decoded);
    }

    public static function getFixtureForExport(string $filename)
    {
        $resolved = self::resolveFixturePath($filename);
        if (is_wp_error($resolved)) {
            return $resolved;
        }

        return array(
            'path' => $resolved,
            'download_name' => basename($resolved),
        );
    }

    private static function importDecodedFixture(array $decoded): array
    {
        global $wpdb;

        $tablesNode = isset($decoded['tables']) && is_array($decoded['tables'])
            ? $decoded['tables']
            : array();

        if (empty($tablesNode)) {
            return array(
                'success' => false,
                'message' => __('Aucune table exploitable dans la fixture.', 'mj-member'),
            );
        }

        $insertedByTable = array();
        $errors = array();

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TARGET_TABLES as $tableKey) {
            $tableNode = $tablesNode[$tableKey] ?? null;
            if (!is_array($tableNode)) {
                continue;
            }

            $rows = $tableNode['rows'] ?? $tableNode;
            if (!is_array($rows)) {
                continue;
            }

            $tableName = $wpdb->prefix . $tableKey;
            if (!self::tableExists($tableName)) {
                $errors[] = sprintf(__('Table absente: %s', 'mj-member'), $tableName);
                continue;
            }

            $columns = self::getTableColumns($tableName);
            if (empty($columns)) {
                $errors[] = sprintf(__('Colonnes introuvables pour %s', 'mj-member'), $tableName);
                continue;
            }

            $truncateResult = $wpdb->query("TRUNCATE TABLE `{$tableName}`");
            if ($truncateResult === false) {
                $errors[] = sprintf(__('Impossible de vider %s: %s', 'mj-member'), $tableName, (string) $wpdb->last_error);
                continue;
            }

            $inserted = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $filtered = array();
                foreach ($row as $col => $value) {
                    $columnName = (string) $col;
                    if (!in_array($columnName, $columns, true)) {
                        continue;
                    }
                    $filtered[$columnName] = $value;
                }

                if (empty($filtered)) {
                    continue;
                }

                $ok = $wpdb->insert($tableName, $filtered);
                if ($ok === false) {
                    $errors[] = sprintf(
                        __('Insertion echouee dans %s: %s', 'mj-member'),
                        $tableName,
                        (string) $wpdb->last_error
                    );
                    break;
                }

                $inserted++;
            }

            $insertedByTable[$tableKey] = $inserted;
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        $message = empty($errors)
            ? __('Fixtures restaurees avec succes.', 'mj-member')
            : __('Fixtures importees avec avertissements.', 'mj-member');

        return array(
            'success' => empty($errors),
            'message' => $message,
            'inserted' => $insertedByTable,
            'errors' => $errors,
        );
    }

    private static function getTableColumns(string $tableName): array
    {
        global $wpdb;
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$tableName}`", 0);
        if (!is_array($columns)) {
            return array();
        }

        return array_values(array_map('strval', $columns));
    }

    private static function tableExists(string $tableName): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
        return is_string($found) && $found === $tableName;
    }

    private static function ensureFixturesPath(): bool
    {
        $path = self::getFixturesPath();
        if (is_dir($path)) {
            return true;
        }

        return wp_mkdir_p($path);
    }

    private static function buildBaseFilename(string $requestedName): string
    {
        $raw = sanitize_file_name($requestedName);
        $raw = preg_replace('/\.json$/i', '', $raw ?? '');
        $raw = is_string($raw) ? trim($raw, '-_. ') : '';

        if ($raw === '') {
            return 'fixture-' . gmdate('Ymd-His');
        }

        return $raw;
    }

    private static function reserveFixtureFilename(string $baseName): string
    {
        $path = self::getFixturesPath();
        $candidate = $baseName . '.json';
        $index = 1;

        while (file_exists($path . $candidate)) {
            $candidate = $baseName . '-' . $index . '.json';
            $index++;
        }

        return $candidate;
    }

    private static function resolveFixturePath(string $filename)
    {
        $safeName = sanitize_file_name($filename);
        if ($safeName === '') {
            return new \WP_Error('mj_fixtures_invalid_name', __('Nom de fixture invalide.', 'mj-member'));
        }

        $fullPath = self::getFixturesPath() . $safeName;
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            return new \WP_Error('mj_fixtures_missing_file', __('Fixture introuvable dans data/fixtures.', 'mj-member'));
        }

        return $fullPath;
    }
}
