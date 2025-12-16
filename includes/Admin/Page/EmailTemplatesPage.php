<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class EmailTemplatesPage
{
    public static function slug(): string
    {
        return 'mj_email_templates';
    }

    public static function render(): void
    {
        RequestGuard::ensureCapabilityOrDie(Config::capability());

        if (function_exists('mj_email_templates_page')) {
            mj_email_templates_page();
        }
    }
}
