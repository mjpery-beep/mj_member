<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class EventTypesPage
{
    public static function slug(): string
    {
        return 'mj_event_types';
    }

    public static function render(): void
    {
        RequestGuard::ensureCapabilityOrDie(Config::capability());

        $postNotice = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_event_type_action'])) {
            $postNotice = self::handlePost();
        }

        $allTypes = MjEvents::get_type_labels();
        $typeColors = MjEvents::get_type_colors();
        $customEventTypes = MjEvents::get_custom_type_labels();
        $visibilityRoles = MjEvents::get_visibility_roles();
        $visibilityMap = MjEvents::get_type_visibility_map();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Types d\'événements', 'mj-member'); ?></h1>

            <?php self::renderNotice($postNotice); ?>

            <div class="postbox" style="max-width:980px;margin:0 0 16px 0;padding:12px 16px;">
                <h2 style="margin:0 0 12px 0;"><?php esc_html_e('Configurer les types', 'mj-member'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('mj_manage_event_types', 'mj_manage_event_types_nonce'); ?>
                    <input type="hidden" name="mj_event_type_action" value="save" />

                    <table class="widefat striped" style="max-width:940px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Clé', 'mj-member'); ?></th>
                                <th><?php esc_html_e('Libellé', 'mj-member'); ?></th>
                                <th><?php esc_html_e('Couleur', 'mj-member'); ?></th>
                                <th><?php esc_html_e('Coordinateur', 'mj-member'); ?></th>
                                <th><?php esc_html_e('Animateur', 'mj-member'); ?></th>
                                <th><?php esc_html_e('Jeune', 'mj-member'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTypes as $typeKey => $typeLabel) : ?>
                                <?php $rowVisibility = isset($visibilityMap[$typeKey]) && is_array($visibilityMap[$typeKey]) ? $visibilityMap[$typeKey] : array(); ?>
                                <?php $rowColor = isset($typeColors[$typeKey]) ? (string) $typeColors[$typeKey] : '#6C757D'; ?>
                                <tr>
                                    <td><code><?php echo esc_html($typeKey); ?></code></td>
                                    <td>
                                        <input type="text" name="mj_type_labels[<?php echo esc_attr($typeKey); ?>]" value="<?php echo esc_attr($typeLabel); ?>" class="regular-text" />
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <input type="color" name="mj_type_colors[<?php echo esc_attr($typeKey); ?>]" value="<?php echo esc_attr($rowColor); ?>" />
                                            <code><?php echo esc_html(strtoupper($rowColor)); ?></code>
                                        </div>
                                    </td>
                                    <?php foreach ($visibilityRoles as $roleKey => $roleLabel) : ?>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="mj_type_visibility[<?php echo esc_attr($typeKey); ?>][<?php echo esc_attr($roleKey); ?>]" value="1" <?php checked(!empty($rowVisibility[$roleKey])); ?> />
                                                <span class="screen-reader-text"><?php echo esc_html($roleLabel); ?></span>
                                            </label>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="margin-top:12px;">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Enregistrer les modifications', 'mj-member'); ?></button>
                    </p>
                </form>
            </div>

            <div class="postbox" style="max-width:980px;margin:0 0 16px 0;padding:12px 16px;">
                <h2 style="margin:0 0 12px 0;"><?php esc_html_e('Ajouter un type personnalisé', 'mj-member'); ?></h2>
                <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
                    <?php wp_nonce_field('mj_manage_event_types', 'mj_manage_event_types_nonce'); ?>
                    <input type="hidden" name="mj_event_type_action" value="add" />
                    <label style="display:flex;flex-direction:column;gap:4px;min-width:180px;">
                        <span><?php esc_html_e('Clé technique (optionnel)', 'mj-member'); ?></span>
                        <input type="text" name="mj_event_type_key" class="regular-text" placeholder="tournoi" />
                    </label>
                    <label style="display:flex;flex-direction:column;gap:4px;min-width:220px;">
                        <span><?php esc_html_e('Libellé', 'mj-member'); ?></span>
                        <input type="text" name="mj_event_type_label" class="regular-text" placeholder="Tournoi" required />
                    </label>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Ajouter le type', 'mj-member'); ?></button>
                </form>
            </div>

            <div class="postbox" style="max-width:980px;margin:0 0 16px 0;padding:12px 16px;">
                <h2 style="margin:0 0 12px 0;"><?php esc_html_e('Types personnalisés existants', 'mj-member'); ?></h2>
                <?php if (!empty($customEventTypes)) : ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <?php foreach ($customEventTypes as $typeKey => $typeLabel) : ?>
                            <form method="post" style="display:inline-flex;gap:6px;align-items:center;padding:6px 8px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">
                                <?php wp_nonce_field('mj_manage_event_types', 'mj_manage_event_types_nonce'); ?>
                                <input type="hidden" name="mj_event_type_action" value="delete" />
                                <input type="hidden" name="mj_event_type_key" value="<?php echo esc_attr($typeKey); ?>" />
                                <span><strong><?php echo esc_html($typeLabel); ?></strong> <code><?php echo esc_html($typeKey); ?></code></span>
                                <button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Supprimer ce type personnalisé ?', 'mj-member')); ?>');"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="margin:0;color:#50575e;"><?php esc_html_e('Aucun type personnalisé pour le moment.', 'mj-member'); ?></p>
                <?php endif; ?>
            </div>

            <p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mj_events')); ?>"><?php esc_html_e('Retour aux événements', 'mj-member'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * @return array{message:string,type:string}
     */
    private static function handlePost(): array
    {
        $nonce = RequestGuard::readNonce($_POST, 'mj_manage_event_types_nonce');
        if (!RequestGuard::verifyNonce($nonce, 'mj_manage_event_types')) {
            return array(
                'message' => __('Action non autorisée.', 'mj-member'),
                'type' => 'error',
            );
        }

        $action = isset($_POST['mj_event_type_action']) ? sanitize_key((string) wp_unslash($_POST['mj_event_type_action'])) : '';

        if ($action === 'add') {
            $typeKeyRaw = isset($_POST['mj_event_type_key']) ? (string) wp_unslash($_POST['mj_event_type_key']) : '';
            $typeLabelRaw = isset($_POST['mj_event_type_label']) ? (string) wp_unslash($_POST['mj_event_type_label']) : '';

            $typeLabel = sanitize_text_field($typeLabelRaw);
            $typeKey = sanitize_key($typeKeyRaw);
            if ($typeKey === '' && $typeLabel !== '') {
                $typeKey = sanitize_key(remove_accents($typeLabel));
            }

            $result = MjEvents::add_custom_type($typeKey, $typeLabel);
            if (is_wp_error($result)) {
                return array(
                    'message' => $result->get_error_message(),
                    'type' => 'error',
                );
            }

            return array(
                'message' => __('Type ajouté avec succès.', 'mj-member'),
                'type' => 'success',
            );
        }

        if ($action === 'save') {
            $labels = isset($_POST['mj_type_labels']) && is_array($_POST['mj_type_labels'])
                ? wp_unslash($_POST['mj_type_labels'])
                : array();

            $visibility = isset($_POST['mj_type_visibility']) && is_array($_POST['mj_type_visibility'])
                ? wp_unslash($_POST['mj_type_visibility'])
                : array();
            $colors = isset($_POST['mj_type_colors']) && is_array($_POST['mj_type_colors'])
                ? wp_unslash($_POST['mj_type_colors'])
                : array();

            $result = MjEvents::save_type_settings($labels, $visibility, $colors);
            if (is_wp_error($result)) {
                return array(
                    'message' => $result->get_error_message(),
                    'type' => 'error',
                );
            }

            return array(
                'message' => __('Types mis à jour avec succès.', 'mj-member'),
                'type' => 'success',
            );
        }

        if ($action === 'delete') {
            $typeKey = isset($_POST['mj_event_type_key']) ? sanitize_key((string) wp_unslash($_POST['mj_event_type_key'])) : '';
            $result = MjEvents::remove_custom_type($typeKey);
            if (is_wp_error($result)) {
                return array(
                    'message' => $result->get_error_message(),
                    'type' => 'error',
                );
            }

            return array(
                'message' => __('Type supprimé avec succès.', 'mj-member'),
                'type' => 'success',
            );
        }

        return array(
            'message' => __('Action inconnue.', 'mj-member'),
            'type' => 'error',
        );
    }

    /**
     * @param array{message:string,type:string}|null $postNotice
     */
    private static function renderNotice(?array $postNotice = null): void
    {
        $message = '';
        $type = 'success';

        if (is_array($postNotice) && isset($postNotice['message'])) {
            $message = sanitize_text_field((string) $postNotice['message']);
            $type = isset($postNotice['type']) ? sanitize_key((string) $postNotice['type']) : 'success';
        } else {
            $noticeRaw = isset($_GET['mj_events_message']) ? wp_unslash((string) $_GET['mj_events_message']) : '';
            if ($noticeRaw === '') {
                return;
            }

            $message = sanitize_text_field(rawurldecode($noticeRaw));
            $type = isset($_GET['mj_events_message_type']) ? sanitize_key(wp_unslash((string) $_GET['mj_events_message_type'])) : 'success';
        }

        if ($message === '') {
            return;
        }

        $class = 'notice notice-success';
        if ($type === 'error') {
            $class = 'notice notice-error';
        } elseif ($type === 'warning') {
            $class = 'notice notice-warning';
        }

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
}
