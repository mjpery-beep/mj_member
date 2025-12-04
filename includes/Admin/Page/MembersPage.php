<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Admin\Service\MembersActionHandler;
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
        RequestGuard::ensureCapabilityOrDie(Config::capability());
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
        (new MembersActionHandler())->handle();
    }
}
