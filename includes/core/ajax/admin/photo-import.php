<?php

namespace Mj\Member\Core\Ajax\Admin;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\MjNextcloudPhotoImporter;

if (!defined('ABSPATH')) {
    exit;
}

final class PhotoImportController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_member_photo_import_tags', [$this, 'memberPhotoImportTags']);
        add_action('wp_ajax_mj_member_run_photo_import', [$this, 'memberRunPhotoImport']);
        add_action('wp_ajax_mj_member_photo_import_logs', [$this, 'memberPhotoImportLogs']);
        add_action('wp_ajax_mj_member_photo_import_progress', [$this, 'memberPhotoImportProgress']);
        add_action('wp_ajax_mj_member_photo_import_start', [$this, 'memberPhotoImportStart']);
        add_action('wp_ajax_mj_member_photo_import_worker', [$this, 'memberPhotoImportWorker']);
        add_action('wp_ajax_nopriv_mj_member_photo_import_worker', [$this, 'memberPhotoImportWorker']);
    }

    public function memberPhotoImportTags(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    check_ajax_referer('mj_member_photo_import_admin', 'nonce');

    $tags = MjNextcloudPhotoImporter::listAvailableTags();
    if (is_wp_error($tags)) {
        wp_send_json_error(array('message' => $tags->get_error_message()));
    }

    wp_send_json_success(array(
        'tags' => $tags,
    ));
}

    public function memberRunPhotoImport(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    check_ajax_referer('mj_member_photo_import_admin', 'nonce');

    $selectedTags = isset($_POST['tags']) ? wp_unslash($_POST['tags']) : array();
    if (!is_array($selectedTags)) {
        $selectedTags = array();
    }

    $selectedTags = array_values(array_filter(array_map(static function ($value): string {
        return sanitize_text_field((string) $value);
    }, $selectedTags), static function (string $tag): bool {
        return $tag !== '';
    }));

    $runId = isset($_POST['runId']) ? sanitize_text_field((string) wp_unslash($_POST['runId'])) : '';

    $result = MjNextcloudPhotoImporter::importByTagNames($selectedTags, $runId);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success($result);
}

    public function memberPhotoImportStart(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    check_ajax_referer('mj_member_photo_import_admin', 'nonce');

    $selectedTags = isset($_POST['tags']) ? wp_unslash($_POST['tags']) : array();
    if (!is_array($selectedTags)) {
        $selectedTags = array();
    }

    $selectedTags = array_values(array_filter(array_map(static function ($value): string {
        return sanitize_text_field((string) $value);
    }, $selectedTags), static function (string $tag): bool {
        return $tag !== '';
    }));

    if (empty($selectedTags)) {
        wp_send_json_error(array('message' => __('Sélectionnez au moins une étiquette.', 'mj-member')));
    }

    $runId = isset($_POST['runId']) ? sanitize_text_field((string) wp_unslash($_POST['runId'])) : '';
    if ($runId === '') {
        $runId = 'photoimport-' . wp_generate_password(10, false, false) . '-' . time();
    }

    MjNextcloudPhotoImporter::startRuntimeTracking($runId, array(
        'step' => 'queued',
        'status' => 'running',
    ));
    MjNextcloudPhotoImporter::pushRuntimeMessage($runId, __('Worker import en file d’attente...', 'mj-member'), array('step' => 'queued'));

    $token = wp_generate_password(32, false, false);
    $payload = array(
        'token' => $token,
        'tags' => $selectedTags,
        'created_at' => time(),
    );
    set_transient('mj_member_photo_import_worker_' . $runId, $payload, 30 * MINUTE_IN_SECONDS);

    $dispatch = wp_remote_post(admin_url('admin-ajax.php'), array(
        'timeout' => 4,
        'blocking' => false,
        'sslverify' => apply_filters('https_local_ssl_verify', false),
        'body' => array(
            'action' => 'mj_member_photo_import_worker',
            'runId' => $runId,
            'token' => $token,
        ),
    ));

    if (is_wp_error($dispatch)) {
        MjNextcloudPhotoImporter::pushRuntimeMessage(
            $runId,
            __('Impossible de démarrer le worker en arrière-plan. Vérifiez le loopback WordPress.', 'mj-member'),
            array('step' => 'error', 'status' => 'error', 'level' => 'error')
        );

        wp_send_json_error(array(
            'message' => __('Le worker n’a pas pu démarrer. Vérifiez la configuration loopback du serveur.', 'mj-member'),
            'runId' => $runId,
        ));
    }

    wp_send_json_success(array(
        'runId' => $runId,
        'message' => __('Import démarré. Suivez la progression ci-dessous.', 'mj-member'),
    ));
}

    public function memberPhotoImportWorker(): void
{
    if (function_exists('ignore_user_abort')) {
        ignore_user_abort(true);
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $runId = isset($_POST['runId']) ? sanitize_text_field((string) wp_unslash($_POST['runId'])) : '';
    $token = isset($_POST['token']) ? sanitize_text_field((string) wp_unslash($_POST['token'])) : '';
    if ($runId === '' || $token === '') {
        wp_die('missing-params');
    }

    $stored = get_transient('mj_member_photo_import_worker_' . $runId);
    if (!is_array($stored) || !isset($stored['token']) || !hash_equals((string) $stored['token'], $token)) {
        wp_die('invalid-token');
    }

    delete_transient('mj_member_photo_import_worker_' . $runId);

    MjNextcloudPhotoImporter::pushRuntimeMessage($runId, __('Worker démarré, initialisation de l’import...', 'mj-member'), array('step' => 'worker_started'));

    $tags = isset($stored['tags']) && is_array($stored['tags']) ? $stored['tags'] : array();
    $result = MjNextcloudPhotoImporter::importByTagNames($tags, $runId);

    if (is_wp_error($result)) {
        wp_die('error:' . $result->get_error_message());
    }

    wp_die('ok');
}

    public function memberPhotoImportProgress(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    check_ajax_referer('mj_member_photo_import_admin', 'nonce');

    $runId = isset($_POST['runId']) ? sanitize_text_field((string) wp_unslash($_POST['runId'])) : '';
    if ($runId === '') {
        wp_send_json_error(array('message' => __('Identifiant de suivi manquant.', 'mj-member')));
    }

    $state = MjNextcloudPhotoImporter::getRuntimeState($runId);
    wp_send_json_success(array(
        'state' => $state,
    ));
}

    public function memberPhotoImportLogs(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
    }

    check_ajax_referer('mj_member_photo_import_admin', 'nonce');

    $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 120;
    $limit = max(20, min(400, $limit));

    $logPath = WP_CONTENT_DIR . '/debug.log';
    if (!file_exists($logPath) || !is_readable($logPath)) {
        wp_send_json_success(array(
            'lines' => '',
            'count' => 0,
            'message' => __('Aucun fichier debug.log lisible.', 'mj-member'),
        ));
    }

    $tail = $this->tailLog($logPath, 3000);
    if (!is_array($tail)) {
        $tail = array();
    }

    $filtered = array();
    foreach ($tail as $line) {
        if (strpos((string) $line, '[mj-member][photo-import]') !== false) {
            $filtered[] = rtrim((string) $line, "\r\n");
        }
    }

    if (count($filtered) > $limit) {
        $filtered = array_slice($filtered, -$limit);
    }

    wp_send_json_success(array(
        'lines' => implode("\n", $filtered),
        'count' => count($filtered),
        'message' => sprintf(__('Logs photo import: %d ligne(s).', 'mj-member'), count($filtered)),
    ));
}

    private function tailLog(string $path, int $maxLines = 3000): array
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return array();
    }

    $lines = array();
    $buffer = '';
    $position = -1;

    fseek($handle, 0, SEEK_END);
    $fileSize = ftell($handle);
    if ($fileSize === 0) {
        fclose($handle);
        return array();
    }

    while (-$position <= $fileSize && count($lines) < $maxLines) {
        fseek($handle, $position, SEEK_END);
        $char = fgetc($handle);

        if ($char === "\n") {
            if ($buffer !== '') {
                $lines[] = strrev($buffer);
                $buffer = '';
            }
        } else {
            $buffer .= $char;
        }

        $position--;
    }

    if ($buffer !== '') {
        $lines[] = strrev($buffer);
    }

    fclose($handle);

    return array_reverse($lines);
    }
}
