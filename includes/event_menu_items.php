<?php

namespace Mj\Member\Module {
    use Mj\Member\Core\Contracts\ModuleInterface;

    if (!defined('ABSPATH')) {
        exit;
    }

    final class EventMenuItemsModule implements ModuleInterface {
        public function register(): void {
            if (is_admin()) {
                add_action('load-nav-menus.php', 'mj_member_event_menu_items_register_metabox');
                add_action('admin_head-nav-menus.php', 'mj_member_event_menu_items_register_metabox', 1);
                add_action('admin_head-nav-menus.php', 'mj_member_event_menu_items_make_metabox_visible');
            }
        }
    }
}

namespace {
    use Mj\Member\Classes\Crud\MjEvents;

    if (!defined('ABSPATH')) {
        exit;
    }

    if (!function_exists('mj_member_event_menu_items_register_metabox')) {
        function mj_member_event_menu_items_register_metabox() {
            add_meta_box(
                'mj-member-events-menu-items',
                __('Événements', 'mj-member'),
                'mj_member_event_menu_items_render_metabox',
                'nav-menus',
                'side',
                'default'
            );

            mj_member_event_menu_items_make_metabox_visible();
        }
    }

    if (!function_exists('mj_member_event_menu_items_make_metabox_visible')) {
        function mj_member_event_menu_items_make_metabox_visible() {
            $user_id = get_current_user_id();
            if ($user_id <= 0) {
                return;
            }

            $hidden_metaboxes = get_user_option('metaboxhidden_nav-menus', $user_id);
            if (!is_array($hidden_metaboxes)) {
                return;
            }

            $hidden_metaboxes = array_values(array_diff($hidden_metaboxes, array('mj-member-events-menu-items')));
            update_user_meta($user_id, 'metaboxhidden_nav-menus', $hidden_metaboxes);
        }
    }

    if (!function_exists('mj_member_event_menu_items_get_permalink')) {
        function mj_member_event_menu_items_get_permalink($event): string {
            $slug = isset($event->slug) ? sanitize_title((string) $event->slug) : '';
            if ($slug === '') {
                return '';
            }

            $permalink = apply_filters('mj_member_event_permalink', '', $event);
            if (is_string($permalink) && $permalink !== '') {
                return esc_url_raw($permalink);
            }

            return home_url('/evenement/' . rawurlencode($slug) . '/');
        }
    }

    if (!function_exists('mj_member_event_menu_items_render_metabox')) {
        function mj_member_event_menu_items_render_metabox() {
            if (!current_user_can('edit_theme_options')) {
                return;
            }

            $events = MjEvents::get_all(array(
                'statuses' => array(MjEvents::STATUS_ACTIVE),
                'orderby' => 'date_debut',
                'order' => 'ASC',
            ));

            $items = array();
            foreach ($events as $event) {
                if (!$event || !isset($event->id)) {
                    continue;
                }

                $url = mj_member_event_menu_items_get_permalink($event);
                if ($url === '') {
                    continue;
                }

                $title = isset($event->title) ? wp_strip_all_tags((string) $event->title) : '';
                if ($title === '') {
                    $title = sprintf(__('Événement #%d', 'mj-member'), (int) $event->id);
                }

                $items[] = array(
                    'title' => $title,
                    'url' => $url,
                );
            }
            ?>
            <div id="posttype-mj-member-event" class="posttypediv">
                <div id="tabs-panel-mj-member-event" class="tabs-panel tabs-panel-active">
                    <ul id="mj-member-event-checklist" class="categorychecklist form-no-clear">
                        <?php if (empty($items)) : ?>
                            <li><?php esc_html_e('Aucun événement actif à ajouter.', 'mj-member'); ?></li>
                        <?php else : ?>
                            <?php foreach ($items as $index => $item) : ?>
                                <li>
                                    <label class="menu-item-title">
                                        <input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo esc_attr('-' . ($index + 1)); ?>][menu-item-object-id]" value="<?php echo esc_attr('-' . ($index + 1)); ?>" />
                                        <?php echo esc_html($item['title']); ?>
                                    </label>
                                    <input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr('-' . ($index + 1)); ?>][menu-item-type]" value="custom" />
                                    <input type="hidden" class="menu-item-title" name="menu-item[<?php echo esc_attr('-' . ($index + 1)); ?>][menu-item-title]" value="<?php echo esc_attr($item['title']); ?>" />
                                    <input type="hidden" class="menu-item-url" name="menu-item[<?php echo esc_attr('-' . ($index + 1)); ?>][menu-item-url]" value="<?php echo esc_url($item['url']); ?>" />
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php if (!empty($items)) : ?>
                    <p class="button-controls wp-clearfix">
                        <span class="add-to-menu">
                            <input type="submit" class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e('Ajouter au menu', 'mj-member'); ?>" name="add-post-type-menu-item" id="submit-posttype-mj-member-event" />
                            <span class="spinner"></span>
                        </span>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }
    }
}