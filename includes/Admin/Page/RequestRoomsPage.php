<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjRequestRooms;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestRoomsPage
{
    public static function slug(): string
    {
        return 'mj_request_rooms';
    }

    public static function render(): void
    {
        RequestGuard::ensureCapabilityOrDie(Config::capability());

        if (function_exists('mj_member_ensure_request_management_tables')) {
            mj_member_ensure_request_management_tables();
        }

        self::enqueueAssets();

        $notice = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_request_rooms_action'])) {
            $notice = self::handlePost();
        }

        $rooms = MjRequestRooms::get_all(array('include_inactive' => true));
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editRoom = $editId > 0 ? MjRequestRooms::find($editId) : null;

        ?>
        <div class="wrap mj-request-admin mj-request-rooms-admin">
            <h1 class="wp-heading-inline mj-request-rooms-admin__title"><?php esc_html_e('MJ Request - Salles', 'mj-member'); ?></h1>
            <a href="<?php echo esc_url(add_query_arg(array('page' => self::slug()), admin_url('admin.php'))); ?>" class="page-title-action mj-request-rooms-admin__add"><?php esc_html_e('Ajouter', 'mj-member'); ?></a>
            <hr class="wp-header-end">

            <?php self::renderNotice($notice); ?>

            <?php self::renderForm($editRoom); ?>

            <h2 class="mj-request-rooms-admin__section-title"><?php esc_html_e('Salles existantes', 'mj-member'); ?></h2>
            <table class="wp-list-table widefat fixed striped mj-request-rooms-admin__table">
                <thead>
                    <tr>
                        <th style="width:20%;"><?php esc_html_e('Nom', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Description', 'mj-member'); ?></th>
                        <th style="width:8%;"><?php esc_html_e('Capacité', 'mj-member'); ?></th>
                        <th style="width:14%;"><?php esc_html_e('Options', 'mj-member'); ?></th>
                        <th style="width:14%;"><?php esc_html_e('Matériel', 'mj-member'); ?></th>
                        <th style="width:7%;"><?php esc_html_e('Ordre', 'mj-member'); ?></th>
                        <th style="width:8%;"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                        <th style="width:14%;"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)) : ?>
                        <tr><td colspan="8"><?php esc_html_e('Aucune salle trouvée.', 'mj-member'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($rooms as $room) : ?>
                            <?php
                            $options = self::normalizeCatalogItems(json_decode((string) ($room->options_json ?? '[]'), true));
                            $materials = self::normalizeCatalogItems(json_decode((string) ($room->materials_json ?? '[]'), true));
                            $editUrl = add_query_arg(
                                array(
                                    'page' => self::slug(),
                                    'edit' => (int) $room->id,
                                ),
                                admin_url('admin.php')
                            );
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html(self::emojiLabel(isset($room->emoji) ? (string) $room->emoji : '', (string) $room->name)); ?></strong></td>
                                <td><?php echo esc_html(self::descriptionExcerpt((string) $room->description)); ?></td>
                                <td><?php echo (int) $room->capacity; ?></td>
                                <td><?php echo esc_html(self::catalogSummary($options)); ?></td>
                                <td><?php echo esc_html(self::catalogSummary($materials)); ?></td>
                                <td><?php echo (int) $room->sort_order; ?></td>
                                <td><?php echo !empty($room->is_active) ? esc_html__('Actif', 'mj-member') : esc_html__('Inactif', 'mj-member'); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url($editUrl); ?>"><?php esc_html_e('Modifier', 'mj-member'); ?></a>
                                    <form method="post" class="mj-request-rooms-admin__inline-form" onsubmit="return confirm('<?php echo esc_js(__('Supprimer cette salle ?', 'mj-member')); ?>');">
                                        <?php wp_nonce_field('mj_request_rooms_manage', 'mj_request_rooms_nonce'); ?>
                                        <input type="hidden" name="mj_request_rooms_action" value="delete">
                                        <input type="hidden" name="room_id" value="<?php echo (int) $room->id; ?>">
                                        <button type="submit" class="button button-small"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function renderForm(?object $room): void
    {
        $isEdit = $room !== null;
        $emoji = $isEdit ? (string) ($room->emoji ?? '') : '';
        $name = $isEdit ? (string) ($room->name ?? '') : '';
        $description = $isEdit ? (string) ($room->description ?? '') : '';
        $capacity = $isEdit ? (int) ($room->capacity ?? 0) : 0;
        $sortOrder = $isEdit ? (int) ($room->sort_order ?? 0) : 0;
        $isActive = $isEdit ? !empty($room->is_active) : true;

        $options = array();
        $materials = array();
        $photoIds = array();
        if ($isEdit) {
            $decodedOptions = json_decode((string) ($room->options_json ?? '[]'), true);
            $decodedMaterials = json_decode((string) ($room->materials_json ?? '[]'), true);
            $decodedPhotoIds = json_decode((string) ($room->photo_ids_json ?? '[]'), true);
            $options = self::normalizeCatalogItems($decodedOptions);
            $materials = self::normalizeCatalogItems($decodedMaterials);
            if (is_array($decodedPhotoIds)) {
                foreach ($decodedPhotoIds as $photoId) {
                    $id = (int) $photoId;
                    if ($id > 0) {
                        $photoIds[$id] = $id;
                    }
                }
                $photoIds = array_values($photoIds);
            }
        }

        ?>
        <div class="postbox mj-request-rooms-admin__formbox">
            <h2 class="mj-request-rooms-admin__form-title"><?php echo $isEdit ? esc_html__('Modifier la salle', 'mj-member') : esc_html__('Ajouter une salle', 'mj-member'); ?></h2>
            <form method="post" class="mj-request-rooms-admin__form">
                <?php wp_nonce_field('mj_request_rooms_manage', 'mj_request_rooms_nonce'); ?>
                <input type="hidden" name="mj_request_rooms_action" value="save">
                <?php if ($isEdit) : ?>
                    <input type="hidden" name="room_id" value="<?php echo (int) $room->id; ?>">
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Emoji', 'mj-member'); ?></th>
                            <td><?php self::renderEmojiField('emoji', $emoji, 'mj-request-room-emoji', 'mj-request-room-emoji-hint'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-room-name"><?php esc_html_e('Nom', 'mj-member'); ?></label></th>
                            <td><input id="mj-request-room-name" name="name" type="text" class="regular-text" value="<?php echo esc_attr($name); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-room-description-editor"><?php esc_html_e('Description', 'mj-member'); ?></label></th>
                            <td>
                                <?php
                                wp_editor(
                                    $description,
                                    'mj-request-room-description-editor',
                                    self::editorSettings('description', 8)
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-room-capacity"><?php esc_html_e('Capacité', 'mj-member'); ?></label></th>
                            <td><input id="mj-request-room-capacity" name="capacity" type="number" min="0" value="<?php echo (int) $capacity; ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Options', 'mj-member'); ?></th>
                            <td><?php self::renderCatalogField('options_items', $options, __('Ajouter une option', 'mj-member')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Matériel', 'mj-member'); ?></th>
                            <td><?php self::renderCatalogField('materials_items', $materials, __('Ajouter un matériel', 'mj-member')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Images', 'mj-member'); ?></th>
                            <td><?php self::renderPhotoField($photoIds); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-room-sort-order"><?php esc_html_e('Ordre', 'mj-member'); ?></label></th>
                            <td><input id="mj-request-room-sort-order" name="sort_order" type="number" value="<?php echo (int) $sortOrder; ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                            <td><label><input name="is_active" type="checkbox" value="1" <?php checked($isActive); ?>> <?php esc_html_e('Actif', 'mj-member'); ?></label></td>
                        </tr>
                    </tbody>
                </table>

                <p class="mj-request-rooms-admin__form-actions">
                    <button type="submit" class="button button-primary"><?php echo $isEdit ? esc_html__('Mettre à jour', 'mj-member') : esc_html__('Ajouter', 'mj-member'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * @return array{message:string,type:string}
     */
    private static function handlePost(): array
    {
        $nonce = RequestGuard::readNonce($_POST, 'mj_request_rooms_nonce');
        if (!RequestGuard::verifyNonce($nonce, 'mj_request_rooms_manage')) {
            return array(
                'message' => __('Action non autorisée.', 'mj-member'),
                'type' => 'error',
            );
        }

        $action = isset($_POST['mj_request_rooms_action']) ? sanitize_key((string) wp_unslash($_POST['mj_request_rooms_action'])) : '';

        if ($action === 'delete') {
            $result = MjRequestRooms::delete((int) ($_POST['room_id'] ?? 0));
            if (is_wp_error($result)) {
                return array('message' => $result->get_error_message(), 'type' => 'error');
            }

            return array('message' => __('Salle supprimée.', 'mj-member'), 'type' => 'success');
        }

        if ($action !== 'save') {
            return array('message' => __('Action inconnue.', 'mj-member'), 'type' => 'error');
        }

        $payload = array(
            'emoji' => wp_unslash((string) ($_POST['emoji'] ?? '')),
            'name' => sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))),
            'description' => wp_unslash((string) ($_POST['description'] ?? '')),
            'capacity' => (int) ($_POST['capacity'] ?? 0),
            'options_json' => self::parseCatalogItems(isset($_POST['options_items']) ? $_POST['options_items'] : array()),
            'materials_json' => self::parseCatalogItems(isset($_POST['materials_items']) ? $_POST['materials_items'] : array()),
            'photo_ids_json' => self::parseIdsCsv((string) ($_POST['photo_ids_csv'] ?? '')),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        $roomId = (int) ($_POST['room_id'] ?? 0);
        if ($roomId > 0) {
            $result = MjRequestRooms::update($roomId, $payload);
            if (is_wp_error($result)) {
                return array('message' => $result->get_error_message(), 'type' => 'error');
            }

            return array('message' => __('Salle mise à jour.', 'mj-member'), 'type' => 'success');
        }

        $created = MjRequestRooms::create($payload);
        if (is_wp_error($created)) {
            return array('message' => $created->get_error_message(), 'type' => 'error');
        }

        return array('message' => __('Salle ajoutée.', 'mj-member'), 'type' => 'success');
    }

    /**
     * @return array<int,string>
     */
    private static function parseCsv(string $value): array
    {
        $parts = array_map('trim', explode(',', wp_unslash($value)));
        $clean = array();
        foreach ($parts as $part) {
            $item = sanitize_text_field((string) $part);
            if ($item !== '') {
                $clean[$item] = $item;
            }
        }

        return array_values($clean);
    }

    /**
     * @param mixed $items
     * @return array<int,array{title:string,emoji:string,photo_id:int}>
     */
    private static function normalizeCatalogItems($items): array
    {
        if (!is_array($items)) {
            return array();
        }

        $out = array();
        foreach ($items as $item) {
            $title = '';
            $emoji = '';
            $photoId = 0;

            if (is_scalar($item) || (is_object($item) && method_exists($item, '__toString'))) {
                $title = sanitize_text_field((string) $item);
            } elseif (is_array($item)) {
                $title = sanitize_text_field((string) ($item['title'] ?? ($item['label'] ?? '')));
                $emoji = self::sanitizeEmoji((string) ($item['emoji'] ?? ''));
                $photoId = (int) ($item['photo_id'] ?? ($item['photoId'] ?? 0));
            }

            if ($title === '') {
                continue;
            }

            $out[] = array(
                'title' => $title,
                'emoji' => $emoji,
                'photo_id' => max(0, $photoId),
            );
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return array<int,array{title:string,emoji:string,photo_id:int}>
     */
    private static function parseCatalogItems($rows): array
    {
        if (!is_array($rows)) {
            return array();
        }

        $normalized = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = sanitize_text_field(wp_unslash((string) ($row['title'] ?? '')));
            if ($title === '') {
                continue;
            }

            $emoji = self::sanitizeEmoji(wp_unslash((string) ($row['emoji'] ?? '')));
            $photoId = (int) ($row['photo_id'] ?? 0);

            $normalized[] = array(
                'title' => $title,
                'emoji' => $emoji,
                'photo_id' => max(0, $photoId),
            );
        }

        return $normalized;
    }

    /**
     * @return array<int,int>
     */
    private static function parseIdsCsv(string $value): array
    {
        $parts = array_map('trim', explode(',', wp_unslash($value)));
        $ids = array();
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param array{message:string,type:string}|null $notice
     */
    private static function renderNotice(?array $notice): void
    {
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }

        $class = (isset($notice['type']) && $notice['type'] === 'error') ? 'notice notice-error' : 'notice notice-success';
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html((string) $notice['message']));
    }

    private static function enqueueAssets(): void
    {
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        wp_enqueue_style('mj-member-event-form');
        wp_enqueue_style('mj-member-registration-manager');

        $stylePath = Config::path() . 'css/request-rooms-admin.css';
        $styleVersion = file_exists($stylePath) ? (string) filemtime($stylePath) : Config::version();

        wp_register_style(
            'mj-member-request-rooms-admin',
            Config::url() . 'css/request-rooms-admin.css',
            array('mj-member-event-form'),
            $styleVersion
        );
        wp_enqueue_style('mj-member-request-rooms-admin');

        wp_enqueue_script('mj-member-preact');
        wp_enqueue_script('mj-member-preact-hooks');
        wp_enqueue_script('mj-member-regmgr-emoji-picker');

        $scriptPath = Config::path() . 'includes/js/admin-request-emoji.js';
        $scriptVersion = file_exists($scriptPath) ? (string) filemtime($scriptPath) : Config::version();

        wp_register_script(
            'mj-member-request-admin-emoji',
            Config::url() . 'includes/js/admin-request-emoji.js',
            array('mj-member-preact', 'mj-member-preact-hooks', 'mj-member-regmgr-emoji-picker'),
            $scriptVersion,
            true
        );

        wp_localize_script('mj-member-request-admin-emoji', 'mjRequestAdminEmoji', self::emojiStrings());
        wp_enqueue_script('mj-member-request-admin-emoji');

        $mediaScriptPath = Config::path() . 'includes/js/admin-request-room-media.js';
        $mediaScriptVersion = file_exists($mediaScriptPath) ? (string) filemtime($mediaScriptPath) : Config::version();

        wp_register_script(
            'mj-member-request-admin-room-media',
            Config::url() . 'includes/js/admin-request-room-media.js',
            array('jquery'),
            $mediaScriptVersion,
            true
        );

        wp_localize_script('mj-member-request-admin-room-media', 'mjRequestRoomMedia', self::mediaStrings());
        wp_enqueue_script('mj-member-request-admin-room-media');

        $catalogScriptPath = Config::path() . 'includes/js/admin-request-room-catalog.js';
        $catalogScriptVersion = file_exists($catalogScriptPath) ? (string) filemtime($catalogScriptPath) : Config::version();

        wp_register_script(
            'mj-member-request-admin-room-catalog',
            Config::url() . 'includes/js/admin-request-room-catalog.js',
            array('jquery', 'mj-member-request-admin-room-media', 'mj-member-request-admin-emoji'),
            $catalogScriptVersion,
            true
        );

        wp_localize_script('mj-member-request-admin-room-catalog', 'mjRequestRoomCatalog', self::catalogStrings());
        wp_enqueue_script('mj-member-request-admin-room-catalog');
    }

    /**
     * @param array<int,int> $photoIds
     */
    private static function renderPhotoField(array $photoIds): void
    {
        $csv = implode(',', array_map('strval', $photoIds));
        ?>
        <div class="mj-request-room-media" data-room-media-field>
            <input type="hidden" id="mj-request-room-photo-ids" name="photo_ids_csv" value="<?php echo esc_attr($csv); ?>" data-room-media-input>
            <p class="mj-request-room-media__actions">
                <button type="button" class="button" data-room-media-select><?php esc_html_e('Sélectionner des images', 'mj-member'); ?></button>
                <button type="button" class="button" data-room-media-clear><?php esc_html_e('Vider', 'mj-member'); ?></button>
            </p>
            <div class="mj-request-room-media__preview" data-room-media-preview>
                <?php foreach ($photoIds as $photoId) : ?>
                    <?php
                    $thumb = wp_get_attachment_image_url((int) $photoId, 'thumbnail');
                    $full = wp_get_attachment_image_url((int) $photoId, 'full');
                    $url = $thumb ?: $full;
                    if (!$url) {
                        continue;
                    }
                    ?>
                    <span class="mj-request-room-media__item" data-room-media-id="<?php echo (int) $photoId; ?>">
                        <img src="<?php echo esc_url($url); ?>" alt="" class="mj-request-room-media__thumb" />
                        <button type="button" class="button button-small" data-room-media-remove="<?php echo (int) $photoId; ?>">×</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <p class="description"><?php esc_html_e('Vous pouvez sélectionner plusieurs images pour la salle.', 'mj-member'); ?></p>
        </div>
        <?php
    }

    /**
     * @param array<int,array{title:string,emoji:string,photo_id:int}> $items
     */
    private static function renderCatalogField(string $fieldName, array $items, string $addLabel): void
    {
        $rows = !empty($items) ? $items : array(array('title' => '', 'emoji' => '', 'photo_id' => 0));
        ?>
        <div class="mj-request-room-catalog" data-room-catalog-field data-base-name="<?php echo esc_attr($fieldName); ?>">
            <div data-room-catalog-rows>
                <?php foreach ($rows as $index => $item) : ?>
                    <?php
                    $title = sanitize_text_field((string) ($item['title'] ?? ''));
                    $emoji = self::sanitizeEmoji((string) ($item['emoji'] ?? ''));
                    $photoId = (int) ($item['photo_id'] ?? 0);
                    $thumb = $photoId > 0 ? wp_get_attachment_image_url($photoId, 'thumbnail') : '';
                    ?>
                    <div class="mj-request-room-catalog__row" data-room-catalog-row>
                        <label class="mj-request-room-catalog__label">
                            <span class="screen-reader-text"><?php esc_html_e('Titre', 'mj-member'); ?></span>
                            <input type="text" class="regular-text" name="<?php echo esc_attr($fieldName . '[' . $index . '][title]'); ?>" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('Titre', 'mj-member'); ?>" data-room-catalog-title>
                        </label>
                        <label class="mj-request-room-catalog__label">
                            <span class="screen-reader-text"><?php esc_html_e('Emoji', 'mj-member'); ?></span>
                            <span class="mj-request-room-catalog__emoji" data-room-catalog-emoji-field>
                                <span class="mj-request-room-catalog__emoji-picker" data-room-catalog-emoji-root></span>
                                <input type="text" class="small-text" name="<?php echo esc_attr($fieldName . '[' . $index . '][emoji]'); ?>" value="<?php echo esc_attr($emoji); ?>" placeholder="<?php esc_attr_e('Emoji', 'mj-member'); ?>" maxlength="16" data-room-catalog-emoji-input>
                            </span>
                        </label>
                        <div class="mj-request-room-catalog__photo-actions">
                            <input type="hidden" name="<?php echo esc_attr($fieldName . '[' . $index . '][photo_id]'); ?>" value="<?php echo (int) $photoId; ?>" data-room-catalog-photo-id>
                            <button type="button" class="button" data-room-catalog-photo-select><?php esc_html_e('Photo', 'mj-member'); ?></button>
                            <button type="button" class="button" data-room-catalog-photo-clear><?php esc_html_e('Retirer', 'mj-member'); ?></button>
                        </div>
                        <button type="button" class="button mj-request-room-catalog__remove" data-room-catalog-remove><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                        <div class="mj-request-room-catalog__preview<?php echo $thumb ? '' : ' is-empty'; ?>" data-room-catalog-photo-preview>
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" alt="" class="mj-request-room-catalog__thumb" />
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mj-request-room-catalog__add-wrap"><button type="button" class="button" data-room-catalog-add><?php echo esc_html($addLabel); ?></button></p>
            <p class="description"><?php esc_html_e('Chaque entrée peut contenir un titre, un emoji et une photo.', 'mj-member'); ?></p>
        </div>
        <?php
    }

    private static function renderEmojiField(string $fieldName, string $value, string $inputId, string $hintId): void
    {
        ?>
        <div class="mj-form-field mj-form-field--emoji" data-emoji-field>
            <div class="mj-form-emoji" data-emoji-container>
                <div class="mj-form-emoji__picker" data-emoji-picker-root></div>
                <input
                    type="text"
                    id="<?php echo esc_attr($inputId); ?>"
                    name="<?php echo esc_attr($fieldName); ?>"
                    class="mj-form-emoji__fallback"
                    value="<?php echo esc_attr($value); ?>"
                    maxlength="16"
                    placeholder="<?php esc_attr_e('Ex : 🎯', 'mj-member'); ?>"
                    autocomplete="off"
                    data-emoji-input
                    aria-describedby="<?php echo esc_attr($hintId); ?>"
                />
            </div>
            <p class="description mj-form-emoji__hint" id="<?php echo esc_attr($hintId); ?>"><?php esc_html_e('Facultatif, affiché avec le titre.', 'mj-member'); ?></p>
        </div>
        <?php
    }

    private static function editorSettings(string $textareaName, int $rows): array
    {
        return array(
            'textarea_name' => $textareaName,
            'textarea_rows' => $rows,
            'media_buttons' => true,
            'quicktags' => true,
        );
    }

    private static function emojiStrings(): array
    {
        return array(
            'eventEmojiPlaceholder' => __('Ex : 🎯', 'mj-member'),
            'eventEmojiPicker' => __('Choisir', 'mj-member'),
            'eventEmojiPickerClose' => __('Fermer', 'mj-member'),
            'eventEmojiClear' => __('Effacer', 'mj-member'),
            'eventEmojiSuggestions' => __('Suggestions', 'mj-member'),
            'eventEmojiSearchPlaceholder' => __('Rechercher un emoji', 'mj-member'),
            'eventEmojiSearchNoResult' => __('Aucun emoji ne correspond à votre recherche.', 'mj-member'),
            'eventEmojiAllCategory' => __('Tout', 'mj-member'),
        );
    }

    private static function mediaStrings(): array
    {
        return array(
            'title' => __('Sélectionner des images de salle', 'mj-member'),
            'button' => __('Utiliser ces images', 'mj-member'),
            'select' => __('Sélectionner des images', 'mj-member'),
            'clear' => __('Vider', 'mj-member'),
        );
    }

    private static function catalogStrings(): array
    {
        return array(
            'selectPhotoTitle' => __('Sélectionner une photo', 'mj-member'),
            'selectPhotoButton' => __('Utiliser cette photo', 'mj-member'),
            'emptyTitlePlaceholder' => __('Titre', 'mj-member'),
            'emptyEmojiPlaceholder' => __('Emoji', 'mj-member'),
            'photoButton' => __('Photo', 'mj-member'),
            'clearButton' => __('Retirer', 'mj-member'),
            'removeButton' => __('Supprimer', 'mj-member'),
            'emojiPickerPlaceholder' => __('Ex : 🎯', 'mj-member'),
            'emojiPickerLabel' => __('Choisir', 'mj-member'),
            'emojiPickerClose' => __('Fermer', 'mj-member'),
            'emojiPickerClear' => __('Effacer', 'mj-member'),
            'emojiPickerSuggestions' => __('Suggestions', 'mj-member'),
            'emojiPickerSearchPlaceholder' => __('Rechercher un emoji', 'mj-member'),
            'emojiPickerSearchNoResult' => __('Aucun emoji ne correspond à votre recherche.', 'mj-member'),
            'emojiPickerAllCategory' => __('Tout', 'mj-member'),
        );
    }

    /**
     * @param array<int,array{title:string,emoji:string,photo_id:int}> $items
     */
    private static function catalogSummary(array $items): string
    {
        $labels = array();
        foreach ($items as $item) {
            $title = isset($item['title']) ? (string) $item['title'] : '';
            if ($title === '') {
                continue;
            }

            $labels[] = self::emojiLabel(isset($item['emoji']) ? (string) $item['emoji'] : '', $title);
        }

        return implode(', ', $labels);
    }

    private static function sanitizeEmoji(string $value): string
    {
        $candidate = wp_check_invalid_utf8($value);
        $candidate = wp_strip_all_tags((string) $candidate, false);
        $candidate = preg_replace('/[\x00-\x1F\x7F]+/', '', (string) $candidate);
        if (!is_string($candidate)) {
            return '';
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        return trim(wp_html_excerpt($candidate, 16, ''));
    }

    private static function emojiLabel(string $emoji, string $label): string
    {
        $emoji = trim($emoji);
        return $emoji !== '' ? trim($emoji . ' ' . $label) : $label;
    }

    private static function descriptionExcerpt(string $description): string
    {
        return wp_trim_words(wp_strip_all_tags($description), 18);
    }
}
