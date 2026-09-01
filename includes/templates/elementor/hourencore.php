<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('hourencore');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();

$instance_id = isset($template_data['instance_id']) ? sanitize_html_class((string) $template_data['instance_id']) : wp_unique_id('mj-hourencore-');
$week = isset($template_data['week']) && is_array($template_data['week']) ? $template_data['week'] : array();
$days = isset($template_data['days']) && is_array($template_data['days']) ? $template_data['days'] : array();
$entries = isset($template_data['entries']) && is_array($template_data['entries']) ? $template_data['entries'] : array();
$tasks = isset($template_data['tasks']) && is_array($template_data['tasks']) ? $template_data['tasks'] : array();
$projects = isset($template_data['projects']) && is_array($template_data['projects']) ? $template_data['projects'] : array();
$ajax = isset($template_data['ajax']) && is_array($template_data['ajax']) ? $template_data['ajax'] : array();

if (empty($week)) {
    $monday = new DateTimeImmutable('monday this week');
    $sunday = $monday->modify('+6 days');
    $week = array(
        'start' => $monday->format('Y-m-d'),
        'end' => $sunday->format('Y-m-d'),
        'label' => sprintf(__('Semaine du %s au %s', 'mj-member'), date_i18n('d/m', $monday->getTimestamp()), date_i18n('d/m', $sunday->getTimestamp())),
    );
}

if (empty($days)) {
    $days = array();
    $cursor = DateTimeImmutable::createFromFormat('Y-m-d', (string) $week['start']);
    if ($cursor instanceof DateTimeImmutable) {
        for ($i = 0; $i < 7; $i++) {
            $current = $cursor->modify('+' . $i . ' days');
            $days[] = array(
                'date' => $current->format('Y-m-d'),
                'label' => date_i18n('l d/m', $current->getTimestamp()),
            );
        }
    }
}

if (empty($entries) && function_exists('is_elementor_preview') && is_elementor_preview()) {
    $entries = array(
        array(
            'id' => wp_unique_id('slot-'),
            'date' => $week['start'],
            'start' => '09:00',
            'end' => '12:00',
            'task' => __('Accueil jeunes', 'mj-member'),
            'project' => __('Maison de jeunes', 'mj-member'),
        ),
        array(
            'id' => wp_unique_id('slot-'),
            'date' => DateTimeImmutable::createFromFormat('Y-m-d', $week['start'])->modify('+2 days')->format('Y-m-d'),
            'start' => '14:00',
            'end' => '18:00',
            'task' => __('Préparation atelier', 'mj-member'),
            'project' => __('Atelier théâtre', 'mj-member'),
        ),
    );
}

if (empty($tasks)) {
    $tasks = array(
        __('Accueil jeunes', 'mj-member'),
        __('Réunion d\'équipe', 'mj-member'),
        __('Préparation atelier', 'mj-member'),
        __('Animation événement', 'mj-member'),
    );
}

if (empty($projects)) {
    $projects = array(
        array('id' => 'core', 'label' => __('Maison de jeunes', 'mj-member')),
        array('id' => 'atelier-theatre', 'label' => __('Atelier théâtre', 'mj-member')),
        array('id' => 'stage-ete', 'label' => __('Stage été', 'mj-member')),
    );
}

if (empty($ajax)) {
    $ajax = array(
        'endpoint' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mj-member-hourencore'),
        'actions' => array(
            'save' => 'mj_member_hourencore_save',
            'delete' => 'mj_member_hourencore_delete',
            'project_create' => 'mj_member_hourencore_project_create',
        ),
    );
}

$total_minutes = 0;
foreach ($entries as $entry) {
    $start = isset($entry['start']) ? (string) $entry['start'] : '';
    $end = isset($entry['end']) ? (string) $entry['end'] : '';
    if ($start === '' || $end === '') {
        continue;
    }
    $start_date = DateTimeImmutable::createFromFormat('H:i', $start);
    $end_date = DateTimeImmutable::createFromFormat('H:i', $end);
    if (!$start_date || !$end_date) {
        continue;
    }
    $diff = $end_date->getTimestamp() - $start_date->getTimestamp();
    if ($diff > 0) {
        $total_minutes += (int) round($diff / 60);
    }
}

$total_hours = floor($total_minutes / 60);
$total_remaining_minutes = $total_minutes % 60;
$total_label = sprintf(_n('%d heure', '%d heures', $total_hours, 'mj-member'), $total_hours);
if ($total_remaining_minutes > 0) {
    $total_label .= ' ' . sprintf(__('%d min', 'mj-member'), $total_remaining_minutes);
}

$config = array(
    'instanceId' => $instance_id,
    'week' => $week,
    'days' => $days,
    'entries' => $entries,
    'tasks' => array_values($tasks),
    'projects' => $projects,
    'ajax' => $ajax,
    'i18n' => array(
        'addSlot' => __('Ajouter une plage horaire', 'mj-member'),
        'editSlot' => __('Modifier la plage horaire', 'mj-member'),
        'deleteSlot' => __('Supprimer', 'mj-member'),
        'save' => __('Enregistrer', 'mj-member'),
        'cancel' => __('Annuler', 'mj-member'),
        'newProjectPlaceholder' => __('Nom du projet…', 'mj-member'),
        'weekTotal' => __('Total semaine', 'mj-member'),
    ),
);

$config_attribute = esc_attr(wp_json_encode($config));

$root_classes = array('mj-hourencore');
$root_attributes = array(
    'class="' . esc_attr(implode(' ', $root_classes)) . '"',
    'data-mj-hourencore="' . esc_attr($instance_id) . '"',
    'data-config="' . $config_attribute . '"',
);
?>

<div <?php echo implode(' ', $root_attributes); ?>>
    <header class="mj-hourencore__header">
        <div class="mj-hourencore__period">
            <button type="button" class="mj-hourencore__nav mj-hourencore__nav--prev" data-direction="prev" aria-label="<?php esc_attr_e('Semaine précédente', 'mj-member'); ?>">
                <span aria-hidden="true">&#x2039;</span>
            </button>
            <div class="mj-hourencore__period-label">
                <?php echo esc_html(isset($week['label']) ? $week['label'] : ''); ?>
            </div>
            <button type="button" class="mj-hourencore__nav mj-hourencore__nav--next" data-direction="next" aria-label="<?php esc_attr_e('Semaine suivante', 'mj-member'); ?>">
                <span aria-hidden="true">&#x203A;</span>
            </button>
        </div>
        <div class="mj-hourencore__totals" aria-live="polite">
            <span class="mj-hourencore__totals-label"><?php esc_html_e('Total semaine', 'mj-member'); ?> :</span>
            <span class="mj-hourencore__totals-value" data-total-display><?php echo esc_html($total_label); ?></span>
        </div>
    </header>

    <div class="mj-hourencore__content">
        <div class="mj-hourencore__grid" role="grid">
            <?php foreach ($days as $day) :
                $day_date = isset($day['date']) ? (string) $day['date'] : '';
                $day_label = isset($day['label']) ? (string) $day['label'] : $day_date;
                $day_slots = array();
                foreach ($entries as $entry) {
                    if (isset($entry['date']) && $entry['date'] === $day_date) {
                        $day_slots[] = $entry;
                    }
                }
                ?>
                <div class="mj-hourencore__day" role="gridcell" data-date="<?php echo esc_attr($day_date); ?>">
                    <header class="mj-hourencore__day-header">
                        <h3 class="mj-hourencore__day-title"><?php echo esc_html($day_label); ?></h3>
                        <button type="button" class="mj-hourencore__add-slot" data-action="add-slot">
                            <span aria-hidden="true">+</span>
                            <span class="screen-reader-text"><?php esc_html_e('Ajouter une plage horaire', 'mj-member'); ?></span>
                        </button>
                    </header>
                    <div class="mj-hourencore__timeline" data-dropzone>
                        <?php if (!empty($day_slots)) :
                            foreach ($day_slots as $slot) :
                                $slot_id = isset($slot['id']) ? (string) $slot['id'] : uniqid('slot-');
                                $slot_start = isset($slot['start']) ? (string) $slot['start'] : '';
                                $slot_end = isset($slot['end']) ? (string) $slot['end'] : '';
                                $slot_task = isset($slot['task']) ? (string) $slot['task'] : '';
                                $slot_project = isset($slot['project']) ? (string) $slot['project'] : '';
                                ?>
                                <article class="mj-hourencore__slot" data-slot-id="<?php echo esc_attr($slot_id); ?>" data-start="<?php echo esc_attr($slot_start); ?>" data-end="<?php echo esc_attr($slot_end); ?>">
                                    <header class="mj-hourencore__slot-header">
                                        <span class="mj-hourencore__slot-time"><?php echo esc_html($slot_start . ' – ' . $slot_end); ?></span>
                                        <?php if ($slot_project !== '') : ?>
                                            <span class="mj-hourencore__slot-project"><?php echo esc_html($slot_project); ?></span>
                                        <?php endif; ?>
                                    </header>
                                    <div class="mj-hourencore__slot-body">
                                        <p class="mj-hourencore__slot-task"><?php echo esc_html($slot_task); ?></p>
                                        <button type="button" class="mj-hourencore__slot-edit" data-action="edit-slot"><?php esc_html_e('Modifier', 'mj-member'); ?></button>
                                    </div>
                                </article>
                            <?php endforeach;
                        endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="mj-hourencore__sidebar">
            <section class="mj-hourencore__section">
                <h4 class="mj-hourencore__section-title"><?php esc_html_e('Tâches fréquentes', 'mj-member'); ?></h4>
                <div class="mj-hourencore__chips" data-task-suggestions>
                    <?php foreach ($tasks as $task_label) : ?>
                        <button type="button" class="mj-hourencore__chip" data-task-label="<?php echo esc_attr($task_label); ?>"><?php echo esc_html($task_label); ?></button>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mj-hourencore__section">
                <h4 class="mj-hourencore__section-title"><?php esc_html_e('Projets', 'mj-member'); ?></h4>
                <div class="mj-hourencore__chips" data-project-suggestions>
                    <?php foreach ($projects as $project) :
                        $project_id = isset($project['id']) ? (string) $project['id'] : '';
                        $project_label = isset($project['label']) ? (string) $project['label'] : '';
                        ?>
                        <button type="button" class="mj-hourencore__chip" data-project-id="<?php echo esc_attr($project_id); ?>" data-project-label="<?php echo esc_attr($project_label); ?>"><?php echo esc_html($project_label); ?></button>
                    <?php endforeach; ?>
                </div>
                <form class="mj-hourencore__project-form" data-project-form>
                    <label class="screen-reader-text" for="<?php echo esc_attr($instance_id); ?>-project-input"><?php esc_html_e('Ajouter un projet', 'mj-member'); ?></label>
                    <input type="text" id="<?php echo esc_attr($instance_id); ?>-project-input" class="mj-hourencore__project-input" placeholder="<?php esc_attr_e('Nouveau projet…', 'mj-member'); ?>" />
                    <button type="submit" class="mj-hourencore__project-add" aria-label="<?php esc_attr_e('Ajouter un projet', 'mj-member'); ?>">
                        <span aria-hidden="true">+</span>
                    </button>
                </form>
            </section>

            <section class="mj-hourencore__section">
                <h4 class="mj-hourencore__section-title"><?php esc_html_e('Événements de la semaine', 'mj-member'); ?></h4>
                <div class="mj-hourencore__events" data-week-events>
                    <?php
                    $events = isset($template_data['events']) && is_array($template_data['events']) ? $template_data['events'] : array();
                    if (empty($events) && function_exists('is_elementor_preview') && is_elementor_preview()) {
                        $events = array(
                            array(
                                'title' => __('Soirée jeux', 'mj-member'),
                                'date' => $week['start'],
                                'time' => '19:00',
                            ),
                            array(
                                'title' => __('Atelier théâtre', 'mj-member'),
                                'date' => DateTimeImmutable::createFromFormat('Y-m-d', $week['start'])->modify('+4 days')->format('Y-m-d'),
                                'time' => '17:30',
                            ),
                        );
                    }

                    if (!empty($events)) :
                        foreach ($events as $event) :
                            $event_title = isset($event['title']) ? (string) $event['title'] : '';
                            $event_date = isset($event['date']) ? (string) $event['date'] : '';
                            $event_time = isset($event['time']) ? (string) $event['time'] : '';
                            ?>
                            <article class="mj-hourencore__event">
                                <h5 class="mj-hourencore__event-title"><?php echo esc_html($event_title); ?></h5>
                                <p class="mj-hourencore__event-meta"><?php echo esc_html($event_date . ($event_time !== '' ? ' • ' . $event_time : '')); ?></p>
                            </article>
                        <?php endforeach;
                    else :
                        ?>
                        <p class="mj-hourencore__empty">
                            <?php esc_html_e('Aucun événement cette semaine.', 'mj-member'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</div>
