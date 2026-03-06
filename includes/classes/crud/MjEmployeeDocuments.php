<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CRUD repository for employee documents (payslips, contracts, etc.).
 *
 * Documents are stored on disk inside data/employee-docs/ with the same
 * security model used for medical certificates (directory-level .htaccess
 * deny, randomised filenames, AJAX-gated downloads with capability checks).
 */
class MjEmployeeDocuments extends MjTools implements CrudRepositoryInterface
{
    private const TABLE = 'mj_employee_documents';

    /* ------------------------------------------------------------------ *
     * Document types                                                      *
     * ------------------------------------------------------------------ */
    public const TYPE_PAYSLIP  = 'payslip';
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_MISC     = 'misc';

    public const TYPES = [
        self::TYPE_PAYSLIP  => 'Fiche de paie',
        self::TYPE_CONTRACT => 'Emploi',
        self::TYPE_MISC     => 'Divers',
    ];

    /* ------------------------------------------------------------------ *
     * Allowed MIME types & extensions                                     *
     * ------------------------------------------------------------------ */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];

    public const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo

    /* ------------------------------------------------------------------ *
     * Upload directory                                                    *
     * ------------------------------------------------------------------ */
    public static function uploadDir(): string
    {
        return MJ_MEMBER_PATH . 'data/employee-docs/';
    }

    /**
     * Ensure the upload directory exists with a deny-all .htaccess.
     */
    public static function ensureUploadDir(): void
    {
        $dir = self::uploadDir();
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        $htaccess = $dir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
        }
        $index = $dir . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    /* ------------------------------------------------------------------ *
     * Table helpers                                                       *
     * ------------------------------------------------------------------ */
    private static function table_name(): string
    {
        if (function_exists('mj_member_get_employee_documents_table_name')) {
            return mj_member_get_employee_documents_table_name();
        }
        return self::getTableName(self::TABLE);
    }

    /* ------------------------------------------------------------------ *
     * CRUD – get_all                                                      *
     * ------------------------------------------------------------------ */
    /**
     * @param array<string,mixed> $args  Accepted keys: member_id (required).
     * @return array<int,object>
     */
    public static function get_all(array $args = array())
    {
        $memberId = isset($args['member_id']) ? (int) $args['member_id'] : 0;
        if ($memberId <= 0) {
            return [];
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE member_id = %d ORDER BY document_date DESC, created_at DESC",
            $memberId
        );

        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : [];
    }

    /* ------------------------------------------------------------------ *
     * CRUD – count                                                        *
     * ------------------------------------------------------------------ */
    /**
     * @param array<string,mixed> $args
     */
    public static function count(array $args = array())
    {
        $memberId = isset($args['member_id']) ? (int) $args['member_id'] : 0;
        if ($memberId <= 0) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return 0;
        }

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE member_id = %d", $memberId);
        $result = $wpdb->get_var($sql);
        return $result ? (int) $result : 0;
    }

    /* ------------------------------------------------------------------ *
     * CRUD – get_by_id                                                    *
     * ------------------------------------------------------------------ */
    public static function get_by_id(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();
        if (function_exists('mj_member_table_exists') && !mj_member_table_exists($table)) {
            return null;
        }

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    /* ------------------------------------------------------------------ *
     * CRUD – create                                                       *
     * ------------------------------------------------------------------ */
    /**
     * @param array<string,mixed> $data
     * @return int|false  Inserted ID on success, false on failure.
     */
    public static function create($data)
    {
        global $wpdb;
        $table = self::table_name();

        $insert = [
            'member_id'     => isset($data['member_id']) ? (int) $data['member_id'] : 0,
            'doc_type'      => isset($data['doc_type']) ? sanitize_key($data['doc_type']) : self::TYPE_MISC,
            'label'         => isset($data['label']) ? sanitize_text_field($data['label']) : '',
            'original_name' => isset($data['original_name']) ? sanitize_file_name($data['original_name']) : '',
            'stored_name'   => isset($data['stored_name']) ? sanitize_file_name($data['stored_name']) : '',
            'mime_type'     => isset($data['mime_type']) ? sanitize_mime_type($data['mime_type']) : '',
            'file_size'     => isset($data['file_size']) ? (int) $data['file_size'] : 0,
            'document_date' => isset($data['document_date']) ? sanitize_text_field($data['document_date']) : current_time('Y-m-d'),
            'payslip_month' => isset($data['payslip_month']) ? (int) $data['payslip_month'] : null,
            'payslip_year'  => isset($data['payslip_year']) ? (int) $data['payslip_year'] : null,
            'uploaded_by'   => isset($data['uploaded_by']) ? (int) $data['uploaded_by'] : 0,
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d'];

        $result = $wpdb->insert($table, $insert, $formats);
        if ($result === false) {
            return false;
        }
        return (int) $wpdb->insert_id;
    }

    /* ------------------------------------------------------------------ *
     * CRUD – update                                                       *
     * ------------------------------------------------------------------ */
    /**
     * @param int                  $id
     * @param array<string,mixed>  $data
     * @return bool
     */
    public static function update($id, $data): bool
    {
        global $wpdb;
        $table = self::table_name();

        $allowed = ['doc_type', 'label', 'document_date', 'payslip_month', 'payslip_year'];
        $update  = [];
        $formats = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            if (in_array($col, ['payslip_month', 'payslip_year', 'member_id'], true)) {
                $update[$col]  = $data[$col] !== null ? (int) $data[$col] : null;
                $formats[]     = $data[$col] !== null ? '%d' : '%s';
            } elseif ($col === 'doc_type') {
                $update[$col] = sanitize_key($data[$col]);
                $formats[]    = '%s';
            } else {
                $update[$col] = sanitize_text_field($data[$col]);
                $formats[]    = '%s';
            }
        }

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update($table, $update, ['id' => $id], $formats, ['%d']);
        return $result !== false;
    }

    /* ------------------------------------------------------------------ *
     * CRUD – delete (also removes the file from disk)                     *
     * ------------------------------------------------------------------ */
    /**
     * @param int $id
     * @return bool
     */
    public static function delete($id): bool
    {
        $doc = self::get_by_id($id);
        if (!$doc) {
            return false;
        }

        // Remove file from disk
        if (!empty($doc->stored_name)) {
            $filePath = self::uploadDir() . sanitize_file_name($doc->stored_name);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        global $wpdb;
        $table = self::table_name();
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);
        return $result !== false;
    }

    /* ------------------------------------------------------------------ *
     * Helpers                                                             *
     * ------------------------------------------------------------------ */

    /**
     * Generate a secure random filename for storage.
     */
    public static function generateStoredName(int $memberId, string $extension): string
    {
        $uniqueId  = wp_generate_password(16, false);
        $timestamp = time();
        return sprintf('%d_%s_%s.%s', $memberId, $timestamp, $uniqueId, $extension);
    }

    /**
     * Return the full disk path for a stored document.
     */
    public static function filePath(string $storedName): string
    {
        return self::uploadDir() . sanitize_file_name($storedName);
    }

    /**
     * Get type labels array for JS localisation.
     */
    public static function typeLabels(): array
    {
        return self::TYPES;
    }
}
