<?php

namespace Mj\Member;

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralise le chargement des modules fonctionnels du plugin.
 */
final class Bootstrap
{
    /**
     * Liste des modules procéduraux à charger.
     * Les chemins sont relatifs à la racine du plugin.
     *
     * @var string[]
     */
    private const MODULES = array(
        'includes/security.php',
        'includes/data_retention.php',
        'includes/member_accounts.php',
        'includes/dashboard.php',
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
        'includes/event_closures_admin.php',
        'includes/templates/elementor/animateur_account.php',
        'includes/hour_encode.php',
        'includes/email_templates.php',
        'includes/send_emails.php',
        'includes/settings.php',
        'includes/import_members.php',
        'includes/cards_pdf_admin.php',
        'includes/event_photos_admin.php',
        'includes/notifications.php',
        'includes/contact_messages.php',
        'includes/contact_messages_admin.php',
        'includes/todos.php',
        'includes/core/ajax/front/todos.php',
        'includes/core/ajax/front/events.php',
        'includes/core/ajax/front/EventsManagerHandlers.php',
        'includes/documents.php',
        'includes/idea_box.php',
        'includes/todos_admin.php',
        'includes/core/capabilities.php',
        'includes/core/assets.php',
        'includes/core/schema.php',
        'includes/core/ajax/admin/members.php',
        'includes/core/ajax/admin/payments.php',
        'includes/core/ajax/admin/events.php',
        'includes/core/ajax/admin/emails.php',
        'includes/core/ajax/admin/hours.php',
        'includes/core/ajax/admin/todos.php',
        'includes/core/ajax/admin/registration-manager.php',
    );

    /** @var bool */
    private static $loaded = false;

    public static function init(): void
    {
        if (self::$loaded) {
            return;
        }

        $modules = apply_filters('mj_member_bootstrap_modules', self::MODULES);

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
