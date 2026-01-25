<?php

if (!defined('ABSPATH')) {
    exit;
}

$noticeKey = isset($noticeKey) ? $noticeKey : '';
$errorMessage = isset($errorMessage) ? $errorMessage : '';
$listTable = isset($listTable) ? $listTable : null;
$createUrl = isset($createUrl) ? $createUrl : add_query_arg('page', \Mj\Member\Admin\Page\BadgesPage::slug(), admin_url('admin.php'));

?>
<div class="wrap mj-member-admin mj-member-admin-badges">
    <h1 class="wp-heading-inline"><?php esc_html_e('Badges', 'mj-member'); ?></h1>
    <a href="<?php echo esc_url($createUrl); ?>" class="page-title-action"><?php esc_html_e('Ajouter un badge', 'mj-member'); ?></a>
    <hr class="wp-header-end" />

    <?php if ($noticeKey === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Badge enregistré.', 'mj-member'); ?></p></div>
    <?php elseif ($noticeKey === 'deleted') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Badge supprimé.', 'mj-member'); ?></p></div>
    <?php elseif ($noticeKey === 'updated') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Badges mis à jour.', 'mj-member'); ?></p></div>
    <?php elseif ($noticeKey === 'assigned') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Badge attribué au membre.', 'mj-member'); ?></p></div>
    <?php elseif ($noticeKey === 'error' && $errorMessage !== '') : ?>
        <div class="notice notice-error"><p><?php echo esc_html($errorMessage); ?></p></div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(\Mj\Member\Admin\Page\BadgesPage::slug()); ?>" />
        <?php $listTable->search_box(__('Rechercher', 'mj-member'), 'mj-member-badges'); ?>
        <?php $listTable->display(); ?>
    </form>
</div>
