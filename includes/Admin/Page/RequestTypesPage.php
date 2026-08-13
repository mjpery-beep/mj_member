<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjRequestTypes;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestTypesPage
{
    public static function slug(): string
    {
        return 'mj_request_types';
    }

    public static function render(): void
    {
        RequestGuard::ensureCapabilityOrDie(Config::capability());

        if (function_exists('mj_member_ensure_request_management_tables')) {
            mj_member_ensure_request_management_tables();
        }

        self::enqueueAssets();

        $notice = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_request_types_action'])) {
            $notice = self::handlePost();
        }

        $types = MjRequestTypes::get_all(array('include_inactive' => true));
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editType = null;
        if ($editId > 0) {
            foreach ($types as $row) {
                if ((int) ($row->id ?? 0) === $editId) {
                    $editType = $row;
                    break;
                }
            }
        }

        ?>
        <div class="wrap mj-request-admin">
            <h1 class="wp-heading-inline"><?php esc_html_e('MJ Request - Types de demande', 'mj-member'); ?></h1>
            <a href="<?php echo esc_url(add_query_arg(array('page' => self::slug()), admin_url('admin.php'))); ?>" class="page-title-action"><?php esc_html_e('Ajouter', 'mj-member'); ?></a>
            <hr class="wp-header-end">

            <?php self::renderNotice($notice); ?>

            <?php self::renderForm($editType); ?>

            <h2><?php esc_html_e('Types existants', 'mj-member'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:12%;"><?php esc_html_e('Clé', 'mj-member'); ?></th>
                        <th style="width:16%;"><?php esc_html_e('Libellé', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Description', 'mj-member'); ?></th>
                        <th style="width:19%;"><?php esc_html_e('Options', 'mj-member'); ?></th>
                        <th style="width:7%;"><?php esc_html_e('Ordre', 'mj-member'); ?></th>
                        <th style="width:8%;"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                        <th style="width:14%;"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($types)) : ?>
                        <tr><td colspan="7"><?php esc_html_e('Aucun type trouvé.', 'mj-member'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($types as $type) : ?>
                            <?php
                            $editUrl = add_query_arg(
                                array(
                                    'page' => self::slug(),
                                    'edit' => (int) $type->id,
                                ),
                                admin_url('admin.php')
                            );
                            $flags = array();
                            if (!empty($type->allows_location)) {
                                $flags[] = __('Lieu', 'mj-member');
                            }
                            if (!empty($type->allows_materials)) {
                                $flags[] = __('Matériel', 'mj-member');
                            }
                            if (!empty($type->allows_date)) {
                                $flags[] = __('Date', 'mj-member');
                            }
                            if (!empty($type->allows_multiple_dates)) {
                                $flags[] = __('Plusieurs dates', 'mj-member');
                            }
                            if (!empty($type->requires_animateur)) {
                                $flags[] = __('Animateur requis', 'mj-member');
                            }
                            ?>
                            <tr>
                                <td><code><?php echo esc_html((string) $type->type_key); ?></code></td>
                                <td><strong><?php echo esc_html(self::emojiLabel(isset($type->emoji) ? (string) $type->emoji : '', (string) $type->label)); ?></strong></td>
                                <td><?php echo esc_html(self::descriptionExcerpt((string) $type->description)); ?></td>
                                <td><?php echo esc_html(implode(' | ', $flags)); ?></td>
                                <td><?php echo (int) $type->sort_order; ?></td>
                                <td><?php echo !empty($type->is_active) ? esc_html__('Actif', 'mj-member') : esc_html__('Inactif', 'mj-member'); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url($editUrl); ?>"><?php esc_html_e('Modifier', 'mj-member'); ?></a>
                                    <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('<?php echo esc_js(__('Supprimer ce type de demande ?', 'mj-member')); ?>');">
                                        <?php wp_nonce_field('mj_request_types_manage', 'mj_request_types_nonce'); ?>
                                        <input type="hidden" name="mj_request_types_action" value="delete">
                                        <input type="hidden" name="type_id" value="<?php echo (int) $type->id; ?>">
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

    private static function renderForm(?object $type): void
    {
        $isEdit = $type !== null;
        $typeKey = $isEdit ? (string) ($type->type_key ?? '') : '';
        $emoji = $isEdit ? (string) ($type->emoji ?? '') : '';
        $label = $isEdit ? (string) ($type->label ?? '') : '';
        $description = $isEdit ? (string) ($type->description ?? '') : '';
        $sortOrder = $isEdit ? (int) ($type->sort_order ?? 0) : 0;

        ?>
        <div class="postbox" style="max-width: 980px; padding: 12px 16px; margin: 0 0 16px 0;">
            <h2 style="margin-top:0;"><?php echo $isEdit ? esc_html__('Modifier le type de demande', 'mj-member') : esc_html__('Ajouter un type de demande', 'mj-member'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('mj_request_types_manage', 'mj_request_types_nonce'); ?>
                <input type="hidden" name="mj_request_types_action" value="save">
                <?php if ($isEdit) : ?>
                    <input type="hidden" name="type_id" value="<?php echo (int) $type->id; ?>">
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Emoji', 'mj-member'); ?></th>
                            <td><?php self::renderEmojiField('emoji', $emoji, 'mj-request-type-emoji', 'mj-request-type-emoji-hint'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-type-key"><?php esc_html_e('Clé', 'mj-member'); ?></label></th>
                            <td>
                                <?php if ($isEdit) : ?>
                                    <input id="mj-request-type-key" type="text" class="regular-text" value="<?php echo esc_attr($typeKey); ?>" disabled>
                                <?php else : ?>
                                    <input id="mj-request-type-key" name="type_key" type="text" class="regular-text" value="" placeholder="location_salle">
                                    <p class="description"><?php esc_html_e('Si vide, générée automatiquement depuis le libellé.', 'mj-member'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-type-label"><?php esc_html_e('Libellé', 'mj-member'); ?></label></th>
                            <td><input id="mj-request-type-label" name="label" type="text" class="regular-text" value="<?php echo esc_attr($label); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-type-color"><?php esc_html_e('Couleur', 'mj-member'); ?></label></th>
                            <td>
                                <input id="mj-request-type-color" name="color" type="color" value="<?php echo esc_attr($isEdit && !empty($type->color) ? (string) $type->color : '#1F6FEB'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-type-description-editor"><?php esc_html_e('Description', 'mj-member'); ?></label></th>
                            <td>
                                <?php
                                wp_editor(
                                    $description,
                                    'mj-request-type-description-editor',
                                    self::editorSettings('description', 8)
                                );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-request-type-sort-order"><?php esc_html_e('Ordre', 'mj-member'); ?></label></th>
                            <td><input id="mj-request-type-sort-order" name="sort_order" type="number" value="<?php echo (int) $sortOrder; ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Options', 'mj-member'); ?></th>
                            <td>
                                <label><input type="checkbox" name="allows_location" value="1" <?php checked($isEdit ? !empty($type->allows_location) : false); ?>> <?php esc_html_e('Lieu', 'mj-member'); ?></label><br>
                                <label><input type="checkbox" name="allows_materials" value="1" <?php checked($isEdit ? !empty($type->allows_materials) : false); ?>> <?php esc_html_e('Matériel', 'mj-member'); ?></label><br>
                                <label><input type="checkbox" name="allows_date" value="1" <?php checked($isEdit ? !empty($type->allows_date) : false); ?>> <?php esc_html_e('Date', 'mj-member'); ?></label><br>
                                <label><input type="checkbox" name="allows_multiple_dates" value="1" <?php checked($isEdit ? !empty($type->allows_multiple_dates) : false); ?>> <?php esc_html_e('Plusieurs dates', 'mj-member'); ?></label><br>
                                <label><input type="checkbox" name="requires_animateur" value="1" <?php checked($isEdit ? !empty($type->requires_animateur) : false); ?>> <?php esc_html_e('Animateur requis', 'mj-member'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                            <td><label><input name="is_active" type="checkbox" value="1" <?php checked($isEdit ? !empty($type->is_active) : true); ?>> <?php esc_html_e('Actif', 'mj-member'); ?></label></td>
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
        $nonce = RequestGuard::readNonce($_POST, 'mj_request_types_nonce');
        if (!RequestGuard::verifyNonce($nonce, 'mj_request_types_manage')) {
            return array(
                'message' => __('Action non autorisée.', 'mj-member'),
                'type' => 'error',
            );
        }

        $action = isset($_POST['mj_request_types_action']) ? sanitize_key((string) wp_unslash($_POST['mj_request_types_action'])) : '';

        if ($action === 'delete') {
            $result = MjRequestTypes::delete((int) ($_POST['type_id'] ?? 0));
            if (is_wp_error($result)) {
                return array('message' => $result->get_error_message(), 'type' => 'error');
            }

            return array('message' => __('Type supprimé.', 'mj-member'), 'type' => 'success');
        }

        if ($action !== 'save') {
            return array('message' => __('Action inconnue.', 'mj-member'), 'type' => 'error');
        }

        $payload = array(
            'emoji' => wp_unslash((string) ($_POST['emoji'] ?? '')),
            'color' => wp_unslash((string) ($_POST['color'] ?? '')),
            'label' => sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? ''))),
            'description' => wp_unslash((string) ($_POST['description'] ?? '')),
            'allows_location' => isset($_POST['allows_location']) ? 1 : 0,
            'allows_materials' => isset($_POST['allows_materials']) ? 1 : 0,
            'allows_date' => isset($_POST['allows_date']) ? 1 : 0,
            'allows_multiple_dates' => isset($_POST['allows_multiple_dates']) ? 1 : 0,
            'requires_animateur' => isset($_POST['requires_animateur']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        $typeId = (int) ($_POST['type_id'] ?? 0);
        if ($typeId > 0) {
            $result = MjRequestTypes::update($typeId, $payload);
            if (is_wp_error($result)) {
                return array('message' => $result->get_error_message(), 'type' => 'error');
            }

            return array('message' => __('Type mis à jour.', 'mj-member'), 'type' => 'success');
        }

        $typeKey = sanitize_key(wp_unslash((string) ($_POST['type_key'] ?? '')));
        if ($typeKey === '') {
            $typeKey = sanitize_key(remove_accents($payload['label']));
        }
        $payload['type_key'] = $typeKey;

        $created = MjRequestTypes::create($payload);
        if (is_wp_error($created)) {
            return array('message' => $created->get_error_message(), 'type' => 'error');
        }

        return array('message' => __('Type ajouté.', 'mj-member'), 'type' => 'success');
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
