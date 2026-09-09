<?php

if (!defined('ABSPATH')) {
	exit;
}

use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\MjRoles;

if (!function_exists('mj_member_contact_format_recipient_option')) {
	/**
	 * @param array<string,mixed> $recipient
	 * @return array<string,mixed>
	 */
	function mj_member_contact_format_recipient_option(array $recipient) {
		$type = isset($recipient['type']) ? sanitize_key($recipient['type']) : '';
		$reference = isset($recipient['reference']) ? (int) $recipient['reference'] : 0;
		$label = isset($recipient['label']) ? sanitize_text_field($recipient['label']) : '';
		$value = isset($recipient['value']) ? sanitize_key($recipient['value']) : '';

		if ($value === '' && $type !== '') {
			$value = $type . ($reference > 0 ? ':' . $reference : '');
		}

		return array_merge(
			array(
				'value' => $value,
				'label' => $label,
				'type' => $type,
				'reference' => $reference,
				'role' => isset($recipient['role']) ? sanitize_key($recipient['role']) : $type,
				'role_label' => isset($recipient['role_label']) ? sanitize_text_field($recipient['role_label']) : '',
				'description' => isset($recipient['description']) ? sanitize_text_field($recipient['description']) : '',
				'is_cover' => !empty($recipient['is_cover']),
				'cover_theme' => isset($recipient['cover_theme']) ? sanitize_key($recipient['cover_theme']) : '',
			),
			$recipient
		);
	}
}

if (!function_exists('mj_member_get_contact_recipient_options')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function mj_member_get_contact_recipient_options() {
		$recipients = array(
			mj_member_contact_format_recipient_option(array(
				'value' => MjContactMessages::TARGET_ALL,
				'label' => __('Toute l’équipe MJ', 'mj-member'),
				'type' => MjContactMessages::TARGET_ALL,
				'reference' => 0,
				'role' => 'group',
				'role_label' => __('Maison de Jeune', 'mj-member'),
				'description' => __('Votre message sera transmis à l’ensemble de l’équipe.', 'mj-member'),
				'is_cover' => true,
				'cover_theme' => 'indigo',
			)),
		);

		if (!class_exists('MjMembers')) {
			return $recipients;
		}

		$members = MjMembers::get_all(array(
			'limit' => 0,
			'orderby' => 'last_name',
			'order' => 'ASC',
			'filters' => array(
				'roles' => MjRoles::getStaffRoles(),
				'status' => MjMembers::STATUS_ACTIVE,
			),
		));

		foreach ($members as $member) {
			$member_id = isset($member->id) ? (int) $member->id : 0;
			$role = isset($member->role) ? sanitize_key((string) $member->role) : '';
			if ($member_id <= 0 || !in_array($role, MjRoles::getStaffRoles(), true)) {
				continue;
			}

			$name = trim(
				(isset($member->first_name) ? (string) $member->first_name : '') . ' ' .
				(isset($member->last_name) ? (string) $member->last_name : '')
			);
			if ($name === '') {
				$name = isset($member->nickname) ? trim((string) $member->nickname) : '';
			}
			if ($name === '') {
				continue;
			}

			$target_type = $role === MjRoles::COORDINATEUR
				? MjContactMessages::TARGET_COORDINATEUR
				: MjContactMessages::TARGET_ANIMATEUR;

			$recipients[] = mj_member_contact_format_recipient_option(array(
				'value' => $target_type . ':' . $member_id,
				'label' => $name,
				'type' => $target_type,
				'reference' => $member_id,
				'role' => $role,
				'role_label' => MjRoles::getRoleLabel($role),
			));
		}

		return $recipients;
	}
}
