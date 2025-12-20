<?php
/**
 * Template context pour EventPage
 * 
 * Appelé via template_include depuis events_public.php
 * Le payload est déjà construit par EventPageController et stocké dans $GLOBALS['mj_event_page_payload']
 */

use MjMember\Front\EventPageController;
use MjMember\Classes\View\EventPage\EventPageModel;
use MjMember\Classes\View\EventPage\EventPageViewBuilder;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

// Récupère le payload préparé par events_public.php
$payload = isset($GLOBALS['mj_event_page_payload']) ? $GLOBALS['mj_event_page_payload'] : null;

if (!$payload || !is_array($payload)) {
    // Fallback si pas de payload (ne devrait pas arriver)
    wp_die(__('Événement non trouvé.', 'mj-member'), 404);
}

// Charger les assets AVANT get_header() pour qu'ils soient inclus
AssetsManager::requirePackage('event-page');

// Affiche la page
get_header();
echo mj_member_render_event_page_html($payload);
get_footer();

/**
 * Rend la page événement avec Twig ou fallback
 *
 * @param array<string,mixed> $payload
 * @return string HTML rendu
 */
function mj_member_render_event_page_html(array $payload): string
{
    // Charger Twig si disponible
    if (!class_exists('Twig\\Environment')) {
        $pluginAutoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
        if (is_readable($pluginAutoload)) {
            require_once $pluginAutoload;
        }
    }

    if (!class_exists('Twig\\Environment')) {
        $globalAutoload = trailingslashit(ABSPATH) . 'vendor/autoload.php';
        if (is_readable($globalAutoload)) {
            require_once $globalAutoload;
        }
    }

    if (!class_exists('Twig\\Environment')) {
        return mj_member_render_event_page_fallback($payload);
    }

    try {
        $templateDir = dirname(__DIR__);
        $loader = new \Twig\Loader\FilesystemLoader($templateDir);
        $twig = new \Twig\Environment($loader, array(
            'cache' => false,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'autoescape' => 'html',
        ));

        // Ajouter les filtres WordPress
        $twig->addFilter(new \Twig\TwigFilter('esc_url', 'esc_url'));
        $twig->addFilter(new \Twig\TwigFilter('esc_attr', 'esc_attr'));
        $twig->addFilter(new \Twig\TwigFilter('wp_kses_post', 'wp_kses_post', array('is_safe' => array('html'))));

        // Ajouter les fonctions WordPress de traduction
        $twig->addFunction(new \Twig\TwigFunction('__', '__'));
        $twig->addFunction(new \Twig\TwigFunction('_n', '_n'));
        $twig->addFunction(new \Twig\TwigFunction('_x', '_x'));

        $html = $twig->render('event-page/event-page.html.twig', array(
            'model' => $payload['model'] ?? array(),
            'view' => $payload['view'] ?? array(),
            'localization' => $payload['localization'] ?? array(),
            'user' => $payload['model']['user'] ?? array(),
            'assets' => $payload['view']['assets'] ?? array(),
        ));

        // Injecter les données de localisation pour JavaScript
        $localizationJson = wp_json_encode($payload['localization'] ?? array());
        $html .= sprintf(
            '<script type="application/json" id="mj-event-page-config">%s</script>',
            $localizationJson
        );

        return $html;
    } catch (\Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EventPage Twig Error: ' . $e->getMessage());
        }

        return mj_member_render_event_page_fallback($payload);
    }
}

/**
 * Fallback de rendu si Twig n'est pas disponible
 *
 * @param array<string,mixed> $payload
 * @return string
 */
function mj_member_render_event_page_fallback(array $payload): string
{
    $view = $payload['view'] ?? array();
    $page = $view['page'] ?? array();
    $partials = $view['partials'] ?? array();

    $hero = $partials['hero'] ?? array();
    $description = $partials['description'] ?? array();
    $registration = $partials['registration'] ?? array();
    $location = $partials['location'] ?? array();

    ob_start();
    ?>
    
    <main class="<?php echo esc_attr($page['class'] ?? 'mj-event-page'); ?>" style="<?php echo esc_attr($page['style'] ?? ''); ?>">
        <div class="mj-event-page__wrapper">
            <section class="mj-event-page__hero">
                <h1>NO TWIG !! </h1>
                <?php if (!empty($hero['type_label'])) : ?>
                    <span class="mj-event-page__badge"><?php echo esc_html($hero['type_label']); ?></span>
                <?php endif; ?>
                <h1 class="mj-event-page__title"><?php echo esc_html($hero['title'] ?? ''); ?></h1>
                <?php if (!empty($hero['display_label'])) : ?>
                    <p class="mj-event-page__date"><?php echo esc_html($hero['display_label']); ?></p>
                <?php endif; ?>
                <?php if (!empty($hero['cover_url'])) : ?>
                    <div class="mj-event-page__hero-cover">
                        <img src="<?php echo esc_url($hero['cover_url']); ?>" alt="<?php echo esc_attr($hero['title'] ?? ''); ?>" />
                    </div>
                <?php endif; ?>
            </section>

            <div class="mj-event-page__body">
                <div class="mj-event-page__main">
                    <?php if (!empty($description['content_html'])) : ?>
                        <section class="mj-event-page__card mj-event-page__description">
                            <?php echo wp_kses_post($description['content_html']); ?>
                        </section>
                    <?php endif; ?>

                    <section class="mj-event-page__card mj-event-page__registration">
                        <h3><?php esc_html_e('Inscription', 'mj-member'); ?></h3>
                        <?php if (!empty($registration['is_free_participation'])) : ?>
                            <p><?php esc_html_e('Participation libre : aucune inscription requise.', 'mj-member'); ?></p>
                        <?php elseif (!empty($registration['requires_login'])) : ?>
                            <p><?php esc_html_e('Connectez-vous pour vous inscrire.', 'mj-member'); ?></p>
                        <?php endif; ?>
                    </section>

                    <?php if (!empty($location['has_location'])) : ?>
                        <section class="mj-event-page__card mj-event-page__location">
                            <h2><?php esc_html_e('Lieu', 'mj-member'); ?></h2>
                            <?php if (!empty($location['name'])) : ?>
                                <p class="mj-event-page__location-name"><?php echo esc_html($location['name']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($location['address'])) : ?>
                                <p class="mj-event-page__location-address"><?php echo esc_html($location['address']); ?></p>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <?php
    return ob_get_clean() ?: '';
}
