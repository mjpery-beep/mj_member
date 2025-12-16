<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class SendEmailsPage
{
    public static function slug(): string
    {
        return 'mj_send_emails';
    }

    public static function render(): void
    {
        RequestGuard::ensureCapabilityOrDie(Config::capability());

        if (function_exists('mj_send_emails_page')) {
            mj_send_emails_page();
        }
    }
}
