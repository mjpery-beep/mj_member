<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('dock-tabs');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();
$tabs = isset($template_data['tabs']) && is_array($template_data['tabs']) ? $template_data['tabs'] : array();

if (empty($tabs)) {
    return;
}

$dock_position = isset($template_data['dock_position']) ? sanitize_key((string) $template_data['dock_position']) : 'bottom';
if (!in_array($dock_position, array('top', 'right', 'bottom', 'left'), true)) {
    $dock_position = 'bottom';
}

$default_tab_index = isset($template_data['default_tab_index']) ? (int) $template_data['default_tab_index'] : 1;
if ($default_tab_index < 1) {
    $default_tab_index = 1;
}
$default_tab_index = min($default_tab_index, count($tabs));

$widget_id = isset($template_data['widget_id']) ? sanitize_html_class((string) $template_data['widget_id']) : '';
$root_id = 'mj-dock-tabs-' . ($widget_id !== '' ? $widget_id : wp_generate_password(6, false, false));

$root_classes = array(
    'mj-dock-tabs',
    'mj-dock-tabs--' . $dock_position,
);

?>
<div
    id="<?php echo esc_attr($root_id); ?>"
    class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $root_classes))); ?>"
    data-mj-dock-tabs="1"
    data-default-index="<?php echo esc_attr((string) $default_tab_index); ?>"
    data-dock-position="<?php echo esc_attr($dock_position); ?>"
>
    <div class="mj-dock-tabs__panels">
        <?php foreach ($tabs as $index => $tab) :
            $tab_number = $index + 1;
            $is_active = $tab_number === $default_tab_index;
            $tab_key = isset($tab['id']) ? sanitize_html_class((string) $tab['id']) : ('tab-' . $tab_number);
            $tab_button_id = $root_id . '-btn-' . $tab_key;
            $tab_panel_id = $root_id . '-panel-' . $tab_key;
            ?>
            <section
                id="<?php echo esc_attr($tab_panel_id); ?>"
                class="mj-dock-tabs__panel<?php echo $is_active ? ' is-active' : ''; ?>"
                data-tab-panel="<?php echo esc_attr((string) $tab_number); ?>"
                role="tabpanel"
                aria-labelledby="<?php echo esc_attr($tab_button_id); ?>"
                <?php echo $is_active ? '' : ' hidden="hidden"'; ?>
            >
                <div class="mj-dock-tabs__panel-content">
                    <?php echo wp_kses_post(isset($tab['content']) ? (string) $tab['content'] : ''); ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="mj-dock-tabs__dock" role="tablist" aria-label="<?php echo esc_attr__('Navigation dock', 'mj-member'); ?>">
        <?php foreach ($tabs as $index => $tab) :
            $tab_number = $index + 1;
            $is_active = $tab_number === $default_tab_index;
            $tab_key = isset($tab['id']) ? sanitize_html_class((string) $tab['id']) : ('tab-' . $tab_number);
            $tab_button_id = $root_id . '-btn-' . $tab_key;
            $tab_panel_id = $root_id . '-panel-' . $tab_key;
            $title = isset($tab['title']) ? (string) $tab['title'] : sprintf(__('Onglet %d', 'mj-member'), $tab_number);
            $icon_url = isset($tab['icon_url']) ? (string) $tab['icon_url'] : '';
            $icon_alt = isset($tab['icon_alt']) ? (string) $tab['icon_alt'] : $title;
            ?>
            <button
                type="button"
                id="<?php echo esc_attr($tab_button_id); ?>"
                class="mj-dock-tabs__tab<?php echo $is_active ? ' is-active' : ''; ?>"
                data-tab-target="<?php echo esc_attr((string) $tab_number); ?>"
                role="tab"
                aria-controls="<?php echo esc_attr($tab_panel_id); ?>"
                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
            >
                <span class="mj-dock-tabs__icon" aria-hidden="true">
                    <?php if ($icon_url !== '') : ?>
                        <img src="<?php echo esc_url($icon_url); ?>" alt="<?php echo esc_attr($icon_alt); ?>" loading="lazy" />
                    <?php else : ?>
                        <span class="mj-dock-tabs__icon-fallback"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($title, 0, 1, 'UTF-8') : substr($title, 0, 1)); ?></span>
                    <?php endif; ?>
                </span>
                <span class="mj-dock-tabs__label"><?php echo esc_html($title); ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>
