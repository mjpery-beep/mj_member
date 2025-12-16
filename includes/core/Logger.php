<?php

namespace Mj\Member\Core;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple structured logger writing JSON entries to the wp-uploads directory.
 */
final class Logger
{
    private const DEFAULT_CHANNEL = 'app';
    private const BASE_DIRECTORY = 'mj-member/logs';
    private const DEFAULT_FILENAME = 'mj-member.log';

    /**
     * Generic logger entry point.
     *
     * @param string $level
     * @param string $message
     * @param array<string,mixed> $context
     * @param string $channel
     */
    public static function log(string $level, string $message, array $context = array(), string $channel = self::DEFAULT_CHANNEL): void
    {
        $channel = self::sanitizeChannel($channel);
        $level = strtoupper($level);
        $entry = self::formatEntry($channel, $level, $message, $context);

        if (!self::append($channel, $entry)) {
            error_log($entry);
        }
    }

    /**
     * Log an informational message.
     *
     * @param array<string,mixed> $context
     */
    public static function info(string $message, array $context = array(), string $channel = self::DEFAULT_CHANNEL): void
    {
        self::log('INFO', $message, $context, $channel);
    }

    /**
     * Log a warning message.
     *
     * @param array<string,mixed> $context
     */
    public static function warning(string $message, array $context = array(), string $channel = self::DEFAULT_CHANNEL): void
    {
        self::log('WARNING', $message, $context, $channel);
    }

    /**
     * Log an error message.
     *
     * @param array<string,mixed> $context
     */
    public static function error(string $message, array $context = array(), string $channel = self::DEFAULT_CHANNEL): void
    {
        self::log('ERROR', $message, $context, $channel);
    }

    /**
     * Ensure channel name follows a predictable pattern.
     */
    private static function sanitizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        if ($channel === '') {
            return self::DEFAULT_CHANNEL;
        }

        if (function_exists('sanitize_key')) {
            $channel = sanitize_key($channel);
        } else {
            $channel = preg_replace('/[^a-z0-9_\-]/', '', $channel);
        }

        return $channel !== '' ? $channel : self::DEFAULT_CHANNEL;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function formatEntry(string $channel, string $level, string $message, array $context): string
    {
        $entry = array(
            'timestamp' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'channel' => $channel,
            'level' => $level,
            'message' => $message,
            'context' => self::normalizeContext($context),
        );

        $json = function_exists('wp_json_encode') ? wp_json_encode($entry, JSON_UNESCAPED_SLASHES) : json_encode($entry);
        if ($json === false) {
            $json = json_encode(array(
                'timestamp' => gmdate('c'),
                'channel' => $channel,
                'level' => $level,
                'message' => $message,
            ));
        }

        if ($json === false) {
            $json = '[' . gmdate('c') . '] ' . $channel . '.' . $level . ' ' . $message;
        }

        return $json . PHP_EOL;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof WP_Error) {
                $context[$key] = array(
                    'code' => $value->get_error_code(),
                    'message' => $value->get_error_message(),
                    'data' => $value->get_error_data(),
                );
                continue;
            }

            if (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $context[$key] = $value->toArray();
                    continue;
                }

                if (method_exists($value, '__toString')) {
                    $context[$key] = (string) $value;
                    continue;
                }

                $encoded = json_decode(json_encode($value), true);
                $context[$key] = $encoded !== null ? $encoded : get_class($value);
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                $context[$key] = json_decode(json_encode($value), true);
            }
        }

        return $context;
    }

    private static function append(string $channel, string $entry): bool
    {
        $path = self::resolveFilePath($channel);
        if (!$path) {
            return false;
        }

        $result = @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
        return $result !== false;
    }

    private static function resolveFilePath(string $channel): ?string
    {
        $directory = self::resolveDirectory();
        if (!$directory) {
            return null;
        }

        $filename = self::resolveFileName($channel);
        return trailingslashit($directory) . $filename;
    }

    private static function resolveDirectory(): ?string
    {
        if (!function_exists('wp_upload_dir')) {
            return null;
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return null;
        }

        $base = trailingslashit($uploads['basedir']) . self::BASE_DIRECTORY;
        if (!wp_mkdir_p($base)) {
            return null;
        }

        return $base;
    }

    private static function resolveFileName(string $channel): string
    {
        $map = array(
            'stripe' => 'stripe-events.log',
            'payments' => 'payments.log',
            'import' => 'import-members.log',
            'security' => 'security.log',
            self::DEFAULT_CHANNEL => self::DEFAULT_FILENAME,
        );

        $filename = isset($map[$channel]) ? $map[$channel] : 'mj-member-' . $channel . '.log';

        if (function_exists('apply_filters')) {
            $filename = apply_filters('mj_member_logger_channel_file', $filename, $channel);
        }

        return $filename;
    }
}
