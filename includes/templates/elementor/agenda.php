<?php
/**
 * Template du widget « Agenda unifié MJ ».
 *
 * Construit la configuration passée au bundle JS (Schedule-X + shell Preact).
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;
use Mj\Member\Classes\MjAgendaAcl;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Crud\MjMembers;

$settings = $this->get_settings_for_display();
$widget_id = 'mj-agenda-' . $this->get_id();

$elementor_plugin = \Elementor\Plugin::$instance;
$editor_is_edit = isset($elementor_plugin->editor) && method_exists($elementor_plugin->editor, 'is_edit_mode')
    ? (bool) $elementor_plugin->editor->is_edit_mode()
    : false;
$preview_is_active = isset($elementor_plugin->preview) && method_exists($elementor_plugin->preview, 'is_preview_mode')
    ? (bool) $elementor_plugin->preview->is_preview_mode()
    : false;
$is_preview = $editor_is_edit || $preview_is_active || !empty($settings['__is_preview_mode']);

AssetsManager::requirePackage('agenda');

$sw = static function ($key, $default = false) use ($settings) {
    if (!array_key_exists($key, $settings)) {
        return $default;
    }
    return $settings[$key] === 'yes';
};

$title = !empty($settings['title']) ? sanitize_text_field($settings['title']) : __('Agenda', 'mj-member');
$intro = !empty($settings['intro']) ? sanitize_textarea_field($settings['intro']) : '';

$all_layers = MjAgendaAcl::LAYERS;
$layers = isset($settings['data_layers']) && is_array($settings['data_layers'])
    ? array_values(array_intersect($all_layers, array_map('sanitize_key', $settings['data_layers'])))
    : $all_layers;
if (empty($layers)) {
    $layers = $all_layers;
}

$all_views = array('month-grid', 'week', 'day', 'month-agenda', 'list');
$views = isset($settings['available_views']) && is_array($settings['available_views'])
    ? array_values(array_intersect($all_views, array_map('sanitize_key', $settings['available_views'])))
    : array('month-grid', 'week', 'day', 'list');
if (empty($views)) {
    $views = array('month-grid');
}

$default_view = !empty($settings['default_view']) && in_array($settings['default_view'], $views, true)
    ? $settings['default_view']
    : $views[0];
$default_view_mobile = !empty($settings['default_view_mobile']) && in_array($settings['default_view_mobile'], $all_views, true)
    ? $settings['default_view_mobile']
    : 'month-agenda';

$colors = array(
    'event_occurrences' => !empty($settings['color_event']) ? $settings['color_event'] : '#6366f1',
    'worked_hours' => !empty($settings['color_worked_hours']) ? $settings['color_worked_hours'] : '#0ea5e9',
    'todos' => !empty($settings['color_todo']) ? $settings['color_todo'] : '#8b5cf6',
    'leave_requests' => !empty($settings['color_leave']) ? $settings['color_leave'] : '#22c55e',
    'work_schedules' => !empty($settings['color_work_schedule']) ? $settings['color_work_schedule'] : '#94a3b8',
    'requests' => !empty($settings['color_request']) ? $settings['color_request'] : '#f97316',
    'internal_notes' => !empty($settings['color_internal_note']) ? $settings['color_internal_note'] : '#eab308',
    'closure' => !empty($settings['color_closure']) ? $settings['color_closure'] : '#ef4444',
);

$event_statuses = isset($settings['event_statuses']) && is_array($settings['event_statuses'])
    ? array_values(array_filter(array_map('sanitize_key', $settings['event_statuses'])))
    : array('actif');
if (empty($event_statuses)) {
    $event_statuses = array('actif');
}
$event_types = isset($settings['event_types']) && is_array($settings['event_types'])
    ? array_values(array_filter(array_map('sanitize_key', $settings['event_types'])))
    : array();

$user_id = get_current_user_id();
$member_id = 0;
$role = MjRoles::JEUNE;
$member_name = '';
if (!$is_preview && $user_id && class_exists(MjMembers::class)) {
    $member = MjMembers::getByWpUserId($user_id);
    if ($member) {
        $arr = method_exists($member, 'toArray') ? $member->toArray() : (array) $member;
        $member_id = isset($arr['id']) ? (int) $arr['id'] : 0;
        $role = MjAgendaAcl::normalizeRole($arr['role'] ?? '');
        $member_name = trim(($arr['first_name'] ?? '') . ' ' . ($arr['last_name'] ?? ''));
    }
}

$fixed_height = 0;
if (($settings['calendar_height'] ?? 'auto') === 'fixed') {
    $fixed_height = isset($settings['fixed_height']['size']) ? (int) $settings['fixed_height']['size'] : 720;
}

$config = array(
    'widgetId' => $widget_id,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('mj-member-agenda'),
    'isPreview' => $is_preview,
    'title' => $title,
    'intro' => $intro,
    'layers' => $layers,
    'views' => $views,
    'defaultView' => $default_view,
    'defaultViewMobile' => $default_view_mobile,
    'colors' => $colors,
    'eventStatuses' => $event_statuses,
    'eventTypes' => $event_types,
    'range' => array(
        'monthsBefore' => (int) ($settings['range_months_before'] ?? 1),
        'monthsAfter' => (int) ($settings['range_months_after'] ?? 3),
    ),
    'memberScope' => in_array(($settings['member_scope'] ?? 'team'), array('self', 'team', 'all'), true)
        ? $settings['member_scope']
        : 'team',
    'grid' => array(
        'firstDay' => ($settings['first_day_of_week'] ?? 'monday') === 'sunday' ? 0 : 1,
        'dayStart' => sprintf('%02d:00', (int) ($settings['day_start_hour'] ?? 7)),
        'dayEnd' => sprintf('%02d:00', (int) ($settings['day_end_hour'] ?? 23)),
        'weekends' => $sw('show_weekends', true),
        'nowIndicator' => $sw('show_current_time_indicator', true),
    ),
    'interactions' => array(
        'dnd' => $sw('enable_drag_drop', true),
        'resize' => $sw('enable_resize', true),
        'clickCreate' => $sw('enable_click_create', true),
        'confirmMove' => $sw('confirm_before_move', true),
    ),
    'toolbar' => array(
        'show' => $sw('show_toolbar', true),
        'viewSwitcher' => $sw('show_view_switcher', true),
        'todayButton' => $sw('show_today_button', true),
        'filters' => $sw('show_filters', true),
        'legend' => $sw('show_legend', true),
        'addButton' => $sw('show_add_button', true),
        'sticky' => $sw('sticky_toolbar', false),
    ),
    'highlightClosures' => $sw('highlight_closures', true),
    'height' => array(
        'mode' => ($settings['calendar_height'] ?? 'auto') === 'fixed' ? 'fixed' : 'auto',
        'fixed' => $fixed_height,
    ),
    'acl' => MjAgendaAcl::snapshotForUser($user_id),
    'currentMember' => array('id' => $member_id, 'role' => $role, 'name' => $member_name),
    'locale' => function_exists('determine_locale') ? determine_locale() : get_locale(),
);

$config_json = wp_json_encode($config);
?>
<div id="<?php echo esc_attr($widget_id); ?>"
     class="mj-agenda mj-agenda--booting"
     data-mj-agenda
     data-config="<?php echo esc_attr($config_json); ?>">
    <?php if ($title !== '') : ?>
        <h2 class="mj-agenda__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($intro !== '') : ?>
        <p class="mj-agenda__intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>
    <div class="mj-agenda__boot" data-boot>
        <span class="mj-agenda__spinner" aria-hidden="true"></span>
        <span><?php esc_html_e('Chargement de l\'agenda…', 'mj-member'); ?></span>
    </div>
    <div class="mj-agenda__root" data-agenda-root hidden></div>
</div>
<?php if ($is_preview) : ?>
    <div class="mj-agenda__preview-hint">
        <?php esc_html_e('Le calendrier interactif s\'affiche sur la page publiée.', 'mj-member'); ?>
    </div>
<?php endif; ?>
