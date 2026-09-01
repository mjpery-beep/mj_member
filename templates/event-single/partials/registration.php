<?php
if (!defined('ABSPATH')) {
    exit;
}


$registration_ajax = isset($registration_view['ajax']) && is_array($registration_view['ajax']) ? $registration_view['ajax'] : array();
$registration_ajax_url = isset($registration_ajax['url']) ? (string) $registration_ajax['url'] : admin_url('admin-ajax.php');
$registration_ajax_nonce = isset($registration_ajax['nonce']) ? (string) $registration_ajax['nonce'] : wp_create_nonce('mj-member-event-register');

$registration_is_free_participation = !empty($registration_view['is_free_participation']);
if ($registration_is_free_participation) {
    return;
}

$registration_is_open = !empty($registration_view['is_open']);
$registration_show_price = !empty($registration_view['show_price']);
$registration_price_candidate = isset($registration_view['price_display']) ? (string) $registration_view['price_display'] : '';
$registration_has_interactive = !empty($registration_view['has_interactive']);
$registration_requires_login = !empty($registration_view['requires_login']);
$registration_form_participants = isset($registration_view['form_participants']) && is_array($registration_view['form_participants'])
    ? $registration_view['form_participants']
    : array();
$registration_all_registered = !empty($registration_view['all_registered']);
$registration_has_participants = !empty($registration_view['has_participants']);
$registration_config_json = isset($registration_view['config_json']) ? (string) $registration_view['config_json'] : '';
$registration_can_manage_children = !empty($registration_view['can_manage_children']);
$registration_note_max_length = isset($registration_view['note_max_length']) ? max(0, (int) $registration_view['note_max_length']) : 400;
$event_id = isset($registration_view['event_id']) ? (int) $registration_view['event_id'] : 0;
$registration_form_available_count = isset($registration_view['form_available_count']) ? (int) $registration_view['form_available_count'] : 0;
$registration_cta_label = isset($registration_view['cta_label']) && $registration_view['cta_label'] !== ''
    ? (string) $registration_view['cta_label']
    : __('S\'inscrire', 'mj-member');
$registration_cta_registered = isset($registration_view['cta_registered_label']) ? (string) $registration_view['cta_registered_label'] : '';
$registration_url = isset($registration_view['url']) ? (string) $registration_view['url'] : '';
$registration_payment_required = !empty($registration_view['payment_required']);
$registration_has_reservations = !empty($registration_view['has_reservations']);
$registration_reservations = isset($registration_view['reservations']) && is_array($registration_view['reservations'])
    ? $registration_view['reservations']
    : array();
$event_capacity_total = isset($registration_view['capacity_total']) ? (int) $registration_view['capacity_total'] : 0;
?>
<section class="mj-member-event-single__card mj-member-event-single__registration">
    <div class="mj-member-event-single__registration-header">
        <h3><?php echo esc_html__('Mes réservations', 'mj-member'); ?></h3>
        <span class="mj-member-event-single__registration-status <?php echo $registration_is_open ? 'is-open' : 'is-closed'; ?>"><?php echo $registration_is_open ? esc_html__('Ouvertes', 'mj-member') : esc_html__('Clôturées', 'mj-member'); ?></span>
    </div>
    <?php if ($registration_show_price) : ?>
    <p class="mj-member-event-single__registration-price">
        <span class="mj-member-event-single__registration-price-label"><?php echo esc_html__('Tarif', 'mj-member'); ?></span>
        <span class="mj-member-event-single__registration-price-value"><?php echo esc_html($registration_price_candidate); ?></span>
    </p>
    <?php endif; ?>

    <div class="mj-member-event-single__registration-body">
        <div class="mj-member-event-single__registration-actions">
            <?php if ($registration_has_interactive) : ?>
            <div class="mj-member-event-single__registration-interactive">
                <?php if ($registration_requires_login) : ?>
                <p class="mj-member-event-single__registration-note"><?php echo esc_html__('Connecte-toi pour continuer.', 'mj-member'); ?></p>
                <button type="button" class="mj-member-event-single__registration-login" data-mj-event-open-login><?php echo esc_html__('Se connecter', 'mj-member'); ?></button>
                <?php elseif (!empty($registration_form_participants)) : ?>
                <?php
                $registration_note_max = $registration_note_max_length > 0 ? (int) $registration_note_max_length : 400;
                $note_field_id = 'mj-member-event-note-' . (int) $event_id;
                $first_available_selected = false;
                ?>
                <?php if ($registration_all_registered && $registration_has_participants) : ?>
                <p class="mj-member-event-single__registration-note"><?php echo esc_html__('Tous vos profils disponibles sont déjà inscrits pour cet événement.', 'mj-member'); ?></p>
                <?php endif; ?>
                <form class="mj-member-event-single__registration-form" data-mj-event-registration data-event-id="<?php echo esc_attr((int) $event_id); ?>" data-note-max="<?php echo esc_attr($registration_note_max); ?>" data-ajax-url="<?php echo esc_url($registration_ajax_url); ?>" data-ajax-nonce="<?php echo esc_attr($registration_ajax_nonce); ?>"<?php if ($registration_config_json !== '') : ?> data-registration-config="<?php echo esc_attr($registration_config_json); ?>"<?php endif; ?>>
                    <?php
                    $registration_can_manage_children = !empty($registration_can_manage_children);
                    $registration_fieldset_label = esc_html__('Choisis la personne à inscrire', 'mj-member');
                    $registration_fieldset_label_attr = esc_attr__('Choisis la personne à inscrire', 'mj-member');
                    $registration_member_entries = array();
                    $registration_primary_participant = null;
                    $registration_participant_count = 0;
                    $first_available_selected = false;
                    if (!empty($registration_form_participants) && is_array($registration_form_participants)) {
                        foreach ($registration_form_participants as $participant_item) {
                            $participant_id = isset($participant_item['id']) ? (int) $participant_item['id'] : 0;
                            if ($participant_id <= 0) {
                                continue;
                            }
                            $participant_name = isset($participant_item['name']) ? (string) $participant_item['name'] : ('#' . $participant_id);
                            $participant_status_label = isset($participant_item['status_label']) ? (string) $participant_item['status_label'] : '';
                            $participant_status_class = isset($participant_item['status_class']) ? (string) $participant_item['status_class'] : '';
                            $participant_is_registered = !empty($participant_item['is_registered']);
                            $participant_eligible = !array_key_exists('eligible', $participant_item) || !empty($participant_item['eligible']);
                            $participant_reasons = array();
                            if (!empty($participant_item['ineligible_reasons']) && is_array($participant_item['ineligible_reasons'])) {
                                $participant_reasons = $participant_item['ineligible_reasons'];
                            } elseif (!empty($participant_item['ineligibleReasons']) && is_array($participant_item['ineligibleReasons'])) {
                                $participant_reasons = $participant_item['ineligibleReasons'];
                            }
                            if (!empty($participant_reasons)) {
                                $participant_reasons = array_values(array_map('sanitize_text_field', $participant_reasons));
                            }
                            $participant_eligibility_label = !$participant_eligible
                                ? (!empty($participant_item['eligibility_label']) ? (string) $participant_item['eligibility_label'] : __('Conditions non respectées', 'mj-member'))
                                : '';
                            if (!$participant_eligible) {
                                $participant_status_class = trim($participant_status_class . ' is-ineligible');
                            }
                            $participant_status_text = '';
                            if ($participant_is_registered && $participant_status_label !== '') {
                                $participant_status_text = $participant_status_label;
                            } elseif (!$participant_eligible && $participant_eligibility_label !== '') {
                                $participant_status_text = $participant_eligibility_label;
                            }
                            $input_disabled = $participant_is_registered || !$participant_eligible;
                            $should_check = false;
                            if (!$participant_is_registered && $participant_eligible && !$first_available_selected) {
                                $should_check = true;
                                $first_available_selected = true;
                            }

                            $registration_member_entries[] = array(
                                'id' => $participant_id,
                                'name' => $participant_name,
                                'status_class' => $participant_status_class,
                                'status_text' => $participant_status_text,
                                'status_label' => $participant_status_label,
                                'is_registered' => $participant_is_registered,
                                'eligible' => $participant_eligible,
                                'reasons' => $participant_reasons,
                                'input_disabled' => $input_disabled,
                                'should_check' => $should_check,
                            );

                            if (!$registration_primary_participant && $should_check) {
                                $registration_primary_participant = array(
                                    'id' => $participant_id,
                                    'name' => $participant_name,
                                );
                            }
                        }
                    }
                    $registration_participant_count = count($registration_member_entries);
                    $registration_show_participant_selector = $registration_participant_count > 1;
                    if (!$registration_primary_participant && !empty($registration_member_entries)) {
                        $fallback_entry = $registration_member_entries[0];
                        if (!empty($fallback_entry['id'])) {
                            $registration_primary_participant = array(
                                'id' => (int) $fallback_entry['id'],
                                'name' => isset($fallback_entry['name']) ? (string) $fallback_entry['name'] : '',
                            );
                        }
                    }
                    ?>
                    <fieldset class="mj-member-event-single__registration-fieldset" aria-label="<?php echo $registration_fieldset_label_attr; ?>"<?php if (!$registration_show_participant_selector) : ?> hidden<?php endif; ?>>
                        <?php if ($registration_show_participant_selector) : ?>
                        <legend><?php echo $registration_fieldset_label; ?></legend>
                        <?php endif; ?>
                        <ul class="mj-member-event-single__registration-members" data-mj-event-members>
                            <?php foreach ($registration_member_entries as $participant_entry) :
                                $participant_id = isset($participant_entry['id']) ? (int) $participant_entry['id'] : 0;
                                if ($participant_id <= 0) {
                                    continue;
                                }
                                $participant_name = isset($participant_entry['name']) ? (string) $participant_entry['name'] : ('#' . $participant_id);
                                $participant_is_registered = !empty($participant_entry['is_registered']);
                                $participant_eligible = !empty($participant_entry['eligible']);
                                $participant_reasons = !empty($participant_entry['reasons']) && is_array($participant_entry['reasons']) ? $participant_entry['reasons'] : array();
                                $participant_status_text = isset($participant_entry['status_text']) ? (string) $participant_entry['status_text'] : '';
                                $participant_status_class = isset($participant_entry['status_class']) ? (string) $participant_entry['status_class'] : '';
                                $input_disabled = !empty($participant_entry['input_disabled']);
                                $should_check = !empty($participant_entry['should_check']);
                                ?>
                            <li class="mj-member-event-single__registration-member<?php echo $participant_is_registered ? ' is-registered' : ''; ?><?php echo !$participant_eligible ? ' is-ineligible' : ''; ?>" data-mj-event-member data-member-id="<?php echo esc_attr($participant_id); ?>" data-eligible="<?php echo $participant_eligible ? '1' : '0'; ?>">
                                <label class="mj-member-event-single__registration-member-label">
                                    <input type="radio" name="participant" value="<?php echo esc_attr($participant_id); ?>" <?php echo $input_disabled ? 'disabled' : ''; ?><?php echo $should_check ? ' checked' : ''; ?> />
                                    <span class="mj-member-event-single__registration-member-name"><?php echo esc_html($participant_name); ?></span>
                                </label>
                                <span class="mj-member-event-single__registration-member-status<?php echo $participant_status_class !== '' ? ' ' . esc_attr($participant_status_class) : ''; ?>" data-role="status">
                                    <?php if ($participant_status_text !== '') : ?>
                                    <?php echo esc_html($participant_status_text); ?>
                                    <?php endif; ?>
                                </span>
                                <?php if (!$participant_eligible && !empty($participant_reasons)) : ?>
                                <ul class="mj-member-event-single__registration-member-reasons">
                                    <?php foreach ($participant_reasons as $participant_reason) : ?>
                                    <li><?php echo esc_html($participant_reason); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mj-member-event-single__registration-members-empty" data-mj-event-members-empty<?php if ($registration_form_available_count > 0) : ?> hidden<?php endif; ?>><?php echo $registration_all_registered ? esc_html__('Tous vos profils sont déjà inscrits.', 'mj-member') : esc_html__('Aucun profil éligible n’est disponible pour cette inscription.', 'mj-member'); ?></p>
                    </fieldset>
                    <?php if (!$registration_show_participant_selector && $registration_primary_participant && !empty($registration_primary_participant['name'])) : ?>
                    <p class="mj-member-event-single__registration-member-summary"><?php echo esc_html(sprintf(__('Inscription pour %s', 'mj-member'), $registration_primary_participant['name'])); ?></p>
                    <?php endif; ?>
                    <div class="mj-member-event-single__registration-note">
                        <label for="<?php echo esc_attr($note_field_id); ?>"><?php echo esc_html__('Message pour l’équipe (optionnel)', 'mj-member'); ?></label>
                        <textarea id="<?php echo esc_attr($note_field_id); ?>" name="note" maxlength="<?php echo esc_attr($registration_note_max); ?>" placeholder="<?php echo esc_attr__('Précise une allergie, une contrainte horaire, ...', 'mj-member'); ?>"></textarea>
                    </div>
                    <?php
                    $confirmation_field_id = 'mj-member-event-confirmation-' . (int) $event_id;
                    $confirmation_label_payment = __('Je confirme ma participation et je finaliserai le paiement via la page suivante.', 'mj-member');
                    $confirmation_label_free = __('Je confirme ma participation et le récapitulatif reçu me convient.', 'mj-member');
                    $confirmation_hint_payment = __('La page de paiement s’ouvrira automatiquement après validation. Tu recevras aussi le lien par email.', 'mj-member');
                    $confirmation_hint_free = __('Tu recevras un email récapitulatif dès la validation.', 'mj-member');
                    ?>
                    <div class="mj-member-event-single__registration-confirmation" data-mj-event-confirmation>
                        <label class="mj-member-event-single__registration-confirmation-checkbox" for="<?php echo esc_attr($confirmation_field_id); ?>">
                            <input type="checkbox" id="<?php echo esc_attr($confirmation_field_id); ?>" name="registration_confirmation" value="1" data-mj-event-confirmation-checkbox required />
                            <span><?php echo esc_html($registration_payment_required ? $confirmation_label_payment : $confirmation_label_free); ?></span>
                        </label>
                        <p class="mj-member-event-single__registration-confirmation-hint"><?php echo esc_html($registration_payment_required ? $confirmation_hint_payment : $confirmation_hint_free); ?></p>
                    </div>
                    <button type="submit" class="mj-member-event-single__registration-submit" data-mj-event-registration-submit<?php if ($registration_form_available_count === 0) : ?> disabled<?php endif; ?>><?php echo esc_html__('Confirmer mon inscription', 'mj-member'); ?></button>
                    <div class="mj-member-event-single__registration-feedback" data-mj-event-registration-feedback aria-live="polite"></div>
                </form>
                <?php if ($registration_url !== '') : ?>
                <noscript>
                    <div class="mj-member-event-single__registration-links">
                        <a class="mj-member-event-single__registration-link" href="<?php echo esc_url($registration_url); ?>"><?php echo esc_html($registration_cta_label); ?></a>
                    </div>
                </noscript>
                <?php endif; ?>
                <?php else : ?>
                <p class="mj-member-event-single__registration-note"><?php echo esc_html__('Aucun profil éligible n’est disponible pour cette inscription.', 'mj-member'); ?></p>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div class="mj-member-event-single__registration-static">
                <?php if ($registration_is_open && $registration_url !== '') : ?>
                <div class="mj-member-event-single__registration-links">
                    <a class="mj-member-event-single__registration-link" href="<?php echo esc_url($registration_url); ?>"><?php echo esc_html($registration_cta_label); ?></a>
                </div>
                <?php elseif (!$registration_is_open) : ?>
                <p class="mj-member-event-single__registration-note"><?php echo esc_html__('Les inscriptions sont cloturées pour cet événement.', 'mj-member'); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (is_user_logged_in()) : ?>
            <div class="mj-member-event-single__registration-divider" aria-hidden="true"></div>
            <div
                class="mj-member-event-single__registration-reservations<?php echo $registration_has_reservations ? '' : ' is-empty'; ?>"
                data-mj-event-reservations
                data-event-id="<?php echo esc_attr((int) $event_id); ?>"
                data-has-reservations="<?php echo $registration_has_reservations ? '1' : '0'; ?>"
            >
                <div class="mj-member-event-single__reservations-feedback" data-mj-event-reservations-feedback aria-live="polite"></div>
                <ul class="mj-member-event-single__reservations-list" data-mj-event-reservations-list>
                    <?php if ($registration_has_reservations) : ?>
                        <?php foreach ($registration_reservations as $reservation_entry) : ?>
                            <li
                                class="mj-member-event-single__reservation"
                                data-mj-event-reservation
                                data-member-id="<?php echo esc_attr(isset($reservation_entry['member_id']) ? (int) $reservation_entry['member_id'] : 0); ?>"
                                data-registration-id="<?php echo esc_attr(isset($reservation_entry['registration_id']) ? (int) $reservation_entry['registration_id'] : 0); ?>"
                                <?php echo !empty($reservation_entry['status_key']) ? ' data-status-key="' . esc_attr((string) $reservation_entry['status_key']) . '"' : ''; ?>
                            >
                                <div class="mj-member-event-single__reservation-header">
                                    <div class="mj-member-event-single__reservation-main">
                                        <p class="mj-member-event-single__reservation-title"><?php echo esc_html($reservation_entry['name']); ?></p>
                                        <?php if (!empty($reservation_entry['status_label'])) : ?>
                                            <span class="mj-member-event-single__reservation-status<?php echo $reservation_entry['status_class'] !== '' ? ' ' . esc_attr($reservation_entry['status_class']) : ''; ?>"><?php echo esc_html($reservation_entry['status_label']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($reservation_entry['can_cancel'])) : ?>
                                        <button
                                            type="button"
                                            class="mj-member-event-single__reservation-cancel"
                                            data-mj-event-cancel
                                            data-member-id="<?php echo esc_attr((int) $reservation_entry['member_id']); ?>"
                                            data-registration-id="<?php echo esc_attr((int) $reservation_entry['registration_id']); ?>"
                                        ><?php echo esc_html__('Se désinscrire', 'mj-member'); ?></button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($reservation_entry['created_label'])) : ?>
                                <p class="mj-member-event-single__reservation-meta"><?php printf(
                                    esc_html__('Réservé le %s', 'mj-member'),
                                    esc_html($reservation_entry['created_label'])
                                ); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($reservation_entry['occurrences'])) : ?>
                                <ul class="mj-member-event-single__reservation-occurrences">
                                    <?php foreach ($reservation_entry['occurrences'] as $occurrence_label) : ?>
                                    <li class="mj-member-event-single__reservation-occurrence"><?php echo esc_html($occurrence_label); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <p class="mj-member-event-single__reservations-empty" data-mj-event-reservations-empty<?php if ($registration_has_reservations) : ?> hidden<?php endif; ?>><?php echo esc_html__('Tu n\'as pas encore de réservation pour cet événement.', 'mj-member'); ?></p>
            </div>
        <?php else : ?>
            <div class="mj-member-event-single__registration-divider" aria-hidden="true"></div>
            <div class="mj-member-event-single__registration-reminder">
                <p><?php echo esc_html__('Connecte-toi pour prévisualiser et gérer tes réservations.', 'mj-member'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($event_capacity_total > 0) : ?>
        <div class="mj-member-event-single__registration-divider" aria-hidden="true"></div>
        <ul class="mj-member-event-single__registration-meta">
            <li>
                <strong><?php echo esc_html__('Places max', 'mj-member'); ?></strong>
                <span><?php echo esc_html(sprintf(_n('%d place', '%d places', $event_capacity_total, 'mj-member'), $event_capacity_total)); ?></span>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</section>
