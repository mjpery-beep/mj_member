<?php

namespace Mj\Member\Admin;

use Mj\Member\Core\Config;
use Mj\Member\Admin\Page\MembersPage;
use Mj\Member\Admin\Page\EventsPage;
use Mj\Member\Admin\Page\LocationsPage;
use Mj\Member\Admin\Page\DashboardPage;
use Mj\Member\Admin\Page\EventPhotosPage;
use Mj\Member\Admin\Page\ClosuresPage;
use Mj\Member\Admin\Page\EmailTemplatesPage;
use Mj\Member\Admin\Page\SendEmailsPage;
use Mj\Member\Admin\Page\SettingsPage;
use Mj\Member\Admin\Page\ImportMembersPage;
use Mj\Member\Admin\Page\CardsPdfPage;
use Mj\Member\Admin\Page\ContactMessagesPage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistre le menu d'administration du plugin MJ Member.
 */
final class AdminMenu
{
    public static function boot(): void
    {
        add_action('admin_menu', array(__CLASS__, 'registerMenu'));
    }

    /**
     * Déclare l'ensemble des pages du menu MJ Member.
     */
    public static function registerMenu(): void
    {
        $capability = Config::capability();
        $contactCapability = Config::contactCapability();

        add_menu_page(
            'Maison de Jeune',
            'Maison de Jeune',
            $capability,
            'mj_member',
            array(DashboardPage::class, 'render'),
            'dashicons-admin-home',
            30
        );

        add_submenu_page(
            'mj_member',
            __('Tableau de bord', 'mj-member'),
            __('Tableau de bord', 'mj-member'),
            $capability,
            'mj_member_dashboard',
            array(DashboardPage::class, 'render')
        );

        remove_submenu_page('mj_member', 'mj_member');

        $membersHook = add_submenu_page(
            'mj_member',
            __('Membres', 'mj-member'),
            __('Membres', 'mj-member'),
            $capability,
            MembersPage::slug(),
            array(MembersPage::class, 'render')
        );
        MembersPage::registerHooks($membersHook);

        add_submenu_page(
            'mj_member',
            __('Événements', 'mj-member'),
            __('Événements', 'mj-member'),
            $capability,
            EventsPage::slug(),
            array(EventsPage::class, 'render')
        );

        $locationsHook = add_submenu_page(
            'mj_member',
            __('Lieux', 'mj-member'),
            __('Lieux', 'mj-member'),
            $capability,
            LocationsPage::slug(),
            array(LocationsPage::class, 'render')
        );
        LocationsPage::registerHooks($locationsHook);

        add_submenu_page(
            'mj_member',
            __('Photos événements', 'mj-member'),
            __('Photos événements', 'mj-member'),
            $capability,
            EventPhotosPage::slug(),
            array(EventPhotosPage::class, 'render')
        );

        add_submenu_page(
            'mj_member',
            __('Fermetures MJ', 'mj-member'),
            __('Fermetures MJ', 'mj-member'),
            $capability,
            ClosuresPage::slug(),
            array(ClosuresPage::class, 'render')
        );

        add_submenu_page(
            'mj_member',
            'Template emails',
            'Template emails',
            $capability,
            EmailTemplatesPage::slug(),
            array(EmailTemplatesPage::class, 'render')
        );

        add_submenu_page(
            'mj_member',
            'Envoye email',
            'Envoye email',
            $capability,
            SendEmailsPage::slug(),
            array(SendEmailsPage::class, 'render')
        );

        add_submenu_page(
            'mj_member',
            'Configuration',
            'Configuration',
            'manage_options',
            SettingsPage::slug(),
            array(SettingsPage::class, 'render')
        );

        add_submenu_page(
            'mj_member',
            'Import CSV membres',
            'Import CSV',
            $capability,
            ImportMembersPage::slug(),
            array(ImportMembersPage::class, 'render')
        );

        add_submenu_page(
            'mj_member',
            __('Cartes de visite PDF', 'mj-member'),
            __('Cartes PDF', 'mj-member'),
            $capability,
            CardsPdfPage::slug(),
            array(CardsPdfPage::class, 'render')
        );

        if ($contactCapability !== '') {
            add_submenu_page(
                'mj_member',
                __('Messages', 'mj-member'),
                __('Messages', 'mj-member'),
                $contactCapability,
                ContactMessagesPage::slug(),
                array(ContactMessagesPage::class, 'render')
            );
        }
    }
}
