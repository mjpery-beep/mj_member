<?php

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Table\MjEvents_List_Table;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mj_event_type_action'])) {
    $can_manage = current_user_can(Config::capability());
    $nonce_ok = isset($_POST['mj_manage_event_types_nonce']) && wp_verify_nonce(wp_unslash((string) $_POST['mj_manage_event_types_nonce']), 'mj_manage_event_types');

    $notice_type = 'success';
    $notice_message = '';

    if (!$can_manage || !$nonce_ok) {
        $notice_type = 'error';
        $notice_message = __('Action non autorisée.', 'mj-member');
    } else {
        $action = sanitize_key((string) wp_unslash($_POST['mj_event_type_action']));

        if ($action === 'add') {
            $type_key_raw = isset($_POST['mj_event_type_key']) ? (string) wp_unslash($_POST['mj_event_type_key']) : '';
            $type_label_raw = isset($_POST['mj_event_type_label']) ? (string) wp_unslash($_POST['mj_event_type_label']) : '';

            $type_label = sanitize_text_field($type_label_raw);
            $type_key = sanitize_key($type_key_raw);
            if ($type_key === '' && $type_label !== '') {
                $type_key = sanitize_key(remove_accents($type_label));
            }

            $result = MjEvents::add_custom_type($type_key, $type_label);
            if (is_wp_error($result)) {
                $notice_type = 'error';
                $notice_message = $result->get_error_message();
            } else {
                $notice_message = __('Type ajouté avec succès.', 'mj-member');
            }
        } elseif ($action === 'delete') {
            $type_key = isset($_POST['mj_event_type_key']) ? sanitize_key((string) wp_unslash($_POST['mj_event_type_key'])) : '';
            $result = MjEvents::remove_custom_type($type_key);
            if (is_wp_error($result)) {
                $notice_type = 'error';
                $notice_message = $result->get_error_message();
            } else {
                $notice_message = __('Type supprimé avec succès.', 'mj-member');
            }
        } else {
            $notice_type = 'error';
            $notice_message = __('Action inconnue.', 'mj-member');
        }
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'mj_events',
            'mj_events_message' => rawurlencode($notice_message),
            'mj_events_message_type' => $notice_type,
        ),
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

$table = new MjEvents_List_Table();
$table->process_table_actions();
$table->prepare_items();

$notice_raw = isset($_GET['mj_events_message']) ? wp_unslash($_GET['mj_events_message']) : '';
if ($notice_raw !== '') {
    $message = sanitize_text_field(rawurldecode($notice_raw));
    $type    = isset($_GET['mj_events_message_type']) ? sanitize_key(wp_unslash($_GET['mj_events_message_type'])) : 'success';

    $class = 'notice notice-success';
    if ($type === 'error') {
        $class = 'notice notice-error';
    } elseif ($type === 'warning') {
        $class = 'notice notice-warning';
    }

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

$new_event_url = add_query_arg(
    array(
        'page'   => 'mj_events',
        'action' => 'add',
    ),
    admin_url('admin.php')
);

$custom_event_types = MjEvents::get_custom_type_labels();
?>

<div class="postbox" style="max-width:960px;margin:0 0 16px 0;padding:12px 16px;">
    <h2 style="margin:0 0 12px 0;"><?php esc_html_e('Types d\'événements personnalisés', 'mj-member'); ?></h2>
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

    <?php if (!empty($custom_event_types)) : ?>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($custom_event_types as $type_key => $type_label) : ?>
                <form method="post" style="display:inline-flex;gap:6px;align-items:center;padding:6px 8px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">
                    <?php wp_nonce_field('mj_manage_event_types', 'mj_manage_event_types_nonce'); ?>
                    <input type="hidden" name="mj_event_type_action" value="delete" />
                    <input type="hidden" name="mj_event_type_key" value="<?php echo esc_attr($type_key); ?>" />
                    <span><strong><?php echo esc_html($type_label); ?></strong> <code><?php echo esc_html($type_key); ?></code></span>
                    <button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Supprimer ce type personnalisé ?', 'mj-member')); ?>');"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p style="margin-top:12px;color:#50575e;"><?php esc_html_e('Aucun type personnalisé pour le moment.', 'mj-member'); ?></p>
    <?php endif; ?>
</div>

<div class="mj-events-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <a href="<?php echo esc_url($new_event_url); ?>" class="button button-primary">➕ Ajouter un événement</a>
    </div>
</div>

<form method="get">
    <input type="hidden" name="page" value="mj_events" />
    <?php $table->search_box('Rechercher un événement', 'mj-events-search'); ?>
    <?php $table->display(); ?>
</form>
