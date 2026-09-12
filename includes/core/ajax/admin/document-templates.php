<?php

namespace Mj\Member\Core\Ajax\Admin;

use Mj\Member\Classes\Crud\MjDocumentTemplates;
use Mj\Member\Core\Config;
use Mj\Member\Core\Contracts\AjaxHandlerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-only CRUD for the document-template library, used by the plugin
 * settings page (onglet "Contrat"). Gated by Config::capability() rather
 * than the Registration Manager's member-role check, since a WordPress
 * admin managing plugin settings may not have an mj_members record.
 */
final class DocumentTemplatesAdminController implements AjaxHandlerInterface
{
    private const NONCE_ACTION = 'mj_document_templates_admin';

    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_admin_update_document_template', [$this, 'updateTemplate']);
        add_action('wp_ajax_mj_admin_delete_document_template', [$this, 'deleteTemplate']);
        add_action('wp_ajax_mj_admin_set_default_document_template', [$this, 'setDefaultTemplate']);
    }

    private function verify(): bool
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_ACTION)) {
            wp_send_json_error(array('message' => __('Vérification de sécurité échouée.', 'mj-member')), 403);
            return false;
        }

        if (!current_user_can(Config::capability())) {
            wp_send_json_error(array('message' => __('Accès refusé.', 'mj-member')), 403);
            return false;
        }

        return true;
    }

    public function updateTemplate(): void
    {
        if (!$this->verify()) {
            return;
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $data = array();
        if (isset($_POST['name'])) {
            $data['name'] = wp_unslash((string) $_POST['name']);
        }
        if (isset($_POST['content'])) {
            $data['content'] = wp_unslash((string) $_POST['content']);
        }

        $result = MjDocumentTemplates::update($id, $data);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }

        wp_send_json_success(array('template' => MjDocumentTemplates::get($id)));
    }

    public function deleteTemplate(): void
    {
        if (!$this->verify()) {
            return;
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $result = MjDocumentTemplates::delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }

        wp_send_json_success(array('id' => $id));
    }

    public function setDefaultTemplate(): void
    {
        if (!$this->verify()) {
            return;
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $result = MjDocumentTemplates::set_default($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
            return;
        }

        wp_send_json_success(array('template' => MjDocumentTemplates::get($id)));
    }
}
