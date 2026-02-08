<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for testimonial reactions (like Facebook reactions).
 * 
 * @package MjMember
 */
class MjTestimonialReactions {
    // Reaction types (Facebook-style emojis)
    const REACTION_LIKE = 'like';
    const REACTION_LOVE = 'love';
    const REACTION_HAHA = 'haha';
    const REACTION_WOW = 'wow';
    const REACTION_SAD = 'sad';
    const REACTION_ANGRY = 'angry';

    /**
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mj_testimonial_reactions';
    }

    /**
     * Get all available reaction types with their emojis and labels.
     *
     * @return array<string,array>
     */
    public static function get_reaction_types() {
        return array(
            self::REACTION_LIKE => array(
                'emoji' => '👍',
                'label' => __('J\'aime', 'mj-member'),
                'color' => '#1877f2',
            ),
            self::REACTION_LOVE => array(
                'emoji' => '❤️',
                'label' => __('J\'adore', 'mj-member'),
                'color' => '#f33e58',
            ),
            self::REACTION_HAHA => array(
                'emoji' => '😂',
                'label' => __('Haha', 'mj-member'),
                'color' => '#f7b125',
            ),
            self::REACTION_WOW => array(
                'emoji' => '😮',
                'label' => __('Wouah', 'mj-member'),
                'color' => '#f7b125',
            ),
            self::REACTION_SAD => array(
                'emoji' => '😢',
                'label' => __('Triste', 'mj-member'),
                'color' => '#f7b125',
            ),
            self::REACTION_ANGRY => array(
                'emoji' => '😠',
                'label' => __('Grrr', 'mj-member'),
                'color' => '#e9710f',
            ),
        );
    }

    /**
     * Validate reaction type.
     *
     * @param string $type
     * @return bool
     */
    public static function is_valid_type($type) {
        return array_key_exists($type, self::get_reaction_types());
    }

    /**
     * Add or update a reaction from a member on a testimonial.
     *
     * @param int $testimonial_id
     * @param int $member_id
     * @param string $reaction_type
     * @return bool|int
     */
    public static function react($testimonial_id, $member_id, $reaction_type) {
        global $wpdb;
        $table = self::get_table_name();

        if (!self::is_valid_type($reaction_type)) {
            return false;
        }

        // Check if member already has a reaction on this testimonial
        $existing = self::get_member_reaction($testimonial_id, $member_id);

        if ($existing) {
            if ($existing->reaction_type === $reaction_type) {
                // Same reaction = remove it (toggle off)
                return self::remove_reaction($testimonial_id, $member_id);
            }
            // Different reaction = update it
            $result = $wpdb->update(
                $table,
                array(
                    'reaction_type' => $reaction_type,
                    'reacted_at' => current_time('mysql'),
                ),
                array(
                    'testimonial_id' => (int) $testimonial_id,
                    'member_id' => (int) $member_id,
                ),
                array('%s', '%s'),
                array('%d', '%d')
            );
            return $result !== false;
        }

        // New reaction
        $result = $wpdb->insert(
            $table,
            array(
                'testimonial_id' => (int) $testimonial_id,
                'member_id' => (int) $member_id,
                'reaction_type' => $reaction_type,
                'reacted_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Remove a member's reaction from a testimonial.
     *
     * @param int $testimonial_id
     * @param int $member_id
     * @return bool
     */
    public static function remove_reaction($testimonial_id, $member_id) {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->delete(
            $table,
            array(
                'testimonial_id' => (int) $testimonial_id,
                'member_id' => (int) $member_id,
            ),
            array('%d', '%d')
        );

        return $result !== false;
    }

    /**
     * Get a member's reaction on a testimonial.
     *
     * @param int $testimonial_id
     * @param int $member_id
     * @return object|null
     */
    public static function get_member_reaction($testimonial_id, $member_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE testimonial_id = %d AND member_id = %d",
            (int) $testimonial_id,
            (int) $member_id
        ));
    }

    /**
     * Get reaction counts for a testimonial, grouped by type.
     *
     * @param int $testimonial_id
     * @return array<string,int>
     */
    public static function get_counts($testimonial_id) {
        global $wpdb;
        $table = self::get_table_name();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT reaction_type, COUNT(*) as count 
             FROM {$table} 
             WHERE testimonial_id = %d 
             GROUP BY reaction_type",
            (int) $testimonial_id
        ));

        $counts = array();
        foreach ($results as $row) {
            $counts[$row->reaction_type] = (int) $row->count;
        }

        return $counts;
    }

    /**
     * Get total reaction count for a testimonial.
     *
     * @param int $testimonial_id
     * @return int
     */
    public static function get_total_count($testimonial_id) {
        global $wpdb;
        $table = self::get_table_name();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE testimonial_id = %d",
            (int) $testimonial_id
        ));
    }

    /**
     * Get summary data for a testimonial (counts + member names for tooltip).
     *
     * @param int $testimonial_id
     * @param int $limit Number of names to return
     * @return array
     */
    public static function get_summary($testimonial_id, $limit = 3) {
        global $wpdb;
        $table = self::get_table_name();
        $members_table = MjMembers::getTableName(MjMembers::TABLE_NAME);

        $counts = self::get_counts($testimonial_id);
        $total = array_sum($counts);

        // Get top reactors
        $reactors = $wpdb->get_results($wpdb->prepare(
            "SELECT r.reaction_type, m.first_name, m.last_name
             FROM {$table} r
             LEFT JOIN {$members_table} m ON m.id = r.member_id
             WHERE r.testimonial_id = %d
             ORDER BY r.reacted_at DESC
             LIMIT %d",
            (int) $testimonial_id,
            (int) $limit
        ));

        $names = array();
        foreach ($reactors as $reactor) {
            if ($reactor->first_name) {
                $names[] = $reactor->first_name;
            }
        }

        return array(
            'counts' => $counts,
            'total' => $total,
            'top_emojis' => self::get_top_emojis($counts, 3),
            'names' => $names,
        );
    }

    /**
     * Get top N emojis by count.
     *
     * @param array $counts
     * @param int $limit
     * @return array
     */
    public static function get_top_emojis($counts, $limit = 3) {
        $types = self::get_reaction_types();
        arsort($counts);
        
        $emojis = array();
        $i = 0;
        foreach ($counts as $type => $count) {
            if ($count > 0 && isset($types[$type])) {
                $emojis[] = $types[$type]['emoji'];
                $i++;
                if ($i >= $limit) break;
            }
        }
        
        return $emojis;
    }

    /**
     * Delete all reactions for a testimonial.
     *
     * @param int $testimonial_id
     * @return bool
     */
    public static function delete_for_testimonial($testimonial_id) {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->delete(
            $table,
            array('testimonial_id' => (int) $testimonial_id),
            array('%d')
        );

        return $result !== false;
    }
}
