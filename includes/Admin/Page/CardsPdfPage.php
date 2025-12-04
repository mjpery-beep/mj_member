<?php

namespace Mj\Member\Admin\Page;

if (!defined('ABSPATH')) {
    exit;
}

final class CardsPdfPage
{
    public static function slug(): string
    {
        return 'mj_member_cards_pdf';
    }

    public static function render(): void
    {
        if (function_exists('mj_member_cards_pdf_page')) {
            mj_member_cards_pdf_page();
        }
    }
}
