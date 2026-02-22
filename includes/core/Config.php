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

        self::defineIfMissing('MJ_MEMBER_VERSION', '2.22.0');
        self::defineIfMissing('MJ_MEMBER_SCHEMA_VERSION', '2.64.0');
        self::defineIfMissing('MJ_MEMBER_PATH', plugin_dir_path($pluginFile));
        self::defineIfMissing('MJ_MEMBER_URL', plugin_dir_url($pluginFile));
        self::defineIfMissing('MJ_MEMBER_CAPABILITY', 'mj_manage_members');
        self::defineIfMissing('MJ_MEMBER_CONTACT_CAPABILITY', 'mj_manage_contact_messages');
        self::defineIfMissing('MJ_MEMBER_HOURS_CAPABILITY', 'mj_member_log_hours');
        self::defineIfMissing('MJ_MEMBER_TODOS_CAPABILITY', 'mj_member_manage_todos');
        self::defineIfMissing('MJ_MEMBER_DOCUMENTS_CAPABILITY', 'mj_member_manage_documents');
        self::defineIfMissing('MJ_MEMBER_GOOGLE_DRIVE_ROOT_FOLDER_ID', '');
        self::defineIfMissing('MJ_MEMBER_GOOGLE_SERVICE_ACCOUNT_JSON', '');
        self::defineIfMissing('MJ_MEMBER_GOOGLE_IMPERSONATE_USER', '');
        self::defineIfMissing('MJ_MEMBER_PAYMENT_EXPIRATION_DAYS', self::DEFAULT_PAYMENT_EXPIRATION);
        self::defineIfMissing('MJ_MEMBER_DATA_RETENTION_DAYS', 1095);
        self::defineIfMissing('MJ_MEMBER_OPENAI_API_KEY', '');
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

    public static function hoursCapability(): string
    {
        return constant('MJ_MEMBER_HOURS_CAPABILITY');
    }

    public static function todosCapability(): string
    {
        return constant('MJ_MEMBER_TODOS_CAPABILITY');
    }

    public static function documentsCapability(): string
    {
        return constant('MJ_MEMBER_DOCUMENTS_CAPABILITY');
    }

    public static function paymentExpirationDays(): int
    {
        return (int) constant('MJ_MEMBER_PAYMENT_EXPIRATION_DAYS');
    }

    public static function dataRetentionDays(): int
    {
        return (int) constant('MJ_MEMBER_DATA_RETENTION_DAYS');
    }

    public static function openAiApiKey(): string
    {
        $defined = (string) constant('MJ_MEMBER_OPENAI_API_KEY');
        if ($defined !== '') {
            return \sanitize_text_field($defined);
        }

        $option = \get_option('mj_member_openai_api_key', '');
        if (!is_string($option) || $option === '') {
            return '';
        }

        return \sanitize_text_field($option);
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

    public static function googleDriveRootFolderId(): string
    {
        $defined = (string) constant('MJ_MEMBER_GOOGLE_DRIVE_ROOT_FOLDER_ID');
        if ($defined !== '') {
            return \sanitize_text_field($defined);
        }

        $option = \get_option('mj_documents_google_root_folder_id', '');
        if (!is_string($option) || $option === '') {
            return '';
        }

        return \sanitize_text_field($option);
    }

    public static function googleDriveServiceAccountJson(): string
    {
        $defined = (string) constant('MJ_MEMBER_GOOGLE_SERVICE_ACCOUNT_JSON');
        if ($defined !== '') {
            return $defined;
        }

        $option = \get_option('mj_documents_google_service_account_json', '');
        return is_string($option) ? $option : '';
    }

    public static function googleDriveImpersonatedUser(): string
    {
        $defined = (string) constant('MJ_MEMBER_GOOGLE_IMPERSONATE_USER');
        if ($defined !== '') {
            $email = \sanitize_email($defined);
            return $email !== '' ? $email : '';
        }

        $option = \get_option('mj_documents_google_impersonate_user', '');
        if (!is_string($option) || $option === '') {
            return '';
        }

        $email = \sanitize_email($option);
        return $email !== '' ? $email : '';
    }

    public static function googleDriveIsReady(): bool
    {
        if (!class_exists('Google\\Client')) {
            return false;
        }

        $json = self::googleDriveServiceAccountJson();
        if ($json === '') {
            return false;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return false;
        }

        return isset($decoded['client_email'], $decoded['private_key']);
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
