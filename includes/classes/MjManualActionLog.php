<?php

namespace Mj\Member\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight log storage for manually triggered backup/sync actions.
 */
final class MjManualActionLog
{
    private const OPTION_KEY = 'mj_manual_action_logs';
    private const MAX_ENTRIES = 100;

    /**
     * Add one log entry.
     *
     * @param array<string, scalar|null> $context
     */
    public static function add(string $action, bool $success, string $message, array $context = array()): void
    {
        $logs = self::getAll(self::MAX_ENTRIES);

        $userId = get_current_user_id();
        $userLogin = '';
        if ($userId > 0) {
            $user = wp_get_current_user();
            if ($user && isset($user->user_login)) {
                $userLogin = (string) $user->user_login;
            }
        }

        $sanitizedContext = array();
        foreach ($context as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $safeKey = sanitize_key((string) $key);
            if ($safeKey === '') {
                continue;
            }
            $sanitizedContext[$safeKey] = is_string($value)
                ? sanitize_text_field($value)
                : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        $logs[] = array(
            'time' => time(),
            'action' => sanitize_key($action),
            'success' => $success,
            'message' => sanitize_text_field($message),
            'context' => $sanitizedContext,
            'user_id' => $userId,
            'user_login' => sanitize_text_field($userLogin),
        );

        if (count($logs) > self::MAX_ENTRIES) {
            $logs = array_slice($logs, -self::MAX_ENTRIES);
        }

        update_option(self::OPTION_KEY, $logs, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(int $limit = 50): array
    {
        $raw = get_option(self::OPTION_KEY, array());
        if (!is_array($raw)) {
            return array();
        }

        $limit = max(1, $limit);
        $raw = array_slice($raw, -$limit);

        return array_reverse($raw);
    }

    public static function clear(): void
    {
        update_option(self::OPTION_KEY, array(), false);
    }
}
