<?php

if (!defined('ABSPATH')) {
    exit;
}

$badge = isset($badge) && is_array($badge) ? $badge : null;
$members = isset($members) && is_array($members) ? $members : array();
$criteriaRecords = isset($criteriaRecords) && is_array($criteriaRecords) ? $criteriaRecords : array();
$selectedCriteriaIds = isset($selectedCriteriaIds) && is_array($selectedCriteriaIds) ? $selectedCriteriaIds : array();
$selectedCriteriaIds = array_map('intval', $selectedCriteriaIds);
$selectedCriteriaIds = array_values(array_unique($selectedCriteriaIds));
$backUrl = isset($backUrl) ? $backUrl : add_query_arg('page', \Mj\Member\Admin\Page\BadgesPage::slug(), admin_url('admin.php'));
$formAction = add_query_arg('action', 'assign_badge', admin_url('admin-post.php'));
$assignedListUrl = isset($assignedListUrl) ? $assignedListUrl : add_query_arg(array(
    'page' => \Mj\Member\Admin\Page\BadgesPage::slug(),
    'action' => 'assigned',
    'badge_id' => isset($badge['id']) ? (int) $badge['id'] : 0,
), admin_url('admin.php'));
$selectedMemberId = isset($selectedMemberId) ? (int) $selectedMemberId : 0;
$selectedStatus = isset($selectedStatus) ? (string) $selectedStatus : \Mj\Member\Classes\Crud\MjMemberBadges::STATUS_AWARDED;
$selectedAwardedAt = isset($selectedAwardedAt) ? (string) $selectedAwardedAt : '';
$selectedEvidence = isset($selectedEvidence) ? (string) $selectedEvidence : '';
$isEditing = !empty($isEditing);

if (!$badge) {
    wp_safe_redirect($backUrl);
    exit;
}

$statusOptions = \Mj\Member\Classes\Crud\MjMemberBadges::get_status_labels();
?>
<div class="wrap mj-member-admin mj-member-admin-badges">
    <h1 class="wp-heading-inline"><?php echo esc_html(sprintf(__('Attribuer le badge “%s”', 'mj-member'), $badge['label'] ?? __('Badge', 'mj-member'))); ?></h1>
    <a href="<?php echo esc_url($assignedListUrl); ?>" class="button" style="margin-left:12px;">
        <?php esc_html_e('Voir les membres attribués', 'mj-member'); ?>
    </a>
    <hr class="wp-header-end" />

    <?php if (isset($_GET['error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html(rawurldecode((string) $_GET['error'])); ?></p></div>
    <?php endif; ?>

    <?php if ($isEditing) : ?>
        <div class="notice notice-info"><p><?php esc_html_e('Vous mettez à jour les critères validés pour ce membre.', 'mj-member'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($formAction); ?>">
        <?php wp_nonce_field('mj_member_assign_badge'); ?>
        <input type="hidden" name="badge_id" value="<?php echo esc_attr((string) ($badge['id'] ?? 0)); ?>" />

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="member_id"><?php esc_html_e('Membre', 'mj-member'); ?></label></th>
                    <td>
                        <select id="member_id" name="member_id" class="regular-text">
                            <option value=""><?php esc_html_e('Sélectionner un membre…', 'mj-member'); ?></option>
                            <?php foreach ($members as $member) :
                                $memberId = isset($member['id']) ? (int) $member['id'] : 0;
                                if ($memberId <= 0) {
                                    continue;
                                }
                                $name = trim(sprintf('%s %s', $member['first_name'] ?? '', $member['last_name'] ?? ''));
                                if ($name === '') {
                                    $name = sprintf(__('Membre #%d', 'mj-member'), $memberId);
                                }
                            ?>
                                <option value="<?php echo esc_attr((string) $memberId); ?>" <?php selected($memberId, $selectedMemberId); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="status"><?php esc_html_e('Statut', 'mj-member'); ?></label></th>
                    <td>
                        <select id="status" name="status">
                            <?php foreach ($statusOptions as $value => $labelOption) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $selectedStatus); ?>><?php echo esc_html($labelOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="awarded_at"><?php esc_html_e('Date d’attribution', 'mj-member'); ?></label></th>
                    <td>
                        <input type="datetime-local" id="awarded_at" name="awarded_at" value="<?php echo esc_attr($selectedAwardedAt); ?>" />
                        <p class="description"><?php esc_html_e('Laisser vide pour utiliser la date actuelle.', 'mj-member'); ?></p>
                    </td>
                </tr>
                <?php if (!empty($criteriaRecords)) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Critères remplis', 'mj-member'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($criteriaRecords as $criterion) :
                                    $criterionId = isset($criterion['id']) ? (int) $criterion['id'] : 0;
                                    if ($criterionId <= 0) {
                                        continue;
                                    }
                                    $criterionLabel = isset($criterion['label']) ? (string) $criterion['label'] : '';
                                ?>
                                    <label style="display:block;">
                                        <input type="checkbox" name="criteria_ids[]" value="<?php echo esc_attr((string) $criterionId); ?>" <?php checked(in_array($criterionId, $selectedCriteriaIds, true)); ?> />
                                        <?php echo esc_html($criterionLabel); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Sélectionnez les critères validés pour ce membre.', 'mj-member'); ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row"><label for="evidence"><?php esc_html_e('Commentaire / preuve', 'mj-member'); ?></label></th>
                    <td>
                        <textarea id="evidence" name="evidence" rows="4" class="large-text"><?php echo esc_textarea($selectedEvidence); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo esc_html($isEditing ? __('Mettre à jour l’attribution', 'mj-member') : __('Attribuer le badge', 'mj-member')); ?></button>
            <a class="button" href="<?php echo esc_url($backUrl); ?>"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
        </p>
    </form>

    <?php if (empty($members)) : ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e('Aucun membre disponible. Ajoutez d’abord des membres.', 'mj-member'); ?></p>
        </div>
    <?php endif; ?>
</div>
