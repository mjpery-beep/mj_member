<?php

use Mj\Member\Core\Config;

// Admin page for email templates
function mj_email_templates_page() {
    $capability = Config::capability();

    if (!current_user_can($capability)) {
        wp_die(esc_html__('Accès refusé.', 'mj-member'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mj_email_templates';

    // Handle save
    if (isset($_POST['mj_save_template']) && check_admin_referer('mj_save_template_nonce')) {
        $post_data = wp_unslash($_POST);

        $id = isset($post_data['template_id']) ? (int) $post_data['template_id'] : 0;
        $slug = isset($post_data['template_slug']) ? sanitize_title($post_data['template_slug']) : '';
        $subject = isset($post_data['template_subject']) ? sanitize_text_field($post_data['template_subject']) : '';
        $content = isset($post_data['template_content']) ? wp_kses_post($post_data['template_content']) : '';
        $sms_raw = isset($post_data['template_sms_content']) ? $post_data['template_sms_content'] : '';
        $sms_content = is_string($sms_raw) ? sanitize_textarea_field($sms_raw) : '';

        if ($id > 0) {
            $wpdb->update(
                $table,
                array(
                    'slug' => $slug,
                    'subject' => $subject,
                    'content' => $content,
                    'sms_content' => $sms_content,
                ),
                array('id' => $id),
                array('%s','%s','%s','%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'slug' => $slug,
                    'subject' => $subject,
                    'content' => $content,
                    'sms_content' => $sms_content,
                ),
                array('%s','%s','%s','%s')
            );
            $id = $wpdb->insert_id;
        }

        echo '<div class="notice notice-success"><p>Template sauvegardé</p></div>';
    }

    // Handle delete
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $get_action = sanitize_key($_GET['action']);
        $template_id = intval($_GET['id']);
        $nonce_value = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if ($get_action !== 'delete' || !$nonce_value || !wp_verify_nonce($nonce_value, 'mj_delete_template')) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }
        $wpdb->delete($table, array('id' => $template_id), array('%d'));
        echo '<div class="notice notice-success"><p>Template supprimé</p></div>';
    }

    // Edit or Add
    $editing = false;
    $adding = false;
    $template = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $editing = true;
        $template_id = intval($_GET['id']);
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $template_id));
    } elseif (isset($_GET['action']) && $_GET['action'] === 'add') {
        $adding = true;
    }

    // List templates
    $templates = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");

    ?>
    <div class="wrap">
        <h1>Template emails</h1>
        <a class="page-title-action" href="<?php echo esc_url(add_query_arg(array('page'=>'mj_email_templates','action'=>'add'))); ?>">Ajouter un template</a>

        <h2 style="margin-top:20px">Liste</h2>
        <table class="widefat">
            <thead><tr><th>ID</th><th>Slug</th><th>Sujet</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach($templates as $t): ?>
                <tr>
                    <td><?php echo esc_html($t->id); ?></td>
                    <td><?php echo esc_html($t->slug); ?></td>
                    <td><?php echo esc_html($t->subject); ?></td>
                    <td>
                        <a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'mj_email_templates','action'=>'edit','id'=>$t->id))); ?>">Éditer</a>
                        <a class="button button-danger" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page'=>'mj_email_templates','action'=>'delete','id'=>$t->id)), 'mj_delete_template')); ?>" onclick="return confirm('Supprimer ?')">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($editing || $adding): ?>
            <div style="margin-top:30px; max-width:900px;">
                <h2><?php echo $editing ? 'Éditer' : 'Nouveau'; ?> Template</h2>
                <form method="post">
                    <?php wp_nonce_field('mj_save_template_nonce'); ?>
                    <input type="hidden" name="template_id" value="<?php echo $editing ? esc_attr($template->id) : ''; ?>">
                    <p>
                        <label>Slug (identifiant, ex: welcome_email)</label><br>
                        <input type="text" name="template_slug" value="<?php echo $editing ? esc_attr($template->slug) : ''; ?>" class="regular-text">
                    </p>
                    <p>
                        <label>Sujet</label><br>
                        <input type="text" name="template_subject" value="<?php echo $editing ? esc_attr($template->subject) : ''; ?>" class="regular-text">
                    </p>
                    <p>
                        <label>Contenu</label><br>
                            <?php
                            $content = $editing ? $template->content : '';
                            $sms_content_value = $editing ? (isset($template->sms_content) ? (string) $template->sms_content : '') : '';
                            ?>
                            <div class="mj-template-editor-wrapper" style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap;">
                                <div class="mj-template-editor-main" style="flex:1 1 480px; min-width:280px;">
                                    <?php
                                    wp_editor($content, 'template_content', array('textarea_name' => 'template_content', 'textarea_rows' => 10));
                                    ?>
                                </div>
                                <aside class="mj-template-variables-wrapper" data-collapsed="0" style="flex:0 0 660px; max-width:660px; background:#fff8e1; border:1px solid #ffe08a; border-radius:6px; padding:12px; position:relative;">
                                    <div class="mj-template-variables__header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px;">
                                        <strong style="margin:0;">Variables disponibles</strong>
                                        <button type="button" class="button mj-template-variables__toggle" aria-expanded="true">Masquer</button>
                                    </div>
                                    <div class="mj-template-variables__content" style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px;">
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Membre</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{member_first_name}}</code> – Prénom du membre</li>
                                                <li><code>{{member_last_name}}</code> – Nom du membre</li>
                                                <li><code>{{member_full_name}}</code> – Nom complet</li>
                                                <li><code>{{member_email}}</code> – Email principal</li>
                                                <li><code>{{member_phone}}</code> – Téléphone principal</li>
                                                <li><code>{{member_role}}</code> – Rôle MJ (jeune, tuteur…)</li>
                                                <li><code>{{member_subscribe_url}}</code> – URL d'activation du compte</li>
                                            </ul>
                                        </div>
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Tuteur</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{guardian_first_name}}</code> – Prénom du tuteur</li>
                                                <li><code>{{guardian_last_name}}</code> – Nom du tuteur</li>
                                                <li><code>{{guardian_full_name}}</code> – Nom complet</li>
                                                <li><code>{{guardian_email}}</code> – Email de contact</li>
                                                <li><code>{{guardian_summary_plain}}</code> – Résumé texte</li>
                                                <li><code>{{guardian_summary_html}}</code> – Résumé mis en forme</li>
                                            </ul>
                                        </div>
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Paiement</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{payment_amount}}</code> – Montant formaté (ex : 20,00)</li>
                                                <li><code>{{payment_amount_raw}}</code> – Montant brut (ex : 20)</li>
                                                <li><code>{{payment_link}}</code> – Lien de paiement</li>
                                                <li><code>{{payment_checkout_url}}</code> – URL complète de paiement</li>
                                                <li><code>{{payment_qr_url}}</code> – URL vers le QR code</li>
                                                <li><code>{{payment_qr_block}}</code> – Bloc HTML du QR code</li>
                                                <li><code>{{payment_reference}}</code> – Référence interne</li>
                                                <li><code>{{payment_date}}</code> – Date de paiement</li>
                                                <li><code>{{payment_last_date}}</code> – Dernier paiement enregistré</li>
                                                <li><code>{{cash_payment_note}}</code> – Message de paiement en espèces</li>
                                            </ul>
                                        </div>
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Paiements multiples</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{children_payment_table}}</code> – Tableau HTML des paiements</li>
                                                <li><code>{{children_payment_list}}</code> – Liste HTML</li>
                                                <li><code>{{children_payment_list_plain}}</code> – Liste texte</li>
                                                <li><code>{{children_payment_total}}</code> – Total formaté</li>
                                                <li><code>{{children_payment_total_raw}}</code> – Total brut</li>
                                                <li><code>{{children_payment_count}}</code> – Nombre de paiements</li>
                                            </ul>
                                        </div>
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Événement</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{event_title}}</code> – Titre de l'événement</li>
                                                <li><code>{{event_dates}}</code> – Dates formatées</li>
                                                <li><code>{{capacity_total}}</code> – Capacité totale</li>
                                                <li><code>{{active_registrations}}</code> – Inscriptions actives</li>
                                                <li><code>{{remaining_slots}}</code> – Places restantes</li>
                                                <li><code>{{threshold}}</code> – Seuil d'alerte</li>
                                                <li><code>{{event_admin_url}}</code> – URL d'administration</li>
                                                <li><code>{{event_admin_link}}</code> – Lien HTML vers l'événement</li>
                                                <li><code>{{event_details_list}}</code> – Liste HTML des détails</li>
                                            </ul>
                                        </div>
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Inscriptions</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{registration_type}}</code> – Code du type (guardian/member)</li>
                                                <li><code>{{registration_type_label}}</code> – Libellé lisible</li>
                                                <li><code>{{registration_status}}</code> – Statut</li>
                                                <li><code>{{registration_created_at}}</code> – Date de création</li>
                                                <li><code>{{registration_notes}}</code> – Notes internes</li>
                                                <li><code>{{participant_name}}</code> – Nom du participant</li>
                                                <li><code>{{animateurs_list}}</code> – Animateurs référents</li>
                                                <li><code>{{is_waitlist}}</code> – "1" si liste d'attente</li>
                                                <li><code>{{is_promotion}}</code> – "1" si promotion</li>
                                            </ul>
                                        </div>
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Participants</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{children_list}}</code> – Liste texte des jeunes</li>
                                                <li><code>{{children_list_html}}</code> – Liste HTML des jeunes</li>
                                                <li><code>{{member_contact_plain}}</code> – Coordonnées membre (texte)</li>
                                                <li><code>{{member_contact_html}}</code> – Coordonnées membre (HTML)</li>
                                            </ul>
                                        </div>
                                        <div class="mj-template-variables__group" style="background:#fff; border:1px solid #ffe08a; border-radius:6px; padding:10px;">
                                            <strong style="display:block; margin-bottom:6px;">Divers</strong>
                                            <ul style="margin:0; padding:0; list-style:none;">
                                                <li><code>{{date_inscription}}</code> – Date d'inscription</li>
                                                <li><code>{{site_name}}</code> – Nom du site</li>
                                                <li><code>{{site_url}}</code> – Adresse du site</li>
                                                <li><code>{{today}}</code> – Date du jour</li>
                                                <li><code>{{audience}}</code> – Public visé (admin, member…)</li>
                                                <li><code>{{tutor_nom}}</code> – Nom tuteur (legacy)</li>
                                                <li><code>{{tutor_prenom}}</code> – Prénom tuteur (legacy)</li>
                                                <li><code>{{tutor_email}}</code> – Email tuteur (legacy)</li>
                                                <li><code>{{date_last_payement}}</code> – Dernier paiement (legacy)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </aside>
                            </div>
                    </p>
                    <p>
                        <label>Contenu SMS (texte court, sans mise en forme)</label><br>
                        <textarea name="template_sms_content" rows="4" class="large-text" placeholder="Message concis avec variables optionnelles."><?php echo esc_textarea($sms_content_value); ?></textarea>
                        <span class="description">Utilisez les mêmes variables que pour l'email (ex&nbsp;: <code>{{member_first_name}}</code>). Gardez le message sous 160 caractères.</span>
                    </p>
                    <p>
                        <button class="button button-primary" type="submit" name="mj_save_template"><?php echo $editing ? 'Mettre à jour' : 'Créer'; ?></button>
                        <a class="button" href="<?php echo esc_url(remove_query_arg(array('action','id'))); ?>">Retour à la liste</a>
                    </p>
                </form>
                <?php
                static $mj_template_editor_assets_printed = false;
                if (!$mj_template_editor_assets_printed) {
                    $mj_template_editor_assets_printed = true;
                    ?>
                    <style>
                        .mj-template-variables-wrapper.is-collapsed {
                            flex: 0 0 180px !important;
                            max-width: 180px !important;
                            padding-right: 12px;
                        }

                        .mj-template-variables-wrapper.is-collapsed .mj-template-variables__content {
                            display: none !important;
                        }

                        .mj-template-variables-wrapper.is-collapsed .mj-template-variables__header {
                            margin-bottom: 0;
                        }

                        .mj-template-variables__toggle.button {
                            white-space: nowrap;
                        }

                        @media (max-width: 720px) {
                            .mj-template-variables-wrapper[data-collapsed="0"] .mj-template-variables__content {
                                grid-template-columns: 1fr;
                            }
                        }
                    </style>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var toggleButtons = document.querySelectorAll('.mj-template-variables__toggle');
                            toggleButtons.forEach(function (button) {
                                button.addEventListener('click', function () {
                                    var wrapper = button.closest('.mj-template-variables-wrapper');
                                    if (!wrapper) {
                                        return;
                                    }
                                    wrapper.classList.toggle('is-collapsed');
                                    var collapsed = wrapper.classList.contains('is-collapsed');
                                    button.textContent = collapsed ? 'Afficher' : 'Masquer';
                                    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                                });
                            });
                        });
                    </script>
                    <?php
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// (function `mj_email_templates_page` is defined above)
