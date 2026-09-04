<?php
/**
 * AJAX handlers for front-end testimonials.
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\Crud\MjTestimonials;
use Mj\Member\Classes\Crud\MjTestimonialReactions;
use Mj\Member\Classes\Crud\MjTestimonialComments;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\MjSocialMediaPublisher;

if (!defined('ABSPATH')) {
    exit;
}

final class TestimonialsController implements AjaxHandlerInterface {

    public function registerHooks(): void {
        add_action('wp_ajax_mj_front_testimonial_submit', [$this, 'submit']);
        add_action('wp_ajax_nopriv_mj_front_testimonial_submit', [$this, 'submit']);
        add_action('wp_ajax_mj_front_testimonial_list', [$this, 'list']);
        add_action('wp_ajax_nopriv_mj_front_testimonial_list', [$this, 'list']);
        add_action('wp_ajax_mj_front_testimonial_upload', [$this, 'upload']);
        add_action('wp_ajax_nopriv_mj_front_testimonial_upload', [$this, 'upload']);
        add_action('wp_ajax_mj_front_testimonial_my_list', [$this, 'myList']);
        add_action('wp_ajax_mj_front_testimonial_react', [$this, 'react']);
        add_action('wp_ajax_mj_front_testimonial_unreact', [$this, 'unreact']);
        add_action('wp_ajax_mj_front_testimonial_comment_add', [$this, 'commentAdd']);
        add_action('wp_ajax_mj_front_testimonial_comments_list', [$this, 'commentsList']);
        add_action('wp_ajax_nopriv_mj_front_testimonial_comments_list', [$this, 'commentsList']);
        add_action('wp_ajax_mj_front_testimonial_comment_delete', [$this, 'commentDelete']);
        add_action('wp_ajax_mj_front_testimonial_reactions_summary', [$this, 'reactionsSummary']);
        add_action('wp_ajax_nopriv_mj_front_testimonial_reactions_summary', [$this, 'reactionsSummary']);
        add_action('wp_ajax_mj_front_testimonial_link_preview', [$this, 'linkPreview']);
        add_action('wp_ajax_mj_front_testimonial_approve', [$this, 'approve']);
        add_action('wp_ajax_mj_front_testimonial_reject', [$this, 'reject']);
        add_action('wp_ajax_mj_front_testimonial_pending_list', [$this, 'pendingList']);
        add_action('wp_ajax_mj_front_testimonial_search_events', [$this, 'searchEvents']);
        add_action('wp_ajax_nopriv_mj_front_testimonial_search_events', [$this, 'searchEvents']);
        add_action('wp_ajax_mj_front_testimonial_search_members', [$this, 'searchMembers']);
        add_action('wp_ajax_nopriv_mj_front_testimonial_search_members', [$this, 'searchMembers']);
        add_action('wp_ajax_mj_front_testimonial_delete', [$this, 'delete']);
        add_action('wp_ajax_mj_front_testimonial_edit', [$this, 'edit']);
        add_action('wp_ajax_mj_front_testimonial_toggle_featured', [$this, 'toggleFeatured']);
        add_action('wp_ajax_mj_front_testimonial_publish_social', [$this, 'publishSocial']);
        add_action('template_redirect', [$this, 'renderShareBridgePage']);
        add_filter('query_vars',        [$this, 'addQueryVars']);
        add_action('init',              [$this, 'registerTestimonialRewrite'], 20);
    }

    public function addQueryVars(array $vars): array {
        $vars[] = 'mj_testimonial_id';
        return $vars;
    }

    public function registerTestimonialRewrite(): void {
        global $mj_testimonial_clean_urls_active;

        add_rewrite_tag('%mj_testimonial_id%', '([0-9]+)');

        $uri = (string) get_option('mj_member_testimonials_page_uri', '');
        if ($uri === '') {
            $pages = get_posts(array(
                'post_type'      => 'page',
                'name'           => 'temoignages',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ));
            if (!empty($pages)) {
                $uri = get_page_uri((int) $pages[0]);
                if ($uri !== '') {
                    update_option('mj_member_testimonials_page_uri', $uri, false);
                }
            }
        }

        if ($uri === '') {
            $mj_testimonial_clean_urls_active = false;
            return;
        }

        $pattern = '^' . preg_quote($uri, '/') . '/([0-9]+)(?:/[^/]+)?/?$';
        $target  = 'index.php?pagename=' . $uri . '&mj_testimonial_id=$matches[1]';

        // Check BEFORE flushing: is the rule already active in the stored rules?
        $stored = (array) get_option('rewrite_rules', array());
        $mj_testimonial_clean_urls_active = isset($stored[$pattern]);

        add_rewrite_rule($pattern, $target, 'top');

        // Flush once to make the rule effective for the next request
        if (!$mj_testimonial_clean_urls_active) {
            flush_rewrite_rules(false);
        }
    }

    /**
     * Render a lightweight public page used for social sharing previews.
     *
     * Facebook reads OG tags from this URL, which are generated from the
     * selected testimonial media instead of from the full listing page.
     */
    public function renderShareBridgePage(): void {
        $testimonial_id = isset($_GET['mj_testimonial_share']) ? (int) $_GET['mj_testimonial_share'] : 0;
        if ($testimonial_id <= 0 || is_admin()) {
            $this->renderCrawlerSingleTestimonialPage();
            return;
        }

        $testimonial = MjTestimonials::get_by_id($testimonial_id);
        if (!$testimonial || !isset($testimonial->status) || $testimonial->status !== MjTestimonials::STATUS_APPROVED) {
            status_header(404);
            nocache_headers();
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Not found</title></head><body></body></html>';
            exit;
        }

        $target_url = isset($_GET['target']) ? esc_url_raw(wp_unslash($_GET['target'])) : '';
        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $target_host = $target_url ? wp_parse_url($target_url, PHP_URL_HOST) : '';
        if (!$target_url || !wp_http_validate_url($target_url) || !$target_host || !is_string($home_host) || !is_string($target_host) || strtolower($target_host) !== strtolower($home_host)) {
            $target_url = home_url('/');
        }

        $og_data = $this->buildShareOgData($testimonial);

        $share_url = add_query_arg(
            array(
                'mj_testimonial_share' => $testimonial_id,
                'target' => $target_url,
            ),
            home_url('/')
        );

        $this->renderOgHtmlDocument(
            array(
                'title'        => $og_data['title'],
                'description'  => $og_data['description'],
                'image'        => $og_data['image'],
                'images'       => $og_data['images'] ?? array(),
                'videos'       => $og_data['videos'] ?? array(),
                'og_url'       => $share_url,
                'canonical_url' => $share_url,
                'redirect_url' => $this->isSocialCrawlerRequest() ? '' : $target_url,
                'fb_app_id'    => $this->getFacebookAppId(),
            )
        );
    }

    /**
     * For Facebook/Facebot requests on direct ?post=<id> testimonial URLs,
     * output dedicated OG tags so crawler previews use testimonial media.
     */
    private function renderCrawlerSingleTestimonialPage(): void {
        if (!$this->isSocialCrawlerRequest()) {
            return;
        }

        if (!$this->isLikelyTestimonialsRequest()) {
            return;
        }

        $testimonial_id = (int) get_query_var('mj_testimonial_id', 0);
        if ($testimonial_id <= 0) {
            $testimonial_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        }
        if ($testimonial_id <= 0) {
            return;
        }

        $testimonial = MjTestimonials::get_by_id($testimonial_id);
        if (!$testimonial || !isset($testimonial->status) || $testimonial->status !== MjTestimonials::STATUS_APPROVED) {
            return;
        }

        $current_url = $this->getCurrentRequestUrl();
        $og_data = $this->buildShareOgData($testimonial);

        $this->renderOgHtmlDocument(
            array(
                'title'        => $og_data['title'],
                'description'  => $og_data['description'],
                'image'        => $og_data['image'],
                'images'       => $og_data['images'] ?? array(),
                'videos'       => $og_data['videos'] ?? array(),
                'og_url'       => $current_url,
                'canonical_url' => $current_url,
                'fb_app_id'    => $this->getFacebookAppId(),
            )
        );
    }

    /**
     * Build OG title/description/image from a testimonial.
     *
     * @param object $testimonial
     * @return array<string,string>
     */
    private function buildShareOgData($testimonial): array {
        $author_name = '';
        if (isset($testimonial->first_name) && $testimonial->first_name) {
            $author_name = (string) $testimonial->first_name;
            if (isset($testimonial->last_name) && $testimonial->last_name) {
                $author_name .= ' ' . mb_substr((string) $testimonial->last_name, 0, 1) . '.';
            }
        }

        $raw_content = isset($testimonial->content) ? (string) $testimonial->content : '';
        // Strip @{id} member tokens and #event-slug mentions before exposing as OG description
        $content_text = preg_replace('/@\{\d+\}/', '', $raw_content);
        $content_text = preg_replace('/#([a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)\b/i', '', $content_text);
        $content_text = trim(wp_strip_all_tags($content_text));
        $content_text = preg_replace('/\s{2,}/', ' ', $content_text);

        $og_title = $author_name
            ? sprintf(__('Témoignage de %s', 'mj-member'), $author_name)
            : __('Témoignage', 'mj-member');
        $og_description = $content_text !== ''
            ? wp_html_excerpt($content_text, 220, '...')
            : __('Découvrez ce témoignage partagé sur MJ Pery.', 'mj-member');

        // Collect all photo URLs for og:image (multiple allowed by spec)
        $og_images = array();
        $photos = MjTestimonials::get_photo_urls($testimonial, 'large');
        foreach ($photos as $p) {
            if (!empty($p['full']) && is_string($p['full'])) {
                $og_images[] = $p['full'];
            }
        }

        // Collect video URLs and posters for og:video
        $og_videos = array();
        $videos = MjTestimonials::get_videos_data($testimonial);
        foreach ($videos as $v) {
            if (!empty($v['url']) && is_string($v['url'])) {
                $og_videos[] = array('url' => $v['url'], 'poster' => $v['poster'] ?? '');
            }
            // Use video poster as additional og:image fallback
            if (empty($og_images) && !empty($v['poster']) && is_string($v['poster'])) {
                $og_images[] = $v['poster'];
            }
        }

        if (empty($og_images) && isset($testimonial->member_photo_id) && (int) $testimonial->member_photo_id > 0) {
            $avatar_src = wp_get_attachment_image_src((int) $testimonial->member_photo_id, 'large');
            if ($avatar_src && isset($avatar_src[0])) {
                $og_images[] = (string) $avatar_src[0];
            }
        }

        if (empty($og_images)) {
            $site_icon = (string) wp_get_site_icon_url(512);
            if ($site_icon !== '') {
                $og_images[] = $site_icon;
            }
        }

        return array(
            'title'       => $og_title,
            'description' => $og_description,
            'image'       => $og_images[0] ?? '',
            'images'      => $og_images,
            'videos'      => $og_videos,
        );
    }

    /**
     * @return array<string,mixed> $data
     */
    private function renderOgHtmlDocument(array $data): void {
        $title        = isset($data['title']) ? (string) $data['title'] : __('Témoignage', 'mj-member');
        $description  = isset($data['description']) ? (string) $data['description'] : '';
        $og_url       = isset($data['og_url']) ? (string) $data['og_url'] : home_url('/');
        $canonical_url = isset($data['canonical_url']) ? (string) $data['canonical_url'] : $og_url;
        $redirect_url = isset($data['redirect_url']) ? (string) $data['redirect_url'] : '';
        $fb_app_id    = isset($data['fb_app_id']) ? preg_replace('/[^0-9]/', '', (string) $data['fb_app_id']) : '';
        $images       = isset($data['images']) && is_array($data['images']) ? $data['images'] : array();
        $videos       = isset($data['videos']) && is_array($data['videos']) ? $data['videos'] : array();
        // Backward compat: single 'image' key
        if (empty($images) && isset($data['image']) && (string) $data['image'] !== '') {
            $images = array((string) $data['image']);
        }
        $first_image = $images[0] ?? '';

        nocache_headers();
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title); ?></title>
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo esc_attr($title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url" content="<?php echo esc_url($og_url); ?>">
    <?php if ($fb_app_id !== '') : ?>
    <meta property="fb:app_id" content="<?php echo esc_attr($fb_app_id); ?>">
    <?php endif; ?>
    <?php foreach ($images as $img_url) : if (!is_string($img_url) || $img_url === '') continue; ?>
    <meta property="og:image" content="<?php echo esc_url($img_url); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endforeach; ?>
    <?php foreach ($videos as $vid) : if (!is_array($vid) || empty($vid['url'])) continue; ?>
    <meta property="og:video" content="<?php echo esc_url($vid['url']); ?>">
    <meta property="og:video:type" content="video/mp4">
    <?php if (!empty($vid['poster'])) : ?>
    <meta property="og:video:image" content="<?php echo esc_url($vid['poster']); ?>">
    <?php endif; ?>
    <?php endforeach; ?>
    <meta name="twitter:card" content="<?php echo !empty($videos) ? 'player' : 'summary_large_image'; ?>">
    <meta name="twitter:title" content="<?php echo esc_attr($title); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
    <?php if ($first_image !== '') : ?>
    <meta name="twitter:image" content="<?php echo esc_url($first_image); ?>">
    <?php endif; ?>
    <?php if ($redirect_url !== '') : ?>
    <meta http-equiv="refresh" content="0;url=<?php echo esc_url($redirect_url); ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo esc_url($canonical_url); ?>">
</head>
<body>
    <?php if ($redirect_url !== '') : ?>
    <p><a href="<?php echo esc_url($redirect_url); ?>"><?php echo esc_html__('Continuer vers le témoignage', 'mj-member'); ?></a></p>
    <?php endif; ?>
</body>
</html>
        <?php
        exit;
    }

    private function isSocialCrawlerRequest(): bool {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower((string) $_SERVER['HTTP_USER_AGENT']) : '';
        if ($ua === '') {
            return false;
        }

        $needles = array('facebookexternalhit', 'facebot', 'twitterbot', 'linkedinbot', 'slackbot');
        foreach ($needles as $needle) {
            if (strpos($ua, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isLikelyTestimonialsRequest(): bool {
        $section = isset($_GET['section']) ? sanitize_key((string) $_GET['section']) : '';
        if ($section === 'testimonials') {
            return true;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? strtolower((string) $_SERVER['REQUEST_URI']) : '';
        return strpos($uri, 'temoignage') !== false;
    }

    private function getCurrentRequestUrl(): string {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        if ($request_uri === '') {
            $request_uri = '/';
        }
        return home_url($request_uri);
    }

    private function getFacebookAppId(): string {
        $candidates = array(
            (string) get_option('mj_social_facebook_app_id', ''),
            (string) get_option('facebook_app_id', ''),
        );

        if (defined('MJ_FACEBOOK_APP_ID')) {
            $candidates[] = (string) MJ_FACEBOOK_APP_ID;
        }

        foreach ($candidates as $candidate) {
            $normalized = preg_replace('/[^0-9]/', '', $candidate);
            if (is_string($normalized) && $normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * AJAX: Submit a new testimonial (front-end).
     */
    public function submit() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $member_context = $this->resolveSubmissionMemberContext();
        if (!$member_context) {
            wp_send_json_error(__('Vous devez être connecté pour soumettre un témoignage.', 'mj-member'), 403);
        }

        $member_id = (int) $member_context['member_id'];
        $member_record = $member_context['member'];

        // Parse event slug (from EventPage submissions)
        $event_slug = isset($_POST['event_slug']) ? sanitize_title(wp_unslash($_POST['event_slug'])) : '';

        // Parse content
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

        // Prepend #slug to content when submitted from an event page
        if ($event_slug !== '' && $content !== '') {
            $mention = '#' . $event_slug;
            // Only prepend if not already present
            if (strpos($content, $mention) === false) {
                $content = $mention . ' ' . $content;
            }
        }

        // Parse photo IDs
        $photo_ids = array();
        if (isset($_POST['photo_ids'])) {
            $raw_photos = $_POST['photo_ids'];
            if (is_string($raw_photos)) {
                $decoded = json_decode(wp_unslash($raw_photos), true);
                if (is_array($decoded)) {
                    $photo_ids = array_map('intval', array_filter($decoded));
                }
            } elseif (is_array($raw_photos)) {
                $photo_ids = array_map('intval', array_filter($raw_photos));
            }
        }

        // Parse video IDs (multi-video: JSON array; fallback to legacy single video_id)
        $video_ids = array();
        if (isset($_POST['video_ids'])) {
            $raw_vids = wp_unslash($_POST['video_ids']);
            if (is_string($raw_vids)) {
                $decoded = json_decode($raw_vids, true);
                if (is_array($decoded)) $video_ids = array_values(array_filter(array_map('intval', $decoded)));
            } elseif (is_array($raw_vids)) {
                $video_ids = array_values(array_filter(array_map('intval', $raw_vids)));
            }
        }
        if (empty($video_ids) && isset($_POST['video_id']) && (int) $_POST['video_id'] > 0) {
            $video_ids = array((int) $_POST['video_id']);
        }

        // Parse link preview
        $link_preview = null;
        if (isset($_POST['link_preview']) && !empty($_POST['link_preview'])) {
            $link_preview = wp_unslash($_POST['link_preview']);
        }

        // Validate that at least some content exists
        if (empty($content) && empty($photo_ids) && empty($video_ids)) {
            wp_send_json_error(__('Veuillez ajouter du texte, des photos ou une vidéo.', 'mj-member'), 400);
        }

        // Check if member is trusted - auto-approve if true
        $is_trusted = isset($member_record->is_trusted_member) && (int) $member_record->is_trusted_member === 1;
        $initial_status = $is_trusted ? MjTestimonials::STATUS_APPROVED : MjTestimonials::STATUS_PENDING;

        // Create testimonial
        $create_data = array(
            'member_id' => $member_id,
            'content' => $content,
            'photo_ids' => $photo_ids,
            'video_ids' => $video_ids,
            'link_preview' => $link_preview,
            'status' => $initial_status,
        );

        // Attach event_slug when submitted from an event page
        if ($event_slug !== '') {
            $create_data['event_slug'] = $event_slug;
        }

        $result = MjTestimonials::create($create_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 500);
        }

        // Trigger notification to admins
        do_action('mj_member_testimonial_created', (int) $result, $member_id);

        $success_message = $is_trusted
            ? __('Merci pour votre témoignage ! Il est maintenant visible.', 'mj-member')
            : __('Merci pour votre témoignage ! Il sera visible après validation.', 'mj-member');

        wp_send_json_success(array(
            'message' => $success_message,
            'id' => $result,
        ));
    }

    /**
     * AJAX: Get approved testimonials for display.
     */
    public function list() {
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? min(50, max(1, (int) $_POST['per_page'])) : 10;
        $featured_only = isset($_POST['featured_only']) && $_POST['featured_only'] === '1';
        $base_url = isset($_POST['base_url']) ? esc_url_raw(wp_unslash($_POST['base_url'])) : '';
        if ($base_url === '' || !wp_http_validate_url($base_url)) {
            $base_url = home_url('/mon-compte/temoignages/');
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        $current_member_id = $current_member && isset($current_member->id) ? (int) $current_member->id : 0;
        $current_member_role = $current_member && isset($current_member->role) ? (string) $current_member->role : '';
        $is_animator = in_array($current_member_role, array('animateur', 'coordinateur'), true);

        $query_args = array(
            'page' => $page,
            'per_page' => $per_page,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        if ($featured_only) {
            $testimonials = MjTestimonials::get_featured($query_args);
            $total = MjTestimonials::count(array('status' => MjTestimonials::STATUS_APPROVED, 'featured' => 1));
        } else {
            $testimonials = MjTestimonials::get_approved($query_args);
            $total = MjTestimonials::count(array('status' => MjTestimonials::STATUS_APPROVED));
        }
        $items = array();

        foreach ($testimonials as $t) {
            $photos = MjTestimonials::get_photo_urls($t, 'large');
            $videos = MjTestimonials::get_videos_data($t);

            $member_name = '';
            $member_initial = '?';
            if (isset($t->first_name) && $t->first_name) {
                $member_name = $t->first_name;
                $member_initial = mb_strtoupper(mb_substr($t->first_name, 0, 1));
                if (isset($t->last_name) && $t->last_name) {
                    $member_name .= ' ' . mb_substr($t->last_name, 0, 1) . '.';
                }
            }

            $member_avatar_url = '';
            if (isset($t->member_photo_id) && $t->member_photo_id) {
                $avatar_src = wp_get_attachment_image_src((int) $t->member_photo_id, 'thumbnail');
                if ($avatar_src) {
                    $member_avatar_url = $avatar_src[0];
                }
            }

            $created_ago = '';
            if (isset($t->created_at) && $t->created_at) {
                $created_ago = human_time_diff(strtotime($t->created_at), current_time('timestamp'));
            }

            $link_preview = MjTestimonials::get_link_preview($t);
            $testimonial_id = (int) $t->id;
            $testimonial_member_id = isset($t->member_id) ? (int) $t->member_id : 0;
            $testimonial_status = isset($t->status) ? (string) $t->status : MjTestimonials::STATUS_APPROVED;
            $is_owner = $current_member_id > 0 && $testimonial_member_id === $current_member_id;
            $can_manage = $is_owner || $is_animator;
            $post_url = add_query_arg(array('post' => $testimonial_id), $base_url);
            $share_url = add_query_arg(array(
                'mj_testimonial_share' => $testimonial_id,
                'target' => $post_url,
            ), home_url('/'));

            $raw_content = isset($t->content) ? (string) $t->content : '';
            $content_html = wp_kses_post(wpautop(self::linkifyMemberMentions(self::linkifyEventMentions($raw_content))));

            $items[] = array(
                'id' => $testimonial_id,
                'content' => $content_html,
                'contentHtml' => $content_html,
                'rawContent' => $raw_content,
                'photos' => $photos,
                'videos' => $videos,
                'linkPreview' => $link_preview,
                'memberId' => $testimonial_member_id,
                'memberName' => $member_name,
                'memberInitial' => $member_initial,
                'memberAvatarUrl' => $member_avatar_url,
                'createdAgo' => $created_ago,
                'createdAt' => isset($t->created_at) ? $t->created_at : '',
                'status' => $testimonial_status,
                'featured' => !empty($t->featured),
                'canManage' => $can_manage,
                'canToggleFeatured' => $is_animator,
                'postUrl' => $post_url,
                'shareUrl' => $share_url,
                'mentionedMembers' => isset($t->content) ? self::extractMentionedMembers($t->content) : array(),
            );
        }

        wp_send_json_success(array(
            'testimonials' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $per_page,
            'totalPages' => ceil($total / $per_page),
        ));
    }

    /**
     * AJAX: Upload media for testimonial (photo or video).
     */
    public function upload() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        if (!$this->resolveSubmissionMemberContext()) {
            wp_send_json_error(__('Vous devez être connecté pour envoyer des fichiers.', 'mj-member'), 403);
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(__('Aucun fichier reçu.', 'mj-member'), 400);
        }

        $file = $_FILES['file'];

        // Detect PHP-level upload errors before any custom validation
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $php_upload_errors = array(
                UPLOAD_ERR_INI_SIZE   => sprintf(
                    __('La vidéo dépasse la limite autorisée par le serveur (%s). Raccourcissez l\'enregistrement.', 'mj-member'),
                    size_format(wp_max_upload_size())
                ),
                UPLOAD_ERR_FORM_SIZE  => __('La vidéo dépasse la taille maximale du formulaire.', 'mj-member'),
                UPLOAD_ERR_PARTIAL    => __('Le fichier n\'a été que partiellement téléchargé. Réessayez.', 'mj-member'),
                UPLOAD_ERR_NO_FILE    => __('Aucun fichier sélectionné.', 'mj-member'),
                UPLOAD_ERR_NO_TMP_DIR => __('Dossier temporaire manquant sur le serveur.', 'mj-member'),
                UPLOAD_ERR_CANT_WRITE => __('Impossible d\'écrire le fichier sur le serveur.', 'mj-member'),
                UPLOAD_ERR_EXTENSION  => __('Upload bloqué par une extension PHP.', 'mj-member'),
            );
            $err_code = (int) $file['error'];
            $err_msg  = isset($php_upload_errors[$err_code])
                ? $php_upload_errors[$err_code]
                : __('Erreur d\'upload inconnue.', 'mj-member');
            wp_send_json_error($err_msg, 400);
        }

        $media_type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'photo';

        // Validate file type
        $allowed_photo_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $allowed_video_types = array('video/mp4', 'video/webm', 'video/quicktime');

        $file_type = wp_check_filetype($file['name']);
        $mime_type = isset($file_type['type']) ? $file_type['type'] : '';

        if ($media_type === 'video') {
            if (!in_array($mime_type, $allowed_video_types, true)) {
                wp_send_json_error(__('Format vidéo non supporté. Utilisez MP4, WebM ou MOV.', 'mj-member'), 400);
            }
            // Limit video size to 100MB
            if ($file['size'] > 100 * 1024 * 1024) {
                wp_send_json_error(__('La vidéo est trop volumineuse (max 100 Mo).', 'mj-member'), 400);
            }
        } else {
            if (!in_array($mime_type, $allowed_photo_types, true)) {
                wp_send_json_error(__('Format image non supporté. Utilisez JPG, PNG, GIF ou WebP.', 'mj-member'), 400);
            }
            // Limit photo size to 10MB
            if ($file['size'] > 10 * 1024 * 1024) {
                wp_send_json_error(__('L\'image est trop volumineuse (max 10 Mo).', 'mj-member'), 400);
            }
        }

        // Include WordPress upload handling
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // Process upload
        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message(), 500);
        }

        // Get URL for response
        $url = '';
        $thumb = '';
        if ($media_type === 'video') {
            $url = wp_get_attachment_url($attachment_id);
        } else {
            $url = wp_get_attachment_image_url($attachment_id, 'medium');
            $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        }

        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => $url,
            'thumb' => $thumb ?: $url,
            'type' => $media_type,
        ));
    }

    /**
     * Resolve member context for standard or kiosk submissions.
     *
     * @return array<string,mixed>|null
     */
    private function resolveSubmissionMemberContext() {
        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if ($current_member && isset($current_member->id)) {
            return array(
                'member_id' => (int) $current_member->id,
                'member' => $current_member,
                'is_kiosk' => false,
            );
        }

        $kiosk_member_id = isset($_POST['kiosk_member_id']) ? (int) $_POST['kiosk_member_id'] : 0;
        $kiosk_signature = isset($_POST['kiosk_signature']) ? sanitize_text_field(wp_unslash($_POST['kiosk_signature'])) : '';

        if ($kiosk_member_id <= 0 || $kiosk_signature === '') {
            return null;
        }

        if (!wp_verify_nonce($kiosk_signature, 'mj-testimonial-kiosk-' . $kiosk_member_id)) {
            return null;
        }

        $kiosk_member = MjMembers::getById($kiosk_member_id);
        if (!$kiosk_member || !isset($kiosk_member->id)) {
            return null;
        }

        return array(
            'member_id' => (int) $kiosk_member->id,
            'member' => $kiosk_member,
            'is_kiosk' => true,
        );
    }

    /**
     * AJAX: Get member's own testimonials.
     */
    public function myList() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        $member_id = (int) $current_member->id;
        $testimonials = MjTestimonials::get_for_member($member_id, array(
            'per_page' => 20,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        $status_labels = MjTestimonials::get_status_labels();
        $items = array();

        foreach ($testimonials as $t) {
            $photos = MjTestimonials::get_photo_urls($t, 'medium');
            $video = MjTestimonials::get_video_data($t);
            $status_key = isset($t->status) ? $t->status : 'pending';

            $items[] = array(
                'id' => (int) $t->id,
                'content' => isset($t->content) ? self::linkifyMemberMentions(self::linkifyEventMentions($t->content)) : '',
                'photos' => $photos,
                'video' => $video,
                'status' => $status_key,
                'statusLabel' => isset($status_labels[$status_key]) ? $status_labels[$status_key] : $status_key,
                'createdAt' => isset($t->created_at) ? $t->created_at : '',
                'rejectionReason' => isset($t->rejection_reason) ? $t->rejection_reason : null,
            );
        }

        wp_send_json_success(array(
            'testimonials' => $items,
            'count' => count($items),
        ));
    }

    /**
     * AJAX: Add/toggle a reaction on a testimonial.
     */
    public function react() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté pour réagir.', 'mj-member'), 403);
        }

        $testimonial_id = isset($_POST['testimonial_id']) ? (int) $_POST['testimonial_id'] : 0;
        $reaction_type = isset($_POST['reaction_type']) ? sanitize_key($_POST['reaction_type']) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        if (!MjTestimonialReactions::is_valid_type($reaction_type)) {
            wp_send_json_error(__('Type de réaction invalide.', 'mj-member'), 400);
        }

        $member_id = (int) $current_member->id;

        // Check if this is a new reaction (not already reacted with same type)
        $existing_reaction = MjTestimonialReactions::get_member_reaction($testimonial_id, $member_id);
        $is_new_reaction = !$existing_reaction || $existing_reaction->reaction_type !== $reaction_type;

        $result = MjTestimonialReactions::react($testimonial_id, $member_id, $reaction_type);

        if ($result === false) {
            wp_send_json_error(__('Erreur lors de l\'ajout de la réaction.', 'mj-member'), 500);
        }

        // Trigger notification only for new reactions
        if ($is_new_reaction) {
            $testimonial = MjTestimonials::get_by_id($testimonial_id);
            if ($testimonial && isset($testimonial->member_id)) {
                $author_member_id = (int) $testimonial->member_id;
                do_action('mj_member_testimonial_reaction', $testimonial_id, $author_member_id, $member_id, $reaction_type);
            }
        }

        // Get updated summary
        $summary = MjTestimonialReactions::get_summary($testimonial_id);
        $member_reaction = MjTestimonialReactions::get_member_reaction($testimonial_id, $member_id);

        wp_send_json_success(array(
            'summary' => $summary,
            'memberReaction' => $member_reaction ? $member_reaction->reaction_type : null,
            'reactionTypes' => MjTestimonialReactions::get_reaction_types(),
        ));
    }

    /**
     * AJAX: Remove reaction from a testimonial.
     */
    public function unreact() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        $testimonial_id = isset($_POST['testimonial_id']) ? (int) $_POST['testimonial_id'] : 0;

        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        $member_id = (int) $current_member->id;
        MjTestimonialReactions::remove_reaction($testimonial_id, $member_id);

        // Get updated summary
        $summary = MjTestimonialReactions::get_summary($testimonial_id);

        wp_send_json_success(array(
            'summary' => $summary,
            'memberReaction' => null,
        ));
    }

    /**
     * AJAX: Add a comment to a testimonial.
     */
    public function commentAdd() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté pour commenter.', 'mj-member'), 403);
        }

        $testimonial_id = isset($_POST['testimonial_id']) ? (int) $_POST['testimonial_id'] : 0;
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        if (empty(trim($content))) {
            wp_send_json_error(__('Le commentaire ne peut pas être vide.', 'mj-member'), 400);
        }

        $member_id = (int) $current_member->id;
        $comment_id = MjTestimonialComments::add($testimonial_id, $member_id, $content);

        if (!$comment_id) {
            wp_send_json_error(__('Erreur lors de l\'ajout du commentaire.', 'mj-member'), 500);
        }

        // Trigger notification to testimonial author
        $testimonial = MjTestimonials::get_by_id($testimonial_id);
        if ($testimonial && isset($testimonial->member_id)) {
            $author_member_id = (int) $testimonial->member_id;
            do_action('mj_member_testimonial_comment', $testimonial_id, $author_member_id, $member_id, $comment_id);
        }

        $comment = MjTestimonialComments::get($comment_id);
        $comment_data = MjTestimonialComments::format_for_json($comment);
        $comment_data['isOwner'] = true;

        $comment_count = MjTestimonialComments::count_for_testimonial($testimonial_id);

        wp_send_json_success(array(
            'comment' => $comment_data,
            'commentCount' => $comment_count,
        ));
    }

    /**
     * AJAX: Get comments for a testimonial.
     */
    public function commentsList() {
        $testimonial_id = isset($_POST['testimonial_id']) ? (int) $_POST['testimonial_id'] : 0;
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? min(50, max(1, (int) $_POST['per_page'])) : 10;

        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        $current_member_id = ($current_member && isset($current_member->id)) ? (int) $current_member->id : 0;

        $comments = MjTestimonialComments::get_for_testimonial($testimonial_id, array(
            'page' => $page,
            'per_page' => $per_page,
        ));

        $items = array();
        foreach ($comments as $comment) {
            $data = MjTestimonialComments::format_for_json($comment);
            $data['isOwner'] = ($current_member_id > 0 && (int) $comment->member_id === $current_member_id);
            $items[] = $data;
        }

        $total = MjTestimonialComments::count_for_testimonial($testimonial_id);

        wp_send_json_success(array(
            'comments' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $per_page,
            'totalPages' => ceil($total / $per_page),
        ));
    }

    /**
     * AJAX: Delete a comment (owner only).
     */
    public function commentDelete() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        $comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

        if ($comment_id <= 0) {
            wp_send_json_error(__('Commentaire invalide.', 'mj-member'), 400);
        }

        $member_id = (int) $current_member->id;

        // Check ownership
        if (!MjTestimonialComments::is_owner($comment_id, $member_id)) {
            wp_send_json_error(__('Vous ne pouvez supprimer que vos propres commentaires.', 'mj-member'), 403);
        }

        $comment = MjTestimonialComments::get($comment_id);
        $testimonial_id = $comment ? (int) $comment->testimonial_id : 0;

        $result = MjTestimonialComments::delete($comment_id);

        if (!$result) {
            wp_send_json_error(__('Erreur lors de la suppression du commentaire.', 'mj-member'), 500);
        }

        $comment_count = $testimonial_id > 0 ? MjTestimonialComments::count_for_testimonial($testimonial_id) : 0;

        wp_send_json_success(array(
            'deleted' => true,
            'commentCount' => $comment_count,
        ));
    }

    /**
     * AJAX: Get reaction summary for a testimonial.
     */
    public function reactionsSummary() {
        $testimonial_id = isset($_POST['testimonial_id']) ? (int) $_POST['testimonial_id'] : 0;

        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        $member_id = ($current_member && isset($current_member->id)) ? (int) $current_member->id : 0;

        $summary = MjTestimonialReactions::get_summary($testimonial_id);
        $member_reaction = $member_id > 0 ? MjTestimonialReactions::get_member_reaction($testimonial_id, $member_id) : null;

        wp_send_json_success(array(
            'summary' => $summary,
            'memberReaction' => $member_reaction ? $member_reaction->reaction_type : null,
            'reactionTypes' => MjTestimonialReactions::get_reaction_types(),
        ));
    }

    /**
     * AJAX: Fetch link preview (Open Graph metadata) for a URL.
     */
    public function linkPreview() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        // Must be logged in
        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(__('URL invalide.', 'mj-member'), 400);
        }

        // Check if it's YouTube first
        $youtube_id = $this->extractYoutubeId($url);
        if ($youtube_id) {
            wp_send_json_success(array(
                'url' => $url,
                'title' => 'YouTube',
                'description' => 'Vidéo YouTube',
                'image' => "https://i.ytimg.com/vi/{$youtube_id}/hqdefault.jpg",
                'site_name' => 'YouTube',
                'is_youtube' => true,
                'youtube_id' => $youtube_id,));
        }

        // Fetch the URL content for non-YouTube links
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; MjMember/1.0; +https://www.mj-pery.be)',
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(__('Impossible de récupérer la page.', 'mj-member'), 500);
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            wp_send_json_error(__('Contenu vide.', 'mj-member'), 500);
        }

        // Parse Open Graph and meta tags
        $preview = $this->parseLinkPreview($body, $url);

        if (empty($preview['title']) && empty($preview['description']) && empty($preview['image'])) {
            wp_send_json_error(__('Aucun aperçu disponible pour ce lien.', 'mj-member'), 404);
        }

        wp_send_json_success($preview);
    }

    /**
     * AJAX: Approve a testimonial (animators only).
     */
    public function approve() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        // Get current member
        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        // Check if user is animator
        $member_role = isset($current_member->role) ? $current_member->role : null;
        if (!$member_role || !in_array($member_role, array('animateur', 'coordinateur'), true)) {
            wp_send_json_error(__('Seuls les animateurs peuvent approuver les témoignages.', 'mj-member'), 403);
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(__('Identifiant invalide.', 'mj-member'), 400);
        }

        // Get testimonial
        $testimonial = MjTestimonials::get_by_id($id);
        if (!$testimonial) {
            wp_send_json_error(__('Témoignage introuvable.', 'mj-member'), 404);
        }

        // Only allow approving pending testimonials
        if (isset($testimonial->status) && $testimonial->status !== MjTestimonials::STATUS_PENDING) {
            wp_send_json_error(__('Seuls les témoignages en attente peuvent être approuvés.', 'mj-member'), 400);
        }

        $result = MjTestimonials::approve($id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 500);
        }

        // Trigger notification
        $member_id = isset($testimonial->member_id) ? (int) $testimonial->member_id : 0;
        if ($member_id > 0) {
            do_action('mj_member_testimonial_approved', $id, $member_id);
        }

        wp_send_json_success(array(
            'message' => __('Témoignage approuvé.', 'mj-member'),
            'id' => $id,
        ));
    }

    /**
     * AJAX: Reject a testimonial (animators only).
     */
    public function reject() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        // Get current member
        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        // Check if user is animator
        $member_role = isset($current_member->role) ? $current_member->role : null;
        if (!$member_role || !in_array($member_role, array('animateur', 'coordinateur'), true)) {
            wp_send_json_error(__('Seuls les animateurs peuvent refuser les témoignages.', 'mj-member'), 403);
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(__('Identifiant invalide.', 'mj-member'), 400);
        }

        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

        // Get testimonial
        $testimonial = MjTestimonials::get_by_id($id);
        if (!$testimonial) {
            wp_send_json_error(__('Témoignage introuvable.', 'mj-member'), 404);
        }

        // Only allow rejecting pending testimonials
        if (isset($testimonial->status) && $testimonial->status !== MjTestimonials::STATUS_PENDING) {
            wp_send_json_error(__('Seuls les témoignages en attente peuvent être refusés.', 'mj-member'), 400);
        }

        $result = MjTestimonials::reject($id, $reason);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 500);
        }

        // Trigger notification
        $member_id = isset($testimonial->member_id) ? (int) $testimonial->member_id : 0;
        if ($member_id > 0) {
            do_action('mj_member_testimonial_rejected', $id, $member_id, $reason);
        }

        wp_send_json_success(array(
            'message' => __('Témoignage refusé.', 'mj-member'),
            'id' => $id,
        ));
    }

    /**
     * AJAX: Get pending testimonials for animators.
     */
    public function pendingList() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        // Get current member
        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        // Check if user is animator
        $member_role = isset($current_member->role) ? $current_member->role : null;
        if (!$member_role || !in_array($member_role, array('animateur', 'coordinateur'), true)) {
            wp_send_json_error(__('Accès non autorisé.', 'mj-member'), 403);
        }

        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? min(50, max(1, (int) $_POST['per_page'])) : 10;

        $testimonials = MjTestimonials::query(array(
            'status' => MjTestimonials::STATUS_PENDING,
            'page' => $page,
            'per_page' => $per_page,
            'orderby' => 'created_at',
            'order' => 'ASC',
        ));

        $total = MjTestimonials::count(array('status' => MjTestimonials::STATUS_PENDING));
        $items = array();

        foreach ($testimonials as $t) {
            $photos = MjTestimonials::get_photo_urls($t, 'medium');
            $video = MjTestimonials::get_video_data($t);

            $member_name = '';
            if (isset($t->first_name)) {
                $member_name = $t->first_name;
                if (isset($t->last_name) && $t->last_name) {
                    $member_name .= ' ' . mb_substr($t->last_name, 0, 1) . '.';
                }
            }

            $link_preview = MjTestimonials::get_link_preview($t);

            $items[] = array(
                'id' => (int) $t->id,
                'content' => isset($t->content) ? self::linkifyMemberMentions(self::linkifyEventMentions($t->content)) : '',
                'photos' => $photos,
                'video' => $video,
                'linkPreview' => $link_preview,
                'memberName' => $member_name,
                'memberId' => isset($t->member_id) ? (int) $t->member_id : 0,
                'createdAt' => isset($t->created_at) ? $t->created_at : '',
                'status' => MjTestimonials::STATUS_PENDING,
            );
        }

        wp_send_json_success(array(
            'testimonials' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $per_page,
            'totalPages' => ceil($total / $per_page),
        ));
    }

    /**
     * AJAX: Search members for @mention autocomplete in testimonials.
     */
    public function searchMembers() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        $member_ids = array();
        if (isset($_POST['ids'])) {
            $raw_ids = wp_unslash($_POST['ids']);
            if (is_string($raw_ids)) {
                $raw_ids = json_decode($raw_ids, true);
            }
            if (is_array($raw_ids)) {
                $member_ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $raw_ids)))), 0, 20);
            }
        }

        $member_slugs = array();
        if (isset($_POST['slugs'])) {
            $raw_slugs = wp_unslash($_POST['slugs']);
            if (is_string($raw_slugs)) {
                $raw_slugs = json_decode($raw_slugs, true);
            }
            if (is_array($raw_slugs)) {
                $member_slugs = array_slice(array_values(array_unique(array_filter(array_map('sanitize_title', $raw_slugs)))), 0, 20);
            }
        }

        $members = array();
        if (!empty($member_ids)) {
            foreach ($member_ids as $member_id) {
                $member = MjMembers::getById($member_id);
                if ($member) {
                    $members[] = $member;
                }
            }
        }
        if (!empty($member_slugs)) {
            foreach ($member_slugs as $member_slug) {
                $member = MjMembers::getBySlug($member_slug);
                if ($member) {
                    $members[] = $member;
                }
            }
        }
        if (empty($member_ids) && empty($member_slugs)) {
            $members = MjMembers::get_all(array(
                'search' => $search,
                'orderby' => 'last_name',
                'order' => 'ASC',
                'limit' => 10,
            ));
        }

        $items = array();
        foreach ($members as $m) {
            if (!isset($m->id) || !isset($m->first_name)) {
                continue;
            }
            $name = $m->first_name;
            if (isset($m->last_name) && $m->last_name !== '') {
                $name .= ' ' . mb_strtoupper(mb_substr($m->last_name, 0, 1)) . '.';
            }
            $avatar_url = '';
            if (isset($m->photo_id) && $m->photo_id) {
                $src = wp_get_attachment_image_src((int) $m->photo_id, 'thumbnail');
                if ($src) {
                    $avatar_url = $src[0];
                }
            }
            $items[] = array(
                'id'        => (int) $m->id,
                'slug'      => isset($m->slug) ? (string) $m->slug : '',
                'name'      => $name,
                'initial'   => mb_strtoupper(mb_substr($m->first_name, 0, 1)),
                'avatarUrl' => $avatar_url,
            );
        }

        wp_send_json_success(array('members' => $items));
    }

    /**
     * AJAX: Search events for #mention autocomplete in testimonials.
     *
     * Returns a list of events matching the search query (title or slug).
     * Used by the front-end autocomplete when users type #.
     */
    public function searchEvents() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        // Search events by title/slug, only active or past events
        $events = MjEvents::get_all(array(
            'search' => $search,
            'statuses' => array(MjEvents::STATUS_ACTIVE, MjEvents::STATUS_PAST),
            'orderby' => 'date_debut',
            'order' => 'DESC',
            'limit' => 10,
        ));

        $items = array();
        foreach ($events as $event) {
            $slug = '';
            if (isset($event->slug) && $event->slug !== '') {
                $slug = $event->slug;
            } else {
                $slug = MjEvents::get_or_create_slug((int) $event->id);
            }

            $items[] = array(
                'id' => (int) $event->id,
                'title' => isset($event->title) ? $event->title : '',
                'slug' => $slug,
                'emoji' => isset($event->emoji) ? $event->emoji : '',
                'type' => isset($event->type) ? $event->type : '',
                'date_debut' => isset($event->date_debut) ? $event->date_debut : '',
                'permalink' => function_exists('mj_member_build_event_permalink') ? mj_member_build_event_permalink($slug) : '',
            );
        }

        wp_send_json_success(array('events' => $items));
    }

    /**
     * AJAX: Delete own testimonial (front-end).
     *
     * Verifies ownership by matching current member ID to testimonial member_id.
     */
    public function delete() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        $testimonial_id = isset($_POST['testimonial_id']) ? intval($_POST['testimonial_id']) : 0;
        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        // Verify ownership
        $testimonial = MjTestimonials::get_by_id($testimonial_id);
        if (!$testimonial) {
            wp_send_json_error(__('Témoignage introuvable.', 'mj-member'), 404);
        }

        $is_animator = isset($current_member->role) && in_array($current_member->role, array('animateur', 'coordinateur'), true);
        if ((int) $testimonial->member_id !== (int) $current_member->id && !$is_animator) {
            wp_send_json_error(__('Vous ne pouvez supprimer que vos propres témoignages.', 'mj-member'), 403);
        }

        $deleted = MjTestimonials::delete($testimonial_id);
        if (!$deleted) {
            wp_send_json_error(__('Erreur lors de la suppression.', 'mj-member'), 500);
        }

        wp_send_json_success(array(
            'message' => __('Témoignage supprimé.', 'mj-member'),
        ));
    }

    /**
     * AJAX: Edit own testimonial content (front-end).
     *
     * Verifies ownership by matching current member ID to testimonial member_id.
     * Only the text content can be changed; photos/video stay unchanged.
     */
    public function edit() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        $testimonial_id = isset($_POST['testimonial_id']) ? intval($_POST['testimonial_id']) : 0;
        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        // Verify ownership or animator role
        $testimonial = MjTestimonials::get_by_id($testimonial_id);
        if (!$testimonial) {
            wp_send_json_error(__('Témoignage introuvable.', 'mj-member'), 404);
        }

        $is_animator = isset($current_member->role) && in_array($current_member->role, array('animateur', 'coordinateur'), true);
        if ((int) $testimonial->member_id !== (int) $current_member->id && !$is_animator) {
            wp_send_json_error(__('Vous ne pouvez modifier que vos propres témoignages.', 'mj-member'), 403);
        }

        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

        // Build update data
        $update_data = array();

        if (!empty(trim($content))) {
            $update_data['content'] = $content;
        }

        // Parse photo_ids if provided
        if (isset($_POST['photo_ids'])) {
            $raw_photos = wp_unslash($_POST['photo_ids']);
            if (is_string($raw_photos)) {
                $decoded = json_decode($raw_photos, true);
                if (is_array($decoded)) {
                    $update_data['photo_ids'] = array_map('intval', array_filter($decoded));
                } else {
                    $update_data['photo_ids'] = array();
                }
            } elseif (is_array($raw_photos)) {
                $update_data['photo_ids'] = array_map('intval', array_filter($raw_photos));
            }
        }

        // Parse video_ids (multi-video); fallback to legacy video_id
        if (isset($_POST['video_ids'])) {
            $raw_vids = wp_unslash($_POST['video_ids']);
            $decoded_vids = is_string($raw_vids) ? json_decode($raw_vids, true) : (is_array($raw_vids) ? $raw_vids : array());
            $update_data['video_ids'] = is_array($decoded_vids)
                ? array_values(array_filter(array_map('intval', $decoded_vids)))
                : array();
        } elseif (isset($_POST['video_id'])) {
            $vid = (int) $_POST['video_id'];
            $update_data['video_ids'] = $vid > 0 ? array($vid) : array();
        }

        // Animator-only fields
        if ($is_animator) {
            if (!empty($_POST['created_at'])) {
                $raw_date = sanitize_text_field(wp_unslash($_POST['created_at']));
                $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $raw_date);
                if ($dt) {
                    $update_data['created_at'] = $dt->format('Y-m-d H:i:s');
                }
            }
            if (!empty($_POST['new_member_id'])) {
                $new_member_id = (int) $_POST['new_member_id'];
                if ($new_member_id > 0) {
                    $new_member = MjMembers::getById($new_member_id);
                    if ($new_member) {
                        $update_data['member_id'] = $new_member_id;
                    }
                }
            }
        }

        // Must have at least content or media
        $has_content   = !empty(trim($content));
        $has_photos    = isset($update_data['photo_ids']) ? !empty($update_data['photo_ids']) : !empty(MjTestimonials::parse_photo_ids($testimonial));
        $has_video     = isset($update_data['video_ids']) ? !empty($update_data['video_ids']) : !empty(MjTestimonials::parse_video_ids($testimonial));

        if (!$has_content && !$has_photos && !$has_video) {
            wp_send_json_error(__('Le témoignage doit contenir au moins du texte, une photo ou une vidéo.', 'mj-member'), 400);
        }

        if (empty($update_data)) {
            wp_send_json_error(__('Aucune modification détectée.', 'mj-member'), 400);
        }

        $updated = MjTestimonials::update($testimonial_id, $update_data);

        if (!$updated || is_wp_error($updated)) {
            wp_send_json_error(__('Erreur lors de la mise à jour.', 'mj-member'), 500);
        }

        // Re-fetch testimonial for response
        $fresh = MjTestimonials::get_by_id($testimonial_id);
        $final_content = $fresh->content ?? $content;

        // Return the linkified HTML so JS can update the DOM
        $html_content = wp_kses_post(wpautop(self::linkifyMemberMentions(self::linkifyEventMentions($final_content))));

        // Build photos array for JS
        $photos = MjTestimonials::get_photo_urls($fresh, 'large');
        $photos_for_js = array_map(function($p) {
            return array('id' => $p['id'], 'url' => $p['url'], 'full' => $p['full']);
        }, $photos);

        // Build videos array for JS
        $videos = MjTestimonials::get_videos_data($fresh);
        $videos_for_js = array_map(function($v) { return array('id' => $v['id'], 'url' => $v['url']); }, $videos);

        $slider_html = self::buildMediaSliderHtml($photos, $videos, $testimonial_id);

        $mentioned_members = self::extractMentionedMembers($final_content);
        $mentioned_members_html = self::buildMentionedMembersHtml($mentioned_members);

        // Build updated member info for response
        $fresh_member = MjMembers::getById((int) $fresh->member_id);
        $new_member_name = '';
        $new_member_initial = '';
        $new_member_avatar_url = '';
        if ($fresh_member) {
            $new_member_name = trim(($fresh_member->first_name ?? '') . ' ' . ($fresh_member->last_name ?? ''));
            $new_member_initial = $new_member_name ? mb_strtoupper(mb_substr($new_member_name, 0, 1)) : '?';
            $photo_id = $fresh_member->photo_id ?? 0;
            if ($photo_id) {
                $src = wp_get_attachment_image_src((int) $photo_id, 'thumbnail');
                if ($src) $new_member_avatar_url = $src[0];
            }
        }

        $new_created_at = $fresh->created_at ?? '';
        $new_created_ago = $new_created_at ? human_time_diff(strtotime($new_created_at), current_time('timestamp')) : '';

        wp_send_json_success(array(
            'message'              => __('Témoignage modifié.', 'mj-member'),
            'content'              => $final_content,
            'contentHtml'         => $html_content,
            'photos'              => $photos_for_js,
            'videos'              => $videos_for_js,
            'sliderHtml'          => $slider_html,
            'mentionedMembers'    => $mentioned_members,
            'mentionedMembersHtml' => $mentioned_members_html,
            'newMemberId'         => (int) $fresh->member_id,
            'newMemberName'       => $new_member_name,
            'newMemberInitial'    => $new_member_initial,
            'newMemberAvatarUrl'  => $new_member_avatar_url,
            'newCreatedAt'        => $new_created_at,
            'newCreatedAgo'       => $new_created_ago,
        ));
    }

    /**
     * AJAX: Toggle featured status for a testimonial (animator/coordinator only).
     */
    public function toggleFeatured() {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'), 403);
        }

        // Only animateurs/coordinateurs can toggle featured
        $is_animator = isset($current_member->role) && in_array($current_member->role, array('animateur', 'coordinateur'), true);
        if (!$is_animator) {
            wp_send_json_error(__('Vous n\'avez pas les droits pour cette action.', 'mj-member'), 403);
        }

        $testimonial_id = isset($_POST['testimonial_id']) ? intval($_POST['testimonial_id']) : 0;
        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'), 400);
        }

        $result = MjTestimonials::toggle_featured($testimonial_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 500);
        }

        $label = $result['featured']
            ? __('Retirer la mise en avant', 'mj-member')
            : __('Mettre en avant', 'mj-member');

        wp_send_json_success(array(
            'featured' => $result['featured'],
            'label'    => $label,
            'message'  => $result['featured']
                ? __('Témoignage mis en avant.', 'mj-member')
                : __('Témoignage retiré de la mise en avant.', 'mj-member'),
        ));
    }

    /**
     * AJAX: Publish a testimonial to one or more social platforms (animators/coordinators only).
     */
    public function publishSocial(): void {
        check_ajax_referer('mj-testimonial-submit', '_wpnonce');

        $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
        if (!$current_member || !isset($current_member->id)) {
            wp_send_json_error(__('Vous devez être connecté.', 'mj-member'));
        }

        $member_role = isset($current_member->role) ? $current_member->role : null;
        if (!$member_role || !in_array($member_role, array('animateur', 'coordinateur'), true)) {
            wp_send_json_error(__('Accès refusé.', 'mj-member'));
        }

        $testimonial_id = isset($_POST['testimonial_id']) ? (int) $_POST['testimonial_id'] : 0;
        if ($testimonial_id <= 0) {
            wp_send_json_error(__('Témoignage invalide.', 'mj-member'));
        }

        $testimonial = MjTestimonials::get_by_id($testimonial_id);
        if (!$testimonial) {
            wp_send_json_error(__('Témoignage introuvable.', 'mj-member'));
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        if (empty(trim($message))) {
            wp_send_json_error(__('Le message ne peut pas être vide.', 'mj-member'));
        }

        // Parse selected platforms (whitelist)
        $platforms = array();
        if (isset($_POST['platforms']) && is_array($_POST['platforms'])) {
            foreach ($_POST['platforms'] as $p) {
                $p = sanitize_key($p);
                if (in_array($p, array('facebook', 'instagram'), true)) {
                    $platforms[] = $p;
                }
            }
        }
        if (empty($platforms)) {
            wp_send_json_error(__('Sélectionnez au moins une plateforme.', 'mj-member'));
        }

        // Parse selected photo IDs → URLs
        $image_urls = array();
        $selected_photo_ids = array();
        if (isset($_POST['photo_ids'])) {
            $raw = wp_unslash($_POST['photo_ids']);
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : array());
            if (is_array($decoded)) {
                $selected_photo_ids = array_values(array_filter(array_map('intval', $decoded)));
            }
        }
        foreach ($selected_photo_ids as $photo_id) {
            $src = wp_get_attachment_image_src($photo_id, 'large');
            if ($src && !empty($src[0])) {
                $image_urls[] = (string) $src[0];
            }
        }

        // Build link block
        $include_post_url    = !empty($_POST['include_post_url']);
        $include_event_urls  = !empty($_POST['include_event_urls']);

        $post_url = '';
        if ($include_post_url) {
            $post_url = isset($_POST['post_url']) ? esc_url_raw(wp_unslash($_POST['post_url'])) : '';
            if ($post_url === '' || !wp_http_validate_url($post_url)) {
                $post_url = home_url('/mon-compte/temoignages/?section=testimonials&post=' . $testimonial_id);
            }
        }

        $extra_urls = array();
        if ($include_event_urls && isset($testimonial->content)) {
            $extra_urls = $this->extractMentionedEventUrls((string) $testimonial->content);
        }

        $link_parts = array_filter(array_merge(
            $post_url !== '' ? array($post_url) : array(),
            $extra_urls
        ));
        $link = implode("\n", $link_parts);

        // Publish to each platform
        $publisher    = new MjSocialMediaPublisher();
        $results      = array();
        $has_error    = false;
        $settings_url = admin_url('admin.php?page=mj_settings');

        if (in_array('facebook', $platforms, true)) {
            $fb_result = $publisher->publishToFacebook($message, $link, $image_urls);
            if (is_wp_error($fb_result)) {
                $err_data     = $fb_result->get_error_data() ?: array();
                $token_expired = !empty($err_data['tokenExpired']);
                $results['facebook'] = array(
                    'success'      => false,
                    'message'      => $fb_result->get_error_message(),
                    'tokenExpired' => $token_expired,
                    'settingsUrl'  => $token_expired ? $settings_url : '',
                );
                $has_error = true;
            } else {
                $results['facebook'] = array('success' => true, 'message' => $fb_result['message'] ?? __('Publié !', 'mj-member'));
            }
        }

        if (in_array('instagram', $platforms, true)) {
            $ig_image  = $image_urls[0] ?? '';
            $ig_result = $publisher->publishToInstagram($message, $link, $ig_image);
            if (is_wp_error($ig_result)) {
                $err_data     = $ig_result->get_error_data() ?: array();
                $token_expired = !empty($err_data['tokenExpired']);
                $results['instagram'] = array(
                    'success'      => false,
                    'message'      => $ig_result->get_error_message(),
                    'tokenExpired' => $token_expired,
                    'settingsUrl'  => $token_expired ? $settings_url : '',
                );
                $has_error = true;
            } else {
                $results['instagram'] = array('success' => true, 'message' => $ig_result['message'] ?? __('Publié !', 'mj-member'));
            }
        }

        wp_send_json_success(array(
            'results'  => $results,
            'hasError' => $has_error,
            'message'  => $has_error
                ? __('Publication partielle — vérifiez les résultats ci-dessous.', 'mj-member')
                : __('Publié avec succès sur toutes les plateformes !', 'mj-member'),
        ));
    }

    /**
     * Extract permalinks for all #event-slug mentions in content.
     *
     * @return string[]
     */
    private function extractMentionedEventUrls(string $content): array {
        if (!preg_match_all('/#([a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)\b/i', $content, $matches)) {
            return array();
        }
        $urls = array();
        foreach (array_unique($matches[1]) as $slug) {
            $slug  = sanitize_title($slug);
            $event = $slug !== '' ? MjEvents::find_by_slug($slug) : null;
            if (!$event) {
                continue;
            }
            $permalink = function_exists('mj_member_build_event_permalink')
                ? mj_member_build_event_permalink($slug)
                : '';
            if ($permalink !== '') {
                $urls[] = $permalink;
            }
        }
        return $urls;
    }

    /**
     * Return array of configured social platforms keys ('facebook', 'instagram').
     *
     * @return string[]
     */
    public static function getConfiguredPlatforms(): array {
        $platforms = array();
        if (get_option('mj_social_facebook_page_token', '') !== '' && get_option('mj_social_facebook_page_id', '') !== '') {
            $platforms[] = 'facebook';
        }
        if (get_option('mj_social_instagram_access_token', '') !== '' && get_option('mj_social_instagram_business_id', '') !== '') {
            $platforms[] = 'instagram';
        }
        return $platforms;
    }

    /**
    * Convert @event-slug and #event-slug mentions in testimonial content to clickable links.
     *
     * @param string $content The raw testimonial content.
     * @return string Content with @mentions converted to links.
     */
    public static function linkifyEventMentions(string $content): string {
        // Support the historic @slug syntax as well as the current #slug syntax.
        // @{member_id} is deliberately excluded because it starts with a brace.
        return preg_replace_callback(
            '/(?:@|#)([a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)\b/i',
            function ($matches) {
                $slug = sanitize_title($matches[1]);
                if ($slug === '') {
                    return $matches[0];
                }

                $event = MjEvents::find_by_slug($slug);
                if (!$event) {
                    return $matches[0]; // Not a valid event slug, leave as-is
                }

                $permalink = function_exists('mj_member_build_event_permalink')
                    ? mj_member_build_event_permalink($slug)
                    : '';
                if ($permalink === '') {
                    return $matches[0];
                }

                $title = isset($event->title) ? esc_attr($event->title) : $slug;
                $emoji = (isset($event->emoji) && $event->emoji !== '') ? $event->emoji . ' ' : '';
                $label = $emoji . esc_html($event->title ?? $slug);

                return '<a href="' . esc_url($permalink) . '" class="mj-testimonial-event-link" title="' . $title . '">' . $label . '</a>';
            },
            $content
        );
    }

    /**
    * Convert @{member-slug} tokens to inline member mention spans.
     *
     * @param string $content Raw testimonial content.
     * @return string Content with @{id} tokens replaced by HTML spans.
     */
    public static function linkifyMemberMentions(string $content): string {
        // Strip @{id} tokens from displayed text — members appear only in the chips below.
        return preg_replace('/@\{([a-z0-9][a-z0-9\-]*)\}/i', '', $content);
    }

    /**
    * Extract data for all members mentioned via @{slug} in the content.
     *
     * @param string $content Raw testimonial content.
     * @return array Array of ['id', 'name', 'initial', 'avatarUrl'].
     */
    public static function extractMentionedMembers(string $content): array {
        if (!preg_match_all('/@\{([a-z0-9][a-z0-9\-]*)\}/i', $content, $matches)) {
            return array();
        }
        $tokens = array_unique($matches[1]);
        $result = array();
        foreach ($tokens as $token) {
            $member = ctype_digit($token)
                ? MjMembers::getById((int) $token)
                : MjMembers::getBySlug($token);
            if (!$member || !isset($member->first_name)) {
                continue;
            }
            $name = $member->first_name;
            if (isset($member->last_name) && $member->last_name !== '') {
                $name .= ' ' . mb_strtoupper(mb_substr($member->last_name, 0, 1)) . '.';
            }
            $avatar_url = '';
            if (isset($member->photo_id) && $member->photo_id) {
                $src = wp_get_attachment_image_src((int) $member->photo_id, 'thumbnail');
                if ($src) {
                    $avatar_url = $src[0];
                }
            }
            $result[] = array(
                'id'        => $id,
                'name'      => $name,
                'initial'   => mb_strtoupper(mb_substr($member->first_name, 0, 1)),
                'avatarUrl' => $avatar_url,
            );
        }
        return $result;
    }

    /**
     * Build the unified media slider HTML (photos + video as slides).
     *
     * All values are escaped via esc_url/esc_attr — output can be echoed directly.
     *
     * @param array      $photos  From MjTestimonials::get_photo_urls() — each has 'url', 'full'.
     * @param array|null $video   From MjTestimonials::get_video_data() — has 'url', 'poster'.
     * @param int|string $post_id Used for the lightbox group attribute.
     * @return string
     */
    public static function buildMediaSliderHtml(array $photos, $videos_or_video, $post_id): string {
        // Accept either an array of video objects (multi) or a single video object (legacy)
        $videos = array();
        if (is_array($videos_or_video)) {
            if (!empty($videos_or_video) && isset($videos_or_video[0]) && is_array($videos_or_video[0])) {
                $videos = $videos_or_video; // already an array of video objects
            } elseif (!empty($videos_or_video) && isset($videos_or_video['url'])) {
                $videos = array($videos_or_video); // single video object wrapped
            }
        }

        $slides = array();
        foreach ($photos as $photo) {
            if (!empty($photo['url'])) {
                $slides[] = array(
                    'type' => 'photo',
                    'url'  => $photo['url'],
                    'full' => $photo['full'] ?? $photo['url'],
                );
            }
        }
        foreach ($videos as $video) {
            if (!empty($video['url'])) {
                $slides[] = array(
                    'type'   => 'video',
                    'url'    => $video['url'],
                    'poster' => $video['poster'] ?? '',
                );
            }
        }
        if (empty($slides)) {
            return '';
        }

        $total = count($slides);
        $html  = '<div class="mj-feed-post__slider" data-index="0" data-total="' . esc_attr($total) . '">';
        $html .= '<div class="mj-feed-post__slider-track">';

        foreach ($slides as $slide) {
            $html .= '<div class="mj-feed-post__slide">';
            if ($slide['type'] === 'photo') {
                $html .= '<a href="' . esc_url($slide['full']) . '" class="mj-feed-post__slide-link" data-lightbox="post-' . esc_attr($post_id) . '">';
                $html .= '<img src="' . esc_url($slide['url']) . '" alt="" loading="lazy">';
                $html .= '</a>';
            } else {
                $poster = !empty($slide['poster']) ? ' poster="' . esc_url($slide['poster']) . '"' : '';
                $html .= '<video controls playsinline' . $poster . '>';
                $html .= '<source src="' . esc_url($slide['url']) . '" type="video/mp4">';
                $html .= '</video>';
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .mj-feed-post__slider-track

        if ($total > 1) {
            $prev_svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
            $next_svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
            $html .= '<button type="button" class="mj-feed-post__slider-btn mj-feed-post__slider-btn--prev" aria-label="Précédent" style="display:none;">' . $prev_svg . '</button>';
            $html .= '<button type="button" class="mj-feed-post__slider-btn mj-feed-post__slider-btn--next" aria-label="Suivant">' . $next_svg . '</button>';
            $html .= '<div class="mj-feed-post__slider-dots">';
            for ($i = 0; $i < $total; $i++) {
                $html .= '<span class="mj-feed-post__slider-dot' . ($i === 0 ? ' is-active' : '') . '" data-index="' . $i . '"></span>';
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .mj-feed-post__slider
        return $html;
    }

    /**
     * Build the HTML block for the mentioned members section.
     *
     * @param array $members Result of extractMentionedMembers().
     * @return string HTML string or empty string.
     */
    public static function buildMentionedMembersHtml(array $members): string {
        if (empty($members)) {
            return '';
        }
        $html = '<div class="mj-feed-post__member-mentions">';
        foreach ($members as $m) {
            $html .= '<div class="mj-feed-post__member-mention-chip">';
            if (!empty($m['avatarUrl'])) {
                $html .= '<img src="' . esc_url($m['avatarUrl']) . '" alt="" class="mj-feed-post__member-mention-avatar" loading="lazy">';
            } else {
                $html .= '<span class="mj-feed-post__member-mention-initial">' . esc_html($m['initial'] ?? '?') . '</span>';
            }
            $html .= '<span class="mj-feed-post__member-mention-name">' . esc_html($m['name']) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Extract YouTube video ID from a URL.
     *
     * @param string $url
     * @return string|null YouTube video ID or null
     */
    private function extractYoutubeId($url) {
        // List of YouTube URL patterns to match
        $patterns = array(
            // youtu.be/VIDEO_ID
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            // youtube.com/watch?v=VIDEO_ID
            '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
            // youtube.com/embed/VIDEO_ID
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            // youtube.com/v/VIDEO_ID
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
            // v=VIDEO_ID anywhere (as fallback)
            '/v=([a-zA-Z0-9_-]{11})/',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return isset($matches[1]) ? trim($matches[1]) : null;
            }
        }

        return null;
    }

    /**
     * Parse Open Graph and meta tags from HTML to extract link preview data.
     * Also detects YouTube URLs and returns embed data.
     *
     * @param string $html
     * @param string $url
     * @return array
     */
    private function parseLinkPreview($html, $url) {
        // First check if it's a YouTube URL
        $youtube_id = $this->extractYoutubeId($url);
        if ($youtube_id) {
            return array(
                'url' => $url,
                'title' => 'YouTube',
                'description' => 'Vidéo YouTube',
                'image' => "https://i.ytimg.com/vi/{$youtube_id}/hqdefault.jpg",
                'site_name' => 'YouTube',
                'is_youtube' => true,
                'youtube_id' => $youtube_id,
            );
        }

        $preview = array(
            'url' => $url,
            'title' => '',
            'description' => '',
            'image' => '',
            'site_name' => '',
        );

        // Use DOMDocument for parsing
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // Open Graph tags
        $og_tags = array(
            'og:title' => 'title',
            'og:description' => 'description',
            'og:image' => 'image',
            'og:site_name' => 'site_name',
        );

        foreach ($og_tags as $property => $key) {
            $nodes = $xpath->query("//meta[@property='{$property}']/@content");
            if ($nodes->length > 0) {
                $preview[$key] = trim($nodes->item(0)->nodeValue);
            }
        }

        // Twitter Card fallbacks
        if (empty($preview['title'])) {
            $nodes = $xpath->query("//meta[@name='twitter:title']/@content");
            if ($nodes->length > 0) {
                $preview['title'] = trim($nodes->item(0)->nodeValue);
            }
        }
        if (empty($preview['description'])) {
            $nodes = $xpath->query("//meta[@name='twitter:description']/@content");
            if ($nodes->length > 0) {
                $preview['description'] = trim($nodes->item(0)->nodeValue);
            }
        }
        if (empty($preview['image'])) {
            $nodes = $xpath->query("//meta[@name='twitter:image']/@content");
            if ($nodes->length > 0) {
                $preview['image'] = trim($nodes->item(0)->nodeValue);
            }
        }

        // Standard meta fallbacks
        if (empty($preview['title'])) {
            $nodes = $xpath->query("//title");
            if ($nodes->length > 0) {
                $preview['title'] = trim($nodes->item(0)->textContent);
            }
        }
        if (empty($preview['description'])) {
            $nodes = $xpath->query("//meta[@name='description']/@content");
            if ($nodes->length > 0) {
                $preview['description'] = trim($nodes->item(0)->nodeValue);
            }
        }

        // Make image URL absolute if relative
        if (!empty($preview['image']) && strpos($preview['image'], 'http') !== 0) {
            $parsed_url = parse_url($url);
            $base = $parsed_url['scheme'] . '://' . $parsed_url['host'];
            if (strpos($preview['image'], '/') === 0) {
                $preview['image'] = $base . $preview['image'];
            } else {
                $preview['image'] = $base . '/' . $preview['image'];
            }
        }

        // Sanitize output
        $preview['title'] = sanitize_text_field($preview['title']);
        $preview['description'] = sanitize_text_field(wp_trim_words($preview['description'], 30, '...'));
        $preview['image'] = esc_url_raw($preview['image']);
        $preview['site_name'] = sanitize_text_field($preview['site_name']);

        // Extract site name from URL if not found
        if (empty($preview['site_name'])) {
            $parsed = parse_url($url);
            $preview['site_name'] = isset($parsed['host']) ? preg_replace('/^www\./', '', $parsed['host']) : '';
        }

        return $preview;
    }
}
