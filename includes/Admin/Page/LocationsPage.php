<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Admin\Service\LocationsActionHandler;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class LocationsPage
{
    public static function slug(): string
    {
        return 'mj_locations';
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
            <h1><?php esc_html_e('Gestion des lieux', 'mj-member'); ?></h1>
            <?php require Config::path() . 'includes/locations_page.php'; ?>
        </div>
        <?php
    }

    public static function handleLoad(): void
    {
        (new LocationsActionHandler())->handle();
    }
}
