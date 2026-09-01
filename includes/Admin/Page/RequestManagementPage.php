<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjRequestRooms;
use Mj\Member\Classes\Crud\MjRequestTypes;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestManagementPage
{
    public static function slug(): string
    {
        return 'mj_request';
    }

    public static function render(): void
    {
        RequestGuard::ensureCapabilityOrDie(Config::capability());

        if (function_exists('mj_member_ensure_request_management_tables')) {
            mj_member_ensure_request_management_tables();
        }

        $postNotice = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_request_admin_action'])) {
            $postNotice = self::handlePost();
        }

        $activeTab = self::resolveTab(isset($_GET['tab']) ? wp_unslash((string) $_GET['tab']) : '', $postNotice);
        $rooms = MjRequestRooms::get_all(array('include_inactive' => true));
        $types = MjRequestTypes::get_all(array('include_inactive' => true));

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MJ Request', 'mj-member'); ?></h1>
            <?php self::renderNotice($postNotice); ?>

            <h2 class="nav-tab-wrapper" style="margin-bottom:16px;">
                <a class="nav-tab <?php echo $activeTab === 'rooms' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => self::slug(), 'tab' => 'rooms'), admin_url('admin.php'))); ?>">
                    <?php esc_html_e('Salles', 'mj-member'); ?>
                </a>
                <a class="nav-tab <?php echo $activeTab === 'types' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => self::slug(), 'tab' => 'types'), admin_url('admin.php'))); ?>">
                    <?php esc_html_e('Types de demande', 'mj-member'); ?>
                </a>
            </h2>

            <?php if ($activeTab === 'rooms') : ?>
                <?php self::renderRoomsTab($rooms); ?>
            <?php else : ?>
                <?php self::renderTypesTab($types); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function resolveTab(string $queryTab, ?array $postNotice): string
    {
        $tab = sanitize_key($queryTab);
        if ($tab !== 'rooms' && $tab !== 'types') {
            $tab = '';
        }

        if ($tab === '' && is_array($postNotice) && !empty($postNotice['tab'])) {
            $tab = sanitize_key((string) $postNotice['tab']);
        }

        return $tab === 'types' ? 'types' : 'rooms';
    }

    /**
     * @param array<int,object> $rooms
     */
    private static function renderRoomsTab(array $rooms): void
    {
        ?>
        <div class="postbox" style="max-width: 1200px; padding: 14px 18px; margin-bottom: 16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Ajouter une salle', 'mj-member'); ?></h2>
            <form method="post" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;align-items:end;">
                <?php wp_nonce_field('mj_request_admin_manage', 'mj_request_admin_nonce'); ?>
                <input type="hidden" name="mj_request_admin_action" value="create_room">
                <input type="hidden" name="mj_request_admin_tab" value="rooms">

                <label>
                    <strong><?php esc_html_e('Nom', 'mj-member'); ?></strong>
                    <input class="regular-text" style="width:100%;" type="text" name="name" required>
                </label>
                <label>
                    <strong><?php esc_html_e('Capacité', 'mj-member'); ?></strong>
                    <input class="small-text" type="number" min="0" step="1" name="capacity" value="0">
                </label>
                <label>
                    <strong><?php esc_html_e('Ordre', 'mj-member'); ?></strong>
                    <input class="small-text" type="number" step="1" name="sort_order" value="0">
                </label>

                <label style="grid-column: span 3;">
                    <strong><?php esc_html_e('Description', 'mj-member'); ?></strong>
                    <textarea name="description" rows="2" style="width:100%;"></textarea>
                </label>

                <label>
                    <strong><?php esc_html_e('Options salle (CSV)', 'mj-member'); ?></strong>
                    <input class="regular-text" style="width:100%;" type="text" name="options_csv" placeholder="Accès au bar, Accès à la scène">
                </label>
                <label>
                    <strong><?php esc_html_e('Matériel (CSV)', 'mj-member'); ?></strong>
                    <input class="regular-text" style="width:100%;" type="text" name="materials_csv" placeholder="Projecteur, Micros">
                </label>
                <label>
                    <strong><?php esc_html_e('Statut', 'mj-member'); ?></strong><br>
                    <label><input type="checkbox" name="is_active" value="1" checked> <?php esc_html_e('Actif', 'mj-member'); ?></label>
                </label>

                <div style="grid-column: span 3;">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Ajouter la salle', 'mj-member'); ?></button>
                </div>
            </form>
        </div>

        <div class="postbox" style="max-width: 1200px; padding: 14px 18px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Salles existantes', 'mj-member'); ?></h2>
            <?php if (empty($rooms)) : ?>
                <p><?php esc_html_e('Aucune salle enregistrée.', 'mj-member'); ?></p>
            <?php endif; ?>

            <?php foreach ($rooms as $room) : ?>
                <?php
                $options = json_decode((string) ($room->options_json ?? ''), true);
                $materials = json_decode((string) ($room->materials_json ?? ''), true);
                if (!is_array($options)) {
                    $options = array();
                }
                if (!is_array($materials)) {
                    $materials = array();
                }
                ?>
                <form method="post" style="border:1px solid #dcdcde;padding:10px;border-radius:8px;margin-bottom:10px;">
                    <?php wp_nonce_field('mj_request_admin_manage', 'mj_request_admin_nonce'); ?>
                    <input type="hidden" name="mj_request_admin_action" value="update_room">
                    <input type="hidden" name="mj_request_admin_tab" value="rooms">
                    <input type="hidden" name="room_id" value="<?php echo (int) $room->id; ?>">

                    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;align-items:end;">
                        <label>
                            <strong><?php esc_html_e('Nom', 'mj-member'); ?></strong>
                            <input type="text" name="name" value="<?php echo esc_attr((string) $room->name); ?>" style="width:100%;" required>
                        </label>
                        <label>
                            <strong><?php esc_html_e('Capacité', 'mj-member'); ?></strong>
                            <input type="number" min="0" name="capacity" value="<?php echo (int) $room->capacity; ?>">
                        </label>
                        <label>
                            <strong><?php esc_html_e('Ordre', 'mj-member'); ?></strong>
                            <input type="number" name="sort_order" value="<?php echo (int) $room->sort_order; ?>">
                        </label>
                        <label>
                            <strong><?php esc_html_e('Actif', 'mj-member'); ?></strong><br>
                            <input type="checkbox" name="is_active" value="1" <?php checked(!empty($room->is_active)); ?>>
                        </label>

                        <label style="grid-column: span 4;">
                            <strong><?php esc_html_e('Description', 'mj-member'); ?></strong>
                            <textarea name="description" rows="2" style="width:100%;"><?php echo esc_textarea((string) $room->description); ?></textarea>
                        </label>

                        <label style="grid-column: span 2;">
                            <strong><?php esc_html_e('Options salle (CSV)', 'mj-member'); ?></strong>
                            <input type="text" name="options_csv" value="<?php echo esc_attr(implode(', ', array_map('strval', $options))); ?>" style="width:100%;">
                        </label>
                        <label style="grid-column: span 2;">
                            <strong><?php esc_html_e('Matériel (CSV)', 'mj-member'); ?></strong>
                            <input type="text" name="materials_csv" value="<?php echo esc_attr(implode(', ', array_map('strval', $materials))); ?>" style="width:100%;">
                        </label>
                    </div>

                    <div style="margin-top:8px;display:flex;gap:8px;">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Enregistrer', 'mj-member'); ?></button>
                    </div>
                </form>

                <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Supprimer cette salle ?', 'mj-member')); ?>');" style="margin:0 0 12px 0;">
                    <?php wp_nonce_field('mj_request_admin_manage', 'mj_request_admin_nonce'); ?>
                    <input type="hidden" name="mj_request_admin_action" value="delete_room">
                    <input type="hidden" name="mj_request_admin_tab" value="rooms">
                    <input type="hidden" name="room_id" value="<?php echo (int) $room->id; ?>">
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                </form>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param array<int,object> $types
     */
    private static function renderTypesTab(array $types): void
    {
        ?>
        <div class="postbox" style="max-width: 1200px; padding: 14px 18px; margin-bottom: 16px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Ajouter un type de demande', 'mj-member'); ?></h2>
            <form method="post" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;align-items:end;">
                <?php wp_nonce_field('mj_request_admin_manage', 'mj_request_admin_nonce'); ?>
                <input type="hidden" name="mj_request_admin_action" value="create_type">
                <input type="hidden" name="mj_request_admin_tab" value="types">

                <label>
                    <strong><?php esc_html_e('Clé', 'mj-member'); ?></strong>
                    <input class="regular-text" style="width:100%;" type="text" name="type_key" placeholder="location_salle">
                </label>
                <label>
                    <strong><?php esc_html_e('Libellé', 'mj-member'); ?></strong>
                    <input class="regular-text" style="width:100%;" type="text" name="label" required>
                </label>
                <label>
                    <strong><?php esc_html_e('Ordre', 'mj-member'); ?></strong>
                    <input class="small-text" type="number" step="1" name="sort_order" value="0">
                </label>

                <label style="grid-column: span 3;">
                    <strong><?php esc_html_e('Description', 'mj-member'); ?></strong>
                    <textarea name="description" rows="2" style="width:100%;"></textarea>
                </label>

                <label><input type="checkbox" name="allows_location" value="1"> <?php esc_html_e('Lieu', 'mj-member'); ?></label>
                <label><input type="checkbox" name="allows_materials" value="1"> <?php esc_html_e('Matériel', 'mj-member'); ?></label>
                <label><input type="checkbox" name="allows_date" value="1"> <?php esc_html_e('Date', 'mj-member'); ?></label>
                <label><input type="checkbox" name="allows_multiple_dates" value="1"> <?php esc_html_e('Plusieurs dates', 'mj-member'); ?></label>
                <label><input type="checkbox" name="requires_animateur" value="1"> <?php esc_html_e('Animateur requis', 'mj-member'); ?></label>
                <label><input type="checkbox" name="is_active" value="1" checked> <?php esc_html_e('Actif', 'mj-member'); ?></label>

                <div style="grid-column: span 3;">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Ajouter le type', 'mj-member'); ?></button>
                </div>
            </form>
        </div>

        <div class="postbox" style="max-width: 1200px; padding: 14px 18px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Types existants', 'mj-member'); ?></h2>
            <?php if (empty($types)) : ?>
                <p><?php esc_html_e('Aucun type enregistré.', 'mj-member'); ?></p>
            <?php endif; ?>

            <?php foreach ($types as $type) : ?>
                <form method="post" style="border:1px solid #dcdcde;padding:10px;border-radius:8px;margin-bottom:10px;">
                    <?php wp_nonce_field('mj_request_admin_manage', 'mj_request_admin_nonce'); ?>
                    <input type="hidden" name="mj_request_admin_action" value="update_type">
                    <input type="hidden" name="mj_request_admin_tab" value="types">
                    <input type="hidden" name="type_id" value="<?php echo (int) $type->id; ?>">

                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;align-items:end;">
                        <label>
                            <strong><?php esc_html_e('Clé', 'mj-member'); ?></strong>
                            <input type="text" disabled value="<?php echo esc_attr((string) $type->type_key); ?>" style="width:100%;">
                        </label>
                        <label>
                            <strong><?php esc_html_e('Libellé', 'mj-member'); ?></strong>
                            <input type="text" name="label" value="<?php echo esc_attr((string) $type->label); ?>" style="width:100%;" required>
                        </label>
                        <label>
                            <strong><?php esc_html_e('Ordre', 'mj-member'); ?></strong>
                            <input type="number" name="sort_order" value="<?php echo (int) $type->sort_order; ?>">
                        </label>

                        <label style="grid-column: span 3;">
                            <strong><?php esc_html_e('Description', 'mj-member'); ?></strong>
                            <textarea name="description" rows="2" style="width:100%;"><?php echo esc_textarea((string) $type->description); ?></textarea>
                        </label>

                        <label><input type="checkbox" name="allows_location" value="1" <?php checked(!empty($type->allows_location)); ?>> <?php esc_html_e('Lieu', 'mj-member'); ?></label>
                        <label><input type="checkbox" name="allows_materials" value="1" <?php checked(!empty($type->allows_materials)); ?>> <?php esc_html_e('Matériel', 'mj-member'); ?></label>
                        <label><input type="checkbox" name="allows_date" value="1" <?php checked(!empty($type->allows_date)); ?>> <?php esc_html_e('Date', 'mj-member'); ?></label>
                        <label><input type="checkbox" name="allows_multiple_dates" value="1" <?php checked(!empty($type->allows_multiple_dates)); ?>> <?php esc_html_e('Plusieurs dates', 'mj-member'); ?></label>
                        <label><input type="checkbox" name="requires_animateur" value="1" <?php checked(!empty($type->requires_animateur)); ?>> <?php esc_html_e('Animateur requis', 'mj-member'); ?></label>
                        <label><input type="checkbox" name="is_active" value="1" <?php checked(!empty($type->is_active)); ?>> <?php esc_html_e('Actif', 'mj-member'); ?></label>
                    </div>

                    <div style="margin-top:8px;display:flex;gap:8px;">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Enregistrer', 'mj-member'); ?></button>
                    </div>
                </form>

                <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Supprimer ce type ?', 'mj-member')); ?>');" style="margin:0 0 12px 0;">
                    <?php wp_nonce_field('mj_request_admin_manage', 'mj_request_admin_nonce'); ?>
                    <input type="hidden" name="mj_request_admin_action" value="delete_type">
                    <input type="hidden" name="mj_request_admin_tab" value="types">
                    <input type="hidden" name="type_id" value="<?php echo (int) $type->id; ?>">
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                </form>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @return array{message:string,type:string,tab:string}
     */
    private static function handlePost(): array
    {
        $nonce = RequestGuard::readNonce($_POST, 'mj_request_admin_nonce');
        if (!RequestGuard::verifyNonce($nonce, 'mj_request_admin_manage')) {
            return array(
                'message' => __('Action non autorisée.', 'mj-member'),
                'type' => 'error',
                'tab' => 'rooms',
            );
        }

        $action = isset($_POST['mj_request_admin_action']) ? sanitize_key((string) wp_unslash($_POST['mj_request_admin_action'])) : '';
        $tab = isset($_POST['mj_request_admin_tab']) ? sanitize_key((string) wp_unslash($_POST['mj_request_admin_tab'])) : 'rooms';
        if ($tab !== 'types') {
            $tab = 'rooms';
        }

        switch ($action) {
            case 'create_room':
                $createRoom = MjRequestRooms::create(array(
                    'name' => sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))),
                    'description' => sanitize_textarea_field(wp_unslash((string) ($_POST['description'] ?? ''))),
                    'capacity' => (int) ($_POST['capacity'] ?? 0),
                    'options_json' => self::parseCsvToArray((string) ($_POST['options_csv'] ?? '')),
                    'materials_json' => self::parseCsvToArray((string) ($_POST['materials_csv'] ?? '')),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ));
                if (is_wp_error($createRoom)) {
                    return self::errorNotice($createRoom->get_error_message(), 'rooms');
                }
                return self::successNotice(__('Salle ajoutée.', 'mj-member'), 'rooms');

            case 'update_room':
                $updateRoom = MjRequestRooms::update((int) ($_POST['room_id'] ?? 0), array(
                    'name' => sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))),
                    'description' => sanitize_textarea_field(wp_unslash((string) ($_POST['description'] ?? ''))),
                    'capacity' => (int) ($_POST['capacity'] ?? 0),
                    'options_json' => self::parseCsvToArray((string) ($_POST['options_csv'] ?? '')),
                    'materials_json' => self::parseCsvToArray((string) ($_POST['materials_csv'] ?? '')),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ));
                if (is_wp_error($updateRoom)) {
                    return self::errorNotice($updateRoom->get_error_message(), 'rooms');
                }
                return self::successNotice(__('Salle mise à jour.', 'mj-member'), 'rooms');

            case 'delete_room':
                $deleteRoom = MjRequestRooms::delete((int) ($_POST['room_id'] ?? 0));
                if (is_wp_error($deleteRoom)) {
                    return self::errorNotice($deleteRoom->get_error_message(), 'rooms');
                }
                return self::successNotice(__('Salle supprimée.', 'mj-member'), 'rooms');

            case 'create_type':
                $label = sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? '')));
                $typeKeyRaw = sanitize_key(wp_unslash((string) ($_POST['type_key'] ?? '')));
                $typeKey = $typeKeyRaw !== '' ? $typeKeyRaw : sanitize_key(remove_accents($label));

                $createType = MjRequestTypes::create(array(
                    'type_key' => $typeKey,
                    'label' => $label,
                    'description' => sanitize_textarea_field(wp_unslash((string) ($_POST['description'] ?? ''))),
                    'allows_location' => isset($_POST['allows_location']) ? 1 : 0,
                    'allows_materials' => isset($_POST['allows_materials']) ? 1 : 0,
                    'allows_date' => isset($_POST['allows_date']) ? 1 : 0,
                    'allows_multiple_dates' => isset($_POST['allows_multiple_dates']) ? 1 : 0,
                    'requires_animateur' => isset($_POST['requires_animateur']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ));

                if (is_wp_error($createType)) {
                    return self::errorNotice($createType->get_error_message(), 'types');
                }
                return self::successNotice(__('Type de demande ajouté.', 'mj-member'), 'types');

            case 'update_type':
                $updateType = MjRequestTypes::update((int) ($_POST['type_id'] ?? 0), array(
                    'label' => sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? ''))),
                    'description' => sanitize_textarea_field(wp_unslash((string) ($_POST['description'] ?? ''))),
                    'allows_location' => isset($_POST['allows_location']) ? 1 : 0,
                    'allows_materials' => isset($_POST['allows_materials']) ? 1 : 0,
                    'allows_date' => isset($_POST['allows_date']) ? 1 : 0,
                    'allows_multiple_dates' => isset($_POST['allows_multiple_dates']) ? 1 : 0,
                    'requires_animateur' => isset($_POST['requires_animateur']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                ));
                if (is_wp_error($updateType)) {
                    return self::errorNotice($updateType->get_error_message(), 'types');
                }
                return self::successNotice(__('Type de demande mis à jour.', 'mj-member'), 'types');

            case 'delete_type':
                $deleteType = MjRequestTypes::delete((int) ($_POST['type_id'] ?? 0));
                if (is_wp_error($deleteType)) {
                    return self::errorNotice($deleteType->get_error_message(), 'types');
                }
                return self::successNotice(__('Type de demande supprimé.', 'mj-member'), 'types');
        }

        return self::errorNotice(__('Action inconnue.', 'mj-member'), $tab);
    }

    /**
     * @return array<int,string>
     */
    private static function parseCsvToArray(string $value): array
    {
        $raw = wp_unslash($value);
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $clean = array();
        foreach ($parts as $part) {
            $entry = sanitize_text_field((string) $part);
            if ($entry !== '') {
                $clean[$entry] = $entry;
            }
        }

        return array_values($clean);
    }

    /**
     * @return array{message:string,type:string,tab:string}
     */
    private static function successNotice(string $message, string $tab): array
    {
        return array(
            'message' => $message,
            'type' => 'success',
            'tab' => $tab,
        );
    }

    /**
     * @return array{message:string,type:string,tab:string}
     */
    private static function errorNotice(string $message, string $tab): array
    {
        return array(
            'message' => $message,
            'type' => 'error',
            'tab' => $tab,
        );
    }

    /**
     * @param array{message:string,type:string,tab:string}|null $postNotice
     */
    private static function renderNotice(?array $postNotice): void
    {
        if (!$postNotice || empty($postNotice['message'])) {
            return;
        }

        $class = $postNotice['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr($class),
            esc_html((string) $postNotice['message'])
        );
    }
}
