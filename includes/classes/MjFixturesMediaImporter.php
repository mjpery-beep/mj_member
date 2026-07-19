<?php

namespace Mj\Member\Classes;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjFixturesMediaImporter
{
    private const STATE_PREFIX = 'mj_member_fixture_media_state_';
    private const QUEUE_PREFIX = 'mj_member_fixture_media_queue_';
    private const TOKEN_PREFIX = 'mj_member_fixture_media_worker_';
    private const STATE_TTL = 2 * HOUR_IN_SECONDS;

    public static function start(string $runId = '', bool $cleanBefore = false): array|WP_Error
    {
        $runId = sanitize_key($runId);
        if ($runId === '') {
            $runId = 'fixtures-media-' . wp_generate_password(10, false, false) . '-' . time();
        }

        $queue = self::loadQueueItems();
        if (is_wp_error($queue)) {
            return $queue;
        }

        if (empty($queue)) {
            return new WP_Error('fixtures_media_empty', __('Aucune URL media exploitable dans wp_media.json.', 'mj-member'));
        }

        if ($cleanBefore) {
            $ids = get_posts(array(
                'post_type' => 'attachment',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
            ));
            foreach ((array) $ids as $id) {
                wp_delete_attachment((int) $id, true);
            }
        }

        $state = array(
            'runId' => $runId,
            'status' => 'running',
            'step' => 'queued',
            'started_at' => time(),
            'updated_at' => time(),
            'total' => count($queue),
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'current' => '',
            'events' => array(),
        );

        self::pushEvent($state, __('File d\'import wp_media initialisée.', 'mj-member'));
        self::saveState($runId, $state);
        set_transient(self::QUEUE_PREFIX . $runId, $queue, self::STATE_TTL);

        $token = wp_generate_password(32, false, false);
        set_transient(self::TOKEN_PREFIX . $runId, array('token' => $token, 'created_at' => time()), self::STATE_TTL);

        $dispatched = self::dispatchWorker($runId, $token);
        if (is_wp_error($dispatched)) {
            $state['status'] = 'error';
            $state['step'] = 'dispatch_failed';
            self::pushEvent($state, __('Impossible de démarrer le worker en arrière-plan.', 'mj-member'), 'error');
            self::saveState($runId, $state);
            return $dispatched;
        }

        return array(
            'runId' => $runId,
            'message' => __('Import wp_media démarré.', 'mj-member'),
            'total' => (int) $state['total'],
        );
    }

    public static function processWorker(string $runId, string $token): array|WP_Error
    {
        $runId = sanitize_key($runId);
        if ($runId === '' || $token === '') {
            return new WP_Error('fixtures_media_worker_params', __('Paramètres worker manquants.', 'mj-member'));
        }

        $stored = get_transient(self::TOKEN_PREFIX . $runId);
        if (!is_array($stored) || empty($stored['token']) || !hash_equals((string) $stored['token'], $token)) {
            return new WP_Error('fixtures_media_worker_token', __('Token worker invalide.', 'mj-member'));
        }

        $state = self::getState($runId);
        if (!is_array($state)) {
            return new WP_Error('fixtures_media_state_missing', __('Etat runtime introuvable.', 'mj-member'));
        }

        if (($state['status'] ?? '') !== 'running') {
            return array('status' => $state['status'] ?? 'done');
        }

        $queue = get_transient(self::QUEUE_PREFIX . $runId);
        if (!is_array($queue)) {
            $state['status'] = 'error';
            $state['step'] = 'queue_missing';
            self::pushEvent($state, __('Queue runtime introuvable.', 'mj-member'), 'error');
            self::saveState($runId, $state);
            return new WP_Error('fixtures_media_queue_missing', __('Queue runtime introuvable.', 'mj-member'));
        }

        $processed = (int) ($state['processed'] ?? 0);
        $total = (int) ($state['total'] ?? 0);

        if ($processed >= $total) {
            $state['status'] = 'done';
            $state['step'] = 'completed';
            self::pushEvent($state, __('Import wp_media terminé.', 'mj-member'));
            self::saveState($runId, $state);
            delete_transient(self::TOKEN_PREFIX . $runId);
            delete_transient(self::QUEUE_PREFIX . $runId);
            return array('status' => 'done');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $currentItem = $queue[$processed] ?? array();
        $currentUrl = is_array($currentItem) ? (string) ($currentItem['source_url'] ?? '') : '';
        $state['current'] = $currentUrl;
        $state['step'] = 'downloading';

        $overwrite = !empty(MjFixturesManager::getImageImportSettings()['overwrite_existing']);
        $result = self::importOne($currentItem, $overwrite);

        if (($result['status'] ?? '') === 'imported') {
            $state['imported'] = (int) ($state['imported'] ?? 0) + 1;
            self::pushEvent($state, (string) ($result['message'] ?? __('Image importée.', 'mj-member')));
        } elseif (($result['status'] ?? '') === 'skipped') {
            $state['skipped'] = (int) ($state['skipped'] ?? 0) + 1;
            self::pushEvent($state, (string) ($result['message'] ?? __('Image ignorée.', 'mj-member')), 'warning');
        } else {
            $state['failed'] = (int) ($state['failed'] ?? 0) + 1;
            self::pushEvent($state, (string) ($result['message'] ?? __('Echec import image.', 'mj-member')), 'error');
        }

        $state['processed'] = $processed + 1;
        $state['updated_at'] = time();

        if ((int) $state['processed'] >= $total) {
            $state['status'] = 'done';
            $state['step'] = 'completed';
            self::pushEvent($state, __('Import wp_media terminé.', 'mj-member'));
            self::saveState($runId, $state);
            delete_transient(self::TOKEN_PREFIX . $runId);
            delete_transient(self::QUEUE_PREFIX . $runId);
            return array('status' => 'done');
        }

        self::saveState($runId, $state);
        $dispatch = self::dispatchWorker($runId, $token);
        if (is_wp_error($dispatch)) {
            $state['status'] = 'error';
            $state['step'] = 'dispatch_failed';
            self::pushEvent($state, __('Worker interrompu: impossible de planifier l\'étape suivante.', 'mj-member'), 'error');
            self::saveState($runId, $state);
            return $dispatch;
        }

        return array('status' => 'running');
    }

    public static function getState(string $runId): ?array
    {
        $runId = sanitize_key($runId);
        if ($runId === '') {
            return null;
        }

        $state = get_transient(self::STATE_PREFIX . $runId);
        return is_array($state) ? $state : null;
    }

    private static function loadQueueItems(): array|WP_Error
    {
        $path = MjFixturesManager::getFixturesDir() . 'wp_media.json';
        if (!is_readable($path)) {
            return new WP_Error('fixtures_media_file_missing', __('wp_media.json introuvable dans data/fixtures.', 'mj-member'));
        }

        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
            return new WP_Error('fixtures_media_invalid', __('wp_media.json est invalide (items manquant).', 'mj-member'));
        }

        $queue = array();
        foreach ((array) $decoded['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sourceUrl = isset($item['source_url']) ? esc_url_raw((string) $item['source_url']) : '';
            if ($sourceUrl === '' || !preg_match('/^https?:\/\//i', $sourceUrl)) {
                continue;
            }

            $queue[] = array(
                'source_url' => $sourceUrl,
                'source_file_name' => sanitize_file_name((string) ($item['source_file_name'] ?? '')),
                'post' => isset($item['post']) && is_array($item['post']) ? $item['post'] : array(),
            );
        }

        return $queue;
    }

    private static function importOne(array $item, bool $overwrite): array
    {
        $sourceUrl = esc_url_raw((string) ($item['source_url'] ?? ''));
        if ($sourceUrl === '' || !preg_match('/^https?:\/\//i', $sourceUrl)) {
            return array('status' => 'failed', 'message' => __('URL source invalide.', 'mj-member'));
        }

        $existing = self::findAttachmentBySourceUrl($sourceUrl);
        if ($existing > 0 && !$overwrite) {
            return array('status' => 'skipped', 'message' => sprintf(__('Déjà présent: %s', 'mj-member'), $sourceUrl));
        }

        if ($existing > 0 && $overwrite) {
            wp_delete_attachment($existing, true);
        }

        $fallbackName = (string) ($item['source_file_name'] ?? 'fixture-media.jpg');
        $filename = self::filenameFromUrl($sourceUrl, $fallbackName);
        $tmp = download_url($sourceUrl, 25);
        if (is_wp_error($tmp) || !is_string($tmp) || $tmp === '') {
            return array('status' => 'failed', 'message' => sprintf(__('Echec téléchargement: %s', 'mj-member'), $sourceUrl));
        }

        $fileArray = array(
            'name' => $filename,
            'tmp_name' => $tmp,
        );

        $post = isset($item['post']) && is_array($item['post']) ? $item['post'] : array();
        $attachmentArgs = array(
            'post_title' => sanitize_text_field((string) ($post['post_title'] ?? pathinfo($filename, PATHINFO_FILENAME))),
            'post_excerpt' => sanitize_text_field((string) ($post['post_excerpt'] ?? '')),
            'post_content' => sanitize_textarea_field((string) ($post['post_content'] ?? '')),
            'post_mime_type' => sanitize_text_field((string) ($post['post_mime_type'] ?? 'application/octet-stream')),
        );

        $attachmentId = media_handle_sideload($fileArray, 0, $attachmentArgs['post_content'], $attachmentArgs);
        if (is_wp_error($attachmentId) || (int) $attachmentId <= 0) {
            @unlink($tmp);
            return array('status' => 'failed', 'message' => sprintf(__('Echec insertion media: %s', 'mj-member'), $sourceUrl));
        }

        update_post_meta((int) $attachmentId, '_mj_fixture_source_url', $sourceUrl);

        return array('status' => 'imported', 'message' => sprintf(__('Importée: %s', 'mj-member'), $sourceUrl));
    }

    private static function findAttachmentBySourceUrl(string $sourceUrl): int
    {
        $rows = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_mj_fixture_source_url',
            'meta_value' => $sourceUrl,
            'suppress_filters' => false,
        ));

        if (!empty($rows[0])) {
            return (int) $rows[0];
        }

        return 0;
    }

    private static function filenameFromUrl(string $url, string $fallback): string
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $basename = sanitize_file_name(basename($path));
        if ($basename !== '') {
            return $basename;
        }

        $cleanFallback = sanitize_file_name($fallback);
        if ($cleanFallback !== '') {
            return $cleanFallback;
        }

        return 'fixture-media-' . time() . '.jpg';
    }

    private static function saveState(string $runId, array $state): void
    {
        set_transient(self::STATE_PREFIX . $runId, $state, self::STATE_TTL);
    }

    private static function pushEvent(array &$state, string $message, string $level = 'info'): void
    {
        if (!isset($state['events']) || !is_array($state['events'])) {
            $state['events'] = array();
        }

        $state['events'][] = array(
            'ts' => time(),
            'level' => $level,
            'message' => $message,
        );

        if (count($state['events']) > 250) {
            $state['events'] = array_slice($state['events'], -250);
        }
    }

    private static function dispatchWorker(string $runId, string $token): true|WP_Error
    {
        $response = wp_remote_post(admin_url('admin-ajax.php'), array(
            'timeout' => 4,
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => array(
                'action' => 'mj_member_fixture_media_restore_worker',
                'runId' => $runId,
                'token' => $token,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }
}
