<?php

use Mj\Member\Classes\Crud\MjTodos;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('todo-widget');

$title = isset($title) ? (string) $title : '';
$intro = isset($intro) ? (string) $intro : '';
$showCompleted = isset($showCompleted) ? (bool) $showCompleted : true;

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$hasAccess = $isPreview;
if (!$hasAccess && function_exists('mj_member_todo_user_has_access')) {
    $hasAccess = mj_member_todo_user_has_access();
}

$sampleProjects = array();
$sampleTodos = array();

if ($isPreview) {
    $sampleProjects = array(
        array('id' => 1, 'title' => __('Événements', 'mj-member')),
        array('id' => 2, 'title' => __('Communication', 'mj-member')),
    );

    $now = current_time('timestamp');
    $sampleTodos = array(
        array(
            'id' => 101,
            'title' => __('Préparer la réunion de coordination', 'mj-member'),
            'description' => __('Lister les sujets prioritaires, vérifier les disponibilités des animateurs et préparer le plan B.', 'mj-member'),
            'status' => MjTodos::STATUS_OPEN,
            'projectId' => 1,
            'projectTitle' => __('Événements', 'mj-member'),
            'dueDate' => wp_date('Y-m-d', $now + (2 * DAY_IN_SECONDS)),
            'completedAt' => '',
            'emoji' => '🗓️',
            'media' => array(
                array(
                    'id' => 501,
                    'todo_id' => 101,
                    'attachment_id' => 1001,
                    'title' => __('Ordre du jour.pdf', 'mj-member'),
                    'filename' => 'ordre-du-jour.pdf',
                    'url' => '#',
                    'icon_url' => '',
                    'preview_url' => '',
                    'mime_type' => 'application/pdf',
                    'type' => 'application',
                    'created_at' => wp_date('Y-m-d H:i', $now - (3 * HOUR_IN_SECONDS)),
                    'member_id' => 1,
                ),
            ),
        ),
        array(
            'id' => 102,
            'title' => __('Mettre à jour la brochure bénévoles', 'mj-member'),
            'description' => __('Actualiser les horaires, ajouter les nouvelles consignes sécurité et relire avec l’équipe com.', 'mj-member'),
            'status' => MjTodos::STATUS_COMPLETED,
            'projectId' => 2,
            'projectTitle' => __('Communication', 'mj-member'),
            'dueDate' => wp_date('Y-m-d', $now - DAY_IN_SECONDS),
            'completedAt' => wp_date('Y-m-d H:i', $now - (6 * HOUR_IN_SECONDS)),
            'emoji' => '✅',
            'media' => array(),
        ),
        array(
            'id' => 103,
            'title' => __('Commander du matériel pour l’atelier DJ', 'mj-member'),
            'description' => __('Comparer les devis câbles/platines, valider le panier et prévoir le retrait.', 'mj-member'),
            'status' => MjTodos::STATUS_OPEN,
            'projectId' => 1,
            'projectTitle' => __('Événements', 'mj-member'),
            'dueDate' => '',
            'completedAt' => '',
            'emoji' => '🎧',
            'media' => array(
                array(
                    'id' => 502,
                    'todo_id' => 103,
                    'attachment_id' => 1002,
                    'title' => __('Liste matériel.png', 'mj-member'),
                    'filename' => 'liste-materiel.png',
                    'url' => '#',
                    'icon_url' => '',
                    'preview_url' => '',
                    'mime_type' => 'image/png',
                    'type' => 'image',
                    'created_at' => wp_date('Y-m-d H:i', $now - HOUR_IN_SECONDS),
                    'member_id' => 2,
                ),
            ),
        ),
    );
}

$introHtml = $intro !== '' ? wp_kses_post($intro) : '';

$config = array(
    'title' => $title,
    'intro' => $introHtml,
    'showCompleted' => $showCompleted,
    'hasAccess' => $hasAccess,
    'preview' => $isPreview,
    'previewData' => $isPreview ? array(
        'todos' => $sampleTodos,
        'projects' => $sampleProjects,
    ) : array(),
);

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<?php $fallbackNotice = (!$isPreview && !$hasAccess); ?>
<div class="mj-todo-widget" data-mj-member-todo-widget data-config="<?php echo esc_attr($configJson); ?>">
    <?php if ($fallbackNotice) : ?>
        <p class="mj-todo-widget__notice mj-todo-widget__notice--fallback"><?php esc_html_e('Vous n’avez pas accès à cette section.', 'mj-member'); ?></p>
    <?php endif; ?>
    <noscript>
        <p class="mj-todo-widget__notice"><?php esc_html_e('Ce module nécessite JavaScript pour afficher vos tâches.', 'mj-member'); ?></p>
    </noscript>
</div>
