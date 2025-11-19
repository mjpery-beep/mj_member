<?php

require_once plugin_dir_path(__FILE__) . 'classes/MjEvents_List_Table.php';

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
?>

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
