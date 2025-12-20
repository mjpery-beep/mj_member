<?php

use Mj\Member\Classes\Crud\MjIdeas;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('idea-box');

$title = isset($title) ? (string) $title : '';
$intro = isset($intro) ? (string) $intro : '';
$allowSubmission = isset($allowSubmission) ? (bool) $allowSubmission : true;

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();

$sampleIdeas = array();
if ($isPreview) {
    $sampleIdeas = array(
        array(
            'id' => 1,
            'title' => __('Atelier podcast mensuel', 'mj-member'),
            'content' => __('Organisons un atelier régulier pour découvrir la radio et le montage audio.', 'mj-member'),
            'status' => MjIdeas::STATUS_PUBLISHED,
            'voteCount' => 8,
            'createdAt' => wp_date('Y-m-d H:i:s', current_time('timestamp') - (2 * DAY_IN_SECONDS)),
            'updatedAt' => wp_date('Y-m-d H:i:s', current_time('timestamp') - (2 * DAY_IN_SECONDS)),
            'author' => array(
                'id' => 101,
                'name' => __('Camille D.', 'mj-member'),
                'role' => MjRoles::BENEVOLE,
            ),
            'viewerHasVoted' => true,
            'isOwner' => false,
            'canDelete' => true,
        ),
        array(
            'id' => 2,
            'title' => __('Tournoi de jeux coopératifs', 'mj-member'),
            'content' => __('Proposons une soirée avec des jeux coopératifs pour créer du lien entre les équipes.', 'mj-member'),
            'status' => MjIdeas::STATUS_PUBLISHED,
            'voteCount' => 5,
            'createdAt' => wp_date('Y-m-d H:i:s', current_time('timestamp') - (5 * DAY_IN_SECONDS)),
            'updatedAt' => wp_date('Y-m-d H:i:s', current_time('timestamp') - (5 * DAY_IN_SECONDS)),
            'author' => array(
                'id' => 102,
                'name' => __('Léo P.', 'mj-member'),
                'role' => MjRoles::ANIMATEUR,
            ),
            'viewerHasVoted' => false,
            'isOwner' => false,
            'canDelete' => true,
        ),
        array(
            'id' => 3,
            'title' => __('Bibliothèque partagée', 'mj-member'),
            'content' => __('Mettons en place une bibliothèque de jeux et de livres à emprunter au local.', 'mj-member'),
            'status' => MjIdeas::STATUS_PUBLISHED,
            'voteCount' => 2,
            'createdAt' => wp_date('Y-m-d H:i:s', current_time('timestamp') - (9 * DAY_IN_SECONDS)),
            'updatedAt' => wp_date('Y-m-d H:i:s', current_time('timestamp') - (9 * DAY_IN_SECONDS)),
            'author' => array(
                'id' => 103,
                'name' => __('Nina S.', 'mj-member'),
                'role' => MjRoles::JEUNE,
            ),
            'viewerHasVoted' => false,
            'isOwner' => false,
            'canDelete' => true,
        ),
    );
}

$config = array(
    'title' => $title,
    'intro' => $intro,
    'allowSubmission' => $allowSubmission,
    'preview' => $isPreview,
    'previewData' => $isPreview ? array(
        'ideas' => $sampleIdeas,
        'member' => array(
            'id' => 999,
            'name' => __('Alexis R.', 'mj-member'),
            'role' => MjRoles::ANIMATEUR,
        ),
    ) : array(),
);

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<div class="mj-idea-box" data-mj-member-idea-box data-config="<?php echo esc_attr($configJson); ?>">
    <noscript>
        <p class="mj-idea-box__notice"><?php esc_html_e('Activez JavaScript pour consulter et partager des idées.', 'mj-member'); ?></p>
    </noscript>
</div>
