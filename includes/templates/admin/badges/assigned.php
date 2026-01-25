<?php
if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Admin\Page\BadgesPage;
use Mj\Member\Classes\Crud\MjMemberBadgeCriteria;
use Mj\Member\Classes\Crud\MjMemberBadges;

$backUrl = isset($backUrl) ? $backUrl : add_query_arg('page', BadgesPage::slug(), admin_url('admin.php'));
$assignUrl = isset($assignUrl) ? $assignUrl : add_query_arg([
    'page' => BadgesPage::slug(),
    'action' => 'assign',
    'badge_id' => isset($badge['id']) ? (int) $badge['id'] : 0,
], admin_url('admin.php'));
$badgeLabel = isset($badge['label']) ? $badge['label'] : __('Badge', 'mj-member');
$assignmentCount = isset($assignments) && is_array($assignments) ? count($assignments) : 0;
$assignments = isset($assignments) && is_array($assignments) ? $assignments : array();
$assignmentCriteria = isset($assignmentCriteria) && is_array($assignmentCriteria) ? $assignmentCriteria : array();
$criterionIndex = isset($criterionIndex) && is_array($criterionIndex) ? $criterionIndex : array();
$statusLabels = MjMemberBadges::get_status_labels();
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html(sprintf(__('Membres ayant le badge “%s”', 'mj-member'), $badgeLabel)); ?>
    </h1>
    <a href="<?php echo esc_url($assignUrl); ?>" class="button" style="margin-left:12px;">
        <?php esc_html_e('Attribuer à un membre', 'mj-member'); ?>
    </a>
    <a href="<?php echo esc_url($backUrl); ?>" class="page-title-action">
        <?php esc_html_e('Retour aux badges', 'mj-member'); ?>
    </a>
    <hr class="wp-header-end" />

    <?php if (!empty($badge['description'])) : ?>
        <p><?php echo esc_html($badge['description']); ?></p>
    <?php endif; ?>

    <?php if (empty($assignmentCount)) : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('Aucun membre ne possède encore ce badge.', 'mj-member'); ?></p>
        </div>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Membre', 'mj-member'); ?></th>
                    <th><?php esc_html_e('Statut', 'mj-member'); ?></th>
                    <th><?php esc_html_e('Critères validés', 'mj-member'); ?></th>
                    <th><?php esc_html_e('Attribution', 'mj-member'); ?></th>
                    <th><?php esc_html_e('Dernière mise à jour', 'mj-member'); ?></th>
                    <th><?php esc_html_e('Actions', 'mj-member'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assignment) :
                    $memberName = isset($assignment['member_name']) && $assignment['member_name'] !== ''
                        ? $assignment['member_name']
                        : sprintf(__('Membre #%d', 'mj-member'), isset($assignment['member_id']) ? (int) $assignment['member_id'] : 0);
                    $statusKey = isset($assignment['status']) ? (string) $assignment['status'] : MjMemberBadges::STATUS_AWARDED;
                    $statusLabel = isset($statusLabels[$statusKey]) ? $statusLabels[$statusKey] : $statusKey;
                    $assignedAt = !empty($assignment['awarded_at']) ? mysql2date(get_option('date_format'), $assignment['awarded_at']) : '—';
                    $updatedAt = !empty($assignment['updated_at']) ? mysql2date(get_option('date_format'), $assignment['updated_at']) : '—';

                    $criteriaList = array();
                    $memberId = isset($assignment['member_id']) ? (int) $assignment['member_id'] : 0;
                    $memberCriteria = $memberId > 0 && isset($assignmentCriteria[$memberId]) ? $assignmentCriteria[$memberId] : array();
                    if (!empty($memberCriteria)) {
                        foreach ($memberCriteria as $criterionAward) {
                            if (!is_array($criterionAward)) {
                                continue;
                            }
                            $criterionId = isset($criterionAward['criterion_id']) ? (int) $criterionAward['criterion_id'] : 0;
                            if ($criterionId <= 0) {
                                continue;
                            }
                            if (!isset($criterionIndex[$criterionId])) {
                                continue;
                            }
                            if (!empty($criterionAward['status']) && $criterionAward['status'] === MjMemberBadgeCriteria::STATUS_REVOKED) {
                                continue;
                            }
                            $label = isset($criterionIndex[$criterionId]['label']) ? $criterionIndex[$criterionId]['label'] : sprintf(__('Critère #%d', 'mj-member'), $criterionId);
                            $criteriaList[] = $label;
                        }
                    }
                    $editUrl = add_query_arg('member_id', $memberId, $assignUrl);
                    ?>
                    <tr>
                        <td><?php echo esc_html($memberName); ?></td>
                        <td><?php echo esc_html($statusLabel); ?></td>
                        <td>
                            <?php if ($criteriaList) : ?>
                                <ul>
                                    <?php foreach ($criteriaList as $label) : ?>
                                        <li><?php echo esc_html($label); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <em><?php esc_html_e('Non précisé', 'mj-member'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($assignedAt); ?></td>
                        <td><?php echo esc_html($updatedAt); ?></td>
                        <td>
                            <a href="<?php echo esc_url($editUrl); ?>" class="button button-small">
                                <?php esc_html_e('Modifier les critères', 'mj-member'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
