<?php
if (!defined('ABSPATH')) {
    exit;
}

$animateurs_data = isset($animateurs_view) && is_array($animateurs_view) ? $animateurs_view : array();

$animateurs_count_value = isset($animateurs_data['count'])
    ? (int) $animateurs_data['count']
    : (isset($animateurs_count) ? (int) $animateurs_count : 0);

if ($animateurs_count_value <= 0) {
    return;
}

$animateurs_items_list = isset($animateurs_data['items']) && is_array($animateurs_data['items'])
    ? $animateurs_data['items']
    : (isset($animateur_items) && is_array($animateur_items) ? $animateur_items : array());

$animateurs_event_id = isset($animateurs_data['event_id'])
    ? (int) $animateurs_data['event_id']
    : (isset($event_id) ? (int) $event_id : 0);

$whatsapp_icon_url = isset($event_single_assets_url) ? $event_single_assets_url . 'images/whatsapp-icon.png' : '';
?>
<section class="mj-member-event-single__card mj-member-event-single__animateurs">
    <h4><?php echo esc_html__('Animateurs responsables', 'mj-member'); ?></h4>
    <div class="mj-member-event-single__animateurs-list">
        <?php foreach ($animateurs_items_list as $animateur_item) :
            if (!is_array($animateur_item)) {
                continue;
            }
            $animateur_classes = array('mj-member-event-single__animateur');
            if (!empty($animateur_item['is_primary'])) {
                $animateur_classes[] = 'is-primary';
            }
            $animateur_initials = !empty($animateur_item['initials']) ? $animateur_item['initials'] : '#';
            $animateur_name = isset($animateur_item['name']) ? $animateur_item['name'] : '';
            $animateur_role_label = isset($animateur_item['role_label']) ? $animateur_item['role_label'] : '';
            $animateur_email = isset($animateur_item['email']) ? $animateur_item['email'] : '';
            $animateur_phone = isset($animateur_item['phone']) ? $animateur_item['phone'] : '';
            $animateur_whatsapp_link = isset($animateur_item['whatsapp_link']) ? $animateur_item['whatsapp_link'] : '';
            $animateur_avatar_url = isset($animateur_item['avatar_url']) ? $animateur_item['avatar_url'] : '';
            $animateur_avatar_alt = isset($animateur_item['avatar_alt']) ? $animateur_item['avatar_alt'] : ($animateur_name !== '' ? sprintf(__('Portrait de %s', 'mj-member'), $animateur_name) : __('Portrait animateur', 'mj-member'));
            $animateur_id = isset($animateur_item['id']) ? (int) $animateur_item['id'] : 0;
            $animateur_contact_form_url = '';
            if ($contact_form_page_url !== '' && $animateur_id > 0) {
                $contact_query = array(
                    'recipient' => $contact_recipient_prefix . ':' . $animateur_id,
                );
                if ($animateurs_event_id > 0) {
                    $contact_query['event'] = $animateurs_event_id;
                }
                $animateur_contact_form_url = add_query_arg($contact_query, $contact_form_page_url);
            }
            $animateur_has_contact_option = ($animateur_email !== '' || $animateur_phone !== '' || $animateur_whatsapp_link !== '' || $animateur_contact_form_url !== '');
            ?>
        <article class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $animateur_classes))); ?>">
            <span class="mj-member-event-single__animateur-avatar">
                <?php if ($animateur_avatar_url !== '') : ?>
                <img src="<?php echo esc_url($animateur_avatar_url); ?>" alt="<?php echo esc_attr($animateur_avatar_alt); ?>" loading="lazy" />
                <?php else : ?>
                <?php echo esc_html($animateur_initials); ?>
                <?php endif; ?>
            </span>
            <div class="mj-member-event-single__animateur-content">
                <?php if ($animateur_name !== '') : ?>
                <p class="mj-member-event-single__animateur-name"><?php echo esc_html($animateur_name); ?></p>
                <?php endif; ?>
                <?php if ($animateur_role_label !== '') : ?>
                <p class="mj-member-event-single__animateur-role">
                    <span class="mj-member-event-single__animateur-role-badge"><?php echo esc_html($animateur_role_label); ?></span>
                </p>
                <?php endif; ?>
                <?php if ($animateur_has_contact_option) : ?>
                <ul class="mj-member-event-single__animateur-contact">
                    <?php if ($animateur_email !== '') : ?>
                    <li>
                        <a href="mailto:<?php echo esc_attr($animateur_email); ?>">
                            <span class="mj-member-event-single__animateur-contact-icon">@</span>
                            <span class="screen-reader-text"><?php echo esc_html__('Envoyer un email', 'mj-member'); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($animateur_phone !== '') : ?>
                    <li>
                        <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $animateur_phone)); ?>">
                            <span class="mj-member-event-single__animateur-contact-icon">☎</span>
                            <span class="screen-reader-text"><?php echo esc_html__('Appeler', 'mj-member'); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($animateur_whatsapp_link !== '') : ?>
                    <li>
                        <a href="<?php echo esc_url($animateur_whatsapp_link); ?>" target="_blank" rel="noopener">
                            <span class="mj-member-event-single__animateur-contact-icon">
                                <?php if ($whatsapp_icon_url !== '') : ?>
                                <img src="<?php echo esc_url($whatsapp_icon_url); ?>" alt="WhatsApp" />
                                <?php else : ?>
                                WA
                                <?php endif; ?>
                            </span>
                            <span class="screen-reader-text"><?php echo esc_html__('Contacter via WhatsApp', 'mj-member'); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($animateur_contact_form_url !== '') : ?>
                    <li>
                        <a href="<?php echo esc_url($animateur_contact_form_url); ?>" title="<?php echo esc_attr__('Page contact MJ (demandes sérieuses uniquement)', 'mj-member'); ?>">
                            <span class="mj-member-event-single__animateur-contact-icon">✉</span>
                            <span class="screen-reader-text"><?php echo esc_html__('Accéder à la page contact MJ', 'mj-member'); ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
