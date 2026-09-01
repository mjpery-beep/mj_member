<?php
if (!defined('ABSPATH')) {
    exit;
}

$description_data = isset($description_view) && is_array($description_view)
    ? $description_view
    : (isset($description) && is_array($description) ? $description : array());

$description_html_content = isset($description_data['content_html'])
    ? (string) $description_data['content_html']
    : (isset($description_html) ? (string) $description_html : '');

$resource_url = isset($description_data['resource_url'])
    ? (string) $description_data['resource_url']
    : (isset($article_permalink) ? (string) $article_permalink : '');

$resource_label = isset($description_data['resource_label']) && $description_data['resource_label'] !== ''
    ? (string) $description_data['resource_label']
    : __('Dossier pédagogique', 'mj-member');

$resource_icon = isset($description_data['resource_icon']) && $description_data['resource_icon'] !== ''
    ? (string) $description_data['resource_icon']
    : '&#128193;';
?>
<article class="mj-member-event-single__card mj-member-event-single__description">
    <?php if ($description_html_content !== '') : ?>
    <?php echo wp_kses_post($description_html_content); ?>
    <?php else : ?>
    <p><?php echo esc_html__('Les informations detaillees seront bientot disponibles.', 'mj-member'); ?></p>
    <?php endif; ?>
    <?php if ($resource_url !== '') : ?>
    <p class="mj-member-event-single__description-resource">
        <a href="<?php echo esc_url($resource_url); ?>" target="_blank" rel="noopener">
            <span class="mj-member-event-single__description-resource-icon" aria-hidden="true"><?php echo wp_kses_post($resource_icon); ?></span>
            <span><?php echo esc_html($resource_label); ?></span>
        </a>
    </p>
    <?php endif; ?>
</article>
