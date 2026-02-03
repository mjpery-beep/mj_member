<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service pour gérer les points d'expérience (XP) des membres.
 *
 * XP are awarded for:
 * - Completing a badge criterion: 10 XP
 * - Completing a badge (all criteria met): 100 XP
 */
final class MjMemberXp extends MjTools
{
    /** XP awarded when a single criterion is completed */
    public const XP_PER_CRITERION = 10;

    /** XP awarded when a badge is fully completed */
    public const XP_PER_BADGE_COMPLETION = 100;

    /**
     * Get the XP total for a member.
     *
     * @param int $memberId
     * @return int
     */
    public static function get(int $memberId): int
    {
        if ($memberId <= 0) {
            return 0;
        }

        global $wpdb;
        $table = self::getTableName('mj_members');

        $xp = $wpdb->get_var($wpdb->prepare(
            "SELECT xp_total FROM {$table} WHERE id = %d",
            $memberId
        ));

        return $xp !== null ? (int) $xp : 0;
    }

    /**
     * Add XP to a member.
     *
     * @param int $memberId
     * @param int $amount  Positive amount to add.
     * @return int|WP_Error The new XP total or error.
     */
    public static function add(int $memberId, int $amount)
    {
        if ($memberId <= 0) {
            return new WP_Error('mj_member_xp_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        if ($amount <= 0) {
            return self::get($memberId);
        }

        global $wpdb;
        $table = self::getTableName('mj_members');

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET xp_total = xp_total + %d WHERE id = %d",
            $amount,
            $memberId
        ));

        if ($updated === false) {
            return new WP_Error('mj_member_xp_update_failed', __('Impossible de mettre à jour les XP.', 'mj-member'));
        }

        return self::get($memberId);
    }

    /**
     * Subtract XP from a member. XP cannot go below 0.
     *
     * @param int $memberId
     * @param int $amount  Positive amount to subtract.
     * @return int|WP_Error The new XP total or error.
     */
    public static function subtract(int $memberId, int $amount)
    {
        if ($memberId <= 0) {
            return new WP_Error('mj_member_xp_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        if ($amount <= 0) {
            return self::get($memberId);
        }

        global $wpdb;
        $table = self::getTableName('mj_members');

        // Use CASE WHEN to avoid BIGINT UNSIGNED underflow error
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET xp_total = CASE WHEN xp_total >= %d THEN xp_total - %d ELSE 0 END WHERE id = %d",
            $amount,
            $amount,
            $memberId
        ));

        if ($updated === false) {
            return new WP_Error('mj_member_xp_update_failed', __('Impossible de mettre à jour les XP.', 'mj-member'));
        }

        return self::get($memberId);
    }

    /**
     * Set a member's XP to a specific value.
     *
     * @param int $memberId
     * @param int $amount
     * @return int|WP_Error The new XP total or error.
     */
    public static function set(int $memberId, int $amount)
    {
        if ($memberId <= 0) {
            return new WP_Error('mj_member_xp_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        $amount = max(0, $amount);

        global $wpdb;
        $table = self::getTableName('mj_members');

        $updated = $wpdb->update(
            $table,
            array('xp_total' => $amount),
            array('id' => $memberId),
            array('%d'),
            array('%d')
        );

        if ($updated === false) {
            return new WP_Error('mj_member_xp_update_failed', __('Impossible de mettre à jour les XP.', 'mj-member'));
        }

        return $amount;
    }

    /**
     * Award XP for completing criteria.
     *
     * @param int $memberId
     * @param int $criteriaCount Number of criteria completed.
     * @return int|WP_Error The new XP total or error.
     */
    public static function awardForCriteria(int $memberId, int $criteriaCount)
    {
        if ($criteriaCount <= 0) {
            return self::get($memberId);
        }

        return self::add($memberId, $criteriaCount * self::XP_PER_CRITERION);
    }

    /**
     * Revoke XP for criteria that were un-awarded.
     *
     * @param int $memberId
     * @param int $criteriaCount Number of criteria revoked.
     * @return int|WP_Error The new XP total or error.
     */
    public static function revokeForCriteria(int $memberId, int $criteriaCount)
    {
        if ($criteriaCount <= 0) {
            return self::get($memberId);
        }

        return self::subtract($memberId, $criteriaCount * self::XP_PER_CRITERION);
    }

    /**
     * Award XP for completing a badge.
     *
     * @param int $memberId
     * @return int|WP_Error The new XP total or error.
     */
    public static function awardForBadgeCompletion(int $memberId)
    {
        return self::add($memberId, self::XP_PER_BADGE_COMPLETION);
    }

    /**
     * Revoke XP for a badge that is no longer complete.
     *
     * @param int $memberId
     * @return int|WP_Error The new XP total or error.
     */
    public static function revokeForBadgeCompletion(int $memberId)
    {
        return self::subtract($memberId, self::XP_PER_BADGE_COMPLETION);
    }
}
