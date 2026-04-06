<?php
/**
 * AJAX handlers for dynamic fields configuration.
 *
 * @package MjMember
 */

namespace Mj\Member\Core\Ajax\Admin;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\Crud\MjDynamicFields;
use Mj\Member\Classes\Crud\MjDynamicFieldValues;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class DynamicFieldsController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_dynfields_list', [$this, 'dynfieldsList']);
        add_action('wp_ajax_mj_dynfields_create', [$this, 'dynfieldsCreate']);
        add_action('wp_ajax_mj_dynfields_update', [$this, 'dynfieldsUpdate']);
        add_action('wp_ajax_mj_dynfields_delete', [$this, 'dynfieldsDelete']);
        add_action('wp_ajax_mj_dynfields_reorder', [$this, 'dynfieldsReorder']);
        add_action('wp_ajax_mj_dynfields_get_member_values', [$this, 'dynfieldsGetMemberValues']);
    }

    /**
     * List all dynamic fields.
     */
    public function dynfieldsList(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
        }

        check_ajax_referer('mj_dynfields_nonce', '_nonce');

        $fields = MjDynamicFields::getAll();
        $output = array();

        foreach ($fields as $field) {
            $output[] = $this->formatField($field);
        }

        wp_send_json_success(array('fields' => $output));
    }

    /**
     * Create a new dynamic field.
     */
    public function dynfieldsCreate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
        }

        check_ajax_referer('mj_dynfields_nonce', '_nonce');

        $data = $this->parseInput();

        if (empty($data['title'])) {
            wp_send_json_error(array('message' => __('Le titre est obligatoire.', 'mj-member')));
        }

        $id = MjDynamicFields::create($data);

        if (!$id) {
            wp_send_json_error(array('message' => __('Erreur lors de la création du champ.', 'mj-member')));
        }

        $field = MjDynamicFields::getById($id);

        wp_send_json_success(array(
            'field' => $this->formatField($field),
            'message' => __('Champ créé avec succès.', 'mj-member'),
        ));
    }

    /**
     * Update an existing dynamic field.
     */
    public function dynfieldsUpdate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
        }

        check_ajax_referer('mj_dynfields_nonce', '_nonce');

        $id = isset($_POST['field_id']) ? absint($_POST['field_id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => __('ID du champ manquant.', 'mj-member')));
        }

        $data = $this->parseInput();

        if (isset($data['title']) && $data['title'] === '') {
            wp_send_json_error(array('message' => __('Le titre est obligatoire.', 'mj-member')));
        }

        $updated = MjDynamicFields::update($id, $data);

        if (!$updated) {
            wp_send_json_error(array('message' => __('Erreur lors de la mise à jour du champ.', 'mj-member')));
        }

        $field = MjDynamicFields::getById($id);

        wp_send_json_success(array(
            'field' => $this->formatField($field),
            'message' => __('Champ mis à jour.', 'mj-member'),
        ));
    }

    /**
     * Delete a dynamic field.
     */
    public function dynfieldsDelete(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
        }

        check_ajax_referer('mj_dynfields_nonce', '_nonce');

        $id = isset($_POST['field_id']) ? absint($_POST['field_id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => __('ID du champ manquant.', 'mj-member')));
        }

        $deleted = MjDynamicFields::delete($id);

        if (!$deleted) {
            wp_send_json_error(array('message' => __('Erreur lors de la suppression du champ.', 'mj-member')));
        }

        wp_send_json_success(array('message' => __('Champ supprimé.', 'mj-member')));
    }

    /**
     * Reorder fields.
     */
    public function dynfieldsReorder(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
        }

        check_ajax_referer('mj_dynfields_nonce', '_nonce');

        $order = isset($_POST['order']) ? $_POST['order'] : array();
        if (!is_array($order)) {
            $order = json_decode(wp_unslash($order), true);
        }
        if (!is_array($order) || empty($order)) {
            wp_send_json_error(array('message' => __('Ordre invalide.', 'mj-member')));
        }

        $ids = array_map('absint', $order);
        MjDynamicFields::reorder($ids);

        wp_send_json_success(array('message' => __('Ordre mis à jour.', 'mj-member')));
    }

    /**
     * Get dynamic field values for a member (used in admin/gestionnaire).
     */
    public function dynfieldsGetMemberValues(): void
    {
        $capability = Config::capability();
        if (!current_user_can($capability) && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'mj-member')));
        }

        check_ajax_referer('mj_dynfields_nonce', '_nonce');

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        if (!$member_id) {
            wp_send_json_error(array('message' => __('ID du membre manquant.', 'mj-member')));
        }

        $fields = MjDynamicFields::getAll();
        $values = MjDynamicFieldValues::getByMemberKeyed($member_id);

        $output = array();
        foreach ($fields as $field) {
            $output[] = array(
                'id'         => (int) $field->id,
                'slug'       => $field->slug,
                'title'      => $field->title,
                'fieldType'  => $field->field_type,
                'value'      => isset($values[(int) $field->id]) ? $values[(int) $field->id] : '',
                'options'    => MjDynamicFields::decodeOptions($field),
            );
        }

        wp_send_json_success(array('fields' => $output));
    }

    /**
     * Parse input data from POST request.
     *
     * @return array<string,mixed>
     */
    private function parseInput(): array
    {
        $data = array();

        if (isset($_POST['title'])) {
            $data['title'] = sanitize_text_field(wp_unslash($_POST['title']));
        }
        if (isset($_POST['slug'])) {
            $data['slug'] = sanitize_key(wp_unslash($_POST['slug']));
        }
        if (isset($_POST['field_type'])) {
            $data['field_type'] = sanitize_text_field(wp_unslash($_POST['field_type']));
        }
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_textarea_field(wp_unslash($_POST['description']));
        }
        if (isset($_POST['show_in_registration'])) {
            $data['show_in_registration'] = (int) $_POST['show_in_registration'];
        }
        if (isset($_POST['show_in_account'])) {
            $data['show_in_account'] = (int) $_POST['show_in_account'];
        }
        if (isset($_POST['is_required'])) {
            $data['is_required'] = (int) $_POST['is_required'];
        }
        if (isset($_POST['allow_other'])) {
            $data['allow_other'] = (int) $_POST['allow_other'];
        }
        if (isset($_POST['other_label'])) {
            $data['other_label'] = sanitize_text_field(wp_unslash($_POST['other_label']));
        }
        if (isset($_POST['show_in_notes'])) {
            $data['show_in_notes'] = (int) $_POST['show_in_notes'];
        }
        if (isset($_POST['youth_only'])) {
            $data['youth_only'] = (int) $_POST['youth_only'];
        }
        if (isset($_POST['options_list'])) {
            $raw = wp_unslash($_POST['options_list']);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $data['options_list'] = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode("\n", $raw)));
            } else {
                $data['options_list'] = is_array($raw) ? $raw : array();
            }
        }
        if (isset($_POST['sort_order'])) {
            $data['sort_order'] = absint($_POST['sort_order']);
        }

        return $data;
    }

    /**
     * Format a field object for JSON output.
     *
     * @param object $field
     * @return array<string,mixed>
     */
    private function formatField(object $field): array
    {
        return array(
            'id'                 => (int) $field->id,
            'slug'               => $field->slug ?? '',
            'fieldType'          => $field->field_type ?? 'text',
            'title'              => $field->title ?? '',
            'description'        => $field->description ?? '',
            'showInRegistration' => (bool) ($field->show_in_registration ?? false),
            'showInAccount'      => (bool) ($field->show_in_account ?? false),
            'isRequired'         => (bool) ($field->is_required ?? false),
            'allowOther'         => (bool) ($field->allow_other ?? false),
            'otherLabel'         => $field->other_label ?? '',
            'showInNotes'        => (bool) ($field->show_in_notes ?? false),
            'youthOnly'          => (bool) ($field->youth_only ?? false),
            'optionsList'        => MjDynamicFields::decodeOptions($field),
            'sortOrder'          => (int) ($field->sort_order ?? 0),
        );
    }
}
