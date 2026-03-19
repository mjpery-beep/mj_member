<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the backup of mj_* database tables to Nextcloud.
 *
 * Usage:
 *   $result = MjDatabaseBackup::run();
 *   // returns true on success, WP_Error on failure
 */
final class MjDatabaseBackup
{
    private const OPTION_LAST_RUN    = 'mj_backup_last_run';
    private const OPTION_LAST_STATUS = 'mj_backup_last_status';

    /* ------------------------------------------------------------------
     * Public API
     * ----------------------------------------------------------------*/

    /**
     * Returns all tables whose name begins with {$wpdb->prefix}mj_.
     *
     * @return string[]
     */
    public static function getMjTables(): array
    {
        global $wpdb;

        $like   = $wpdb->esc_like($wpdb->prefix . 'mj_') . '%';
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));

        return is_array($tables) ? $tables : [];
    }

    /**
     * Generates a SQL dump string for all mj_ tables.
     */
    public static function generateSqlDump(): string
    {
        global $wpdb;

        $tables = self::getMjTables();
        if (empty($tables)) {
            return '';
        }

        $output  = "-- MJ Member database backup\n";
        $output .= '-- Generated: ' . current_time('Y-m-d H:i:s') . "\n";
        $output .= '-- WordPress DB prefix: ' . $wpdb->prefix . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            if (!$create) {
                continue;
            }

            $output .= "-- Table: {$table}\n";
            $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $output .= $create[1] . ";\n\n";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            if (!empty($rows)) {
                $columns = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($rows[0])));
                foreach ($rows as $row) {
                    $values = array_map([self::class, 'escapeSqlValue'], $row);
                    $output .= "INSERT INTO `{$table}` ({$columns}) VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $output;
    }

    /**
     * Runs the full backup: generates SQL, uploads to Nextcloud, prunes old files.
     *
     * @return true|WP_Error
     */
    public static function run(): true|WP_Error
    {
        if (!Config::nextcloudIsReady()) {
            return new WP_Error(
                'nextcloud_not_ready',
                'Nextcloud n\'est pas configuré. Renseignez les paramètres dans l\'onglet Agenda & Google.'
            );
        }

        $nextcloud = MjNextcloud::make();
        if (is_wp_error($nextcloud)) {
            return $nextcloud;
        }

        $sql = self::generateSqlDump();
        if ($sql === '') {
            return new WP_Error('no_tables', 'Aucune table mj_ trouvée dans la base de données.');
        }

        $backupFolder = self::getBackupFolder();

        $ensure = self::ensureFolder($nextcloud, $backupFolder);
        if (is_wp_error($ensure)) {
            self::saveStatus(false, '', $ensure->get_error_message());
            return $ensure;
        }

        $filename = 'mj-member-backup-' . current_time('Y-m-d_H-i-s') . '.sql';

        $upload = $nextcloud->putContent($backupFolder, $filename, $sql, 'application/sql');
        if (is_wp_error($upload)) {
            self::saveStatus(false, $filename, $upload->get_error_message());
            return $upload;
        }

        self::pruneOldBackups($nextcloud, $backupFolder);

        update_option(self::OPTION_LAST_RUN, time());
        self::saveStatus(true, $filename, 'OK');

        return true;
    }

    /**
     * Returns the Nextcloud folder used for backups (relative path, no leading slash).
     */
    public static function getBackupFolder(): string
    {
        $folder = trim((string) get_option('mj_backup_nextcloud_folder', 'backups/database'), '/');

        return $folder !== '' ? $folder : 'backups/database';
    }

    /**
     * Returns the last backup status array.
     *
     * @return array{success?:bool, filename?:string, message?:string}
     */
    public static function getLastStatus(): array
    {
        $v = get_option(self::OPTION_LAST_STATUS, []);

        return is_array($v) ? $v : [];
    }

    /**
     * Returns the Unix timestamp of the last successful backup, or 0 if never run.
     */
    public static function getLastRun(): int
    {
        return (int) get_option(self::OPTION_LAST_RUN, 0);
    }

    /* ------------------------------------------------------------------
     * Private helpers
     * ----------------------------------------------------------------*/

    /**
     * Creates each folder segment of $folderPath in Nextcloud if it does not exist.
     *
     * @return true|WP_Error
     */
    private static function ensureFolder(MjNextcloud $nc, string $folderPath): true|WP_Error
    {
        $parts   = explode('/', trim($folderPath, '/'));
        $current = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $parent  = $current;
            $current = $current !== '' ? $current . '/' . $part : $part;

            $list = $nc->listFolder($current);
            if (is_wp_error($list)) {
                $created = $nc->createFolder($parent, $part);
                if (is_wp_error($created)) {
                    return $created;
                }
            }
        }

        return true;
    }

    /**
     * Keeps only the $keep most recent .sql files in $folderPath, deletes the rest.
     */
    private static function pruneOldBackups(MjNextcloud $nc, string $folderPath): void
    {
        $keep = max(1, (int) get_option('mj_backup_retention', 7));
        $list = $nc->listFolder($folderPath);

        if (is_wp_error($list) || empty($list['items'])) {
            return;
        }

        $files = array_values(array_filter(
            $list['items'],
            fn($i) => ($i['type'] ?? '') === 'file' && str_ends_with((string) ($i['name'] ?? ''), '.sql')
        ));

        if (count($files) <= $keep) {
            return;
        }

        // Sort ascending by name (filenames embed datetime → oldest first)
        usort($files, fn($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        foreach (array_slice($files, 0, count($files) - $keep) as $file) {
            $path = $file['id'] ?? '';
            if ($path !== '') {
                $nc->delete($path);
            }
        }
    }

    /**
     * Persists the last backup status to wp_options.
     */
    private static function saveStatus(bool $success, string $filename, string $message): void
    {
        update_option(self::OPTION_LAST_STATUS, [
            'success'  => $success,
            'filename' => $filename,
            'message'  => $message,
        ]);
    }

    /**
     * Escapes a single value for use inside a MySQL INSERT statement.
     *
     * @param mixed $v
     */
    private static function escapeSqlValue($v): string
    {
        if ($v === null) {
            return 'NULL';
        }

        $s = (string) $v;
        $s = str_replace('\\',   '\\\\', $s);
        $s = str_replace("\0",   '\0',   $s);
        $s = str_replace("\n",   '\n',   $s);
        $s = str_replace("\r",   '\r',   $s);
        $s = str_replace("'",    "\\'",  $s);
        $s = str_replace('"',    '\\"',  $s);
        $s = str_replace("\x1a", '\Z',   $s);

        return "'{$s}'";
    }
}
