<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Classes\Crud\MjLevels;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class LevelsPage
{
    private static $actionsRegistered = false;

    public static function slug(): string
    {
        return 'mj-member-levels';
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
            static::render_level_form();
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
            'save_level'   => array(static::class, 'handle_save_level'),
            'delete_level' => array(static::class, 'handle_delete_level'),
        );

        foreach ($forms as $name => $handler) {
            $hook = 'admin_post_' . $name;
            add_action($hook, $handler);
        }
    }

    public static function deleteNonceAction(int $levelId): string
    {
        return 'mj_member_delete_level_' . $levelId;
    }

    private static function render_list(): void
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $statusFilter = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';

        $args = array(
            'orderby' => 'level_number',
            'order' => 'ASC',
        );

        if ($search !== '') {
            $args['search'] = $search;
        }

        if ($statusFilter !== '') {
            $args['status'] = $statusFilter;
        }

        $levels = MjLevels::get_all($args);

        $createUrl = add_query_arg(
            array(
                'page'   => static::slug(),
                'action' => 'new',
            ),
            admin_url('admin.php')
        );

        $noticeKey = isset($_GET['mj_levels_notice']) ? sanitize_key(wp_unslash((string) $_GET['mj_levels_notice'])) : '';

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Niveaux', 'mj-member'); ?></h1>
            <a href="<?php echo esc_url($createUrl); ?>" class="page-title-action"><?php esc_html_e('Ajouter', 'mj-member'); ?></a>
            <hr class="wp-header-end">

            <?php if ($noticeKey === 'created'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Niveau créé avec succès.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'updated'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Niveau mis à jour.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'deleted'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Niveau supprimé.', 'mj-member'); ?></p></div>
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
                    <button type="submit" class="button"><?php esc_html_e('Filtrer', 'mj-member'); ?></button>
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('Image', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Niveau', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Titre', 'mj-member'); ?></th>
                        <th style="width: 120px;"><?php esc_html_e('Plafond XP', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Récompense', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Coins', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($levels)): ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('Aucun niveau trouvé.', 'mj-member'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($levels as $level): ?>
                            <?php
                            $editUrl = add_query_arg(array(
                                'page' => static::slug(),
                                'action' => 'edit',
                                'id' => $level['id'],
                            ), admin_url('admin.php'));

                            $deleteUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=delete_level&id=' . $level['id']),
                                static::deleteNonceAction($level['id'])
                            );

                            $imageUrl = '';
                            if (!empty($level['image_id'])) {
                                $imageData = wp_get_attachment_image_src($level['image_id'], 'thumbnail');
                                if ($imageData) {
                                    $imageUrl = $imageData[0];
                                }
                            }
                            ?>
                            <tr>
                                <td>
                                    <?php if ($imageUrl): ?>
                                        <img src="<?php echo esc_url($imageUrl); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: linear-gradient(135deg, #ffd700, #ff8c00); border-radius: 50%; color: #fff; font-weight: bold; font-size: 14px;"><?php echo esc_html($level['level_number']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 1.2em;"><?php echo esc_html($level['level_number']); ?></strong>
                                </td>
                                <td>
                                    <strong><a href="<?php echo esc_url($editUrl); ?>"><?php echo esc_html($level['title']); ?></a></strong>
                                    <?php if ($level['description']): ?>
                                        <br><small style="color: #666;"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($level['description']), 15)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html(number_format($level['xp_threshold'], 0, ',', ' ')); ?></strong> XP
                                </td>
                                <td>
                                    <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 12px;">+<?php echo esc_html($level['xp_reward']); ?> XP</span>
                                </td>
                                <td>
                                    <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px; font-size: 12px;">🪙 <?php echo esc_html($level['coins']); ?></span>
                                </td>
                                <td>
                                    <?php if ($level['status'] === 'active'): ?>
                                        <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Actif</span>
                                    <?php else: ?>
                                        <span style="background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Archivé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($editUrl); ?>" class="button button-small"><?php esc_html_e('Modifier', 'mj-member'); ?></a>
                                    <a href="<?php echo esc_url($deleteUrl); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Supprimer ce niveau ?', 'mj-member'); ?>');"><?php esc_html_e('Supprimer', 'mj-member'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-radius: 8px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Comment fonctionnent les niveaux ?', 'mj-member'); ?></h3>
                <ul style="margin-bottom: 0;">
                    <li>📊 <strong><?php esc_html_e('Plafond XP', 'mj-member'); ?></strong> : <?php esc_html_e('Le nombre d\'XP requis pour atteindre ce niveau.', 'mj-member'); ?></li>
                    <li>🎁 <strong><?php esc_html_e('Récompense XP', 'mj-member'); ?></strong> : <?php esc_html_e('Les XP bonus gagnés lors du passage à ce niveau.', 'mj-member'); ?></li>
                    <li>🪙 <strong><?php esc_html_e('Coins', 'mj-member'); ?></strong> : <?php esc_html_e('Les coins gagnés lors du passage à ce niveau.', 'mj-member'); ?></li>
                    <li>⬆️ <?php esc_html_e('Les membres progressent automatiquement vers le niveau suivant lorsqu\'ils atteignent le plafond XP.', 'mj-member'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    private static function render_level_form(): void
    {
        $levelId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $isEdit = $levelId > 0;

        $defaults = array(
            'level_number' => 1,
            'title' => '',
            'description' => '',
            'xp_threshold' => 0,
            'xp_reward' => 10,
            'coins' => 10,
            'image_id' => 0,
            'status' => MjLevels::STATUS_ACTIVE,
        );

        $level = $defaults;
        if ($isEdit) {
            $existing = MjLevels::get($levelId);
            if ($existing) {
                $level = array_merge($defaults, $existing);
            }
        } else {
            // Pour un nouveau niveau, suggérer le prochain numéro disponible
            $allLevels = MjLevels::get_all(array('orderby' => 'level_number', 'order' => 'DESC', 'limit' => 1));
            if (!empty($allLevels)) {
                $level['level_number'] = (int) $allLevels[0]['level_number'] + 1;
            }
        }

        $formAction = admin_url('admin-post.php');
        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));

        wp_enqueue_media();
        ?>
        <div class="wrap">
            <h1><?php echo $isEdit ? esc_html__('Modifier le niveau', 'mj-member') : esc_html__('Nouveau niveau', 'mj-member'); ?></h1>

            <form method="post" action="<?php echo esc_url($formAction); ?>">
                <input type="hidden" name="action" value="save_level">
                <?php wp_nonce_field('save_level', 'mj_level_nonce'); ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="level_id" value="<?php echo esc_attr($levelId); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="level_number"><?php esc_html_e('Numéro du niveau', 'mj-member'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="number" name="level_number" id="level_number" value="<?php echo esc_attr($level['level_number']); ?>" class="small-text" min="1" required>
                            <p class="description"><?php esc_html_e('Le numéro unique du niveau (1, 2, 3...)', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="title"><?php esc_html_e('Titre', 'mj-member'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" name="title" id="title" value="<?php echo esc_attr($level['title']); ?>" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Ex: Débutant, Apprenti, Expert, Légende...', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e('Description', 'mj-member'); ?></label></th>
                        <td>
                            <textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea($level['description']); ?></textarea>
                            <p class="description"><?php esc_html_e('Description affichée aux membres pour ce niveau.', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="xp_threshold"><?php esc_html_e('Plafond XP', 'mj-member'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="number" name="xp_threshold" id="xp_threshold" value="<?php echo esc_attr($level['xp_threshold']); ?>" min="0" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Nombre d\'XP nécessaires pour atteindre ce niveau. Le niveau 1 devrait avoir 0.', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="xp_reward"><?php esc_html_e('Récompense XP', 'mj-member'); ?></label></th>
                        <td>
                            <input type="number" name="xp_reward" id="xp_reward" value="<?php echo esc_attr($level['xp_reward']); ?>" min="0" class="small-text">
                            <p class="description"><?php esc_html_e('XP bonus attribués au membre lors du passage à ce niveau.', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coins"><?php esc_html_e('Coins', 'mj-member'); ?></label></th>
                        <td>
                            <input type="number" name="coins" id="coins" value="<?php echo esc_attr($level['coins']); ?>" min="0" class="small-text">
                            <p class="description"><?php esc_html_e('Coins attribués au membre lors du passage à ce niveau.', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="image_id"><?php esc_html_e('Image / Badge', 'mj-member'); ?></label></th>
                        <td>
                            <div id="level-image-preview" style="margin-bottom: 10px;">
                                <?php if (!empty($level['image_id'])): ?>
                                    <?php $imgUrl = wp_get_attachment_image_url($level['image_id'], 'thumbnail'); ?>
                                    <?php if ($imgUrl): ?>
                                        <img src="<?php echo esc_url($imgUrl); ?>" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="image_id" id="image_id" value="<?php echo esc_attr($level['image_id']); ?>">
                            <button type="button" class="button" id="select-image-btn"><?php esc_html_e('Sélectionner une image', 'mj-member'); ?></button>
                            <button type="button" class="button" id="remove-image-btn" <?php echo !empty($level['image_id']) ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                            <p class="description"><?php esc_html_e('Image représentant le niveau (badge, icône...)', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status"><?php esc_html_e('Statut', 'mj-member'); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected($level['status'], 'active'); ?>><?php esc_html_e('Actif', 'mj-member'); ?></option>
                                <option value="archived" <?php selected($level['status'], 'archived'); ?>><?php esc_html_e('Archivé', 'mj-member'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $isEdit ? esc_html__('Mettre à jour', 'mj-member') : esc_html__('Créer', 'mj-member'); ?></button>
                    <a href="<?php echo esc_url($backUrl); ?>" class="button"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
                    <?php if ($isEdit): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=delete_level&id=' . $levelId), static::deleteNonceAction($levelId))); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('Supprimer ce niveau ?', 'mj-member'); ?>');"><?php esc_html_e('Supprimer', 'mj-member'); ?></a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var mediaFrame;

            $('#select-image-btn').on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }
                mediaFrame = wp.media({
                    title: '<?php esc_html_e('Sélectionner une image', 'mj-member'); ?>',
                    button: { text: '<?php esc_html_e('Utiliser cette image', 'mj-member'); ?>' },
                    multiple: false
                });
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $('#image_id').val(attachment.id);
                    var imgUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    $('#level-image-preview').html('<img src="' + imgUrl + '" style="max-width: 150px; max-height: 150px; border-radius: 8px;">');
                    $('#remove-image-btn').show();
                });
                mediaFrame.open();
            });

            $('#remove-image-btn').on('click', function(e) {
                e.preventDefault();
                $('#image_id').val('');
                $('#level-image-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    public static function handle_save_level(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        if (!isset($_POST['mj_level_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mj_level_nonce'])), 'save_level')) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        $levelId = isset($_POST['level_id']) ? (int) $_POST['level_id'] : 0;
        $isEdit = $levelId > 0;

        $data = array(
            'level_number' => isset($_POST['level_number']) ? (int) $_POST['level_number'] : 1,
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '',
            'xp_threshold' => isset($_POST['xp_threshold']) ? (int) $_POST['xp_threshold'] : 0,
            'xp_reward' => isset($_POST['xp_reward']) ? (int) $_POST['xp_reward'] : 0,
            'coins' => isset($_POST['coins']) ? (int) $_POST['coins'] : 0,
            'image_id' => isset($_POST['image_id']) && $_POST['image_id'] !== '' ? (int) $_POST['image_id'] : null,
            'status' => isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'active',
        );

        if ($isEdit) {
            $result = MjLevels::update($levelId, $data);
        } else {
            $result = MjLevels::create($data);
        }

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        $redirectUrl = add_query_arg(
            array(
                'page' => static::slug(),
                'mj_levels_notice' => $isEdit ? 'updated' : 'created',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    public static function handle_delete_level(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $levelId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($levelId <= 0) {
            wp_die(esc_html__('Niveau invalide.', 'mj-member'));
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', static::deleteNonceAction($levelId))) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        $result = MjLevels::delete($levelId);

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        $redirectUrl = add_query_arg(
            array(
                'page' => static::slug(),
                'mj_levels_notice' => 'deleted',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }
}
