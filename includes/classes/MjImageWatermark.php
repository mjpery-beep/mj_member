<?php

namespace Mj\Member\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * On-the-fly image watermarking for testimonial photos.
 *
 * Uses the GD extension only (no ImageMagick dependency). Watermarked copies are
 * generated on first request and cached under uploads/mj-testimonials-wm/ so they
 * are then served as static files. Any failure falls back to the original URL.
 */
final class MjImageWatermark
{
    public const OPTION_ENABLED  = 'mj_testimonials_watermark_enabled';
    public const OPTION_LOGO_ID  = 'mj_testimonials_watermark_logo_id';
    public const OPTION_OPACITY  = 'mj_testimonials_watermark_opacity';   // 5-100 (%)
    public const OPTION_SCALE    = 'mj_testimonials_watermark_scale';     // 5-90 (% of image width)
    public const OPTION_POSITION = 'mj_testimonials_watermark_position';  // see POSITIONS

    public const CACHE_DIR = 'mj-testimonials-wm';

    public const POSITIONS = array(
        'bottom-right',
        'bottom-left',
        'bottom-center',
        'top-right',
        'top-left',
        'center',
    );

    /** @var array<string,string> */
    private static array $urlCache = array();

    public static function isEnabled(): bool
    {
        return get_option(self::OPTION_ENABLED, '0') === '1'
            && self::isSupported()
            && self::logoPath() !== '';
    }

    /**
     * Whether the server has the GD primitives we need.
     */
    public static function isSupported(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecreatefrompng')
            && function_exists('imagecopyresampled')
            && function_exists('imagecolorat');
    }

    /**
     * Absolute path to the configured watermark PNG, or '' when unavailable.
     */
    public static function logoPath(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $resolved = '';
        $logo_id = (int) get_option(self::OPTION_LOGO_ID, 0);
        if ($logo_id <= 0) {
            return $resolved;
        }

        $path = get_attached_file($logo_id);
        if (!$path || !is_readable($path)) {
            return $resolved;
        }

        $type = wp_check_filetype($path);
        if (($type['type'] ?? '') !== 'image/png') {
            return $resolved;
        }

        $resolved = $path;
        return $resolved;
    }

    public static function opacity(): int
    {
        return max(5, min(100, (int) get_option(self::OPTION_OPACITY, 60)));
    }

    public static function scalePercent(): int
    {
        return max(5, min(90, (int) get_option(self::OPTION_SCALE, 22)));
    }

    public static function position(): string
    {
        $pos = sanitize_key((string) get_option(self::OPTION_POSITION, 'bottom-right'));
        return in_array($pos, self::POSITIONS, true) ? $pos : 'bottom-right';
    }

    /**
     * Return a watermarked URL for a source image URL (generating + caching on
     * first call). Returns the original URL untouched on any failure or when the
     * feature is disabled.
     */
    public static function watermarkUrl(string $sourceUrl): string
    {
        if ($sourceUrl === '' || !self::isEnabled()) {
            return $sourceUrl;
        }

        if (isset(self::$urlCache[$sourceUrl])) {
            return self::$urlCache[$sourceUrl];
        }

        $result = self::$urlCache[$sourceUrl] = self::generate($sourceUrl);
        return $result;
    }

    /**
     * Delete every cached watermarked file. Returns the number of files removed.
     */
    public static function purgeCache(): int
    {
        $uploads = wp_get_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . self::CACHE_DIR;
        if (!is_dir($dir)) {
            return 0;
        }

        $removed = 0;
        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file) && @unlink($file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    // ------------------------------------------------------------------ internals

    private static function generate(string $sourceUrl): string
    {
        $uploads = wp_get_upload_dir();
        if (!empty($uploads['error'])) {
            return $sourceUrl;
        }

        // Map the URL onto a local file inside the uploads directory (scheme-agnostic).
        $baseUrl = preg_replace('#^https?:#', '', (string) $uploads['baseurl']);
        $target  = preg_replace('#^https?:#', '', $sourceUrl);
        $query   = '';
        if (strpos($target, '?') !== false) {
            [$target, $query] = explode('?', $target, 2);
        }

        if ($baseUrl === '' || strpos($target, $baseUrl) !== 0) {
            return $sourceUrl;
        }

        $relPath = ltrim(substr($target, strlen($baseUrl)), '/');
        if ($relPath === '' || strpos($relPath, self::CACHE_DIR . '/') === 0) {
            // Already a cached watermarked file — leave it alone.
            return $sourceUrl;
        }

        $srcFile = trailingslashit($uploads['basedir']) . $relPath;
        if (!is_readable($srcFile)) {
            return $sourceUrl;
        }

        $ext = strtolower(pathinfo($srcFile, PATHINFO_EXTENSION));
        $supportedExt = array('jpg', 'jpeg', 'png', 'webp', 'gif');
        if (!in_array($ext, $supportedExt, true)) {
            return $sourceUrl;
        }

        $logoPath = self::logoPath();
        $opacity  = self::opacity();
        $scale    = self::scalePercent();
        $position = self::position();

        $signature = implode('-', array(
            hash('crc32b', $relPath),
            (int) @filemtime($srcFile),
            (int) @filemtime($logoPath),
            $opacity,
            $scale,
            $position,
        ));
        $cacheName = $signature . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);

        $cacheDir = trailingslashit($uploads['basedir']) . self::CACHE_DIR;
        $cacheUrl = trailingslashit($uploads['baseurl']) . self::CACHE_DIR . '/' . $cacheName;
        $cacheFile = $cacheDir . '/' . $cacheName;

        if (is_readable($cacheFile) && filesize($cacheFile) > 0) {
            return $cacheUrl . ($query !== '' ? '?' . $query : '');
        }

        if (!wp_mkdir_p($cacheDir)) {
            return $sourceUrl;
        }

        $ok = self::compose($srcFile, $logoPath, $cacheFile, $ext, $opacity, $scale, $position);
        if (!$ok || !is_readable($cacheFile)) {
            return $sourceUrl;
        }

        return $cacheUrl . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Composite the logo over the source image and write the result.
     */
    private static function compose(
        string $srcFile,
        string $logoPath,
        string $destFile,
        string $ext,
        int $opacity,
        int $scalePercent,
        string $position
    ): bool {
        $src = self::loadImage($srcFile, $ext);
        if (!$src) {
            return false;
        }

        $logo = @imagecreatefrompng($logoPath);
        if (!$logo) {
            imagedestroy($src);
            return false;
        }

        try {
            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($src);
            }
            imagealphablending($src, true);
            imagesavealpha($src, true);

            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $logoW = imagesx($logo);
            $logoH = imagesy($logo);
            if ($srcW < 1 || $srcH < 1 || $logoW < 1 || $logoH < 1) {
                return false;
            }

            // Target watermark width as a share of the image width, keeping ratio.
            $targetW = (int) round($srcW * ($scalePercent / 100));
            $targetW = max(24, min($targetW, (int) round($srcW * 0.9)));
            $targetH = max(1, (int) round($logoH * ($targetW / $logoW)));
            if ($targetH > $srcH * 0.9) {
                $targetH = (int) round($srcH * 0.9);
                $targetW = max(1, (int) round($logoW * ($targetH / $logoH)));
            }

            $scaled = imagecreatetruecolor($targetW, $targetH);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
            imagefilledrectangle($scaled, 0, 0, $targetW, $targetH, $transparent);
            imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $targetW, $targetH, $logoW, $logoH);

            if ($opacity < 100) {
                self::applyOpacity($scaled, $opacity);
            }

            $margin = max(8, (int) round($srcW * 0.03));
            [$dstX, $dstY] = self::anchor($position, $srcW, $srcH, $targetW, $targetH, $margin);

            imagealphablending($src, true);
            imagecopy($src, $scaled, $dstX, $dstY, 0, 0, $targetW, $targetH);
            imagedestroy($scaled);

            return self::saveImage($src, $destFile, $ext);
        } finally {
            imagedestroy($src);
            imagedestroy($logo);
        }
    }

    /**
     * Scale the alpha channel of every pixel so the whole layer becomes more
     * transparent. $opacity is 5-100.
     */
    private static function applyOpacity($img, int $opacity): void
    {
        $factor = max(0.0, min(1.0, $opacity / 100));
        $w = imagesx($img);
        $h = imagesy($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F; // 0 opaque .. 127 transparent
                if ($alpha === 0x7F) {
                    continue;
                }
                $newAlpha = (int) round(127 - (127 - $alpha) * $factor);
                $newAlpha = max(0, min(127, $newAlpha));
                $color = imagecolorallocatealpha(
                    $img,
                    ($rgba >> 16) & 0xFF,
                    ($rgba >> 8) & 0xFF,
                    $rgba & 0xFF,
                    $newAlpha
                );
                imagesetpixel($img, $x, $y, $color);
            }
        }
    }

    /**
     * @return array{0:int,1:int} top-left position for the watermark
     */
    private static function anchor(string $position, int $srcW, int $srcH, int $wmW, int $wmH, int $margin): array
    {
        switch ($position) {
            case 'bottom-left':
                return array($margin, $srcH - $wmH - $margin);
            case 'bottom-center':
                return array((int) round(($srcW - $wmW) / 2), $srcH - $wmH - $margin);
            case 'top-right':
                return array($srcW - $wmW - $margin, $margin);
            case 'top-left':
                return array($margin, $margin);
            case 'center':
                return array((int) round(($srcW - $wmW) / 2), (int) round(($srcH - $wmH) / 2));
            case 'bottom-right':
            default:
                return array($srcW - $wmW - $margin, $srcH - $wmH - $margin);
        }
    }

    /**
     * @return \GdImage|resource|false
     */
    private static function loadImage(string $file, string $ext)
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false;
            case 'png':
                return @imagecreatefrompng($file);
            case 'webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
            case 'gif':
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($file) : false;
        }
        return false;
    }

    /**
     * @param \GdImage|resource $img
     */
    private static function saveImage($img, string $file, string $ext): bool
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                if (!function_exists('imagejpeg')) {
                    return false;
                }
                // JPEG has no alpha — flatten onto white.
                $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
                $white = imagecolorallocate($flat, 255, 255, 255);
                imagefilledrectangle($flat, 0, 0, imagesx($img), imagesy($img), $white);
                imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                $ok = imagejpeg($flat, $file, 88);
                imagedestroy($flat);
                return (bool) $ok;
            case 'png':
                imagesavealpha($img, true);
                return function_exists('imagepng') ? (bool) imagepng($img, $file, 6) : false;
            case 'webp':
                imagesavealpha($img, true);
                return function_exists('imagewebp') ? (bool) imagewebp($img, $file, 88) : false;
            case 'gif':
                return function_exists('imagegif') ? (bool) imagegif($img, $file) : false;
        }
        return false;
    }
}
