<?php

namespace Mj\Member\Admin;

use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjMembers;
use MjNotificationTypes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajoute une meta box "Notifier les membres" sur les articles WordPress.
 */
final class PostNotifyMetaBox
{
    public static function boot(): void
    {
        add_action('add_meta_boxes', array(__CLASS__, 'registerMetaBox'));
        add_action('wp_ajax_mj_member_notify_post', array(__CLASS__, 'handleNotifyAjax'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueueScripts'));
        add_filter('post_row_actions', array(__CLASS__, 'addRowAction'), 10, 2);
        add_action('admin_footer-edit.php', array(__CLASS__, 'addListScript'));
    }

    /**
     * Ajoute un lien "Notifier" dans les actions de la liste des articles.
     *
     * @param array    $actions Les actions existantes.
     * @param \WP_Post $post    L'article.
     * @return array
     */
    public static function addRowAction($actions, $post): array
    {
        if ($post->post_type !== 'post' || $post->post_status !== 'publish') {
            return $actions;
        }

        $notified = get_post_meta($post->ID, '_mj_member_notified', true);
        $label = $notified ? '🔔 Re-notifier' : '🔔 Notifier';
        
        $actions['mj_notify'] = sprintf(
            '<a href="#" class="mj-notify-post" data-post-id="%d" data-nonce="%s" style="color: #2271b1;">%s</a>',
            $post->ID,
            wp_create_nonce('mj_member_notify_post'),
            $label
        );

        return $actions;
    }

    /**
     * Ajoute le script JavaScript pour la liste des articles.
     */
    public static function addListScript(): void
    {
        global $post_type;
        if ($post_type !== 'post') {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.mj-notify-post').on('click', function(e) {
                e.preventDefault();
                
                var link = $(this);
                var postId = link.data('post-id');
                var nonce = link.data('nonce');
                var originalText = link.text();
                
                if (!confirm('Envoyer une notification à tous les membres actifs ?')) {
                    return;
                }
                
                link.text('Envoi...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mj_member_notify_post',
                        post_id: postId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            link.text('✅ ' + response.data.message);
                            setTimeout(function() {
                                link.text('🔔 Re-notifier');
                            }, 3000);
                        } else {
                            alert('Erreur : ' + response.data.message);
                            link.text(originalText);
                        }
                    },
                    error: function() {
                        alert('Erreur de communication avec le serveur.');
                        link.text(originalText);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Enregistre la meta box sur les articles.
     */
    public static function registerMetaBox(): void
    {
        add_meta_box(
            'mj_member_notify_post',
            __('🔔 Notifier les membres', 'mj-member'),
            array(__CLASS__, 'renderMetaBox'),
            'post',
            'side',
            'high'
        );
    }

    /**
     * Affiche le contenu de la meta box.
     *
     * @param \WP_Post $post L'article en cours d'édition.
     */
    public static function renderMetaBox($post): void
    {
        // Vérifier si une notification a déjà été envoyée
        $notified = get_post_meta($post->ID, '_mj_member_notified', true);
        $notified_date = get_post_meta($post->ID, '_mj_member_notified_date', true);

        wp_nonce_field('mj_member_notify_post', 'mj_member_notify_nonce');
        ?>
        <div id="mj-member-notify-container">
            <?php if ($post->post_status !== 'publish') : ?>
                <p style="color: #666; font-style: italic;">
                    <?php esc_html_e('L\'article doit être publié avant de pouvoir notifier les membres.', 'mj-member'); ?>
                </p>
            <?php else : ?>
                <?php if ($notified) : ?>
                    <p style="color: #46b450; margin-bottom: 10px;">
                        ✅ <?php esc_html_e('Notification envoyée', 'mj-member'); ?>
                        <?php if ($notified_date) : ?>
                            <br><small><?php echo esc_html(sprintf(__('le %s', 'mj-member'), date_i18n('d/m/Y à H:i', strtotime($notified_date)))); ?></small>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                
                <button type="button" 
                        id="mj-member-notify-btn" 
                        class="button button-primary" 
                        data-post-id="<?php echo esc_attr($post->ID); ?>"
                        style="width: 100%;">
                    📰 <?php echo $notified ? esc_html__('Renvoyer la notification', 'mj-member') : esc_html__('Notifier les membres', 'mj-member'); ?>
                </button>
                
                <p style="color: #666; font-size: 12px; margin-top: 8px;">
                    <?php esc_html_e('Envoie une notification à tous les membres actifs pour leur signaler ce nouvel article.', 'mj-member'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue les scripts pour la meta box.
     *
     * @param string $hook La page admin actuelle.
     */
    public static function enqueueScripts($hook): void
    {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'post') {
            return;
        }

        wp_add_inline_script('jquery', self::getInlineScript());
    }

    /**
     * Retourne le script inline pour le bouton de notification.
     *
     * @return string
     */
    private static function getInlineScript(): string
    {
        return "
        jQuery(document).ready(function($) {
            $('#mj-member-notify-btn').on('click', function(e) {
                e.preventDefault();
                
                var btn = $(this);
                var postId = btn.data('post-id');
                var nonce = $('#mj_member_notify_nonce').val();
                
                if (!confirm('Envoyer une notification à tous les membres actifs ?')) {
                    return;
                }
                
                btn.prop('disabled', true).text('Envoi en cours...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mj_member_notify_post',
                        post_id: postId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.text('✅ ' + response.data.message);
                            setTimeout(function() {
                                btn.prop('disabled', false).text('📰 Renvoyer la notification');
                            }, 2000);
                        } else {
                            alert('Erreur : ' + response.data.message);
                            btn.prop('disabled', false).text('📰 Notifier les membres');
                        }
                    },
                    error: function() {
                        alert('Erreur de communication avec le serveur.');
                        btn.prop('disabled', false).text('📰 Notifier les membres');
                    }
                });
            });
        });
        ";
    }

    /**
     * Gère la requête AJAX pour envoyer la notification.
     */
    public static function handleNotifyAjax(): void
    {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mj_member_notify_post')) {
            wp_send_json_error(array('message' => __('Nonce invalide.', 'mj-member')));
        }

        // Vérifier les permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('ID d\'article invalide.', 'mj-member')));
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            wp_send_json_error(array('message' => __('L\'article doit être publié.', 'mj-member')));
        }

        // Récupérer tous les membres actifs
        if (!class_exists(MjMembers::class)) {
            wp_send_json_error(array('message' => __('Classe MjMembers non disponible.', 'mj-member')));
        }

        $all_members = MjMembers::get_all(array(
            'filters' => array('status' => 'actif'),
        ));

        if (empty($all_members)) {
            wp_send_json_error(array('message' => __('Aucun membre actif trouvé.', 'mj-member')));
        }

        $recipients = array();
        foreach ($all_members as $member) {
            // MemberData est un objet, pas un tableau
            $member_id = is_object($member) ? (int) $member->id : (isset($member['id']) ? (int) $member['id'] : 0);
            if ($member_id > 0) {
                $recipients[] = $member_id;
            }
        }

        if (empty($recipients)) {
            wp_send_json_error(array('message' => __('Aucun destinataire valide.', 'mj-member')));
        }

        // Construire et envoyer la notification
        $post_title = $post->post_title;
        $post_url = get_permalink($post_id);

        $notification_data = array(
            'type' => class_exists('MjNotificationTypes') ? MjNotificationTypes::POST_PUBLISHED : 'post_published',
            'title' => '📰 Nouvel article : ' . $post_title,
            'message' => 'Un nouvel article vient d\'être publié sur le site.',
            'url' => $post_url,
            'icon' => '📰',
            'context' => array(
                'post_id' => $post_id,
                'post_title' => $post_title,
            ),
        );

        if (function_exists('mj_member_record_notification')) {
            mj_member_record_notification($notification_data, $recipients);
        } else {
            wp_send_json_error(array('message' => __('Fonction de notification non disponible.', 'mj-member')));
        }

        // Marquer l'article comme notifié
        update_post_meta($post_id, '_mj_member_notified', 1);
        update_post_meta($post_id, '_mj_member_notified_date', current_time('mysql'));

        wp_send_json_success(array(
            'message' => sprintf(__('Notification envoyée à %d membres !', 'mj-member'), count($recipients)),
            'recipients_count' => count($recipients),
        ));
    }
}
