<?php
/**
 * AJAX handlers for Expense Reports (front-end).
 *
 * @package MJ_Member
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjExpenses;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjTodoProjects;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\Config;

/**
 * Localize expense reports script data.
 *
 * @return void
 */
function mj_member_expenses_localize(): void
{
    $userId = get_current_user_id();
    $memberObj = $userId ? MjMembers::getByWpUserId($userId) : null;
    $member = $memberObj ? $memberObj->toArray() : null;
    $memberId = $member ? (int) $member['id'] : 0;
    $memberRole = $member ? ($member['role'] ?? '') : '';
    $isCoordinator = MjRoles::isCoordinateur($memberRole);
    $hasAccess = MjRoles::isAnimateurOrCoordinateur($memberRole) || MjRoles::isBenevole($memberRole);

    // Get own expenses
    $ownExpenses = array();
    if ($memberId > 0) {
        $rows = MjExpenses::get_all(array('member_id' => $memberId, 'limit' => 0));
        $ownExpenses = MjExpenses::enrich($rows);
    }

    // Get events list for selects
    $eventsList = array();
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjEvents')) {
        $eventsTable = function_exists('mj_member_get_events_table_name') ? mj_member_get_events_table_name() : '';
        if ($eventsTable !== '' && function_exists('mj_member_table_exists') && mj_member_table_exists($eventsTable)) {
            global $wpdb;
            $eventRows = $wpdb->get_results(
                "SELECT id, title FROM {$eventsTable} ORDER BY date_debut DESC LIMIT 200"
            );
            if (is_array($eventRows)) {
                foreach ($eventRows as $ev) {
                    $eventsList[] = array(
                        'id' => (int) $ev->id,
                        'title' => $ev->title,
                    );
                }
            }
        }
    }

    // Get projects list
    $projectsList = array();
    if (class_exists('Mj\\Member\\Classes\\Crud\\MjTodoProjects')) {
        $allProjects = MjTodoProjects::get_all(array('orderby' => 'title', 'order' => 'ASC', 'limit' => 0));
        if (is_array($allProjects)) {
            foreach ($allProjects as $p) {
                $projectsList[] = array(
                    'id' => is_array($p) ? (int) ($p['id'] ?? 0) : (int) ($p->id ?? 0),
                    'title' => is_array($p) ? ($p['title'] ?? '') : ($p->title ?? ''),
                );
            }
        }
    }

    // Coordinator: all expenses + members list
    $allExpenses = array();
    $membersList = array();
    if ($isCoordinator) {
        $rows = MjExpenses::get_all(array('limit' => 0));
        $allExpenses = MjExpenses::enrich($rows);

        $allMembers = MjMembers::get_all(array(
            'filters' => array(
                'roles' => array(MjRoles::ANIMATEUR, MjRoles::COORDINATEUR, MjRoles::BENEVOLE),
            ),
        ));
        $allMembers = array_filter($allMembers, function ($m) {
            return $m->status === 'active';
        });
        foreach ($allMembers as $m) {
            $avatarUrl = '';
            if (!empty($m->photo_id)) {
                $avatarUrl = wp_get_attachment_image_url((int) $m->photo_id, 'thumbnail') ?? '';
            }
            $membersList[] = array(
                'id' => (int) $m->id,
                'name' => trim($m->first_name . ' ' . $m->last_name),
                'role' => $m->role,
                'avatar' => $avatarUrl,
            );
        }
    }

    // Format expenses for JS
    $formatExpense = function ($exp) {
        $files = MjExpenses::decode_receipt_files($exp->receipt_file ?? '');
        $receipts = array();
        foreach ($files as $idx => $fname) {
            $receipts[] = array(
                'index' => $idx,
                'ext'   => strtolower(pathinfo($fname, PATHINFO_EXTENSION)),
            );
        }
        return array(
            'id'              => (int) $exp->id,
            'member_id'       => (int) $exp->member_id,
            'member_name'     => $exp->member_name ?? '',
            'amount'          => (float) $exp->amount,
            'description'     => $exp->description ?? '',
            'project_id'      => $exp->project_id ? (int) $exp->project_id : null,
            'project_name'    => $exp->project_name ?? '',
            'receipt_file'    => !empty($files),
            'receipts'        => $receipts,
            'status'          => $exp->status,
            'event_ids'       => $exp->event_ids ?? array(),
            'reviewed_by'     => $exp->reviewed_by ? (int) $exp->reviewed_by : null,
            'reviewer_comment' => $exp->reviewer_comment ?? '',
            'created_at'      => $exp->created_at ?? '',
            'updated_at'      => $exp->updated_at ?? '',
            'is_paid'         => !empty($exp->is_paid),
            'payment_method'  => $exp->payment_method ?? '',
            'payment_date'    => $exp->payment_date ?? '',
            'bank_statement'  => $exp->bank_statement ?? '',
        );
    };

    wp_localize_script('mj-member-expenses', 'mjExpenses', array(
        'ajaxUrl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('mj-expenses'),
        'memberId'      => $memberId,
        'isCoordinator' => $isCoordinator,
        'hasAccess'     => $hasAccess,
        'events'        => $eventsList,
        'projects'      => $projectsList,
        'members'       => $membersList,
        'ownExpenses'   => array_values(array_map($formatExpense, $ownExpenses)),
        'allExpenses'   => $isCoordinator ? array_values(array_map($formatExpense, $allExpenses)) : array(),
        'statusLabels'  => MjExpenses::get_status_labels(),
        'i18n'          => array(
            'title'               => __('Notes de Frais', 'mj-member'),
            'newExpense'          => __('Nouveau document', 'mj-member'),
            'amount'              => __('Montant (€)', 'mj-member'),
            'description'         => __('Description', 'mj-member'),
            'project'             => __('Projet', 'mj-member'),
            'events'              => __('Événements liés', 'mj-member'),
            'receipt'             => __('Justificatifs', 'mj-member'),
            'uploadReceipt'       => __('Ajouter des photos', 'mj-member'),
            'viewReceipt'         => __('Voir le justificatif', 'mj-member'),
            'status'              => __('Statut', 'mj-member'),
            'date'                => __('Date', 'mj-member'),
            'member'              => __('Membre', 'mj-member'),
            'actions'             => __('Actions', 'mj-member'),
            'reimburse'           => __('Marquer remboursé', 'mj-member'),
            'reject'              => __('Refuser', 'mj-member'),
            'delete'              => __('Supprimer', 'mj-member'),
            'approve'             => __('Approuver', 'mj-member'),
            'submit'              => __('Soumettre', 'mj-member'),
            'edit'                => __('Modifier', 'mj-member'),
            'editExpense'         => __('Modifier la note de frais', 'mj-member'),
            'cancel'              => __('Annuler', 'mj-member'),
            'close'               => __('Fermer', 'mj-member'),
            'save'                => __('Enregistrer', 'mj-member'),
            'noExpenses'          => __('Aucune note de frais pour le moment.', 'mj-member'),
            'myExpenses'          => __('Mes notes de frais', 'mj-member'),
            'allExpenses'         => __('Toutes les notes de frais', 'mj-member'),
            'filterByMember'      => __('Filtrer par membre', 'mj-member'),
            'filterByProject'     => __('Filtrer par projet', 'mj-member'),
            'filterByEvent'       => __('Filtrer par événement', 'mj-member'),
            'filterByStatus'      => __('Filtrer par statut', 'mj-member'),
            'allMembers'          => __('Tous les membres', 'mj-member'),
            'allProjects'         => __('Tous les projets', 'mj-member'),
            'allEvents'           => __('Tous les événements', 'mj-member'),
            'allStatuses'         => __('Tous les statuts', 'mj-member'),
            'noProject'           => __('— Aucun projet —', 'mj-member'),
            'pending'             => __('En attente', 'mj-member'),
            'approved'            => __('Approuvée', 'mj-member'),
            'rejected'            => __('Refusée', 'mj-member'),
            'reimbursed'          => __('Remboursée', 'mj-member'),
            'total'               => __('Total', 'mj-member'),
            'rejectionReason'     => __('Motif du refus', 'mj-member'),
            'rejectionReasonRequired' => __('Veuillez indiquer un motif de refus.', 'mj-member'),
            'success'             => __('Opération réussie.', 'mj-member'),
            'error'               => __('Une erreur est survenue.', 'mj-member'),
            'confirmDelete'       => __('Êtes-vous sûr de vouloir supprimer cette note de frais ?', 'mj-member'),
            'amountRequired'      => __('Le montant est requis.', 'mj-member'),
            'descriptionRequired' => __('La description est requise.', 'mj-member'),
            'currency'            => __('€', 'mj-member'),
            'summaryByMember'     => __('Résumé par membre', 'mj-member'),
            'pendingAmount'       => __('En attente', 'mj-member'),
            'reimbursedAmount'    => __('Remboursé', 'mj-member'),
            'selectEvents'        => __('Sélectionner les événements', 'mj-member'),
            'selectProject'       => __('Sélectionner un projet', 'mj-member'),
            'isPaid'              => __('La facture est payée', 'mj-member'),
            'paymentMethod'       => __('Mode de paiement', 'mj-member'),
            'unpaid'              => __('À payer', 'mj-member'),
            'paymentDate'         => __('Date du paiement', 'mj-member'),
            'bankStatement'       => __('Extrait CB', 'mj-member'),
            'cashMj'              => __('Cash MJ', 'mj-member'),
            'cbMj'                => __('CB MJ', 'mj-member'),
            'cashPerso'           => __('Cash personnel', 'mj-member'),
            'cbPerso'             => __('CB personnel', 'mj-member'),
            'markAsReimbursed'    => __('Marquer comme remboursé', 'mj-member'),
            'accountingized'      => __('Comptabilisé', 'mj-member'),
        ),
    ));
}

/**
 * Register AJAX actions for expenses.
 *
 * @return void
 */
function mj_member_register_expenses_ajax(): void
{
    add_action('wp_ajax_mj_expense_create', 'mj_member_expense_create_handler');
    add_action('wp_ajax_mj_expense_update', 'mj_member_expense_update_handler');
    add_action('wp_ajax_mj_expense_update_status', 'mj_member_expense_update_status_handler');
    add_action('wp_ajax_mj_expense_delete', 'mj_member_expense_delete_handler');
    add_action('wp_ajax_mj_expense_receipt', 'mj_member_expense_receipt_handler');
    add_action('wp_ajax_mj_expense_bank_statement', 'mj_member_expense_bank_statement_handler');
}
add_action('init', 'mj_member_register_expenses_ajax');

/**
 * Get the current member or fail.
 *
 * @return array{member: array, memberId: int, isCoordinator: bool}
 */
function mj_member_expenses_get_current_member(): array
{
    $userId = get_current_user_id();
    if (!$userId) {
        wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 403);
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        wp_send_json_error(array('message' => __('Membre introuvable.', 'mj-member')), 403);
    }

    $role = $member['role'] ?? '';
    $hasAccess = MjRoles::isAnimateurOrCoordinateur($role) || MjRoles::isBenevole($role);
    if (!$hasAccess) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    return array(
        'member'        => $member,
        'memberId'      => (int) $member['id'],
        'isCoordinator' => MjRoles::isCoordinateur($role),
    );
}

/**
 * Create an expense.
 *
 * @return void
 */
function mj_member_expense_create_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-expenses')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_expenses_get_current_member();
    $memberId = $ctx['memberId'];

    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int) $_POST['project_id'] : null;
    $eventIds = isset($_POST['event_ids']) ? array_map('intval', (array) $_POST['event_ids']) : array();

    // Payment fields
    $isPaid = !empty($_POST['is_paid']);
    $paymentMethod = $isPaid && isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : null;
    $paymentDate = $isPaid && isset($_POST['payment_date']) ? sanitize_text_field(wp_unslash($_POST['payment_date'])) : null;

    if ($amount <= 0) {
        wp_send_json_error(array('message' => __('Le montant doit être supérieur à 0.', 'mj-member')), 400);
    }
    if ($description === '') {
        wp_send_json_error(array('message' => __('La description est requise.', 'mj-member')), 400);
    }

    // Handle file uploads (multiple)
    $receiptFiles = array();
    if (!empty($_FILES['receipts'])) {
        // Normalise PHP multi-file upload array
        $files = $_FILES['receipts'];
        if (is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ((int) $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $single = array(
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                );
                $uploaded = mj_member_expenses_handle_upload($single, $memberId);
                if (is_wp_error($uploaded)) {
                    wp_send_json_error(array('message' => $uploaded->get_error_message()), 400);
                }
                $receiptFiles[] = $uploaded;
            }
        } else {
            // Single file sent as receipts (no [] in name)
            if ((int) $files['error'] === UPLOAD_ERR_OK) {
                $uploaded = mj_member_expenses_handle_upload($files, $memberId);
                if (is_wp_error($uploaded)) {
                    wp_send_json_error(array('message' => $uploaded->get_error_message()), 400);
                }
                $receiptFiles[] = $uploaded;
            }
        }
    }
    // Legacy: single receipt field
    if (empty($receiptFiles) && !empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $uploaded = mj_member_expenses_handle_upload($_FILES['receipt'], $memberId);
        if (is_wp_error($uploaded)) {
            wp_send_json_error(array('message' => $uploaded->get_error_message()), 400);
        }
        $receiptFiles[] = $uploaded;
    }

    // Handle bank statement upload (CB Perso only)
    $bankStatement = null;
    if ($paymentMethod === 'cb_perso' && !empty($_FILES['bank_statement']) && $_FILES['bank_statement']['error'] === UPLOAD_ERR_OK) {
        $bankFile = $_FILES['bank_statement'];
        $uploaded = mj_member_expenses_handle_upload($bankFile, $memberId, 'bank_statement');
        if (is_wp_error($uploaded)) {
            wp_send_json_error(array('message' => $uploaded->get_error_message()), 400);
        }
        $bankStatement = $uploaded;
    }

    $result = MjExpenses::create(array(
        'member_id'       => $memberId,
        'amount'          => $amount,
        'description'     => $description,
        'project_id'      => $projectId,
        'receipt_files'   => $receiptFiles,
        'status'          => MjExpenses::STATUS_PENDING,
        'is_paid'         => $isPaid,
        'payment_method'  => $paymentMethod,
        'payment_date'    => $paymentDate,
        'bank_statement'  => $bankStatement,
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 500);
    }

    // Sync events
    if (!empty($eventIds)) {
        MjExpenses::sync_events($result, $eventIds);
    }

    // Return enriched expense
    $expense = MjExpenses::get_by_id($result);
    $enriched = MjExpenses::enrich(array($expense));
    $exp = $enriched[0] ?? $expense;

    // Fire notification hook
    do_action('mj_member_expense_created', (int) $exp->id, (int) $exp->member_id, (float) $exp->amount);

    $files = MjExpenses::decode_receipt_files($exp->receipt_file ?? '');
    $receipts = array();
    foreach ($files as $idx => $fname) {
        $receipts[] = array(
            'index' => $idx,
            'ext'   => strtolower(pathinfo($fname, PATHINFO_EXTENSION)),
        );
    }

    wp_send_json_success(array(
        'message' => __('Note de frais créée avec succès.', 'mj-member'),
        'expense' => array(
            'id'           => (int) $exp->id,
            'member_id'    => (int) $exp->member_id,
            'member_name'  => $exp->member_name ?? '',
            'amount'       => (float) $exp->amount,
            'description'  => $exp->description ?? '',
            'project_id'   => $exp->project_id ? (int) $exp->project_id : null,
            'project_name' => $exp->project_name ?? '',
            'receipt_file' => !empty($files),
            'receipts'     => $receipts,
            'status'       => $exp->status,
            'event_ids'    => $exp->event_ids ?? array(),
            'created_at'   => $exp->created_at ?? '',
            'is_paid'      => (bool) ($exp->is_paid ?? false),
            'payment_method' => $exp->payment_method ?? null,
            'payment_date' => $exp->payment_date ?? null,
            'bank_statement' => $exp->bank_statement ?? null,
        ),
    ));
}

/**
 * Update an existing expense (owner only, pending status only).
 *
 * @return void
 */
function mj_member_expense_update_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-expenses')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_expenses_get_current_member();
    $memberId = $ctx['memberId'];

    $expenseId = isset($_POST['expense_id']) ? (int) $_POST['expense_id'] : 0;
    if ($expenseId <= 0) {
        wp_send_json_error(array('message' => __('Note de frais introuvable.', 'mj-member')), 400);
    }

    $expense = MjExpenses::get_by_id($expenseId);
    if (!$expense) {
        wp_send_json_error(array('message' => __('Note de frais introuvable.', 'mj-member')), 404);
    }

    // Only the owner can edit, and only when pending
    if ((int) $expense->member_id !== $memberId) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }
    if ($expense->status !== MjExpenses::STATUS_PENDING) {
        wp_send_json_error(array('message' => __('Seules les notes en attente peuvent être modifiées.', 'mj-member')), 403);
    }

    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int) $_POST['project_id'] : null;
    $eventIds = isset($_POST['event_ids']) ? array_map('intval', (array) $_POST['event_ids']) : array();

    // Payment fields
    $isPaid = !empty($_POST['is_paid']);
    $paymentMethod = $isPaid && isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : null;
    $paymentDate = $isPaid && isset($_POST['payment_date']) ? sanitize_text_field(wp_unslash($_POST['payment_date'])) : null;

    if ($amount <= 0) {
        wp_send_json_error(array('message' => __('Le montant doit être supérieur à 0.', 'mj-member')), 400);
    }
    if ($description === '') {
        wp_send_json_error(array('message' => __('La description est requise.', 'mj-member')), 400);
    }

    // Handle file deletions
    $existingFiles = MjExpenses::decode_receipt_files($expense->receipt_file);
    $deleteIndices = isset($_POST['delete_files']) ? array_map('intval', (array) $_POST['delete_files']) : array();
    if (!empty($deleteIndices)) {
        foreach ($deleteIndices as $idx) {
            if (isset($existingFiles[$idx])) {
                $fpath = MJ_MEMBER_PATH . 'data/expenses/' . $existingFiles[$idx];
                if (file_exists($fpath)) {
                    @unlink($fpath);
                }
                unset($existingFiles[$idx]);
            }
        }
        $existingFiles = array_values($existingFiles);
    }

    // Handle new file uploads (added to remaining files)
    $newFiles = array();
    if (!empty($_FILES['receipts'])) {
        $files = $_FILES['receipts'];
        if (is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ((int) $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $single = array(
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                );
                $uploaded = mj_member_expenses_handle_upload($single, $memberId);
                if (is_wp_error($uploaded)) {
                    wp_send_json_error(array('message' => $uploaded->get_error_message()), 400);
                }
                $newFiles[] = $uploaded;
            }
        } else {
            if ((int) $files['error'] === UPLOAD_ERR_OK) {
                $uploaded = mj_member_expenses_handle_upload($files, $memberId);
                if (is_wp_error($uploaded)) {
                    wp_send_json_error(array('message' => $uploaded->get_error_message()), 400);
                }
                $newFiles[] = $uploaded;
            }
        }
    }

    $allFiles = array_merge($existingFiles, $newFiles);

    // Handle bank statement upload (CB Perso only)
    $bankStatement = $expense->bank_statement ?? null; // Keep existing if not changed
    if ($paymentMethod === 'cb_perso' && !empty($_FILES['bank_statement']) && $_FILES['bank_statement']['error'] === UPLOAD_ERR_OK) {
        $bankFile = $_FILES['bank_statement'];
        $uploaded = mj_member_expenses_handle_upload($bankFile, $memberId, 'bank_statement');
        if (is_wp_error($uploaded)) {
            wp_send_json_error(array('message' => $uploaded->get_error_message()), 400);
        }
        $bankStatement = $uploaded;
    }

    $updateData = array(
        'amount'          => $amount,
        'description'     => $description,
        'project_id'      => $projectId,
        'receipt_files'   => $allFiles,
        'is_paid'         => $isPaid,
        'payment_method'  => $paymentMethod,
        'payment_date'    => $paymentDate,
        'bank_statement'  => $bankStatement,
    );

    $result = MjExpenses::update($expenseId, $updateData);
    if ($result === false || is_wp_error($result)) {
        wp_send_json_error(array('message' => __('Erreur lors de la mise à jour.', 'mj-member')), 500);
    }

    // Sync events
    MjExpenses::sync_events($expenseId, $eventIds);

    // Return enriched expense
    $updatedExpense = MjExpenses::get_by_id($expenseId);
    $enriched = MjExpenses::enrich(array($updatedExpense));
    $exp = $enriched[0] ?? $updatedExpense;

    $rFiles = MjExpenses::decode_receipt_files($exp->receipt_file ?? '');
    $receipts = array();
    foreach ($rFiles as $idx => $fname) {
        $receipts[] = array(
            'index' => $idx,
            'ext'   => strtolower(pathinfo($fname, PATHINFO_EXTENSION)),
        );
    }

    wp_send_json_success(array(
        'message' => __('Note de frais mise à jour.', 'mj-member'),
        'expense' => array(
            'id'              => (int) $exp->id,
            'member_id'       => (int) $exp->member_id,
            'member_name'     => $exp->member_name ?? '',
            'amount'          => (float) $exp->amount,
            'description'     => $exp->description ?? '',
            'project_id'      => $exp->project_id ? (int) $exp->project_id : null,
            'project_name'    => $exp->project_name ?? '',
            'receipt_file'    => !empty($rFiles),
            'receipts'        => $receipts,
            'status'          => $exp->status,
            'event_ids'       => $exp->event_ids ?? array(),
            'reviewed_by'     => $exp->reviewed_by ? (int) $exp->reviewed_by : null,
            'reviewer_comment' => $exp->reviewer_comment ?? '',
            'created_at'      => $exp->created_at ?? '',
            'updated_at'      => $exp->updated_at ?? '',
            'is_paid'         => (bool) ($exp->is_paid ?? false),
            'payment_method'  => $exp->payment_method ?? null,
            'payment_date'    => $exp->payment_date ?? null,
            'bank_statement'  => $exp->bank_statement ?? null,
        ),
    ));
}

/**
 * Update expense status (coordinator only, or reject).
 *
 * @return void
 */
function mj_member_expense_update_status_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-expenses')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_expenses_get_current_member();
    if (!$ctx['isCoordinator']) {
        wp_send_json_error(array('message' => __('Seul le coordinateur peut effectuer cette action.', 'mj-member')), 403);
    }

    $expenseId = isset($_POST['expense_id']) ? (int) $_POST['expense_id'] : 0;
    $newStatus = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
    $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

    if ($expenseId <= 0) {
        wp_send_json_error(array('message' => __('Note de frais introuvable.', 'mj-member')), 400);
    }

    $expense = MjExpenses::get_by_id($expenseId);
    if (!$expense) {
        wp_send_json_error(array('message' => __('Note de frais introuvable.', 'mj-member')), 404);
    }

    $allowedStatuses = array(
        MjExpenses::STATUS_APPROVED,
        MjExpenses::STATUS_REJECTED,
        MjExpenses::STATUS_REIMBURSED,
            MjExpenses::STATUS_ACCOUNTINGIZED,
    );

    if (!in_array($newStatus, $allowedStatuses, true)) {
        wp_send_json_error(array('message' => __('Statut invalide.', 'mj-member')), 400);
    }

    if ($newStatus === MjExpenses::STATUS_REJECTED && $comment === '') {
        wp_send_json_error(array('message' => __('Veuillez indiquer un motif de refus.', 'mj-member')), 400);
    }

    $updateData = array(
        'status'           => $newStatus,
        'reviewed_by'      => $ctx['memberId'],
        'reviewed_at'      => current_time('mysql'),
        'reviewer_comment' => $comment,
    );

    $result = MjExpenses::update($expenseId, $updateData);
    if ($result === false || is_wp_error($result)) {
        wp_send_json_error(array('message' => __('Erreur lors de la mise à jour.', 'mj-member')), 500);
    }

    // Fire notification hooks
    if ($newStatus === MjExpenses::STATUS_REIMBURSED) {
        do_action('mj_member_expense_reimbursed', $expenseId, (int) $expense->member_id, (float) $expense->amount);
    } elseif ($newStatus === MjExpenses::STATUS_REJECTED) {
        do_action('mj_member_expense_rejected', $expenseId, (int) $expense->member_id, (float) $expense->amount, $comment);
    }

    wp_send_json_success(array(
        'message' => __('Statut mis à jour avec succès.', 'mj-member'),
        'status'  => $newStatus,
    ));
}

/**
 * Delete an expense (coordinator only).
 *
 * @return void
 */
function mj_member_expense_delete_handler(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mj-expenses')) {
        wp_send_json_error(array('message' => __('Sécurité échouée.', 'mj-member')), 403);
    }

    $ctx = mj_member_expenses_get_current_member();

    $expenseId = isset($_POST['expense_id']) ? (int) $_POST['expense_id'] : 0;
    if ($expenseId <= 0) {
        wp_send_json_error(array('message' => __('Note de frais introuvable.', 'mj-member')), 400);
    }

    $expense = MjExpenses::get_by_id($expenseId);
    if (!$expense) {
        wp_send_json_error(array('message' => __('Note de frais introuvable.', 'mj-member')), 404);
    }

    // Coordinator can always delete; owner can delete only when pending
    $isOwner = (int) $expense->member_id === $ctx['memberId'];
    if (!$ctx['isCoordinator'] && !$isOwner) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }
    if ($isOwner && !$ctx['isCoordinator'] && $expense->status !== MjExpenses::STATUS_PENDING) {
        wp_send_json_error(array('message' => __('Seules les notes en attente peuvent être supprimées.', 'mj-member')), 403);
    }

    $result = MjExpenses::delete($expenseId);
    if (!$result) {
        wp_send_json_error(array('message' => __('Erreur lors de la suppression.', 'mj-member')), 500);
    }

    wp_send_json_success(array('message' => __('Note de frais supprimée.', 'mj-member')));
}

/**
 * Serve a receipt file securely (same pattern as leave-requests certificates).
 *
 * @return void
 */
function mj_member_expense_receipt_handler(): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'mj-expenses')) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    $expenseId = isset($_GET['expense_id']) ? (int) $_GET['expense_id'] : 0;
    if ($expenseId <= 0) {
        wp_die(__('Note de frais introuvable.', 'mj-member'), '', array('response' => 404));
    }

    $userId = get_current_user_id();
    if (!$userId) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    $expense = MjExpenses::get_by_id($expenseId);
    if (!$expense || empty($expense->receipt_file)) {
        wp_die(__('Fichier introuvable.', 'mj-member'), '', array('response' => 404));
    }

    // Check access: owner or coordinator
    $isOwner = (int) $member['id'] === (int) $expense->member_id;
    $isCoordinator = MjRoles::isCoordinateur($member['role'] ?? '');
    if (!$isOwner && !$isCoordinator) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    // Resolve file from JSON array by index (default 0)
    $files = MjExpenses::decode_receipt_files($expense->receipt_file);
    $fileIndex = isset($_GET['file_index']) ? (int) $_GET['file_index'] : 0;
    if (!isset($files[$fileIndex])) {
        wp_die(__('Fichier introuvable.', 'mj-member'), '', array('response' => 404));
    }

    $filePath = MJ_MEMBER_PATH . 'data/expenses/' . sanitize_file_name($files[$fileIndex]);
    if (!file_exists($filePath)) {
        wp_die(__('Fichier introuvable.', 'mj-member'), '', array('response' => 404));
    }

    // MIME type whitelist
    $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $filePath) : 'application/octet-stream';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!in_array($mimeType, $allowedTypes, true)) {
        wp_die(__('Type de fichier non autorisé.', 'mj-member'), '', array('response' => 403));
    }

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $filename = 'justificatif_' . $expenseId . '_' . ($fileIndex + 1) . '.' . $ext;

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

/**
 * Handle secure bank statement file delivery.
 *
 * @return void
 */
function mj_member_expense_bank_statement_handler(): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'mj-expenses')) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    $expenseId = isset($_GET['expense_id']) ? (int) $_GET['expense_id'] : 0;
    if ($expenseId <= 0) {
        wp_die(__('Note de frais introuvable.', 'mj-member'), '', array('response' => 404));
    }

    $userId = get_current_user_id();
    if (!$userId) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    $memberObj = MjMembers::getByWpUserId($userId);
    $member = $memberObj ? $memberObj->toArray() : null;
    if (!$member) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    $expense = MjExpenses::get_by_id($expenseId);
    if (!$expense || empty($expense->bank_statement)) {
        wp_die(__('Fichier introuvable.', 'mj-member'), '', array('response' => 404));
    }

    // Check access: owner or coordinator
    $isOwner = (int) $member['id'] === (int) $expense->member_id;
    $isCoordinator = MjRoles::isCoordinateur($member['role'] ?? '');
    if (!$isOwner && !$isCoordinator) {
        wp_die(__('Accès refusé.', 'mj-member'), '', array('response' => 403));
    }

    $filePath = MJ_MEMBER_PATH . 'data/expenses/' . sanitize_file_name($expense->bank_statement);
    if (!file_exists($filePath)) {
        wp_die(__('Fichier introuvable.', 'mj-member'), '', array('response' => 404));
    }

    // MIME type whitelist
    $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $filePath) : 'application/octet-stream';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!in_array($mimeType, $allowedTypes, true)) {
        wp_die(__('Type de fichier non autorisé.', 'mj-member'), '', array('response' => 403));
    }

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $filename = 'extrait_cb_' . $expenseId . '.' . $ext;

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

/**
 * Handle secure file upload for expense receipts.
 *
 * @param array $file $_FILES entry.
 * @param int   $memberId Member ID.
 * @return string|\WP_Error Filename on success, WP_Error on failure.
 */
function mj_member_expenses_handle_upload(array $file, int $memberId)
{
    // Validate file type
    $allowedTypes = array('application/pdf', 'image/jpeg', 'image/png', 'image/gif');
    $allowedExtensions = array('pdf', 'jpg', 'jpeg', 'png', 'gif');
    $fileType = wp_check_filetype($file['name']);
    $extension = strtolower($fileType['ext'] ?? '');

    if (!in_array($extension, $allowedExtensions, true)) {
        return new \WP_Error('invalid_type', __('Type de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, GIF.', 'mj-member'));
    }

    // Validate MIME type via finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($detectedMime, $allowedTypes, true)) {
            return new \WP_Error('invalid_mime', __('Type MIME non autorisé.', 'mj-member'));
        }
    }

    // Validate file size (max 10MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return new \WP_Error('too_large', __('Le fichier est trop volumineux (max 10 Mo).', 'mj-member'));
    }

    // Create secure directory if not exists
    $uploadDir = MJ_MEMBER_PATH . 'data/expenses/';
    if (!file_exists($uploadDir)) {
        wp_mkdir_p($uploadDir);
        // Create .htaccess to deny direct access
        $htaccess = $uploadDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
        }
        // Create index.php
        $indexFile = $uploadDir . 'index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }
    }

    // Generate unique secure filename
    $uniqueId = wp_generate_password(16, false);
    $timestamp = time();
    $secureFilename = sprintf('%d_%d_%s.%s', $memberId, $timestamp, $uniqueId, $extension);
    $targetPath = $uploadDir . $secureFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return new \WP_Error('upload_failed', __('Échec du téléchargement du fichier.', 'mj-member'));
    }

    return $secureFilename;
}
