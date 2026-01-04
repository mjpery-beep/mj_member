<?php
if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Core\Config;
use Mj\Member\Core\AssetsManager;

$settings = $this->get_settings_for_display();
$widget_id = 'mj-events-manager-' . $this->get_id();
$is_preview = \Elementor\Plugin::$instance->editor->is_edit_mode();

AssetsManager::requirePackage('events-manager');

$title = !empty($settings['title']) ? $settings['title'] : __('Gestion des événements', 'mj-member');
$show_past = !empty($settings['show_past_events']) && $settings['show_past_events'] === 'yes';
$per_page = !empty($settings['events_per_page']) ? (int) $settings['events_per_page'] : 20;

if ($is_preview) {
    $now_timestamp = current_time('timestamp');
    $preview_events = [
        [
            'title' => __('Stage vacances - Arts créatifs', 'mj-member'),
            'status' => __('Actif', 'mj-member'),
            'type' => __('Stage', 'mj-member'),
            'date' => wp_date('d/m/Y H:i', $now_timestamp + DAY_IN_SECONDS),
            'price' => number_format_i18n(25, 0) . ' €',
            'capacity' => sprintf(_n('%d place', '%d places', 20, 'mj-member'), 20),
            'description' => __('Atelier immersif de deux jours pour les 8-12 ans avec restitution scénique.', 'mj-member'),
            'schedule_type' => 'range',
            'schedule_range' => sprintf(
                __('%1$s • %2$s - %3$s', 'mj-member'),
                wp_date('d/m/Y', $now_timestamp + DAY_IN_SECONDS),
                wp_date('H:i', $now_timestamp + DAY_IN_SECONDS),
                wp_date('H:i', $now_timestamp + DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS)
            ),
            'attendance_show_all_members' => true,
        ],
        [
            'title' => __('Atelier découverte Hip-Hop', 'mj-member'),
            'status' => __('Brouillon', 'mj-member'),
            'type' => __('Atelier', 'mj-member'),
            'date' => wp_date('d/m/Y H:i', $now_timestamp + 3 * DAY_IN_SECONDS),
            'price' => __('Gratuit', 'mj-member'),
            'capacity' => sprintf(_n('%d place', '%d places', 12, 'mj-member'), 12),
            'description' => __('Session d\'initiation aux bases du breakdance encadrée par un intervenant MJ.', 'mj-member'),
            'schedule_type' => 'recurring',
            'schedule_days' => [
                [
                    'day' => __('Mercredi', 'mj-member'),
                    'time' => '14:00 - 16:00',
                ],
                [
                    'day' => __('Samedi', 'mj-member'),
                    'time' => '10:30 - 12:00',
                ],
            ],
            'schedule_until' => sprintf(
                __('Jusqu\'au %s', 'mj-member'),
                wp_date('d/m/Y', $now_timestamp + 90 * DAY_IN_SECONDS)
            ),
            'attendance_show_all_members' => false,
        ],
    ];
    ?>
    <div class="mj-events-manager mj-events-manager--preview" data-widget-id="<?php echo esc_attr($widget_id); ?>">
        <div class="mj-events-manager__header">
            <h2 class="mj-events-manager__title"><?php echo esc_html($title); ?></h2>
            <button type="button" class="mj-events-manager__add-btn" disabled>
                <span class="dashicons dashicons-plus-alt2"></span>
                <span><?php esc_html_e('Créer un événement', 'mj-member'); ?></span>
            </button>
        </div>

        <div class="mj-events-manager__toolbar">
            <div class="mj-events-manager__search">
                <input type="text" class="mj-events-manager__search-input" placeholder="<?php esc_attr_e('Rechercher...', 'mj-member'); ?>" disabled />
            </div>
            <div class="mj-events-manager__filters">
                <select class="mj-events-manager__filter-select" disabled>
                    <option><?php esc_html_e('Tous les statuts', 'mj-member'); ?></option>
                </select>
                <select class="mj-events-manager__filter-select" disabled>
                    <option><?php esc_html_e('Tous les types', 'mj-member'); ?></option>
                </select>
            </div>
        </div>

        <div class="mj-events-manager__content">
            <div class="mj-events-manager__list">
                <?php foreach ($preview_events as $index => $preview_event) : ?>
                    <div class="mj-events-manager-card" data-event-id="preview-<?php echo esc_attr((string) $index); ?>">
                        <div class="mj-events-manager-card__header">
                            <h3 class="mj-events-manager-card__title"><?php echo esc_html($preview_event['title']); ?></h3>
                            <div class="mj-events-manager-card__badges">
                                <span class="mj-events-manager-card__badge mj-events-manager-card__badge--status"><?php echo esc_html($preview_event['status']); ?></span>
                                <span class="mj-events-manager-card__badge mj-events-manager-card__badge--type"><?php echo esc_html($preview_event['type']); ?></span>
                            </div>
                        </div>
                        <div class="mj-events-manager-card__body">
                            <div class="mj-events-manager-card__meta">
                                <div class="mj-events-manager-card__meta-item">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <span><?php echo esc_html($preview_event['date']); ?></span>
                                </div>
                                <div class="mj-events-manager-card__meta-item">
                                    <span class="dashicons dashicons-tickets-alt"></span>
                                    <span><?php echo esc_html($preview_event['price']); ?></span>
                                </div>
                                <div class="mj-events-manager-card__meta-item">
                                    <span class="dashicons dashicons-groups"></span>
                                    <span><?php echo esc_html($preview_event['capacity']); ?></span>
                                </div>
                                <?php
                                $attendance_label = !empty($preview_event['attendance_show_all_members'])
                                    ? __('Liste de présence : tous les membres', 'mj-member')
                                    : __('Liste de présence : inscrits uniquement', 'mj-member');
                                ?>
                                <div class="mj-events-manager-card__meta-item">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <span><?php echo esc_html($attendance_label); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($preview_event['schedule_type'])) : ?>
                                <div class="mj-events-manager-card__schedule">
                                    <div class="mj-events-manager-card__schedule-title"><?php esc_html_e('Planification', 'mj-member'); ?></div>
                                    <?php if ($preview_event['schedule_type'] === 'range' && !empty($preview_event['schedule_range'])) : ?>
                                        <p class="mj-events-manager-card__schedule-range"><?php echo esc_html($preview_event['schedule_range']); ?></p>
                                    <?php elseif ($preview_event['schedule_type'] === 'recurring' && !empty($preview_event['schedule_days']) && is_array($preview_event['schedule_days'])) : ?>
                                        <ul class="mj-events-manager-card__schedule-list">
                                            <?php foreach ($preview_event['schedule_days'] as $day_item) : ?>
                                                <?php if (empty($day_item['day']) && empty($day_item['time'])) { continue; } ?>
                                                <li class="mj-events-manager-card__schedule-item">
                                                    <?php if (!empty($day_item['day'])) : ?>
                                                        <span class="mj-events-manager-card__schedule-day"><?php echo esc_html($day_item['day']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($day_item['time'])) : ?>
                                                        <span class="mj-events-manager-card__schedule-time"><?php echo esc_html($day_item['time']); ?></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if (!empty($preview_event['schedule_until'])) : ?>
                                            <div class="mj-events-manager-card__schedule-footer"><?php echo esc_html($preview_event['schedule_until']); ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <p class="mj-events-manager-card__description"><?php echo esc_html($preview_event['description']); ?></p>
                        </div>
                        <div class="mj-events-manager-card__actions">
                            <button type="button" class="mj-events-manager-card__action" disabled>
                                <span class="dashicons dashicons-edit"></span>
                                <span><?php esc_html_e('Modifier', 'mj-member'); ?></span>
                            </button>
                            <button type="button" class="mj-events-manager-card__action mj-events-manager-card__action--danger" disabled>
                                <span class="dashicons dashicons-trash"></span>
                                <span><?php esc_html_e('Supprimer', 'mj-member'); ?></span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mj-events-manager__pagination">
                <div class="mj-events-manager-pagination">
                    <button type="button" class="mj-events-manager-pagination__btn mj-events-manager-pagination__btn--active" disabled>1</button>
                    <button type="button" class="mj-events-manager-pagination__btn" disabled>2</button>
                    <button type="button" class="mj-events-manager-pagination__btn" disabled>3</button>
                </div>
            </div>
        </div>

        <div class="mj-events-manager__feedback" aria-live="polite"><?php esc_html_e('Mode aperçu Elementor - interactions désactivées.', 'mj-member'); ?></div>
    </div>
    <?php
    return;
}

if (!class_exists('MjEvents')) {
    require_once Config::path() . 'includes/classes/crud/MjEvents.php';
}
if (!class_exists('Mj\Member\Classes\Forms\EventFormRenderer')) {
    require_once Config::path() . 'includes/classes/forms/EventFormRenderer.php';
}

$ajax_url = admin_url('admin-ajax.php');
$ajax_nonce = wp_create_nonce('mj-events-manager');

$event_types = MjEvents::get_type_labels();
$event_statuses = MjEvents::get_status_labels();
$event_categories = [];
if (function_exists('get_terms')) {
    $terms = get_terms([
        'taxonomy' => 'mj_event_category',
        'hide_empty' => false,
    ]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $event_categories[$term->term_id] = $term->name;
        }
    }
}

$config_json = wp_json_encode([
    'widgetId' => $widget_id,
    'ajaxUrl' => $ajax_url,
    'nonce' => $ajax_nonce,
    'showPast' => $show_past,
    'perPage' => $per_page,
    'eventTypes' => $event_types,
    'eventStatuses' => $event_statuses,
    'eventCategories' => $event_categories,
    'weekdayLabels' => [
        'monday' => __('Lundi', 'mj-member'),
        'tuesday' => __('Mardi', 'mj-member'),
        'wednesday' => __('Mercredi', 'mj-member'),
        'thursday' => __('Jeudi', 'mj-member'),
        'friday' => __('Vendredi', 'mj-member'),
        'saturday' => __('Samedi', 'mj-member'),
        'sunday' => __('Dimanche', 'mj-member'),
    ],
    'ordinals' => [
        'first' => __('1er', 'mj-member'),
        'second' => __('2e', 'mj-member'),
        'third' => __('3e', 'mj-member'),
        'fourth' => __('4e', 'mj-member'),
        'last' => __('Dernier', 'mj-member'),
    ],
    'strings' => [
        'addNew' => __('Créer un événement', 'mj-member'),
        'edit' => __('Modifier', 'mj-member'),
        'delete' => __('Supprimer', 'mj-member'),
        'confirmDelete' => __('Supprimer cet événement ? Cette action est irréversible.', 'mj-member'),
        'loading' => __('Chargement...', 'mj-member'),
        'error' => __('Une erreur est survenue.', 'mj-member'),
        'success' => __('Opération réussie.', 'mj-member'),
        'saveEvent' => __('Enregistrer', 'mj-member'),
        'cancel' => __('Annuler', 'mj-member'),
        'close' => __('Fermer', 'mj-member'),
        'noEvents' => __('Aucun événement trouvé.', 'mj-member'),
        'search' => __('Rechercher...', 'mj-member'),
        'filterAll' => __('Tous', 'mj-member'),
        'filterActive' => __('Actifs', 'mj-member'),
        'filterPast' => __('Passés', 'mj-member'),
        'sortNewest' => __('Plus récents', 'mj-member'),
        'sortOldest' => __('Plus anciens', 'mj-member'),
        'sortTitle' => __('Titre', 'mj-member'),
        'scheduleTitle' => __('Planification', 'mj-member'),
        'scheduleAllDay' => __('Toute la journée', 'mj-member'),
        'scheduleUntilPrefix' => __('Jusqu\'au', 'mj-member'),
        'scheduleFallback' => __('Planification non renseignée.', 'mj-member'),
        'scheduleMonthlyPattern' => __('Chaque %1$s %2$s', 'mj-member'),
        'attendanceAllMembers' => __('Liste de présence : tous les membres', 'mj-member'),
        'attendanceRegisteredOnly' => __('Liste de présence : inscrits uniquement', 'mj-member'),
    ],
]);
?>

<div class="mj-events-manager" data-widget-id="<?php echo esc_attr($widget_id); ?>" data-mj-events-manager data-config="<?php echo esc_attr($config_json); ?>">
    <div class="mj-events-manager__header">
        <h2 class="mj-events-manager__title"><?php echo esc_html($title); ?></h2>
        <button type="button" class="mj-events-manager__add-btn" data-action="add-event">
            <span class="dashicons dashicons-plus-alt2"></span>
            <span><?php esc_html_e('Créer un événement', 'mj-member'); ?></span>
        </button>
    </div>

    <div class="mj-events-manager__toolbar">
        <div class="mj-events-manager__search">
            <input type="text" class="mj-events-manager__search-input" placeholder="<?php esc_attr_e('Rechercher...', 'mj-member'); ?>" data-search-input />
        </div>
        <div class="mj-events-manager__filters">
            <select class="mj-events-manager__filter-select" data-filter-status>
                <option value=""><?php esc_html_e('Tous les statuts', 'mj-member'); ?></option>
                <?php foreach ($event_statuses as $status_key => $status_label) : ?>
                    <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_label); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="mj-events-manager__filter-select" data-filter-type>
                <option value=""><?php esc_html_e('Tous les types', 'mj-member'); ?></option>
                <?php foreach ($event_types as $type_key => $type_label) : ?>
                    <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mj-events-manager__feedback" data-feedback aria-live="polite"></div>

    <div class="mj-events-manager__content">
        <div class="mj-events-manager__loading" data-loading hidden>
            <div class="mj-events-manager__spinner"></div>
            <p><?php esc_html_e('Chargement...', 'mj-member'); ?></p>
        </div>

        <div class="mj-events-manager__list" data-events-list></div>

        <div class="mj-events-manager__empty" data-empty-state hidden>
            <div class="mj-events-manager__empty-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <p><?php esc_html_e('Aucun événement trouvé.', 'mj-member'); ?></p>
            <button type="button" class="mj-events-manager__add-btn mj-events-manager__add-btn--secondary" data-action="add-event">
                <?php esc_html_e('Créer votre premier événement', 'mj-member'); ?>
            </button>
        </div>

        <div class="mj-events-manager__pagination" data-pagination hidden></div>
    </div>
</div>

<div class="mj-events-manager-modal" data-modal hidden>
    <div class="mj-events-manager-modal__overlay" data-modal-overlay></div>
    <div class="mj-events-manager-modal__container">
        <div class="mj-events-manager-modal__header">
            <h3 class="mj-events-manager-modal__title" data-modal-title><?php esc_html_e('Événement', 'mj-member'); ?></h3>
            <button type="button" class="mj-events-manager-modal__close" data-modal-close aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="mj-events-manager-modal__body" data-modal-body>
            <form class="mj-events-manager-form" data-event-form>
                <input type="hidden" name="event_id" data-event-id value="" />
                
                <?php
                use Mj\Member\Classes\Forms\EventFormRenderer;
                
                // Préparer les options pour le rendu
                $form_options = [
                    'types' => $event_types,
                    'statuses' => $event_statuses,
                ];
                
                // Rendre les champs de base (le JS hydratera les valeurs dynamiquement)
                EventFormRenderer::renderBasicFields([], $form_options);
                
                // Rendre les champs de planification/récurrence
                EventFormRenderer::renderScheduleFields([]);
                ?>

                <div class="mj-events-manager-form__actions">
                    <button type="button" class="mj-events-manager-form__cancel" data-modal-close><?php esc_html_e('Annuler', 'mj-member'); ?></button>
                    <button type="submit" class="mj-events-manager-form__submit"><?php esc_html_e('Enregistrer', 'mj-member'); ?></button>
                </div>

                <div class="mj-events-manager-form__feedback" data-form-feedback></div>
            </form>
        </div>
    </div>
</div>
