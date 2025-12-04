<?php

namespace Mj\Member\Admin\Page;

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
        if (function_exists('mj_send_emails_page')) {
            mj_send_emails_page();
        }
    }
}
