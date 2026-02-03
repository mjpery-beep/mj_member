<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Classes\Crud\MjTrophies;
use Mj\Member\Classes\Crud\MjMemberTrophies;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class TrophiesPage
{
    private static $actionsRegistered = false;

    public static function slug(): string
    {
        return 'mj-member-trophies';
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
            static::render_trophy_form();
            return;
        }

        if ($action === 'assign') {
            static::render_assign_form();
            return;
        }

        if ($action === 'assigned') {
            static::render_assigned_list();
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
            'save_trophy'   => array(static::class, 'handle_save_trophy'),
            'delete_trophy' => array(static::class, 'handle_delete_trophy'),
            'assign_trophy' => array(static::class, 'handle_assign_trophy'),
            'revoke_trophy' => array(static::class, 'handle_revoke_trophy'),
        );

        foreach ($forms as $name => $handler) {
            $hook = 'admin_post_' . $name;
            add_action($hook, $handler);
        }
    }

    public static function deleteNonceAction(int $trophyId): string
    {
        return 'mj_member_delete_trophy_' . $trophyId;
    }

    private static function render_list(): void
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $statusFilter = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';
        $autoFilter = isset($_GET['auto_mode']) ? sanitize_key(wp_unslash((string) $_GET['auto_mode'])) : '';

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

        if ($autoFilter !== '') {
            $args['auto_mode'] = $autoFilter === '1' ? 1 : 0;
        }

        $trophies = MjTrophies::get_all($args);

        $createUrl = add_query_arg(
            array(
                'page'   => static::slug(),
                'action' => 'new',
            ),
            admin_url('admin.php')
        );

        $noticeKey = isset($_GET['mj_trophies_notice']) ? sanitize_key(wp_unslash((string) $_GET['mj_trophies_notice'])) : '';

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Trophées', 'mj-member'); ?></h1>
            <a href="<?php echo esc_url($createUrl); ?>" class="page-title-action"><?php esc_html_e('Ajouter', 'mj-member'); ?></a>
            <hr class="wp-header-end">

            <?php if ($noticeKey === 'created'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Trophée créé avec succès.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'updated'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Trophée mis à jour.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'deleted'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Trophée supprimé.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'assigned'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Trophée attribué avec succès.', 'mj-member'); ?></p></div>
            <?php elseif ($noticeKey === 'revoked'): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Trophée révoqué.', 'mj-member'); ?></p></div>
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
                    <select name="auto_mode">
                        <option value=""><?php esc_html_e('Tous les modes', 'mj-member'); ?></option>
                        <option value="1" <?php selected($autoFilter, '1'); ?>><?php esc_html_e('Automatique', 'mj-member'); ?></option>
                        <option value="0" <?php selected($autoFilter, '0'); ?>><?php esc_html_e('Manuel', 'mj-member'); ?></option>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e('Filtrer', 'mj-member'); ?></button>
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('Image', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Titre', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('XP', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Mode', 'mj-member'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Statut', 'mj-member'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Ordre', 'mj-member'); ?></th>
                        <th style="width: 200px;"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trophies)): ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('Aucun trophée trouvé.', 'mj-member'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trophies as $trophy): ?>
                            <?php
                            $editUrl = add_query_arg(array(
                                'page' => static::slug(),
                                'action' => 'edit',
                                'id' => $trophy['id'],
                            ), admin_url('admin.php'));

                            $assignUrl = add_query_arg(array(
                                'page' => static::slug(),
                                'action' => 'assign',
                                'id' => $trophy['id'],
                            ), admin_url('admin.php'));

                            $assignedUrl = add_query_arg(array(
                                'page' => static::slug(),
                                'action' => 'assigned',
                                'id' => $trophy['id'],
                            ), admin_url('admin.php'));

                            $deleteUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=delete_trophy&id=' . $trophy['id']),
                                static::deleteNonceAction($trophy['id'])
                            );

                            $imageUrl = '';
                            if ($trophy['image_id'] > 0) {
                                $imageData = wp_get_attachment_image_src($trophy['image_id'], 'thumbnail');
                                if ($imageData) {
                                    $imageUrl = $imageData[0];
                                }
                            }

                            $assignedCount = MjMemberTrophies::count(array(
                                'trophy_id' => $trophy['id'],
                                'status' => MjMemberTrophies::STATUS_AWARDED,
                            ));
                            ?>
                            <tr>
                                <td>
                                    <?php if ($imageUrl): ?>
                                        <img src="<?php echo esc_url($imageUrl); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <span style="display: inline-block; width: 40px; height: 40px; background: #ddd; border-radius: 4px;"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><a href="<?php echo esc_url($editUrl); ?>"><?php echo esc_html($trophy['title']); ?></a></strong>
                                    <?php if ($trophy['description']): ?>
                                        <br><small style="color: #666;"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($trophy['description']), 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html($trophy['xp']); ?></strong> XP</td>
                                <td>
                                    <?php if ($trophy['auto_mode']): ?>
                                        <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 12px;">⚙️ Auto</span>
                                    <?php else: ?>
                                        <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px; font-size: 12px;">✋ Manuel</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trophy['status'] === 'active'): ?>
                                        <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Actif</span>
                                    <?php else: ?>
                                        <span style="background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px; font-size: 12px;">Archivé</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($trophy['display_order']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($editUrl); ?>" class="button button-small"><?php esc_html_e('Modifier', 'mj-member'); ?></a>
                                    <a href="<?php echo esc_url($assignUrl); ?>" class="button button-small"><?php esc_html_e('Attribuer', 'mj-member'); ?></a>
                                    <?php if ($assignedCount > 0): ?>
                                        <a href="<?php echo esc_url($assignedUrl); ?>" class="button button-small" title="<?php echo esc_attr(sprintf(__('%d membre(s)', 'mj-member'), $assignedCount)); ?>">
                                            👥 <?php echo esc_html($assignedCount); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_trophy_form(): void
    {
        $trophyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $isEdit = $trophyId > 0;

        $defaults = array(
            'title' => '',
            'slug' => '',
            'description' => '',
            'xp' => 10,
            'auto_mode' => false,
            'auto_hook' => '',
            'auto_threshold' => 1,
            'image_id' => 0,
            'display_order' => 0,
            'status' => MjTrophies::STATUS_ACTIVE,
        );

        $trophy = $defaults;
        if ($isEdit) {
            $existing = MjTrophies::get($trophyId);
            if ($existing) {
                $trophy = array_merge($defaults, $existing);
            }
        }

        $formAction = admin_url('admin-post.php');
        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));

        wp_enqueue_media();
        ?>
        <div class="wrap">
            <h1><?php echo $isEdit ? esc_html__('Modifier le trophée', 'mj-member') : esc_html__('Nouveau trophée', 'mj-member'); ?></h1>

            <form method="post" action="<?php echo esc_url($formAction); ?>">
                <input type="hidden" name="action" value="save_trophy">
                <?php wp_nonce_field('save_trophy', 'mj_trophy_nonce'); ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="trophy_id" value="<?php echo esc_attr($trophyId); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="title"><?php esc_html_e('Titre', 'mj-member'); ?> <span class="required">*</span></label></th>
                        <td><input type="text" name="title" id="title" value="<?php echo esc_attr($trophy['title']); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="slug"><?php esc_html_e('Slug', 'mj-member'); ?></label></th>
                        <td><input type="text" name="slug" id="slug" value="<?php echo esc_attr($trophy['slug']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e('Description', 'mj-member'); ?></label></th>
                        <td><textarea name="description" id="description" rows="4" class="large-text"><?php echo esc_textarea($trophy['description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="xp"><?php esc_html_e('Points XP', 'mj-member'); ?></label></th>
                        <td><input type="number" name="xp" id="xp" value="<?php echo esc_attr($trophy['xp']); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="image_id"><?php esc_html_e('Image', 'mj-member'); ?></label></th>
                        <td>
                            <div id="trophy-image-preview" style="margin-bottom: 10px;">
                                <?php if ($trophy['image_id'] > 0): ?>
                                    <?php $imgUrl = wp_get_attachment_image_url($trophy['image_id'], 'thumbnail'); ?>
                                    <?php if ($imgUrl): ?>
                                        <img src="<?php echo esc_url($imgUrl); ?>" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="image_id" id="image_id" value="<?php echo esc_attr($trophy['image_id']); ?>">
                            <button type="button" class="button" id="select-image-btn"><?php esc_html_e('Sélectionner une image', 'mj-member'); ?></button>
                            <button type="button" class="button" id="remove-image-btn" <?php echo $trophy['image_id'] ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Supprimer', 'mj-member'); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="auto_mode"><?php esc_html_e('Mode automatique', 'mj-member'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_mode" id="auto_mode" value="1" <?php checked($trophy['auto_mode']); ?>>
                                <?php esc_html_e('Attribution automatique selon un déclencheur', 'mj-member'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="auto-mode-fields" <?php echo $trophy['auto_mode'] ? '' : 'style="display:none;"'; ?>>
                        <th scope="row"><label for="auto_hook"><?php esc_html_e('Déclencheur', 'mj-member'); ?></label></th>
                        <td>
                            <select name="auto_hook" id="auto_hook">
                                <option value=""><?php esc_html_e('-- Sélectionner --', 'mj-member'); ?></option>
                                <option value="account_activated" <?php selected($trophy['auto_hook'], 'account_activated'); ?>><?php esc_html_e('Compte activé', 'mj-member'); ?></option>
                                <option value="subscription_paid" <?php selected($trophy['auto_hook'], 'subscription_paid'); ?>><?php esc_html_e('Cotisation payée', 'mj-member'); ?></option>
                                <option value="profile_completed" <?php selected($trophy['auto_hook'], 'profile_completed'); ?>><?php esc_html_e('Profil complété', 'mj-member'); ?></option>
                                <option value="photo_published" <?php selected($trophy['auto_hook'], 'photo_published'); ?>><?php esc_html_e('Photo publiée', 'mj-member'); ?></option>
                                <option value="photos_count" <?php selected($trophy['auto_hook'], 'photos_count'); ?>><?php esc_html_e('Nombre de photos', 'mj-member'); ?></option>
                                <option value="idea_submitted" <?php selected($trophy['auto_hook'], 'idea_submitted'); ?>><?php esc_html_e('Idée soumise', 'mj-member'); ?></option>
                                <option value="ideas_count" <?php selected($trophy['auto_hook'], 'ideas_count'); ?>><?php esc_html_e('Nombre d\'idées', 'mj-member'); ?></option>
                                <option value="event_registered" <?php selected($trophy['auto_hook'], 'event_registered'); ?>><?php esc_html_e('Inscrit à une activité', 'mj-member'); ?></option>
                                <option value="registrations_count" <?php selected($trophy['auto_hook'], 'registrations_count'); ?>><?php esc_html_e('Nombre d\'inscriptions', 'mj-member'); ?></option>
                                <option value="comment_posted" <?php selected($trophy['auto_hook'], 'comment_posted'); ?>><?php esc_html_e('Commentaire publié', 'mj-member'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="auto-mode-fields" <?php echo $trophy['auto_mode'] ? '' : 'style="display:none;"'; ?>>
                        <th scope="row"><label for="auto_threshold"><?php esc_html_e('Seuil', 'mj-member'); ?></label></th>
                        <td>
                            <input type="number" name="auto_threshold" id="auto_threshold" value="<?php echo esc_attr($trophy['auto_threshold']); ?>" min="1" class="small-text">
                            <p class="description"><?php esc_html_e('Nombre requis pour déclencher l\'attribution (ex: 5 photos)', 'mj-member'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="display_order"><?php esc_html_e('Ordre d\'affichage', 'mj-member'); ?></label></th>
                        <td><input type="number" name="display_order" id="display_order" value="<?php echo esc_attr($trophy['display_order']); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status"><?php esc_html_e('Statut', 'mj-member'); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected($trophy['status'], 'active'); ?>><?php esc_html_e('Actif', 'mj-member'); ?></option>
                                <option value="archived" <?php selected($trophy['status'], 'archived'); ?>><?php esc_html_e('Archivé', 'mj-member'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $isEdit ? esc_html__('Mettre à jour', 'mj-member') : esc_html__('Créer', 'mj-member'); ?></button>
                    <a href="<?php echo esc_url($backUrl); ?>" class="button"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
                    <?php if ($isEdit): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=delete_trophy&id=' . $trophyId), static::deleteNonceAction($trophyId))); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('Supprimer ce trophée ?', 'mj-member'); ?>');"><?php esc_html_e('Supprimer', 'mj-member'); ?></a>
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
                    $('#trophy-image-preview').html('<img src="' + imgUrl + '" style="max-width: 150px; max-height: 150px; border-radius: 8px;">');
                    $('#remove-image-btn').show();
                });
                mediaFrame.open();
            });

            $('#remove-image-btn').on('click', function(e) {
                e.preventDefault();
                $('#image_id').val('');
                $('#trophy-image-preview').html('');
                $(this).hide();
            });

            $('#auto_mode').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.auto-mode-fields').show();
                } else {
                    $('.auto-mode-fields').hide();
                }
            });
        });
        </script>
        <?php
    }

    private static function render_assign_form(): void
    {
        $trophyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($trophyId <= 0) {
            wp_die(esc_html__('Trophée invalide.', 'mj-member'));
        }

        $trophy = MjTrophies::get($trophyId);
        if (!$trophy) {
            wp_die(esc_html__('Trophée non trouvé.', 'mj-member'));
        }

        $members = MjMembers::get_all(array(
            'status' => 'active',
            'orderby' => 'last_name',
            'order' => 'ASC',
        ));

        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));
        $formAction = admin_url('admin-post.php');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(sprintf(__('Attribuer le trophée : %s', 'mj-member'), $trophy['title'])); ?></h1>

            <form method="post" action="<?php echo esc_url($formAction); ?>">
                <input type="hidden" name="action" value="assign_trophy">
                <input type="hidden" name="trophy_id" value="<?php echo esc_attr($trophyId); ?>">
                <?php wp_nonce_field('assign_trophy', 'mj_assign_trophy_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="member_id"><?php esc_html_e('Membre', 'mj-member'); ?></label></th>
                        <td>
                            <select name="member_id" id="member_id" required style="min-width: 300px;">
                                <option value=""><?php esc_html_e('-- Sélectionner un membre --', 'mj-member'); ?></option>
                                <?php foreach ($members as $member): ?>
                                    <?php
                                    $hasTrophy = MjMemberTrophies::has_trophy($member['id'], $trophyId);
                                    $label = sprintf('%s %s', $member['first_name'], $member['last_name']);
                                    if ($hasTrophy) {
                                        $label .= ' ✓';
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($member['id']); ?>" <?php disabled($hasTrophy); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notes"><?php esc_html_e('Notes', 'mj-member'); ?></label></th>
                        <td><textarea name="notes" id="notes" rows="3" class="large-text"></textarea></td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Attribuer', 'mj-member'); ?></button>
                    <a href="<?php echo esc_url($backUrl); ?>" class="button"><?php esc_html_e('Annuler', 'mj-member'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_assigned_list(): void
    {
        $trophyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($trophyId <= 0) {
            wp_die(esc_html__('Trophée invalide.', 'mj-member'));
        }

        $trophy = MjTrophies::get($trophyId);
        if (!$trophy) {
            wp_die(esc_html__('Trophée non trouvé.', 'mj-member'));
        }

        $assignments = MjMemberTrophies::get_for_trophy($trophyId);
        $backUrl = add_query_arg('page', static::slug(), admin_url('admin.php'));

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(sprintf(__('Membres avec le trophée : %s', 'mj-member'), $trophy['title'])); ?></h1>

            <p><a href="<?php echo esc_url($backUrl); ?>" class="button">← <?php esc_html_e('Retour à la liste', 'mj-member'); ?></a></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Membre', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Date d\'attribution', 'mj-member'); ?></th>
                        <th><?php esc_html_e('Notes', 'mj-member'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Actions', 'mj-member'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('Aucun membre n\'a encore ce trophée.', 'mj-member'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <?php
                            $member = MjMembers::get($assignment['member_id']);
                            $memberName = $member ? sprintf('%s %s', $member['first_name'], $member['last_name']) : __('Membre inconnu', 'mj-member');
                            $revokeUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=revoke_trophy&trophy_id=' . $trophyId . '&member_id=' . $assignment['member_id']),
                                'revoke_trophy_' . $trophyId . '_' . $assignment['member_id']
                            );
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($memberName); ?></strong></td>
                                <td><?php echo esc_html($assignment['awarded_at'] ? wp_date('d/m/Y H:i', strtotime($assignment['awarded_at'])) : '-'); ?></td>
                                <td><?php echo esc_html($assignment['notes'] ?: '-'); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($revokeUrl); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Révoquer ce trophée ?', 'mj-member'); ?>');">
                                        <?php esc_html_e('Révoquer', 'mj-member'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_save_trophy(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        if (!isset($_POST['mj_trophy_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mj_trophy_nonce'])), 'save_trophy')) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        $trophyId = isset($_POST['trophy_id']) ? (int) $_POST['trophy_id'] : 0;
        $isEdit = $trophyId > 0;

        $data = array(
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '',
            'slug' => isset($_POST['slug']) ? sanitize_title(wp_unslash((string) $_POST['slug'])) : '',
            'description' => isset($_POST['description']) ? wp_kses_post(wp_unslash((string) $_POST['description'])) : '',
            'xp' => isset($_POST['xp']) ? (int) $_POST['xp'] : 0,
            'auto_mode' => isset($_POST['auto_mode']) ? 1 : 0,
            'auto_hook' => isset($_POST['auto_hook']) ? sanitize_key(wp_unslash((string) $_POST['auto_hook'])) : '',
            'auto_threshold' => isset($_POST['auto_threshold']) ? (int) $_POST['auto_threshold'] : 1,
            'image_id' => isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0,
            'display_order' => isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0,
            'status' => isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'active',
        );

        if ($isEdit) {
            $result = MjTrophies::update($trophyId, $data);
            $notice = 'updated';
        } else {
            $result = MjTrophies::create($data);
            $notice = 'created';
            if (!is_wp_error($result) && is_int($result)) {
                $trophyId = $result;
            }
        }

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        if ($result === false) {
            global $wpdb;
            $error_msg = $wpdb->last_error ? $wpdb->last_error : __('Erreur lors de la sauvegarde du trophée.', 'mj-member');
            wp_die(esc_html($error_msg));
        }

        $redirect_url = add_query_arg(array(
            'page' => static::slug(),
            'mj_trophies_notice' => $notice,
        ), admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function handle_delete_trophy(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $trophyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($trophyId <= 0) {
            wp_die(esc_html__('Trophée invalide.', 'mj-member'));
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', static::deleteNonceAction($trophyId))) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        MjTrophies::delete($trophyId);

        wp_safe_redirect(add_query_arg(array(
            'page' => static::slug(),
            'mj_trophies_notice' => 'deleted',
        ), admin_url('admin.php')));
        exit;
    }

    public static function handle_assign_trophy(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        if (!isset($_POST['mj_assign_trophy_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mj_assign_trophy_nonce'])), 'assign_trophy')) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        $trophyId = isset($_POST['trophy_id']) ? (int) $_POST['trophy_id'] : 0;
        $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['notes'])) : '';

        if ($trophyId <= 0 || $memberId <= 0) {
            wp_die(esc_html__('Données invalides.', 'mj-member'));
        }

        $result = MjMemberTrophies::award($memberId, $trophyId, array('notes' => $notes));

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        wp_safe_redirect(add_query_arg(array(
            'page' => static::slug(),
            'mj_trophies_notice' => 'assigned',
        ), admin_url('admin.php')));
        exit;
    }

    public static function handle_revoke_trophy(): void
    {
        if (!current_user_can(Config::capability())) {
            wp_die(esc_html__('Accès refusé.', 'mj-member'));
        }

        $trophyId = isset($_GET['trophy_id']) ? (int) $_GET['trophy_id'] : 0;
        $memberId = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;

        if ($trophyId <= 0 || $memberId <= 0) {
            wp_die(esc_html__('Données invalides.', 'mj-member'));
        }

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'revoke_trophy_' . $trophyId . '_' . $memberId)) {
            wp_die(esc_html__('Nonce invalide.', 'mj-member'));
        }

        MjMemberTrophies::revoke($memberId, $trophyId);

        wp_safe_redirect(add_query_arg(array(
            'page' => static::slug(),
            'action' => 'assigned',
            'id' => $trophyId,
            'mj_trophies_notice' => 'revoked',
        ), admin_url('admin.php')));
        exit;
    }
}
