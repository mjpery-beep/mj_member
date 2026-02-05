<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service pour gérer les coins des membres.
 *
 * Coins are awarded based on the coins value defined on each criterion, badge, or trophy.
 */
final class MjMemberCoins extends MjTools
{
    /**
     * Get the coins total for a member.
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

        $coins = $wpdb->get_var($wpdb->prepare(
            "SELECT coins_total FROM {$table} WHERE id = %d",
            $memberId
        ));

        return $coins !== null ? (int) $coins : 0;
    }

    /**
     * Add coins to a member.
     *
     * @param int $memberId
     * @param int $amount  Positive amount to add.
     * @return int|WP_Error The new coins total or error.
     */
    public static function add(int $memberId, int $amount)
    {
        if ($memberId <= 0) {
            return new WP_Error('mj_member_coins_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        if ($amount <= 0) {
            return self::get($memberId);
        }

        global $wpdb;
        $table = self::getTableName('mj_members');

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET coins_total = coins_total + %d WHERE id = %d",
            $amount,
            $memberId
        ));

        if ($updated === false) {
            return new WP_Error('mj_member_coins_update_failed', __('Impossible de mettre à jour les coins.', 'mj-member'));
        }

        return self::get($memberId);
    }

    /**
     * Subtract coins from a member. Coins cannot go below 0.
     *
     * @param int $memberId
     * @param int $amount  Positive amount to subtract.
     * @return int|WP_Error The new coins total or error.
     */
    public static function subtract(int $memberId, int $amount)
    {
        if ($memberId <= 0) {
            return new WP_Error('mj_member_coins_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        if ($amount <= 0) {
            return self::get($memberId);
        }

        global $wpdb;
        $table = self::getTableName('mj_members');

        // Use CASE WHEN to avoid BIGINT UNSIGNED underflow error
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET coins_total = CASE WHEN coins_total >= %d THEN coins_total - %d ELSE 0 END WHERE id = %d",
            $amount,
            $amount,
            $memberId
        ));

        if ($updated === false) {
            return new WP_Error('mj_member_coins_update_failed', __('Impossible de mettre à jour les coins.', 'mj-member'));
        }

        return self::get($memberId);
    }

    /**
     * Set a member's coins to a specific value.
     *
     * @param int $memberId
     * @param int $amount
     * @return int|WP_Error The new coins total or error.
     */
    public static function set(int $memberId, int $amount)
    {
        if ($memberId <= 0) {
            return new WP_Error('mj_member_coins_invalid_member', __('Membre invalide.', 'mj-member'));
        }

        $amount = max(0, $amount);

        global $wpdb;
        $table = self::getTableName('mj_members');

        $updated = $wpdb->update(
            $table,
            array('coins_total' => $amount),
            array('id' => $memberId),
            array('%d'),
            array('%d')
        );

        if ($updated === false) {
            return new WP_Error('mj_member_coins_update_failed', __('Impossible de mettre à jour les coins.', 'mj-member'));
        }

        return $amount;
    }

    /**
     * Award coins for completing criteria based on each criterion's coins value.
     *
     * @param int $memberId
     * @param array<int> $criterionIds Array of criterion IDs that were awarded.
     * @return int|WP_Error The new coins total or error.
     */
    public static function awardForCriteria(int $memberId, array $criterionIds)
    {
        if (empty($criterionIds)) {
            return self::get($memberId);
        }

        $totalCoins = 0;
        foreach ($criterionIds as $criterionId) {
            $criterion = MjBadgeCriteria::get_by_id((int) $criterionId);
            if ($criterion && isset($criterion['coins'])) {
                $totalCoins += (int) $criterion['coins'];
            }
        }

        if ($totalCoins <= 0) {
            return self::get($memberId);
        }

        return self::add($memberId, $totalCoins);
    }

    /**
     * Revoke coins for criteria that were un-awarded based on each criterion's coins value.
     *
     * @param int $memberId
     * @param array<int> $criterionIds Array of criterion IDs that were revoked.
     * @return int|WP_Error The new coins total or error.
     */
    public static function revokeForCriteria(int $memberId, array $criterionIds)
    {
        if (empty($criterionIds)) {
            return self::get($memberId);
        }

        $totalCoins = 0;
        foreach ($criterionIds as $criterionId) {
            $criterion = MjBadgeCriteria::get_by_id((int) $criterionId);
            if ($criterion && isset($criterion['coins'])) {
                $totalCoins += (int) $criterion['coins'];
            }
        }

        if ($totalCoins <= 0) {
            return self::get($memberId);
        }

        return self::subtract($memberId, $totalCoins);
    }

    /**
     * Award coins for completing a badge based on the badge's coins value.
     *
     * @param int $memberId
     * @param int $badgeId
     * @return int|WP_Error The new coins total or error.
     */
    public static function awardForBadgeCompletion(int $memberId, int $badgeId)
    {
        $badge = MjBadges::get($badgeId);
        if (!$badge || empty($badge['coins'])) {
            return self::get($memberId);
        }

        return self::add($memberId, (int) $badge['coins']);
    }

    /**
     * Revoke coins for a badge that is no longer complete.
     *
     * @param int $memberId
     * @param int $badgeId
     * @return int|WP_Error The new coins total or error.
     */
    public static function revokeForBadgeCompletion(int $memberId, int $badgeId)
    {
        $badge = MjBadges::get($badgeId);
        if (!$badge || empty($badge['coins'])) {
            return self::get($memberId);
        }

        return self::subtract($memberId, (int) $badge['coins']);
    }

    /**
     * Award coins for earning a trophy.
     *
     * @param int $memberId
     * @param int $trophyId
     * @return int|WP_Error The new coins total or error.
     */
    public static function awardForTrophy(int $memberId, int $trophyId)
    {
        $trophy = MjTrophies::get($trophyId);
        if (!$trophy || empty($trophy['coins'])) {
            return self::get($memberId);
        }

        return self::add($memberId, (int) $trophy['coins']);
    }

    /**
     * Revoke coins for a trophy that was un-awarded.
     *
     * @param int $memberId
     * @param int $trophyId
     * @return int|WP_Error The new coins total or error.
     */
    public static function revokeForTrophy(int $memberId, int $trophyId)
    {
        $trophy = MjTrophies::get($trophyId);
        if (!$trophy || empty($trophy['coins'])) {
            return self::get($memberId);
        }

        return self::subtract($memberId, (int) $trophy['coins']);
    }

    /**
     * Award coins for reaching a new level.
     *
     * @param int $memberId
     * @param int $levelNumber The level number that was reached.
     * @return int|WP_Error The new coins total or error.
     */
    public static function awardForLevelUp(int $memberId, int $levelNumber)
    {
        $level = MjLevels::get_by_number($levelNumber);
        if (!$level || empty($level['coins'])) {
            return self::get($memberId);
        }

        return self::add($memberId, (int) $level['coins']);
    }

    /**
     * Revoke coins when dropping back to a previous level (level down).
     *
     * @param int $memberId
     * @param int $levelNumber The level number that was lost.
     * @return int|WP_Error The new coins total or error.
     */
    public static function revokeForLevelDown(int $memberId, int $levelNumber)
    {
        $level = MjLevels::get_by_number($levelNumber);
        if (!$level || empty($level['coins'])) {
            return self::get($memberId);
        }

        return self::subtract($memberId, (int) $level['coins']);
    }
}
