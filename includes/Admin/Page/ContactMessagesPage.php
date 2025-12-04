<?php

namespace Mj\Member\Admin\Page;

if (!defined('ABSPATH')) {
    exit;
}

final class ContactMessagesPage
{
    public static function slug(): string
    {
        return 'mj_contact_messages';
    }

    public static function render(): void
    {
        if (function_exists('mj_member_contact_messages_page')) {
            mj_member_contact_messages_page();
        }
    }
}
