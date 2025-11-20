<?php

require_once plugin_dir_path(__FILE__) . 'classes/MjMembers_List_Table.php';

$table = new MjMembers_List_Table();

// Gérer l'enregistrement des préférences de colonnes
if (isset($_POST['mj_save_columns'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mj_column_visibility_nonce')) {
        wp_die('Accès non autorisé');
    }
    
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    
    $visible_columns = isset($_POST['mj_columns']) ? (array) $_POST['mj_columns'] : array();
    
    // Nettoyer et valider les colonnes
    $valid_columns = array('photo', 'last_name', 'first_name', 'age', 'role', 'email', 'login', 'phone', 'guardian', 'requires_payment', 'status', 'date_last_payement', 'payment_status', 'photo_usage_consent', 'date_inscription', 'actions');
    $visible_columns = array_intersect($visible_columns, $valid_columns);
    
    // Sauvegarder les préférences
    update_user_meta($user_id, 'mj_visible_columns', $visible_columns);
}

// Traiter les actions en masse AVANT de préparer les éléments
$table->process_bulk_action();

// Maintenant préparer et afficher les éléments
$table->prepare_items();

// Gestion de la suppression simple
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'mj_delete_nonce')) {
        wp_die('Accès non autorisé');
    }
    
    MjMembers_CRUD::delete(intval($_GET['id']));
    wp_redirect(admin_url('admin.php?page=mj_members'));
    exit;
}

?>

<div class="mj-members-container">
    <div style="margin-bottom: 20px;">
        <a href="<?php echo add_query_arg(array('page' => 'mj_members', 'action' => 'add')); ?>" class="button button-primary">
            ➕ Ajouter un membre
        </a>
    </div>
    
    <div class="mj-table-responsive">
        <?php
        ob_start();
        $table->display();
        $table_html = ob_get_clean();

        if ($table_html) {
            $column_headers = method_exists($table, 'get_columns') ? $table->get_columns() : array();

            if (!empty($column_headers) && class_exists('DOMDocument') && class_exists('DOMXPath')) {
                $table_dom = new DOMDocument('1.0', 'UTF-8');
                libxml_use_internal_errors(true);
                $table_dom->loadHTML('<?xml encoding="utf-8" ?>' . $table_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();

                $xpath = new DOMXPath($table_dom);
                $cells = $xpath->query('//table[contains(@class,"wp-list-table")]//tbody//tr//td');
                foreach ($cells as $cell) {
                    /** @var DOMElement $cell */
                    if (!$cell->hasAttribute('data-colname')) {
                        $class_attr = $cell->getAttribute('class');
                        $column_class = '';
                        if ($class_attr !== '') {
                            $classes = preg_split('/\s+/', $class_attr);
                            foreach ($classes as $class_name) {
                                if (strpos($class_name, 'column-') === 0) {
                                    $column_class = substr($class_name, strlen('column-'));
                                    break;
                                }
                            }
                        }

                        $label = '';
                        if ($column_class !== '' && isset($column_headers[$column_class])) {
                            $label = $column_headers[$column_class];
                        }

                        if ($label === '') {
                            $label = __('Colonne', 'mj-member');
                        }

                        $cell->setAttribute('data-colname', $label);
                    }
                }

                echo $table_dom->saveHTML();
            } else {
                echo $table_html;
            }
        }
        ?>
    </div>
</div>

<style>
.badge-success {
    background-color: #28a745;
    color: white;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.badge-warning {
    background-color: #ffc107;
    color: #000;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.button-small {
    padding: 3px 8px;
    margin-right: 5px;
    font-size: 12px;
}

.button-link-delete {
    color: #dc3545;
}

.button-link-delete:hover {
    color: #c82333;
}
</style>