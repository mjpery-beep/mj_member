<?php

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class PlayerController implements AjaxHandlerInterface
{
    private const META_KEY = 'mj_member_player_state';

    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_member_player_state_get', [$this, 'getState']);
        add_action('wp_ajax_mj_member_player_state_save', [$this, 'saveState']);
    }

    public function getState(): void
    {
        check_ajax_referer('mj-player-widget', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 401);
        }

        $userId = get_current_user_id();
        $rawState = get_user_meta($userId, self::META_KEY, true);

        if (is_string($rawState) && $rawState !== '') {
            $decoded = json_decode($rawState, true);
            if (is_array($decoded)) {
                $rawState = $decoded;
            }
        }

        $state = $this->sanitizeState(is_array($rawState) ? $rawState : array());

        wp_send_json_success(array(
            'state' => $state,
        ));
    }

    public function saveState(): void
    {
        check_ajax_referer('mj-player-widget', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Vous devez être connecté.', 'mj-member')), 401);
        }

        $rawState = isset($_POST['state']) ? wp_unslash((string) $_POST['state']) : '';
        $decoded = json_decode($rawState, true);

        if (!is_array($decoded)) {
            wp_send_json_error(array('message' => __('État du player invalide.', 'mj-member')), 400);
        }

        $state = $this->sanitizeState($decoded);
        $userId = get_current_user_id();
        update_user_meta($userId, self::META_KEY, $state);

        wp_send_json_success(array(
            'saved' => true,
            'state' => $state,
        ));
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function sanitizeState(array $state): array
    {
        $rawPlaylists = isset($state['playlists']) && is_array($state['playlists']) ? $state['playlists'] : array();
        $playlists = array();

        foreach (array_slice($rawPlaylists, 0, 30) as $playlist) {
            if (!is_array($playlist)) {
                continue;
            }

            $playlistId = isset($playlist['id']) ? sanitize_key((string) $playlist['id']) : '';
            $playlistName = isset($playlist['name']) ? sanitize_text_field((string) $playlist['name']) : '';
            $rawTracks = isset($playlist['tracks']) && is_array($playlist['tracks']) ? $playlist['tracks'] : array();
            $tracks = array();

            foreach (array_slice($rawTracks, 0, 400) as $track) {
                if (!is_array($track)) {
                    continue;
                }

                $trackId = isset($track['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $track['id']) : '';
                if (!is_string($trackId) || $trackId === '') {
                    continue;
                }

                $title = isset($track['title']) ? sanitize_text_field((string) $track['title']) : '';
                $channel = isset($track['channel']) ? sanitize_text_field((string) $track['channel']) : '';
                $thumb = isset($track['thumb']) ? esc_url_raw((string) $track['thumb']) : '';

                $tracks[] = array(
                    'id' => $trackId,
                    'title' => $title,
                    'channel' => $channel,
                    'thumb' => $thumb,
                );
            }

            if ($playlistName === '') {
                $playlistName = __('Playlist', 'mj-member');
            }

            if ($playlistId === '') {
                $playlistId = 'pl_' . wp_generate_password(8, false, false);
            }

            $playlists[] = array(
                'id' => $playlistId,
                'name' => $playlistName,
                'tracks' => $tracks,
            );
        }

        if (empty($playlists)) {
            $playlists[] = array(
                'id' => 'pl_default',
                'name' => __('Ma Playlist', 'mj-member'),
                'tracks' => array(),
            );
        }

        $activePlaylistId = isset($state['activePlaylistId']) ? sanitize_key((string) $state['activePlaylistId']) : '';
        $hasActive = false;
        foreach ($playlists as $playlist) {
            if (isset($playlist['id']) && $playlist['id'] === $activePlaylistId) {
                $hasActive = true;
                break;
            }
        }

        if (!$hasActive) {
            $activePlaylistId = isset($playlists[0]['id']) ? (string) $playlists[0]['id'] : 'pl_default';
        }

        return array(
            'playlists' => $playlists,
            'activePlaylistId' => $activePlaylistId,
        );
    }
}
