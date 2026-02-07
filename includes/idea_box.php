<?php

use Mj\Member\Classes\Crud\MjIdeas;
use Mj\Member\Classes\Crud\MjIdeaVotes;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;
use Mj\Member\Classes\Value\MemberData;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_idea_box_resolve_member')) {
    function mj_member_idea_box_resolve_member(): ?MemberData
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $userId = get_current_user_id();
        if ($userId <= 0 || !class_exists(MjMembers::class)) {
            return null;
        }

        $member = MjMembers::getByWpUserId($userId);
        if (!($member instanceof MemberData)) {
            return null;
        }

        $status = (string) $member->get('status', '');
        if ($status !== 'active') {
            return null;
        }

        return $member;
    }
}

if (!function_exists('mj_member_idea_box_member_payload')) {
    /**
     * @return array<string,mixed>
     */
    function mj_member_idea_box_member_payload(?MemberData $member): array
    {
        if (!($member instanceof MemberData)) {
            return array(
                'id' => 0,
                'name' => '',
                'role' => '',
            );
        }

        $memberId = (int) $member->get('id', 0);
        $firstName = sanitize_text_field((string) $member->get('first_name', ''));
        $lastName = sanitize_text_field((string) $member->get('last_name', ''));
        $name = trim($firstName . ' ' . $lastName);
        if ($name === '') {
            $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
        }

        return array(
            'id' => $memberId,
            'name' => $name,
            'role' => sanitize_key((string) $member->get('role', '')),
        );
    }
}

if (!function_exists('mj_member_idea_box_localize')) {
    function mj_member_idea_box_localize(): void
    {
        static $localized = false;
        if ($localized) {
            return;
        }

        if (!wp_script_is('mj-member-idea-box', 'enqueued')) {
            return;
        }

        $member = mj_member_idea_box_resolve_member();
        $memberPayload = mj_member_idea_box_member_payload($member);
        $memberId = isset($memberPayload['id']) ? (int) $memberPayload['id'] : 0;

        $config = array(
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce('mj_member_idea_box'),
            'roles' => \Mj\Member\Classes\MjRoles::getJsConfig(),
            'actions' => array(
                'fetch' => 'mj_member_idea_box_fetch',
                'create' => 'mj_member_idea_box_create',
                'vote' => 'mj_member_idea_box_vote',
                'delete' => 'mj_member_idea_box_delete',
            ),
            'memberId' => $memberId,
            'member' => $memberPayload,
            'hasAccess' => $member instanceof MemberData,
            'maxLengths' => array(
                'title' => 180,
                'content' => 1000,
            ),
            'i18n' => array(
                'loading' => __('Chargement des idées…', 'mj-member'),
                'empty' => __('Aucune idée proposée pour le moment.', 'mj-member'),
                'loadError' => __('Impossible de charger les idées.', 'mj-member'),
                'submit' => __('Partager', 'mj-member'),
                'titlePlaceholder' => __('Titre de votre idée', 'mj-member'),
                'contentPlaceholder' => __('Décrivez votre idée…', 'mj-member'),
                'formError' => __('Merci de saisir une idée.', 'mj-member'),
                'titleError' => __('Merci de saisir un titre.', 'mj-member'),
                'createError' => __('Impossible d’enregistrer votre idée.', 'mj-member'),
                'voteError' => __('Impossible de mettre à jour le vote.', 'mj-member'),
                'voteOwnIdea' => __('Vous ne pouvez pas voter pour votre propre idée.', 'mj-member'),
                'accessDenied' => __('Vous devez être connecté pour participer.', 'mj-member'),
                'voteLabel' => __('+1', 'mj-member'),
                'votesOne' => __('%d soutien', 'mj-member'),
                'votesMany' => __('%d soutiens', 'mj-member'),
                'justNow' => __('À l’instant', 'mj-member'),
                'characterCount' => __('%1$s / %2$s caractères', 'mj-member'),
                'deleteLabel' => __('Supprimer', 'mj-member'),
                'deleteConfirm' => __('Confirmer la suppression de cette idée ?', 'mj-member'),
                'deleteError' => __('Impossible de supprimer l’idée.', 'mj-member'),
            ),
        );

        wp_localize_script('mj-member-idea-box', 'mjMemberIdeaBox', $config);
        $localized = true;
    }
}

if (!function_exists('mj_member_ajax_idea_box_fetch')) {
    function mj_member_ajax_idea_box_fetch(): void
    {
        check_ajax_referer('mj_member_idea_box', 'nonce');

        $member = mj_member_idea_box_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $memberId = (int) $member->get('id', 0);
        $memberRole = sanitize_key((string) $member->get('role', ''));
        $ideas = MjIdeas::get_with_votes(array(
            'status' => MjIdeas::STATUS_PUBLISHED,
            'orderby' => 'vote_count',
            'order' => 'DESC',
        ), $memberId, array('viewer_role' => $memberRole));

        wp_send_json_success(array(
            'ideas' => $ideas,
            'member' => mj_member_idea_box_member_payload($member),
        ));
    }
}

if (!function_exists('mj_member_ajax_idea_box_create')) {
    function mj_member_ajax_idea_box_create(): void
    {
        check_ajax_referer('mj_member_idea_box', 'nonce');

        $member = mj_member_idea_box_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $memberId = (int) $member->get('id', 0);
        if ($memberId <= 0) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }
        $memberRole = sanitize_key((string) $member->get('role', ''));

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        if ($title !== '') {
            $title = mb_substr($title, 0, 180);
        }
        if ($title === '') {
            wp_send_json_error(array('message' => __('Merci de saisir un titre.', 'mj-member')));
        }

        $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash((string) $_POST['content'])) : '';
        if ($content === '') {
            wp_send_json_error(array('message' => __('Merci de saisir une idée.', 'mj-member')));
        }

        if (mb_strlen($content) > 1000) {
            $content = mb_substr($content, 0, 1000);
        }

        $created = MjIdeas::create(array(
            'member_id' => $memberId,
            'title' => $title,
            'content' => $content,
            'status' => MjIdeas::STATUS_PUBLISHED,
        ));

        if (is_wp_error($created)) {
            wp_send_json_error(array('message' => $created->get_error_message()));
        }

        $ideaId = (int) $created;

        // Déclencher la notification pour la nouvelle idée
        do_action('mj_member_idea_published', $ideaId, $memberId, $title, $content);

        $ideas = MjIdeas::get_with_votes(array(
            'include_ids' => array($ideaId),
            'status' => MjIdeas::STATUS_PUBLISHED,
        ), $memberId, array('viewer_role' => $memberRole));

        $payload = !empty($ideas) ? $ideas[0] : null;
        if (!is_array($payload)) {
            wp_send_json_error(array('message' => __('Impossible de récupérer l’idée créée.', 'mj-member')));
        }

        wp_send_json_success(array('idea' => $payload));
    }
}

if (!function_exists('mj_member_ajax_idea_box_vote')) {
    function mj_member_ajax_idea_box_vote(): void
    {
        check_ajax_referer('mj_member_idea_box', 'nonce');

        $member = mj_member_idea_box_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $memberId = (int) $member->get('id', 0);
        if ($memberId <= 0) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $ideaId = isset($_POST['idea_id']) ? (int) $_POST['idea_id'] : 0;
        if ($ideaId <= 0) {
            wp_send_json_error(array('message' => __('Idée introuvable.', 'mj-member')));
        }

        $mode = isset($_POST['vote']) ? sanitize_key((string) $_POST['vote']) : 'add';

        $idea = MjIdeas::get($ideaId);
        if (!is_array($idea) || (int) ($idea['id'] ?? 0) !== $ideaId) {
            wp_send_json_error(array('message' => __('Idée introuvable.', 'mj-member')));
        }

        $ownerId = isset($idea['member_id']) ? (int) $idea['member_id'] : 0;
        if ($ownerId === $memberId) {
            wp_send_json_error(array('message' => __('Vous ne pouvez pas voter pour votre propre idée.', 'mj-member')));
        }

        if ($mode === 'remove') {
            $result = MjIdeaVotes::remove($ideaId, $memberId);
        } else {
            $result = MjIdeaVotes::add($ideaId, $memberId);

            // Déclencher une notification si le vote a été ajouté avec succès
            if ($result === true) {
                /**
                 * Action déclenchée quand un membre vote pour une idée.
                 *
                 * @param int $ideaId   ID de l'idée
                 * @param int $ownerId  ID du propriétaire de l'idée
                 * @param int $voterId  ID du membre qui a voté
                 * @param array $idea   Données de l'idée
                 */
                do_action('mj_member_idea_voted', $ideaId, $ownerId, $memberId, $idea);
            }
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $ideas = MjIdeas::get_with_votes(array(
            'include_ids' => array($ideaId),
            'status' => MjIdeas::STATUS_PUBLISHED,
        ), $memberId, array('viewer_role' => sanitize_key((string) $member->get('role', ''))));

        $payload = !empty($ideas) ? $ideas[0] : null;
        if (!is_array($payload)) {
            wp_send_json_error(array('message' => __('Impossible de récupérer l’idée mise à jour.', 'mj-member')));
        }

        wp_send_json_success(array('idea' => $payload));
    }
}

if (!function_exists('mj_member_ajax_idea_box_delete')) {
    function mj_member_ajax_idea_box_delete(): void
    {
        check_ajax_referer('mj_member_idea_box', 'nonce');

        $member = mj_member_idea_box_resolve_member();
        if (!($member instanceof MemberData)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $memberId = (int) $member->get('id', 0);
        if ($memberId <= 0) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $role = sanitize_key((string) $member->get('role', ''));
        $allowedRoles = array(
            sanitize_key((string) MjRoles::ANIMATEUR),
            sanitize_key((string) MjRoles::COORDINATEUR),
        );

        if (!in_array($role, $allowedRoles, true)) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        $ideaId = isset($_POST['idea_id']) ? (int) $_POST['idea_id'] : 0;
        if ($ideaId <= 0) {
            wp_send_json_error(array('message' => __('Idée introuvable.', 'mj-member')));
        }

        $idea = MjIdeas::get($ideaId);
        if (!is_array($idea) || (int) ($idea['id'] ?? 0) !== $ideaId) {
            wp_send_json_error(array('message' => __('Idée introuvable.', 'mj-member')));
        }

        $result = MjIdeas::delete($ideaId);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('idea_id' => $ideaId));
    }
}

add_action('wp_ajax_mj_member_idea_box_fetch', 'mj_member_ajax_idea_box_fetch');
add_action('wp_ajax_mj_member_idea_box_create', 'mj_member_ajax_idea_box_create');
add_action('wp_ajax_mj_member_idea_box_vote', 'mj_member_ajax_idea_box_vote');
add_action('wp_ajax_mj_member_idea_box_delete', 'mj_member_ajax_idea_box_delete');
