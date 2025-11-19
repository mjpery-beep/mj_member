<?php

if (!defined('ABSPATH')) {
    exit;
}

class MjEvents_CRUD {
    const TABLE = 'mj_events';
    const REG_TABLE = 'mj_event_registrations';

    const STATUS_ACTIVE = 'actif';
    const STATUS_DRAFT = 'brouillon';
    const STATUS_PAST = 'passe';

    const TYPE_STAGE = 'stage';
    const TYPE_SOIREE = 'soiree';
    const TYPE_SORTIE = 'sortie';

    /** @var array<string, string> */
    private static $columnFormats = array(
        'title' => '%s',
        'status' => '%s',
        'type' => '%s',
        'cover_id' => '%d',
        'description' => '%s',
        'age_min' => '%d',
        'age_max' => '%d',
        'date_debut' => '%s',
        'date_fin' => '%s',
        'date_fin_inscription' => '%s',
        'prix' => '%f',
        'created_at' => '%s',
        'updated_at' => '%s',
    );

    /**
     * @return array<string, string>
     */
    public static function get_status_labels() {
        return array(
            self::STATUS_ACTIVE => 'Actif',
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_PAST => 'Passe',
        );
    }

    /**
     * @return array<string, string>
     */
    public static function get_type_labels() {
        return array(
            self::TYPE_STAGE => 'Stage',
            self::TYPE_SOIREE => 'Soiree',
            self::TYPE_SORTIE => 'Sortie',
        );
    }

    /**
     * @param int $event_id
     * @return object|null
     */
    public static function find($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id);
        return $wpdb->get_row($sql);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public static function create(array $data) {
        global $wpdb;
        $table = mj_member_get_events_table_name();

        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        list($filtered, $formats) = self::prepare_for_db($data);
        if (empty($filtered)) {
            return false;
        }

        $result = $wpdb->insert($table, $filtered, $formats);
        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $event_id
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function update($event_id, array $data) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = mj_member_get_events_table_name();

        $data['updated_at'] = current_time('mysql');

        $nullable_columns = array();
        if (array_key_exists('cover_id', $data) && (int) $data['cover_id'] <= 0) {
            $nullable_columns[] = 'cover_id';
            unset($data['cover_id']);
        }
        if (array_key_exists('date_fin_inscription', $data) && empty($data['date_fin_inscription'])) {
            $nullable_columns[] = 'date_fin_inscription';
            unset($data['date_fin_inscription']);
        }

        list($filtered, $formats) = self::prepare_for_db($data);
        if (empty($filtered) && empty($nullable_columns)) {
            return false;
        }

        $result = true;
        if (!empty($filtered)) {
            $update_result = $wpdb->update($table, $filtered, array('id' => $event_id), $formats, array('%d'));
            $result = ($update_result !== false);
        }

        if ($result && !empty($nullable_columns)) {
            foreach ($nullable_columns as $column) {
                if (!isset(self::$columnFormats[$column])) {
                    continue;
                }
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET {$column} = NULL WHERE id = %d", $event_id));
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_default_values() {
        return array(
            'title' => '',
            'status' => self::STATUS_DRAFT,
            'type' => self::TYPE_STAGE,
            'cover_id' => 0,
            'description' => '',
            'age_min' => 12,
            'age_max' => 26,
            'date_debut' => '',
            'date_fin' => '',
            'date_fin_inscription' => '',
            'prix' => '0.00',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private static function prepare_for_db(array $data) {
        $prepared = array();
        $formats = array();

        foreach (self::$columnFormats as $column => $format) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];

            if ($column === 'description' && $value === null) {
                $value = '';
            }

            if ($value === null || $value === '') {
                if (in_array($column, array('description', 'title'), true)) {
                    $prepared[$column] = '';
                    $formats[] = $format;
                }
                continue;
            }

            switch ($column) {
                case 'cover_id':
                case 'age_min':
                case 'age_max':
                    $value = (int) $value;
                    if ($value <= 0 && $column === 'cover_id') {
                        continue 2;
                    }
                    break;
                case 'prix':
                    $value = (float) $value;
                    break;
                default:
                    $value = is_string($value) ? $value : (string) $value;
                    break;
            }

            $prepared[$column] = $value;
            $formats[] = $format;
        }

        return array($prepared, $formats);
    }
}
