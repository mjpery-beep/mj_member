<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MjEventPhotos')) {
    require_once plugin_dir_path(__FILE__) . 'classes/crud/MjEventPhotos.php';
}

if (!function_exists('mj_member_event_photos_page')) {
    function mj_member_event_photos_page() {
        $capability = Config::capability();

        if (!current_user_can($capability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : MjEventPhotos::STATUS_PENDING;
        if ($status_filter !== '' && !in_array($status_filter, array_keys(MjEventPhotos::get_status_labels()), true)) {
            $status_filter = MjEventPhotos::STATUS_PENDING;
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 25;

        $photos = MjEventPhotos::query(array(
            'status' => $status_filter,
            'per_page' => $per_page,
            'paged' => $paged,
            'order' => 'DESC',
            'orderby' => 'created_at',
        ));

        $status_labels = MjEventPhotos::get_status_labels();
        $current_url = add_query_arg(array());
        $notice_key = isset($_GET['mj_event_photo_notice']) ? sanitize_key(wp_unslash($_GET['mj_event_photo_notice'])) : '';
        $message = '';
        switch ($notice_key) {
            case 'approved':
                $message = __('Photo validée.', 'mj-member');
                break;
            case 'rejected':
                $message = __('Photo refusée.', 'mj-member');
                break;
            case 'deleted':
                $message = __('Photo supprimée.', 'mj-member');
                break;
            case 'error':
                $message = __('Une erreur est survenue.', 'mj-member');
                break;
        }

        $events_cache = array();
        $members_cache = array();

        require_once ABSPATH . 'wp-admin/includes/template.php';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Photos des événements', 'mj-member'); ?></h1>

            <?php if ($message !== '') : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($status_labels as $key => $label) :
                    $tab_url = add_query_arg(array('status' => $key, 'paged' => 1));
                    $is_active = ($status_filter === $key) ? ' nav-tab-active' : '';
                    ?>
                    <a class="nav-tab<?php echo esc_attr($is_active); ?>" href="<?php echo esc_url($tab_url); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php if (empty($photos)) : ?>
                <p><?php esc_html_e('Aucune photo à afficher pour ce statut.', 'mj-member'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Photo', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Événement', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Participant', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Statut', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Soumise le', 'mj-member'); ?></th>
                            <th><?php esc_html_e('Actions', 'mj-member'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($photos as $row) :
                            $photo_id = (int) $row->id;
                            $attachment_id = (int) $row->attachment_id;
                            $event_id = (int) $row->event_id;
                            $member_id = (int) $row->member_id;

                            if ($event_id > 0 && !isset($events_cache[$event_id]) && class_exists('MjEvents_CRUD')) {
                                $events_cache[$event_id] = MjEvents_CRUD::find($event_id);
                            }

                            if ($member_id > 0 && !isset($members_cache[$member_id]) && class_exists('MjMembers_CRUD')) {
                                $members_cache[$member_id] = MjMembers_CRUD::getById($member_id);
                            }

                            $event = isset($events_cache[$event_id]) ? $events_cache[$event_id] : null;
                            $member = isset($members_cache[$member_id]) ? $members_cache[$member_id] : null;

                            $event_title = $event && isset($event->title) ? $event->title : sprintf(__('Événement #%d', 'mj-member'), $event_id);
                            $event_link = $event && isset($event->slug) ? mj_member_get_event_public_link($event) : '';
                            $member_name = $member ? mj_member_event_photos_format_member_name($member) : sprintf(__('Membre #%d', 'mj-member'), $member_id);
                            $thumbnail = $attachment_id ? wp_get_attachment_image($attachment_id, array(120, 120)) : '';
                            $status = isset($row->status) ? sanitize_key((string) $row->status) : MjEventPhotos::STATUS_PENDING;
                            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
                            $submitted_at = isset($row->created_at) ? strtotime((string) $row->created_at) : 0;
                            $submitted_display = $submitted_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $submitted_at) : '';
                            $nonce = wp_create_nonce('mj-member-review-photo-' . $photo_id);
                            $redirect_to = esc_url(add_query_arg(array('status' => $status_filter, 'paged' => $paged)));
                            ?>
                            <tr>
                                <td><?php echo $thumbnail ? wp_kses_post($thumbnail) : esc_html__('Aperçu indisponible', 'mj-member'); ?></td>
                                <td>
                                    <strong><?php echo esc_html($event_title); ?></strong><br>
                                    <?php if ($event_link) : ?>
                                        <a href="<?php echo esc_url($event_link); ?>" target="_blank" rel="noopener">
                                            <?php esc_html_e('Voir la fiche publique', 'mj-member'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($member_name); ?></td>
                                <td><?php echo esc_html($status_label); ?></td>
                                <td><?php echo esc_html($submitted_display); ?></td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="mj_member_review_event_photo">
                                            <input type="hidden" name="decision" value="approve">
                                            <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                                            <button type="submit" class="button button-primary"<?php disabled($status === MjEventPhotos::STATUS_APPROVED); ?>><?php esc_html_e('Valider', 'mj-member'); ?></button>
                                        </form>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="mj_member_review_event_photo">
                                            <input type="hidden" name="decision" value="reject">
                                            <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                                            <input type="text" name="reason" placeholder="<?php esc_attr_e('Motif (optionnel)', 'mj-member'); ?>" style="width:100%;margin-bottom:4px;">
                                            <button type="submit" class="button button-secondary"<?php disabled($status === MjEventPhotos::STATUS_REJECTED); ?>><?php esc_html_e('Refuser', 'mj-member'); ?></button>
                                        </form>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Supprimer définitivement cette photo ? Cette action est irréversible.', 'mj-member')); ?>');">
                                            <input type="hidden" name="action" value="mj_member_review_event_photo">
                                            <input type="hidden" name="decision" value="delete">
                                            <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                                            <button type="submit" class="button button-link-delete"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mj_member_event_photos_review_handler')) {
    function mj_member_event_photos_review_handler() {
        $capability = Config::capability();

        if (!current_user_can($capability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $photo_id = isset($_POST['photo_id']) ? (int) $_POST['photo_id'] : 0;
        $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';
        $redirect = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : admin_url('admin.php?page=mj_event_photos');
        $redirect = $redirect !== '' ? $redirect : admin_url('admin.php?page=mj_event_photos');

        if ($photo_id <= 0 || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mj-member-review-photo-' . $photo_id)) {
            wp_safe_redirect(add_query_arg('mj_event_photo_notice', 'error', $redirect));
            exit;
        }

        $photo = MjEventPhotos::get($photo_id);
        if (!$photo) {
            wp_safe_redirect(add_query_arg('mj_event_photo_notice', 'error', $redirect));
            exit;
        }

        switch ($decision) {
            case 'approve':
                $updated = MjEventPhotos::update_status($photo_id, MjEventPhotos::STATUS_APPROVED, array(
                    'reviewed_at' => current_time('mysql'),
                    'reviewed_by' => get_current_user_id(),
                ));
                $notice = $updated instanceof WP_Error ? 'error' : 'approved';
                break;
            case 'reject':
                $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';
                $updated = MjEventPhotos::update_status($photo_id, MjEventPhotos::STATUS_REJECTED, array(
                    'reviewed_at' => current_time('mysql'),
                    'reviewed_by' => get_current_user_id(),
                    'rejection_reason' => $reason,
                ));
                $notice = $updated instanceof WP_Error ? 'error' : 'rejected';
                break;
            case 'delete':
                $attachment_id = isset($photo->attachment_id) ? (int) $photo->attachment_id : 0;
                $deleted = MjEventPhotos::delete($photo_id);
                if ($deleted && $attachment_id > 0) {
                    wp_delete_attachment($attachment_id, true);
                }
                $notice = $deleted ? 'deleted' : 'error';
                break;
            default:
                $notice = 'error';
                break;
        }

        wp_safe_redirect(add_query_arg('mj_event_photo_notice', $notice, $redirect));
        exit;
    }

    add_action('admin_post_mj_member_review_event_photo', 'mj_member_event_photos_review_handler');
}

if (!function_exists('mj_member_get_event_public_link')) {
    function mj_member_get_event_public_link($event) {
        if (!$event) {
            return '';
        }

        $slug = isset($event->slug) ? $event->slug : '';
        if ($slug === '') {
            return '';
        }

        $base = home_url('/evenements/');
        return trailingslashit($base) . rawurlencode($slug);
    }
}
