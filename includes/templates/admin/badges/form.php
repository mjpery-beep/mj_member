<?php

if (!defined('ABSPATH')) {
    exit;
}

$badgeId = isset($badgeId) ? (int) $badgeId : 0;
$isEdit = isset($isEdit) ? (bool) $isEdit : $badgeId > 0;
$badge = isset($badge) && is_array($badge) ? $badge : array();
$backUrl = isset($backUrl) ? $backUrl : add_query_arg('page', \Mj\Member\Admin\Page\BadgesPage::slug(), admin_url('admin.php'));

$label = $badge['label'] ?? '';
$slug = $badge['slug'] ?? '';
$summary = $badge['summary'] ?? '';
$description = $badge['description'] ?? '';
$displayOrder = isset($badge['display_order']) ? (int) $badge['display_order'] : 0;
$status = $badge['status'] ?? \Mj\Member\Classes\Crud\MjBadges::STATUS_ACTIVE;
$prompt = $badge['prompt'] ?? '';
$icon = $badge['icon'] ?? '';
$imageId = isset($badge['image_id']) ? (int) $badge['image_id'] : 0;
$imageUrl = $imageId > 0 ? wp_get_attachment_image_url($imageId, 'medium') : '';

$criteriaRecords = isset($criteriaRecords) && is_array($criteriaRecords) ? $criteriaRecords : array();

if (!isset($criteriaText)) {
    $criteriaLabels = array();
    if (!empty($criteriaRecords)) {
        foreach ($criteriaRecords as $record) {
            if (!is_array($record)) {
                continue;
            }
            $label = isset($record['label']) ? trim((string) $record['label']) : '';
            if ($label !== '') {
                $criteriaLabels[] = $label;
            }
        }
    } else {
        $rawCriteria = $badge['criteria'] ?? array();
        if (is_string($rawCriteria)) {
            $rawCriteria = preg_split('/\r\n|\r|\n/', $rawCriteria);
        }
        if (is_array($rawCriteria)) {
            foreach ($rawCriteria as $entry) {
                $label = trim((string) $entry);
                if ($label !== '') {
                    $criteriaLabels[] = $label;
                }
            }
        }
    }

    $criteriaText = implode("\n", $criteriaLabels);
}

$statusOptions = \Mj\Member\Classes\Crud\MjBadges::get_status_labels();
?>
<div class="wrap mj-member-admin mj-member-admin-badges">
    <h1>
        <?php if ($isEdit) : ?>
            <?php esc_html_e('Modifier un badge', 'mj-member'); ?>
        <?php else : ?>
            <?php esc_html_e('Créer un badge', 'mj-member'); ?>
        <?php endif; ?>
    </h1>

    <?php if (isset($_GET['mj_badges_notice']) && sanitize_key(wp_unslash((string) $_GET['mj_badges_notice'])) === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Badge enregistré.', 'mj-member'); ?></p></div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html(rawurldecode((string) $_GET['error'])); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mj_member_save_badge'); ?>
        <input type="hidden" name="action" value="save_badge" />
        <input type="hidden" name="badge_id" value="<?php echo esc_attr((string) $badgeId); ?>" />

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="label"><?php esc_html_e('Nom du badge', 'mj-member'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="label" name="label" value="<?php echo esc_attr($label); ?>" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="slug"><?php esc_html_e('Slug', 'mj-member'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="slug" name="slug" value="<?php echo esc_attr($slug); ?>" />
                        <p class="description"><?php esc_html_e('Identifiant unique (laisser vide pour générer automatiquement).', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="summary"><?php esc_html_e('Résumé', 'mj-member'); ?></label></th>
                    <td>
                        <textarea id="summary" name="summary" rows="3" class="large-text"><?php echo esc_textarea($summary); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="description"><?php esc_html_e('Description détaillée', 'mj-member'); ?></label></th>
                    <td>
                        <textarea id="description" name="description" rows="6" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Critères', 'mj-member'); ?></th>
                    <td>
                        <textarea name="criteria" rows="4" class="large-text"><?php echo esc_textarea($criteriaText); ?></textarea>
                        <p class="description"><?php esc_html_e('Un critère par ligne.', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="display_order"><?php esc_html_e('Ordre d’affichage', 'mj-member'); ?></label></th>
                    <td>
                        <input type="number" id="display_order" name="display_order" value="<?php echo esc_attr((string) $displayOrder); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="icon"><?php esc_html_e('Icône', 'mj-member'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="icon" name="icon" value="<?php echo esc_attr($icon); ?>" />
                        <p class="description"><?php esc_html_e('Slug d’icône (facultatif).', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Image', 'mj-member'); ?></th>
                    <td>
                        <div class="mj-badge-image-field">
                            <div style="margin-bottom:8px;">
                                <img id="badge-image-preview" src="<?php echo $imageUrl ? esc_url($imageUrl) : ''; ?>" alt="<?php esc_attr_e('Aperçu de l’image du badge', 'mj-member'); ?>" style="max-width:150px;height:auto;<?php echo $imageUrl ? '' : 'display:none;'; ?>" />
                            </div>
                            <input type="hidden" name="image_id" id="badge_image_id" value="<?php echo esc_attr((string) $imageId); ?>" />
                            <button type="button" class="button" id="badge-image-select"><?php esc_html_e('Sélectionner une image', 'mj-member'); ?></button>
                            <button type="button" class="button" id="badge-image-clear" <?php echo $imageUrl ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Retirer l’image', 'mj-member'); ?></button>
                            <p class="description"><?php esc_html_e('Choisissez une image dans la médiathèque pour illustrer ce badge.', 'mj-member'); ?></p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="prompt"><?php esc_html_e('Prompt illustration', 'mj-member'); ?></label></th>
                    <td>
                        <textarea id="prompt" name="prompt" rows="4" class="large-text"><?php echo esc_textarea($prompt); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="status"><?php esc_html_e('Statut', 'mj-member'); ?></label></th>
                    <td>
                        <select id="status" name="status">
                            <?php foreach ($statusOptions as $value => $labelOption) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($labelOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo $isEdit ? esc_html__('Enregistrer les modifications', 'mj-member') : esc_html__('Créer le badge', 'mj-member'); ?>
            </button>
            <a class="button" href="<?php echo esc_url($backUrl); ?>"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
        </p>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectButton = document.getElementById('badge-image-select');
        const clearButton = document.getElementById('badge-image-clear');
        const input = document.getElementById('badge_image_id');
        const preview = document.getElementById('badge-image-preview');
        if (!selectButton || typeof wp === 'undefined' || !wp.media) {
            return;
        }

        let frame = null;

        selectButton.addEventListener('click', function (event) {
            event.preventDefault();
            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: '<?php echo esc_js(__('Sélectionner une image', 'mj-member')); ?>',
                button: { text: '<?php echo esc_js(__('Utiliser cette image', 'mj-member')); ?>' },
                library: { type: ['image'] },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first();
                if (!attachment) {
                    return;
                }
                const data = attachment.toJSON();
                input.value = data.id || '';
                if (data.url) {
                    preview.src = data.url;
                    preview.style.display = '';
                }
                if (clearButton) {
                    clearButton.style.display = '';
                }
            });

            frame.open();
        });

        if (clearButton) {
            clearButton.addEventListener('click', function (event) {
                event.preventDefault();
                input.value = '';
                preview.src = '';
                preview.style.display = 'none';
                clearButton.style.display = 'none';
            });
        }
    });
    </script>
</div>
