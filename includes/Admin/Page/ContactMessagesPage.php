<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Core\Config;

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
        $capability = Config::contactCapability();
        if ($capability !== '') {
            RequestGuard::ensureCapabilityOrDie($capability);
        }

        if (function_exists('mj_member_contact_messages_page')) {
            mj_member_contact_messages_page();
        }
    }
}
