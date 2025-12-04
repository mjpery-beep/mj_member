<?php

namespace Mj\Member\Admin\Page;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportMembersPage
{
    public static function slug(): string
    {
        return 'mj_member_import';
    }

    public static function render(): void
    {
        if (function_exists('mj_member_import_members_page')) {
            mj_member_import_members_page();
        }
    }
}
