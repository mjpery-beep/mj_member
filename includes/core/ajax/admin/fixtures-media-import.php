<?php

namespace Mj\Member\Core\Ajax\Admin;

use Mj\Member\Classes\MjFixturesMediaImporter;
use Mj\Member\Core\Contracts\AjaxHandlerInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class FixturesMediaImportController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_member_fixture_media_restore_start', [$this, 'start']);
        add_action('wp_ajax_mj_member_fixture_media_restore_progress', [$this, 'progress']);
        add_action('wp_ajax_mj_member_fixture_media_restore_worker', [$this, 'worker']);
        add_action('wp_ajax_nopriv_mj_member_fixture_media_restore_worker', [$this, 'worker']);
    }

    public function start(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        check_ajax_referer('mj_member_fixtures_media_admin', 'nonce');

        $runId = isset($_POST['runId']) ? sanitize_text_field((string) wp_unslash($_POST['runId'])) : '';
        $cleanBefore = !empty($_POST['cleanBefore']);

        $result = MjFixturesMediaImporter::start($runId, $cleanBefore);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function progress(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
        }

        check_ajax_referer('mj_member_fixtures_media_admin', 'nonce');

        $runId = isset($_POST['runId']) ? sanitize_text_field((string) wp_unslash($_POST['runId'])) : '';
        if ($runId === '') {
            wp_send_json_error(array('message' => __('Identifiant de suivi manquant.', 'mj-member')));
        }

        $state = MjFixturesMediaImporter::getState($runId);
        wp_send_json_success(array('state' => $state));
    }

    public function worker(): void
    {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $runId = isset($_POST['runId']) ? sanitize_text_field((string) wp_unslash($_POST['runId'])) : '';
        $token = isset($_POST['token']) ? sanitize_text_field((string) wp_unslash($_POST['token'])) : '';

        $result = MjFixturesMediaImporter::processWorker($runId, $token);
        if (is_wp_error($result)) {
            wp_die('error:' . $result->get_error_message());
        }

        wp_die('ok');
    }
}
