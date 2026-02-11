<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Classes\Crud\MjTestimonials;
use Mj\Member\Classes\Crud\MjTestimonialComments;
use Mj\Member\Classes\Crud\MjTestimonialReactions;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page for managing testimonials.
 */
final class TestimonialsPage
{
    public static function slug(): string
    {
        return 'mj-member-testimonials';
    }

    public static function render(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'mj-member'));
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';

        if ($action === 'view') {
            static::render_view();
            return;
        }

        static::render_list();
    }

    private static function render_list(): void
    {
        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;

        $args = array(
            'per_page' => $per_page,
            'page' => $page,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        if ($status_filter !== '') {
            $args['status'] = $status_filter;
        }

        if ($search !== '') {
            $args['search'] = $search;
        }

        $testimonials = MjTestimonials::query($args);
        $total_count = MjTestimonials::count($args);
        $total_pages = ceil($total_count / $per_page);

        $pending_count = MjTestimonials::count(array('status' => MjTestimonials::STATUS_PENDING));
        $approved_count = MjTestimonials::count(array('status' => MjTestimonials::STATUS_APPROVED));
        $rejected_count = MjTestimonials::count(array('status' => MjTestimonials::STATUS_REJECTED));

        $status_labels = MjTestimonials::get_status_labels();

        $notice = isset($_GET['notice']) ? sanitize_key(wp_unslash((string) $_GET['notice'])) : '';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Témoignages', 'mj-member'); ?></h1>
            <hr class="wp-header-end">

            <?php if ($notice === 'approved'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Témoignage approuvé avec succès.', 'mj-member'); ?></p></div>
            <?php elseif ($notice === 'rejected'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Témoignage refusé.', 'mj-member'); ?></p></div>
            <?php elseif ($notice === 'deleted'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Témoignage supprimé.', 'mj-member'); ?></p></div>
            <?php endif; ?>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . static::slug())); ?>" <?php echo $status_filter === '' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Tous', 'mj-member'); ?> <span class="count">(<?php echo esc_html((string)($pending_count + $approved_count + $rejected_count)); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . static::slug() . '&status=pending')); ?>" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('En attente', 'mj-member'); ?> <span class="count">(<?php echo esc_html((string)$pending_count); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . static::slug() . '&status=approved')); ?>" <?php echo $status_filter === 'approved' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Approuvés', 'mj-member'); ?> <span class="count">(<?php echo esc_html((string)$approved_count); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . static::slug() . '&status=rejected')); ?>" <?php echo $status_filter === 'rejected' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Refusés', 'mj-member'); ?> <span class="count">(<?php echo esc_html((string)$rejected_count); ?>)</span>
                    </a>
                </li>
            </ul>

            <form method="get" style="clear: both; margin-top: 15px;">
                <input type="hidden" name="page" value="<?php echo esc_attr(static::slug()); ?>">
                <?php if ($status_filter !== ''): ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                <?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="testimonial-search-input"><?php esc_html_e('Rechercher des témoignages', 'mj-member'); ?></label>
                    <input type="search" id="testimonial-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                    <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Rechercher', 'mj-member'); ?>">
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th><?php esc_html_e('Membre', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Contenu', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Médias', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('En avant', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Date', 'mj-member'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($testimonials)): ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('Aucun témoignage trouvé.', 'mj-member'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($testimonials as $testimonial): ?>
                            <?php
                            $member_name = '';
                            if (isset($testimonial->first_name) || isset($testimonial->last_name)) {
                                $member_name = trim((isset($testimonial->first_name) ? $testimonial->first_name : '') . ' ' . (isset($testimonial->last_name) ? $testimonial->last_name : ''));
                            }
                            if ($member_name === '') {
                                $member_name = __('Membre inconnu', 'mj-member');
                            }

                            $photos = MjTestimonials::get_photo_urls($testimonial, 'thumbnail');
                            $video = MjTestimonials::get_video_data($testimonial);
                            $content_preview = isset($testimonial->content) ? wp_trim_words(wp_strip_all_tags($testimonial->content), 20, '...') : '';
                            $status_key = isset($testimonial->status) ? $testimonial->status : 'pending';
                            $status_label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key;

                            $view_url = add_query_arg(array(
                                'page' => static::slug(),
                                'action' => 'view',
                                'id' => $testimonial->id,
                            ), admin_url('admin.php'));
                            ?>
                            <tr>
                                <td><?php echo esc_html((string)$testimonial->id); ?></td>
                                <td>
                                    <strong><?php echo esc_html($member_name); ?></strong>
                                    <?php if (isset($testimonial->email) && $testimonial->email): ?>
                                        <br><small><?php echo esc_html($testimonial->email); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($content_preview): ?>
                                        <?php echo esc_html($content_preview); ?>
                                    <?php else: ?>
                                        <em><?php esc_html_e('(aucun texte)', 'mj-member'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $media_parts = array();
                                    if (count($photos) > 0) {
                                        /* translators: %d: number of photos */
                                        $media_parts[] = sprintf(__('%d photo(s)', 'mj-member'), count($photos));
                                    }
                                    if ($video) {
                                        $media_parts[] = __('1 vidéo', 'mj-member');
                                    }
                                    echo esc_html(implode(', ', $media_parts) ?: '-');
                                    ?>
                                </td>
                                <td>
                                    <?php echo !empty($testimonial->featured) ? '⭐ ' . esc_html__('Oui', 'mj-member') : '-'; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    if ($status_key === 'pending') {
                                        $status_class = 'background: #f0c36d; color: #5a4608;';
                                    } elseif ($status_key === 'approved') {
                                        $status_class = 'background: #7ad03a; color: #1e4d0d;';
                                    } elseif ($status_key === 'rejected') {
                                        $status_class = 'background: #dd3d36; color: #fff;';
                                    }
                                    ?>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $created = isset($testimonial->created_at) ? $testimonial->created_at : '';
                                    if ($created) {
                                        echo esc_html(wp_date('d/m/Y H:i', strtotime($created)));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($view_url); ?>" class="button button-small">
                                        <?php esc_html_e('Voir', 'mj-member'); ?>
                                    </a>
                                    <?php if ($status_key === 'pending'): ?>
                                        <button type="button" class="button button-small button-primary mj-testimonial-approve" data-id="<?php echo esc_attr((string)$testimonial->id); ?>">
                                            <?php esc_html_e('Approuver', 'mj-member'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'total' => $total_pages,
                            'current' => $page,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        );
                        echo wp_kses_post(paginate_links($pagination_args));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($) {
            $('.mj-testimonial-approve').on('click', function() {
                var id = $(this).data('id');
                if (!id) return;

                if (!confirm('<?php echo esc_js(__('Approuver ce témoignage ?', 'mj-member')); ?>')) {
                    return;
                }

                $.post(ajaxurl, {
                    action: 'mj_admin_testimonial_approve',
                    id: id,
                    _wpnonce: '<?php echo esc_js(wp_create_nonce('mj_admin_testimonial')); ?>'
                }).done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || 'Erreur');
                    }
                });
            });
        });
        </script>
        <?php
    }

    private static function render_view(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            wp_die(esc_html__('Témoignage introuvable.', 'mj-member'));
        }

        $testimonial = MjTestimonials::get_by_id($id);
        if (!$testimonial) {
            wp_die(esc_html__('Témoignage introuvable.', 'mj-member'));
        }

        $member_name = '';
        if (isset($testimonial->first_name) || isset($testimonial->last_name)) {
            $member_name = trim((isset($testimonial->first_name) ? $testimonial->first_name : '') . ' ' . (isset($testimonial->last_name) ? $testimonial->last_name : ''));
        }
        if ($member_name === '') {
            $member_name = __('Membre inconnu', 'mj-member');
        }

        $photos = MjTestimonials::get_photo_urls($testimonial, 'medium');
        $video = MjTestimonials::get_video_data($testimonial);
        $link_preview = MjTestimonials::get_link_preview($testimonial);
        $status_labels = MjTestimonials::get_status_labels();
        $status_key = isset($testimonial->status) ? $testimonial->status : 'pending';
        $status_label = isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key;

        // Get comments and reactions
        $comments = MjTestimonialComments::get_for_testimonial($id, array('per_page' => 100));
        $reactions_summary = MjTestimonialReactions::get_summary($id);

        $back_url = admin_url('admin.php?page=' . static::slug());
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Témoignage', 'mj-member'); ?> #<?php echo esc_html((string)$testimonial->id); ?>
                <a href="<?php echo esc_url($back_url); ?>" class="page-title-action"><?php esc_html_e('Retour à la liste', 'mj-member'); ?></a>
            </h1>
            <hr class="wp-header-end">

            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
                <div class="postbox" style="margin-top: 0;">
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Membre', 'mj-member'); ?></th>
                                <td>
                                    <strong><?php echo esc_html($member_name); ?></strong>
                                    <?php if (isset($testimonial->email) && $testimonial->email): ?>
                                        <br><small><?php echo esc_html($testimonial->email); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Statut', 'mj-member'); ?></th>
                                <td>
                                    <?php
                                    $status_class = '';
                                    if ($status_key === 'pending') {
                                        $status_class = 'background: #f0c36d; color: #5a4608;';
                                    } elseif ($status_key === 'approved') {
                                        $status_class = 'background: #7ad03a; color: #1e4d0d;';
                                    } elseif ($status_key === 'rejected') {
                                        $status_class = 'background: #dd3d36; color: #fff;';
                                    }
                                    ?>
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: 500; <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Date de création', 'mj-member'); ?></th>
                                <td>
                                    <?php
                                    $created = isset($testimonial->created_at) ? $testimonial->created_at : '';
                                    echo $created ? esc_html(wp_date('d/m/Y à H:i', strtotime($created))) : '-';
                                    ?>
                                </td>
                            </tr>
                            <?php if (isset($testimonial->reviewed_at) && $testimonial->reviewed_at): ?>
                            <tr>
                                <th><?php esc_html_e('Date de révision', 'mj-member'); ?></th>
                                <td><?php echo esc_html(wp_date('d/m/Y à H:i', strtotime($testimonial->reviewed_at))); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($status_key === 'rejected' && isset($testimonial->rejection_reason) && $testimonial->rejection_reason): ?>
                            <tr>
                                <th><?php esc_html_e('Raison du refus', 'mj-member'); ?></th>
                                <td><?php echo esc_html($testimonial->rejection_reason); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><?php esc_html_e('Contenu', 'mj-member'); ?></th>
                                <td>
                                    <div id="mj-testimonial-content-display" style="background: #f9f9f9; padding: 15px; border-radius: 6px; border-left: 4px solid #0073aa; margin-bottom: 10px;">
                                        <?php if (isset($testimonial->content) && $testimonial->content): ?>
                                            <?php echo wp_kses_post(wpautop($testimonial->content)); ?>
                                        <?php else: ?>
                                            <em><?php esc_html_e('(aucun texte)', 'mj-member'); ?></em>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button button-small" id="mj-edit-content-btn" style="margin-top: 10px;">
                                        <?php esc_html_e('Éditer le contenu', 'mj-member'); ?>
                                    </button>
                                    <textarea id="mj-testimonial-content-edit" style="display: none; width: 100%; min-height: 150px; margin-top: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"><?php echo isset($testimonial->content) ? esc_textarea($testimonial->content) : ''; ?></textarea>
                                    <div id="mj-edit-content-buttons" style="display: none; margin-top: 10px;">
                                        <button type="button" class="button button-primary" id="mj-save-content-btn"><?php esc_html_e('Enregistrer', 'mj-member'); ?></button>
                                        <button type="button" class="button" id="mj-cancel-content-btn"><?php esc_html_e('Annuler', 'mj-member'); ?></button>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($link_preview && !empty($link_preview['url'])): ?>
                            <tr>
                                <th><?php esc_html_e('Lien associé', 'mj-member'); ?></th>
                                <td>
                                    <?php if (!empty($link_preview['is_youtube']) && !empty($link_preview['youtube_id'])): ?>
                                        <!-- YouTube -->
                                        <div style="background: #000; aspect-ratio: 16/9; border-radius: 6px; overflow: hidden; margin-bottom: 10px;">
                                            <iframe  
                                                src="https://www.youtube.com/embed/<?php echo esc_attr($link_preview['youtube_id']); ?>?rel=0" 
                                                width="100%" 
                                                height="100%" 
                                                frameborder="0" 
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                allowfullscreen>
                                            </iframe>
                                        </div>
                                        <small><strong><?php echo esc_html($link_preview['site_name']); ?></strong></small>
                                    <?php else: ?>
                                        <!-- Regular link preview -->
                                        <div style="border: 1px solid #ddd; border-radius: 6px; overflow: hidden; max-width: 400px;">
                                            <?php if (!empty($link_preview['image'])): ?>
                                                <img src="<?php echo esc_url($link_preview['image']); ?>" alt="" style="width: 100%; height: 200px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div style="padding: 10px;">
                                                <small style="color: #666;"><?php echo esc_html($link_preview['site_name']); ?></small>
                                                <div style="font-weight: 600; margin: 5px 0;"><?php echo esc_html($link_preview['title']); ?></div>
                                                <?php if (!empty($link_preview['description'])): ?>
                                                    <div style="font-size: 13px; color: #666;"><?php echo esc_html($link_preview['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <a href="<?php echo esc_url($link_preview['url']); ?>" target="_blank" rel="noopener noreferrer" style="color: #0073aa; text-decoration: none;">
                                                <?php echo esc_html(__('Voir le lien', 'mj-member')); ?> →
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($photos)): ?>
                            <tr>
                                <th><?php esc_html_e('Photos', 'mj-member'); ?></th>
                                <td>
                                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                        <?php foreach ($photos as $photo): ?>
                                            <a href="<?php echo esc_url($photo['full']); ?>" target="_blank">
                                                <img src="<?php echo esc_url($photo['thumb']); ?>" alt="" style="width: 120px; height: 120px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd;">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($video): ?>
                            <tr>
                                <th><?php esc_html_e('Vidéo', 'mj-member'); ?></th>
                                <td>
                                    <video controls style="max-width: 100%; max-height: 400px; border-radius: 6px;">
                                        <source src="<?php echo esc_url($video['url']); ?>" type="video/mp4">
                                        <?php esc_html_e('Votre navigateur ne supporte pas la lecture vidéo.', 'mj-member'); ?>
                                    </video>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <!-- Réactions -->
                    <?php if (!empty($reactions_summary['total'])): ?>
                    <div style="border-top: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
                        <h3><?php esc_html_e('Réactions', 'mj-member'); ?> (<?php echo esc_html((string)$reactions_summary['total']); ?>)</h3>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <?php foreach ($reactions_summary['top_emojis'] ?? array() as $emoji): ?>
                                <span style="font-size: 24px;"><?php echo esc_html($emoji); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($reactions_summary['names'])): ?>
                            <small style="display: block; margin-top: 10px; color: #666;">
                                <?php echo esc_html(implode(', ', array_slice($reactions_summary['names'], 0, 3))); ?>
                                <?php if (count($reactions_summary['names']) > 3): ?>
                                    <?php printf(esc_html(__('et %d autres', 'mj-member')), count($reactions_summary['names']) - 3); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Commentaires -->
                    <?php if (!empty($comments)): ?>
                    <div style="border-top: 1px solid #ddd; padding: 15px;">
                        <h3><?php esc_html_e('Commentaires', 'mj-member'); ?> (<?php echo esc_html((string)count($comments)); ?>)</h3>
                        <?php foreach ($comments as $comment): ?>
                            <?php
                            $comment_author = isset($comment->first_name) ? $comment->first_name : '';
                            if (isset($comment->last_name) && $comment->last_name) {
                                $comment_author .= ' ' . $comment->last_name;
                            }
                            if (!$comment_author) {
                                $comment_author = __('Anonyme', 'mj-member');
                            }
                            $comment_date = isset($comment->created_at) ? wp_date('d/m/Y H:i', strtotime($comment->created_at)) : '-';
                            ?>
                            <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                <strong><?php echo esc_html($comment_author); ?></strong>
                                <br><small style="color: #666;"><?php echo esc_html($comment_date); ?></small>
                                <p style="margin: 8px 0 0 0;"><?php echo wp_kses_post(wpautop($comment->content)); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="border-top: 1px solid #ddd; padding: 15px; color: #666;">
                        <em><?php esc_html_e('Aucun commentaire', 'mj-member'); ?></em>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Actions -->
                <div>
                    <div class="postbox">
                        <h3 class="handle"><?php esc_html_e('Actions', 'mj-member'); ?></h3>
                        <div class="inside">
                            <?php if ($status_key === 'pending'): ?>
                                <button type="button" class="button button-block button-primary mj-testimonial-action" data-action="approve" data-id="<?php echo esc_attr((string)$testimonial->id); ?>" style="margin-bottom: 10px;">
                                    <?php esc_html_e('✓ Approuver', 'mj-member'); ?>
                                </button>
                                <button type="button" class="button button-block mj-testimonial-action" data-action="reject" data-id="<?php echo esc_attr((string)$testimonial->id); ?>" style="margin-bottom: 10px;">
                                    <?php esc_html_e('✗ Refuser', 'mj-member'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="button button-block mj-testimonial-action" data-action="toggle-featured" data-id="<?php echo esc_attr((string)$testimonial->id); ?>" style="margin-bottom: 10px; background: <?php echo !empty($testimonial->featured) ? '#7ad03a' : '#666'; ?>; color: white; border: none;">
                                <?php echo !empty($testimonial->featured) ? '⭐ ' . esc_html__('Retirer de l\'accueil', 'mj-member') : '☆ ' . esc_html__('Mettre à l\'accueil', 'mj-member'); ?>
                            </button>

                            <button type="button" class="button button-block button-link-delete mj-testimonial-action" data-action="delete" data-id="<?php echo esc_attr((string)$testimonial->id); ?>">
                                <?php esc_html_e('Supprimer', 'mj-member'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($) {
            // Content edit toggle
            $('#mj-edit-content-btn').on('click', function() {
                $('#mj-testimonial-content-display').hide();
                $('#mj-testimonial-content-edit').show();
                $('#mj-edit-content-buttons').show();
                $(this).hide();
            });

            $('#mj-cancel-content-btn').on('click', function() {
                $('#mj-testimonial-content-display').show();
                $('#mj-testimonial-content-edit').hide();
                $('#mj-edit-content-buttons').hide();
                $('#mj-edit-content-btn').show();
            });

            $('#mj-save-content-btn').on('click', function() {
                var content = $('#mj-testimonial-content-edit').val();
                $.post(ajaxurl, {
                    action: 'mj_admin_testimonial_update_content',
                    id: <?php echo (int) $id; ?>,
                    content: content,
                    _wpnonce: '<?php echo esc_js(wp_create_nonce('mj_admin_testimonial')); ?>'
                }).done(function(response) {
                    if (response.success) {
                        $('#mj-testimonial-content-display').html(response.data.content_html).show();
                        $('#mj-testimonial-content-edit').hide();
                        $('#mj-edit-content-buttons').hide();
                        $('#mj-edit-content-btn').show();
                        alert('<?php echo esc_js(__('Contenu mis à jour avec succès !', 'mj-member')); ?>');
                    } else {
                        alert(response.data || '<?php echo esc_js(__('Erreur lors de la mise à jour', 'mj-member')); ?>');
                    }
                });
            });

            // Action buttons
            $('.mj-testimonial-action').on('click', function() {
                var action = $(this).data('action');
                var id = $(this).data('id');

                if (action === 'delete') {
                    if (!confirm('<?php echo esc_js(__('Supprimer définitivement ce témoignage ?', 'mj-member')); ?>')) {
                        return;
                    }
                } else if (action === 'reject') {
                    var reason = prompt('<?php echo esc_js(__('Raison du refus (facultatif) :', 'mj-member')); ?>');
                    if (reason === null) return;

                    $.post(ajaxurl, {
                        action: 'mj_admin_testimonial_reject',
                        id: id,
                        reason: reason,
                        _wpnonce: '<?php echo esc_js(wp_create_nonce('mj_admin_testimonial')); ?>'
                    }).done(function(response) {
                        if (response.success) {
                            location.href = '<?php echo esc_js(admin_url('admin.php?page=' . static::slug() . '&notice=rejected')); ?>';
                        } else {
                            alert(response.data || 'Erreur');
                        }
                    });
                    return;
                } else if (action === 'toggle-featured') {
                    $.post(ajaxurl, {
                        action: 'mj_admin_testimonial_toggle_featured',
                        id: id,
                        _wpnonce: '<?php echo esc_js(wp_create_nonce('mj_admin_testimonial')); ?>'
                    }).done(function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || 'Erreur');
                        }
                    });
                    return;
                } else if (action === 'approve') {
                    if (!confirm('<?php echo esc_js(__('Approuver ce témoignage ?', 'mj-member')); ?>')) {
                        return;
                    }
                }

                var ajaxAction = action === 'delete' ? 'mj_admin_testimonial_delete' : 'mj_admin_testimonial_' + action;
                var redirectNotice = action === 'delete' ? 'deleted' : (action === 'approve' ? 'approved' : 'rejected');

                $.post(ajaxurl, {
                    action: ajaxAction,
                    id: id,
                    _wpnonce: '<?php echo esc_js(wp_create_nonce('mj_admin_testimonial')); ?>'
                }).done(function(response) {
                    if (response.success) {
                        location.href = '<?php echo esc_js(admin_url('admin.php?page=' . static::slug())); ?>&notice=' + redirectNotice;
                    } else {
                        alert(response.data || 'Erreur');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
