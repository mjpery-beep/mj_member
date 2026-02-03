<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\Locations\LocationsActionState;
use Mj\Member\Admin\Locations\LocationsPageContext;
use Mj\Member\Admin\Locations\LocationsPageRenderer;
use Mj\Member\Admin\RequestGuard;
use Mj\Member\Admin\Service\LocationsActionHandler;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class LocationsPage
{
    /** @var LocationsActionState|null */
    private static $actionState;

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
        $state = self::$actionState instanceof LocationsActionState ? self::$actionState : LocationsActionState::create();
        $context = LocationsPageContext::build($state, $_GET);

        if ($context->isFormAction()) {
            wp_enqueue_media();
            $scriptPath = Config::path() . 'includes/js/admin-locations.js';
            $scriptVersion = file_exists($scriptPath) ? (string) filemtime($scriptPath) : Config::version();
            wp_enqueue_script(
                'mj-admin-locations',
                Config::url() . 'includes/js/admin-locations.js',
                array('jquery'),
                $scriptVersion,
                true
            );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gestion des lieux', 'mj-member'); ?></h1>
            <?php (new LocationsPageRenderer())->render($context); ?>
        </div>
        <?php
    }

    public static function handleLoad(): void
    {
        self::$actionState = (new LocationsActionHandler())->handle();
    }
}
