<?php

namespace Mj\Member\Classes\Crud;

use Mj\Member\Classes\MjTools;

if (!defined('ABSPATH')) {
    exit;
}

final class MjInventory extends MjTools
{
    public const STATUSES = array(
        'good' => 'Bon état',
        'damaged' => 'Abîmé',
        'broken' => 'Cassé',
    );

    private static function table(string $suffix): string
    {
        $function = 'mj_member_get_inventory_' . $suffix . '_table_name';
        return function_exists($function) ? $function() : self::getTableName('mj_inventory_' . $suffix);
    }

    public static function tableForAjax(string $suffix): string
    {
        return in_array($suffix, array('categories', 'locations'), true) ? self::table($suffix) : '';
    }

    public static function categories(): array
    {
        global $wpdb;
        $table = self::table('categories');
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC");
        return is_array($rows) ? $rows : array();
    }

    public static function locations(): array
    {
        global $wpdb;
        $table = self::table('locations');
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC");
        return is_array($rows) ? $rows : array();
    }

    public static function createCategory(string $name, string $icon = ''): int
    {
        global $wpdb;
        $result = $wpdb->insert(self::table('categories'), array(
            'name' => sanitize_text_field($name),
            'icon' => sanitize_text_field($icon),
        ), array('%s', '%s'));
        return $result === false ? 0 : (int) $wpdb->insert_id;
    }

    public static function createLocation(string $name, string $icon = '', string $description = ''): int
    {
        global $wpdb;
        $result = $wpdb->insert(self::table('locations'), array(
            'name' => sanitize_text_field($name),
            'icon' => sanitize_text_field($icon),
            'description' => sanitize_textarea_field($description),
        ), array('%s', '%s', '%s'));
        return $result === false ? 0 : (int) $wpdb->insert_id;
    }

    public static function list(array $filters = array()): array
    {
        global $wpdb;
        $items = self::table('items');
        $categories = self::table('categories');
        $locations = self::table('locations');
        $where = array('1=1');
        $values = array();

        foreach (array('category_id' => 'i.category_id', 'location_id' => 'i.location_id') as $key => $column) {
            if ($filters[$key] ?? false) {
                $where[] = $column . ' = %d';
                $values[] = absint($filters[$key]);
            }
        }
        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $where[] = 'i.status = %s';
            $values[] = sanitize_key($filters['status']);
        }
        if (!empty($filters['borrowed'])) {
            $where[] = 'i.borrowed_by IS ' . ($filters['borrowed'] === 'yes' ? 'NOT ' : '') . 'NULL';
        }
        if (!empty($filters['q'])) {
            $where[] = '(i.name LIKE %s OR i.description LIKE %s)';
            $query = '%' . $wpdb->esc_like(sanitize_text_field($filters['q'])) . '%';
            $values[] = $query;
            $values[] = $query;
        }

        $sql = "SELECT i.*, c.name AS category_name, c.icon AS category_icon, l.name AS location_name, l.icon AS location_icon
                FROM {$items} i
                LEFT JOIN {$categories} c ON c.id = i.category_id
                LEFT JOIN {$locations} l ON l.id = i.location_id
                WHERE " . implode(' AND ', $where) . ' ORDER BY i.name ASC';
        $rows = $values ? $wpdb->get_results($wpdb->prepare($sql, $values)) : $wpdb->get_results($sql);
        return is_array($rows) ? $rows : array();
    }

    public static function get(int $id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT i.*, c.name AS category_name, l.name AS location_name FROM ' . self::table('items') . ' i LEFT JOIN ' . self::table('categories') . ' c ON c.id=i.category_id LEFT JOIN ' . self::table('locations') . ' l ON l.id=i.location_id WHERE i.id=%d LIMIT 1', $id));
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        global $wpdb;
        $cleanData = self::cleanItem($data);
        $result = $wpdb->insert(self::table('items'), $cleanData, self::itemFormats($cleanData));
        return $result === false ? 0 : (int) $wpdb->insert_id;
    }

    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        $cleanData = self::cleanItem($data, false);
        return $wpdb->update(self::table('items'), $cleanData, array('id' => $id), self::itemFormats($cleanData), array('%d')) !== false;
    }

    public static function delete(int $id): bool
    {
        global $wpdb;
        $item = self::get($id);
        if (!$item || !$wpdb->delete(self::table('items'), array('id' => $id), array('%d'))) {
            return false;
        }
        if (!empty($item->slug)) {
            self::removeDirectory((string) $item->slug);
        }
        return true;
    }

    public static function borrow(int $id, int $userId): array|false
    {
        global $wpdb;
        $item = self::get($id);
        if (!$item || $item->borrowed_by) {
            return false;
        }
        $now = current_time('mysql');
        $wpdb->update(self::table('items'), array('borrowed_by' => $userId, 'borrowed_at' => $now), array('id' => $id), array('%d', '%s'), array('%d'));
        $wpdb->insert(self::table('borrow_history'), array('item_id' => $id, 'borrowed_by' => $userId, 'borrowed_at' => $now), array('%d', '%d', '%s'));
        return array('borrowed_by' => $userId, 'borrowed_at' => $now);
    }

    public static function returnItem(int $id, int $userId): bool
    {
        global $wpdb;
        $item = self::get($id);
        if (!$item || !$item->borrowed_by) {
            return false;
        }
        $now = current_time('mysql');
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table('borrow_history') . ' SET returned_at=%s, returned_by=%d WHERE item_id=%d AND returned_at IS NULL', $now, $userId, $id));
        return $wpdb->update(self::table('items'), array('borrowed_by' => null, 'borrowed_at' => null), array('id' => $id), array('%d', '%s'), array('%d')) !== false;
    }

    public static function uploadDir(string $slug = ''): string
    {
        return rtrim(MJ_MEMBER_PATH, '/\\') . '/data/inventory/' . ($slug !== '' ? trim($slug, '/\\') . '/' : '');
    }

    public static function ensureUploadDir(string $slug): string
    {
        $dir = self::uploadDir($slug);
        wp_mkdir_p($dir);
        if (!file_exists($dir . '.htaccess')) {
            file_put_contents($dir . '.htaccess', "Order deny,allow\nDeny from all\n");
        }
        if (!file_exists($dir . 'index.php')) {
            file_put_contents($dir . 'index.php', "<?php\n");
        }
        return $dir;
    }

    public static function uniqueSlug(string $name, int $ignoreId = 0): string
    {
        global $wpdb;
        $base = sanitize_title(remove_accents($name));
        $base = $base !== '' ? $base : 'objet';
        $slug = $base;
        $suffix = 2;
        while ((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table('items') . ' WHERE slug=%s AND id!=%d', $slug, $ignoreId)) > 0) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }

    public static function savePhoto(string $slug, array $file): array|false
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return false;
        }
        $type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (empty($type['type']) || strpos((string) $type['type'], 'image/') !== 0) {
            return false;
        }
        $dir = self::ensureUploadDir($slug);
        $path = $dir . 'cover.jpg';
        $source = imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$source || !function_exists('imagejpeg')) {
            return false;
        }
        imagejpeg($source, $path, 88);
        $thumbnail = self::thumbnailBase64($source);
        imagedestroy($source);
        return array('photo_path' => 'data/inventory/' . $slug . '/cover.jpg', 'thumbnail' => $thumbnail);
    }

    private static function thumbnailBase64($source): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $size = min($width, $height);
        $thumb = imagecreatetruecolor(240, 240);
        imagecopyresampled($thumb, $source, 0, 0, (int) (($width - $size) / 2), (int) (($height - $size) / 2), 240, 240, $size, $size);
        ob_start();
        imagejpeg($thumb, null, 82);
        $data = base64_encode((string) ob_get_clean());
        imagedestroy($thumb);
        return 'data:image/jpeg;base64,' . $data;
    }

    private static function removeDirectory(string $slug): void
    {
        $dir = self::uploadDir($slug);
        foreach (glob($dir . '*') ?: array() as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    private static function cleanItem(array $data, bool $includeDefaults = true): array
    {
        $result = array();
        $fields = array('slug', 'name', 'description', 'status', 'category_id', 'location_id', 'safety_note_long', 'safety_note_short', 'photo_path', 'thumbnail', 'borrowed_by', 'borrowed_at', 'created_by');
        foreach ($fields as $field) {
            if (!$includeDefaults && !array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field] ?? null;
            $result[$field] = in_array($field, array('category_id', 'location_id', 'borrowed_by', 'created_by'), true) ? ($value === null ? null : absint($value)) : ($field === 'status' ? (isset(self::STATUSES[$value]) ? $value : 'good') : ($value === null ? null : sanitize_textarea_field((string) $value)));
        }
        return $result;
    }

    private static function itemFormats(array $data): array
    {
        $integerFields = array('category_id', 'location_id', 'borrowed_by', 'created_by');
        $formats = array();
        foreach (array_keys($data) as $field) {
            $formats[] = in_array($field, $integerFields, true) ? '%d' : '%s';
        }
        return $formats;
    }
}