<?php

use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

$intro_text = isset($intro_text) && is_string($intro_text) ? $intro_text : '';

AssetsManager::requirePackage('hour-encode');

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$hasAccess = current_user_can(Config::hoursCapability());

if (!$hasAccess && !$isPreview) {
    echo '<div class="mj-hour-encode mj-hour-encode--restricted">' . esc_html__("Vous n'avez pas accès à cet outil.", 'mj-member') . '</div>';
    return;
}

$now = current_time('timestamp');
$weekStartTimestamp = strtotime('monday this week', $now);
if (!is_int($weekStartTimestamp)) {
    $weekStartTimestamp = $now;
}

$locale = determine_locale();
$commonTasks = array();
$projects = array();
$entries = array();
$events = array();
$projectTotalsPreview = array();

if ($isPreview) {
    $commonTasks = array(
        'Accueil jeunes',
        'Préparation atelier',
        'Réunion équipe',
        'Suivi administratif',
    );

    $projects = array(
        'Projet Citoyen',
        'Studio Musique',
        'Communication',
    );

    $entries = array(
        array(
            'id' => 'sample-1',
            'task' => 'Accueil jeunes',
            'project' => 'Projet Citoyen',
            'start' => wp_date('c', $weekStartTimestamp + (2 * HOUR_IN_SECONDS)),
            'end' => wp_date('c', $weekStartTimestamp + (4 * HOUR_IN_SECONDS)),
            'color' => '#3366ff',
        ),
        array(
            'id' => 'sample-2',
            'task' => 'Préparation atelier',
            'project' => 'Studio Musique',
            'start' => wp_date('c', $weekStartTimestamp + DAY_IN_SECONDS + (9 * HOUR_IN_SECONDS)),
            'end' => wp_date('c', $weekStartTimestamp + DAY_IN_SECONDS + (12 * HOUR_IN_SECONDS)),
            'color' => '#26a69a',
        ),
        array(
            'id' => 'sample-3',
            'task' => 'Réunion équipe',
            'project' => 'Communication',
            'start' => wp_date('c', $weekStartTimestamp + (4 * DAY_IN_SECONDS) + (14 * HOUR_IN_SECONDS)),
            'end' => wp_date('c', $weekStartTimestamp + (4 * DAY_IN_SECONDS) + (16 * HOUR_IN_SECONDS)),
            'color' => '#ef6c00',
        ),
    );

    $events = array(
        array(
            'id' => 'event-1',
            'title' => 'Soirée jeux',
            'start' => wp_date('c', $weekStartTimestamp + (2 * DAY_IN_SECONDS) + (18 * HOUR_IN_SECONDS)),
            'end' => wp_date('c', $weekStartTimestamp + (2 * DAY_IN_SECONDS) + (21 * HOUR_IN_SECONDS)),
            'location' => 'MJ Péry',
        ),
        array(
            'id' => 'event-2',
            'title' => 'Atelier DJ',
            'start' => wp_date('c', $weekStartTimestamp + (5 * DAY_IN_SECONDS) + (15 * HOUR_IN_SECONDS)),
            'end' => wp_date('c', $weekStartTimestamp + (5 * DAY_IN_SECONDS) + (18 * HOUR_IN_SECONDS)),
            'location' => 'Studio musique',
        ),
    );

    $projectTotalsPreview = array(
        array(
            'project' => 'Projet Citoyen',
            'total_minutes' => 540,
            'months' => array(
                wp_date('Y-m', $weekStartTimestamp) => 320,
            ),
            'years' => array(
                wp_date('Y', $weekStartTimestamp) => 540,
            ),
            'weeks' => array(
                wp_date('Y-m-d', $weekStartTimestamp) => 540,
                wp_date('Y-m-d', $weekStartTimestamp - (7 * DAY_IN_SECONDS)) => 480,
            ),
            'tasks' => array(
                'Accueil jeunes' => 180,
                'Suivi administratif' => 220,
                'Atelier citoyen' => 140,
            ),
        ),
        array(
            'project' => 'Studio Musique',
            'total_minutes' => 420,
            'months' => array(
                wp_date('Y-m', $weekStartTimestamp) => 240,
            ),
            'years' => array(
                wp_date('Y', $weekStartTimestamp) => 420,
            ),
            'weeks' => array(
                wp_date('Y-m-d', $weekStartTimestamp) => 420,
            ),
            'tasks' => array(
                'Atelier DJ' => 200,
                'Préparation scène' => 140,
                'Maintenance studio' => 80,
            ),
        ),
        array(
            'project' => 'Communication',
            'total_minutes' => 180,
            'months' => array(
                wp_date('Y-m', $weekStartTimestamp) => 180,
            ),
            'years' => array(
                wp_date('Y', $weekStartTimestamp) => 180,
            ),
            'weeks' => array(
                wp_date('Y-m-d', $weekStartTimestamp) => 120,
                wp_date('Y-m-d', $weekStartTimestamp + (7 * DAY_IN_SECONDS)) => 60,
            ),
            'tasks' => array(
                'Réseaux sociaux' => 120,
                'Newsletter' => 60,
            ),
        ),
    );
}

$config = array(
    'locale' => $locale,
    'weekStart' => wp_date('Y-m-d', $weekStartTimestamp),
    'timezone' => wp_timezone_string(),
    'introText' => $intro_text,
    'ajax' => array(
        'url' => esc_url_raw(admin_url('admin-ajax.php')),
        'action' => 'mj_member_hour_encode_week',
        'weekAction' => 'mj_member_hour_encode_week',
        'createAction' => 'mj_member_hour_encode_create',
        'updateAction' => 'mj_member_hour_encode_update',
        'deleteAction' => 'mj_member_hour_encode_delete',
        'renameProjectAction' => 'mj_member_hour_encode_rename_project',
        'renameTaskAction' => 'mj_member_hour_encode_rename_task',
        'nonce' => wp_create_nonce('mj-member-hour-encode'),
        'renameNonce' => wp_create_nonce('mj-member-hour-encode'),
    ),
    'entries' => array_map(static function ($entry) {
        return array(
            'id' => isset($entry['id']) ? sanitize_text_field((string) $entry['id']) : '',
            'task' => isset($entry['task']) ? sanitize_text_field((string) $entry['task']) : '',
            'project' => isset($entry['project']) ? sanitize_text_field((string) $entry['project']) : '',
            'start' => isset($entry['start']) ? sanitize_text_field((string) $entry['start']) : '',
            'end' => isset($entry['end']) ? sanitize_text_field((string) $entry['end']) : '',
            'color' => isset($entry['color']) ? (sanitize_hex_color((string) $entry['color']) ?: '') : '',
        );
    }, $entries),
    'commonTasks' => array_map('sanitize_text_field', $commonTasks),
    'projects' => array_map('sanitize_text_field', $projects),
    'events' => array_map(static function ($event) {
        return array(
            'id' => isset($event['id']) ? sanitize_text_field((string) $event['id']) : '',
            'title' => isset($event['title']) ? sanitize_text_field((string) $event['title']) : '',
            'start' => isset($event['start']) ? sanitize_text_field((string) $event['start']) : '',
            'end' => isset($event['end']) ? sanitize_text_field((string) $event['end']) : '',
            'location' => isset($event['location']) ? sanitize_text_field((string) $event['location']) : '',
            'accentColor' => isset($event['color']) ? (sanitize_hex_color((string) $event['color']) ?: '') : (isset($event['accent_color']) ? (sanitize_hex_color((string) $event['accent_color']) ?: '') : ''),
        );
    }, $events),
    'projectTotals' => array_map(static function ($total) {
        $sanitizedTasks = array();
        if (isset($total['tasks']) && is_array($total['tasks'])) {
            foreach ($total['tasks'] as $taskName => $minutes) {
                $label = sanitize_text_field((string) $taskName);
                if ($label === '') {
                    continue;
                }
                $sanitizedTasks[$label] = isset($minutes) ? (int) $minutes : 0;
            }
        }
        return array(
            'project' => isset($total['project']) ? sanitize_text_field((string) $total['project']) : '',
            'total_minutes' => isset($total['total_minutes']) ? (int) $total['total_minutes'] : 0,
            'months' => isset($total['months']) && is_array($total['months']) ? array_map('intval', $total['months']) : array(),
            'years' => isset($total['years']) && is_array($total['years']) ? array_map('intval', $total['years']) : array(),
            'tasks' => $sanitizedTasks,
        );
    }, $projectTotalsPreview),
    'labels' => array(
        'title' => __('Encodage des Heures de Travail', 'mj-member'),
        'subtitle' => __('Enregistrez et suivez vos heures de travail pour la semaine.', 'mj-member'),
        'weekRange' => __('Semaine du %s au %s', 'mj-member'),
        'previousWeek' => __('Semaine précédente', 'mj-member'),
        'nextWeek' => __('Semaine suivante', 'mj-member'),
        'today' => __('Aujourd’hui', 'mj-member'),
        'totalWeek' => __('Total semaine', 'mj-member'),
        'totalMonth' => __('Total mois', 'mj-member'),
        'totalYear' => __('Total année', 'mj-member'),
        'totalLifetime' => __('Total cumulé', 'mj-member'),
        'statsWeek' => __('Semaine', 'mj-member'),
        'statsMonth' => __('Mois', 'mj-member'),
        'statsYear' => __('Année', 'mj-member'),
        'statsTotal' => __('Total', 'mj-member'),
        'export' => __('Exporter', 'mj-member'),
        'hoursShort' => __('h', 'mj-member'),
        'emptyCalendar' => __('Le calendrier se chargera une fois les données récupérées.', 'mj-member'),
        'newTask' => __('Nouvelle tâche', 'mj-member'),
        'suggestedTasks' => __('Tâches suggérées', 'mj-member'),
        'pinnedProjects' => __('Projets épinglés', 'mj-member'),
        'projectPlaceholder' => __('Ajouter un projet…', 'mj-member'),
        'addProjectAction' => __('Ajouter', 'mj-member'),
        'addProjectShort' => __('Ajouter', 'mj-member'),
        'loading' => __('Chargement…', 'mj-member'),
        'noEvents' => __('Aucun événement planifié pour cette semaine.', 'mj-member'),
        'fetchError' => __('Impossible de charger les données de la semaine.', 'mj-member'),
        'noTasks' => __('Aucune suggestion disponible pour le moment.', 'mj-member'),
        'noProjects' => __('Aucun projet enregistré pour le moment.', 'mj-member'),
        'selectionTitle' => __('Encoder une nouvelle plage', 'mj-member'),
        'selectionEditTitle' => __('Modifier la plage encodée', 'mj-member'),
        'selectionDescription' => '',
        'selectionTaskLabel' => __('Intitulé de la tâche', 'mj-member'),
        'selectionProjectLabel' => __('Projet associé', 'mj-member'),
        'selectionStartLabel' => __('Début', 'mj-member'),
        'selectionEndLabel' => __('Fin', 'mj-member'),
        'selectionDurationLabel' => __('Durée estimée', 'mj-member'),
        'selectionConfirm' => __('Encoder cette plage', 'mj-member'),
        'selectionUpdate' => __('Mettre à jour la plage', 'mj-member'),
        'selectionCancel' => __('Annuler', 'mj-member'),
        'selectionErrorRange' => __('Veuillez choisir une heure de fin postérieure à l’heure de début.', 'mj-member'),
        'selectionErrorTask' => __('Veuillez saisir un intitulé.', 'mj-member'),
        'selectionDelete' => __('Supprimer', 'mj-member'),
        'selectionDeleteConfirm' => __('Voulez-vous vraiment supprimer cette plage ?', 'mj-member'),
        'selectionDeleteSuccess' => __('Plage supprimée avec succès.', 'mj-member'),
        'calendarTitle' => __('Calendrier', 'mj-member'),
        'calendarPrevious' => __('Mois précédent', 'mj-member'),
        'calendarNext' => __('Mois suivant', 'mj-member'),
    ),
    'capabilities' => array(
        'canManage' => $hasAccess,
    ),
    'isPreview' => $isPreview,
);

$configJson = wp_json_encode($config, JSON_UNESCAPED_SLASHES);
if (!is_string($configJson)) {
    $configJson = '{}';
}
?>
<?php if ($intro_text !== '') : ?>
    <div class="mj-hour-encode__intro">
        <?php echo wp_kses_post($intro_text); ?>
    </div>
<?php endif; ?>
<div class="mj-hour-encode" data-config="<?php echo esc_attr($configJson); ?>">
    <div class="mj-hour-encode__placeholder">
        <?php echo esc_html($config['labels']['loading']); ?>
    </div>
</div>
