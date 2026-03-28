<?php
/**
 * Template Elementor - Header MJ Complet
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

$config_json      = wp_json_encode($config);
$widget_unique_id = 'mj-header-' . esc_attr($widget_id);

if (!function_exists('mj_header_svg_icon')) {
function mj_header_svg_icon(string $name): string {
    $icons = array(
        'calendar'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd"/></svg>',
        'grid'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd"/></svg>',
        'cloud'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.5 9.75a6 6 0 0 1 11.573-2.226 3.75 3.75 0 0 1 4.133 4.303A4.5 4.5 0 0 1 18 20.25H6.75a5.25 5.25 0 0 1-4.233-8.385A6.032 6.032 0 0 1 4.5 9.75Z" clip-rule="evenodd"/></svg>',
        'bell'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.25 9a6.75 6.75 0 0 1 13.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 0 1-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 1 1-7.48 0 24.585 24.585 0 0 1-4.831-1.244.75.75 0 0 1-.298-1.205A8.217 8.217 0 0 0 5.25 9.75V9Zm4.502 8.9a2.25 2.25 0 1 0 4.496 0 25.057 25.057 0 0 1-4.496 0Z" clip-rule="evenodd"/></svg>',
        'user'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd"/></svg>',
        'lock'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3A5.25 5.25 0 0 0 12 1.5Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd"/></svg>',
        'menu'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg>',
        'close'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>',
        'arrow-right' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>',
        'logout'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.5 3.75A1.5 1.5 0 0 0 6 5.25v13.5a1.5 1.5 0 0 0 1.5 1.5h6a1.5 1.5 0 0 0 1.5-1.5V15a.75.75 0 0 1 1.5 0v3.75a3 3 0 0 1-3 3h-6a3 3 0 0 1-3-3V5.25a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3V9A.75.75 0 0 1 15 9V5.25a1.5 1.5 0 0 0-1.5-1.5h-6Zm10.72 4.72a.75.75 0 0 1 1.06 0l3 3a.75.75 0 0 1 0 1.06l-3 3a.75.75 0 1 1-1.06-1.06l1.72-1.72H9a.75.75 0 0 1 0-1.5h10.94l-1.72-1.72a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>',
        'link'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M19.902 4.098a3.75 3.75 0 0 0-5.304 0l-4.5 4.5a3.75 3.75 0 0 0 1.035 6.037.75.75 0 0 1-.646 1.353 5.25 5.25 0 0 1-1.449-8.45l4.5-4.5a5.25 5.25 0 1 1 7.424 7.424l-1.757 1.757a.75.75 0 1 1-1.06-1.06l1.757-1.757a3.75 3.75 0 0 0 0-5.304Zm-7.382 6.964a.75.75 0 0 1 1.06 0 3.75 3.75 0 0 0 5.304 0l.753-.754a5.25 5.25 0 0 0-7.424-7.424l-1.5 1.5a.75.75 0 0 1-1.06-1.06l1.5-1.5a6.75 6.75 0 0 1 9.546 9.546l-.754.754a5.25 5.25 0 0 1-7.424 0 .75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>',
    );
    return $icons[$name] ?? $icons['link'];
}
}
?>
<div id="<?php echo $widget_unique_id; ?>"
     class="mj-header <?php echo esc_attr($sticky_class); ?>"
     data-mj-header-id="<?php echo esc_attr($widget_id); ?>"
     data-config='<?php echo esc_attr($config_json); ?>'>

    <div class="mj-header__inner">
        <?php foreach ($ordered_items as $item):
            $key   = $item['key'];
            $order = $item['order'];
        ?>

        <?php if ($key === 'logo' && $logo_enabled): ?>
        <div class="mj-header__logo-wrap" style="order:<?php echo (int)$order; ?>">
            <a href="<?php echo esc_url($logo_link); ?>" class="mj-header__logo">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                <?php elseif ($is_preview): ?>
                    <span class="mj-header__logo-placeholder">&#128444; Logo (configurer dans Personnaliser &gt; Identité du site)</span>
                <?php else: ?>
                    <span class="mj-header__logo-text"><?php echo esc_html(get_bloginfo('name')); ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php elseif ($key === 'nav' && $nav_enabled): ?>
        <div class="mj-header__nav-wrap" style="order:<?php echo (int)$order; ?>">
            <button type="button" class="mj-header__burger" aria-label="<?php esc_attr_e('Menu', 'mj-member'); ?>" aria-expanded="false">
                <?php echo mj_header_svg_icon('menu'); ?>
            </button>
            <nav class="mj-header__nav" aria-label="<?php esc_attr_e('Navigation principale', 'mj-member'); ?>">
                <?php
                if ($nav_menu_id) {
                    wp_nav_menu(array('menu' => $nav_menu_id, 'container' => false, 'menu_class' => 'mj-header__nav-list', 'depth' => $nav_show_sub ? 2 : 1, 'fallback_cb' => false));
                } elseif ($is_preview) {
                    echo '<ul class="mj-header__nav-list"><li><a href="#">Accueil</a></li><li><a href="#">Activités</a></li><li><a href="#">Nous contacter</a></li></ul>';
                }
                ?>
            </nav>
        </div>

        <?php elseif ($key === 'spacer'): ?>
        <div class="mj-header__spacer" style="order:<?php echo (int)$order; ?>; flex:1;"></div>

        <?php elseif ($key === 'agenda' && $agenda_enabled): ?>
        <div class="mj-header__action-item" style="order:<?php echo (int)$order; ?>">
            <button type="button" class="mj-header__trigger" data-mj-header-trigger="agenda" aria-expanded="false" aria-haspopup="true" title="<?php echo esc_attr($agenda_label); ?>">
                <span class="mj-header__trigger-icon">
                    <?php echo $agenda_custom_icon ? '<img src="' . esc_url($agenda_custom_icon) . '" alt="" />' : mj_header_svg_icon('calendar'); ?>
                </span>
            </button>
            <div class="mj-header-dropdown mj-header-dropdown--agenda" data-mj-header-dropdown="agenda" role="dialog" aria-label="<?php echo esc_attr($agenda_label); ?>">
                <div class="mj-header-dropdown__header">
                    <span class="mj-header-dropdown__title"><?php echo esc_html($agenda_label); ?></span>
                    <a href="<?php echo esc_url($agenda_url); ?>" class="mj-header-dropdown__header-link"><?php esc_html_e('Voir tout', 'mj-member'); ?></a>
                    <button type="button" class="mj-header-dropdown__close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>"><?php echo mj_header_svg_icon('close'); ?></button>
                </div>
                <div class="mj-header-dropdown__content">
                    <?php if ($agenda_view_mode === 'calendrier'): ?>
                    <?php Mj_Member_Elementor_Events_Calendar_Widget::render_widget(
                        array(
                            'show_toolbar_left'    => 'yes',
                            'show_toolbar_actions' => '',
                            'months_before'        => 0,
                            'months_after'         => $agenda_calendar_months,
                            'show_leave_requests'  => '',
                            'show_todos'           => '',
                        ),
                        array(
                            'force_mobile'       => true,
                            'additional_classes' => array('mj-header-agenda-calendar'),
                        )
                    ); ?>
                    <?php else: ?>
                    <div class="mj-header-agenda-list" data-mj-agenda-list>
                        <?php if ($is_preview): ?>
                        <div class="mj-header-agenda-item">
                            <span class="mj-header-agenda-item__emoji">&#127919;</span>
                            <div class="mj-header-agenda-item__body">
                                <span class="mj-header-agenda-item__title">Événement exemple</span>
                                <span class="mj-header-agenda-item__date">Aujourd'hui</span>
                            </div>
                        </div>
                        <div class="mj-header-agenda-item">
                            <span class="mj-header-agenda-item__emoji">&#127912;</span>
                            <div class="mj-header-agenda-item__body">
                                <span class="mj-header-agenda-item__title">Atelier exemple</span>
                                <span class="mj-header-agenda-item__date">Demain</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mj-header-dropdown__loader">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-linecap="round"/>
                            </svg>
                            <span><?php esc_html_e('Chargement…', 'mj-member'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'gestionnaire' && $gest_enabled): ?>
        <div class="mj-header__action-item" style="order:<?php echo (int)$order; ?>">
            <button type="button" class="mj-header__trigger" data-mj-header-trigger="gestionnaire" aria-expanded="false" aria-haspopup="true" title="<?php echo esc_attr($gest_label); ?>">
                <span class="mj-header__trigger-icon">
                    <?php echo $gest_custom_icon ? '<img src="' . esc_url($gest_custom_icon) . '" alt="" />' : mj_header_svg_icon('grid'); ?>
                </span>
            </button>
            <div class="mj-header-dropdown mj-header-dropdown--gestionnaire" data-mj-header-dropdown="gestionnaire" role="dialog" aria-label="<?php echo esc_attr($gest_label); ?>">
                <div class="mj-header-dropdown__header">
                    <span class="mj-header-dropdown__title"><?php echo esc_html($gest_label); ?></span>
                    <a href="<?php echo esc_url($gest_url); ?>" class="mj-header-dropdown__header-link"><?php esc_html_e('Ouvrir', 'mj-member'); ?></a>
                    <button type="button" class="mj-header-dropdown__close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>"><?php echo mj_header_svg_icon('close'); ?></button>
                </div>
                <div class="mj-header-gest-favs-grid">
                    <div class="mj-header-gest-favs-col">
                        <a class="mj-header-gest-favs-col__title mj-header-gest-favs-col__title--link" href="<?php echo esc_url(add_query_arg('main-tab', 'member', $gest_url)); ?>"><?php esc_html_e('Membres', 'mj-member'); ?></a>
                        <?php if (!empty($gest_fav_member_items)): ?>
                        <ul class="mj-header-gest-fav-list">
                            <?php foreach ($gest_fav_member_items as $fi): ?>
                            <li class="mj-header-gest-fav-item">
                                <a class="mj-header-gest-fav-link" href="<?php echo esc_url((string)$fi['url']); ?>">
                                    <span class="mj-header-gest-fav-avatar">
                                        <?php if (!empty($fi['avatar_url'])): ?>
                                            <img src="<?php echo esc_url((string)$fi['avatar_url']); ?>" alt="" loading="lazy" />
                                        <?php else: ?>
                                            <span class="mj-header-gest-fav-initials"><?php echo esc_html((string)($fi['initials'] ?? 'M')); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="mj-header-gest-fav-label"><?php echo esc_html((string)$fi['label']); ?></span>
                                </a>
                                <div class="mj-header-gest-fav-actions">
                                    <a href="<?php echo esc_url((string)$fi['tab_urls']['info']); ?>" title="<?php esc_attr_e('Infos', 'mj-member'); ?>">&#128100;</a>
                                    <a href="<?php echo esc_url((string)$fi['tab_urls']['edit']); ?>" title="<?php esc_attr_e('Modifier', 'mj-member'); ?>">&#9998;</a>
                                    <a href="<?php echo esc_url((string)$fi['tab_urls']['badge']); ?>" title="<?php esc_attr_e('Badges', 'mj-member'); ?>">&#127942;</a>
                                    <a href="<?php echo esc_url((string)$fi['tab_urls']['testimonials']); ?>" title="<?php esc_attr_e('Témoignages', 'mj-member'); ?>">&#11088;</a>
                                    <a href="<?php echo esc_url((string)$fi['tab_urls']['notes']); ?>" title="<?php esc_attr_e('Notes', 'mj-member'); ?>">&#128203;</a>
                                    <a href="<?php echo esc_url((string)$fi['tab_urls']['ideas']); ?>" title="<?php esc_attr_e('Idées', 'mj-member'); ?>">&#128161;</a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="mj-header-gest-fav-empty"><?php esc_html_e('Aucun favori', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="mj-header-gest-favs-col">
                        <a class="mj-header-gest-favs-col__title mj-header-gest-favs-col__title--link" href="<?php echo esc_url(add_query_arg('main-tab', 'event', $gest_url)); ?>"><?php esc_html_e('Événements', 'mj-member'); ?></a>
                        <?php if (!empty($gest_fav_event_items)): ?>
                        <ul class="mj-header-gest-fav-list">
                            <?php foreach ($gest_fav_event_items as $fe): ?>
                            <li class="mj-header-gest-fav-item">
                                <a class="mj-header-gest-fav-link" href="<?php echo esc_url((string)$fe['url']); ?>">
                                    <span class="mj-header-gest-fav-emoji"><?php echo esc_html((string)($fe['emoji'] ?? '&#128197;')); ?></span>
                                    <span class="mj-header-gest-fav-label"><?php echo esc_html((string)$fe['label']); ?></span>
                                </a>
                                <div class="mj-header-gest-fav-actions">
                                    <a href="<?php echo esc_url((string)$fe['tab_urls']['inscription']); ?>" title="<?php esc_attr_e('Inscriptions', 'mj-member'); ?>">&#128203;</a>
                                    <a href="<?php echo esc_url((string)$fe['tab_urls']['presence']); ?>" title="<?php esc_attr_e('Présences', 'mj-member'); ?>">&#10003;</a>
                                    <a href="<?php echo esc_url((string)$fe['tab_urls']['edit']); ?>" title="<?php esc_attr_e('Modifier', 'mj-member'); ?>">&#9998;</a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="mj-header-gest-fav-empty"><?php esc_html_e('Aucun favori', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'nextcloud' && $nc_enabled): ?>
        <div class="mj-header__action-item" style="order:<?php echo (int)$order; ?>">
            <button type="button" class="mj-header__trigger" data-mj-header-trigger="nextcloud" aria-expanded="false" aria-haspopup="true" title="<?php echo esc_attr($nc_label); ?>">
                <span class="mj-header__trigger-icon">
                    <?php echo $nc_custom_icon ? '<img src="' . esc_url($nc_custom_icon) . '" alt="" />' : mj_header_svg_icon('cloud'); ?>
                </span>
            </button>
            <div class="mj-header-dropdown mj-header-dropdown--nextcloud" data-mj-header-dropdown="nextcloud" role="dialog" aria-label="<?php echo esc_attr($nc_label); ?>">
                <div class="mj-header-dropdown__header">
                    <span class="mj-header-dropdown__title"><?php echo esc_html($nc_label); ?></span>
                    <?php if ($nc_page_url): ?>
                    <a href="<?php echo esc_url($nc_page_url); ?>" class="mj-header-dropdown__header-link"><?php esc_html_e('Ouvrir', 'mj-member'); ?></a>
                    <?php endif; ?>
                    <button type="button" class="mj-header-dropdown__close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>"><?php echo mj_header_svg_icon('close'); ?></button>
                </div>
                <div data-mj-nc-apps style="display:none"></div>
            </div>
        </div>

        <?php elseif ($key === 'notifications' && $notif_enabled && $is_logged_in): ?>
        <div class="mj-header__action-item" style="order:<?php echo (int)$order; ?>">
            <button type="button" class="mj-header__trigger" data-mj-header-trigger="notifications" aria-expanded="false" aria-haspopup="true" title="<?php echo esc_attr($notif_label); ?>">
                <span class="mj-header__trigger-icon">
                    <?php echo $notif_icon ? '<img src="' . esc_url($notif_icon) . '" alt="" />' : mj_header_svg_icon('bell'); ?>
                </span>
                <span class="mj-header__trigger-badge<?php echo ($unread_count === 0 && !$is_preview) ? ' mj-header__trigger-badge--hidden' : ''; ?>" data-mj-notif-badge>
                    <?php echo $unread_count > 99 ? '99+' : (int)$unread_count; ?>
                </span>
            </button>
            <div class="mj-header-dropdown mj-header-dropdown--notifications" data-mj-header-dropdown="notifications" role="dialog" aria-label="<?php echo esc_attr($notif_label); ?>">
                <div class="mj-header-dropdown__header">
                    <span class="mj-header-dropdown__title"><?php echo esc_html($notif_label); ?></span>
                    <button type="button" class="mj-header-dropdown__header-action" data-notif-action="mark-all-read" title="<?php esc_attr_e('Tout marquer comme lu', 'mj-member'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                        </svg>
                        <span><?php esc_html_e('Tout lire', 'mj-member'); ?></span>
                    </button>
                    <button type="button" class="mj-header-dropdown__close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>"><?php echo mj_header_svg_icon('close'); ?></button>
                </div>
                <div class="mj-header-dropdown__content">
                    <div class="mj-header-notif-list" data-mj-notif-list>
                        <?php if ($is_preview): ?>
                        <div class="mj-header-notif-item mj-header-notif-item--unread">
                            <div class="mj-header-notif-icon">📅</div>
                            <div class="mj-header-notif-body">
                                <span class="mj-header-notif-title">Inscription confirmée : Stage Vacances</span>
                                <time class="mj-header-notif-time">il y a 5 min</time>
                            </div>
                        </div>
                        <div class="mj-header-notif-item">
                            <div class="mj-header-notif-icon">💰</div>
                            <div class="mj-header-notif-body">
                                <span class="mj-header-notif-title">Paiement confirmé</span>
                                <time class="mj-header-notif-time">il y a 1 h</time>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mj-header-dropdown__loader">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-linecap="round"/>
                            </svg>
                            <span><?php esc_html_e('Chargement…', 'mj-member'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mj-header-dropdown__footer">
                    <button type="button" class="mj-header-notif-delete-all" data-notif-action="archive-all" data-member-id="<?php echo (int)get_current_user_id(); ?>">
                        <?php esc_html_e('Tout supprimer', 'mj-member'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php elseif ($key === 'account' && $acc_enabled): ?>
        <div class="mj-header__action-item" style="order:<?php echo (int)$order; ?>">
            <button type="button" class="mj-header__trigger" data-mj-header-trigger="account" aria-expanded="false" aria-haspopup="true" title="<?php echo esc_attr($is_logged_in ? $acc_label_in : $acc_label_out); ?>">
                <span class="mj-header__trigger-icon <?php echo ($is_logged_in && $acc_avatar_url) ? 'mj-header__trigger-icon--avatar' : ''; ?>">
                    <?php if ($is_logged_in && $acc_avatar_url): echo '<img src="' . esc_url($acc_avatar_url) . '" alt="" class="mj-header__trigger-avatar" />';
                    elseif ($acc_icon): echo '<img src="' . esc_url($acc_icon) . '" alt="" />';
                    elseif ($is_logged_in): echo mj_header_svg_icon('user');
                    else: echo mj_header_svg_icon('lock'); endif; ?>
                </span>
            </button>
            <div class="mj-header-dropdown mj-header-dropdown--account" data-mj-header-dropdown="account" role="dialog" aria-label="<?php echo esc_attr($acc_label_in); ?>">
                <?php if ($is_logged_in || $is_preview): ?>
                <div class="mj-header-acc-panel-head">
                    <span class="mj-header-acc-panel-role"><?php echo esc_html($acc_member_role_label); ?></span>
                    <button type="button" class="mj-header-acc-panel-close mj-header-dropdown__close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"></path></svg>
                    </button>
                </div>
                <?php if (!empty($acc_link_sections)): ?>
                <div class="mj-header-acc-panel">
                    <?php foreach ($acc_link_sections as $section):
                        if (empty($section['links'])) continue;
                    ?>
                    <div class="mj-header-acc-section">
                        <?php if (!empty($section['label'])): ?>
                        <div class="mj-header-acc-section__title"><?php echo esc_html($section['label']); ?></div>
                        <?php endif; ?>
                        <div class="mj-header-acc-section__links">
                            <?php foreach ($section['links'] as $al):
                                $al_label     = $al['label'] ?? '';
                                $al_url       = $al['url'] ?? '#';
                                $al_badge     = (int)($al['badge'] ?? 0);
                                $al_logout    = !empty($al['is_logout']);
                                $al_icon      = $al['icon'] ?? array();
                                $al_icon_html = $al_icon['html'] ?? '';
                                $al_icon_url  = $al_icon['url'] ?? '';
                            ?>
                            <a href="<?php echo ($is_preview && $al_logout) ? '#' : esc_url($al_url); ?>"
                               class="mj-header-acc-card<?php echo $al_logout ? ' mj-header-acc-card--logout' : ''; ?>"
                               <?php if ($al_logout && !$is_preview): ?>data-mj-logout<?php endif; ?>>
                                <span class="mj-header-acc-card__icon" aria-hidden="true">
                                    <?php if ($al_icon_html): echo $al_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput
                                    elseif ($al_icon_url): echo '<img src="' . esc_url($al_icon_url) . '" alt="" loading="lazy" />';
                                    elseif ($al_logout): echo mj_header_svg_icon('logout');
                                    else: echo mj_header_svg_icon('link'); endif; ?>
                                </span>
                                <span class="mj-header-acc-card__label"><?php echo esc_html($al_label); ?></span>
                                <?php if ($al_badge > 0): ?>
                                <span class="mj-header-acc-card__badge"><?php echo (int)$al_badge; ?></span>
                                <?php endif; ?>
                                <span class="mj-header-acc-card__chevron" aria-hidden="true">&rsaquo;</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="mj-header-acc-login-panel">
                    <div class="mj-header-acc-login-panel__head">
                        <div class="mj-header-acc-login-panel__head-content">
                            <span class="mj-header-acc-login-panel__icon"><?php echo mj_header_svg_icon('lock'); ?></span>
                            <div>
                                <span class="mj-header-acc-login-panel__title"><?php esc_html_e('Connexion', 'mj-member'); ?></span>
                                <span class="mj-header-acc-login-panel__subtitle"><?php esc_html_e('Accédez à votre espace membre', 'mj-member'); ?></span>
                            </div>
                        </div>
                        <button type="button" class="mj-header-acc-panel-close mj-header-dropdown__close" aria-label="<?php esc_attr_e('Fermer', 'mj-member'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                        </button>
                    </div>
                    <form class="mj-header-login-form" method="post" novalidate>
                        <?php wp_nonce_field('mj-member-event-register', '_wpnonce_login', false); ?>
                        <div class="mj-header-login-form__field">
                            <label class="mj-header-login-form__label" for="<?php echo esc_attr($widget_unique_id); ?>-login-user"><?php esc_html_e('Identifiant ou e-mail', 'mj-member'); ?></label>
                            <input type="text"
                                   id="<?php echo esc_attr($widget_unique_id); ?>-login-user"
                                   name="log"
                                   class="mj-header-login-form__input"
                                   autocomplete="username"
                                   placeholder="<?php esc_attr_e('votre@email.com', 'mj-member'); ?>"
                                   required />
                        </div>
                        <div class="mj-header-login-form__field">
                            <label class="mj-header-login-form__label" for="<?php echo esc_attr($widget_unique_id); ?>-login-pwd"><?php esc_html_e('Mot de passe', 'mj-member'); ?></label>
                            <div class="mj-header-login-form__pwd-wrap">
                                <input type="password"
                                       id="<?php echo esc_attr($widget_unique_id); ?>-login-pwd"
                                       name="pwd"
                                       class="mj-header-login-form__input"
                                       autocomplete="current-password"
                                       placeholder="••••••••"
                                       required />
                                <button type="button" class="mj-header-login-form__toggle-pwd" aria-label="<?php esc_attr_e('Afficher/masquer le mot de passe', 'mj-member'); ?>">
                                    <svg class="mj-header-login-form__eye-show" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                                    <svg class="mj-header-login-form__eye-hide" viewBox="0 0 20 20" fill="currentColor" style="display:none"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a10.065 10.065 0 0 1-3.271.546 10.004 10.004 0 0 1-9.335-6.41 1.651 1.651 0 0 1 0-1.185A10.082 10.082 0 0 1 4.09 5.31l5.498 5.499a2.5 2.5 0 0 0 1.16 3.12Z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="mj-header-login-form__error" role="alert"></div>
                        <button type="submit" class="mj-header-login-form__submit"><?php echo esc_html($acc_label_out); ?></button>
                    </form>
                    <?php if (!empty($acc_register_url)): ?>
                    <div class="mj-header-acc-login-register">
                        <span><?php esc_html_e('Pas encore membre ?', 'mj-member'); ?></span>
                        <a href="<?php echo esc_url($acc_register_url); ?>" class="mj-header-acc-login-register__link"><?php esc_html_e('Créer un compte', 'mj-member'); ?></a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="mj-header-overlay" aria-hidden="true"></div>
</div>
