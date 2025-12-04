<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class EventsPage
{
    public static function slug(): string
    {
        return 'mj_events';
    }

    public static function render(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gestion des événements & stages', 'mj-member'); ?></h1>
            <?php
            $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

            if ($action === 'add' || $action === 'edit') {
                require Config::path() . 'includes/forms/form_event.php';
            } else {
                require Config::path() . 'includes/table_events.php';
            }
            ?>
        </div>
        <?php
    }
}
