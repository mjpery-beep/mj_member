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
        <div class="wrap mj-request-admin">
            <h1 class="wp-heading-inline"><?php esc_html_e('MJ Request - Salles', 'mj-member'); ?></h1>
            <a href="<?php echo esc_url(add_query_arg(array('page' => self::slug()), admin_url('admin.php'))); ?>" class="page-title-action"><?php esc_html_e('Ajouter', 'mj-member'); ?></a>
            <hr class="wp-header-end">

            <?php self::renderNotice($notice); ?>

            <?php self::renderForm($editRoom); ?>

            <h2><?php esc_html_e('Salles existantes', 'mj-member'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
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
                            $options = json_decode((string) ($room->options_json ?? '[]'), true);
                            $materials = json_decode((string) ($room->materials_json ?? '[]'), true);
                            if (!is_array($options)) {
                                $options = array();
                            }
                            if (!is_array($materials)) {
                                $materials = array();
                            }
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
                                <td><?php echo esc_html(implode(', ', array_map('strval', $options))); ?></td>
                                <td><?php echo esc_html(implode(', ', array_map('strval', $materials))); ?></td>
                                <td><?php echo (int) $room->sort_order; ?></td>
                                <td><?php echo !empty($room->is_active) ? esc_html__('Actif', 'mj-member') : esc_html__('Inactif', 'mj-member'); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url($editUrl); ?>"><?php esc_html_e('Modifier', 'mj-member'); ?></a>
                                    <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('<?php echo esc_js(__('Supprimer cette salle ?', 'mj-member')); ?>');">
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
        if ($isEdit) {
            $decodedOptions = json_decode((string) ($room->options_json ?? '[]'), true);
            $decodedMaterials = json_decode((string) ($room->materials_json ?? '[]'), true);
            $options = is_array($decodedOptions) ? $decodedOptions : array();
            $materials = is_array($decodedMaterials) ? $decodedMaterials : array();
        }

        ?>
        <div class="postbox" style="max-width: 980px; padding: 12px 16px; margin: 0 0 16px 0;">
            <h2 style="margin-top:0;"><?php echo $isEdit ? esc_html__('Modifier la salle', 'mj-member') : esc_html__('Ajouter une salle', 'mj-member'); ?></h2>
            <form method="post">
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
                            <th scope="row"><label for="mj-request-room-options"><?php esc_html_e('Options (CSV)', 'mj-member'); ?></label></th>
                            <td><input id="mj-request-room-options" name="options_csv" type="text" class="regular-text" value="<?php echo esc_attr(implode(', ', array_map('strval', $options))); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-room-materials"><?php esc_html_e('Matériel (CSV)', 'mj-member'); ?></label></th>
                            <td><input id="mj-request-room-materials" name="materials_csv" type="text" class="regular-text" value="<?php echo esc_attr(implode(', ', array_map('strval', $materials))); ?>"></td>
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

                <p>
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
            'options_json' => self::parseCsv((string) ($_POST['options_csv'] ?? '')),
            'materials_json' => self::parseCsv((string) ($_POST['materials_csv'] ?? '')),
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

        wp_enqueue_style('mj-member-event-form');
        wp_enqueue_style('mj-member-registration-manager');
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
