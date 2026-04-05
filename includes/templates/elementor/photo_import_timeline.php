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
$initialLimit = isset($template_data['initial_limit']) ? max(1, (int) $template_data['initial_limit']) : count($items);
$totalLimit = isset($template_data['total_limit']) ? max($initialLimit, (int) $template_data['total_limit']) : $initialLimit;
$batchSize = isset($template_data['batch_size']) ? max(12, (int) $template_data['batch_size']) : 60;
$ajaxUrl = isset($template_data['ajax_url']) ? (string) $template_data['ajax_url'] : admin_url('admin-ajax.php');
$nonce = isset($template_data['nonce']) ? (string) $template_data['nonce'] : '';
$yearSummary = isset($template_data['year_summary']) && is_array($template_data['year_summary']) ? $template_data['year_summary'] : array();
$initialLoadedYear = isset($template_data['initial_loaded_year']) ? (int) $template_data['initial_loaded_year'] : 0;
$isPreview = !empty($template_data['is_preview']);

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

$yearCountsMap = array();
foreach ($yearSummary as $yearInfo) {
    if (!is_array($yearInfo)) {
        continue;
    }
    $year = isset($yearInfo['year']) ? (int) $yearInfo['year'] : 0;
    if ($year <= 0) {
        continue;
    }
    $yearCountsMap[$year] = isset($yearInfo['count']) ? max(0, (int) $yearInfo['count']) : 0;
}

if ($isPreview && empty($yearCountsMap) && !empty($items)) {
    $previewYear = (int) wp_date('Y', time());
    $yearCountsMap[$previewYear] = count($items);
    $initialLoadedYear = $previewYear;
}

$estimatedCardHeight = 230;
$estimatedCols = 4;
?>

<section
    class="mj-photo-timeline"
    id="<?php echo esc_attr($componentId); ?>"
    data-mj-photo-timeline
    data-ajax-url="<?php echo esc_url($ajaxUrl); ?>"
    data-nonce="<?php echo esc_attr($nonce); ?>"
    data-offset="<?php echo esc_attr((string) count($items)); ?>"
    data-total-limit="<?php echo esc_attr((string) $totalLimit); ?>"
    data-batch-size="<?php echo esc_attr((string) $batchSize); ?>"
    data-initial-year="<?php echo esc_attr((string) $initialLoadedYear); ?>"
    data-preview="<?php echo $isPreview ? '1' : '0'; ?>"
>
    <?php if ($title !== '' || $subtitle !== '') : ?>
        <div class="mj-photo-timeline__header">
            <?php if ($title !== '') : ?>
                <h2 class="mj-photo-timeline__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <?php if ($subtitle !== '') : ?>
                <p class="mj-photo-timeline__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($yearCountsMap) && empty($grouped)) : ?>
        <div class="mj-photo-timeline__empty"><?php echo esc_html($emptyMessage); ?></div>
    <?php else : ?>
        <div class="mj-photo-timeline__layout">
            <aside class="mj-photo-timeline__year-rail" aria-label="<?php esc_attr_e('Navigation par année', 'mj-member'); ?>">
                <ul class="mj-photo-timeline__year-list" data-mj-photo-year-list>
                    <?php foreach ($yearCountsMap as $year => $count) : ?>
                        <li class="mj-photo-timeline__year-item">
                            <button
                                type="button"
                                class="mj-photo-timeline__year-button<?php echo ((int) $year === $initialLoadedYear) ? ' is-active' : ''; ?>"
                                data-mj-photo-year-jump
                                data-year="<?php echo esc_attr((string) $year); ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Aller à %d', 'mj-member'), (int) $year)); ?>"
                            >
                                <span class="mj-photo-timeline__year-dot" aria-hidden="true"></span>
                                <span class="mj-photo-timeline__year-label"><?php echo esc_html((string) $year); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <div class="mj-photo-timeline__years" data-mj-photo-years>
                <?php foreach ($yearCountsMap as $year => $count) :
                    $estimatedRows = max(1, (int) ceil(((int) $count) / $estimatedCols));
                    $estimatedHeight = max(320, ($estimatedRows * $estimatedCardHeight));
                    $isLoadedYear = ((int) $year === $initialLoadedYear && !empty($grouped));
                    ?>
                    <section
                        class="mj-photo-timeline__year-section<?php echo $isLoadedYear ? ' is-loaded' : ''; ?>"
                        data-mj-photo-year-section
                        data-year="<?php echo esc_attr((string) $year); ?>"
                        data-year-count="<?php echo esc_attr((string) $count); ?>"
                        data-loaded="<?php echo $isLoadedYear ? '1' : '0'; ?>"
                    >
                        <header class="mj-photo-timeline__year-header">
                            <h3 class="mj-photo-timeline__year-title"><?php echo esc_html((string) $year); ?></h3>
                            <span class="mj-photo-timeline__year-count"><?php echo esc_html(sprintf(_n('%d photo', '%d photos', (int) $count, 'mj-member'), (int) $count)); ?></span>
                        </header>

                        <div class="mj-photo-timeline__months" data-mj-photo-year-months>
                            <?php if ($isLoadedYear) : ?>
                                <?php foreach ($grouped as $monthKey => $month) : ?>
                                    <section class="mj-photo-timeline__month" data-month-key="<?php echo esc_attr((string) $monthKey); ?>" data-month-label="<?php echo esc_attr((string) $month['label']); ?>">
                                        <h4 class="mj-photo-timeline__month-title"><?php echo esc_html((string) $month['label']); ?></h4>
                                        <ul class="mj-photo-timeline__grid">
                                            <?php foreach ($month['items'] as $item) :
                                                $thumb = isset($item['thumb_url']) ? esc_url((string) $item['thumb_url']) : '';
                                                $display = isset($item['display_url']) ? esc_url((string) $item['display_url']) : '';
                                                $titleItem = isset($item['title']) ? (string) $item['title'] : (isset($item['source_name']) ? (string) $item['source_name'] : __('Photo importée', 'mj-member'));
                                                $dateLabel = isset($item['taken_at_label']) ? (string) $item['taken_at_label'] : '';
                                                if ($thumb === '' || $display === '') {
                                                    continue;
                                                }
                                                ?>
                                                <li class="mj-photo-timeline__item">
                                                    <button
                                                        type="button"
                                                        class="mj-photo-timeline__card"
                                                        data-mj-photo-open
                                                        data-full="<?php echo esc_url($display); ?>"
                                                        data-title="<?php echo esc_attr($titleItem); ?>"
                                                        data-date="<?php echo esc_attr($dateLabel); ?>"
                                                    >
                                                        <span class="mj-photo-timeline__media">
                                                            <span class="mj-photo-timeline__image-loader" aria-hidden="true"></span>
                                                            <img
                                                                src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="
                                                                data-src="<?php echo esc_url($thumb); ?>"
                                                                alt="<?php echo esc_attr($titleItem); ?>"
                                                                loading="lazy"
                                                                decoding="async"
                                                                fetchpriority="low"
                                                                data-mj-photo-image
                                                            />
                                                        </span>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </section>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div
                            class="mj-photo-timeline__year-placeholder<?php echo $isLoadedYear ? ' is-hidden' : ''; ?>"
                            data-mj-photo-year-placeholder
                            style="height: <?php echo esc_attr((string) $estimatedHeight); ?>px;"
                            aria-hidden="true"
                        ></div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mj-photo-timeline__modal" data-mj-photo-modal hidden>
        <div class="mj-photo-timeline__overlay" data-mj-photo-close></div>
        <div class="mj-photo-timeline__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Aperçu photo', 'mj-member'); ?>">
            <button type="button" class="mj-photo-timeline__close" data-mj-photo-close aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">×</button>
            <button type="button" class="mj-photo-timeline__nav mj-photo-timeline__nav--prev" data-mj-photo-prev aria-label="<?php esc_attr_e('Photo précédente', 'mj-member'); ?>">‹</button>
            <figure class="mj-photo-timeline__figure">
                <img src="" alt="" data-mj-photo-modal-image />
            </figure>
            <button type="button" class="mj-photo-timeline__nav mj-photo-timeline__nav--next" data-mj-photo-next aria-label="<?php esc_attr_e('Photo suivante', 'mj-member'); ?>">›</button>
        </div>
    </div>
</section>
