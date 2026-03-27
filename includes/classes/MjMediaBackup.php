<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Syncs the WordPress uploads directory to Nextcloud.
 *
 * Two modes:
 *  - rclone : fast, handles large files, requires the rclone binary on the server.
 *  - PHP WebDAV : pure PHP, uses MjNextcloud; skips files > MAX_FILE_SIZE_MB.
 *
 * Both modes are incremental: a local cache (mtime + size) avoids re-uploading
 * unchanged files on subsequent runs (rsync-like behaviour).
 */
final class MjMediaBackup
{
    private const OPTION_LAST_RUN  = 'mj_media_backup_last_run';
    private const OPTION_LAST_STATUS = 'mj_media_backup_last_status';
    private const OPTION_CACHE     = 'mj_media_backup_file_cache';
    private const OPTION_MAX_RUNTIME_SECONDS = 'mj_media_backup_max_runtime_seconds';
    private const OPTION_PAUSE_MS  = 'mj_media_backup_pause_ms';
    private const MAX_FILE_SIZE_MB = 50;
    private const DEFAULT_MAX_RUNTIME_SECONDS = 20;
    private const DEFAULT_PAUSE_MS = 120;

    /* ------------------------------------------------------------------
     * Public API
     * ----------------------------------------------------------------*/

    /** @return true|WP_Error */
    public static function run(?callable $progressFn = null): true|WP_Error
    {
        if ($progressFn) {
            $progressFn('Validation de la configuration Nextcloud...');
        }

        if (!Config::nextcloudIsReady()) {
            return new WP_Error('nextcloud_not_ready', 'Nextcloud n\'est pas configuré. Complétez la configuration dans l\'onglet Nextcloud.');
        }

        $rcloneBin = self::getRcloneBinary();
        if ($rcloneBin !== '' && self::canExec() && self::isRcloneAvailable($rcloneBin)) {
            if ($progressFn) {
                $progressFn('Mode rclone détecté, lancement de la synchronisation...');
            }
            return self::runViaRclone($rcloneBin, $progressFn);
        }

        if ($progressFn) {
            $progressFn('Mode PHP WebDAV détecté, lancement de la synchronisation...');
        }

        return self::runViaWebDAV($progressFn);
    }

    public static function getRemoteFolder(): string
    {
        $folder = trim((string) get_option('mj_media_backup_folder', 'backups/uploads'), '/');
        return $folder !== '' ? $folder : 'backups/uploads';
    }

    public static function getRcloneBinary(): string
    {
        return trim((string) get_option('mj_media_backup_rclone_binary', ''));
    }

    public static function isRcloneMode(): bool
    {
        $bin = self::getRcloneBinary();
        return $bin !== '' && self::canExec() && self::isRcloneAvailable($bin);
    }

    public static function getLastStatus(): array
    {
        $v = get_option(self::OPTION_LAST_STATUS, []);
        return is_array($v) ? $v : [];
    }

    public static function getLastRun(): int
    {
        return (int) get_option(self::OPTION_LAST_RUN, 0);
    }

    public static function clearCache(): void
    {
        delete_option(self::OPTION_CACHE);
    }

    public static function getMaxRuntimeSeconds(): int
    {
        return max(5, (int) get_option(self::OPTION_MAX_RUNTIME_SECONDS, self::DEFAULT_MAX_RUNTIME_SECONDS));
    }

    public static function getPauseMs(): int
    {
        return max(0, (int) get_option(self::OPTION_PAUSE_MS, self::DEFAULT_PAUSE_MS));
    }

    /* ------------------------------------------------------------------
     * rclone mode
     * ----------------------------------------------------------------*/

    private static function runViaRclone(string $rcloneBin, ?callable $progressFn = null): true|WP_Error
    {
        $uploadsInfo = wp_upload_dir();
        $source      = $uploadsInfo['basedir'];

        if (!is_dir($source)) {
            return new WP_Error('no_uploads', 'Dossier uploads introuvable : ' . $source);
        }

        if ($progressFn) {
            $progressFn('Préparation de la commande rclone...');
        }

        $ncUrl      = Config::nextcloudUrl();
        $ncUser     = Config::nextcloudUser();
        $ncPass     = Config::nextcloudPassword();
        $rootFolder = trim((string) get_option('mj_member_nextcloud_root_folder', ''), '/');
        $folder     = self::getRemoteFolder();

        // Build the WebDAV root URL for the service user
        $davBase    = rtrim($ncUrl, '/') . '/remote.php/dav/files/' . rawurlencode($ncUser) . '/';
        $remoteDir  = $davBase . ltrim(($rootFolder !== '' ? $rootFolder . '/' : '') . $folder . '/', '/');

        $obscuredPass = self::rcloneObscure($ncPass);
        $binEsc       = escapeshellcmd($rcloneBin);
        $sourceEsc    = escapeshellarg($source);

        // rclone connection-string syntax: no config file needed
        $remoteSpec = sprintf(
            ':webdav,url=%s,vendor=nextcloud,user=%s,pass=%s:',
            escapeshellarg($remoteDir),
            escapeshellarg($ncUser),
            escapeshellarg($obscuredPass)
        );

        $cmd = sprintf(
            '%s sync %s %s --transfers 2 --log-level INFO 2>&1',
            $binEsc,
            $sourceEsc,
            escapeshellarg($remoteSpec)
        );

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
        exec($cmd, $output, $exitCode);

        if ($progressFn) {
            $progressFn('Commande rclone terminée, analyse du résultat...');
        }

        $lastLines = implode("\n", array_slice($output, -5));
        $success   = $exitCode === 0;
        $message   = $success
            ? 'rclone sync terminé.'
            : sprintf('Erreur rclone (code %d) : %s', $exitCode, $lastLines);

        self::saveStatus($success, $message);

        if (!$success) {
            return new WP_Error('rclone_error', $message);
        }

        if ($progressFn) {
            $progressFn('Synchronisation média terminée avec succès.');
        }

        return true;
    }

    /* ------------------------------------------------------------------
     * PHP WebDAV mode
     * ----------------------------------------------------------------*/

    private static function runViaWebDAV(?callable $progressFn = null): true|WP_Error
    {
        if ($progressFn) {
            $progressFn('Connexion au service Nextcloud...');
        }

        $nc = MjNextcloud::make();
        if (is_wp_error($nc)) {
            return $nc;
        }

        $uploadsInfo = wp_upload_dir();
        $basePath    = $uploadsInfo['basedir'];

        if (!is_dir($basePath)) {
            return new WP_Error('no_uploads', 'Dossier uploads introuvable : ' . $basePath);
        }

        if ($progressFn) {
            $progressFn('Préparation du dossier distant de sauvegarde...');
        }

        $remoteBase  = self::getRemoteFolder();
        $ensure      = self::ensureFolder($nc, $remoteBase);
        if (is_wp_error($ensure)) {
            return $ensure;
        }

        $maxRuntimeSeconds = self::getMaxRuntimeSeconds();
        $pauseMs           = self::getPauseMs();
        $startedAt         = microtime(true);
        $stoppedForBudget  = false;

        $cache        = self::getCache();
        $newCache     = $cache;
        $maxBytes     = self::MAX_FILE_SIZE_MB * 1024 * 1024;
        $knownFolders = [$remoteBase => true];
        $stats        = ['uploaded' => 0, 'unchanged' => 0, 'skipped_size' => 0, 'skipped_thumbnails' => 0, 'errors' => 0, 'scanned' => 0];
        $processed    = 0;

        if ($progressFn) {
            $progressFn(sprintf('Mode lot activé : runtime max %ss, pause %dms.', $maxRuntimeSeconds, $pauseMs));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }

            $processed++;
            $stats['scanned']++;

            if ((microtime(true) - $startedAt) >= $maxRuntimeSeconds) {
                $stoppedForBudget = true;
                if ($progressFn) {
                    $progressFn(sprintf('Limite de runtime atteinte (%ss), arrêt propre du lot en cours...', $maxRuntimeSeconds));
                }
                break;
            }

            if ($progressFn && ($processed === 1 || $processed % 50 === 0)) {
                $progressFn(sprintf('Traitement des fichiers uploads... %d fichiers analysés', $processed));
            }

            $localPath    = $fileInfo->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', substr($localPath, strlen($basePath))), '/');
            $size         = $fileInfo->getSize();
            $mtime        = $fileInfo->getMTime();

            if (self::isWordPressThumbnailVariant($localPath)) {
                $stats['skipped_thumbnails']++;
                continue;
            }

            if ($size > $maxBytes) {
                $stats['skipped_size']++;
                continue;
            }

            $cacheHash                 = md5($relativePath . ':' . $size . ':' . $mtime);
            $newCache[$relativePath]   = $cacheHash;

            if (isset($cache[$relativePath]) && $cache[$relativePath] === $cacheHash) {
                $stats['unchanged']++;
                continue;
            }

            // Ensure remote subdirectory
            $relDir    = dirname($relativePath);
            $remoteDir = $relDir === '.' ? $remoteBase : rtrim($remoteBase, '/') . '/' . $relDir;

            if (!isset($knownFolders[$remoteDir])) {
                $ens = self::ensureFolder($nc, $remoteDir);
                if (is_wp_error($ens)) {
                    $stats['errors']++;
                    continue;
                }
                $knownFolders[$remoteDir] = true;
            }

            $content = file_get_contents($localPath);
            if ($content === false) {
                $stats['errors']++;
                continue;
            }

            $mime   = self::detectMime($localPath);
            $result = $nc->uploadContent($remoteDir, basename($localPath), $content, $mime);
            unset($content);

            if (is_wp_error($result)) {
                $stats['errors']++;
            } else {
                $stats['uploaded']++;
                if ($pauseMs > 0) {
                    usleep($pauseMs * 1000);
                }
            }
        }

        self::saveCache($newCache);

        $success = $stats['errors'] === 0;
        $message = sprintf(
            'WebDAV – %d envoyés, %d inchangés, %d miniatures ignorées, %d ignorés (>%dMB), %d erreurs, %d analysés.',
            $stats['uploaded'],
            $stats['unchanged'],
            $stats['skipped_thumbnails'],
            $stats['skipped_size'],
            self::MAX_FILE_SIZE_MB,
            $stats['errors'],
            $stats['scanned']
        );
        if ($stoppedForBudget) {
            $message .= ' Lot partiel terminé (limite runtime atteinte), relancez pour continuer.';
        }
        self::saveStatus($success, $message);

        if ($progressFn) {
            $progressFn('Synchronisation média terminée.');
        }

        return true;
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    private static function canExec(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('exec', $disabled, true);
    }

    private static function isRcloneAvailable(string $bin): bool
    {
        if (is_executable($bin)) {
            return true;
        }
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
        exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
        return $code === 0;
    }

    /**
     * Implements rclone's password-obscuring algorithm (AES-256-CTR, hardcoded key,
     * raw URL-safe base64 — matches rclone obscure exactly).
     *
     * Source: https://github.com/rclone/rclone/blob/master/fs/config/obscure/obscure.go
     */
    private static function rcloneObscure(string $plaintext): string
    {
        // phpcs:disable Generic.Files.LineLength
        $cryptKey  = "\x9c\x93\x5b\x48\x73\x0a\x55\x42\xfc\x46\xba\xb3\x94\x8d\x45\xf8\xbc\xea\xb2\x58\xfd\xb9\x99\x5e\x4c\xd8\xa1\x2b\xc1\xed\x73\x48";
        // phpcs:enable
        $iv        = random_bytes(16); // AES block size
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CTR', $cryptKey, OPENSSL_RAW_DATA, $iv);
        // Raw URL-safe base64, no padding (matches Go's base64.RawURLEncoding)
        return rtrim(strtr(base64_encode($iv . $encrypted), '+/', '-_'), '=');
    }

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
            $list    = $nc->listFolder($current);
            if (is_wp_error($list)) {
                $created = $nc->createFolder($parent, $part);
                if (is_wp_error($created)) {
                    return $created;
                }
            }
        }

        return true;
    }

    private static function getCache(): array
    {
        $v = get_option(self::OPTION_CACHE, []);
        return is_array($v) ? $v : [];
    }

    private static function saveCache(array $cache): void
    {
        update_option(self::OPTION_CACHE, $cache);
    }

    private static function saveStatus(bool $success, string $message): void
    {
        update_option(self::OPTION_LAST_STATUS, compact('success', 'message'));
        update_option(self::OPTION_LAST_RUN, time());
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $m = mime_content_type($path);
            if ($m !== false && $m !== '') {
                return $m;
            }
        }
        static $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',  'gif'  => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'pdf'  => 'application/pdf',
            'mp4'  => 'video/mp4', 'webm' => 'video/webm',
            'mp3'  => 'audio/mpeg', 'ogg' => 'audio/ogg',
            'zip'  => 'application/zip',
        ];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Detect generated WordPress image thumbnails like "photo-150x150.jpg".
     * We only skip the file if the corresponding original exists next to it.
     */
    private static function isWordPressThumbnailVariant(string $absolutePath): bool
    {
        $basename = basename($absolutePath);
        if (!preg_match('/^(?P<name>.+)-(?P<w>\\d+)x(?P<h>\\d+)\\.(?P<ext>jpe?g|png|gif|webp|avif)$/i', $basename, $m)) {
            return false;
        }

        $dir = dirname($absolutePath);
        $original = $dir . DIRECTORY_SEPARATOR . $m['name'] . '.' . $m['ext'];
        return is_file($original);
    }
}
