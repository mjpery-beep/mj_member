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
use Mj\Member\Classes\Crud\MjEvents;

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

    // Parse event slug (from EventPage submissions)
    $event_slug = isset($_POST['event_slug']) ? sanitize_title(wp_unslash($_POST['event_slug'])) : '';

    // Parse content
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

    // Prepend @slug to content when submitted from an event page
    if ($event_slug !== '' && $content !== '') {
        $mention = '@' . $event_slug;
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

    // Check if member is trusted - auto-approve if true
    $is_trusted = isset($current_member->is_trusted_member) && (int) $current_member->is_trusted_member === 1;
    $initial_status = $is_trusted ? MjTestimonials::STATUS_APPROVED : MjTestimonials::STATUS_PENDING;

    // Create testimonial
    $create_data = array(
        'member_id' => $member_id,
        'content' => $content,
        'photo_ids' => $photo_ids,
        'video_id' => $video_id > 0 ? $video_id : null,
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
add_action('wp_ajax_mj_front_testimonial_submit', 'mj_front_testimonial_submit_handler');

/**
 * AJAX: Get approved testimonials for display.
 */
function mj_front_testimonial_list_handler() {
    $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? min(50, max(1, (int) $_POST['per_page'])) : 10;
    $featured_only = isset($_POST['featured_only']) && $_POST['featured_only'] === '1';

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
            'content' => isset($t->content) ? mj_member_testimonial_linkify_event_mentions($t->content) : '',
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
            'content' => isset($t->content) ? mj_member_testimonial_linkify_event_mentions($t->content) : '',
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

    // Check if it's YouTube first
    $youtube_id = mj_extract_youtube_id($url);
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
    $preview = mj_parse_link_preview($body, $url);

    if (empty($preview['title']) && empty($preview['description']) && empty($preview['image'])) {
        wp_send_json_error(__('Aucun aperçu disponible pour ce lien.', 'mj-member'), 404);
    }

    wp_send_json_success($preview);
}
add_action('wp_ajax_mj_front_testimonial_link_preview', 'mj_front_testimonial_link_preview_handler');

/**
 * Extract YouTube video ID from a URL.
 *
 * @param string $url
 * @return string|null YouTube video ID or null
 */
function mj_extract_youtube_id($url) {
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
function mj_parse_link_preview($html, $url) {
    // First check if it's a YouTube URL
    $youtube_id = mj_extract_youtube_id($url);
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

/**
 * AJAX: Approve a testimonial (animators only).
 */
function mj_front_testimonial_approve_handler() {
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
add_action('wp_ajax_mj_front_testimonial_approve', 'mj_front_testimonial_approve_handler');

/**
 * AJAX: Reject a testimonial (animators only).
 */
function mj_front_testimonial_reject_handler() {
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
add_action('wp_ajax_mj_front_testimonial_reject', 'mj_front_testimonial_reject_handler');

/**
 * AJAX: Get pending testimonials for animators.
 */
function mj_front_testimonial_pending_list_handler() {
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
            'content' => isset($t->content) ? mj_member_testimonial_linkify_event_mentions($t->content) : '',
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
add_action('wp_ajax_mj_front_testimonial_pending_list', 'mj_front_testimonial_pending_list_handler');

/**
 * AJAX: Search events for @mention autocomplete in testimonials.
 *
 * Returns a list of events matching the search query (title or slug).
 * Used by the front-end autocomplete when users type @.
 */
function mj_front_testimonial_search_events_handler() {
    check_ajax_referer('mj-testimonial-submit', '_wpnonce');

    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    if (mb_strlen($search) < 1) {
        wp_send_json_success(array('events' => array()));
    }

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
add_action('wp_ajax_mj_front_testimonial_search_events', 'mj_front_testimonial_search_events_handler');

/**
 * AJAX: Delete own testimonial (front-end).
 *
 * Verifies ownership by matching current member ID to testimonial member_id.
 */
function mj_front_testimonial_delete_handler() {
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
add_action('wp_ajax_mj_front_testimonial_delete', 'mj_front_testimonial_delete_handler');

/**
 * AJAX: Edit own testimonial content (front-end).
 *
 * Verifies ownership by matching current member ID to testimonial member_id.
 * Only the text content can be changed; photos/video stay unchanged.
 */
function mj_front_testimonial_edit_handler() {
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

    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';

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

    // Parse video_id if provided (0 = remove, >0 = set)
    if (isset($_POST['video_id'])) {
        $update_data['video_id'] = (int) $_POST['video_id'];
    }

    // Must have at least content or media
    $has_content   = !empty(trim($content));
    $has_photos    = isset($update_data['photo_ids']) ? !empty($update_data['photo_ids']) : !empty(MjTestimonials::parse_photo_ids($testimonial));
    $has_video     = isset($update_data['video_id']) ? ($update_data['video_id'] > 0) : ((int)($testimonial->video_id ?? 0) > 0);

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
    $html_content = wp_kses_post(wpautop(mj_member_testimonial_linkify_event_mentions($final_content)));

    // Build photos array for JS
    $photos = MjTestimonials::get_photo_urls($fresh, 'large');
    $photos_for_js = array_map(function($p) {
        return array('id' => $p['id'], 'url' => $p['url'], 'full' => $p['full']);
    }, $photos);

    // Build video data for JS
    $video = MjTestimonials::get_video_data($fresh);
    $video_for_js = $video ? array('id' => $video['id'], 'url' => $video['url']) : null;

    // Build photos HTML for DOM replacement
    $photos_html = '';
    if (!empty($photos)) {
        $photos_html .= '<div class="mj-feed-post__media mj-feed-post__media--photos-' . min(count($photos), 5) . '">';
        foreach (array_slice($photos, 0, 5) as $index => $photo) {
            $photos_html .= '<a href="' . esc_url($photo['full']) . '" class="mj-feed-post__photo" data-lightbox="post-' . $testimonial_id . '">';
            $photos_html .= '<img src="' . esc_url($photo['url']) . '" alt="" loading="lazy">';
            if ($index === 4 && count($photos) > 5) {
                $photos_html .= '<span class="mj-feed-post__photo-more">+' . (count($photos) - 5) . '</span>';
            }
            $photos_html .= '</a>';
        }
        $photos_html .= '</div>';
    }

    // Build video HTML for DOM replacement
    $video_html = '';
    if ($video) {
        $video_html = '<div class="mj-feed-post__media mj-feed-post__media--video">';
        $video_html .= '<video controls playsinline poster="' . esc_url($video['poster']) . '">';
        $video_html .= '<source src="' . esc_url($video['url']) . '" type="video/mp4">';
        $video_html .= '</video></div>';
    }

    wp_send_json_success(array(
        'message'     => __('Témoignage modifié.', 'mj-member'),
        'content'     => $final_content,
        'contentHtml' => $html_content,
        'photos'      => $photos_for_js,
        'photosHtml'  => $photos_html,
        'video'       => $video_for_js,
        'videoHtml'   => $video_html,
    ));
}
add_action('wp_ajax_mj_front_testimonial_edit', 'mj_front_testimonial_edit_handler');

/**
 * Convert @event-slug mentions in testimonial content to clickable links.
 *
 * Matches patterns like @mon-evenement-slug and replaces them with
 * an anchor link pointing to the event page.
 *
 * @param string $content The raw testimonial content.
 * @return string Content with @mentions converted to links.
 */
function mj_member_testimonial_linkify_event_mentions(string $content): string {
    // Match @followed-by-slug-chars (letters, digits, hyphens)
    return preg_replace_callback(
        '/@([a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)\b/i',
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
 * AJAX: Toggle featured status for a testimonial (animator/coordinator only).
 */
function mj_front_testimonial_toggle_featured_handler() {
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
add_action('wp_ajax_mj_front_testimonial_toggle_featured', 'mj_front_testimonial_toggle_featured_handler');
