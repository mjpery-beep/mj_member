<?php

namespace Mj\Member\Admin\Page;

if (!defined('ABSPATH')) {
    exit;
}

final class DashboardPage
{
    public static function slug(): string
    {
        return 'mj_member_dashboard';
    }

    public static function render(): void
    {
        if (function_exists('mj_member_dashboard_page')) {
            mj_member_dashboard_page();
        }
    }
}
