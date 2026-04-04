<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('photo-import-timeline');

$title = isset($template_data['title']) ? (string) $template_data['title'] : __('Souvenirs en photos', 'mj-member');
$subtitle = isset($template_data['subtitle']) ? (string) $template_data['subtitle'] : '';
$emptyMessage = isset($template_data['empty_message']) ? (string) $template_data['empty_message'] : __('Aucune photo importée pour le moment.', 'mj-member');
$items = isset($template_data['items']) && is_array($template_data['items']) ? $template_data['items'] : array();

$componentId = 'mj-photo-timeline-' . wp_generate_uuid4();

$grouped = array();
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $ts = isset($item['taken_at_ts']) ? (int) $item['taken_at_ts'] : 0;
    if ($ts <= 0) {
        $ts = time();
    }
    $monthKey = wp_date('Y-m', $ts);
    if (!isset($grouped[$monthKey])) {
        $grouped[$monthKey] = array(
            'label' => wp_date('F Y', $ts),
            'items' => array(),
        );
    }
    $grouped[$monthKey]['items'][] = $item;
}
?>

<section class="mj-photo-timeline" id="<?php echo esc_attr($componentId); ?>" data-mj-photo-timeline>
    <header class="mj-photo-timeline__header">
        <?php if ($title !== '') : ?>
            <h2 class="mj-photo-timeline__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php if ($subtitle !== '') : ?>
            <p class="mj-photo-timeline__subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
    </header>

    <?php if (empty($grouped)) : ?>
        <div class="mj-photo-timeline__empty"><?php echo esc_html($emptyMessage); ?></div>
    <?php else : ?>
        <div class="mj-photo-timeline__months">
            <?php foreach ($grouped as $month) : ?>
                <section class="mj-photo-timeline__month">
                    <h3 class="mj-photo-timeline__month-title"><?php echo esc_html((string) $month['label']); ?></h3>
                    <div class="mj-photo-timeline__grid">
                        <?php foreach ($month['items'] as $item) :
                            $thumb = isset($item['thumb_url']) ? esc_url((string) $item['thumb_url']) : '';
                            $display = isset($item['display_url']) ? esc_url((string) $item['display_url']) : '';
                            $titleItem = isset($item['title']) ? (string) $item['title'] : (isset($item['source_name']) ? (string) $item['source_name'] : __('Photo importée', 'mj-member'));
                            $dateLabel = isset($item['taken_at_label']) ? (string) $item['taken_at_label'] : '';
                            if ($thumb === '' || $display === '') {
                                continue;
                            }
                            ?>
                            <button
                                type="button"
                                class="mj-photo-timeline__card"
                                data-mj-photo-open
                                data-full="<?php echo esc_url($display); ?>"
                                data-title="<?php echo esc_attr($titleItem); ?>"
                                data-date="<?php echo esc_attr($dateLabel); ?>"
                            >
                                <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($titleItem); ?>" loading="lazy" />
                                <span class="mj-photo-timeline__card-meta">
                                    <strong><?php echo esc_html($titleItem); ?></strong>
                                    <?php if ($dateLabel !== '') : ?>
                                        <small><?php echo esc_html($dateLabel); ?></small>
                                    <?php endif; ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mj-photo-timeline__modal" data-mj-photo-modal hidden>
        <div class="mj-photo-timeline__overlay" data-mj-photo-close></div>
        <div class="mj-photo-timeline__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Aperçu photo', 'mj-member'); ?>">
            <button type="button" class="mj-photo-timeline__close" data-mj-photo-close aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">×</button>
            <button type="button" class="mj-photo-timeline__nav mj-photo-timeline__nav--prev" data-mj-photo-prev aria-label="<?php esc_attr_e('Photo précédente', 'mj-member'); ?>">‹</button>
            <figure class="mj-photo-timeline__figure">
                <img src="" alt="" data-mj-photo-modal-image />
                <figcaption>
                    <strong data-mj-photo-modal-title></strong>
                    <small data-mj-photo-modal-date></small>
                </figcaption>
            </figure>
            <button type="button" class="mj-photo-timeline__nav mj-photo-timeline__nav--next" data-mj-photo-next aria-label="<?php esc_attr_e('Photo suivante', 'mj-member'); ?>">›</button>
        </div>
    </div>
</section>
