<?php

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_contact_messages_is_admin_url')) {
    function mj_member_contact_messages_is_admin_url($url) {
        if ($url === '') {
            return false;
        }

        $parsed = wp_parse_url($url);
        if (empty($parsed) || empty($parsed['path'])) {
            return false;
        }

        return strpos($parsed['path'], 'wp-admin') !== false;
    }
}

if (!function_exists('mj_member_contact_messages_prepare_redirect')) {
    function mj_member_contact_messages_prepare_redirect($redirect_target, $success, $admin_notice_success, $admin_notice_failure, $front_notice_success, $front_notice_failure, array $fallback_args) {
        if ($redirect_target !== '') {
            $param = 'notice';
            $notice_success = $admin_notice_success;
            $notice_failure = $admin_notice_failure;

            if (!mj_member_contact_messages_is_admin_url($redirect_target)) {
                $param = 'mj_contact_notice';
                $notice_success = $front_notice_success;
                $notice_failure = $front_notice_failure;
            }

            $notice_value = $success ? $notice_success : $notice_failure;

            return add_query_arg($param, $notice_value, $redirect_target);
        }

        $fallback_args['notice'] = $success ? $admin_notice_success : $admin_notice_failure;

        return add_query_arg($fallback_args, admin_url('admin.php'));
    }
}

if (!function_exists('mj_member_contact_messages_get_mail_error_cache_key')) {
    function mj_member_contact_messages_get_mail_error_cache_key() {
        if (!is_user_logged_in()) {
            return '';
        }

        return 'mj_member_mail_error_' . get_current_user_id();
    }
}

if (!function_exists('mj_member_contact_messages_store_mail_error')) {
    /**
     * @param WP_Error $wp_error
     * @return void
     */
    function mj_member_contact_messages_store_mail_error($wp_error) {
        if (!($wp_error instanceof WP_Error)) {
            return;
        }

        $cache_key = mj_member_contact_messages_get_mail_error_cache_key();
        if ($cache_key === '') {
            return;
        }

        $payload = array(
            'code' => $wp_error->get_error_code(),
            'message' => sanitize_text_field($wp_error->get_error_message()),
        );

        set_transient($cache_key, $payload, MINUTE_IN_SECONDS * 5);
    }
}

add_action('wp_mail_failed', 'mj_member_contact_messages_store_mail_error');

if (!function_exists('mj_member_contact_messages_consume_mail_error')) {
    /**
     * @return array{code:string,message:string}|null
     */
    function mj_member_contact_messages_consume_mail_error() {
        $cache_key = mj_member_contact_messages_get_mail_error_cache_key();
        if ($cache_key === '') {
            return null;
        }

        $data = get_transient($cache_key);
        if ($data !== false) {
            delete_transient($cache_key);
        }

        if (empty($data) || !is_array($data)) {
            return null;
        }

        return array(
            'code' => isset($data['code']) ? sanitize_key($data['code']) : '',
            'message' => isset($data['message']) ? sanitize_text_field($data['message']) : '',
        );
    }
}

if (!function_exists('mj_member_contact_messages_get_from_settings')) {
    /**
     * @return array{email:string,name:string}
     */
    function mj_member_contact_messages_get_from_settings() {
        $site_name = function_exists('get_bloginfo') ? get_bloginfo('name') : 'WordPress';
        $email = '';
        $name = $site_name !== '' ? sanitize_text_field($site_name) : 'WordPress';

        $option_email = get_option('mj_member_contact_from_email');
        if (is_string($option_email)) {
            $option_email = sanitize_email($option_email);
            if ($option_email !== '') {
                $email = $option_email;
            }
        }

        $override_email = Config::contactFromEmailOverride();
        if ($override_email !== '') {
            $email = $override_email;
        }

        $option_name = get_option('mj_member_contact_from_name');
        if (is_string($option_name)) {
            $option_name = sanitize_text_field($option_name);
            if ($option_name !== '') {
                $name = $option_name;
            }
        }

        $override_name = Config::contactFromNameOverride();
        if ($override_name !== '') {
            $name = $override_name;
        }

        return array(
            'email' => $email,
            'name' => $name,
        );
    }
}

if (!function_exists('mj_member_contact_messages_page')) {
    function mj_member_contact_messages_page() {
        $contactCapability = Config::contactCapability();

        if (!current_user_can($contactCapability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        $message_id = isset($_GET['message']) ? (int) $_GET['message'] : 0;

        if ($action === 'view' && $message_id > 0) {
            mj_member_render_contact_message_detail($message_id);
            return;
        }

        mj_member_render_contact_messages_list();
    }
}

if (!function_exists('mj_member_render_contact_messages_list')) {
    function mj_member_render_contact_messages_list() {
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $target_filter = isset($_GET['target_type']) ? sanitize_key(wp_unslash($_GET['target_type'])) : '';
        $assigned_filter = isset($_GET['assigned_to']) ? (int) $_GET['assigned_to'] : 0;
        $search_query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $read_state = isset($_GET['read_state']) ? sanitize_key(wp_unslash($_GET['read_state'])) : '';
        $allowed_read_states = array('', 'unread', 'read');
        if (!in_array($read_state, $allowed_read_states, true)) {
            $read_state = '';
        }
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;

        $query_args = array(
            'status' => $status_filter,
            'target_type' => $target_filter,
            'assigned_to' => $assigned_filter,
            'search' => $search_query,
            'paged' => $paged,
            'per_page' => $per_page,
            'order' => 'DESC',
            'orderby' => 'created_at',
            'read_state' => $read_state,
        );

        $messages = MjContactMessages::query($query_args);
        $total_items = MjContactMessages::count($query_args);
        $total_pages = (int) ceil($total_items / $per_page);

        $status_labels = MjContactMessages::get_status_labels();
        $target_labels = MjContactMessages::get_target_labels();
        $notices = mj_member_contact_messages_get_notice();

        $current_url = remove_query_arg(array('notice', 'updated', 'deleted'));
        $current_request = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        $base_count_args = array(
            'status' => $status_filter,
            'target_type' => $target_filter,
            'assigned_to' => $assigned_filter,
            'search' => $search_query,
        );

        $total_all = MjContactMessages::count($base_count_args);
        $total_unread = MjContactMessages::count(array_merge($base_count_args, array('read_state' => 'unread')));
        $total_read = MjContactMessages::count(array_merge($base_count_args, array('read_state' => 'read')));

        $list_base_url = remove_query_arg(array('read_state', 'paged'), $current_url);
        $all_url = remove_query_arg('read_state', $list_base_url);
        $unread_url = add_query_arg('read_state', 'unread', $list_base_url);
        $read_url = add_query_arg('read_state', 'read', $list_base_url);

        require_once ABSPATH . 'wp-admin/includes/template.php';
        ?>
        <div class="wrap">
            <style>
                .mj-contact-message-indicator {
                    display: inline-block;
                    width: 8px;
                    height: 8px;
                    border-radius: 999px;
                    background: #ef4444;
                    margin-right: 6px;
                    vertical-align: middle;
                }

                .wp-list-table tr.unread td {
                    font-weight: 600;
                }

                .mj-contact-message-filters .components-base-control__field {
                    margin: 0;
                }

                .mj-contact-message-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    align-items: center;
                }

                .mj-contact-message-actions form {
                    display: inline-flex;
                    margin: 0;
                }
            </style>
            <ul class="subsubsub" style="margin-top:8px;">
                <li>
                    <a href="<?php echo esc_url($all_url); ?>" class="<?php echo $read_state === '' ? 'current' : ''; ?>">
                        <?php esc_html_e('Tous', 'mj-member'); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($total_all)); ?>)</span>
                    </a>
                    |
                </li>
                <li>
                    <a href="<?php echo esc_url($unread_url); ?>" class="<?php echo $read_state === 'unread' ? 'current' : ''; ?>">
                        <?php esc_html_e('Non lus', 'mj-member'); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($total_unread)); ?>)</span>
                    </a>
                    |
                </li>
                <li>
                    <a href="<?php echo esc_url($read_url); ?>" class="<?php echo $read_state === 'read' ? 'current' : ''; ?>">
                        <?php esc_html_e('Lus', 'mj-member'); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($total_read)); ?>)</span>
                    </a>
                </li>
            </ul>
            <h1 class="wp-heading-inline"><?php esc_html_e('Messages de contact', 'mj-member'); ?></h1>
            <?php if (!empty($notices)) : ?>
                <hr class="wp-header-end">
                <?php foreach ($notices as $notice) : ?>
                    <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                        <p><?php echo esc_html($notice['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <hr class="wp-header-end">
            <?php endif; ?>

            <form method="get" class="mj-member-contact-filters mj-contact-message-filters" style="margin-bottom:15px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="page" value="mj_contact_messages">
                <div>
                    <label for="mj-contact-status" class="screen-reader-text"><?php esc_html_e('Filtrer par statut', 'mj-member'); ?></label>
                    <select id="mj-contact-status" name="status">
                        <option value=""><?php esc_html_e('Tous les statuts', 'mj-member'); ?></option>
                        <?php foreach ($status_labels as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"<?php selected($status_filter, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="mj-contact-target" class="screen-reader-text"><?php esc_html_e('Filtrer par destinataire', 'mj-member'); ?></label>
                    <select id="mj-contact-target" name="target_type">
                        <option value=""><?php esc_html_e('Toute l\'équipe', 'mj-member'); ?></option>
                        <?php foreach ($target_labels as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"<?php selected($target_filter, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="mj-contact-assigned" class="screen-reader-text"><?php esc_html_e('Filtrer par assignation', 'mj-member'); ?></label>
                    <select id="mj-contact-assigned" name="assigned_to">
                        <option value="0"><?php esc_html_e('Toutes les affectations', 'mj-member'); ?></option>
                        <?php $assignable = mj_member_get_contact_assignable_users(); ?>
                        <?php foreach ($assignable as $user) : ?>
                            <option value="<?php echo esc_attr($user['id']); ?>"<?php selected($assigned_filter, $user['id']); ?>><?php echo esc_html($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="mj-contact-read-state" class="screen-reader-text"><?php esc_html_e('Filtrer par lecture', 'mj-member'); ?></label>
                    <select id="mj-contact-read-state" name="read_state">
                        <option value=""><?php esc_html_e('Tous les messages', 'mj-member'); ?></option>
                        <option value="unread"<?php selected($read_state, 'unread'); ?>><?php esc_html_e('Non lus', 'mj-member'); ?></option>
                        <option value="read"<?php selected($read_state, 'read'); ?>><?php esc_html_e('Lus', 'mj-member'); ?></option>
                    </select>
                </div>
                <div>
                    <label for="mj-contact-search" class="screen-reader-text"><?php esc_html_e('Recherche', 'mj-member'); ?></label>
                    <input type="search" id="mj-contact-search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Recherche…', 'mj-member'); ?>">
                </div>
                <div>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filtrer', 'mj-member'); ?></button>
                    <a href="<?php echo esc_url(remove_query_arg(array('status', 'target_type', 'assigned_to', 'read_state', 's', 'paged'))); ?>" class="button"><?php esc_html_e('Réinitialiser', 'mj-member'); ?></a>
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Sujet', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Expéditeur', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Destinataire', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Assigné à', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Reçu le', 'mj-member'); ?></th>
                        <th scope="col"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('Aucun message pour ces critères.', 'mj-member'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($messages as $message) :
                            $status_key = isset($message->status) ? sanitize_key($message->status) : MjContactMessages::STATUS_NEW;
                            $status_label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key;
                            $subject = isset($message->subject) && $message->subject !== '' ? $message->subject : __('(Sans sujet)', 'mj-member');
                            $sender = isset($message->sender_name) ? $message->sender_name : '';
                            $sender_email = isset($message->sender_email) ? $message->sender_email : '';
                            $target_label = isset($message->target_label) && $message->target_label !== '' ? $message->target_label : __('Tous', 'mj-member');
                            $assigned = '';
                            if (!empty($message->assigned_to)) {
                                $assigned_user = get_user_by('id', (int) $message->assigned_to);
                                if ($assigned_user) {
                                    $assigned = $assigned_user->display_name;
                                }
                            }
                            $created_at = isset($message->created_at) ? strtotime((string) $message->created_at) : false;
                            $view_url = add_query_arg(array('action' => 'view', 'message' => (int) $message->id), $current_url);
                            $is_unread = MjContactMessages::is_unread($message);
                            $row_class = $is_unread ? 'unread' : '';
                            ?>
                            <tr<?php echo $row_class !== '' ? ' class="' . esc_attr($row_class) . '"' : ''; ?>>
                                <td>
                                    <?php if ($is_unread) : ?>
                                        <span class="mj-contact-message-indicator" aria-hidden="true"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Message non lu', 'mj-member'); ?></span>
                                    <?php endif; ?>
                                    <strong><?php echo esc_html($status_label); ?></strong>
                                </td>
                                <td><a href="<?php echo esc_url($view_url); ?>"><?php echo esc_html($subject); ?></a></td>
                                <td>
                                    <?php echo esc_html($sender); ?><br>
                                    <a href="mailto:<?php echo esc_attr($sender_email); ?>"><?php echo esc_html($sender_email); ?></a>
                                </td>
                                <td><?php echo esc_html($target_label); ?></td>
                                <td><?php echo $assigned !== '' ? esc_html($assigned) : '&mdash;'; ?></td>
                                <td>
                                    <?php if ($created_at) : ?>
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $created_at)); ?>
                                    <?php else : ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <?php
                                $toggle_state = $is_unread ? 'read' : 'unread';
                                $toggle_label = $is_unread ? __('Marquer comme lu', 'mj-member') : __('Marquer comme non lu', 'mj-member');
                                $toggle_nonce = wp_create_nonce('mj-member-toggle-contact-message-read-' . (int) $message->id);
                                $delete_nonce = wp_create_nonce('mj-member-delete-contact-message-' . (int) $message->id);
                                ?>
                                <td>
                                    <div class="mj-contact-message-actions">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="mj_member_toggle_contact_message_read">
                                            <input type="hidden" name="message_id" value="<?php echo esc_attr((int) $message->id); ?>">
                                            <input type="hidden" name="target_state" value="<?php echo esc_attr($toggle_state); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($current_request); ?>">
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($toggle_nonce); ?>">
                                            <button type="submit" class="button button-small"><?php echo esc_html($toggle_label); ?></button>
                                        </form>
                                        <a class="button button-secondary" href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('Consulter', 'mj-member'); ?></a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Supprimer définitivement ce message ?', 'mj-member')); ?>');">
                                            <input type="hidden" name="action" value="mj_member_delete_contact_message">
                                            <input type="hidden" name="message_id" value="<?php echo esc_attr((int) $message->id); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($current_request); ?>">
                                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($delete_nonce); ?>">
                                            <button type="submit" class="button button-small delete"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo; Précédent', 'mj-member'),
                            'next_text' => __('Suivant &raquo;', 'mj-member'),
                            'total' => $total_pages,
                            'current' => $paged,
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mj_member_render_contact_message_detail')) {
    function mj_member_render_contact_message_detail($message_id) {
        $message = MjContactMessages::get($message_id);
        if (!$message) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Message introuvable.', 'mj-member'));
            return;
        }

        if (MjContactMessages::is_unread($message)) {
            MjContactMessages::mark_as_read($message_id);
            $message = MjContactMessages::get($message_id);
        }

        $status_labels = MjContactMessages::get_status_labels();
        $status_key = isset($message->status) ? sanitize_key($message->status) : MjContactMessages::STATUS_NEW;
        $status_label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key;
        $assigned_user = null;
        if (!empty($message->assigned_to)) {
            $assigned_user = get_user_by('id', (int) $message->assigned_to);
        }

        $is_read = isset($message->is_read) ? (int) $message->is_read === 1 : false;
        $sender_email = isset($message->sender_email) ? sanitize_email((string) $message->sender_email) : '';
        $sender_name = isset($message->sender_name) ? sanitize_text_field((string) $message->sender_name) : '';
        $raw_subject = isset($message->subject) ? sanitize_text_field((string) $message->subject) : '';
        $reply_subject_default = $raw_subject !== '' ? sprintf(__('Re: %s', 'mj-member'), $raw_subject) : __('Réponse à votre message', 'mj-member');
        $detail_redirect = add_query_arg(array(
            'page' => 'mj_contact_messages',
            'action' => 'view',
            'message' => $message_id,
        ), admin_url('admin.php'));
        $toggle_nonce = wp_create_nonce('mj-member-toggle-contact-message-read-' . $message_id);
        $toggle_target_state = $is_read ? 'unread' : 'read';
        $toggle_label = $is_read ? __('Marquer comme non lu', 'mj-member') : __('Marquer comme lu', 'mj-member');

        $activity = MjContactMessages::get_activity_entries($message);
        $redirect_list = admin_url('admin.php?page=mj_contact_messages');
        $nonce = wp_create_nonce('mj-member-update-contact-message-' . $message_id);
        $reply_nonce = wp_create_nonce('mj-member-reply-contact-message-' . $message_id);
        $delete_nonce = wp_create_nonce('mj-member-delete-contact-message-' . $message_id);
        $delete_redirect = admin_url('admin.php?page=mj_contact_messages');

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Message de contact', 'mj-member'); ?></h1>
            <a href="<?php echo esc_url($redirect_list); ?>" class="page-title-action"><?php esc_html_e('Retour à la liste', 'mj-member'); ?></a>
            <hr class="wp-header-end">

            <div class="mj-contact-message-card" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px;">
                <section class="mj-contact-message-summary" style="background:#fff; padding:20px; border:1px solid #e2e8f0; border-radius:6px;">
                    <h2><?php esc_html_e('Résumé', 'mj-member'); ?></h2>
                    <p><strong><?php esc_html_e('Statut', 'mj-member'); ?> :</strong> <?php echo esc_html($status_label); ?></p>
                    <p><strong><?php esc_html_e('Reçu le', 'mj-member'); ?> :</strong> <?php echo isset($message->created_at) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->created_at))) : '&mdash;'; ?></p>
                    <p><strong><?php esc_html_e('Dernière mise à jour', 'mj-member'); ?> :</strong> <?php echo isset($message->updated_at) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->updated_at))) : '&mdash;'; ?></p>
                    <p style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
                        <span><strong><?php esc_html_e('Lecture', 'mj-member'); ?> :</strong> <?php echo $is_read ? esc_html__('Lu', 'mj-member') : esc_html__('Non lu', 'mj-member'); ?></span>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex; margin:0;">
                            <input type="hidden" name="action" value="mj_member_toggle_contact_message_read">
                            <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                            <input type="hidden" name="target_state" value="<?php echo esc_attr($toggle_target_state); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($detail_redirect); ?>">
                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($toggle_nonce); ?>">
                            <button type="submit" class="button button-small"><?php echo esc_html($toggle_label); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex; margin:0;" onsubmit="return confirm('<?php echo esc_js(__('Supprimer définitivement ce message ?', 'mj-member')); ?>');">
                            <input type="hidden" name="action" value="mj_member_delete_contact_message">
                            <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($delete_redirect); ?>">
                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($delete_nonce); ?>">
                            <button type="submit" class="button button-small delete"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                        </form>
                    </p>
                    <p><strong><?php esc_html_e('Assigné à', 'mj-member'); ?> :</strong> <?php echo $assigned_user ? esc_html($assigned_user->display_name) : '&mdash;'; ?></p>
                    <p><strong><?php esc_html_e('Destinataire', 'mj-member'); ?> :</strong> <?php echo isset($message->target_label) ? esc_html($message->target_label) : '&mdash;'; ?></p>
                    <?php if (!empty($message->source_url)) : ?>
                        <p><strong><?php esc_html_e('Page d’origine', 'mj-member'); ?> :</strong> <a href="<?php echo esc_url($message->source_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($message->source_url); ?></a></p>
                    <?php endif; ?>
                </section>

                <section class="mj-contact-message-content" style="background:#fff; padding:20px; border:1px solid #e2e8f0; border-radius:6px;">
                    <h2><?php esc_html_e('Message', 'mj-member'); ?></h2>
                    <p><strong><?php esc_html_e('Expéditeur', 'mj-member'); ?> :</strong> <?php echo esc_html($message->sender_name); ?> &lt;<a href="mailto:<?php echo esc_attr($message->sender_email); ?>"><?php echo esc_html($message->sender_email); ?></a>&gt;</p>
                    <p><strong><?php esc_html_e('Sujet', 'mj-member'); ?> :</strong> <?php echo $message->subject !== '' ? esc_html($message->subject) : esc_html__('(Sans sujet)', 'mj-member'); ?></p>
                    <hr>
                    <div class="mj-contact-message-body" style="white-space:pre-wrap; line-height:1.6;">
                        <?php echo wpautop(wp_kses_post($message->message)); ?>
                    </div>
                </section>

                <section class="mj-contact-message-update" style="background:#fff; padding:20px; border:1px solid #e2e8f0; border-radius:6px;">
                    <h2><?php esc_html_e('Gestion du ticket', 'mj-member'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="mj_member_update_contact_message">
                        <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

                        <p>
                            <label for="mj-contact-status-field"><strong><?php esc_html_e('Statut', 'mj-member'); ?></strong></label><br>
                            <select id="mj-contact-status-field" name="status">
                                <?php foreach ($status_labels as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"<?php selected($status_key, $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p>
                            <label for="mj-contact-assigned-field"><strong><?php esc_html_e('Assigné à', 'mj-member'); ?></strong></label><br>
                            <select id="mj-contact-assigned-field" name="assigned_to">
                                <option value="0"><?php esc_html_e('Non assigné', 'mj-member'); ?></option>
                                <?php $assignable = mj_member_get_contact_assignable_users(); ?>
                                <?php foreach ($assignable as $user) : ?>
                                    <option value="<?php echo esc_attr($user['id']); ?>"<?php selected($assigned_user ? $assigned_user->ID : 0, $user['id']); ?>><?php echo esc_html($user['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p>
                            <label for="mj-contact-read-field"><strong><?php esc_html_e('État de lecture', 'mj-member'); ?></strong></label><br>
                            <select id="mj-contact-read-field" name="is_read">
                                <option value="0"<?php selected(!$is_read); ?>><?php esc_html_e('Marquer comme non lu', 'mj-member'); ?></option>
                                <option value="1"<?php selected($is_read); ?>><?php esc_html_e('Marquer comme lu', 'mj-member'); ?></option>
                            </select>
                        </p>

                        <p>
                            <label for="mj-contact-note-field"><strong><?php esc_html_e('Ajouter une note interne', 'mj-member'); ?></strong></label><br>
                            <textarea id="mj-contact-note-field" name="internal_note" rows="4" style="width:100%;"></textarea>
                        </p>

                        <p>
                            <button type="submit" class="button button-primary"><?php esc_html_e('Enregistrer', 'mj-member'); ?></button>
                        </p>
                    </form>
                </section>

                <section class="mj-contact-message-reply" style="background:#fff; padding:20px; border:1px solid #e2e8f0; border-radius:6px;">
                    <h2><?php esc_html_e('Réponse rapide', 'mj-member'); ?></h2>
                    <?php if ($sender_email !== '') : ?>
                        <p><?php printf(esc_html__('Destinataire : %1$s (%2$s)', 'mj-member'), esc_html($sender_name !== '' ? $sender_name : $sender_email), esc_html($sender_email)); ?></p>
                    <?php else : ?>
                        <p><?php esc_html_e("Ce message ne comporte pas d'adresse email valide.", 'mj-member'); ?></p>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="mj_member_reply_contact_message">
                        <input type="hidden" name="message_id" value="<?php echo esc_attr($message_id); ?>">
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($reply_nonce); ?>">

                        <p>
                            <label for="mj-contact-reply-subject"><strong><?php esc_html_e('Sujet de la réponse', 'mj-member'); ?></strong></label><br>
                            <input type="text" id="mj-contact-reply-subject" name="reply_subject" value="<?php echo esc_attr($reply_subject_default); ?>" class="regular-text" style="width:100%;" required>
                        </p>

                        <p>
                            <label for="mj-contact-reply-body"><strong><?php esc_html_e('Message', 'mj-member'); ?></strong></label><br>
                            <textarea id="mj-contact-reply-body" name="reply_body" rows="6" style="width:100%;" required></textarea>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="reply_copy" value="1">
                                <?php esc_html_e("M'envoyer une copie (BCC).", 'mj-member'); ?>
                            </label>
                        </p>

                        <p>
                            <button type="submit" class="button button-primary" <?php disabled($sender_email === ''); ?>><?php esc_html_e('Envoyer la réponse', 'mj-member'); ?></button>
                        </p>
                    </form>
                </section>

                <section class="mj-contact-message-activity" style="grid-column:1 / -1; background:#fff; padding:20px; border:1px solid #e2e8f0; border-radius:6px;">
                    <h2><?php esc_html_e('Historique', 'mj-member'); ?></h2>
                    <?php if (empty($activity)) : ?>
                        <p><?php esc_html_e('Aucune activité enregistrée.', 'mj-member'); ?></p>
                    <?php else : ?>
                        <ul style="margin:0; padding-left:20px; list-style:none;">
                            <?php foreach ($activity as $entry) :
                                $timestamp = isset($entry['timestamp']) ? strtotime($entry['timestamp']) : false;
                                $user_label = '';
                                if (!empty($entry['user_id'])) {
                                    $user = get_user_by('id', (int) $entry['user_id']);
                                    if ($user) {
                                        $user_label = $user->display_name;
                                    }
                                }
                                $meta = isset($entry['meta']) && is_array($entry['meta']) ? $entry['meta'] : array();
                                $body_raw = isset($meta['body']) ? (string) $meta['body'] : '';
                                $body_html = $body_raw !== '' ? wpautop(wp_kses_post($body_raw)) : '';
                                $author_name = isset($meta['author_name']) ? sanitize_text_field((string) $meta['author_name']) : '';
                                $author_email = isset($meta['author_email']) ? sanitize_email((string) $meta['author_email']) : '';
                                $has_message = ($body_html !== '' || $author_name !== '' || $author_email !== '');
                                ?>
                                <li style="margin-bottom:18px; border-bottom:1px solid #e2e8f0; padding-bottom:18px;">
                                    <div style="display:flex; flex-direction:column; gap:6px;">
                                        <div style="font-size:13px; color:#4b5563;">
                                            <strong><?php echo $timestamp ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)) : '&mdash;'; ?></strong>
                                            <?php if ($user_label !== '') : ?>
                                                — <?php echo esc_html($user_label); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['note'])) : ?>
                                                <br><?php echo esc_html($entry['note']); ?>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($has_message) : ?>
                                            <div style="background:#f8fafc; border:1px solid #dbeafe; border-radius:4px; padding:12px;">
                                                <?php if ($author_name !== '' || $author_email !== '') : ?>
                                                    <p style="margin:0 0 8px 0; font-weight:600; color:#1f2937;">
                                                        <?php echo esc_html($author_name !== '' ? $author_name : $author_email); ?>
                                                        <?php if ($author_name !== '' && $author_email !== '') : ?>
                                                            <span style="font-weight:400; color:#6b7280;">&lt;<?php echo esc_html($author_email); ?>&gt;</span>
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if ($body_html !== '') : ?>
                                                    <div style="color:#111827; line-height:1.6;">
                                                        <?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                    </div>
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
        </div>
        <?php
    }
}

if (!function_exists('mj_member_handle_contact_message_update')) {
    function mj_member_handle_contact_message_update() {
        $contactCapability = Config::contactCapability();

        if (!current_user_can($contactCapability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        if ($message_id <= 0) {
            wp_safe_redirect(add_query_arg('notice', 'error', admin_url('admin.php?page=mj_contact_messages')));
            exit;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mj-member-update-contact-message-' . $message_id)) {
            wp_safe_redirect(add_query_arg('notice', 'error', admin_url('admin.php?page=mj_contact_messages&action=view&message=' . $message_id)));
            exit;
        }

        $message = MjContactMessages::get($message_id);
        if (!$message) {
            wp_safe_redirect(add_query_arg('notice', 'error', admin_url('admin.php?page=mj_contact_messages')));
            exit;
        }

        $status_labels = MjContactMessages::get_status_labels();
        $new_status = isset($_POST['status']) ? MjContactMessages::sanitize_status(wp_unslash($_POST['status'])) : MjContactMessages::STATUS_NEW;
        $new_assigned = isset($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : 0;
        $note = isset($_POST['internal_note']) ? sanitize_text_field(wp_unslash($_POST['internal_note'])) : '';
        $new_read_flag = isset($_POST['is_read']) ? (int) $_POST['is_read'] : (isset($message->is_read) ? (int) $message->is_read : 0);
        $new_read_flag = $new_read_flag > 0 ? 1 : 0;

        $updates = array();
        $current_status = isset($message->status) ? sanitize_key($message->status) : MjContactMessages::STATUS_NEW;
        $current_assigned = isset($message->assigned_to) ? (int) $message->assigned_to : 0;
        $current_read_flag = isset($message->is_read) ? (int) $message->is_read : 0;

        $status_changed = ($new_status !== $current_status);
        $assigned_changed = ($new_assigned !== $current_assigned);
        $read_changed = ($new_read_flag !== $current_read_flag);

        if ($status_changed) {
            $updates['status'] = $new_status;
        }

        if ($assigned_changed) {
            $updates['assigned_to'] = $new_assigned;
        }

        if ($read_changed) {
            $updates['is_read'] = $new_read_flag;
        }

        if (!empty($updates)) {
            $result = MjContactMessages::update($message_id, $updates);
            if (is_wp_error($result)) {
                wp_safe_redirect(add_query_arg('notice', 'error', admin_url('admin.php?page=mj_contact_messages&action=view&message=' . $message_id)));
                exit;
            }
        }

        if ($status_changed) {
            $status_label = isset($status_labels[$new_status]) ? $status_labels[$new_status] : $new_status;
            MjContactMessages::record_activity($message_id, 'status_changed', array(
                'note' => sprintf(__('Statut mis à jour sur « %s ».', 'mj-member'), $status_label),
                'meta' => array(
                    'from' => $current_status,
                    'to' => $new_status,
                ),
            ));
        }

        if ($assigned_changed) {
            $assigned_label = __('Non assigné', 'mj-member');
            if ($new_assigned > 0) {
                $user = get_user_by('id', $new_assigned);
                if ($user) {
                    $assigned_label = $user->display_name;
                }
            }

            MjContactMessages::record_activity($message_id, 'assigned', array(
                'note' => sprintf(__('Ticket assigné à %s.', 'mj-member'), $assigned_label),
                'meta' => array(
                    'user' => $new_assigned,
                ),
            ));
        }

        if ($note !== '') {
            MjContactMessages::record_activity($message_id, 'note', array(
                'note' => $note,
            ));
        }

        if ($read_changed) {
            $read_note = $new_read_flag === 1 ? __('Message marqué comme lu.', 'mj-member') : __('Message marqué comme non lu.', 'mj-member');
            $read_action = $new_read_flag === 1 ? 'marked_read' : 'marked_unread';
            MjContactMessages::record_activity($message_id, $read_action, array(
                'note' => $read_note,
            ));
        }

        $redirect = add_query_arg(array(
            'page' => 'mj_contact_messages',
            'action' => 'view',
            'message' => $message_id,
            'notice' => 'updated',
        ), admin_url('admin.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    add_action('admin_post_mj_member_update_contact_message', 'mj_member_handle_contact_message_update');
}

if (!function_exists('mj_member_handle_contact_message_toggle_read')) {
    function mj_member_handle_contact_message_toggle_read() {
        $contactCapability = Config::contactCapability();

        if (!current_user_can($contactCapability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        $target_state = isset($_POST['target_state']) ? sanitize_key(wp_unslash($_POST['target_state'])) : '';
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        $redirect_to_raw = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        $redirect_target = $redirect_to_raw !== '' ? wp_validate_redirect($redirect_to_raw, '') : '';

        $success = false;

        if ($message_id > 0 && $nonce !== '' && wp_verify_nonce($nonce, 'mj-member-toggle-contact-message-read-' . $message_id)) {
            $message = MjContactMessages::get($message_id);

            if ($message) {
                $current_flag = isset($message->is_read) ? (int) $message->is_read : 0;
                if ($target_state === 'read') {
                    $new_flag = 1;
                } elseif ($target_state === 'unread') {
                    $new_flag = 0;
                } else {
                    $new_flag = $current_flag === 1 ? 0 : 1;
                }

                if ($new_flag !== $current_flag) {
                    $result = MjContactMessages::update($message_id, array('is_read' => $new_flag));
                    if (!is_wp_error($result)) {
                        $success = true;
                        $read_note = $new_flag === 1 ? __('Message marqué comme lu.', 'mj-member') : __('Message marqué comme non lu.', 'mj-member');
                        $read_action = $new_flag === 1 ? 'marked_read' : 'marked_unread';
                        MjContactMessages::record_activity($message_id, $read_action, array(
                            'note' => $read_note,
                        ));
                    }
                } else {
                    $success = true;
                }
            }
        }

        $redirect = mj_member_contact_messages_prepare_redirect(
            $redirect_target,
            $success,
            'read-toggled',
            'error',
            'read-updated',
            'read-error',
            array('page' => 'mj_contact_messages')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    add_action('admin_post_mj_member_toggle_contact_message_read', 'mj_member_handle_contact_message_toggle_read');
}

if (!function_exists('mj_member_handle_contact_message_delete')) {
    function mj_member_handle_contact_message_delete() {
        $contactCapability = Config::contactCapability();

        if (!current_user_can($contactCapability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        $redirect_to_raw = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        $redirect_target = $redirect_to_raw !== '' ? wp_validate_redirect($redirect_to_raw, '') : '';

        $success = false;

        if ($message_id > 0 && $nonce !== '' && wp_verify_nonce($nonce, 'mj-member-delete-contact-message-' . $message_id)) {
            $message = MjContactMessages::get($message_id);
            if ($message) {
                $deleted = MjContactMessages::delete($message_id);
                if (!is_wp_error($deleted)) {
                    $success = true;
                }
            }
        }

        $redirect = mj_member_contact_messages_prepare_redirect(
            $redirect_target,
            $success,
            'deleted',
            'delete-error',
            'deleted',
            'delete-error',
            array('page' => 'mj_contact_messages')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    add_action('admin_post_mj_member_delete_contact_message', 'mj_member_handle_contact_message_delete');
}

if (!function_exists('mj_member_handle_contact_message_reply')) {
    function mj_member_handle_contact_message_reply() {
        $contactCapability = Config::contactCapability();

        if (!current_user_can($contactCapability)) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        if ($message_id <= 0) {
            $redirect = mj_member_contact_messages_prepare_redirect(
                '',
                false,
                'reply-error',
                'reply-error',
                'reply-error',
                'reply-error-missing',
                array('page' => 'mj_contact_messages')
            );

            wp_safe_redirect($redirect);
            exit;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        $redirect_to_raw = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        $redirect_target = $redirect_to_raw !== '' ? wp_validate_redirect($redirect_to_raw, '') : '';

        if (!wp_verify_nonce($nonce, 'mj-member-reply-contact-message-' . $message_id)) {
            $redirect = mj_member_contact_messages_prepare_redirect(
                $redirect_target,
                false,
                'reply-error',
                'reply-error',
                'reply-error',
                'reply-error-nonce',
                array('page' => 'mj_contact_messages', 'action' => 'view', 'message' => $message_id)
            );

            wp_safe_redirect($redirect);
            exit;
        }

        $message = MjContactMessages::get($message_id);
        if (!$message) {
            $redirect = mj_member_contact_messages_prepare_redirect(
                $redirect_target,
                false,
                'reply-error',
                'reply-error',
                'reply-error',
                'reply-error-missing',
                array('page' => 'mj_contact_messages')
            );

            wp_safe_redirect($redirect);
            exit;
        }

        $recipient_email = isset($message->sender_email) ? sanitize_email((string) $message->sender_email) : '';
        if ($recipient_email === '' || !is_email($recipient_email)) {
            $redirect = mj_member_contact_messages_prepare_redirect(
                $redirect_target,
                false,
                'reply-error',
                'reply-error',
                'reply-error',
                'reply-error-email',
                array('page' => 'mj_contact_messages', 'action' => 'view', 'message' => $message_id)
            );

            wp_safe_redirect($redirect);
            exit;
        }

        $reply_subject = isset($_POST['reply_subject']) ? sanitize_text_field(wp_unslash($_POST['reply_subject'])) : '';
        $reply_body_raw = isset($_POST['reply_body']) ? wp_kses_post(wp_unslash($_POST['reply_body'])) : '';
        if ($reply_subject === '' || trim(wp_strip_all_tags($reply_body_raw)) === '') {
            $redirect = mj_member_contact_messages_prepare_redirect(
                $redirect_target,
                false,
                'reply-error',
                'reply-error',
                'reply-error',
                'reply-error-content',
                array('page' => 'mj_contact_messages', 'action' => 'view', 'message' => $message_id)
            );

            wp_safe_redirect($redirect);
            exit;
        }

        $current_user = wp_get_current_user();
        $headers = array();

        $from_settings = mj_member_contact_messages_get_from_settings();
        $default_from_email = isset($from_settings['email']) ? $from_settings['email'] : '';
        $default_from_name = isset($from_settings['name']) ? $from_settings['name'] : '';

        $from_name_default = ($current_user instanceof WP_User && $current_user->display_name !== '')
            ? $current_user->display_name
            : ($default_from_name !== '' ? $default_from_name : get_bloginfo('name'));

        $from_email = apply_filters('mj_member_contact_messages_from_email', $default_from_email, $message_id, $message);
        $from_name = apply_filters('mj_member_contact_messages_from_name', $from_name_default, $message_id, $message);

        if ($from_email && is_email($from_email)) {
            $headers[] = 'From: ' . sanitize_text_field($from_name) . ' <' . sanitize_email($from_email) . '>';
        }

        if ($current_user instanceof WP_User && !empty($current_user->user_email)) {
            $reply_to_name = $current_user->display_name !== '' ? $current_user->display_name : $current_user->user_login;
            $headers[] = 'Reply-To: ' . sanitize_text_field($reply_to_name) . ' <' . sanitize_email($current_user->user_email) . '>';
        }

        $send_copy = !empty($_POST['reply_copy']);
        if ($send_copy && $current_user instanceof WP_User && !empty($current_user->user_email)) {
            $headers[] = 'Bcc: ' . sanitize_email($current_user->user_email);
        }

        $mail_sent = MjMail::send_notification_to_emails('', array($recipient_email), array(
            'fallback_subject' => $reply_subject,
            'fallback_body' => $reply_body_raw,
            'content_type' => 'text/html',
            'wrap_html' => true,
            'headers' => array_values($headers),
            'log_source' => 'contact_message_reply',
            'context' => array(
                'contact_message_id' => $message_id,
                'contact_reply_actor' => ($current_user instanceof WP_User) ? (int) $current_user->ID : 0,
            ),
        ));

        if (!$mail_sent) {
            $redirect = mj_member_contact_messages_prepare_redirect(
                $redirect_target,
                false,
                'reply-error',
                'reply-error',
                'reply-error',
                'reply-error-mail',
                array('page' => 'mj_contact_messages', 'action' => 'view', 'message' => $message_id)
            );

            wp_safe_redirect($redirect);
            exit;
        }

        MjContactMessages::update($message_id, array('is_read' => 1));

        $actor_label = ($current_user instanceof WP_User && $current_user->display_name !== '') ? $current_user->display_name : '';
        $note = $actor_label !== '' ? sprintf(__('Réponse envoyée par %s.', 'mj-member'), $actor_label) : __('Réponse envoyée.', 'mj-member');

        MjContactMessages::record_activity($message_id, 'reply_sent', array(
            'note' => $note,
            'meta' => array(
                'subject' => $reply_subject,
                'body' => $reply_body_raw,
                'author_name' => $actor_label,
            ),
        ));

        $redirect = mj_member_contact_messages_prepare_redirect(
            $redirect_target,
            true,
            'reply-sent',
            'reply-error',
            'reply-sent',
            'reply-error',
            array('page' => 'mj_contact_messages', 'action' => 'view', 'message' => $message_id)
        );

        wp_safe_redirect($redirect);
        exit;
    }

    add_action('admin_post_mj_member_reply_contact_message', 'mj_member_handle_contact_message_reply');
}

if (!function_exists('mj_member_get_contact_assignable_users')) {
    /**
     * @return array<int,array{id:int,name:string}>
     */
    function mj_member_get_contact_assignable_users() {
        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name'),
        ));

        $assignable = array();
        $contactCapability = Config::contactCapability();
        foreach ($users as $user) {
            if (!($user instanceof WP_User)) {
                continue;
            }

            if (user_can($user, 'manage_options') || ($contactCapability !== '' && user_can($user, $contactCapability))) {
                $assignable[] = array(
                    'id' => (int) $user->ID,
                    'name' => $user->display_name,
                );
            }
        }

        return $assignable;
    }
}

if (!function_exists('mj_member_contact_messages_get_notice')) {
    /**
     * @return array<int,array{type:string,message:string}>
     */
    function mj_member_contact_messages_get_notice() {
        $notice_key = isset($_GET['notice']) ? sanitize_key(wp_unslash($_GET['notice'])) : '';
        if ($notice_key === '') {
            return array();
        }

        switch ($notice_key) {
            case 'updated':
                return array(array('type' => 'success', 'message' => __('Ticket mis à jour.', 'mj-member')));
            case 'error':
                return array(array('type' => 'error', 'message' => __('Une erreur est survenue.', 'mj-member')));
            case 'read-toggled':
                return array(array('type' => 'success', 'message' => __('État de lecture mis à jour.', 'mj-member')));
            case 'reply-sent':
                return array(array('type' => 'success', 'message' => __('Réponse envoyée à l\'expéditeur.', 'mj-member')));
            case 'reply-error':
                return array(array('type' => 'error', 'message' => __('Impossible d\'envoyer la réponse. Vérifiez l\'adresse email.', 'mj-member')));
            case 'deleted':
                return array(array('type' => 'success', 'message' => __('Message supprimé.', 'mj-member')));
            case 'delete-error':
                return array(array('type' => 'error', 'message' => __('Impossible de supprimer le message.', 'mj-member')));
            default:
                return array();
        }
    }
}
