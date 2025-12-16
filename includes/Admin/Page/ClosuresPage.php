<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Core\Config;

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
        RequestGuard::ensureCapabilityOrDie(Config::capability());

        if (function_exists('mj_event_closures_page')) {
            mj_event_closures_page();
        }
    }
}
