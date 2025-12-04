<?php

namespace Mj\Member\Admin\Service;

use Mj\Member\Admin\Page\MembersPage;
use Mj\Member\Admin\RequestGuard;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class MembersActionHandler
{
    public function handle(): void
    {
        if (!RequestGuard::ensureCapability(Config::capability())) {
            return;
        }

        $primaryAction = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        $secondaryAction = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash($_REQUEST['action2'])) : '';
        $resolvedAction = $primaryAction ?: $secondaryAction;

        if ($resolvedAction !== 'delete') {
            return;
        }

        $memberId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $nonceValue = RequestGuard::readNonce($_GET, 'nonce');

        if ($memberId <= 0 || !RequestGuard::verifyNonce($nonceValue, 'mj_delete_nonce')) {
            return;
        }

        \MjMembers_CRUD::delete($memberId);

        $redirect = add_query_arg(
            array(
                'page' => MembersPage::slug(),
                'mj_member_notice' => 'deleted',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
