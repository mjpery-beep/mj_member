<?php
// Admin page for email templates
function mj_email_templates_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'mj_email_templates';

    // Handle save
    if (isset($_POST['mj_save_template']) && check_admin_referer('mj_save_template_nonce')) {
        $id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $slug = sanitize_title($_POST['template_slug']);
        $subject = sanitize_text_field($_POST['template_subject']);
        $content = wp_kses_post($_POST['template_content']);

        if ($id > 0) {
            $wpdb->update($table, array('slug' => $slug, 'subject' => $subject, 'content' => $content), array('id' => $id), array('%s','%s','%s'), array('%d'));
        } else {
            $wpdb->insert($table, array('slug' => $slug, 'subject' => $subject, 'content' => $content), array('%s','%s','%s'));
            $id = $wpdb->insert_id;
        }

        echo '<div class="notice notice-success"><p>Template sauvegardé</p></div>';
    }

    // Handle delete
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'mj_delete_template')) wp_die('Nonce invalide');
        $wpdb->delete($table, array('id' => intval($_GET['id'])), array('%d'));
        echo '<div class="notice notice-success"><p>Template supprimé</p></div>';
    }

    // Edit or Add
    $editing = false;
    $adding = false;
    $template = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $editing = true;
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['id'])));
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
                            // Helper: list of available placeholders
                            ?>
                            <div style="margin-bottom:8px; padding:8px; background:#fff8e1; border:1px solid #ffe08a; border-radius:4px;">
                                <strong>Variables disponibles :</strong>
                                <div style="margin-top:6px;">
                                    <strong>Membre</strong><br>
                                    <code>{{member_first_name}}</code> <code>{{member_last_name}}</code> <code>{{member_full_name}}</code> <code>{{member_email}}</code> <code>{{member_phone}}</code> <code>{{member_role}}</code>
                                    <br>
                                    <strong>Tuteur</strong><br>
                                    <code>{{guardian_first_name}}</code> <code>{{guardian_last_name}}</code> <code>{{guardian_full_name}}</code> <code>{{guardian_email}}</code>
                                    <br>
                                    <strong>Paiement</strong><br>
                                    <code>{{payment_amount}}</code> <code>{{payment_amount_raw}}</code> <code>{{payment_link}}</code> <code>{{payment_qr_url}}</code> <code>{{payment_reference}}</code> <code>{{payment_last_date}}</code> <code>{{payment_date}}</code>
                                    <br>
                                    <strong>Paiements multiples</strong><br>
                                    <code>{{children_payment_table}}</code> <code>{{children_payment_list}}</code> <code>{{children_payment_list_plain}}</code> <code>{{children_payment_total}}</code> <code>{{children_payment_total_raw}}</code> <code>{{children_payment_count}}</code>
                                    <br>
                                    <strong>Divers</strong><br>
                                    <code>{{date_inscription}}</code> <code>{{site_name}}</code> <code>{{site_url}}</code> <code>{{today}}</code>
                                    <br>
                                    <code>{{tutor_nom}}</code> <code>{{tutor_prenom}}</code> <code>{{tutor_email}}</code>
                                    <code>{{date_last_payement}}</code>
                                </div>
                                <div style="margin-top:6px; color:#555; font-size:12px;">Exemple : <em>Bonjour {{jeune_prenom}} {{jeune_nom}},</em></div>
                            </div>
                            <?php
                            wp_editor($content, 'template_content', array('textarea_name'=>'template_content','textarea_rows'=>10));
                            ?>
                    </p>
                    <p>
                        <button class="button button-primary" type="submit" name="mj_save_template"><?php echo $editing ? 'Mettre à jour' : 'Créer'; ?></button>
                        <a class="button" href="<?php echo esc_url(remove_query_arg(array('action','id'))); ?>">Retour à la liste</a>
                    </p>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// (function `mj_email_templates_page` is defined above)
