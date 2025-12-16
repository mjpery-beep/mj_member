<?php

use Mj\Member\Classes\Front\EventFormController;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormView;

if (!defined('ABSPATH')) {
    exit;
}

$controller = new EventFormController();
$context = $controller->buildContext();
$form = $context['form'];
/** @var FormView $formView */
$formView = $context['form_view'];
$errors = $form->getErrors(true);
$actionUrl = isset($args['action_url']) ? esc_url($args['action_url']) : esc_url(admin_url('admin-post.php'));
?>
<div class="mj-member-event-form">
    <?php if ($errors instanceof FormErrorIterator && $errors->count() > 0) : ?>
        <div class="mj-member-event-form__errors">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?php echo esc_html($error->getMessage()); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form class="mj-member-event-form__form" method="post" action="<?php echo $actionUrl; ?>">
        <?php wp_nonce_field('mj_event_front_form', 'mj_event_front_nonce'); ?>
        <?php if (!empty($args['action'])) : ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($args['action']); ?>" />
        <?php endif; ?>
        <div class="mj-member-event-form__grid">
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-title"><?php esc_html_e('Titre', 'mj-member'); ?></label>
                <input type="text" id="mj-front-event-title" name="<?php echo esc_attr($formView['event_title']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_title']->vars['value']); ?>" required />
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-type"><?php esc_html_e('Type', 'mj-member'); ?></label>
                <select id="mj-front-event-type" name="<?php echo esc_attr($formView['event_type']->vars['full_name']); ?>">
                    <?php foreach ($formView['event_type']->vars['choices'] as $choice) : ?>
                        <option value="<?php echo esc_attr($choice->value); ?>"<?php selected($choice->value, $formView['event_type']->vars['value']); ?>><?php echo esc_html($choice->label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-color"><?php esc_html_e('Couleur', 'mj-member'); ?></label>
                <input type="color" id="mj-front-event-color" name="<?php echo esc_attr($formView['event_accent_color']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_accent_color']->vars['value']); ?>" />
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-status"><?php esc_html_e('Statut', 'mj-member'); ?></label>
                <select id="mj-front-event-status" name="<?php echo esc_attr($formView['event_status']->vars['full_name']); ?>">
                    <?php foreach ($formView['event_status']->vars['choices'] as $choice) : ?>
                        <option value="<?php echo esc_attr($choice->value); ?>"<?php selected($choice->value, $formView['event_status']->vars['value']); ?>><?php echo esc_html($choice->label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-location"><?php esc_html_e('Lieu', 'mj-member'); ?></label>
                <select id="mj-front-event-location" name="<?php echo esc_attr($formView['event_location_id']->vars['full_name']); ?>">
                    <option value=""><?php esc_html_e('Sélectionnez un lieu', 'mj-member'); ?></option>
                    <?php foreach ($formView['event_location_id']->vars['choices'] as $choice) : ?>
                        <option value="<?php echo esc_attr($choice->value); ?>"<?php selected($choice->value, $formView['event_location_id']->vars['value']); ?>><?php echo esc_html($choice->label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-date"><?php esc_html_e('Date', 'mj-member'); ?></label>
                <input type="date" id="mj-front-event-date" name="<?php echo esc_attr($formView['event_fixed_date']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_fixed_date']->vars['value']); ?>" />
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-start"><?php esc_html_e('Heure de début', 'mj-member'); ?></label>
                <input type="time" id="mj-front-event-start" name="<?php echo esc_attr($formView['event_fixed_start_time']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_fixed_start_time']->vars['value']); ?>" />
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-end"><?php esc_html_e('Heure de fin', 'mj-member'); ?></label>
                <input type="time" id="mj-front-event-end" name="<?php echo esc_attr($formView['event_fixed_end_time']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_fixed_end_time']->vars['value']); ?>" />
            </div>
            <div class="mj-member-event-form__field mj-member-event-form__field--wide">
                <label class="mj-member-event-form__label" for="mj-front-event-description"><?php esc_html_e('Description', 'mj-member'); ?></label>
                <textarea id="mj-front-event-description" name="<?php echo esc_attr($formView['event_description']->vars['full_name']); ?>" rows="6"><?php echo esc_textarea($formView['event_description']->vars['value']); ?></textarea>
            </div>
            <div class="mj-member-event-form__field">
                <label class="mj-member-event-form__label" for="mj-front-event-price"><?php esc_html_e('Tarif (€)', 'mj-member'); ?></label>
                <input type="number" step="0.01" min="0" id="mj-front-event-price" name="<?php echo esc_attr($formView['event_price']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_price']->vars['value']); ?>" />
            </div>
        </div>
        <input type="hidden" name="<?php echo esc_attr($formView['event_schedule_mode']->vars['full_name']); ?>" value="fixed" />
        <input type="hidden" name="<?php echo esc_attr($formView['event_date_start']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_date_start']->vars['value']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($formView['event_date_end']->vars['full_name']); ?>" value="<?php echo esc_attr($formView['event_date_end']->vars['value']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($formView['event_occurrence_selection_mode']->vars['full_name']); ?>" value="member_choice" />
        <div class="mj-member-event-form__actions">
            <button type="submit" class="mj-member-event-form__submit"><?php esc_html_e('Enregistrer l\'événement', 'mj-member'); ?></button>
        </div>
    </form>
</div>
