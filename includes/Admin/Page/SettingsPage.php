<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsPage
{
    public static function slug(): string
    {
        return 'mj_settings';
    }

    public static function render(): void
    {
        RequestGuard::ensureCapabilityOrDie('manage_options');

        if (function_exists('mj_settings_page')) {
            mj_settings_page();
        }
    }
}
