<?php
/**
 * AJAX handlers for front-end testimonials.
 *
 * @package MjMember
 */

use Mj\Member\Classes\Crud\MjTestimonials;
use Mj\Member\Classes\Crud\MjTestimonialReactions;
use Mj\Member\Classes\Crud\MjTestimonialComments;
use Mj\Member\Classes\Crud\MjMembers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX: Submit a new testimonial (front-end).
 */
function mj_front_testimonial_submit_handler() {
    check_ajax_referer('mj-testimonial-submit', '_wpnonce');

    // Get current member
    $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
    if (!$current_member || !isset($current_member->id)) {
        wp_send_json_error(__('Vous devez être connecté pour soumettre un témoignage.', 'mj-member'), 403);
    }

    $member_id = (int) $current_member->id;

    // Parse content
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

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

    // Parse video ID
    $video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;

    // Parse link preview
    $link_preview = null;
    if (isset($_POST['link_preview']) && !empty($_POST['link_preview'])) {
        $link_preview = wp_unslash($_POST['link_preview']);
    }

    // Validate that at least some content exists
    if (empty($content) && empty($photo_ids) && $video_id <= 0) {
        wp_send_json_error(__('Veuillez ajouter du texte, des photos ou une vidéo.', 'mj-member'), 400);
    }

    // Create testimonial
    $result = MjTestimonials::create(array(
        'member_id' => $member_id,
        'content' => $content,
        'photo_ids' => $photo_ids,
        'video_id' => $video_id > 0 ? $video_id : null,
        'link_preview' => $link_preview,
        'status' => MjTestimonials::STATUS_PENDING,
    ));

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 500);
    }

    // Trigger notification to admins
    do_action('mj_member_testimonial_created', (int) $result, $member_id);

    wp_send_json_success(array(
        'message' => __('Merci pour votre témoignage ! Il sera visible après validation.', 'mj-member'),
        'id' => $result,
    ));
}
add_action('wp_ajax_mj_front_testimonial_submit', 'mj_front_testimonial_submit_handler');

/**
 * AJAX: Get approved testimonials for display.
 */
function mj_front_testimonial_list_handler() {
    $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? min(50, max(1, (int) $_POST['per_page'])) : 10;

    $testimonials = MjTestimonials::get_approved(array(
        'page' => $page,
        'per_page' => $per_page,
        'orderby' => 'created_at',
        'order' => 'DESC',
    ));

    $total = MjTestimonials::count(array('status' => MjTestimonials::STATUS_APPROVED));
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
            'content' => isset($t->content) ? $t->content : '',
            'photos' => $photos,
            'video' => $video,
            'linkPreview' => $link_preview,
            'memberName' => $member_name,
            'createdAt' => isset($t->created_at) ? $t->created_at : '',
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
add_action('wp_ajax_mj_front_testimonial_list', 'mj_front_testimonial_list_handler');
add_action('wp_ajax_nopriv_mj_front_testimonial_list', 'mj_front_testimonial_list_handler');

/**
 * AJAX: Upload media for testimonial (photo or video).
 */
function mj_front_testimonial_upload_handler() {
    check_ajax_referer('mj-testimonial-submit', '_wpnonce');

    // Get current member
    $current_member = function_exists('mj_member_get_current_member') ? mj_member_get_current_member() : null;
    if (!$current_member || !isset($current_member->id)) {
        wp_send_json_error(__('Vous devez être connecté pour envoyer des fichiers.', 'mj-member'), 403);
    }

    if (empty($_FILES['file'])) {
        wp_send_json_error(__('Aucun fichier reçu.', 'mj-member'), 400);
    }

    $file = $_FILES['file'];
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
add_action('wp_ajax_mj_front_testimonial_upload', 'mj_front_testimonial_upload_handler');

/**
 * AJAX: Get member's own testimonials.
 */
function mj_front_testimonial_my_list_handler() {
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
            'content' => isset($t->content) ? $t->content : '',
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
add_action('wp_ajax_mj_front_testimonial_my_list', 'mj_front_testimonial_my_list_handler');

/**
 * AJAX: Add/toggle a reaction on a testimonial.
 */
function mj_front_testimonial_react_handler() {
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
add_action('wp_ajax_mj_front_testimonial_react', 'mj_front_testimonial_react_handler');

/**
 * AJAX: Remove reaction from a testimonial.
 */
function mj_front_testimonial_unreact_handler() {
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
add_action('wp_ajax_mj_front_testimonial_unreact', 'mj_front_testimonial_unreact_handler');

/**
 * AJAX: Add a comment to a testimonial.
 */
function mj_front_testimonial_comment_add_handler() {
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
add_action('wp_ajax_mj_front_testimonial_comment_add', 'mj_front_testimonial_comment_add_handler');

/**
 * AJAX: Get comments for a testimonial.
 */
function mj_front_testimonial_comments_list_handler() {
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
add_action('wp_ajax_mj_front_testimonial_comments_list', 'mj_front_testimonial_comments_list_handler');
add_action('wp_ajax_nopriv_mj_front_testimonial_comments_list', 'mj_front_testimonial_comments_list_handler');

/**
 * AJAX: Delete a comment (owner only).
 */
function mj_front_testimonial_comment_delete_handler() {
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
add_action('wp_ajax_mj_front_testimonial_comment_delete', 'mj_front_testimonial_comment_delete_handler');

/**
 * AJAX: Get reaction summary for a testimonial.
 */
function mj_front_testimonial_reactions_summary_handler() {
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
add_action('wp_ajax_mj_front_testimonial_reactions_summary', 'mj_front_testimonial_reactions_summary_handler');
add_action('wp_ajax_nopriv_mj_front_testimonial_reactions_summary', 'mj_front_testimonial_reactions_summary_handler');

/**
 * AJAX: Fetch link preview (Open Graph metadata) for a URL.
 */
function mj_front_testimonial_link_preview_handler() {
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

    // Fetch the URL content
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
    $preview = mj_parse_link_preview($body, $url);

    if (empty($preview['title']) && empty($preview['description']) && empty($preview['image'])) {
        wp_send_json_error(__('Aucun aperçu disponible pour ce lien.', 'mj-member'), 404);
    }

    wp_send_json_success($preview);
}
add_action('wp_ajax_mj_front_testimonial_link_preview', 'mj_front_testimonial_link_preview_handler');

/**
 * Parse Open Graph and meta tags from HTML to extract link preview data.
 *
 * @param string $html
 * @param string $url
 * @return array
 */
function mj_parse_link_preview($html, $url) {
    $preview = array(
        'url' => $url,
        'title' => '',
        'description' => '',
        'image' => '',
        'site_name' => '',
    );

    // Use DOMDocument for parsing
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

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
