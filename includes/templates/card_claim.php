<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

global $mj_member_card_claim_context;
$context = is_array($mj_member_card_claim_context) ? $mj_member_card_claim_context : array();

$member = isset($context['member']) ? $context['member'] : null;
$card_key = isset($context['card_key']) ? sanitize_text_field($context['card_key']) : '';
$state = isset($context['state']) && is_array($context['state']) ? $context['state'] : array();
$errors = isset($state['errors']) && is_array($state['errors']) ? $state['errors'] : array();
$values = isset($state['values']) && is_array($state['values']) ? $state['values'] : array();

$member_first = $member ? sanitize_text_field($member->first_name ?? '') : '';
$member_last = $member ? sanitize_text_field($member->last_name ?? '') : '';
$member_full_name = trim($member_first . ' ' . $member_last);
$member_role = $member ? sanitize_text_field($member->role_label ?? $member->role ?? '') : '';

$email_value = isset($values['email']) ? $values['email'] : ($member ? sanitize_email($member->email ?? '') : '');
$phone_value = isset($values['phone']) ? $values['phone'] : ($member ? sanitize_text_field($member->phone ?? '') : '');

AssetsManager::requirePackage('member-account');

get_header();
?>
<main class="mj-card-claim" role="main">
    <div class="mj-card-claim__container">
        <?php if (!empty($member_full_name)) : ?>
            <p class="mj-card-claim__hello"><?php printf(esc_html__("Bonjour %s !", 'mj-member'), esc_html($member_full_name)); ?></p>
        <?php endif; ?>

        <h1 class="mj-card-claim__title"><?php esc_html_e("Activer mon espace MJ", 'mj-member'); ?></h1>
        <?php if ($member) : ?>
            <p class="mj-card-claim__intro">
                <?php esc_html_e("Scanne ta carte pour accéder à ton espace personnel MJ. Complète les informations ci-dessous pour créer ton accès.", 'mj-member'); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($errors)) : ?>
            <div class="mj-card-claim__notice mj-card-claim__notice--error" role="alert">
                <ul>
                    <?php foreach ($errors as $message) : ?>
                        <li><?php echo esc_html($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($member) : ?>
            <form method="post" class="mj-card-claim__form" novalidate>
                <?php wp_nonce_field('mj_member_card_claim', 'mj_member_card_claim_nonce'); ?>
                <input type="hidden" name="mj_card_key" value="<?php echo esc_attr($card_key); ?>" />

                <div class="mj-card-claim__field">
                    <label class="mj-card-claim__label"><?php esc_html_e('Nom et prénom', 'mj-member'); ?></label>
                    <div class="mj-card-claim__static" aria-live="polite">
                        <?php echo esc_html($member_full_name !== '' ? $member_full_name : __('Profil MJ', 'mj-member')); ?>
                    </div>
                </div>

                <?php if ($member_role !== '') : ?>
                    <div class="mj-card-claim__field">
                        <label class="mj-card-claim__label"><?php esc_html_e('Rôle MJ', 'mj-member'); ?></label>
                        <div class="mj-card-claim__static"><?php echo esc_html($member_role); ?></div>
                    </div>
                <?php endif; ?>

                <div class="mj-card-claim__field">
                    <label for="mj-card-email" class="mj-card-claim__label"><?php esc_html_e('Adresse email', 'mj-member'); ?></label>
                    <input type="email" id="mj-card-email" name="mj_card_email" class="mj-card-claim__input" required value="<?php echo esc_attr($email_value); ?>" autocomplete="email" />
                    <p class="mj-card-claim__hint"><?php esc_html_e("Cette adresse sera utilisée pour vous connecter et recevoir les notifications MJ.", 'mj-member'); ?></p>
                </div>

                <div class="mj-card-claim__field">
                    <label for="mj-card-phone" class="mj-card-claim__label"><?php esc_html_e('Téléphone (optionnel)', 'mj-member'); ?></label>
                    <input type="tel" id="mj-card-phone" name="mj_card_phone" class="mj-card-claim__input" value="<?php echo esc_attr($phone_value); ?>" autocomplete="tel" />
                </div>

                <div class="mj-card-claim__field">
                    <label for="mj-card-password" class="mj-card-claim__label"><?php esc_html_e('Nouveau mot de passe', 'mj-member'); ?></label>
                    <input type="password" id="mj-card-password" name="mj_card_password" class="mj-card-claim__input" required autocomplete="new-password" />
                    <p class="mj-card-claim__hint"><?php esc_html_e('Minimum 8 caractères, ajoutez des chiffres et symboles pour plus de sécurité.', 'mj-member'); ?></p>
                </div>

                <div class="mj-card-claim__field">
                    <label for="mj-card-password-confirm" class="mj-card-claim__label"><?php esc_html_e('Confirmer le mot de passe', 'mj-member'); ?></label>
                    <input type="password" id="mj-card-password-confirm" name="mj_card_password_confirm" class="mj-card-claim__input" required autocomplete="new-password" />
                </div>

                <button type="submit" class="mj-card-claim__submit mj-button">
                    <?php esc_html_e('Créer mon accès', 'mj-member'); ?>
                </button>
            </form>
        <?php else : ?>
            <div class="mj-card-claim__notice mj-card-claim__notice--warning">
                <p><?php esc_html_e('Nous ne parvenons pas à identifier ta carte. Merci de contacter l’équipe MJ afin de générer un nouveau QR code.', 'mj-member'); ?></p>
                <p><a class="mj-card-claim__link" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Retour à l’accueil', 'mj-member'); ?></a></p>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
