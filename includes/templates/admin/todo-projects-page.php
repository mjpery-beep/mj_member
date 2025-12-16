<?php

use Mj\Member\Admin\Page\TodoProjectsPage;
use Mj\Member\Classes\Table\MjTodoProjects_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

$mode = isset($view['mode']) ? (string) $view['mode'] : 'list';
$notice = isset($view['notice']) && is_array($view['notice']) ? $view['notice'] : array();
$project = isset($view['project']) && is_array($view['project']) ? $view['project'] : array();
$table = isset($view['table']) && $view['table'] instanceof MjTodoProjects_List_Table ? $view['table'] : null;

$pageSlug = TodoProjectsPage::slug();
$pageUrl = add_query_arg('page', $pageSlug, admin_url('admin.php'));
$actionUrl = admin_url('admin-post.php');

?>
<div class="wrap mj-member-projects-admin">
    <h1 class="wp-heading-inline"><?php esc_html_e('Gestion des projets', 'mj-member'); ?></h1>

    <?php if (!empty($notice) && !empty($notice['message'])) : ?>
        <?php $noticeClass = $notice['type'] === 'error' ? 'notice-error' : 'notice-success'; ?>
        <div class="notice <?php echo esc_attr($noticeClass); ?> is-dismissible">
            <p><?php echo esc_html((string) $notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'list' && $table instanceof MjTodoProjects_List_Table) : ?>
        <?php $table->process_bulk_action(); ?>
        <?php $table->prepare_items(); ?>

        <div class="mj-member-list-actions" style="margin:16px 0;">
            <a href="<?php echo esc_url(add_query_arg(array('page' => $pageSlug, 'action' => 'add'), admin_url('admin.php'))); ?>" class="button button-primary">
                <?php esc_html_e('➕ Ajouter un projet', 'mj-member'); ?>
            </a>
        </div>

        <form method="post">
            <input type="hidden" name="page" value="<?php echo esc_attr($pageSlug); ?>">
            <?php $table->search_box(__('Rechercher un projet', 'mj-member'), 'mj-member-projects'); ?>
            <?php $table->display(); ?>
        </form>

    <?php else : ?>
        <?php
        $projectId = isset($project['id']) ? (int) $project['id'] : 0;
        $isEdit = ($mode === 'edit' && $projectId > 0);
        $formTitle = $isEdit ? __('Modifier le projet', 'mj-member') : __('Créer un projet', 'mj-member');
        $formAction = $isEdit ? 'mj_member_todo_project_update' : 'mj_member_todo_project_create';
        $title = isset($project['title']) ? (string) $project['title'] : '';
        $slug = isset($project['slug']) ? (string) $project['slug'] : '';
        $description = isset($project['description']) ? (string) $project['description'] : '';
        $color = isset($project['color']) ? (string) $project['color'] : '';
        ?>

        <div class="mj-member-project-form" style="margin-top:24px;">
            <h2><?php echo esc_html($formTitle); ?></h2>
            <form method="post" action="<?php echo esc_url($actionUrl); ?>" class="mj-member-project-form__form">
                <input type="hidden" name="action" value="<?php echo esc_attr($formAction); ?>">
                <input type="hidden" name="redirect_page" value="<?php echo esc_attr($pageSlug); ?>">
                <?php if ($isEdit) : ?>
                    <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $projectId); ?>">
                <?php endif; ?>
                <?php wp_nonce_field($formAction); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="mj-member-project-title"><?php esc_html_e('Titre', 'mj-member'); ?></label></th>
                            <td><input type="text" name="title" id="mj-member-project-title" class="regular-text" value="<?php echo esc_attr($title); ?>" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-project-slug"><?php esc_html_e('Identifiant (slug)', 'mj-member'); ?></label></th>
                            <td>
                                <input type="text" name="slug" id="mj-member-project-slug" class="regular-text" value="<?php echo esc_attr($slug); ?>">
                                <p class="description"><?php esc_html_e('Laissez vide pour générer automatiquement un identifiant unique.', 'mj-member'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-project-description"><?php esc_html_e('Description', 'mj-member'); ?></label></th>
                            <td><textarea name="description" id="mj-member-project-description" class="large-text" rows="5"><?php echo esc_textarea($description); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mj-member-project-color"><?php esc_html_e('Couleur', 'mj-member'); ?></label></th>
                            <td>
                                <input type="color" name="color" id="mj-member-project-color" value="<?php echo esc_attr($color !== '' ? $color : '#2563eb'); ?>">
                                <p class="description"><?php esc_html_e('Optionnel. Utilisé pour repérer le projet visuellement dans les interfaces.', 'mj-member'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <?php submit_button($isEdit ? __('Enregistrer le projet', 'mj-member') : __('Créer le projet', 'mj-member'), 'primary', 'submit', false); ?>
                    <a class="button" href="<?php echo esc_url($pageUrl); ?>"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
                </p>
            </form>
        </div>
    <?php endif; ?>
</div>
