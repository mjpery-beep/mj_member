<?php

namespace Mj\Member;

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralise le chargement des modules fonctionnels du plugin.
 *
 * Les modules sont séparés en deux groupes :
 * - MODULES : chargés sur chaque requête (front, admin, AJAX, REST, cron).
 * - MODULES_ADMIN : chargés uniquement en contexte admin (pages admin,
 *   admin-ajax.php, admin-post.php) où is_admin() renvoie true.
 */
final class Bootstrap
{
    /**
     * Modules chargés sur chaque requête.
     *
     * @var string[]
     */
    private const MODULES = array(
        // Core infrastructure
        'includes/security.php',
        'includes/data_retention.php',
        'includes/backup.php',
        'includes/core/capabilities.php',
        'includes/core/assets.php',
        'includes/core/schema.php',

        // Front-end rendering, shortcodes, Elementor
        'includes/member_accounts.php',
        'includes/helpers/elementor-widgets.php',
        'includes/events_public.php',
        'includes/event_photos.php',
        'includes/photo_grimlins.php',
        'includes/grimlins_gallery.php',
        'includes/payment_confirmation.php',
        'includes/elementor/widgets-loader.php',
        'includes/account_menu_icons.php',
        'includes/templates/elementor/login_component.php',
        'includes/templates/elementor/account_menu_mobile.php',
        'includes/shortcode_inscription.php',
        'includes/templates/elementor/shortcode_member_account.php',
        'includes/templates/elementor/animateur_account.php',

        // Modules with front-end hooks or exported helpers
        'includes/hour_encode.php',
        'includes/notifications.php',
        'includes/notification_listeners.php',
        'includes/contact_messages.php',
        'includes/todos.php',
        'includes/documents.php',
        'includes/idea_box.php',
        'includes/web_push.php',
        'includes/dashboard.php',

        // Front AJAX handlers (some export localize helpers used by AssetsManager)
        'includes/core/ajax/front/todos.php',
        'includes/core/ajax/front/events.php',
        'includes/core/ajax/front/EventsManagerHandlers.php',
        'includes/core/ajax/front/notification_bell_ajax.php',
        'includes/core/ajax/front/testimonials_ajax.php',
        'includes/core/ajax/front/leave_requests_ajax.php',
        'includes/core/ajax/front/expenses_ajax.php',
        'includes/core/ajax/front/employee_documents_ajax.php',
        'includes/core/ajax/front/work_schedules_ajax.php',
        'includes/core/ajax/front/mileage_ajax.php',
    );

    /**
     * Modules chargés uniquement en contexte admin (is_admin()).
     * Inclut les pages d'administration, les handlers admin_post_*
     * et les endpoints wp_ajax_* réservés au back-office.
     *
     * @var string[]
     */
    private const MODULES_ADMIN = array(
        // Admin pages
        'includes/event_closures_admin.php',
        'includes/email_templates.php',
        'includes/send_emails.php',
        'includes/settings.php',
        'includes/import_members.php',
        'includes/cards_pdf_admin.php',
        'includes/event_photos_admin.php',
        'includes/badges_admin.php',
        'includes/contact_messages_admin.php',
        'includes/todos_admin.php',

        // Admin AJAX handlers
        'includes/core/ajax/admin/members.php',
        'includes/core/ajax/admin/payments.php',
        'includes/core/ajax/admin/events.php',
        'includes/core/ajax/admin/emails.php',
        'includes/core/ajax/admin/hours.php',
        'includes/core/ajax/admin/todos.php',
        'includes/core/ajax/admin/registration-manager.php',
        'includes/core/ajax/admin/documents.php',
        'includes/core/ajax/admin/testimonials.php',
        'includes/core/ajax/admin/leave-requests.php',
        'includes/core/ajax/admin/dynamic-fields.php',
    );

    /** @var bool */
    private static $loaded = false;

    public static function init(): void
    {
        if (self::$loaded) {
            return;
        }

        $modules = apply_filters('mj_member_bootstrap_modules', self::MODULES);

        if (is_admin()) {
            $adminModules = apply_filters('mj_member_bootstrap_admin_modules', self::MODULES_ADMIN);
            $modules = array_merge($modules, $adminModules);
        }

        foreach ($modules as $relativePath) {
            $resolved = self::resolvePath($relativePath);
            if (!$resolved) {
                continue;
            }

            require_once $resolved;
        }

        self::$loaded = true;
    }

    private static function resolvePath(string $relativePath): ?string
    {
        $clean = ltrim($relativePath, '/\\');
        $pluginPath = Config::path();
        $absolute = $pluginPath . $clean;

        if (is_readable($absolute)) {
            return $absolute;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            trigger_error(sprintf('[mj-member] Module introuvable : %s', $absolute));
        }

        return null;
    }
}
