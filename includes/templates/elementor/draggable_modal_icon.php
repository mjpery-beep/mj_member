<?php

if (!defined('ABSPATH')) {
    exit;
}

$widget_id = isset($widget) && is_object($widget) && method_exists($widget, 'get_id')
    ? $widget->get_id()
    : wp_generate_uuid4();

$dialog_id = 'mj-dmiw-dialog-' . sanitize_html_class((string) $widget_id);
$title_id = 'mj-dmiw-title-' . sanitize_html_class((string) $widget_id);

$aria_label = esc_attr__('Ouvrir la modal', 'mj-member');
$close_label = esc_attr__('Fermer', 'mj-member');
$children = isset($children) && is_array($children) ? $children : array();
$modal_shortcode_html = isset($modal_shortcode_html) && is_string($modal_shortcode_html) ? $modal_shortcode_html : '';
$modal_fit_content = !empty($modal_fit_content);
$modal_resizable = !empty($modal_resizable);
$close_button_type = isset($close_button_type) ? (string) $close_button_type : 'icon';
?>
<div
    class="mj-dmiw"
    data-mj-draggable-modal-widget="1"
    data-widget-id="<?php echo esc_attr((string) $widget_id); ?>"
    data-overlay-close="<?php echo $close_on_overlay ? '1' : '0'; ?>"
    data-modal-fit-content="<?php echo $modal_fit_content ? '1' : '0'; ?>"
    data-modal-resizable="<?php echo $modal_resizable ? '1' : '0'; ?>"
    data-close-button-type="<?php echo esc_attr($close_button_type); ?>"
>
    <button type="button" class="mj-dmiw__icon-button" aria-label="<?php echo $aria_label; ?>">
        <span class="mj-dmiw__icon" aria-hidden="true">
            <?php if ($icon_url !== '') : ?>
                <img src="<?php echo esc_url($icon_url); ?>" alt="" loading="lazy" />
            <?php else : ?>
                <svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm4.65 12.35a1 1 0 0 1-1.41 1.41L11 11.52V7a1 1 0 0 1 2 0v3.69Z"></path>
                </svg>
            <?php endif; ?>
        </span>
    </button>

    <div class="mj-dmiw__overlay" hidden></div>

    <section
        id="<?php echo esc_attr($dialog_id); ?>"
        class="mj-dmiw__modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="<?php echo esc_attr($title_id); ?>"
        hidden
    >
        <header class="mj-dmiw__modal-header" data-modal-drag-handle="1">
            <div class="mj-dmiw__modal-title-group">
                <?php if (!empty($show_icon_in_header) && $icon_url !== '') : ?>
                    <span class="mj-dmiw__modal-header-icon"><img src="<?php echo esc_url($icon_url); ?>" alt="" loading="lazy" /></span>
                <?php elseif (!empty($show_icon_in_header)) : ?>
                    <span class="mj-dmiw__modal-header-icon">
                        <svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
                            <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm4.65 12.35a1 1 0 0 1-1.41 1.41L11 11.52V7a1 1 0 0 1 2 0v3.69Z"></path>
                        </svg>
                    </span>
                <?php endif; ?>
                <h3 id="<?php echo esc_attr($title_id); ?>" class="mj-dmiw__modal-title"><?php echo esc_html($modal_title); ?></h3>
            </div>
            <?php if ($close_button_type === 'text') : ?>
                <button type="button" class="mj-dmiw__close" aria-label="<?php echo $close_label; ?>"><?php echo esc_html__('Fermer', 'mj-member'); ?></button>
            <?php else : ?>
                <button type="button" class="mj-dmiw__close" aria-label="<?php echo $close_label; ?>">&times;</button>
            <?php endif; ?>
        </header>
        <div class="mj-dmiw__modal-content">
            <?php
            if ($modal_shortcode_html !== '') {
                echo $modal_shortcode_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } elseif (!empty($children) && is_object($widget) && method_exists($widget, 'print_child')) {
                foreach (array_values($children) as $index => $child) {
                    if (!is_object($child)) {
                        continue;
                    }

                    $widget->print_child((int) $index);
                }
            } elseif (!empty($is_preview)) {
                echo '<div class="mj-dmiw__placeholder">' . esc_html__('Ajoutez un Container a l\'interieur du widget pour afficher son contenu dans la modal.', 'mj-member') . '</div>';
            }
            ?>
        </div>
    </section>
</div>
