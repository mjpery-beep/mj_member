<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for dynamic member fields configuration.
 *
 * @since 2.60.0
 */
class MjDynamicFields extends MjTools
{
    const TABLE_NAME = 'mj_dynamic_fields';

    const TYPE_TEXT      = 'text';
    const TYPE_TEXTAREA  = 'textarea';
    const TYPE_DROPDOWN  = 'dropdown';
    const TYPE_RADIO     = 'radio';
    const TYPE_CHECKBOX  = 'checkbox';
    const TYPE_CHECKLIST = 'checklist';
    const TYPE_TITLE     = 'title';

    /**
     * @return string[]
     */
    public static function getAllowedTypes(): array
    {
        return array(
            self::TYPE_TEXT,
            self::TYPE_TEXTAREA,
            self::TYPE_DROPDOWN,
            self::TYPE_RADIO,
            self::TYPE_CHECKBOX,
            self::TYPE_CHECKLIST,
            self::TYPE_TITLE,
        );
    }

    /**
     * @return array<string,string>
     */
    public static function getTypeLabels(): array
    {
        /** Ajoute un emoji */
        return array(
            self::TYPE_TEXT      => __('📝 Texte', 'mj-member'),
            self::TYPE_TEXTAREA  => __('📝 Zone de texte', 'mj-member'),
            self::TYPE_DROPDOWN  => __('🔽 Liste déroulante', 'mj-member'),
            self::TYPE_RADIO     => __('🔘 Boutons radio', 'mj-member'),
            self::TYPE_CHECKBOX  => __('☑️ Case à cocher', 'mj-member'),
            self::TYPE_CHECKLIST => __('📋 Liste de cases à cocher', 'mj-member'),
            self::TYPE_TITLE     => __('🏷️ Titre de section', 'mj-member'),
        );
    }

    /**
     * @return string
     */
    private static function table_name(): string
    {
        return self::getTableName(self::TABLE_NAME);
    }

    /**
     * Get all fields ordered by sort_order.
     *
     * @return array<int,object>
     */
    public static function getAll(): array
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $results = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC");

        return is_array($results) ? $results : array();
    }

    /**
     * Count all fields.
     *
     * @return int
     */
    public static function count(): int
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Get a single field by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function getById(int $id): ?object
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * Get fields visible in the registration form.
     *
     * @return array<int,object>
     */
    public static function getRegistrationFields(): array
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $results = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE show_in_registration = 1 ORDER BY sort_order ASC, id ASC"
        );

        return is_array($results) ? $results : array();
    }

    /**
     * Get fields visible in the "My Information" account form.
     *
     * @return array<int,object>
     */
    public static function getAccountFields(): array
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $results = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE show_in_account = 1 ORDER BY sort_order ASC, id ASC"
        );

        return is_array($results) ? $results : array();
    }

    /**
     * Create a new dynamic field.
     *
     * @param array<string,mixed> $data
     * @return int|false
     */
    public static function create($data)
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $field_type = isset($data['field_type']) ? sanitize_text_field($data['field_type']) : self::TYPE_TEXT;
        if (!in_array($field_type, self::getAllowedTypes(), true)) {
            $field_type = self::TYPE_TEXT;
        }

        $slug = isset($data['slug']) ? sanitize_key($data['slug']) : '';
        if ($slug === '' && !empty($data['title'])) {
            $slug = sanitize_title($data['title']);
            $slug = str_replace('-', '_', $slug);
        }
        // Truncate slug to fit the varchar(120) column.
        if (mb_strlen($slug) > 120) {
            $slug = mb_substr($slug, 0, 120);
            $slug = rtrim($slug, '_');
        }

        $options_json = '';
        if (isset($data['options_list']) && is_array($data['options_list'])) {
            $options_json = wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', $data['options_list']))));
        } elseif (isset($data['options_list']) && is_string($data['options_list'])) {
            $options_json = $data['options_list'];
        }

        $max_order = (int) $wpdb->get_var("SELECT COALESCE(MAX(sort_order), 0) FROM {$table}");

        $inserted = $wpdb->insert($table, array(
            'slug'                 => $slug,
            'field_type'           => $field_type,
            'title'                => sanitize_text_field($data['title'] ?? ''),
            'description'          => sanitize_textarea_field($data['description'] ?? ''),
            'show_in_registration' => !empty($data['show_in_registration']) ? 1 : 0,
            'show_in_account'      => !empty($data['show_in_account']) ? 1 : 0,
            'is_required'          => !empty($data['is_required']) ? 1 : 0,
            'allow_other'          => !empty($data['allow_other']) ? 1 : 0,
            'other_label'          => sanitize_text_field($data['other_label'] ?? ''),
            'show_in_notes'        => !empty($data['show_in_notes']) ? 1 : 0,
            'youth_only'           => !empty($data['youth_only']) ? 1 : 0,
            'options_list'         => $options_json,
            'sort_order'           => isset($data['sort_order']) ? (int) $data['sort_order'] : $max_order + 1,
        ), array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%d'));

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /**
     * Update a dynamic field.
     *
     * @param int $id
     * @param array<string,mixed> $data
     * @return bool
     */
    public static function update($id, $data): bool
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        $fields = array();
        $formats = array();

        if (isset($data['title'])) {
            $fields['title'] = sanitize_text_field($data['title']);
            $formats[] = '%s';
        }

        if (isset($data['slug'])) {
            $fields['slug'] = sanitize_key($data['slug']);
            $formats[] = '%s';
        }

        if (isset($data['field_type'])) {
            $type = sanitize_text_field($data['field_type']);
            if (in_array($type, self::getAllowedTypes(), true)) {
                $fields['field_type'] = $type;
                $formats[] = '%s';
            }
        }

        if (isset($data['description'])) {
            $fields['description'] = sanitize_textarea_field($data['description']);
            $formats[] = '%s';
        }

        if (isset($data['show_in_registration'])) {
            $fields['show_in_registration'] = !empty($data['show_in_registration']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['show_in_account'])) {
            $fields['show_in_account'] = !empty($data['show_in_account']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['is_required'])) {
            $fields['is_required'] = !empty($data['is_required']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['allow_other'])) {
            $fields['allow_other'] = !empty($data['allow_other']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['other_label'])) {
            $fields['other_label'] = sanitize_text_field($data['other_label']);
            $formats[] = '%s';
        }

        if (isset($data['show_in_notes'])) {
            $fields['show_in_notes'] = !empty($data['show_in_notes']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['youth_only'])) {
            $fields['youth_only'] = !empty($data['youth_only']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (isset($data['options_list'])) {
            if (is_array($data['options_list'])) {
                $fields['options_list'] = wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', $data['options_list']))));
            } else {
                $fields['options_list'] = sanitize_text_field($data['options_list']);
            }
            $formats[] = '%s';
        }

        if (isset($data['sort_order'])) {
            $fields['sort_order'] = (int) $data['sort_order'];
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return false;
        }

        $result = $wpdb->update($table, $fields, array('id' => $id), $formats, array('%d'));

        return $result !== false;
    }

    /**
     * Delete a dynamic field and its values.
     *
     * @param int $id
     * @return bool
     */
    public static function delete($id): bool
    {
        $id = (int) $id;
        $wpdb = self::getWpdb();

        // Delete associated values first
        $values_table = MjDynamicFieldValues::getTableName(MjDynamicFieldValues::TABLE_NAME);
        $wpdb->delete($values_table, array('field_id' => $id), array('%d'));

        $table = self::table_name();
        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        return $result !== false;
    }

    /**
     * Reorder fields by providing an ordered list of IDs.
     *
     * @param int[] $ordered_ids
     * @return void
     */
    public static function reorder(array $ordered_ids): void
    {
        $wpdb = self::getWpdb();
        $table = self::table_name();

        foreach ($ordered_ids as $index => $id) {
            $wpdb->update(
                $table,
                array('sort_order' => $index),
                array('id' => (int) $id),
                array('%d'),
                array('%d')
            );
        }
    }

    /**
     * Decode options_list JSON for a field.
     *
     * @param object|string $field  A field row object, or the raw JSON string.
     * @return string[]
     */
    public static function decodeOptions($field): array
    {
        $raw = is_object($field) ? ($field->options_list ?? '') : (string) $field;
        if ($raw === '' || $raw === '[]') {
            return array();
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : array();
    }
}
