<?php

use Mj\Member\Admin\Page\TodosPage;
use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Classes\Table\MjTodos_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

$mode = isset($view['mode']) ? (string) $view['mode'] : 'list';
$notice = isset($view['notice']) && is_array($view['notice']) ? $view['notice'] : array();
$projects = isset($view['projects']) && is_array($view['projects']) ? $view['projects'] : array();
$members = isset($view['members']) && is_array($view['members']) ? $view['members'] : array();
$statuses = isset($view['statuses']) && is_array($view['statuses']) ? $view['statuses'] : MjTodos::statuses();
$statusLabels = isset($view['status_labels']) && is_array($view['status_labels']) ? $view['status_labels'] : array();
$table = isset($view['table']) && $view['table'] instanceof MjTodos_List_Table ? $view['table'] : null;
$todo = isset($view['todo']) && is_array($view['todo']) ? $view['todo'] : array();

$pageSlug = TodosPage::slug();
$pageUrl = add_query_arg('page', $pageSlug, admin_url('admin.php'));

if (!isset($statusLabels[MjTodos::STATUS_OPEN])) {
    $statusLabels[MjTodos::STATUS_OPEN] = __('À faire', 'mj-member');
}
if (!isset($statusLabels[MjTodos::STATUS_COMPLETED])) {
    $statusLabels[MjTodos::STATUS_COMPLETED] = __('Terminée', 'mj-member');
}
if (!isset($statusLabels[MjTodos::STATUS_ARCHIVED])) {
    $statusLabels[MjTodos::STATUS_ARCHIVED] = __('Archivée', 'mj-member');
}

$actionUrl = admin_url('admin-post.php');

?>
<div class="wrap mj-member-todos-admin">
    <h1 class="wp-heading-inline"><?php esc_html_e('Gestion des tâches', 'mj-member'); ?></h1>

    <?php if (!empty($notice) && !empty($notice['message'])) : ?>
        <?php $noticeClass = $notice['type'] === 'error' ? 'notice-error' : 'notice-success'; ?>
        <div class="notice <?php echo esc_attr($noticeClass); ?> is-dismissible">
            <p><?php echo esc_html((string) $notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'list' && $table instanceof MjTodos_List_Table) : ?>
        <?php $table->process_bulk_action(); ?>
        <?php $table->prepare_items(); ?>

        <div class="mj-member-list-actions" style="margin:16px 0;">
            <a href="<?php echo esc_url(add_query_arg(array('page' => $pageSlug, 'action' => 'add'), admin_url('admin.php'))); ?>" class="button button-primary">
                <?php esc_html_e('➕ Ajouter une tâche', 'mj-member'); ?>
            </a>
        </div>

        <?php $table->views(); ?>

        <form method="post">
            <input type="hidden" name="page" value="<?php echo esc_attr($pageSlug); ?>">
            <?php $table->search_box(__('Rechercher des tâches', 'mj-member'), 'mj-member-todos'); ?>
            <?php $table->display(); ?>
        </form>

    <?php else : ?>
        <?php
        $todoId = isset($todo['id']) ? (int) $todo['id'] : 0;
        $isEdit = ($mode === 'edit' && $todoId > 0);
        $formAction = $isEdit ? 'mj_member_todo_update' : 'mj_member_todo_create';
        $formTitle = $isEdit ? __('Modifier la tâche', 'mj-member') : __('Créer une tâche', 'mj-member');
        $assignedMemberId = isset($todo['assigned_member_id']) ? (int) $todo['assigned_member_id'] : 0;
        $selectedAssignees = array();
        if ($assignedMemberId > 0) {
            $selectedAssignees[$assignedMemberId] = $assignedMemberId;
        }
        if (isset($todo['assignees']) && is_array($todo['assignees'])) {
            foreach ($todo['assignees'] as $assignee) {
                $candidate = isset($assignee['id']) ? (int) $assignee['id'] : 0;
                if ($candidate > 0) {
                    $selectedAssignees[$candidate] = $candidate;
                }
            }
        }
        $selectedAssignees = array_values($selectedAssignees);
        $projectId = isset($todo['project_id']) ? (int) $todo['project_id'] : 0;
        $status = isset($todo['status']) ? (string) $todo['status'] : MjTodos::STATUS_OPEN;
        $dueDate = isset($todo['due_date']) ? (string) $todo['due_date'] : '';
        $description = isset($todo['description']) ? (string) $todo['description'] : '';
        $media = isset($todo['media']) && is_array($todo['media']) ? $todo['media'] : array();
        $returnUrl = add_query_arg('page', $pageSlug, admin_url('admin.php'));
        ?>

        <div class="mj-member-todo-form" style="margin-top:24px;">
            <h2><?php echo esc_html($formTitle); ?></h2>
            <form method="post" action="<?php echo esc_url($actionUrl); ?>" class="mj-member-todo-form__form" data-mj-todo-form>
                <input type="hidden" name="action" value="<?php echo esc_attr($formAction); ?>">
                <input type="hidden" name="redirect_page" value="<?php echo esc_attr($pageSlug); ?>">
                <?php if ($isEdit) : ?>
                    <input type="hidden" name="todo_id" value="<?php echo esc_attr((string) $todoId); ?>">
                <?php endif; ?>
                <?php wp_nonce_field($formAction); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="mj-member-todo-title"><?php esc_html_e('Titre', 'mj-member'); ?></label></th>
                            <td><input type="text" name="title" id="mj-member-todo-title" class="regular-text" value="<?php echo esc_attr(isset($todo['title']) ? (string) $todo['title'] : ''); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-todo-description"><?php esc_html_e('Description', 'mj-member'); ?></label></th>
                            <td><textarea name="description" id="mj-member-todo-description" class="large-text" rows="6"><?php echo esc_textarea($description); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-todo-status"><?php esc_html_e('Statut', 'mj-member'); ?></label></th>
                            <td>
                                <select name="status" id="mj-member-todo-status">
                                    <?php foreach ($statuses as $statusValue) :
                                        $label = isset($statusLabels[$statusValue]) ? $statusLabels[$statusValue] : ucfirst($statusValue);
                                        ?>
                                        <option value="<?php echo esc_attr($statusValue); ?>" <?php selected($status, $statusValue); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-todo-project"><?php esc_html_e('Projet', 'mj-member'); ?></label></th>
                            <td>
                                <select name="project_id" id="mj-member-todo-project">
                                    <option value=""><?php esc_html_e('Aucun projet', 'mj-member'); ?></option>
                                    <?php foreach ($projects as $id => $project) :
                                        $label = isset($project['title']) ? (string) $project['title'] : sprintf(__('Projet #%d', 'mj-member'), (int) $id);
                                        ?>
                                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected($projectId, (int) $id); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-todo-assignees"><?php esc_html_e('Assignation', 'mj-member'); ?></label></th>
                            <td>
                                <select name="assigned_member_ids[]" id="mj-member-todo-assignees" multiple="multiple" size="6" style="min-width:280px;">
                                    <?php foreach ($members as $member) :
                                        $memberId = isset($member['id']) ? (int) $member['id'] : 0;
                                        if ($memberId <= 0) {
                                            continue;
                                        }
                                        $label = isset($member['label']) ? (string) $member['label'] : sprintf(__('Membre #%d', 'mj-member'), $memberId);
                                        ?>
                                        <option value="<?php echo esc_attr((string) $memberId); ?>" <?php selected(in_array($memberId, $selectedAssignees, true), true); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Sélectionnez un ou plusieurs membres à notifier.', 'mj-member'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-todo-due"><?php esc_html_e('Échéance', 'mj-member'); ?></label></th>
                            <td><input type="date" name="due_date" id="mj-member-todo-due" value="<?php echo esc_attr($dueDate !== '' ? substr($dueDate, 0, 10) : ''); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Médias associés', 'mj-member'); ?></th>
                            <td>
                                <div class="mj-todo-media-manager" data-mj-todo-media>
                                    <ul class="mj-todo-media-manager__list" data-mj-todo-media-list>
                                        <?php foreach ($media as $item) :
                                            $attachmentId = isset($item['attachment_id']) ? (int) $item['attachment_id'] : 0;
                                            if ($attachmentId <= 0) {
                                                continue;
                                            }
                                            $title = isset($item['title']) ? (string) $item['title'] : sprintf(__('Média #%d', 'mj-member'), $attachmentId);
                                            $preview = isset($item['preview_url']) ? (string) $item['preview_url'] : '';
                                            $url = isset($item['url']) ? (string) $item['url'] : '';
                                            ?>
                                            <li class="mj-todo-media-manager__item" data-media-id="<?php echo esc_attr((string) $attachmentId); ?>">
                                                <div class="mj-todo-media-manager__thumb">
                                                    <?php if ($preview !== '') : ?>
                                                        <img src="<?php echo esc_url($preview); ?>" alt="" />
                                                    <?php else : ?>
                                                        <span class="mj-todo-media-manager__placeholder" aria-hidden="true">📎</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mj-todo-media-manager__meta">
                                                    <span class="mj-todo-media-manager__title"><?php echo esc_html($title); ?></span>
                                                    <?php if ($url !== '') : ?>
                                                        <span class="mj-todo-media-manager__link"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ouvrir', 'mj-member'); ?></a></span>
                                                    <?php endif; ?>
                                                    <label class="mj-todo-media-manager__remove">
                                                        <input type="checkbox" name="remove_media_ids[]" value="<?php echo esc_attr((string) $attachmentId); ?>">
                                                        <?php esc_html_e('Détacher ce média', 'mj-member'); ?>
                                                    </label>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                    </ul>
                                    <div class="mj-todo-media-manager__actions">
                                        <button type="button" class="button" data-mj-todo-add-media><?php esc_html_e('Ajouter des médias', 'mj-member'); ?></button>
                                    </div>
                                </div>
                                <div class="mj-todo-media-manager__new" data-mj-todo-new-media></div>
                                <p class="description"><?php esc_html_e('Sélectionnez des fichiers dans la médiathèque pour les associer à cette tâche. Décochez un média pour le détacher.', 'mj-member'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <?php submit_button($isEdit ? __('Enregistrer la tâche', 'mj-member') : __('Créer la tâche', 'mj-member'), 'primary', 'submit', false); ?>
                    <a class="button" href="<?php echo esc_url($returnUrl); ?>"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
                </p>
            </form>
        </div>
    <?php endif; ?>
</div>
