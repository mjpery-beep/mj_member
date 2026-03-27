<?php
/**
 * AJAX Handlers pour le widget Header MJ
 *
 * - mj_header_upcoming_events : renvoie les N prochains événements publiés
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Classes\Crud\MjEvents;

/**
 * Récupère les prochains événements pour l'aperçu Agenda du header.
 * Accessible aux utilisateurs connectés et déconnectés.
 */
add_action('wp_ajax_mj_header_upcoming_events', 'mj_header_upcoming_events_handler');
add_action('wp_ajax_nopriv_mj_header_upcoming_events', 'mj_header_upcoming_events_handler');

function mj_header_upcoming_events_handler() {
    check_ajax_referer('mj-header-widget', 'nonce');

    $limit = isset($_POST['limit']) ? max(1, min(10, (int) $_POST['limit'])) : 5;

    $events = MjEvents::get_all(array(
        'statuses' => array('published'),
        'after'    => current_time('Y-m-d H:i:s'),
        'orderby'  => 'date_debut',
        'order'    => 'ASC',
        'limit'    => $limit,
    ));

    $result = array();
    foreach ($events as $event) {
        $result[] = array(
            'id'          => (int) $event->id,
            'title'       => (string) $event->title,
            'date_debut'  => (string) $event->date_debut,
            'date_fin'    => (string) $event->date_fin,
            'type'        => (string) $event->type,
            'accent_color'=> (string) ($event->accent_color ?: ''),
            'emoji'       => (string) ($event->emoji ?: ''),
            'slug'        => (string) ($event->slug ?: ''),
            'url'         => $event->slug ? get_home_url(null, '/evenement/' . $event->slug) : '',
        );
    }

    wp_send_json_success($result);
}
