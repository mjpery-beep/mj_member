<?php
namespace Mj\Member\Module\Admin;

use Mj\Member\Core\Contracts\ModuleInterface;
use Mj\Member\Admin\Page\BadgesPage;

if (!defined('ABSPATH')) { exit; }

final class BadgesAdminModule implements ModuleInterface {
    public function register(): void {
        add_action('init', [BadgesPage::class, 'register_actions']);
    }
}
