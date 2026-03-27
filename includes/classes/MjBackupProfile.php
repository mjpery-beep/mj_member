<?php

namespace Mj\Member\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Represents a named database backup profile.
 *
 * Profiles are stored as a JSON array in the 'mj_backup_profiles' wp_option.
 * On first access the legacy single-backup settings are migrated automatically.
 */
final class MjBackupProfile
{
    private const OPTION_KEY = 'mj_backup_profiles';

    public string $id;
    public string $name;
    /** 'all' or comma-separated substrings of table names (e.g. "mj_members,mj_events") */
    public string $tableFilter;
    public string $frequency;  // daily | twicedaily | weekly
    public int    $dailyHour;
    public int    $twiceDailySecondHour;
    public int    $weeklyDay; // 0=dimanche ... 6=samedi
    public int    $weeklyHour;
    public int    $retention;
    public string $folder;     // Nextcloud relative path (no leading slash)
    public bool   $enabled;

    private function __construct(array $data)
    {
        $this->id          = isset($data['id']) && $data['id'] !== '' ? (string) $data['id'] : wp_generate_uuid4();
        $this->name        = sanitize_text_field((string) ($data['name'] ?? 'Profil'));
        $this->tableFilter = (string) ($data['table_filter'] ?? 'all');
        $freq              = (string) ($data['frequency'] ?? 'daily');
        $this->frequency   = in_array($freq, ['daily', 'twicedaily', 'weekly'], true) ? $freq : 'daily';
        $this->dailyHour   = min(23, max(0, (int) ($data['daily_hour'] ?? 4)));
        $this->twiceDailySecondHour = min(23, max(0, (int) ($data['twicedaily_second_hour'] ?? 18)));
        $this->weeklyDay   = min(6, max(0, (int) ($data['weekly_day'] ?? 4)));
        $this->weeklyHour  = min(23, max(0, (int) ($data['weekly_hour'] ?? $this->dailyHour)));
        $this->retention   = max(1, (int) ($data['retention'] ?? 7));
        $folder            = trim((string) ($data['folder'] ?? 'backups/database'), '/');
        $this->folder      = $folder !== '' ? $folder : 'backups/database';
        $this->enabled     = (bool) ($data['enabled'] ?? true);
    }

    /* ------------------------------------------------------------------
     * Collection helpers
     * ----------------------------------------------------------------*/

    /** @return self[] */
    public static function getAll(): array
    {
        $raw = get_option(self::OPTION_KEY, null);

        if ($raw === null) {
            return self::migrateFromLegacy();
        }

        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        return array_values(array_map(fn($d) => new self((array) $d), $raw));
    }

    /** @param self[] $profiles */
    public static function saveAll(array $profiles): void
    {
        update_option(self::OPTION_KEY, array_values(array_map(fn($p) => $p->toArray(), $profiles)));
    }

    public static function getById(string $id): ?self
    {
        foreach (self::getAll() as $profile) {
            if ($profile->id === $id) {
                return $profile;
            }
        }
        return null;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    /* ------------------------------------------------------------------
     * Instance helpers
     * ----------------------------------------------------------------*/

    /** Returns the WP-Cron hook argument used to identify this profile's event. */
    public function getCronHook(): string
    {
        // One shared hook; profile id passed as argument
        return 'mj_backup_run_profile';
    }

    /**
     * Resolves tableFilter to actual table names present in the database.
     *
     * @return string[]
     */
    public function getTableList(): array
    {
        $allTables = MjDatabaseBackup::getMjTables();

        if ($this->tableFilter === 'all' || trim($this->tableFilter) === '') {
            return $allTables;
        }

        $filters = array_filter(array_map('trim', explode(',', $this->tableFilter)));
        if (empty($filters)) {
            return $allTables;
        }

        return array_values(array_filter($allTables, function (string $table) use ($filters): bool {
            foreach ($filters as $filter) {
                if ($filter !== '' && stripos($table, $filter) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }

    public function getLastStatus(): array
    {
        $v = get_option('mj_backup_profile_status_' . $this->id, []);
        return is_array($v) ? $v : [];
    }

    public function getLastRun(): int
    {
        return (int) get_option('mj_backup_profile_run_' . $this->id, 0);
    }

    public function saveStatus(bool $success, string $filename, string $message): void
    {
        update_option('mj_backup_profile_status_' . $this->id, compact('success', 'filename', 'message'));
        update_option('mj_backup_profile_run_' . $this->id, time());
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'table_filter' => $this->tableFilter,
            'frequency'    => $this->frequency,
            'daily_hour'   => $this->dailyHour,
            'twicedaily_second_hour' => $this->twiceDailySecondHour,
            'weekly_day'   => $this->weeklyDay,
            'weekly_hour'  => $this->weeklyHour,
            'retention'    => $this->retention,
            'folder'       => $this->folder,
            'enabled'      => $this->enabled,
        ];
    }

    /* ------------------------------------------------------------------
     * Migration
     * ----------------------------------------------------------------*/

    /**
     * Converts legacy single-backup options to a default profile on first use.
     *
     * @return self[]
     */
    private static function migrateFromLegacy(): array
    {
        $enabled   = get_option('mj_backup_enabled', '0') === '1';
        $frequency = (string) get_option('mj_backup_frequency', 'daily');
        if (!in_array($frequency, ['daily', 'twicedaily', 'weekly'], true)) {
            $frequency = 'daily';
        }
        $retention = max(1, (int) get_option('mj_backup_retention', 7));
        $folder    = trim((string) get_option('mj_backup_nextcloud_folder', 'backups/database'), '/');
        if ($folder === '') {
            $folder = 'backups/database';
        }

        $profile  = new self([
            'id'           => 'default',
            'name'         => 'Sauvegarde complète',
            'table_filter' => 'all',
            'frequency'    => $frequency,
            'daily_hour'   => 4,
            'twicedaily_second_hour' => 18,
            'weekly_day'   => 4,
            'weekly_hour'  => 4,
            'retention'    => $retention,
            'folder'       => $folder,
            'enabled'      => $enabled,
        ]);
        $profiles = [$profile];
        self::saveAll($profiles);

        return $profiles;
    }
}
