<?php

use Mj\Member\Classes\MjNotificationManager;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_record_notification')) {
    /**
     * @param array<string,mixed> $notification_data
     * @param array<int,mixed> $recipients
     * @return array{notification_id:int,recipient_ids:array<int,int>}|WP_Error
     */
    function mj_member_record_notification(array $notification_data, array $recipients) {
        return MjNotificationManager::record($notification_data, $recipients);
    }
}

if (!function_exists('mj_member_get_member_notifications_feed')) {
    /**
     * @param int $member_id
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_member_notifications_feed($member_id, array $args = array()) {
        return MjNotificationManager::get_member_feed((int) $member_id, $args);
    }
}

if (!function_exists('mj_member_mark_member_notifications_read')) {
    /**
     * @param int $member_id
     * @param array<int,int> $notification_ids
     * @param string|null $timestamp
     * @return int|WP_Error
     */
    function mj_member_mark_member_notifications_read($member_id, array $notification_ids = array(), $timestamp = null) {
        return MjNotificationManager::mark_member_notifications_read((int) $member_id, $notification_ids, $timestamp);
    }
}

if (!function_exists('mj_member_mark_notification_recipient_status')) {
    /**
     * @param array<int,int> $recipient_ids
     * @param string $status
     * @param string|null $timestamp
     * @return int|WP_Error
     */
    function mj_member_mark_notification_recipient_status(array $recipient_ids, $status, $timestamp = null) {
        return MjNotificationManager::mark_recipient_status($recipient_ids, $status, $timestamp);
    }
}

if (!function_exists('mj_member_get_member_unread_notifications_count')) {
    /**
     * @param int $member_id
     * @return int
     */
    function mj_member_get_member_unread_notifications_count($member_id) {
        return MjNotificationManager::get_member_unread_count((int) $member_id);
    }
}

if (!function_exists('mj_member_get_user_notifications_feed')) {
    /**
     * @param int $user_id
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function mj_member_get_user_notifications_feed($user_id, array $args = array()) {
        return MjNotificationManager::get_user_feed((int) $user_id, $args);
    }
}

if (!function_exists('mj_member_get_user_unread_notifications_count')) {
    /**
     * @param int $user_id
     * @param array<string,mixed> $args
     * @return int
     */
    function mj_member_get_user_unread_notifications_count($user_id, array $args = array()) {
        return MjNotificationManager::get_user_unread_count((int) $user_id, $args);
    }
}

if (!function_exists('mj_member_mark_user_notifications_read')) {
    /**
     * @param int $user_id
     * @param array<int,int> $notification_ids
     * @param string|null $timestamp
     * @return int|WP_Error
     */
    function mj_member_mark_user_notifications_read($user_id, array $notification_ids = array(), $timestamp = null) {
        return MjNotificationManager::mark_user_notifications_read((int) $user_id, $notification_ids, $timestamp);
    }
}
