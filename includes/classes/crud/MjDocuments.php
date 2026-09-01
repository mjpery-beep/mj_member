<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class MjDocuments extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_documents';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return array(self::STATUS_ACTIVE, self::STATUS_ARCHIVED);
    }

    private static function table_name(): string
    {
        if (function_exists('mj_member_get_documents_table_name')) {
            return mj_member_get_documents_table_name();
        }

        return self::getTableName(self::TABLE);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public static function get_all(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'folder_id' => null,
            'status' => '',
            'statuses' => array(),
            'include_ids' => array(),
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        if ($args['folder_id'] !== null) {
            $builder->where_equals_int('folder_id', $args['folder_id']);
        }

        $statuses = array();
        if (!empty($args['statuses']) && is_array($args['statuses'])) {
            foreach ($args['statuses'] as $candidate) {
                $normalized = self::normalize_status($candidate);
                if ($normalized !== '') {
                    $statuses[] = $normalized;
                }
            }
        } elseif (!empty($args['status'])) {
            $single = self::normalize_status($args['status']);
            if ($single !== '') {
                $statuses[] = $single;
            }
        }

        if (!empty($statuses)) {
            $builder->where_in_strings('status', $statuses, static function ($value) {
                return self::normalize_status($value);
            });
        }

        if (!empty($args['include_ids'])) {
            $builder->where_in_int('id', (array) $args['include_ids']);
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'original_name', 'filename', 'mime_type'), (string) $args['search']);
        }

        $allowedOrderBy = array('created_at', 'updated_at', 'title', 'size_bytes', 'id');
        $orderby = sanitize_key((string) $args['orderby']);
        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'created_at';
        }

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $limit = (int) $args['limit'];
        $offset = max(0, (int) $args['offset']);

        list($sql, $params) = $builder->build_select('*', $orderby, $order, $limit, $offset);
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)) {
            return array();
        }

        return array_map(array(__CLASS__, 'format_row'), $rows);
    }

    /**
     * @param array<string,mixed> $args
     * @return int
     */
    public static function count(array $args = array())
    {
        global $wpdb;
        $table = self::table_name();

        $defaults = array(
            'folder_id' => null,
            'status' => '',
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $builder = CrudQueryBuilder::for_table($table);

        if ($args['folder_id'] !== null) {
            $builder->where_equals_int('folder_id', $args['folder_id']);
        }

        $status = self::normalize_status($args['status']);
        if ($status !== '') {
            $builder->where_equals('status', $status, static function ($value) {
                return self::normalize_status($value);
            });
        }

        if (!empty($args['search'])) {
            $builder->where_like_any(array('title', 'original_name', 'filename'), (string) $args['search']);
        }

        list($sql, $params) = $builder->build_count('*');
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $result = $wpdb->get_var($sql);
        return $result ? (int) $result : 0;
    }

    /**
     * @param mixed $data
     * @return int|WP_Error
     */
    public static function create($data)
    {
        if (!is_array($data)) {
            return new WP_Error('mj_document_invalid_payload', __('Données invalides pour le document.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $title = isset($data['title']) ? sanitize_text_field((string) $data['title']) : '';
        $original = isset($data['original_name']) ? sanitize_text_field((string) $data['original_name']) : '';
        if ($title === '' && $original !== '') {
            $title = $original;
        }
        if ($title === '') {
            return new WP_Error('mj_document_missing_title', __('Le titre du document est requis.', 'mj-member'));
        }

        $filename = isset($data['filename']) ? self::sanitize_relative_path((string) $data['filename']) : '';
        if ($filename === '') {
            return new WP_Error('mj_document_missing_filename', __('Fichier associé manquant.', 'mj-member'));
        }

        $folderId = isset($data['folder_id']) ? (int) $data['folder_id'] : 0;
        if ($folderId < 0) {
            $folderId = 0;
        }

        $status = self::normalize_status($data['status'] ?? self::STATUS_ACTIVE);
        if ($status === '') {
            $status = self::STATUS_ACTIVE;
        }

        $mimeType = isset($data['mime_type']) ? sanitize_text_field((string) $data['mime_type']) : '';
        $size = isset($data['size_bytes']) ? max(0, (int) $data['size_bytes']) : 0;
        $extension = isset($data['extension']) ? self::sanitize_extension((string) $data['extension']) : '';
        if ($extension === '' && $filename !== '') {
            $extension = self::sanitize_extension(pathinfo($filename, PATHINFO_EXTENSION));
        }

        $userId = get_current_user_id();
        $uploadedBy = isset($data['uploaded_by']) ? (int) $data['uploaded_by'] : $userId;
        if ($uploadedBy < 0) {
            $uploadedBy = 0;
        }
        $updatedBy = isset($data['updated_by']) ? (int) $data['updated_by'] : $uploadedBy;
        if ($updatedBy < 0) {
            $updatedBy = 0;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'folder_id' => $folderId,
                'title' => $title,
                'filename' => $filename,
                'original_name' => $original,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $size,
                'status' => $status,
                'uploaded_by' => $uploadedBy,
                'updated_by' => $updatedBy,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d')
        );

        if (!$inserted) {
            return new WP_Error('mj_document_insert_failed', __('Impossible d’enregistrer le document.', 'mj-member'));
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $id
     * @param mixed $data
     * @return true|WP_Error
     */
    public static function update($id, $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_document_invalid_id', __('Identifiant de document invalide.', 'mj-member'));
        }

        if (!is_array($data)) {
            return new WP_Error('mj_document_invalid_payload', __('Données invalides pour le document.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $payload = array();
        $formats = array();

        if (isset($data['title'])) {
            $title = sanitize_text_field((string) $data['title']);
            if ($title === '') {
                return new WP_Error('mj_document_missing_title', __('Le titre du document est requis.', 'mj-member'));
            }
            $payload['title'] = $title;
            $formats[] = '%s';
        }

        if (isset($data['filename'])) {
            $filename = self::sanitize_relative_path((string) $data['filename']);
            if ($filename === '') {
                return new WP_Error('mj_document_missing_filename', __('Fichier associé manquant.', 'mj-member'));
            }
            $payload['filename'] = $filename;
            $formats[] = '%s';
        }

        if (array_key_exists('folder_id', $data)) {
            $folderId = (int) $data['folder_id'];
            if ($folderId < 0) {
                $folderId = 0;
            }
            $payload['folder_id'] = $folderId;
            $formats[] = '%d';
        }

        if (isset($data['original_name'])) {
            $payload['original_name'] = sanitize_text_field((string) $data['original_name']);
            $formats[] = '%s';
        }

        if (isset($data['mime_type'])) {
            $payload['mime_type'] = sanitize_text_field((string) $data['mime_type']);
            $formats[] = '%s';
        }

        if (isset($data['extension'])) {
            $payload['extension'] = self::sanitize_extension((string) $data['extension']);
            $formats[] = '%s';
        }

        if (isset($data['size_bytes'])) {
            $payload['size_bytes'] = max(0, (int) $data['size_bytes']);
            $formats[] = '%d';
        }

        if (isset($data['status'])) {
            $status = self::normalize_status($data['status']);
            if ($status === '') {
                $status = self::STATUS_ACTIVE;
            }
            $payload['status'] = $status;
            $formats[] = '%s';
        }

        if (isset($data['updated_by'])) {
            $updatedBy = (int) $data['updated_by'];
            if ($updatedBy < 0) {
                $updatedBy = 0;
            }
            $payload['updated_by'] = $updatedBy;
            $formats[] = '%d';
        } else {
            $payload['updated_by'] = get_current_user_id();
            $formats[] = '%d';
        }

        if (empty($payload)) {
            return true;
        }

        $updated = $wpdb->update($table, $payload, array('id' => $id), $formats, array('%d'));
        if ($updated === false) {
            return new WP_Error('mj_document_update_failed', __('Impossible de mettre à jour le document.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return new WP_Error('mj_document_invalid_id', __('Identifiant de document invalide.', 'mj-member'));
        }

        global $wpdb;
        $table = self::table_name();

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if (!$deleted) {
            return new WP_Error('mj_document_delete_failed', __('Impossible de supprimer le document.', 'mj-member'));
        }

        return true;
    }

    /**
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get(int $id): ?array
    {
        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            return null;
        }

        return self::format_row($row);
    }

    /**
     * @param array<string,mixed>|object $row
     * @return array<string,mixed>
     */
    private static function format_row($row): array
    {
        if (is_object($row)) {
            $row = (array) $row;
        }

        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $folderId = isset($row['folder_id']) ? (int) $row['folder_id'] : 0;
        $size = isset($row['size_bytes']) ? max(0, (int) $row['size_bytes']) : 0;
        $status = self::normalize_status($row['status'] ?? '');

        return array(
            'id' => $id,
            'folderId' => $folderId,
            'title' => isset($row['title']) ? sanitize_text_field((string) $row['title']) : '',
            'filename' => isset($row['filename']) ? self::sanitize_relative_path((string) $row['filename']) : '',
            'originalName' => isset($row['original_name']) ? sanitize_text_field((string) $row['original_name']) : '',
            'mimeType' => isset($row['mime_type']) ? sanitize_text_field((string) $row['mime_type']) : '',
            'extension' => isset($row['extension']) ? self::sanitize_extension((string) $row['extension']) : '',
            'size' => $size,
            'status' => $status !== '' ? $status : self::STATUS_ACTIVE,
            'uploadedBy' => isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : 0,
            'updatedBy' => isset($row['updated_by']) ? (int) $row['updated_by'] : 0,
            'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updatedAt' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        );
    }

    private static function normalize_status($status): string
    {
        $value = sanitize_key((string) $status);
        if ($value === self::STATUS_ACTIVE || $value === self::STATUS_ARCHIVED) {
            return $value;
        }
        return '';
    }

    private static function sanitize_extension(string $extension): string
    {
        $sanitized = preg_replace('/[^a-z0-9]+/i', '', strtolower($extension));
        return $sanitized ? $sanitized : '';
    }

    public static function sanitize_relative_path(string $path): string
    {
        $path = str_replace('..', '', $path);
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');
        $path = trim($path);
        $path = sanitize_text_field($path);
        return $path;
    }
}
