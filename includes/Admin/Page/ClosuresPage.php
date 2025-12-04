<?php

namespace Mj\Member\Admin\Page;

if (!defined('ABSPATH')) {
    exit;
}

final class ClosuresPage
{
    public static function slug(): string
    {
        return 'mj_closures';
    }

    public static function render(): void
    {
        if (function_exists('mj_event_closures_page')) {
            mj_event_closures_page();
        }
    }
}
