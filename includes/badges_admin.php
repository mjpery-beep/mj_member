<?php

use Mj\Member\Admin\Page\BadgesPage;

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', array(BadgesPage::class, 'register_actions'));
