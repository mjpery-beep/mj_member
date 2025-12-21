<?php

use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

AssetsManager::requirePackage('payments-overview');

$template_data = isset($template_data) && is_array($template_data) ? $template_data : array();

$title = isset($template_data['title']) ? (string) $template_data['title'] : '';
$intro = isset($template_data['intro']) ? (string) $template_data['intro'] : '';
$display_mode = isset($template_data['display_mode']) ? sanitize_key((string) $template_data['display_mode']) : 'tabs';
if (!in_array($display_mode, array('tabs', 'stack'), true)) {
    $display_mode = 'tabs';
}

$messages = isset($template_data['messages']) && is_array($template_data['messages']) ? $template_data['messages'] : array();
$empty_confirmed_message = isset($messages['empty_confirmed']) ? (string) $messages['empty_confirmed'] : '';
$empty_pending_message = isset($messages['empty_pending']) ? (string) $messages['empty_pending'] : '';
$not_logged_in_message = isset($messages['not_logged_in']) ? (string) $messages['not_logged_in'] : '';
$view_qr_label = isset($messages['view_qr']) ? (string) $messages['view_qr'] : __('Voir le QR code', 'mj-member');
$qr_modal_title = isset($messages['qr_modal_title']) ? (string) $messages['qr_modal_title'] : __('Payer avec le QR code', 'mj-member');
$qr_modal_hint = isset($messages['qr_modal_hint']) ? (string) $messages['qr_modal_hint'] : __('Scannez ce code ou ouvrez le lien pour finaliser le paiement.', 'mj-member');
$qr_modal_cta = isset($messages['qr_modal_cta']) ? (string) $messages['qr_modal_cta'] : __('Ouvrir le lien de paiement', 'mj-member');
$qr_modal_close = isset($messages['qr_modal_close']) ? (string) $messages['qr_modal_close'] : __('Fermer', 'mj-member');
$qr_modal_image_alt = isset($messages['qr_modal_image_alt']) ? (string) $messages['qr_modal_image_alt'] : __('QR code du paiement', 'mj-member');

$options = isset($template_data['options']) && is_array($template_data['options']) ? $template_data['options'] : array();
$show_context = !empty($options['show_context']);
$show_reference = !empty($options['show_reference']);

$viewer = isset($template_data['viewer']) && is_array($template_data['viewer']) ? $template_data['viewer'] : array();
$can_manage_children = !empty($viewer['can_manage_children']);

$members = isset($template_data['members']) && is_array($template_data['members']) ? $template_data['members'] : array();
$confirmed = isset($template_data['confirmed']) && is_array($template_data['confirmed']) ? $template_data['confirmed'] : array();
$pending = isset($template_data['pending']) && is_array($template_data['pending']) ? $template_data['pending'] : array();

$confirmed_entries = isset($confirmed['entries']) && is_array($confirmed['entries']) ? $confirmed['entries'] : array();
$pending_entries = isset($pending['entries']) && is_array($pending['entries']) ? $pending['entries'] : array();
$confirmed_count = isset($confirmed['count']) ? (int) $confirmed['count'] : count($confirmed_entries);
$pending_count = isset($pending['count']) ? (int) $pending['count'] : count($pending_entries);
$confirmed_total = isset($confirmed['total_amount']) ? (float) $confirmed['total_amount'] : 0.0;
$pending_total = isset($pending['total_amount']) ? (float) $pending['total_amount'] : 0.0;

$confirmed_total_display = number_format_i18n($confirmed_total, 2);
$pending_total_display = number_format_i18n($pending_total, 2);

$confirmed_label = sprintf(
    _n('%s paiement confirmé', '%s paiements confirmés', max(1, $confirmed_count), 'mj-member'),
    number_format_i18n($confirmed_count)
);
$pending_label = sprintf(
    _n('%s paiement en attente', '%s paiements en attente', max(1, $pending_count), 'mj-member'),
    number_format_i18n($pending_count)
);

$default_pane = $pending_count > 0 ? 'pending' : 'confirmed';
$should_show_notice = empty($members) && empty($confirmed_entries) && empty($pending_entries);

$config = array(
    'displayMode' => $display_mode,
    'hasConfirmed' => $confirmed_count > 0,
    'hasPending' => $pending_count > 0,
    'defaultPane' => $default_pane,
);
$config_attribute = esc_attr(wp_json_encode($config));

$root_classes = array('mj-payments-overview');
if ($display_mode === 'stack') {
    $root_classes[] = 'is-stack';
}
$root_class_attr = esc_attr(implode(' ', array_map('sanitize_html_class', $root_classes)));

$modal_id = function_exists('wp_unique_id') ? wp_unique_id('mj-payments-overview-modal-') : uniqid('mj-payments-overview-modal-');
$modal_label_id = function_exists('wp_unique_id') ? wp_unique_id('mj-payments-overview-modal-label-') : uniqid('mj-payments-overview-modal-label-');

$is_confirmed_active = $default_pane === 'confirmed';
$is_pending_active = !$is_confirmed_active;

?>
<div class="<?php echo $root_class_attr; ?>" data-display-mode="<?php echo esc_attr($display_mode); ?>" data-config="<?php echo $config_attribute; ?>" data-default-pane="<?php echo esc_attr($default_pane); ?>">
    <div class="mj-payments-overview__header">
        <?php if ($title !== '') : ?>
            <h3 class="mj-payments-overview__title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>
        <?php if ($can_manage_children && !empty($members)) : ?>
            <p class="mj-payments-overview__scope">
                <?php esc_html_e('Vue regroupée pour vos comptes et ceux de vos jeunes.', 'mj-member'); ?>
            </p>
        <?php endif; ?>
        <?php if ($intro !== '') : ?>
            <div class="mj-payments-overview__intro"><?php echo wp_kses_post($intro); ?></div>
        <?php endif; ?>
    </div>

    <?php if ($should_show_notice) : ?>
        <div class="mj-payments-overview__notice">
            <?php echo esc_html($not_logged_in_message !== '' ? $not_logged_in_message : __('Connectez-vous pour consulter vos paiements.', 'mj-member')); ?>
        </div>
    <?php else : ?>
        <?php if ($display_mode === 'tabs') : ?>
            <div class="mj-payments-overview__tabs" role="tablist">
                <button type="button" class="mj-payments-overview__tab<?php echo $is_confirmed_active ? ' is-active' : ''; ?>" data-pane="confirmed" role="tab" aria-selected="<?php echo $is_confirmed_active ? 'true' : 'false'; ?>"<?php echo $is_confirmed_active ? ' tabindex="0"' : ' tabindex="-1"'; ?>>
                    <span class="mj-payments-overview__tab-label"><?php echo esc_html($confirmed_label); ?></span>
                    <span class="mj-payments-overview__tab-amount"><?php echo esc_html($confirmed_total_display); ?> €</span>
                </button>
                <button type="button" class="mj-payments-overview__tab<?php echo $is_pending_active ? ' is-active' : ''; ?>" data-pane="pending" role="tab" aria-selected="<?php echo $is_pending_active ? 'true' : 'false'; ?>"<?php echo $is_pending_active ? ' tabindex="0"' : ' tabindex="-1"'; ?>>
                    <span class="mj-payments-overview__tab-label"><?php echo esc_html($pending_label); ?></span>
                    <span class="mj-payments-overview__tab-amount"><?php echo esc_html($pending_total_display); ?> €</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="mj-payments-overview__panes">
            <section class="mj-payments-overview__pane<?php echo $is_confirmed_active ? ' is-active' : ''; ?>" data-pane="confirmed" role="tabpanel" aria-label="<?php echo esc_attr__('Paiements confirmés', 'mj-member'); ?>"<?php echo ($display_mode === 'tabs' && !$is_confirmed_active) ? ' hidden="hidden"' : ''; ?>>
                <header class="mj-payments-overview__summary">
                    <div class="mj-payments-overview__metric">
                        <span class="mj-payments-overview__metric-label"><?php echo esc_html__('Confirmés', 'mj-member'); ?></span>
                        <span class="mj-payments-overview__metric-value"><?php echo esc_html($confirmed_total_display); ?> €</span>
                    </div>
                    <div class="mj-payments-overview__metric mj-payments-overview__metric--count">
                        <span class="mj-payments-overview__metric-label"><?php echo esc_html__('Transactions', 'mj-member'); ?></span>
                        <span class="mj-payments-overview__metric-value"><?php echo esc_html(number_format_i18n($confirmed_count)); ?></span>
                    </div>
                </header>
                <?php if (empty($confirmed_entries)) : ?>
                    <?php if ($empty_confirmed_message !== '') : ?>
                        <p class="mj-payments-overview__empty"><?php echo esc_html($empty_confirmed_message); ?></p>
                    <?php endif; ?>
                <?php else : ?>
                    <ul class="mj-payments-overview__list" role="list">
                        <?php foreach ($confirmed_entries as $entry) :
                            $member_label = isset($entry['member_label']) ? $entry['member_label'] : '';
                            $role_label = isset($entry['member_role_label']) ? $entry['member_role_label'] : '';
                            $amount_display = isset($entry['amount_display']) ? $entry['amount_display'] : number_format_i18n(isset($entry['amount']) ? (float) $entry['amount'] : 0.0, 2);
                            $date_display = isset($entry['date_display']) ? $entry['date_display'] : '';
                            $method_label = isset($entry['method_label']) ? $entry['method_label'] : '';
                            $reference = isset($entry['reference']) ? $entry['reference'] : '';
                            $status_label = isset($entry['status_label']) ? $entry['status_label'] : __('Paiement confirmé', 'mj-member');
                            ?>
                            <li class="mj-payments-overview__item is-confirmed" role="listitem">
                                <div class="mj-payments-overview__item-header">
                                    <span class="mj-payments-overview__amount"><?php echo esc_html($amount_display); ?> €</span>
                                    <span class="mj-payments-overview__badge mj-payments-overview__badge--confirmed"><?php echo esc_html($status_label); ?></span>
                                </div>
                                <div class="mj-payments-overview__item-body">
                                    <div class="mj-payments-overview__member">
                                        <span class="mj-payments-overview__member-name"><?php echo esc_html($member_label); ?></span>
                                        <?php if ($role_label !== '') : ?>
                                            <span class="mj-payments-overview__member-role"><?php echo esc_html($role_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <dl class="mj-payments-overview__meta">
                                        <?php if ($date_display !== '') : ?>
                                            <div class="mj-payments-overview__meta-field">
                                                <dt><?php esc_html_e('Date', 'mj-member'); ?></dt>
                                                <dd><?php echo esc_html($date_display); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($method_label !== '') : ?>
                                            <div class="mj-payments-overview__meta-field">
                                                <dt><?php esc_html_e('Mode', 'mj-member'); ?></dt>
                                                <dd><?php echo esc_html($method_label); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($show_reference && $reference !== '') : ?>
                                            <div class="mj-payments-overview__meta-field">
                                                <dt><?php esc_html_e('Référence', 'mj-member'); ?></dt>
                                                <dd><?php echo esc_html($reference); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="mj-payments-overview__pane<?php echo $is_pending_active ? ' is-active' : ''; ?>" data-pane="pending" role="tabpanel" aria-label="<?php echo esc_attr__('Paiements en attente', 'mj-member'); ?>"<?php echo ($display_mode === 'tabs' && !$is_pending_active) ? ' hidden="hidden"' : ''; ?>>
                <header class="mj-payments-overview__summary">
                    <div class="mj-payments-overview__metric">
                        <span class="mj-payments-overview__metric-label"><?php echo esc_html__('En attente', 'mj-member'); ?></span>
                        <span class="mj-payments-overview__metric-value"><?php echo esc_html($pending_total_display); ?> €</span>
                    </div>
                    <div class="mj-payments-overview__metric mj-payments-overview__metric--count">
                        <span class="mj-payments-overview__metric-label"><?php echo esc_html__('Transactions', 'mj-member'); ?></span>
                        <span class="mj-payments-overview__metric-value"><?php echo esc_html(number_format_i18n($pending_count)); ?></span>
                    </div>
                </header>
                <?php if (empty($pending_entries)) : ?>
                    <?php if ($empty_pending_message !== '') : ?>
                        <p class="mj-payments-overview__empty"><?php echo esc_html($empty_pending_message); ?></p>
                    <?php endif; ?>
                <?php else : ?>
                    <ul class="mj-payments-overview__list" role="list">
                        <?php foreach ($pending_entries as $entry) :
                            $member_label = isset($entry['member_label']) ? $entry['member_label'] : '';
                            $role_label = isset($entry['member_role_label']) ? $entry['member_role_label'] : '';
                            $amount_display = isset($entry['amount_display']) ? $entry['amount_display'] : number_format_i18n(isset($entry['amount']) ? (float) $entry['amount'] : 0.0, 2);
                            $created_display = isset($entry['created_display']) ? $entry['created_display'] : '';
                            $status_label = isset($entry['status_label']) ? $entry['status_label'] : __('Paiement en attente', 'mj-member');
                            $context_label = isset($entry['context_label']) ? $entry['context_label'] : '';
                            $reference = isset($entry['reference']) ? $entry['reference'] : '';
                            $checkout_url = isset($entry['checkout_url']) ? $entry['checkout_url'] : '';
                            $qr_url = isset($entry['qr_url']) ? $entry['qr_url'] : '';
                            $show_actions = ($checkout_url !== '') || ($qr_url !== '');
                            ?>
                            <li class="mj-payments-overview__item is-pending" role="listitem">
                                <div class="mj-payments-overview__item-header">
                                    <span class="mj-payments-overview__amount"><?php echo esc_html($amount_display); ?> €</span>
                                    <span class="mj-payments-overview__badge mj-payments-overview__badge--pending"><?php echo esc_html($status_label); ?></span>
                                </div>
                                <div class="mj-payments-overview__item-body">
                                    <div class="mj-payments-overview__member">
                                        <span class="mj-payments-overview__member-name"><?php echo esc_html($member_label); ?></span>
                                        <?php if ($role_label !== '') : ?>
                                            <span class="mj-payments-overview__member-role"><?php echo esc_html($role_label); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <dl class="mj-payments-overview__meta">
                                        <?php if ($created_display !== '') : ?>
                                            <div class="mj-payments-overview__meta-field">
                                                <dt><?php esc_html_e('Créé le', 'mj-member'); ?></dt>
                                                <dd><?php echo esc_html($created_display); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($show_context && $context_label !== '') : ?>
                                            <div class="mj-payments-overview__meta-field">
                                                <dt><?php esc_html_e('Contexte', 'mj-member'); ?></dt>
                                                <dd><?php echo esc_html($context_label); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($show_reference && $reference !== '') : ?>
                                            <div class="mj-payments-overview__meta-field">
                                                <dt><?php esc_html_e('Référence', 'mj-member'); ?></dt>
                                                <dd><?php echo esc_html($reference); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                    </dl>
                                    <?php if ($show_actions) : ?>
                                        <div class="mj-payments-overview__actions">
                                            <?php if ($checkout_url !== '') : ?>
                                                <a href="<?php echo esc_url($checkout_url); ?>" class="mj-payments-overview__pay-btn" target="_blank" rel="noopener">
                                                    <svg class="mj-payments-overview__pay-btn-icon mj-payments-overview__pay-btn-icon--leading" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                        <path d="M3 5h18a1 1 0 0 1 1 1v3H2V6a1 1 0 0 1 1-1zm-1 6h20v7a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-7zm4 5h4v-2H6v2z" />
                                                    </svg>
                                                    <span class="mj-payments-overview__pay-btn-label"><?php esc_html_e('Payer maintenant', 'mj-member'); ?></span>
                                                    <svg class="mj-payments-overview__pay-btn-icon mj-payments-overview__pay-btn-icon--trailing" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                        <path d="M5 12h10.586l-3.293-3.293 1.414-1.414L19.414 12l-5.707 5.707-1.414-1.414L15.586 13H5z" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($qr_url !== '') : ?>
                                                <button type="button" class="mj-payments-overview__qr-btn" data-action="show-qr" data-qr-url="<?php echo esc_attr($qr_url); ?>" data-checkout-url="<?php echo esc_attr($checkout_url); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($modal_id); ?>">
                                                    <?php echo esc_html($view_qr_label); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
    <div class="mj-payments-overview__qr-modal" data-qr-modal hidden role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modal_label_id); ?>" id="<?php echo esc_attr($modal_id); ?>" tabindex="-1" data-qr-alt="<?php echo esc_attr($qr_modal_image_alt); ?>">
        <div class="mj-payments-overview__qr-modal-backdrop" data-qr-modal-close></div>
        <div class="mj-payments-overview__qr-modal-dialog" role="document">
            <button type="button" class="mj-payments-overview__qr-modal-close" data-qr-modal-close aria-label="<?php echo esc_attr($qr_modal_close); ?>">&times;</button>
            <h4 class="mj-payments-overview__qr-modal-title" id="<?php echo esc_attr($modal_label_id); ?>"><?php echo esc_html($qr_modal_title); ?></h4>
            <div class="mj-payments-overview__qr-modal-body">
                <img src="" alt="<?php echo esc_attr($qr_modal_image_alt); ?>" data-qr-image loading="lazy" />
                <p class="mj-payments-overview__qr-modal-hint"><?php echo esc_html($qr_modal_hint); ?></p>
                <a href="#" class="mj-payments-overview__qr-modal-link" target="_blank" rel="noopener" data-qr-link><?php echo esc_html($qr_modal_cta); ?></a>
            </div>
        </div>
    </div>
</div>
