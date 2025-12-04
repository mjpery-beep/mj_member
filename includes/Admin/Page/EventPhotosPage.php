<?php

namespace Mj\Member\Admin\Page;

if (!defined('ABSPATH')) {
    exit;
}

final class EventPhotosPage
{
    public static function slug(): string
    {
        return 'mj_event_photos';
    }

    public static function render(): void
    {
        if (function_exists('mj_member_event_photos_page')) {
            mj_member_event_photos_page();
        }
    }
}
