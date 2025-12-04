<?php

namespace Mj\Member\Admin\Page;

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
        if (function_exists('mj_settings_page')) {
            mj_settings_page();
        }
    }
}
