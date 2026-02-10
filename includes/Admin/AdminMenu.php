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
use Mj\Member\Admin\Page\HoursPage;
use Mj\Member\Admin\Page\TodosPage;
use Mj\Member\Admin\Page\TodoProjectsPage;
use Mj\Member\Admin\Page\BadgesPage;
use Mj\Member\Admin\Page\TrophiesPage;
use Mj\Member\Admin\Page\LevelsPage;
use Mj\Member\Admin\Page\ActionsPage;
use Mj\Member\Admin\Page\NotificationsPage;
use Mj\Member\Admin\Page\TestimonialsPage;

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
        
        // Enregistrer les actions admin_post pour TrophiesPage, LevelsPage et ActionsPage
        TrophiesPage::boot();
        LevelsPage::boot();
        ActionsPage::boot();
    }

    /**
     * Déclare l'ensemble des pages du menu MJ Member.
     */
    public static function registerMenu(): void
    {
        $capability = Config::capability();
        $contactCapability = Config::contactCapability();
        $hoursCapability = Config::hoursCapability();
        if ($hoursCapability === '') {
            $hoursCapability = $capability;
        }

        // ===== MENU PRINCIPAL : Maison de Jeune =====
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
            'Configuration',
            'Configuration',
            'manage_options',
            SettingsPage::slug(),
            array(SettingsPage::class, 'render')
        );

        // ===== MENU ÉVÉNEMENTS =====
        add_menu_page(
            __('Événements MJ', 'mj-member'),
            __('MJ Événements', 'mj-member'),
            $capability,
            'mj_events',
            array(EventsPage::class, 'render'),
            'dashicons-calendar-alt',
            31
        );

        add_submenu_page(
            'mj_events',
            __('Événements', 'mj-member'),
            __('Événements', 'mj-member'),
            $capability,
            EventsPage::slug(),
            array(EventsPage::class, 'render')
        );

        remove_submenu_page('mj_events', 'mj_events');

        $locationsHook = add_submenu_page(
            'mj_events',
            __('Lieux', 'mj-member'),
            __('Lieux', 'mj-member'),
            $capability,
            LocationsPage::slug(),
            array(LocationsPage::class, 'render')
        );
        LocationsPage::registerHooks($locationsHook);

        add_submenu_page(
            'mj_events',
            __('Photos événements', 'mj-member'),
            __('Photos', 'mj-member'),
            $capability,
            EventPhotosPage::slug(),
            array(EventPhotosPage::class, 'render')
        );

        add_submenu_page(
            'mj_events',
            __('Fermetures MJ', 'mj-member'),
            __('Fermetures', 'mj-member'),
            $capability,
            ClosuresPage::slug(),
            array(ClosuresPage::class, 'render')
        );

        // ===== MENU COMMUNICATION =====
        add_menu_page(
            __('Communication MJ', 'mj-member'),
            __('MJ Communication', 'mj-member'),
            $capability,
            'mj_communication',
            array(EmailTemplatesPage::class, 'render'),
            'dashicons-email-alt',
            32
        );

        add_submenu_page(
            'mj_communication',
            'Template emails',
            'Templates emails',
            $capability,
            EmailTemplatesPage::slug(),
            array(EmailTemplatesPage::class, 'render')
        );

        remove_submenu_page('mj_communication', 'mj_communication');

        add_submenu_page(
            'mj_communication',
            'Envoyer email',
            'Envoyer email',
            $capability,
            SendEmailsPage::slug(),
            array(SendEmailsPage::class, 'render')
        );

        if ($contactCapability !== '') {
            add_submenu_page(
                'mj_communication',
                __('Messages', 'mj-member'),
                __('Messages', 'mj-member'),
                $contactCapability,
                ContactMessagesPage::slug(),
                array(ContactMessagesPage::class, 'render')
            );
        }

        $notificationsHook = add_submenu_page(
            'mj_communication',
            __('Notifications', 'mj-member'),
            __('Notifications', 'mj-member'),
            $capability,
            NotificationsPage::slug(),
            array(NotificationsPage::class, 'render')
        );
        NotificationsPage::registerHooks($notificationsHook);

        add_submenu_page(
            'mj_communication',
            __('Témoignages', 'mj-member'),
            __('Témoignages', 'mj-member'),
            $capability,
            TestimonialsPage::slug(),
            array(TestimonialsPage::class, 'render')
        );

        // ===== MENU GAMIFICATION =====
        add_menu_page(
            __('Gamification MJ', 'mj-member'),
            __('MJ Gamification', 'mj-member'),
            $capability,
            'mj_gamification',
            array(BadgesPage::class, 'render'),
            'dashicons-awards',
            33
        );

        $badgesHook = add_submenu_page(
            'mj_gamification',
            __('Badges', 'mj-member'),
            __('Badges', 'mj-member'),
            $capability,
            BadgesPage::slug(),
            array(BadgesPage::class, 'render')
        );
        BadgesPage::registerHooks($badgesHook);

        remove_submenu_page('mj_gamification', 'mj_gamification');

        $trophiesHook = add_submenu_page(
            'mj_gamification',
            __('Trophées', 'mj-member'),
            __('Trophées', 'mj-member'),
            $capability,
            TrophiesPage::slug(),
            array(TrophiesPage::class, 'render')
        );
        TrophiesPage::registerHooks($trophiesHook);

        $levelsHook = add_submenu_page(
            'mj_gamification',
            __('Niveaux', 'mj-member'),
            __('Niveaux', 'mj-member'),
            $capability,
            LevelsPage::slug(),
            array(LevelsPage::class, 'render')
        );
        LevelsPage::registerHooks($levelsHook);

        $actionsHook = add_submenu_page(
            'mj_gamification',
            __('Actions', 'mj-member'),
            __('Actions', 'mj-member'),
            $capability,
            ActionsPage::slug(),
            array(ActionsPage::class, 'render')
        );
        ActionsPage::registerHooks($actionsHook);

        // ===== MENU OUTILS =====
        add_menu_page(
            __('Outils MJ', 'mj-member'),
            __('MJ Outils', 'mj-member'),
            $capability,
            'mj_tools',
            array(TodosPage::class, 'render'),
            'dashicons-admin-tools',
            34
        );

        $todosHook = add_submenu_page(
            'mj_tools',
            __('Gestion des tâches', 'mj-member'),
            __('Todos', 'mj-member'),
            $capability,
            TodosPage::slug(),
            array(TodosPage::class, 'render')
        );
        TodosPage::registerHooks($todosHook);

        remove_submenu_page('mj_tools', 'mj_tools');

        $projectsHook = add_submenu_page(
            'mj_tools',
            __('Projets', 'mj-member'),
            __('Projets', 'mj-member'),
            $capability,
            TodoProjectsPage::slug(),
            array(TodoProjectsPage::class, 'render')
        );
        TodoProjectsPage::registerHooks($projectsHook);

        add_submenu_page(
            'mj_tools',
            __('Encodage des heures', 'mj-member'),
            __('Heures', 'mj-member'),
            $hoursCapability,
            HoursPage::slug(),
            array(HoursPage::class, 'render')
        );

        add_submenu_page(
            'mj_tools',
            'Import CSV membres',
            'Import CSV',
            $capability,
            ImportMembersPage::slug(),
            array(ImportMembersPage::class, 'render')
        );

        add_submenu_page(
            'mj_tools',
            __('Cartes de visite PDF', 'mj-member'),
            __('Cartes PDF', 'mj-member'),
            $capability,
            CardsPdfPage::slug(),
            array(CardsPdfPage::class, 'render')
        );
    }
}
