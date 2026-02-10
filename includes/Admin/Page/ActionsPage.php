<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Classes\Crud\MjActionTypes;
use Mj\Member\Classes\Crud\MjMemberActions;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Page d'administration pour gérer les types d'actions de gamification.
 */
final class ActionsPage
{
    private static $actionsRegistered = false;

    public static function slug(): string
    {
        return 'mj-member-actions';
    }

    public static function registerHooks(string $hookSuffix): void
    {
        // Les actions admin_post sont enregistrées via boot()
    }

    public static function boot(): void
    {
        add_action('admin_init', array(static::class, 'register_actions'));
    }

    public static function render(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'mj-member'));
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
        if ($action === 'edit' || $action === 'new') {
            static::render_action_form();
            return;
        }

        if ($action === 'awarded') {
            static::render_awarded_list();
            return;
        }

        static::render_list();
    }

    public static function register_actions(): void
    {
        if (self::$actionsRegistered) {
            return;
        }

        self::$actionsRegistered = true;

        $forms = array(
            'save_action_type'   => array(static::class, 'handle_save_action_type'),
            'delete_action_type' => array(static::class, 'handle_delete_action_type'),
        );

        foreach ($forms as $name => $handler) {
            $hook = 'admin_post_' . $name;
            add_action($hook, $handler);
        }
    }

    public static function deleteNonceAction(int $actionTypeId): string
    {
        return 'mj_member_delete_action_type_' . $actionTypeId;
    }

    private static function render_list(): void
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $statusFilter = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';
        $categoryFilter = isset($_GET['category']) ? sanitize_key(wp_unslash((string) $_GET['category'])) : '';
        $attributionFilter = isset($_GET['attribution']) ? sanitize_key(wp_unslash((string) $_GET['attribution'])) : '';

        $args = array(
            'orderby' => 'display_order',
            'order' => 'ASC',
        );

        if ($search !== '') {
            $args['search'] = $search;
        }

        if ($statusFilter !== '') {
            $args['status'] = $statusFilter;
        }

        if ($categoryFilter !== '') {
            $args['category'] = $categoryFilter;
        }

        if ($attributionFilter !== '') {
            $args['attribution'] = $attributionFilter;
        }

        $actionTypes = MjActionTypes::get_all($args);
        $categoryLabels = MjActionTypes::get_category_labels();

        $createUrl = add_query_arg(
            array(
                'page'   => static::slug(),
                'action' => 'new',
            ),
            admin_url('admin.php')
        );

        $noticeKey = isset($_GET['mj_actions_notice']) ? sanitize_key(wp_unslash((string) $_GET['mj_actions_notice'])) : '';

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Actions de gamification', 'mj-member'); ?></h1>
            <a href="<?php echo esc_url($createUrl); ?>" class="page-title-action"><?php esc_html_e('Ajouter', 'mj-member'); ?></a>
            <hr class="wp-header-end">

            <?php if ($noticeKey === 'created'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Action créée avec succès.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'updated'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Action mise à jour.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'deleted'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Action supprimée.', 'mj-member'); ?></p></div>
            <?php endif; ?>

            <form method="get" style="margin-bottom: 15px;">
                <input type="hidden" name="page" value="<?php echo esc_attr(static::slug()); ?>">
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Rechercher...', 'mj-member'); ?>">
                    <select name="status">
                        <option value=""><?php esc_html_e('Tous les statuts', 'mj-member'); ?></option>
                        <option value="active" <?php selected($statusFilter, 'active'); ?>><?php esc_html_e('Actif', 'mj-member'); ?></option>
                        <option value="archived" <?php selected($statusFilter, 'archived'); ?>><?php esc_html_e('Archivé', 'mj-member'); ?></option>
                    </select>
                    <select name="category">
                        <option value=""><?php esc_html_e('Toutes les catégories', 'mj-member'); ?></option>
                        <?php foreach ($categoryLabels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($categoryFilter, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="attribution">
                        <option value=""><?php esc_html_e('Tous les modes', 'mj-member'); ?></option>
                        <option value="auto" <?php selected($attributionFilter, 'auto'); ?>><?php esc_html_e('Automatique', 'mj-member'); ?></option>
                        <option value="manual" <?php selected($attributionFilter, 'manual'); ?>><?php esc_html_e('Manuelle', 'mj-member'); ?></option>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e('Filtrer', 'mj-member'); ?></button>
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php esc_html_e('Emoji', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Action', 'mj-member'); ?></th>
                        <th style="width: 180px;"><?php esc_html_e('Catégorie', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('XP', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Coins', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Mode', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Attrib.', 'mj-member'); ?></th>
                        <th style="width: 140px;"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($actionTypes)): ?>
                        <tr>
                            <td colspan="9"><?php esc_html_e('Aucune action trouvée.', 'mj-member'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($actionTypes as $actionType): ?>
                            <?php
                            $editUrl = add_query_arg(array(
                                'page' => static::slug(),
                                'action' => 'edit',
                                'id' => $actionType['id'],
                            ), admin_url('admin.php'));

                            $awardedUrl = add_query_arg(array(
                                'page' => static::slug(),
                                'action' => 'awarded',
                                'id' => $actionType['id'],
                            ), admin_url('admin.php'));

                            $deleteUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=delete_action_type&id=' . $actionType['id']),
                                static::deleteNonceAction($actionType['id'])
                            );

                            $awardedCount = MjMemberActions::count(array(
                                'action_type_id' => $actionType['id'],
                            ));

                            $categoryLabel = $categoryLabels[$actionType['category']] ?? $actionType['category'];
                            ?>
                            <tr>
                                <td style="font-size: 24px; text-align: center;">
                                    <?php echo esc_html(($actionType['emoji'] ?? '') ?: '🎯'); ?>
                                </td>
                                <td>
                                    <strong><a href="<?php echo esc_url($editUrl); ?>"><?php echo esc_html($actionType['title']); ?></a></strong>
                                    <?php if ($actionType['description']): ?>
                                        <br><small style="color: #666;"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($actionType['description']), 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo esc_html($categoryLabel); ?></small></td>
                                <td><strong><?php echo esc_html($actionType['xp']); ?></strong> XP</td>
                                <td><span style="background: #fff3cd; padding: 2px 8px; border-radius: 3px; font-size: 12px;">🪙 <?php echo esc_html($actionType['coins']); ?></span></td>
                                <td>
                                    <?php if ($actionType['attribution'] === 'auto'): ?>
                                        <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 12px;">⚙️ Auto</span>
                                    <?php else: ?>
                                        <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px; font-size: 12px;">✋ Manuel</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($actionType['status'] === 'active'): ?>
                                        <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Actif</span>
                                    <?php else: ?>
                                        <span style="background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Archivé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($awardedCount > 0): ?>
                                        <a href="<?php echo esc_url($awardedUrl); ?>" class="button button-small" title="<?php echo esc_attr(sprintf(__('%d attribution(s)', 'mj-member'), $awardedCount)); ?>">
                                            👥 <?php echo esc_html($awardedCount); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($editUrl); ?>" class="button button-small"><?php esc_html_e('Modifier', 'mj-member'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_action_form(): void
    {
        $actionTypeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $isEdit = $actionTypeId > 0;

        $defaults = array(
            'title' => '',
            'slug' => '',
            'description' => '',
            'emoji' => '🎯',
            'xp' => 5,
            'coins' => 0,
            'category' => MjActionTypes::CATEGORY_MJ_ATTITUDE,
            'attribution' => MjActionTypes::ATTRIBUTION_MANUAL,
            'auto_hook' => '',
            'repeatable' => true,
            'max_per_day' => 0,
            'display_order' => 0,
            'status' => MjActionTypes::STATUS_ACTIVE,
        );

        $actionType = $defaults;
        if ($isEdit) {
            $existing = MjActionTypes::get($actionTypeId);
            if ($existing) {
                $actionType = array_merge($defaults, $existing);
            }
        }

        $formAction = admin_url('admin-post.php');
        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));

        $categoryLabels = MjActionTypes::get_category_labels();
        ?>
        <div class="wrap">
            <h1><?php echo $isEdit ? esc_html__('Modifier l\'action', 'mj-member') : esc_html__('Nouvelle action', 'mj-member'); ?></h1>

            <form method="post" action="<?php echo esc_url($formAction); ?>">
                <input type="hidden" name="action" value="save_action_type">
                <?php wp_nonce_field('save_action_type', 'mj_action_type_nonce'); ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="action_type_id" value="<?php echo esc_attr($actionTypeId); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="title"><?php esc_html_e('Titre', 'mj-member'); ?> <span class="required">*</span></label></th>
                        <td><input type="text" name="title" id="title" value="<?php echo esc_attr($actionType['title']); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="slug"><?php esc_html_e('Slug', 'mj-member'); ?></label></th>
                        <td>
                            <input type="text" name="slug" id="slug" value="<?php echo esc_attr($actionType['slug']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Identifiant unique (généré automatiquement si vide)', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e('Description', 'mj-member'); ?></label></th>
                        <td><textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea($actionType['description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="emoji"><?php esc_html_e('Emoji', 'mj-member'); ?></label></th>
                        <td>
                            <input type="text" name="emoji" id="emoji" value="<?php echo esc_attr($actionType['emoji']); ?>" class="small-text" style="font-size: 24px; width: 60px; text-align: center;">
                            <p class="description"><?php esc_html_e('Un emoji représentant cette action', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category"><?php esc_html_e('Catégorie', 'mj-member'); ?></label></th>
                        <td>
                            <select name="category" id="category">
                                <?php foreach ($categoryLabels as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($actionType['category'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="xp"><?php esc_html_e('Points XP', 'mj-member'); ?></label></th>
                        <td><input type="number" name="xp" id="xp" value="<?php echo esc_attr($actionType['xp']); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coins"><?php esc_html_e('Coins', 'mj-member'); ?> 🪙</label></th>
                        <td><input type="number" name="coins" id="coins" value="<?php echo esc_attr($actionType['coins']); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="attribution"><?php esc_html_e('Mode d\'attribution', 'mj-member'); ?></label></th>
                        <td>
                            <select name="attribution" id="attribution">
                                <option value="manual" <?php selected($actionType['attribution'], 'manual'); ?>><?php esc_html_e('Manuelle (par un animateur)', 'mj-member'); ?></option>
                                <option value="auto" <?php selected($actionType['attribution'], 'auto'); ?>><?php esc_html_e('Automatique (par le système)', 'mj-member'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="auto-mode-fields" <?php echo $actionType['attribution'] === 'auto' ? '' : 'style="display:none;"'; ?>>
                        <th scope="row"><label for="auto_hook"><?php esc_html_e('Déclencheur', 'mj-member'); ?></label></th>
                        <td>
                            <input type="text" name="auto_hook" id="auto_hook" value="<?php echo esc_attr($actionType['auto_hook']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Nom du hook WordPress qui déclenche cette action', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="repeatable"><?php esc_html_e('Répétable', 'mj-member'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="repeatable" id="repeatable" value="1" <?php checked($actionType['repeatable']); ?>>
                                <?php esc_html_e('Cette action peut être attribuée plusieurs fois au même membre', 'mj-member'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="repeatable-fields" <?php echo $actionType['repeatable'] ? '' : 'style="display:none;"'; ?>>
                        <th scope="row"><label for="max_per_day"><?php esc_html_e('Limite par jour', 'mj-member'); ?></label></th>
                        <td>
                            <input type="number" name="max_per_day" id="max_per_day" value="<?php echo esc_attr($actionType['max_per_day']); ?>" min="0" class="small-text">
                            <p class="description"><?php esc_html_e('0 = illimité', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="display_order"><?php esc_html_e('Ordre d\'affichage', 'mj-member'); ?></label></th>
                        <td><input type="number" name="display_order" id="display_order" value="<?php echo esc_attr($actionType['display_order']); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status"><?php esc_html_e('Statut', 'mj-member'); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected($actionType['status'], 'active'); ?>><?php esc_html_e('Actif', 'mj-member'); ?></option>
                                <option value="archived" <?php selected($actionType['status'], 'archived'); ?>><?php esc_html_e('Archivé', 'mj-member'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $isEdit ? esc_html__('Mettre à jour', 'mj-member') : esc_html__('Créer', 'mj-member'); ?></button>
                    <a href="<?php echo esc_url($backUrl); ?>" class="button"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
                    <?php if ($isEdit): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=delete_action_type&id=' . $actionTypeId), static::deleteNonceAction($actionTypeId))); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('Supprimer cette action ?', 'mj-member'); ?>');"><?php esc_html_e('Supprimer', 'mj-member'); ?></a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#attribution').on('change', function() {
                if ($(this).val() === 'auto') {
                    $('.auto-mode-fields').show();
                } else {
                    $('.auto-mode-fields').hide();
                }
            });

            $('#repeatable').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.repeatable-fields').show();
                } else {
                    $('.repeatable-fields').hide();
                }
            });
        });
        </script>
        <?php
    }

    private static function render_awarded_list(): void
    {
        $actionTypeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($actionTypeId <= 0) {
            wp_redirect(add_query_arg('page', static::slug(), admin_url('admin.php')));
            exit;
        }

        $actionType = MjActionTypes::get($actionTypeId);
        if (!$actionType) {
            wp_redirect(add_query_arg('page', static::slug(), admin_url('admin.php')));
            exit;
        }

        $awards = MjMemberActions::get_all(array(
            'action_type_id' => $actionTypeId,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html($actionType['emoji']); ?>
                <?php echo esc_html(sprintf(__('Attributions de « %s »', 'mj-member'), $actionType['title'])); ?>
            </h1>
            <p><a href="<?php echo esc_url($backUrl); ?>">&larr; <?php esc_html_e('Retour à la liste', 'mj-member'); ?></a></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Membre', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Notes', 'mj-member'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Attribué par', 'mj-member'); ?></th>
                        <th style="width: 180px;"><?php esc_html_e('Date', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($awards)): ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('Aucune attribution.', 'mj-member'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($awards as $award): ?>
                            <?php
                            $member = MjMembers::get((int) $award['memberId']);
                            $memberName = $member ? trim(($member['firstname'] ?? '') . ' ' . ($member['lastname'] ?? '')) : __('Membre inconnu', 'mj-member');
                            if ($memberName === '') {
                                $memberName = $member['nickname'] ?? __('Membre', 'mj-member');
                            }

                            $awardedByName = '—';
                            if ((int) $award['awardedBy'] > 0) {
                                $awardedByUser = get_userdata((int) $award['awardedBy']);
                                if ($awardedByUser) {
                                    $awardedByName = $awardedByUser->display_name;
                                }
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($memberName); ?></strong></td>
                                <td><?php echo esc_html($award['notes'] ?: '—'); ?></td>
                                <td><?php echo esc_html($awardedByName); ?></td>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($award['createdAt']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_save_action_type(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        if (!isset($_POST['mj_action_type_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['mj_action_type_nonce'])), 'save_action_type')) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        $actionTypeId = isset($_POST['action_type_id']) ? (int) $_POST['action_type_id'] : 0;
        $isEdit = $actionTypeId > 0;

        $data = array(
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '',
            'slug' => isset($_POST['slug']) ? sanitize_title(wp_unslash((string) $_POST['slug'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '',
            'emoji' => isset($_POST['emoji']) ? sanitize_text_field(wp_unslash((string) $_POST['emoji'])) : '🎯',
            'category' => isset($_POST['category']) ? sanitize_key(wp_unslash((string) $_POST['category'])) : 'mj_attitude',
            'xp' => isset($_POST['xp']) ? (int) $_POST['xp'] : 0,
            'coins' => isset($_POST['coins']) ? (int) $_POST['coins'] : 0,
            'attribution' => isset($_POST['attribution']) ? sanitize_key(wp_unslash((string) $_POST['attribution'])) : 'manual',
            'auto_hook' => isset($_POST['auto_hook']) ? sanitize_text_field(wp_unslash((string) $_POST['auto_hook'])) : '',
            'repeatable' => isset($_POST['repeatable']) ? 1 : 0,
            'max_per_day' => isset($_POST['max_per_day']) ? (int) $_POST['max_per_day'] : 0,
            'display_order' => isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0,
            'status' => isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'active',
        );

        if ($isEdit) {
            $result = MjActionTypes::update($actionTypeId, $data);
            $notice = 'updated';
        } else {
            $result = MjActionTypes::create($data);
            $notice = 'created';
        }

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        $redirectUrl = add_query_arg(
            array(
                'page' => static::slug(),
                'mj_actions_notice' => $notice,
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirectUrl);
        exit;
    }

    public static function handle_delete_action_type(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $actionTypeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($actionTypeId <= 0) {
            wp_die(esc_html__('ID invalide.', 'mj-member'));
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_key(wp_unslash($_GET['_wpnonce'])) : '', static::deleteNonceAction($actionTypeId))) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        $result = MjActionTypes::delete($actionTypeId);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        $redirectUrl = add_query_arg(
            array(
                'page' => static::slug(),
                'mj_actions_notice' => 'deleted',
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirectUrl);
        exit;
    }
}
