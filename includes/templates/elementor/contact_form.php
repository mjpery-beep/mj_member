<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('contact-form');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();

$title = isset($template_data['title']) ? $template_data['title'] : '';
$description = isset($template_data['description']) ? $template_data['description'] : '';
$recipients = isset($template_data['recipients']) && is_array($template_data['recipients']) ? $template_data['recipients'] : mj_member_get_contact_recipient_options();
$submit_label = isset($template_data['submit_label']) ? $template_data['submit_label'] : __('Envoyer le message', 'mj-member');
$config = isset($template_data['config']) && is_array($template_data['config']) ? $template_data['config'] : array();
$prefill = isset($template_data['prefill']) && is_array($template_data['prefill']) ? $template_data['prefill'] : array();

$prefill_name = isset($prefill['name']) ? (string) $prefill['name'] : '';
$prefill_email = isset($prefill['email']) ? (string) $prefill['email'] : '';
$prefill_member_id = isset($prefill['member_id']) ? (int) $prefill['member_id'] : 0;
$prefill_locked = !empty($prefill['locked']);
$readonly_name_note_id = '';
$readonly_email_note_id = '';

$show_member_card = !empty($template_data['show_member_card']);
$member_card = isset($template_data['member_card']) && is_array($template_data['member_card']) ? $template_data['member_card'] : array();
$member_card_available = $show_member_card && !empty($member_card['is_available']);

$member_card_name = isset($member_card['name']) ? (string) $member_card['name'] : '';
$member_card_email = isset($member_card['email']) ? (string) $member_card['email'] : '';
$member_card_role = isset($member_card['role_label']) ? (string) $member_card['role_label'] : '';
$member_card_bio = isset($member_card['bio']) ? (string) $member_card['bio'] : '';
$member_card_avatar = isset($member_card['avatar']) && is_array($member_card['avatar']) ? $member_card['avatar'] : array();
$member_card_avatar_url = isset($member_card_avatar['url']) ? (string) $member_card_avatar['url'] : '';
$member_card_avatar_alt = isset($member_card_avatar['alt']) ? (string) $member_card_avatar['alt'] : ($member_card_name !== '' ? sprintf(__('Photo de %s', 'mj-member'), $member_card_name) : __('Photo du membre', 'mj-member'));
$member_card_avatar_initials = isset($member_card_avatar['initials']) ? (string) $member_card_avatar['initials'] : '';
if ($member_card_avatar_initials === '') {
    if ($member_card_name !== '') {
        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $member_card_avatar_initials = mb_strtoupper(mb_substr($member_card_name, 0, 1, 'UTF-8'), 'UTF-8');
        } else {
            $member_card_avatar_initials = strtoupper(substr($member_card_name, 0, 1));
        }
    } else {
        $member_card_avatar_initials = 'MJ';
    }
}

if ($prefill_locked) {
    if (function_exists('wp_unique_id')) {
        $readonly_name_note_id = wp_unique_id('mj-contact-name-note-');
        $readonly_email_note_id = wp_unique_id('mj-contact-email-note-');
    } else {
        $readonly_name_note_id = uniqid('mj-contact-name-note-');
        $readonly_email_note_id = uniqid('mj-contact-email-note-');
    }
}

if (empty($recipients)) {
    if (function_exists('mj_member_contact_format_recipient_option')) {
        $recipients = array(
            mj_member_contact_format_recipient_option(array(
                'value' => MjContactMessages::TARGET_ALL,
                'label' => __('Toute l\'équipe MJ', 'mj-member'),
                'type' => MjContactMessages::TARGET_ALL,
                'reference' => 0,
                'role' => 'group',
                'role_label' => __('Maison de Jeune', 'mj-member'),
                'description' => __('Votre message sera transmis à l\'ensemble de l\'équipe.', 'mj-member'),
                'is_cover' => true,
                'cover_theme' => 'indigo',
            )),
        );
    } else {
        $recipients = array(
            array(
                'value' => MjContactMessages::TARGET_ALL,
                'label' => __('Toute l\'équipe MJ', 'mj-member'),
                'type' => MjContactMessages::TARGET_ALL,
                'reference' => 0,
                'role_label' => __('Maison de Jeune', 'mj-member'),
                'description' => __('Votre message sera transmis à l\'ensemble de l\'équipe.', 'mj-member'),
                'is_cover' => true,
                'cover_theme' => 'indigo',
            ),
        );
    }
}

$general_option = null;
$individual_recipients = array();

foreach ($recipients as $recipient) {
    $value = isset($recipient['value']) ? (string) $recipient['value'] : '';
    if ($value === MjContactMessages::TARGET_ALL && $general_option === null) {
        $general_option = $recipient;
        continue;
    }

    $individual_recipients[] = $recipient;
}

$general_value = $general_option !== null && isset($general_option['value']) ? (string) $general_option['value'] : '';
$default_selected_value = $general_value;
if ($default_selected_value === '' && !empty($individual_recipients)) {
    $first_individual = reset($individual_recipients);
    if ($first_individual && isset($first_individual['value'])) {
        $default_selected_value = (string) $first_individual['value'];
    }
}

$input_index = 0;

$config_json = wp_json_encode($config);
$current_url = esc_url(add_query_arg(array()));
?>
<div class="mj-contact-form" data-config="<?php echo esc_attr($config_json); ?>">
    <div class="mj-contact-form__surface">
        <?php if ($title !== '' || $description !== '') : ?>
            <div class="mj-contact-form__header">
                <?php if ($title !== '') : ?>
                    <h2 class="mj-contact-form__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>

                <?php if ($description !== '') : ?>
                    <p class="mj-contact-form__description"><?php echo wp_kses_post($description); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($member_card_available) : ?>
            <aside class="mj-contact-form__member-card" aria-label="<?php esc_attr_e('Informations sur le membre connecté', 'mj-member'); ?>">
                <span class="mj-contact-form__member-badge"><?php esc_html_e('Connecté·e via votre compte MJ', 'mj-member'); ?></span>
                <div class="mj-contact-form__member-identity">
                    <span class="mj-contact-form__member-avatar">
                        <?php if ($member_card_avatar_url !== '') : ?>
                            <img src="<?php echo esc_url($member_card_avatar_url); ?>" alt="<?php echo esc_attr($member_card_avatar_alt); ?>" loading="lazy">
                        <?php else : ?>
                            <span class="mj-contact-form__member-initials"><?php echo esc_html($member_card_avatar_initials); ?></span>
                        <?php endif; ?>
                    </span>
                    <div class="mj-contact-form__member-texts">
                        <?php if ($member_card_name !== '') : ?>
                            <span class="mj-contact-form__member-name"><?php echo esc_html($member_card_name); ?></span>
                        <?php endif; ?>
                        <?php if ($member_card_role !== '') : ?>
                            <span class="mj-contact-form__member-role"><?php echo esc_html($member_card_role); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($member_card_bio !== '') : ?>
                    <p class="mj-contact-form__member-bio"><?php echo esc_html($member_card_bio); ?></p>
                <?php endif; ?>
                <?php if ($member_card_email !== '') : ?>
                    <p class="mj-contact-form__member-email"><?php echo esc_html($member_card_email); ?></p>
                <?php endif; ?>
            </aside>
        <?php endif; ?>

        <div class="mj-contact-form__layout">
            <div class="mj-contact-form__form-wrapper">
                <form class="mj-contact-form__form" method="post" novalidate data-general-value="<?php echo esc_attr($general_value); ?>">
        <input type="hidden" name="recipient" value="<?php echo esc_attr($default_selected_value); ?>" data-recipient-field>
        <?php if ($member_card_available) : ?>
            <input type="hidden" name="name" value="<?php echo esc_attr($prefill_name); ?>">
            <input type="hidden" name="email" value="<?php echo esc_attr($prefill_email); ?>">
        <?php endif; ?>
        <?php if (!$member_card_available) : ?>
            <div class="mj-contact-form__row">
                <label for="mj-contact-name" class="mj-contact-form__label"><?php esc_html_e('Nom complet', 'mj-member'); ?></label>
                <input
                    id="mj-contact-name"
                    class="mj-contact-form__input<?php echo $prefill_locked ? ' is-readonly' : ''; ?>"
                    type="text"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="<?php esc_attr_e('Votre nom', 'mj-member'); ?>"
                    value="<?php echo esc_attr($prefill_name); ?>"
                    <?php echo $prefill_locked ? 'readonly aria-readonly="true"' : ''; ?>
                    <?php echo ($prefill_locked && $readonly_name_note_id !== '') ? 'aria-describedby="' . esc_attr($readonly_name_note_id) . '"' : ''; ?>
                >
                <?php if ($prefill_locked && $readonly_name_note_id !== '') : ?>
                    <p id="<?php echo esc_attr($readonly_name_note_id); ?>" class="mj-contact-form__readonly-hint"><?php esc_html_e('Prérempli depuis votre profil MJ.', 'mj-member'); ?></p>
                <?php endif; ?>
            </div>

            <div class="mj-contact-form__row">
                <label for="mj-contact-email" class="mj-contact-form__label"><?php esc_html_e('Adresse email', 'mj-member'); ?></label>
                <input
                    id="mj-contact-email"
                    class="mj-contact-form__input<?php echo $prefill_locked ? ' is-readonly' : ''; ?>"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="<?php esc_attr_e('adresse@email.com', 'mj-member'); ?>"
                    value="<?php echo esc_attr($prefill_email); ?>"
                    <?php echo $prefill_locked ? 'readonly aria-readonly="true"' : ''; ?>
                    <?php echo ($prefill_locked && $readonly_email_note_id !== '') ? 'aria-describedby="' . esc_attr($readonly_email_note_id) . '"' : ''; ?>
                >
                <?php if ($prefill_locked && $readonly_email_note_id !== '') : ?>
                    <p id="<?php echo esc_attr($readonly_email_note_id); ?>" class="mj-contact-form__readonly-hint"><?php esc_html_e('Adresse email synchronisée avec votre compte.', 'mj-member'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mj-contact-form__row">
            <fieldset class="mj-contact-form__recipient-group">
                <legend class="mj-contact-form__label"><?php esc_html_e('Destinataire', 'mj-member'); ?></legend>
                <div class="mj-contact-form__recipient-section">
                    <?php if ($general_option !== null) :
                        $recipient = $general_option;
                        $value = isset($recipient['value']) ? (string) $recipient['value'] : '';
                        $label = isset($recipient['label']) ? (string) $recipient['label'] : $value;
                        $role_label = isset($recipient['role_label']) ? (string) $recipient['role_label'] : '';
                        $description_text = isset($recipient['description']) ? (string) $recipient['description'] : '';
                        $is_cover = !empty($recipient['is_cover']);
                        $cover_theme = isset($recipient['cover_theme']) ? (string) $recipient['cover_theme'] : '';
                        $avatar = isset($recipient['avatar']) && is_array($recipient['avatar']) ? $recipient['avatar'] : array();
                        $avatar_url = isset($avatar['url']) ? (string) $avatar['url'] : '';
                        $avatar_alt = isset($avatar['alt']) && $avatar['alt'] !== '' ? (string) $avatar['alt'] : $label;
                        $avatar_initials = isset($avatar['initials']) ? (string) $avatar['initials'] : '';
                        if ($avatar_initials === '') {
                            if (function_exists('mb_substr')) {
                                $avatar_initials = strtoupper(mb_substr($label, 0, 2, 'UTF-8'));
                            } else {
                                $avatar_initials = strtoupper(substr($label, 0, 2));
                            }
                        }
                        $input_id = 'mj-contact-recipient-' . $input_index;
                        $input_index++;
                        $is_selected = ($default_selected_value !== '' && $value === $default_selected_value);
                        $option_classes = array('mj-contact-form__recipient-option', 'mj-contact-form__recipient-option--general');
                        if ($is_selected) {
                            $option_classes[] = 'is-selected';
                        }
                        if ($is_cover) {
                            $option_classes[] = 'mj-contact-form__recipient-option--cover';
                        }
                        $option_class_attr = implode(' ', $option_classes);
                        $cover_theme_attr = $cover_theme !== '' ? ' data-cover-theme="' . esc_attr($cover_theme) . '"' : '';
                        ?>
                        <div class="mj-contact-form__recipient-general">
                            <label class="<?php echo esc_attr($option_class_attr); ?>"<?php echo $cover_theme_attr; ?> for="<?php echo esc_attr($input_id); ?>">
                                <input
                                    id="<?php echo esc_attr($input_id); ?>"
                                    class="mj-contact-form__recipient-input"
                                    type="checkbox"
                                    data-recipient-general
                                    value="<?php echo esc_attr($value); ?>"
                                    <?php checked($is_selected); ?>
                                >
                                <span class="mj-contact-form__recipient-body">
                                    <span class="mj-contact-form__recipient-visual">
                                        <?php if ($avatar_url !== '') : ?>
                                            <span class="mj-contact-form__recipient-photo">
                                                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($avatar_alt); ?>" loading="lazy">
                                            </span>
                                        <?php else : ?>
                                            <span class="mj-contact-form__recipient-initials"><?php echo esc_html($avatar_initials); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="mj-contact-form__recipient-content">
                                        <span class="mj-contact-form__recipient-name"><?php echo esc_html($label); ?></span>
                                        <?php if ($role_label !== '') : ?>
                                            <span class="mj-contact-form__recipient-role"><?php echo esc_html($role_label); ?></span>
                                        <?php endif; ?>
                                        <?php if ($description_text !== '') : ?>
                                            <span class="mj-contact-form__recipient-description"><?php echo esc_html($description_text); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($individual_recipients)) : ?>
                        <div class="mj-contact-form__recipient-list" data-recipient-list>
                            <?php foreach ($individual_recipients as $recipient) :
                                $value = isset($recipient['value']) ? (string) $recipient['value'] : '';
                                $label = isset($recipient['label']) ? (string) $recipient['label'] : $value;
                                $role_label = isset($recipient['role_label']) ? (string) $recipient['role_label'] : '';
                                $is_cover = !empty($recipient['is_cover']);
                                $cover_theme = isset($recipient['cover_theme']) ? (string) $recipient['cover_theme'] : '';
                                $avatar = isset($recipient['avatar']) && is_array($recipient['avatar']) ? $recipient['avatar'] : array();
                                $avatar_url = isset($avatar['url']) ? (string) $avatar['url'] : '';
                                $avatar_alt = isset($avatar['alt']) && $avatar['alt'] !== '' ? (string) $avatar['alt'] : $label;
                                $avatar_initials = isset($avatar['initials']) ? (string) $avatar['initials'] : '';
                                if ($avatar_initials === '') {
                                    if (function_exists('mb_substr')) {
                                        $avatar_initials = strtoupper(mb_substr($label, 0, 2, 'UTF-8'));
                                    } else {
                                        $avatar_initials = strtoupper(substr($label, 0, 2));
                                    }
                                }
                                $input_id = 'mj-contact-recipient-' . $input_index;
                                $input_index++;
                                $is_selected = ($default_selected_value !== '' && $value === $default_selected_value);
                                $option_classes = array('mj-contact-form__recipient-option');
                                if ($is_selected) {
                                    $option_classes[] = 'is-selected';
                                }
                                if ($is_cover) {
                                    $option_classes[] = 'mj-contact-form__recipient-option--cover';
                                }
                                $option_class_attr = implode(' ', $option_classes);
                                $cover_theme_attr = $cover_theme !== '' ? ' data-cover-theme="' . esc_attr($cover_theme) . '"' : '';
                                ?>
                                <label class="<?php echo esc_attr($option_class_attr); ?>"<?php echo $cover_theme_attr; ?> for="<?php echo esc_attr($input_id); ?>">
                                    <input
                                        id="<?php echo esc_attr($input_id); ?>"
                                        class="mj-contact-form__recipient-input"
                                        type="radio"
                                        name="recipient_individual"
                                        value="<?php echo esc_attr($value); ?>"
                                        <?php checked($is_selected); ?>
                                    >
                                    <span class="mj-contact-form__recipient-body">
                                        <span class="mj-contact-form__recipient-visual">
                                            <?php if ($avatar_url !== '') : ?>
                                                <span class="mj-contact-form__recipient-photo">
                                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($avatar_alt); ?>" loading="lazy">
                                                </span>
                                            <?php else : ?>
                                                <span class="mj-contact-form__recipient-initials"><?php echo esc_html($avatar_initials); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="mj-contact-form__recipient-content">
                                            <span class="mj-contact-form__recipient-name"><?php echo esc_html($label); ?></span>
                                            <?php if ($role_label !== '') : ?>
                                                <span class="mj-contact-form__recipient-role"><?php echo esc_html($role_label); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>
        </div>

        <div class="mj-contact-form__row">
            <label for="mj-contact-subject" class="mj-contact-form__label"><?php esc_html_e('Sujet', 'mj-member'); ?></label>
            <input id="mj-contact-subject" class="mj-contact-form__input mj-contact-form__input--subject" type="text" name="subject" placeholder="<?php esc_attr_e('Objet de votre message', 'mj-member'); ?>">
        </div>

        <div class="mj-contact-form__row">
            <label for="mj-contact-message" class="mj-contact-form__label"><?php esc_html_e('Message', 'mj-member'); ?></label>
            <textarea id="mj-contact-message" class="mj-contact-form__textarea" name="message" rows="6" required placeholder="<?php esc_attr_e('Écrivez votre message ici…', 'mj-member'); ?>"></textarea>
        </div>

        <input type="hidden" name="source" value="<?php echo $current_url; ?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(isset($config['nonce']) ? $config['nonce'] : ''); ?>">
        <input type="hidden" name="member_id" value="<?php echo $prefill_member_id > 0 ? esc_attr((string) $prefill_member_id) : ''; ?>">

        <div class="mj-contact-form__actions">
            <button type="submit" class="mj-contact-form__submit button button-primary"><?php echo esc_html($submit_label); ?></button>
        </div>

        <div class="mj-contact-form__feedback" aria-live="polite"></div>
                </form>
            </div>
        </div>
    </div>
</div>
