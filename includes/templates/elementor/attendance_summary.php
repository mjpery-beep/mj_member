<?php
/**
 * Template widget Elementor - Recapitulatif des presences
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEventAttendance;
use Mj\Member\Classes\Crud\MjEventRegistrations;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Core\AssetsManager;

AssetsManager::requirePackage('attendance-summary');

$is_preview = false;
if (function_exists('mj_member_login_component_is_preview_mode')) {
    $is_preview = mj_member_login_component_is_preview_mode();
} elseif (did_action('elementor/loaded') && isset(\Elementor\Plugin::$instance->editor)) {
    $is_preview = \Elementor\Plugin::$instance->editor->is_edit_mode();
}

$widget_title = !empty($title) ? (string) $title : __('Recapitulatif des presences', 'mj-member');
$limit = isset($max_events) ? max(1, (int) $max_events) : 10;
$display_completion = !empty($show_completion);

$summary_rows = array();
$type_options = array();

$format_event_date = static function ($value) {
    $raw = is_string($value) ? trim($value) : '';
    if ($raw === '') {
        return __('Date non definie', 'mj-member');
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    if (function_exists('wp_date')) {
        return wp_date('d/m/Y H:i', $timestamp);
    }

    return date_i18n('d/m/Y H:i', $timestamp);
};

$get_event_value = static function ($event, $key, $default = null) {
    if (is_object($event) && isset($event->{$key})) {
        return $event->{$key};
    }

    if (is_array($event) && array_key_exists($key, $event)) {
        return $event[$key];
    }

    return $default;
};

$compute_event_summary = static function ($event_id) {
    $rows = MjEventRegistrations::get_by_event((int) $event_id);

    $participant_ids = array();
    $marked_member_ids = array();
    $present_records = 0;
    $absent_records = 0;

    foreach ($rows as $registration) {
        $registration_status = sanitize_key((string) $registration->statut);
        if ($registration_status === MjEventRegistrations::STATUS_CANCELLED || $registration_status === MjEventRegistrations::STATUS_WAITLIST) {
            continue;
        }

        $member_id = (int) $registration->member_id;
        if ($member_id <= 0) {
            continue;
        }

        $participant_ids[$member_id] = true;
    }

    // Build attendance counters from the normalized attendance map to stay aligned
    // with the same source used by other attendance-oriented interfaces.
    $attendance_map = MjEventAttendance::get_map((int) $event_id);
    $occurrences_count = is_array($attendance_map) ? count($attendance_map) : 0;

    if (!empty($attendance_map) && is_array($attendance_map)) {
        foreach ($attendance_map as $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $member_id => $entry) {
                $member_id = (int) $member_id;
                if ($member_id <= 0 || !is_array($entry)) {
                    continue;
                }

                $status = isset($entry['status']) ? MjEventAttendance::normalize_status((string) $entry['status']) : '';
                if ($status === MjEventAttendance::STATUS_PRESENT) {
                    $present_records++;
                    $marked_member_ids[$member_id] = true;
                } elseif ($status === MjEventAttendance::STATUS_ABSENT) {
                    $absent_records++;
                    $marked_member_ids[$member_id] = true;
                }
            }
        }
    }

    $participants_count = count($participant_ids);
    $marked_count = count($marked_member_ids);

    return array(
        'participants' => $participants_count,
        'present_records' => $present_records,
        'absent_records' => $absent_records,
        'marked_members' => $marked_count,
        'to_mark' => max(0, $participants_count - $marked_count),
        'completion_rate' => $participants_count > 0 ? (int) round(($marked_count / $participants_count) * 100) : 0,
        'occurrences' => $occurrences_count,
    );
};

if ($is_preview) {
    $summary_rows = array(
        array(
            'title' => __('Stage ete - arts urbains', 'mj-member'),
            'type' => __('Stage', 'mj-member'),
            'status' => __('Actif', 'mj-member'),
            'type_key' => 'stage',
            'date_start_raw' => '2026-07-12 09:00:00',
            'date_start' => __('12/07/2026 09:00', 'mj-member'),
            'date_end' => __('16/07/2026 17:00', 'mj-member'),
            'participants' => 24,
            'present_records' => 89,
            'absent_records' => 7,
            'to_mark' => 3,
            'completion_rate' => 88,
            'occurrences' => 4,
        ),
        array(
            'title' => __('Sortie nature - Ardennes', 'mj-member'),
            'type' => __('Sortie', 'mj-member'),
            'status' => __('Passe', 'mj-member'),
            'type_key' => 'sortie',
            'date_start_raw' => '2026-07-02 08:30:00',
            'date_start' => __('02/07/2026 08:30', 'mj-member'),
            'date_end' => __('02/07/2026 18:30', 'mj-member'),
            'participants' => 17,
            'present_records' => 16,
            'absent_records' => 1,
            'to_mark' => 0,
            'completion_rate' => 100,
            'occurrences' => 1,
        ),
    );

    $type_options = array(
        'stage' => __('Stage', 'mj-member'),
        'sortie' => __('Sortie', 'mj-member'),
    );
} elseif (class_exists('Mj\\Member\\Classes\\Crud\\MjEvents') && class_exists('Mj\\Member\\Classes\\Crud\\MjEventRegistrations') && class_exists('Mj\\Member\\Classes\\Crud\\MjEventAttendance')) {
    $status_filters = array(MjEvents::STATUS_ACTIVE, MjEvents::STATUS_PAST);
    if (!empty($include_draft)) {
        $status_filters[] = MjEvents::STATUS_DRAFT;
    }

    $events = MjEvents::get_all(array(
        'statuses' => array_values(array_unique($status_filters)),
        'orderby' => 'date_debut',
        'order' => 'DESC',
        'limit' => max(10, $limit * 3),
    ));

    $type_labels = MjEvents::get_type_labels();
    $status_labels = MjEvents::get_status_labels();

    foreach ($events as $event) {
        if (count($summary_rows) >= $limit) {
            break;
        }

        $event_id = (int) $get_event_value($event, 'id', 0);
        if ($event_id <= 0) {
            continue;
        }

        $summary = $compute_event_summary($event_id);

        $event_type_key = sanitize_key((string) $get_event_value($event, 'type', ''));
        $event_status_key = sanitize_key((string) $get_event_value($event, 'status', ''));

        $summary_rows[] = array(
            'title' => (string) $get_event_value($event, 'title', sprintf(__('Evenement #%d', 'mj-member'), $event_id)),
            'type' => isset($type_labels[$event_type_key]) ? (string) $type_labels[$event_type_key] : ucfirst($event_type_key),
            'status' => isset($status_labels[$event_status_key]) ? (string) $status_labels[$event_status_key] : ucfirst($event_status_key),
            'type_key' => $event_type_key,
            'date_start_raw' => (string) $get_event_value($event, 'date_debut', ''),
            'date_start' => $format_event_date((string) $get_event_value($event, 'date_debut', '')),
            'date_end' => $format_event_date((string) $get_event_value($event, 'date_fin', '')),
            'participants' => (int) $summary['participants'],
            'present_records' => (int) $summary['present_records'],
            'absent_records' => (int) $summary['absent_records'],
            'to_mark' => (int) $summary['to_mark'],
            'completion_rate' => (int) $summary['completion_rate'],
            'occurrences' => (int) $summary['occurrences'],
        );

        if ($event_type_key !== '' && !isset($type_options[$event_type_key])) {
            $type_options[$event_type_key] = isset($type_labels[$event_type_key])
                ? (string) $type_labels[$event_type_key]
                : ucfirst($event_type_key);
        }
    }
}
?>

<section class="mj-attendance-summary" aria-label="<?php echo esc_attr($widget_title); ?>">
    <header class="mj-attendance-summary__header">
        <h2 class="mj-attendance-summary__title"><?php echo esc_html($widget_title); ?></h2>
        <p class="mj-attendance-summary__subtitle"><?php esc_html_e('Vue rapide des presences enregistrees par evenement.', 'mj-member'); ?></p>
    </header>

    <?php if (empty($summary_rows)) : ?>
        <div class="mj-attendance-summary__empty">
            <?php esc_html_e('Aucune donnee de presence disponible pour le moment.', 'mj-member'); ?>
        </div>
    <?php else : ?>
        <form class="mj-attendance-summary__filters" data-att-summary-filters>
            <label class="mj-attendance-summary__filter-field">
                <span><?php esc_html_e('Type', 'mj-member'); ?></span>
                <select data-filter="type">
                    <option value=""><?php esc_html_e('Tous', 'mj-member'); ?></option>
                    <?php foreach ($type_options as $type_key => $type_label) : ?>
                        <option value="<?php echo esc_attr((string) $type_key); ?>"><?php echo esc_html((string) $type_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="mj-attendance-summary__filter-field">
                <span><?php esc_html_e('A des presences', 'mj-member'); ?></span>
                <select data-filter="presence">
                    <option value=""><?php esc_html_e('Tous', 'mj-member'); ?></option>
                    <option value="yes"><?php esc_html_e('Oui', 'mj-member'); ?></option>
                    <option value="no"><?php esc_html_e('Non', 'mj-member'); ?></option>
                </select>
            </label>

            <label class="mj-attendance-summary__filter-field">
                <span><?php esc_html_e('Du', 'mj-member'); ?></span>
                <input type="date" data-filter="date-from" />
            </label>

            <label class="mj-attendance-summary__filter-field">
                <span><?php esc_html_e('Au', 'mj-member'); ?></span>
                <input type="date" data-filter="date-to" />
            </label>

            <button type="button" class="mj-attendance-summary__reset" data-filter-reset>
                <?php esc_html_e('Reinitialiser', 'mj-member'); ?>
            </button>
        </form>

        <section class="mj-attendance-summary__chart" data-att-summary-chart>
            <header class="mj-attendance-summary__chart-header">
                <h3><?php esc_html_e('Graphique des presences', 'mj-member'); ?></h3>
                <p data-att-summary-chart-meta></p>
            </header>
            <div class="mj-attendance-summary__chart-bars" data-att-summary-chart-bars></div>
        </section>

        <div class="mj-attendance-summary__table-wrap" role="region" aria-label="<?php esc_attr_e('Tableau de recapitulatif des presences', 'mj-member'); ?>">
            <table class="mj-attendance-summary__table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Evenement', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Periode', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Participants', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Presences', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Absences', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('A pointer', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Seances', 'mj-member'); ?></th>
                        <?php if ($display_completion) : ?>
                            <th scope="col"><?php esc_html_e('Taux', 'mj-member'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary_rows as $row) : ?>
                        <?php
                        $completion_class = 'is-low';
                        if ((int) $row['completion_rate'] >= 85) {
                            $completion_class = 'is-high';
                        } elseif ((int) $row['completion_rate'] >= 60) {
                            $completion_class = 'is-medium';
                        }
                        ?>
                        <tr
                            data-att-summary-row
                            data-type="<?php echo esc_attr((string) $row['type_key']); ?>"
                            data-date="<?php echo esc_attr((string) $row['date_start_raw']); ?>"
                            data-present="<?php echo esc_attr((string) (int) $row['present_records']); ?>"
                            data-absent="<?php echo esc_attr((string) (int) $row['absent_records']); ?>"
                            data-pending="<?php echo esc_attr((string) (int) $row['to_mark']); ?>"
                            data-participants="<?php echo esc_attr((string) (int) $row['participants']); ?>"
                            data-title="<?php echo esc_attr((string) $row['title']); ?>"
                        >
                            <td>
                                <div class="mj-attendance-summary__event-title"><?php echo esc_html((string) $row['title']); ?></div>
                                <div class="mj-attendance-summary__event-meta">
                                    <span><?php echo esc_html((string) $row['type']); ?></span>
                                    <span>•</span>
                                    <span><?php echo esc_html((string) $row['status']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div><?php echo esc_html((string) $row['date_start']); ?></div>
                                <div class="mj-attendance-summary__date-end"><?php echo esc_html((string) $row['date_end']); ?></div>
                            </td>
                            <td><?php echo esc_html((string) (int) $row['participants']); ?></td>
                            <td>
                                <span class="mj-attendance-summary__badge is-present"><?php echo esc_html((string) (int) $row['present_records']); ?></span>
                            </td>
                            <td>
                                <span class="mj-attendance-summary__badge is-absent"><?php echo esc_html((string) (int) $row['absent_records']); ?></span>
                            </td>
                            <td>
                                <span class="mj-attendance-summary__badge is-pending"><?php echo esc_html((string) (int) $row['to_mark']); ?></span>
                            </td>
                            <td><?php echo esc_html((string) (int) $row['occurrences']); ?></td>
                            <?php if ($display_completion) : ?>
                                <td>
                                    <span class="mj-attendance-summary__completion <?php echo esc_attr($completion_class); ?>">
                                        <?php echo esc_html((string) (int) $row['completion_rate']); ?>%
                                    </span>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
