<?php
/**
 * Template du widget Témoignages - Style Facebook News Feed
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjTestimonials;
use Mj\Member\Classes\Crud\MjTestimonialReactions;
use Mj\Member\Classes\Crud\MjTestimonialComments;
use Mj\Member\Core\AssetsManager;

$settings = $widget->get_settings_for_display();
$widget_id = 'mj-testimonials-' . $widget->get_id();

// Check if Elementor preview mode
$elementor_plugin = \Elementor\Plugin::$instance;
$editor_is_edit = isset($elementor_plugin->editor) && method_exists($elementor_plugin->editor, 'is_edit_mode')
    ? (bool) $elementor_plugin->editor->is_edit_mode()
    : false;
$preview_is_active = isset($elementor_plugin->preview) && method_exists($elementor_plugin->preview, 'is_preview_mode')
    ? (bool) $elementor_plugin->preview->is_preview_mode()
    : false;
$is_preview = $editor_is_edit || $preview_is_active;

// Load assets
AssetsManager::requirePackage('testimonials');

// Parse settings
$title = isset($settings['title']) ? sanitize_text_field((string) $settings['title']) : __('Témoignages', 'mj-member');
$intro = isset($settings['intro_text']) ? wp_kses_post($settings['intro_text']) : '';
$allow_submission = !isset($settings['allow_submission']) || $settings['allow_submission'] === 'yes';
$show_list = !isset($settings['show_approved_list']) || $settings['show_approved_list'] === 'yes';
$per_page = isset($settings['per_page']) ? (int) $settings['per_page'] : 6;
$max_photos = isset($settings['max_photos']) ? (int) $settings['max_photos'] : 5;
$allow_video = !isset($settings['allow_video']) || $settings['allow_video'] === 'yes';
$layout = isset($settings['layout']) ? sanitize_key($settings['layout']) : 'grid';
$columns = isset($settings['columns']) ? (int) $settings['columns'] : 2;
$featured_only = isset($settings['featured_only']) && $settings['featured_only'] === 'yes';
$display_template = isset($settings['display_template']) ? sanitize_key($settings['display_template']) : 'feed';

// Get current member
$current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
$is_logged_in = $current_member && isset($current_member->id);
$member_id = $is_logged_in ? (int) $current_member->id : 0;
$is_animator = false;

// Check for single post mode (URL parameter)
$single_post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
$is_single_mode = $single_post_id > 0;
$single_testimonial = null;

if ($is_single_mode && !$is_preview) {
    $single_testimonial = MjTestimonials::get_by_id($single_post_id);
    // Only show if approved
    if (!$single_testimonial || $single_testimonial->status !== MjTestimonials::STATUS_APPROVED) {
        $single_testimonial = null;
        $is_single_mode = false;
    }
}

// Get approved testimonials (or all if none approved yet)
$testimonials = array();
$pending_testimonials = array(); // Testimonials pending approval
$my_pending_testimonials = array(); // Current member's pending testimonials

if ($show_list && !$is_preview) {
    if ($featured_only) {
        $testimonials = MjTestimonials::get_featured(array(
            'per_page' => $per_page,
            'page' => 1,
        ));
    } else {
        $testimonials = MjTestimonials::get_approved(array(
            'per_page' => $per_page,
            'page' => 1,
        ));
    }
    
    // Fallback: if no approved testimonials, get all (for debugging)
    if (empty($testimonials)) {
        $testimonials = MjTestimonials::query(array(
            'per_page' => $per_page,
            'page' => 1,
        ));
    }

    // Get pending testimonials for current member (always show to owner)
    if ($is_logged_in) {
        $my_pending_testimonials = MjTestimonials::query(array(
            'status' => MjTestimonials::STATUS_PENDING,
            'member_id' => $member_id,
            'per_page' => 10,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));
    }

    // Get all pending testimonials if user is animator
    $is_animator = $is_logged_in && isset($current_member->role) && in_array($current_member->role, array('animateur', 'coordinateur'), true);
    if ($is_animator) {
        $pending_testimonials = MjTestimonials::query(array(
            'status' => MjTestimonials::STATUS_PENDING,
            'per_page' => $per_page,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));
    }
}

// Preview data
if ($is_preview) {
    $testimonials = array(
        (object) array(
            'id' => 1,
            'member_id' => 0,
            'content' => 'La MJ c\'est vraiment un endroit génial pour se retrouver entre amis. Les animateurs sont super sympas et il y a toujours des activités fun à faire !',
            'first_name' => 'Léa',
            'last_name' => 'Martin',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'photo_ids' => '[]',
            'video_id' => null,
            'status' => MjTestimonials::STATUS_APPROVED,
        ),
        (object) array(
            'id' => 2,
            'member_id' => 0,
            'content' => 'J\'ai adoré le stage de musique l\'été dernier. J\'ai appris tellement de choses et j\'ai rencontré des gens géniaux !',
            'first_name' => 'Mathis',
            'last_name' => 'Dubois',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'photo_ids' => '[]',
            'video_id' => null,
            'status' => MjTestimonials::STATUS_APPROVED,
        ),
        (object) array(
            'id' => 3,
            'member_id' => 0,
            'content' => 'Les soirées jeux de société sont trop bien ! On rigole bien et on découvre plein de nouveaux jeux chaque semaine.',
            'first_name' => 'Camille',
            'last_name' => 'Leroy',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 weeks')),
            'photo_ids' => '[]',
            'video_id' => null,
            'status' => MjTestimonials::STATUS_APPROVED,
        ),
    );
}

// Localize data for JavaScript
$reaction_types = MjTestimonialReactions::get_reaction_types();
$localize_data = array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('mj-testimonial-submit'),
    'isLoggedIn' => $is_logged_in,
    'memberId' => $member_id,
    'perPage' => $per_page,
    'maxPhotos' => $max_photos,
    'allowVideo' => $allow_video,
    'reactionTypes' => $reaction_types,
    'i18n' => array(
        'submitSuccess' => __('Merci pour votre témoignage ! Il sera visible après validation.', 'mj-member'),
        'submitError' => __('Une erreur est survenue. Veuillez réessayer.', 'mj-member'),
        'uploading' => __('Envoi en cours...', 'mj-member'),
        'submit' => __('Envoyer mon témoignage', 'mj-member'),
        'addPhoto' => __('Ajouter une photo', 'mj-member'),
        'addVideo' => __('Enregistrer une vidéo', 'mj-member'),
        'removePhoto' => __('Supprimer', 'mj-member'),
        'maxPhotosReached' => sprintf(__('Maximum %d photos', 'mj-member'), $max_photos),
        'textPlaceholder' => __('Partagez votre expérience...', 'mj-member'),
        'loginRequired' => __('Connectez-vous pour partager votre témoignage.', 'mj-member'),
        'videoRecording' => __('Enregistrement...', 'mj-member'),
        'videoStop' => __('Arrêter', 'mj-member'),
        'videoRetake' => __('Recommencer', 'mj-member'),
        'videoUse' => __('Utiliser cette vidéo', 'mj-member'),
        'loadMore' => __('Voir plus', 'mj-member'),
        'noTestimonials' => __('Aucun témoignage pour le moment.', 'mj-member'),
        'back' => __('← Retour aux témoignages', 'mj-member'),
        'like' => __('J\'aime', 'mj-member'),
        'comment' => __('Commenter', 'mj-member'),
        'share' => __('Partager', 'mj-member'),
        'writeComment' => __('Écrire un commentaire...', 'mj-member'),
        'reply' => __('Répondre', 'mj-member'),
        'viewMoreComments' => __('Voir plus de commentaires', 'mj-member'),
        'deleteComment' => __('Supprimer', 'mj-member'),
        'andOthers' => __('et %d autres', 'mj-member'),
        'you' => __('Vous', 'mj-member'),
    ),
);

$localize_data['isSingleMode'] = $is_single_mode;
$localize_data['singlePostId'] = $single_post_id;
$localize_data['baseUrl'] = remove_query_arg('post');
$localize_data['isAnimator'] = $is_animator ?? false;
$localize_data['hasPendingApprovals'] = $is_animator && !empty($pending_testimonials);
$localize_data['featuredOnly'] = $featured_only;
$localize_data['displayTemplate'] = $display_template;

wp_localize_script('mj-member-testimonials', 'mjTestimonialsData', $localize_data);
?>

<div id="<?php echo esc_attr($widget_id); ?>" class="mj-testimonials mj-testimonials--layout-<?php echo esc_attr($layout); ?> mj-testimonials--template-<?php echo esc_attr($display_template); ?><?php echo $is_single_mode ? ' mj-testimonials--single' : ''; ?>" data-columns="<?php echo esc_attr((string)$columns); ?>">

    <?php if ($title): ?>
        <h2 class="mj-testimonials__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($intro): ?>
        <div class="mj-testimonials__intro"><?php echo wp_kses_post($intro); ?></div>
    <?php endif; ?>

    <?php if ($is_preview): ?>
        <div class="mj-testimonials__preview-notice">
            <p><?php esc_html_e('Aperçu statique : les témoignages ci-dessous sont fictifs.', 'mj-member'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($allow_submission): ?>
        <div class="mj-testimonials__form-section">
            <?php if ($is_logged_in || $is_preview): ?>
                <form class="mj-testimonials__form" data-widget-id="<?php echo esc_attr($widget_id); ?>">
                    <div class="mj-testimonials__form-content">
                        <textarea 
                            name="content" 
                            class="mj-testimonials__textarea" 
                            placeholder="<?php esc_attr_e('Partagez votre expérience à la Maison de Jeunes...', 'mj-member'); ?>"
                            rows="4"
                        ></textarea>
                    </div>

                    <div class="mj-testimonials__media-section">
                        <div class="mj-testimonials__photos-grid" data-photos="[]"></div>
                        
                        <div class="mj-testimonials__media-actions">
                            <button type="button" class="mj-btn mj-btn--secondary mj-testimonials__add-photo">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <span><?php esc_html_e('Photos / Vidéos', 'mj-member'); ?></span>
                            </button>
                            
                            <button type="button" class="mj-btn mj-btn--secondary mj-testimonials__capture-photo">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                                <span><?php esc_html_e('Prendre une photo', 'mj-member'); ?></span>
                            </button>
                            
                            <?php if ($allow_video): ?>
                                <button type="button" class="mj-btn mj-btn--secondary mj-testimonials__add-video">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <circle cx="12" cy="12" r="3" fill="currentColor"></circle>
                                    </svg>
                                    <span><?php esc_html_e('Filmer', 'mj-member'); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="mj-testimonials__camera-preview" style="display: none;">
                            <video class="mj-testimonials__camera-element" playsinline></video>
                            <div class="mj-testimonials__camera-controls">
                                <button type="button" class="mj-btn mj-btn--primary mj-testimonials__camera-capture">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg>
                                    <span><?php esc_html_e('Capturer', 'mj-member'); ?></span>
                                </button>
                                <button type="button" class="mj-btn mj-btn--ghost mj-testimonials__camera-cancel">
                                    <?php esc_html_e('Annuler', 'mj-member'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="mj-testimonials__video-preview" style="display: none;">
                            <video class="mj-testimonials__video-element" playsinline></video>
                            <div class="mj-testimonials__video-controls">
                                <button type="button" class="mj-btn mj-btn--danger mj-testimonials__video-record">
                                    <span class="mj-testimonials__video-record-dot"></span>
                                    <span><?php esc_html_e('Enregistrer', 'mj-member'); ?></span>
                                </button>
                                <button type="button" class="mj-btn mj-btn--secondary mj-testimonials__video-stop" style="display: none;">
                                    <?php esc_html_e('Arrêter', 'mj-member'); ?>
                                </button>
                                <button type="button" class="mj-btn mj-btn--ghost mj-testimonials__video-cancel">
                                    <?php esc_html_e('Annuler', 'mj-member'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="mj-testimonials__video-result" style="display: none;">
                            <video class="mj-testimonials__video-playback" controls playsinline></video>
                            <div class="mj-testimonials__video-result-actions">
                                <button type="button" class="mj-btn mj-btn--secondary mj-testimonials__video-retake">
                                    <?php esc_html_e('Recommencer', 'mj-member'); ?>
                                </button>
                                <button type="button" class="mj-btn mj-btn--danger mj-testimonials__video-remove">
                                    <?php esc_html_e('Supprimer', 'mj-member'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <input type="file" class="mj-testimonials__photo-input" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime" multiple style="display: none;">
                    <input type="hidden" name="photo_ids" value="[]">
                    <input type="hidden" name="video_id" value="">

                    <div class="mj-testimonials__form-footer">
                        <div class="mj-testimonials__form-status"></div>
                        <button type="submit" class="mj-btn mj-btn--primary mj-testimonials__submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                            <span><?php esc_html_e('Envoyer mon témoignage', 'mj-member'); ?></span>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="mj-testimonials__login-prompt">
                    <p><?php esc_html_e('Connectez-vous pour partager votre témoignage.', 'mj-member'); ?></p>
                    <?php
                    $login_url = function_exists('mj_member_get_login_url') ? mj_member_get_login_url() : wp_login_url();
                    ?>
                    <a href="<?php echo esc_url($login_url); ?>" class="mj-btn mj-btn--primary">
                        <?php esc_html_e('Se connecter', 'mj-member'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($is_single_mode && $single_testimonial): ?>
        <div class="mj-testimonials__single-header">
            <a href="<?php echo esc_url(remove_query_arg('post')); ?>" class="mj-btn mj-btn--ghost mj-testimonials__back-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                <span><?php esc_html_e('Retour aux témoignages', 'mj-member'); ?></span>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($show_list): ?>
        <div class="mj-testimonials__feed">
            <?php 
            // En mode single, utiliser uniquement le témoignage sélectionné
            $display_testimonials = ($is_single_mode && $single_testimonial) ? array($single_testimonial) : $testimonials;
            
            // Préparer les témoignages à afficher (approuvés + en attente du membre + tous les en attente si animateur)
            $all_display_testimonials = $display_testimonials;
            
            // Ajouter les témoignages en attente du membre
            if (!empty($my_pending_testimonials)) {
                $all_display_testimonials = array_merge($all_display_testimonials, $my_pending_testimonials);
            }
            
            // Ajouter tous les témoignages en attente si l'utilisateur est animateur
            if ($is_animator && !empty($pending_testimonials)) {
                // Éviter les doublons en vérifiant les IDs
                $existing_ids = array_map(function($t) { return (int)$t->id; }, $all_display_testimonials);
                foreach ($pending_testimonials as $pending) {
                    if (!in_array((int)$pending->id, $existing_ids, true)) {
                        $all_display_testimonials[] = $pending;
                    }
                }
            }
            
            // Trier tous les témoignages par date décroissante (plus récent d'abord)
            usort($all_display_testimonials, function($a, $b) {
                $time_a = strtotime($a->created_at ?? '0000-00-00 00:00:00');
                $time_b = strtotime($b->created_at ?? '0000-00-00 00:00:00');
                return $time_b - $time_a;
            });
            ?>
            <?php if (empty($all_display_testimonials) && !$is_preview): ?>
                <p class="mj-testimonials__empty"><?php esc_html_e('Aucun témoignage pour le moment. Soyez le premier à partager votre expérience !', 'mj-member'); ?></p>
            <?php elseif ($display_template === 'carousel-3'): ?>
                <!-- Carousel 3 columns template -->
                <div class="mj-testimonials__carousel-viewport">
                    <div class="mj-testimonials__carousel-track">
                        <?php foreach ($all_display_testimonials as $testimonial):
                            $photos = MjTestimonials::get_photo_urls($testimonial, 'medium');
                            $video = MjTestimonials::get_video_data($testimonial);
                            $link_preview = MjTestimonials::get_link_preview($testimonial);
                            $member_name = '';
                            if (isset($testimonial->first_name)) {
                                $member_name = $testimonial->first_name;
                                if (isset($testimonial->last_name) && $testimonial->last_name) {
                                    $member_name .= ' ' . mb_substr($testimonial->last_name, 0, 1) . '.';
                                }
                            }
                            $member_initial = isset($testimonial->first_name) && $testimonial->first_name ? mb_strtoupper(mb_substr($testimonial->first_name, 0, 1)) : '?';
                            $member_avatar_url = '';
                            if (!$is_preview && isset($testimonial->member_photo_id) && $testimonial->member_photo_id) {
                                $avatar_src = wp_get_attachment_image_src((int)$testimonial->member_photo_id, 'thumbnail');
                                if ($avatar_src) {
                                    $member_avatar_url = $avatar_src[0];
                                }
                            }
                            $created_ago = '';
                            if (isset($testimonial->created_at)) {
                                $created_ago = human_time_diff(strtotime($testimonial->created_at), current_time('timestamp'));
                            }
                            $reactions_summary = $is_preview ? array('counts' => array('like' => 12, 'love' => 5), 'total' => 17, 'top_emojis' => array('👍', '❤️'), 'names' => array('Marie', 'Lucas')) : MjTestimonialReactions::get_summary($testimonial->id);
                            $comment_count = $is_preview ? 3 : MjTestimonialComments::count_for_testimonial($testimonial->id);
                            $post_id = (int)$testimonial->id;
                        ?>
                            <div class="mj-carousel-card" data-post-id="<?php echo $post_id; ?>">
                                <!-- Header : avatar + nom + date -->
                                <div class="mj-carousel-card__header">
                                    <div class="mj-carousel-card__avatar">
                                        <?php if ($member_avatar_url): ?>
                                            <img src="<?php echo esc_url($member_avatar_url); ?>" alt="<?php echo esc_attr($member_name); ?>">
                                        <?php else: ?>
                                            <span class="mj-carousel-card__avatar-initial"><?php echo esc_html($member_initial); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mj-carousel-card__meta">
                                        <span class="mj-carousel-card__author"><?php echo esc_html($member_name); ?></span>
                                        <?php if ($created_ago): ?>
                                            <span class="mj-carousel-card__date"><?php printf(esc_html__('Il y a %s', 'mj-member'), esc_html($created_ago)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Contenu texte -->
                                <div class="mj-carousel-card__content">
                                    <?php if (isset($testimonial->content) && $testimonial->content): ?>
                                        <?php echo wp_kses_post(wpautop($testimonial->content)); ?>
                                    <?php endif; ?>
                                </div>

                                <!-- Photos -->
                                <?php if (!empty($photos)): ?>
                                    <div class="mj-carousel-card__media mj-carousel-card__media--photos-<?php echo min(count($photos), 4); ?>">
                                        <?php foreach (array_slice($photos, 0, 4) as $index => $photo): ?>
                                            <a href="<?php echo esc_url($photo['full']); ?>" class="mj-carousel-card__photo" data-lightbox="carousel-<?php echo $post_id; ?>">
                                                <img src="<?php echo esc_url($photo['url']); ?>" alt="" loading="lazy">
                                                <?php if ($index === 3 && count($photos) > 4): ?>
                                                    <span class="mj-carousel-card__photo-more">+<?php echo (count($photos) - 4); ?></span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Vidéo -->
                                <?php if ($video): ?>
                                    <div class="mj-carousel-card__media mj-carousel-card__media--video">
                                        <video controls playsinline preload="metadata" poster="<?php echo esc_url($video['poster']); ?>">
                                            <source src="<?php echo esc_url($video['url']); ?>" type="video/mp4">
                                        </video>
                                    </div>
                                <?php endif; ?>

                                <!-- Lien YouTube -->
                                <?php if ($link_preview && !empty($link_preview['is_youtube']) && !empty($link_preview['youtube_id'])): ?>
                                    <div class="mj-carousel-card__media mj-carousel-card__media--youtube">
                                        <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($link_preview['youtube_id']); ?>?rel=0" title="YouTube" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                    </div>
                                <?php endif; ?>

                                <!-- Réactions + commentaires résumé -->
                                <div class="mj-carousel-card__stats">
                                    <?php if ($reactions_summary['total'] > 0): ?>
                                        <span class="mj-carousel-card__reactions">
                                            <?php foreach ($reactions_summary['top_emojis'] as $emoji): ?>
                                                <span class="mj-carousel-card__reaction-emoji"><?php echo esc_html($emoji); ?></span>
                                            <?php endforeach; ?>
                                            <span class="mj-carousel-card__reactions-count"><?php echo esc_html($reactions_summary['total']); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($comment_count > 0): ?>
                                        <span class="mj-carousel-card__comments-count">
                                            <?php printf(esc_html(_n('%d commentaire', '%d commentaires', $comment_count, 'mj-member')), $comment_count); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" class="mj-testimonials__carousel-btn mj-testimonials__carousel-btn--prev" aria-label="<?php esc_attr_e('Précédent', 'mj-member'); ?>">&#8249;</button>
                <button type="button" class="mj-testimonials__carousel-btn mj-testimonials__carousel-btn--next" aria-label="<?php esc_attr_e('Suivant', 'mj-member'); ?>">&#8250;</button>
            <?php else: ?>
                <?php foreach ($all_display_testimonials as $testimonial): 
                    $photos = MjTestimonials::get_photo_urls($testimonial, 'large');
                    $video = MjTestimonials::get_video_data($testimonial);
                    $link_preview = MjTestimonials::get_link_preview($testimonial);
                    $member_name = '';
                    if (isset($testimonial->first_name)) {
                        $member_name = $testimonial->first_name;
                        if (isset($testimonial->last_name) && $testimonial->last_name) {
                            $member_name .= ' ' . mb_substr($testimonial->last_name, 0, 1) . '.';
                        }
                    }
                    $created_ago = '';
                    if (isset($testimonial->created_at)) {
                        $created_ago = human_time_diff(strtotime($testimonial->created_at), current_time('timestamp'));
                    }
                    
                    $reactions_summary = $is_preview ? array('counts' => array('like' => 12, 'love' => 5, 'haha' => 3), 'total' => 20, 'top_emojis' => array('👍', '❤️', '😂'), 'names' => array('Marie', 'Lucas', 'Emma')) : MjTestimonialReactions::get_summary($testimonial->id);
                    $member_reaction = $is_preview ? null : ($is_logged_in ? MjTestimonialReactions::get_member_reaction($testimonial->id, $member_id) : null);
                    $comment_count = $is_preview ? 4 : MjTestimonialComments::count_for_testimonial($testimonial->id);
                    $comments = $is_preview ? array() : MjTestimonialComments::get_for_testimonial($testimonial->id, array('per_page' => 3));
                    
                    $member_initial = isset($testimonial->first_name) && $testimonial->first_name ? mb_strtoupper(mb_substr($testimonial->first_name, 0, 1)) : '?';
                    $member_avatar_url = '';
                    if (!$is_preview && isset($testimonial->member_photo_id) && $testimonial->member_photo_id) {
                        $avatar_src = wp_get_attachment_image_src((int)$testimonial->member_photo_id, 'thumbnail');
                        if ($avatar_src) {
                            $member_avatar_url = $avatar_src[0];
                        }
                    }
                    
                    $post_id = (int)$testimonial->id;
                    
                    // Déterminer le statut et si c'est mon témoignage
                    $testimonial_status = isset($testimonial->status) ? $testimonial->status : MjTestimonials::STATUS_APPROVED;
                    $t_member_id = isset($testimonial->member_id) ? (int)$testimonial->member_id : 0;
                    $is_my_testimonial = $is_logged_in && ($t_member_id === $member_id);
                    $is_pending = $testimonial_status === MjTestimonials::STATUS_PENDING;
                    $show_approval_actions = $is_animator && $is_pending && !$is_my_testimonial;
                ?>
                <article class="mj-feed-post-wrapper<?php echo $is_single_mode ? ' mj-feed-post-wrapper--single' : ''; ?><?php echo $is_pending ? ' mj-feed-post-wrapper--pending' : ''; ?>" data-post-id="<?php echo $post_id; ?>" data-post-url="<?php echo esc_url(add_query_arg('post', $post_id)); ?>" data-post-status="<?php echo esc_attr($testimonial_status); ?>">
                    <div class="mj-feed-post<?php echo $is_single_mode ? ' mj-feed-post--single' : ''; ?><?php echo $is_pending ? ' mj-feed-post--pending' : ''; ?>" data-id="<?php echo $post_id; ?>">
                        <?php if ($is_pending && $is_my_testimonial): ?>
                            <div class="mj-feed-post__pending-badge">
                                <span class="mj-feed-post__pending-badge-icon">⏳</span>
                                <span class="mj-feed-post__pending-badge-text"><?php esc_html_e('En cours de validation', 'mj-member'); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_approval_actions): ?>
                            <div class="mj-feed-post__approval-panel">
                                <p class="mj-feed-post__approval-message"><?php printf(esc_html__('Témoignage en attente d\'approbation de %s', 'mj-member'), esc_html($member_name)); ?></p>
                                <div class="mj-feed-post__approval-actions">
                                    <button type="button" class="mj-btn mj-btn--success mj-feed-post__approve-btn" data-testimonial-id="<?php echo esc_attr((string)$post_id); ?>" data-action="approve-testimonial">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <span><?php esc_html_e('Approuver', 'mj-member'); ?></span>
                                    </button>
                                    <button type="button" class="mj-btn mj-btn--danger mj-feed-post__reject-btn" data-testimonial-id="<?php echo esc_attr((string)$post_id); ?>" data-action="reject-testimonial">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        <span><?php esc_html_e('Refuser', 'mj-member'); ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mj-feed-post__header">
                            <div class="mj-feed-post__avatar">
                                <?php if ($member_avatar_url): ?>
                                    <img src="<?php echo esc_url($member_avatar_url); ?>" alt="<?php echo esc_attr($member_name); ?>" class="mj-feed-post__avatar-img">
                                <?php else: ?>
                                    <span class="mj-feed-post__avatar-initial"><?php echo esc_html($member_initial); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mj-feed-post__meta">
                                <span class="mj-feed-post__author"><?php echo esc_html($member_name); ?></span>
                                <?php if ($created_ago): ?>
                                    <span class="mj-feed-post__date"><?php printf(esc_html__('Il y a %s', 'mj-member'), esc_html($created_ago)); ?> · 🌍</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (isset($testimonial->content) && $testimonial->content): ?>
                            <div class="mj-feed-post__content">
                                <?php echo wp_kses_post(wpautop($testimonial->content)); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($photos)): ?>
                            <div class="mj-feed-post__media mj-feed-post__media--photos-<?php echo min(count($photos), 5); ?>">
                                <?php foreach (array_slice($photos, 0, 5) as $index => $photo): ?>
                                    <a href="<?php echo esc_url($photo['full']); ?>" class="mj-feed-post__photo" data-lightbox="post-<?php echo $post_id; ?>">
                                        <img src="<?php echo esc_url($photo['url']); ?>" alt="" loading="lazy">
                                        <?php if ($index === 4 && count($photos) > 5): ?>
                                            <span class="mj-feed-post__photo-more">+<?php echo (count($photos) - 5); ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($video): ?>
                            <div class="mj-feed-post__media mj-feed-post__media--video">
                                <video controls playsinline poster="<?php echo esc_url($video['poster']); ?>">
                                    <source src="<?php echo esc_url($video['url']); ?>" type="video/mp4">
                                </video>
                            </div>
                        <?php endif; ?>

                        <?php if ($link_preview && !empty($link_preview['url'])): ?>
                            <?php if (!empty($link_preview['is_youtube']) && !empty($link_preview['youtube_id'])): ?>
                                <!-- YouTube Embed -->
                                <div class="mj-feed-post__youtube-embed-container">
                                    <iframe 
                                        class="mj-feed-post__youtube-embed" 
                                        src="https://www.youtube.com/embed/<?php echo esc_attr($link_preview['youtube_id']); ?>?rel=0" 
                                        title="YouTube video" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen>
                                    </iframe>
                                </div>
                            <?php else: ?>
                                <!-- Link Preview -->
                                <a href="<?php echo esc_url($link_preview['url']); ?>" class="mj-feed-post__link-preview" target="_blank" rel="noopener noreferrer">
                                    <?php if (!empty($link_preview['image'])): ?>
                                        <img src="<?php echo esc_url($link_preview['image']); ?>" alt="" class="mj-feed-post__link-preview-image" loading="lazy">
                                    <?php endif; ?>
                                    <div class="mj-feed-post__link-preview-content">
                                        <?php if (!empty($link_preview['site_name'])): ?>
                                            <div class="mj-feed-post__link-preview-site"><?php echo esc_html($link_preview['site_name']); ?></div>
                                        <?php endif; ?>
                                        <div class="mj-feed-post__link-preview-title"><?php echo esc_html($link_preview['title'] ?: $link_preview['url']); ?></div>
                                        <?php if (!empty($link_preview['description'])): ?>
                                            <div class="mj-feed-post__link-preview-desc"><?php echo esc_html($link_preview['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="mj-feed-post__reactions-bar">
                            <div class="mj-feed-post__reactions-summary">
                                <?php if ($reactions_summary['total'] > 0): ?>
                                    <span class="mj-feed-post__reactions-emojis">
                                        <?php foreach ($reactions_summary['top_emojis'] as $emoji): ?>
                                            <span class="mj-feed-post__reaction-emoji"><?php echo esc_html($emoji); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                    <span class="mj-feed-post__reactions-count">
                                        <?php 
                                        if (!empty($reactions_summary['names'])) {
                                            $names = $reactions_summary['names'];
                                            $remaining = $reactions_summary['total'] - count($names);
                                            if ($remaining > 0) {
                                                echo esc_html(implode(', ', $names) . ' ' . sprintf(__('et %d autres', 'mj-member'), $remaining));
                                            } else {
                                                echo esc_html(implode(', ', $names));
                                            }
                                        } else {
                                            echo esc_html($reactions_summary['total']);
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($comment_count > 0): ?>
                                <button type="button" class="mj-feed-post__comments-count" data-action="toggle-comments">
                                    <?php printf(esc_html(_n('%d commentaire', '%d commentaires', $comment_count, 'mj-member')), $comment_count); ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="mj-feed-post__actions">
                            <div type="button" class="mj-feed-post__action mj-feed-post__action--like <?php echo $member_reaction ? 'is-active' : ''; ?>" data-action="react" data-current-reaction="<?php echo $member_reaction ? esc_attr($member_reaction->reaction_type) : ''; ?>">
                                <span class="mj-feed-post__action-icon">
                                    <?php if ($member_reaction && isset($reaction_types[$member_reaction->reaction_type])): ?>
                                        <span class="mj-feed-post__reaction-active"><?php echo esc_html($reaction_types[$member_reaction->reaction_type]['emoji']); ?></span>
                                    <?php else: ?>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="mj-feed-post__action-label">
                                    <?php 
                                    if ($member_reaction && isset($reaction_types[$member_reaction->reaction_type])) {
                                        echo esc_html($reaction_types[$member_reaction->reaction_type]['label']);
                                    } else {
                                        esc_html_e('J\'aime', 'mj-member');
                                    }
                                    ?>
                                </span>
                                <div class="mj-feed-post__reaction-picker">
                                    <?php foreach ($reaction_types as $type => $data): ?>
                                        <button type="button" 
                                        class="mj-feed-post__reaction-option" 
                                        data-reaction="<?php echo esc_attr($type); ?>" 
                                        title="<?php echo esc_attr($data['label']); ?>"
                                        >
                                            <span class="mj-feed-post__reaction-option-emoji">
                                                <?php echo esc_html($data['emoji']); ?>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="button" class="mj-feed-post__action mj-feed-post__action--comment" data-action="toggle-comments">
                                <span class="mj-feed-post__action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                </span>
                                <span class="mj-feed-post__action-label"><?php esc_html_e('Commenter', 'mj-member'); ?></span>
                            </button>
                        </div>

                        <div class="mj-feed-post__comments" style="display: none;">
                            <div class="mj-feed-post__comments-list">
                                <?php foreach ($comments as $comment): 
                                    $comment_author = isset($comment->first_name) ? $comment->first_name : '';
                                    if (isset($comment->last_name) && $comment->last_name) {
                                        $comment_author .= ' ' . mb_substr($comment->last_name, 0, 1) . '.';
                                    }
                                    $comment_initial = $comment_author ? mb_strtoupper(mb_substr($comment_author, 0, 1)) : '?';
                                    $comment_ago = isset($comment->created_at) ? human_time_diff(strtotime($comment->created_at), current_time('timestamp')) : '';
                                    $is_comment_owner = $is_logged_in && isset($comment->member_id) && (int)$comment->member_id === $member_id;
                                ?>
                                    <div class="mj-feed-comment" data-comment-id="<?php echo esc_attr((string)$comment->id); ?>">
                                        <div class="mj-feed-comment__avatar">
                                            <span class="mj-feed-comment__avatar-initial"><?php echo esc_html($comment_initial); ?></span>
                                        </div>
                                        <div class="mj-feed-comment__body">
                                            <div class="mj-feed-comment__bubble">
                                                <span class="mj-feed-comment__author"><?php echo esc_html($comment_author); ?></span>
                                                <span class="mj-feed-comment__text"><?php echo wp_kses_post($comment->content); ?></span>
                                            </div>
                                            <div class="mj-feed-comment__meta">
                                                <span class="mj-feed-comment__time"><?php echo esc_html($comment_ago); ?></span>
                                                <?php if ($is_comment_owner): ?>
                                                    <button type="button" class="mj-feed-comment__delete" data-action="delete-comment"><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($comment_count > 3): ?>
                                <button type="button" class="mj-feed-post__load-more-comments" data-action="load-more-comments" data-page="1">
                                    <?php esc_html_e('Voir plus de commentaires', 'mj-member'); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ($is_logged_in || $is_preview): ?>
                                <form class="mj-feed-post__comment-form">
                                    <div class="mj-feed-comment__avatar">
                                        <span class="mj-feed-comment__avatar-initial"><?php echo esc_html(mb_strtoupper(mb_substr($current_member->first_name ?? 'M', 0, 1))); ?></span>
                                    </div>
                                    <div class="mj-feed-post__comment-input-wrap">
                                        <input type="text" class="mj-feed-post__comment-input" placeholder="<?php esc_attr_e('Écrire un commentaire...', 'mj-member'); ?>">
                                        <button type="submit" class="mj-feed-post__comment-submit">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div> <!-- .mj-feed-post -->
                </article> <!-- .mj-feed-post-wrapper -->
                <?php endforeach; ?>
            <?php endif; /* end carousel-3 / feed branching */ ?>
        </div> <!-- .mj-testimonials__feed -->

        <?php
        // Load more only for feed template, not carousel
        if ($display_template === 'feed'):
            $count_args = array('status' => MjTestimonials::STATUS_APPROVED);
            if ($featured_only) {
                $count_args['featured'] = 1;
            }
            $total_approved = MjTestimonials::count($count_args);
            if ($total_approved > $per_page && !$is_single_mode):
        ?>
            <div class="mj-testimonials__load-more">
                <button type="button" class="mj-btn mj-btn--secondary mj-testimonials__load-more-btn" data-page="1" data-total-pages="<?php echo esc_attr((string)ceil($total_approved / $per_page)); ?>">
                    <?php esc_html_e('Voir plus de témoignages', 'mj-member'); ?>
                </button>
            </div>
        <?php endif; endif; ?>
    <?php endif; ?>
</div>
