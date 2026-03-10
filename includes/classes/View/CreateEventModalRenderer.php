<?php

namespace Mj\Member\Classes\View;

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;

/**
 * Renders the shared create-event stepper modal (CCM).
 *
 * Used by both events-calendar and events-manager widgets.
 * All data-attributes use the `data-ccm-*` namespace so the
 * companion JS module `js/create-event-modal.js` can bind them.
 */
class CreateEventModalRenderer
{
    /**
     * Output the modal HTML.
     *
     * @param string $instance_id  Unique widget instance ID (used for
     *                             aria-labelledby and field IDs).
     */
    public static function render(string $instance_id): void
    {
        $dlg_id = 'ccm-title-' . esc_attr($instance_id);

        echo '<div class="ccm" data-ccm-modal data-ccm-instance="' . esc_attr($instance_id) . '" hidden aria-hidden="true">';
        echo '<div class="ccm__overlay" data-ccm-close></div>';
        echo '<div class="ccm__dialog" role="dialog" aria-modal="true" aria-labelledby="' . $dlg_id . '">';

        // ── Header ──
        echo '<div class="ccm__header">';
        echo '<div class="ccm__header-left">';
        echo '<span class="ccm__header-icon">&#x1F4C5;</span>';
        echo '<h2 class="ccm__title" id="' . $dlg_id . '">' . esc_html__('Nouvel événement', 'mj-member') . '</h2>';
        echo '</div>';
        echo '<button type="button" class="ccm__close" data-ccm-close aria-label="' . esc_attr__('Fermer', 'mj-member') . '">';
        echo '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
        echo '</button>';
        echo '</div>';

        // ── Stepper ──
        echo '<nav class="ccm__stepper" data-ccm-stepper>';
        $step_labels = [
            1 => __('Identité', 'mj-member'),
            2 => __('Horaires', 'mj-member'),
            3 => __('Options', 'mj-member'),
            4 => __('Lieu & Équipe', 'mj-member'),
        ];
        foreach ($step_labels as $sn => $sl) {
            $cls = 'ccm__step-item' . ($sn === 1 ? ' is-active' : '');
            echo '<button type="button" class="' . esc_attr($cls) . '" data-ccm-step-dot="' . $sn . '">';
            echo '<span class="ccm__step-num">' . $sn . '</span>';
            echo '<span class="ccm__step-text">' . esc_html($sl) . '</span>';
            echo '</button>';
        }
        echo '</nav>';

        // ── Feedback ──
        echo '<div class="ccm__feedback" data-ccm-feedback hidden></div>';

        // ── Step 1 – Identity ──
        echo '<div class="ccm__panel is-active" data-ccm-panel="1">';
        echo '<div class="ccm__emoji-zone">';
        echo '<div class="ccm__emoji-mount mj-form-field--emoji" data-ccm-emoji-mount></div>';
        echo '</div>';
        echo '<div class="ccm__field">';
        echo '<label class="ccm__label" for="ccm-title-input-' . esc_attr($instance_id) . '">' . esc_html__('Nom de l\'événement', 'mj-member') . '</label>';
        echo '<input class="ccm__input" type="text" id="ccm-title-input-' . esc_attr($instance_id) . '" data-ccm-title maxlength="190" placeholder="' . esc_attr__('Ex : Atelier DJ', 'mj-member') . '" />';
        echo '</div>';
        echo '<div class="ccm__field">';
        echo '<label class="ccm__label">' . esc_html__('Type d\'activité', 'mj-member') . '</label>';
        echo '<div class="ccm__type-grid" data-ccm-type-grid></div>';
        echo '<input type="hidden" data-ccm-type value="" />';
        echo '</div>';

        // Cover image upload
        echo '<div class="ccm__field">';
        echo '<label class="ccm__label">' . esc_html__('Image de couverture', 'mj-member') . '</label>';
        echo '<div class="ccm__cover-upload" data-ccm-cover-zone>';
        echo '<input type="file" accept="image/*" data-ccm-cover-input class="ccm__cover-input" />';
        echo '<div class="ccm__cover-placeholder" data-ccm-cover-placeholder>';
        echo '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';
        echo '<span>' . esc_html__('Glisser une image ou cliquer pour choisir', 'mj-member') . '</span>';
        echo '</div>';
        echo '<div class="ccm__cover-preview" data-ccm-cover-preview hidden>';
        echo '<img data-ccm-cover-img alt="" />';
        echo '<button type="button" class="ccm__cover-remove" data-ccm-cover-remove title="' . esc_attr__('Supprimer', 'mj-member') . '">&times;</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // end step 1

        // ── Step 2 – Date & time ──
        echo '<div class="ccm__panel" data-ccm-panel="2" hidden>';
        echo '<div class="ccm__date-display" data-ccm-date-display hidden></div>';
        echo '<div class="ccm__field">';
        echo '<label class="ccm__label" for="ccm-date-input-' . esc_attr($instance_id) . '">' . esc_html__('Date', 'mj-member') . '</label>';
        echo '<input class="ccm__input ccm__input--date" type="date" id="ccm-date-input-' . esc_attr($instance_id) . '" data-ccm-date />';
        echo '</div>';
        echo '<div class="ccm__time-row">';
        echo '<div class="ccm__field ccm__field--half">';
        echo '<label class="ccm__label">' . esc_html__('Début', 'mj-member') . '</label>';
        echo '<input class="ccm__input" type="time" data-ccm-start value="14:00" />';
        echo '</div>';
        echo '<span class="ccm__time-sep">&rarr;</span>';
        echo '<div class="ccm__field ccm__field--half">';
        echo '<label class="ccm__label">' . esc_html__('Fin', 'mj-member') . '</label>';
        echo '<input class="ccm__input" type="time" data-ccm-end value="17:00" />';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // end step 2

        // ── Step 3 – Options & Confirmation ──
        echo '<div class="ccm__panel" data-ccm-panel="3" hidden>';

        echo '<div class="ccm__summary-card" data-ccm-summary></div>';

        echo '<div class="ccm__options-section">';
        echo '<h4 class="ccm__options-title">' . esc_html__('Options d\'inscription', 'mj-member') . '</h4>';

        // Toggle rows
        $toggles = [
            ['attr' => 'data-ccm-occurrence-choice', 'label' => __('Les membres choisissent leurs occurrences', 'mj-member'), 'hint' => __('Permet aux membres de s\'inscrire à certaines dates plutôt qu\'à l\'événement entier.', 'mj-member')],
            ['attr' => 'data-ccm-require-validation', 'label' => __('Validation des inscriptions requise', 'mj-member'), 'hint' => __('Les inscriptions doivent être approuvées par un animateur avant confirmation.', 'mj-member')],
            ['attr' => 'data-ccm-free-participation', 'label' => __('Participation libre (sans inscription)', 'mj-member'), 'hint' => __('Les membres peuvent participer sans s\'inscrire au préalable.', 'mj-member')],
            ['attr' => 'data-ccm-show-all-members', 'label' => __('Afficher tous les membres en présence', 'mj-member'), 'hint' => __('Rend visible la liste complète des participants inscrits à l\'événement.', 'mj-member')],
        ];
        foreach ($toggles as $toggle) {
            echo '<label class="ccm__toggle-row">';
            echo '<span class="ccm__toggle-copy">';
            echo '<span class="ccm__toggle-text">' . esc_html($toggle['label']) . '</span>';
            echo '<span class="ccm__toggle-hint">' . esc_html($toggle['hint']) . '</span>';
            echo '</span>';
            echo '<span class="ccm__toggle"><input type="checkbox" ' . $toggle['attr'] . ' /><span class="ccm__toggle-slider"></span></span>';
            echo '</label>';
        }

        echo '<div class="ccm__num-row">';
        $num_fields = [
            ['attr' => 'data-ccm-capacity', 'label' => __('Places max', 'mj-member'), 'min' => 0, 'value' => 0, 'step' => ''],
            ['attr' => 'data-ccm-price', 'label' => __('Prix (€)', 'mj-member'), 'min' => 0, 'value' => 0, 'step' => '0.01'],
            ['attr' => 'data-ccm-age-min', 'label' => __('Âge min', 'mj-member'), 'min' => 0, 'value' => 12, 'step' => ''],
            ['attr' => 'data-ccm-age-max', 'label' => __('Âge max', 'mj-member'), 'min' => 0, 'value' => 26, 'step' => ''],
        ];
        foreach ($num_fields as $nf) {
            echo '<div class="ccm__field ccm__field--compact">';
            echo '<label class="ccm__label">' . esc_html($nf['label']) . '</label>';
            $step_attr = $nf['step'] !== '' ? ' step="' . esc_attr($nf['step']) . '"' : '';
            echo '<input class="ccm__input ccm__input--sm" type="number" ' . $nf['attr'] . ' min="' . (int) $nf['min'] . '" value="' . (int) $nf['value'] . '"' . $step_attr . ' />';
            echo '</div>';
        }
        echo '</div>';

        echo '<p class="ccm__hint">' . esc_html__('0 = illimité. Ces options sont modifiables après création.', 'mj-member') . '</p>';
        echo '</div>'; // options-section
        echo '</div>'; // end step 3

        // ── Step 4 – Lieu, Équipe & Statut ──
        echo '<div class="ccm__panel" data-ccm-panel="4" hidden>';

        echo '<div class="ccm__field">';
        echo '<label class="ccm__label">' . esc_html__('Statut', 'mj-member') . '</label>';
        echo '<select class="ccm__input ccm__select" data-ccm-status>';
        $status_labels = MjEvents::get_status_labels();
        foreach ($status_labels as $sval => $slbl) {
            $selected = ($sval === 'brouillon') ? ' selected' : '';
            echo '<option value="' . esc_attr($sval) . '"' . $selected . '>' . esc_html($slbl) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="ccm__field">';
        echo '<label class="ccm__label">' . esc_html__('Lieu', 'mj-member') . '</label>';
        echo '<select class="ccm__input ccm__select" data-ccm-location>';
        echo '<option value="0">' . esc_html__('— Aucun lieu —', 'mj-member') . '</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="ccm__field">';
        echo '<label class="ccm__label">' . esc_html__('Équipe responsable', 'mj-member') . '</label>';
        echo '<p class="ccm__field-hint">' . esc_html__('Sélectionnez les animateurs référents pour cet événement.', 'mj-member') . '</p>';
        echo '<div class="ccm__team-grid" data-ccm-team-grid></div>';
        echo '</div>';

        echo '</div>'; // end step 4

        // ── Actions footer ──
        echo '<div class="ccm__actions">';
        echo '<div class="ccm__actions-left">';
        echo '<button type="button" class="ccm__btn ccm__btn--ghost" data-ccm-prev hidden>';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>';
        echo ' ' . esc_html__('Retour', 'mj-member');
        echo '</button>';
        echo '</div>';
        echo '<div class="ccm__actions-center">';
        echo '<button type="button" class="ccm__btn ccm__btn--secondary" data-ccm-only hidden>';
        echo '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.5 4.5l-7 7L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        echo ' ' . esc_html__('Créer', 'mj-member');
        echo '</button>';
        echo '<button type="button" class="ccm__btn ccm__btn--accent" data-ccm-submit hidden>';
        echo '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M14 2.5l-8.5 8.5L2 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 6v5.5a1 1 0 01-1 1H3a1 1 0 01-1-1v-7a1 1 0 011-1h5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        echo ' ' . esc_html__('Créer et éditer', 'mj-member');
        echo '</button>';
        echo '</div>';
        echo '<div class="ccm__actions-right">';
        echo '<button type="button" class="ccm__btn ccm__btn--primary" data-ccm-next>';
        echo esc_html__('Continuer', 'mj-member') . ' ';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
        echo '</button>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // dialog
        echo '</div>'; // modal
    }

    /**
     * Build the JS configuration object needed by MjCreateEventModal.init().
     *
     * @return array<string, mixed>
     */
    public static function buildConfig(): array
    {
        $config = [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'createNonce' => wp_create_nonce('mj-events-manager'),
            'createUrl'   => home_url('/mon-compte/gestionnaire/'),
            'createTypes' => MjEvents::get_type_labels(),
            'createTypeColors' => MjEvents::get_type_colors(),
        ];

        // Locations
        $locations = [];
        if (class_exists(MjEventLocations::class)) {
            $all = MjEventLocations::get_all(['orderby' => 'name', 'order' => 'ASC']);
            if (is_array($all)) {
                foreach ($all as $loc) {
                    $id = (int) ($loc->id ?? 0);
                    if ($id <= 0) continue;
                    $name = (string) ($loc->name ?? '');
                    $city = (string) ($loc->city ?? '');
                    $label = $name;
                    if ($city !== '') $label .= ' (' . $city . ')';
                    $locations[$id] = $label;
                }
            }
        }
        $config['createLocations'] = $locations;

        // Animateurs
        $animateurs = [];
        if (class_exists(MjMembers::class) && class_exists(MjRoles::class)) {
            $filters = ['roles' => [MjRoles::ANIMATEUR, MjRoles::COORDINATEUR]];
            $list = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', $filters);
            if (is_array($list)) {
                foreach ($list as $anim) {
                    if (!is_object($anim) || !isset($anim->id)) continue;
                    $id = (int) $anim->id;
                    if ($id <= 0) continue;
                    $first = $anim->first_name ?? '';
                    $last = $anim->last_name ?? '';
                    $display = trim($first . ' ' . $last);
                    if ($display === '') $display = 'Animateur #' . $id;
                    $animateurs[$id] = $display;
                }
            }
        }
        $config['createAnimateurs'] = $animateurs;

        return $config;
    }
}
