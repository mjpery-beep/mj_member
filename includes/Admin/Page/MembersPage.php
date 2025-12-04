<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class MembersPage
{
    public static function slug(): string
    {
        return 'mj_members';
    }

    public static function registerHooks(?string $hookSuffix): void
    {
        if ($hookSuffix === null || $hookSuffix === '') {
            return;
        }

        add_action('load-' . $hookSuffix, array(__CLASS__, 'handleLoad'));
    }

    public static function render(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gestion des Membres', 'mj-member'); ?></h1>
            <?php
            $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

            if ($action === 'add' || $action === 'edit') {
                require Config::path() . 'includes/forms/form_member.php';
            } else {
                require Config::path() . 'includes/table_members.php';
            }
            ?>
        </div>
        <?php
    }

    public static function handleLoad(): void
    {
        if (!current_user_can(Config::capability())) {
            return;
        }

        $primaryAction = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        $secondaryAction = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash($_REQUEST['action2'])) : '';
        $action = $primaryAction ?: $secondaryAction;

        if ($action !== 'delete') {
            return;
        }

        $memberId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $nonceValue = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

        if ($memberId <= 0 || $nonceValue === '' || !wp_verify_nonce($nonceValue, 'mj_delete_nonce')) {
            return;
        }

        \MjMembers_CRUD::delete($memberId);

        $redirect = add_query_arg(
            array(
                'page' => self::slug(),
                'mj_member_notice' => 'deleted',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
