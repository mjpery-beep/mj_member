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
    ?>
    <div class="mj-events-manager" data-widget-id="<?php echo esc_attr($widget_id); ?>">
        <div class="mj-events-manager__preview">
            <div class="mj-events-manager__preview-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php esc_html_e('Aperçu du widget de gestion d\'événements (visible uniquement pour les animateurs et coordinateurs en production).', 'mj-member'); ?></p>
        </div>
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
