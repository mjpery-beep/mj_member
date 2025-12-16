<?php

use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjMembers;

if (!defined('ABSPATH')) {
    exit;
}

function mj_member_cards_pdf_default_values() {
    $stored_background_id = (int) get_option('mj_cards_pdf_background_image_id', 0);
    $stored_back_background_id = (int) get_option('mj_cards_pdf_background_back_image_id', 0);
    $stored_double_sided = get_option('mj_cards_pdf_double_sided', '0') === '1';

    return array(
        'selection_mode' => 'all',
        'roles' => array(),
        'members' => array(),
        'card_bg' => '#ffffff',
        'background_image_id' => $stored_background_id,
        'background_back_image_id' => $stored_back_background_id,
        'text_color' => '#ffffff',
        'font' => 'Helvetica',
        'logo_id' => 0,
        'include_inactive' => false,
        'double_sided' => $stored_double_sided,
    );
}

function mj_member_cards_pdf_consume_state() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return null;
    }

    $key = 'mj_member_cards_pdf_state_' . $user_id;
    $state = get_transient($key);
    if ($state !== false) {
        delete_transient($key);
    }

    return $state;
}

add_action('admin_post_mj_member_cards_pdf_generate', 'mj_member_cards_pdf_handle_generate');

function mj_member_cards_pdf_handle_generate() {
    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_die(esc_html__('Vous n’avez pas les droits nécessaires pour effectuer cette action.', 'mj-member'));
    }

    check_admin_referer('mj_member_cards_pdf_generate', 'mj_member_cards_pdf_nonce');

    $post_data = wp_unslash($_POST);
    $values = mj_member_cards_pdf_default_values();
    $errors = array();

    $role_labels = method_exists('MjMembers', 'getRoleLabels') ? MjMembers::getRoleLabels() : array();
    $role_labels = is_array($role_labels) ? $role_labels : array();

    $values['selection_mode'] = isset($post_data['selection_mode']) ? sanitize_key($post_data['selection_mode']) : 'all';
    if (!in_array($values['selection_mode'], array('all', 'role', 'custom'), true)) {
        $values['selection_mode'] = 'all';
    }

    $values['roles'] = array();
    if (!empty($post_data['roles']) && is_array($post_data['roles'])) {
        foreach ($post_data['roles'] as $role_key) {
            $role_key = sanitize_key($role_key);
            if ($role_key !== '' && isset($role_labels[$role_key])) {
                $values['roles'][] = $role_key;
            }
        }
    }

    $values['members'] = array();
    if (!empty($post_data['members']) && is_array($post_data['members'])) {
        foreach ($post_data['members'] as $member_id) {
            $member_id = (int) $member_id;
            if ($member_id > 0) {
                $values['members'][] = $member_id;
            }
        }
    }

    $values['card_bg'] = isset($post_data['card_bg']) ? sanitize_hex_color($post_data['card_bg']) : '#ffffff';
    if (!$values['card_bg']) {
        $values['card_bg'] = '#ffffff';
    }

    $values['text_color'] = isset($post_data['text_color']) ? sanitize_hex_color($post_data['text_color']) : '#ffffff';
    if (!$values['text_color']) {
        $values['text_color'] = '#ffffff';
    }

    $available_fonts = array('Helvetica', 'Arial', 'Courier', 'Times');
    $values['font'] = isset($post_data['font']) ? sanitize_text_field($post_data['font']) : 'Helvetica';
    if (!in_array($values['font'], $available_fonts, true)) {
        $values['font'] = 'Helvetica';
    }

    $values['background_image_id'] = isset($post_data['background_image_id']) ? (int) $post_data['background_image_id'] : 0;
    $values['background_back_image_id'] = isset($post_data['background_back_image_id']) ? (int) $post_data['background_back_image_id'] : 0;
    $values['logo_id'] = isset($post_data['logo_id']) ? (int) $post_data['logo_id'] : 0;
    $values['include_inactive'] = !empty($post_data['include_inactive']);
    $values['double_sided'] = !empty($post_data['double_sided']);

    $logo_path = '';
    if ($values['logo_id'] > 0) {
        $logo_path_candidate = get_attached_file($values['logo_id']);
        if ($logo_path_candidate && file_exists($logo_path_candidate)) {
            $logo_path = $logo_path_candidate;
        } else {
            $errors[] = esc_html__('Le logo indiqué est introuvable sur le serveur.', 'mj-member');
        }
    }

    $background_path = '';
    if ($values['background_image_id'] > 0) {
        $background_candidate = get_attached_file($values['background_image_id']);
        if ($background_candidate && file_exists($background_candidate)) {
            $background_path = $background_candidate;
        } else {
            $errors[] = esc_html__('L’image de décor indiquée est introuvable sur le serveur.', 'mj-member');
        }
    }

    $back_background_path = '';
    if ($values['background_back_image_id'] > 0) {
        $background_back_candidate = get_attached_file($values['background_back_image_id']);
        if ($background_back_candidate && file_exists($background_back_candidate)) {
            $back_background_path = $background_back_candidate;
        } else {
            $errors[] = esc_html__('L’image de décor verso est introuvable sur le serveur.', 'mj-member');
        }
    }

    $selected_members = MjMemberBusinessCards::collect_members(
        $values['selection_mode'],
        array(
            'roles' => $values['roles'],
            'member_ids' => $values['members'],
            'include_inactive' => $values['include_inactive'],
        )
    );

    if (empty($selected_members)) {
        $errors[] = esc_html__('Veuillez sélectionner au moins un membre avant de générer le PDF.', 'mj-member');
    }

    $transient_payload = array(
        'values' => $values,
        'errors' => array(),
        'notices' => array(),
    );

    if (!empty($errors)) {
        $transient_payload['errors'] = $errors;
        mj_member_cards_pdf_store_state($transient_payload);
        wp_safe_redirect(mj_member_cards_pdf_redirect_url('error'));
        exit;
    }

    $pdf_payload = MjMemberBusinessCards::build_cards_pdf(
        $selected_members,
        array(
            'background_color' => $values['card_bg'],
            'background_image_path' => $background_path,
            'background_back_image_path' => $back_background_path,
            'text_color' => $values['text_color'],
            'font_family' => $values['font'],
            'logo_path' => $logo_path,
            'double_sided' => $values['double_sided'],
        )
    );

    if (is_wp_error($pdf_payload)) {
        $transient_payload['errors'] = array($pdf_payload->get_error_message());
        mj_member_cards_pdf_store_state($transient_payload);
        wp_safe_redirect(mj_member_cards_pdf_redirect_url('error'));
        exit;
    }

    nocache_headers();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdf_payload['filename'] . '"');
    header('Content-Length: ' . strlen($pdf_payload['content']));
    echo $pdf_payload['content'];
    exit;
}

function mj_member_cards_pdf_store_state($state) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }

    $key = 'mj_member_cards_pdf_state_' . $user_id;
    set_transient($key, $state, MINUTE_IN_SECONDS * 5);
}

function mj_member_cards_pdf_redirect_url($status) {
    $base = add_query_arg(
        array(
            'page' => 'mj_member_cards_pdf',
        ),
        admin_url('admin.php')
    );

    if ($status) {
        $base = add_query_arg('mj_cards_pdf_status', $status, $base);
    }

    return $base;
}

function mj_member_cards_pdf_page() {
    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_die(esc_html__('Vous n’avez pas les droits nécessaires pour accéder à cette page.', 'mj-member'));
    }

    wp_enqueue_media();

    $default_values = mj_member_cards_pdf_default_values();
    $values = $default_values;
    $errors = array();
    $notices = array();

    $persisted_state = mj_member_cards_pdf_consume_state();
    if (is_array($persisted_state)) {
        if (!empty($persisted_state['values']) && is_array($persisted_state['values'])) {
            $values = array_merge($values, $persisted_state['values']);
        }
        if (!empty($persisted_state['errors']) && is_array($persisted_state['errors'])) {
            $errors = $persisted_state['errors'];
        }
        if (!empty($persisted_state['notices']) && is_array($persisted_state['notices'])) {
            $notices = $persisted_state['notices'];
        }
    }

    $role_labels = method_exists('MjMembers', 'getRoleLabels') ? MjMembers::getRoleLabels() : array();
    $role_labels = is_array($role_labels) ? $role_labels : array();

    $all_members_for_select = MjMemberBusinessCards::collect_members('all', array('include_inactive' => true));

    $preview_members = MjMemberBusinessCards::collect_members(
        $values['selection_mode'],
        array(
            'roles' => $values['roles'],
            'member_ids' => $values['members'],
            'include_inactive' => $values['include_inactive'],
        )
    );
    $preview_count = count($preview_members);

    $fonts_options = array(
        'Helvetica' => 'Helvetica',
        'Arial' => 'Arial',
        'Courier' => 'Courier',
        'Times' => 'Times New Roman',
    );

    $background_preview_url = '';
    if (!empty($values['background_image_id'])) {
        $background_preview_url = wp_get_attachment_image_url((int) $values['background_image_id'], 'medium');
        if (!$background_preview_url) {
            $background_preview_url = wp_get_attachment_url((int) $values['background_image_id']);
        }
    }

    $back_background_preview_url = '';
    if (!empty($values['background_back_image_id'])) {
        $back_background_preview_url = wp_get_attachment_image_url((int) $values['background_back_image_id'], 'medium');
        if (!$back_background_preview_url) {
            $back_background_preview_url = wp_get_attachment_url((int) $values['background_back_image_id']);
        }
    }

    $member_select_size = min(15, max(6, count($all_members_for_select)));
    ?>
    <div class="wrap mj-member-cards-pdf">
        <h1><?php esc_html_e('Cartes de visite PDF', 'mj-member'); ?></h1>
        <p class="description"><?php esc_html_e('Générez un PDF contenant des cartes de visite au format 85x55mm pour vos membres.', 'mj-member'); ?></p>

        <?php foreach ($errors as $error_message) : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
        <?php endforeach; ?>

        <?php foreach ($notices as $notice_message) : ?>
            <div class="notice notice-success"><p><?php echo esc_html($notice_message); ?></p></div>
        <?php endforeach; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mj_member_cards_pdf_generate', 'mj_member_cards_pdf_nonce'); ?>
            <input type="hidden" name="action" value="mj_member_cards_pdf_generate" />

            <h2><?php esc_html_e('Sélection des membres', 'mj-member'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Mode de sélection', 'mj-member'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="selection_mode" value="all" <?php checked($values['selection_mode'], 'all'); ?> />
                                <span><?php esc_html_e('Tous les membres actifs', 'mj-member'); ?></span>
                            </label>
                            <br />
                            <label>
                                <input type="radio" name="selection_mode" value="role" <?php checked($values['selection_mode'], 'role'); ?> />
                                <span><?php esc_html_e('Filtrer par rôle', 'mj-member'); ?></span>
                            </label>
                            <br />
                            <label>
                                <input type="radio" name="selection_mode" value="custom" <?php checked($values['selection_mode'], 'custom'); ?> />
                                <span><?php esc_html_e('Sélection manuelle', 'mj-member'); ?></span>
                            </label>
                        </fieldset>
                        <p class="description"><?php esc_html_e('Choisissez la source des membres à inclure dans le PDF.', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr data-selection-section="role">
                    <th scope="row"><?php esc_html_e('Rôles à inclure', 'mj-member'); ?></th>
                    <td>
                        <select name="roles[]" multiple size="5" style="min-width: 260px;">
                            <?php foreach ($role_labels as $role_key => $label) : ?>
                                <option value="<?php echo esc_attr($role_key); ?>" <?php selected(in_array($role_key, $values['roles'], true), true); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Sélectionnez un ou plusieurs rôles. Laissez vide pour n’inclure aucun membre.', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr data-selection-section="custom">
                    <th scope="row"><?php esc_html_e('Membres précis', 'mj-member'); ?></th>
                    <td>
                        <select name="members[]" multiple size="<?php echo esc_attr($member_select_size); ?>" style="min-width: 320px;">
                            <?php foreach ($all_members_for_select as $member_entry) : ?>
                                <option value="<?php echo esc_attr($member_entry['id']); ?>" <?php selected(in_array($member_entry['id'], $values['members'], true), true); ?>>
                                    <?php echo esc_html($member_entry['full_name'] . ' – ' . $member_entry['role_label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Maintenez la touche Ctrl (Windows) ou Cmd (macOS) pour sélectionner plusieurs membres.', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Inclure les membres inactifs', 'mj-member'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="include_inactive" value="1" <?php checked($values['include_inactive']); ?> />
                            <span><?php esc_html_e('Autoriser les membres inactifs dans la sélection.', 'mj-member'); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Aperçu de la sélection', 'mj-member'); ?></th>
                    <td>
                        <strong><?php echo esc_html($preview_count); ?></strong>
                        <?php esc_html_e('membre(s) seront inclus dans le PDF.', 'mj-member'); ?>
                    </td>
                </tr>
                </tbody>
            </table>

            <h2><?php esc_html_e('Personnalisation', 'mj-member'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="mj-card-bg"><?php esc_html_e('Couleur de fond', 'mj-member'); ?></label></th>
                    <td>
                        <input type="color" id="mj-card-bg" name="card_bg" value="<?php echo esc_attr($values['card_bg']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Image décorative', 'mj-member'); ?></th>
                    <td>
                        <div class="mj-member-cards-media-picker" data-role="cards-background-picker">
                            <input type="hidden" id="mj-background-image-id" name="background_image_id" value="<?php echo esc_attr($values['background_image_id']); ?>" data-role="attachment-field" />
                            <div class="mj-member-cards-media-picker__preview" data-role="preview"<?php echo $background_preview_url ? '' : ' style="display:none;"'; ?> style="margin:8px 0;max-width:220px;border:1px solid rgba(148,163,184,0.4);border-radius:8px;overflow:hidden;">
                                <img src="<?php echo $background_preview_url ? esc_url($background_preview_url) : ''; ?>" alt="<?php esc_attr_e('Aperçu du décor sélectionné', 'mj-member'); ?>" style="display:block;width:100%;height:auto;" />
                            </div>
                            <p class="description" data-role="placeholder"<?php echo $background_preview_url ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Aucune image sélectionnée.', 'mj-member'); ?></p>
                            <p class="description"><?php esc_html_e('Choisissez une image (JPG/PNG) de la médiathèque. Elle sera appliquée en fond sur chaque carte.', 'mj-member'); ?></p>
                            <div class="mj-member-cards-media-picker__actions">
                                <button type="button" class="button" data-role="select">
                                    <?php echo esc_html__('Choisir une image', 'mj-member'); ?>
                                </button>
                                <button type="button" class="button-link" data-role="remove"<?php echo $background_preview_url ? '' : ' style="display:none;"'; ?>>
                                    <?php echo esc_html__('Retirer', 'mj-member'); ?>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Recto / verso', 'mj-member'); ?></th>
                    <td>
                        <label>
                            <input type="hidden" name="double_sided" value="0" />
                            <input type="checkbox" name="double_sided" value="1" data-role="double-sided-toggle" <?php checked($values['double_sided']); ?> />
                            <span><?php esc_html_e('Générer également une page verso avec le décor configuré.', 'mj-member'); ?></span>
                        </label>
                        <p class="description"><?php esc_html_e('Chaque planche de cartes sera suivie d’une page verso calée sur la même grille.', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr data-double-sided="background"<?php echo $values['double_sided'] ? '' : ' style="display:none;"'; ?>>
                    <th scope="row"><?php esc_html_e('Image décorative (verso)', 'mj-member'); ?></th>
                    <td>
                        <div class="mj-member-cards-media-picker" data-role="cards-background-picker">
                            <input type="hidden" id="mj-background-back-image-id" name="background_back_image_id" value="<?php echo esc_attr($values['background_back_image_id']); ?>" data-role="attachment-field" />
                            <div class="mj-member-cards-media-picker__preview" data-role="preview"<?php echo $back_background_preview_url ? '' : ' style="display:none;"'; ?> style="margin:8px 0;max-width:220px;border:1px solid rgba(148,163,184,0.4);border-radius:8px;overflow:hidden;">
                                <img src="<?php echo $back_background_preview_url ? esc_url($back_background_preview_url) : ''; ?>" alt="<?php esc_attr_e('Aperçu du décor verso sélectionné', 'mj-member'); ?>" style="display:block;width:100%;height:auto;" />
                            </div>
                            <p class="description" data-role="placeholder"<?php echo $back_background_preview_url ? ' style="display:none;"' : ''; ?>><?php esc_html_e('Aucune image sélectionnée pour le verso.', 'mj-member'); ?></p>
                            <p class="description"><?php esc_html_e('Sélectionnez une image spécifique pour le verso. À défaut, la page arrière restera unie.', 'mj-member'); ?></p>
                            <div class="mj-member-cards-media-picker__actions">
                                <button type="button" class="button" data-role="select">
                                    <?php echo esc_html__('Choisir une image', 'mj-member'); ?>
                                </button>
                                <button type="button" class="button-link" data-role="remove"<?php echo $back_background_preview_url ? '' : ' style="display:none;"'; ?>>
                                    <?php echo esc_html__('Retirer', 'mj-member'); ?>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-text-color"><?php esc_html_e('Couleur du texte', 'mj-member'); ?></label></th>
                    <td>
                        <input type="color" id="mj-text-color" name="text_color" value="<?php echo esc_attr($values['text_color']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-font"><?php esc_html_e('Police utilisée', 'mj-member'); ?></label></th>
                    <td>
                        <select name="font" id="mj-font">
                            <?php foreach ($fonts_options as $font_key => $font_label) : ?>
                                <option value="<?php echo esc_attr($font_key); ?>" <?php selected($values['font'], $font_key); ?>>
                                    <?php echo esc_html($font_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Les polices proposées sont compatibles avec le moteur PDF FPDF.', 'mj-member'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mj-logo-id"><?php esc_html_e('Logo MJ (ID média)', 'mj-member'); ?></label></th>
                    <td>
                        <input type="number" id="mj-logo-id" name="logo_id" value="<?php echo esc_attr($values['logo_id']); ?>" min="0" step="1" />
                        <p class="description"><?php esc_html_e('Saisissez l’ID d’une image dans la médiathèque. Elle sera affichée sur chaque carte (PNG/JPG).', 'mj-member'); ?></p>
                    </td>
                </tr>
                </tbody>
            </table>

            <?php submit_button(__('Générer le PDF', 'mj-member')); ?>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modeInputs = document.querySelectorAll('input[name="selection_mode"]');
            var sections = document.querySelectorAll('[data-selection-section]');

            function refreshSections() {
                var current = document.querySelector('input[name="selection_mode"]:checked');
                var activeValue = current ? current.value : 'all';
                sections.forEach(function (section) {
                    section.style.display = (section.getAttribute('data-selection-section') === activeValue) ? 'table-row' : 'none';
                });
            }

            modeInputs.forEach(function (input) {
                input.addEventListener('change', refreshSections);
            });
            refreshSections();

            var doubleSidedToggle = document.querySelector('[data-role="double-sided-toggle"]');
            var doubleSidedSections = document.querySelectorAll('[data-double-sided]');

            function refreshDoubleSided() {
                var enabled = doubleSidedToggle ? doubleSidedToggle.checked : false;
                doubleSidedSections.forEach(function (section) {
                    section.style.display = enabled ? 'table-row' : 'none';
                });
            }

            if (doubleSidedToggle) {
                doubleSidedToggle.addEventListener('change', refreshDoubleSided);
            }
            refreshDoubleSided();

            if (window.wp && wp.media) {
                var pickers = document.querySelectorAll('[data-role="cards-background-picker"]');

                pickers.forEach(function (picker) {
                    if (!picker) {
                        return;
                    }

                    var field = picker.querySelector('[data-role="attachment-field"]');
                    if (!field) {
                        return;
                    }

                    var preview = picker.querySelector('[data-role="preview"]');
                    var previewImg = preview ? preview.querySelector('img') : null;
                    var placeholder = picker.querySelector('[data-role="placeholder"]');
                    var selectBtn = picker.querySelector('[data-role="select"]');
                    var removeBtn = picker.querySelector('[data-role="remove"]');
                    var frame = null;

                    function updatePreview(url, attachmentId) {
                        var hasUrl = !!url;
                        if (typeof attachmentId !== 'undefined') {
                            field.value = attachmentId;
                        }
                        if (!hasUrl && attachmentId === undefined) {
                            field.value = '';
                        }
                        if (preview) {
                            if (!hasUrl) {
                                preview.style.display = 'none';
                                if (previewImg) {
                                    previewImg.removeAttribute('src');
                                }
                            } else {
                                preview.style.display = '';
                                if (previewImg) {
                                    previewImg.setAttribute('src', url);
                                }
                            }
                        }
                        if (placeholder) {
                            placeholder.style.display = hasUrl ? 'none' : '';
                        }
                        if (removeBtn) {
                            removeBtn.style.display = hasUrl ? '' : 'none';
                        }
                    }

                    if (selectBtn) {
                        selectBtn.addEventListener('click', function (event) {
                            event.preventDefault();
                            if (frame) {
                                frame.open();
                                return;
                            }

                            frame = wp.media({
                                title: '<?php echo esc_js(__('Choisir une image de fond', 'mj-member')); ?>',
                                library: {
                                    type: 'image'
                                },
                                button: {
                                    text: '<?php echo esc_js(__('Utiliser cette image', 'mj-member')); ?>'
                                },
                                multiple: false
                            });

                            frame.on('select', function () {
                                var attachment = frame.state().get('selection').first();
                                if (!attachment) {
                                    return;
                                }

                                attachment = attachment.toJSON();
                                var imageUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                                updatePreview(imageUrl, attachment.id);
                            });

                            frame.open();
                        });
                    }

                    if (removeBtn) {
                        removeBtn.addEventListener('click', function (event) {
                            event.preventDefault();
                            updatePreview('', '');
                        });
                    }

                    if (previewImg && previewImg.getAttribute('src')) {
                        updatePreview(previewImg.getAttribute('src'));
                    } else {
                        updatePreview('');
                    }
                });
            }
        });
    </script>
    <?php
}
