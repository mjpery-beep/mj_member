<?php

namespace Mj\Member\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralises plugin configuration (versioning, paths, capabilities).
 */
final class Config
{
    private const DEFAULT_PAYMENT_EXPIRATION = 365;

    private static string $pluginFile;

    public static function bootstrap(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;

        self::defineIfMissing('MJ_MEMBER_VERSION', '2.14.0');
        self::defineIfMissing('MJ_MEMBER_SCHEMA_VERSION', '2.14.0');
        self::defineIfMissing('MJ_MEMBER_PATH', plugin_dir_path($pluginFile));
        self::defineIfMissing('MJ_MEMBER_URL', plugin_dir_url($pluginFile));
        self::defineIfMissing('MJ_MEMBER_CAPABILITY', 'mj_manage_members');
        self::defineIfMissing('MJ_MEMBER_CONTACT_CAPABILITY', 'mj_manage_contact_messages');
        self::defineIfMissing('MJ_MEMBER_PAYMENT_EXPIRATION_DAYS', self::DEFAULT_PAYMENT_EXPIRATION);
    }

    public static function version(): string
    {
        return constant('MJ_MEMBER_VERSION');
    }

    public static function schemaVersion(): string
    {
        return constant('MJ_MEMBER_SCHEMA_VERSION');
    }

    public static function path(): string
    {
        return constant('MJ_MEMBER_PATH');
    }

    public static function url(): string
    {
        return constant('MJ_MEMBER_URL');
    }

    public static function mainFile(): string
    {
        return self::$pluginFile;
    }

    public static function capability(): string
    {
        return constant('MJ_MEMBER_CAPABILITY');
    }

    public static function contactCapability(): string
    {
        return constant('MJ_MEMBER_CONTACT_CAPABILITY');
    }

    public static function paymentExpirationDays(): int
    {
        return (int) constant('MJ_MEMBER_PAYMENT_EXPIRATION_DAYS');
    }

    public static function contactFromEmailOverride(): string
    {
        if (!defined('MJ_MEMBER_CONTACT_FROM_EMAIL')) {
            return '';
        }

        $value = (string) constant('MJ_MEMBER_CONTACT_FROM_EMAIL');
        $sanitized = \sanitize_email($value);

        return $sanitized !== '' ? $sanitized : '';
    }

    public static function contactFromNameOverride(): string
    {
        if (!defined('MJ_MEMBER_CONTACT_FROM_NAME')) {
            return '';
        }

        $value = (string) constant('MJ_MEMBER_CONTACT_FROM_NAME');
        $sanitized = \sanitize_text_field($value);

        return $sanitized !== '' ? $sanitized : '';
    }

    private static function defineIfMissing(string $name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
