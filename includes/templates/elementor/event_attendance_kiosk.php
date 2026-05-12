<?php
/**
 * Template du widget Feuille de presence kiosque
 *
 * @package MjMember
 */

if (!defined('ABSPATH')) {
    exit;
}

use Mj\Member\Core\AssetsManager;

$settings = $this->get_settings_for_display();
$widget_id = 'mj-attkiosk-' . $this->get_id();
$title = !empty($settings['title']) ? (string) $settings['title'] : __('Feuille de presence', 'mj-member');
$default_event_id = !empty($settings['default_event_id']) ? absint($settings['default_event_id']) : 0;

AssetsManager::requirePackage('event-attendance-kiosk');

$config = array(
    'widgetId' => $widget_id,
    'title' => $title,
    'defaultEventId' => $default_event_id,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('mj-registration-manager'),
    'strings' => array(
        'loading' => __('Chargement...', 'mj-member'),
        'eventRequired' => __('Configurez un ID evenement dans le panneau Elementor.', 'mj-member'),
        'loadError' => __('Impossible de charger les donnees de presence.', 'mj-member'),
        'noOccurrences' => __('Aucune seance disponible.', 'mj-member'),
        'searchPlaceholder' => __('Rechercher un membre...', 'mj-member'),
        'noResults' => __('Aucun membre ne correspond a votre recherche.', 'mj-member'),
        'phoneMember' => __('Tel membre', 'mj-member'),
        'guardianName' => __('Tuteur', 'mj-member'),
        'phoneGuardian' => __('Tel tuteur', 'mj-member'),
        'ageSuffix' => __('ans', 'mj-member'),
        'present' => __('Present', 'mj-member'),
        'absent' => __('Absent', 'mj-member'),
        'statusUndefined' => __('Noté comme présent', 'mj-member'),
        'saving' => __('Mise a jour...', 'mj-member'),
    ),
);
?>

<div
    id="<?php echo esc_attr($widget_id); ?>"
    class="mj-attkiosk"
    data-mj-attkiosk
    data-config="<?php echo esc_attr(wp_json_encode($config)); ?>"
>
    <div class="mj-attkiosk__boot"><?php esc_html_e('Chargement du kiosque...', 'mj-member'); ?></div>
</div>
