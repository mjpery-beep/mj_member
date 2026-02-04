<?php

namespace Mj\Member\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight PSR-4 autoloader dedicated to the plugin namespaces.
 */
final class Autoloader
{
    /** @var array<string, string> */
    private static array $prefixes = [];

    /** @var array<string, string> */
    private static array $legacyMap = [];

    private static bool $registered = false;

    /**
     * Register the autoloader for the provided namespace prefixes.
     *
     * @param array<string, string> $prefixes
     * @param array<string, string> $legacyMap
     */
    public static function register(array $prefixes, array $legacyMap = []): void
    {
        if (self::$registered) {
            return;
        }

        foreach ($prefixes as $prefix => $directory) {
            $normalizedPrefix = trim($prefix, '\\') . '\\';
            $normalizedDirectory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
            self::$prefixes[$normalizedPrefix] = $normalizedDirectory;
        }

        self::$legacyMap = $legacyMap;

        spl_autoload_register([self::class, 'loadClass']);
        self::$registered = true;
    }

    /**
     * Attempt to load a class using the configured prefixes.
     */
    private static function loadClass(string $class): void
    {
        if (isset(self::$legacyMap[$class])) {
            $class = self::$legacyMap[$class];
        }

        foreach (self::$prefixes as $prefix => $directory) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            $file = $directory . $relativePath;

            if (is_readable($file)) {
                require_once $file;
                return;
            }
        }
    }
}
