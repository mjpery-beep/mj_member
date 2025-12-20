<?php

namespace Mj\Member\Classes\Front;

use Mj\Member\Classes\View\EventPage\EventPageModel;
use Mj\Member\Classes\View\EventPage\EventPageViewBuilder;
use Mj\Member\Core\AssetsManager;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(Controller::class)) {
    require_once __DIR__ . '/Controller.php';
}

/**
 * EventPageController - Contrôleur MVC propre pour la page événement
 * 
 * Remplace progressivement EventSingleController avec une architecture plus claire :
 * - Model : EventPageModel (données métier normalisées)
 * - View : Templates Twig dans templates/front/event-page/
 * - Localization : Données pour Preact (calendrier, inscriptions)
 */
class EventPageController extends Controller
{
    private const TITLE_FILTER_PRIORITY = 9999;

    /**
     * @var callable|null
     */
    private $documentTitleFilter = null;

    /**
     * @var EventPageModel|null
     */
    private $modelInstance = null;

    /**
     * Build avec overrides pour rétrocompatibilité
     *
     * @param array<string, mixed> $overrides
     * @return array{model: array<string, mixed>, view: array<string, mixed>, localization: array<string, mixed>}
     */
    public function build(array $overrides = array()): array
    {
        if (!empty($overrides)) {
            $this->context = array_merge($this->context, $overrides);
        }

        $payload = parent::build();

        $this->installDocumentTitleFilters($payload['model']);

        return $payload;
    }

    public function teardown(): void
    {
        if ($this->documentTitleFilter !== null) {
            remove_filter('pre_get_document_title', $this->documentTitleFilter, self::TITLE_FILTER_PRIORITY);
            $this->documentTitleFilter = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLegacyPayload(): array
    {
        // EventPage n'utilise pas de legacy payload, on retourne le contexte brut
        return $this->context;
    }

    /**
     * @param array<string, mixed> $legacy
     * @return array<string, mixed>
     */
    protected function buildModel(array $legacy): array
    {
        $this->modelInstance = new EventPageModel($this->context);
        return $this->modelInstance->toArray();
    }

    /**
     * @param array<string, mixed> $legacy
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    protected function buildView(array $legacy, array $model): array
    {
        $builder = new EventPageViewBuilder($model, $this->context);
        return $builder->build();
    }

    /**
     * @param array<string, mixed> $legacy
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    protected function buildLocalization(array $legacy, array $model): array
    {
        return array(
            'ajax' => array(
                'nonce' => wp_create_nonce('mj-member-event-register'),
                'url' => admin_url('admin-ajax.php'),
            ),
            'event' => array(
                'id' => isset($model['event']['id']) ? (int) $model['event']['id'] : 0,
                'title' => isset($model['event']['title']) ? (string) $model['event']['title'] : '',
                'slug' => isset($model['event']['slug']) ? (string) $model['event']['slug'] : '',
            ),
            'registration' => $this->buildRegistrationLocalization($model),
            'occurrences' => $this->buildOccurrencesLocalization($model),
            'user' => $this->buildUserLocalization($model),
            'i18n' => $this->buildI18nLocalization(),
        );
    }

    /**
     * Enqueue les assets nécessaires pour EventPage
     */
    protected function enqueueAssets(): void
    {
        AssetsManager::requirePackage('event-page');
    }

    /**
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    private function buildRegistrationLocalization(array $model): array
    {
        $registration = isset($model['registration']) && is_array($model['registration']) 
            ? $model['registration'] 
            : array();

        return array(
            'is_open' => !empty($registration['is_open']),
            'is_free_participation' => !empty($registration['is_free_participation']),
            'requires_validation' => !empty($registration['requires_validation']),
            'requires_login' => !empty($registration['requires_login']),
            'payment_required' => !empty($registration['payment_required']),
            'price' => isset($registration['price']) ? (float) $registration['price'] : 0,
            'price_display' => isset($registration['price_display']) ? (string) $registration['price_display'] : '',
            'capacity_total' => isset($registration['capacity_total']) ? (int) $registration['capacity_total'] : 0,
            'capacity_remaining' => isset($registration['capacity_remaining']) ? (int) $registration['capacity_remaining'] : 0,
            'participants' => isset($registration['participants']) && is_array($registration['participants']) 
                ? $registration['participants'] 
                : array(),
            'user_reservations' => isset($registration['user_reservations']) && is_array($registration['user_reservations']) 
                ? $registration['user_reservations'] 
                : array(),
        );
    }

    /**
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    private function buildOccurrencesLocalization(array $model): array
    {
        $schedule = isset($model['schedule']) && is_array($model['schedule']) 
            ? $model['schedule'] 
            : array();

        $occurrences = isset($schedule['occurrences']) && is_array($schedule['occurrences']) 
            ? $schedule['occurrences'] 
            : array();

        $items = array();
        foreach ($occurrences as $occ) {
            if (!is_array($occ)) {
                continue;
            }

            $items[] = array(
                'date' => isset($occ['date']) ? (string) $occ['date'] : '',
                'start' => isset($occ['start']) ? (string) $occ['start'] : '',
                'end' => isset($occ['end']) ? (string) $occ['end'] : '',
                'timestamp' => isset($occ['timestamp']) ? (int) $occ['timestamp'] : 0,
                'is_past' => !empty($occ['is_past']),
                'is_today' => !empty($occ['is_today']),
                'is_registered' => !empty($occ['is_registered']),
                'capacity_remaining' => isset($occ['capacity_remaining']) ? (int) $occ['capacity_remaining'] : null,
            );
        }

        return array(
            'mode' => isset($schedule['mode']) ? (string) $schedule['mode'] : 'single',
            'selection_mode' => isset($schedule['selection_mode']) ? (string) $schedule['selection_mode'] : 'all',
            'items' => $items,
            'has_multiple' => count($items) > 1,
            'allows_selection' => isset($schedule['allows_selection']) ? (bool) $schedule['allows_selection'] : false,
        );
    }

    /**
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    private function buildUserLocalization(array $model): array
    {
        $user = isset($model['user']) && is_array($model['user']) 
            ? $model['user'] 
            : array();

        return array(
            'is_logged_in' => !empty($user['is_logged_in']),
            'member_id' => isset($user['member_id']) ? (int) $user['member_id'] : 0,
            'can_register' => !empty($user['can_register']),
            'is_animateur' => !empty($user['is_animateur']),
            'is_volunteer' => !empty($user['is_volunteer']),
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildI18nLocalization(): array
    {
        return array(
            'register' => __("S'inscrire", 'mj-member'),
            'update_registration' => __('Mettre à jour mon inscription', 'mj-member'),
            'cancel_registration' => __('Annuler mon inscription', 'mj-member'),
            'login_required' => __('Connecte-toi pour continuer.', 'mj-member'),
            'login' => __('Se connecter', 'mj-member'),
            'free_participation' => __('Participation libre : aucune inscription requise.', 'mj-member'),
            'closed' => __('Inscriptions clôturées', 'mj-member'),
            'full' => __('Complet', 'mj-member'),
            'remaining_spots' => __('%d place(s) restante(s)', 'mj-member'),
            'select_occurrences' => __('Sélectionne les dates auxquelles tu souhaites participer', 'mj-member'),
            'confirm' => __('Confirmer', 'mj-member'),
            'cancel' => __('Annuler', 'mj-member'),
            'loading' => __('Chargement…', 'mj-member'),
            'error' => __('Une erreur est survenue. Merci de réessayer.', 'mj-member'),
            'success' => __('Inscription confirmée !', 'mj-member'),
            'payment_notice' => __('Vous pouvez effectuer le paiement maintenant ou plus tard dans votre espace membre ou en main propre à un animateur.', 'mj-member'),
            'view_pedagogical_content' => __('Voir le contenu pédagogique', 'mj-member'),
            'open_in_maps' => __('Ouvrir dans Google Maps', 'mj-member'),
            'shared_memories' => __('Souvenirs partagés', 'mj-member'),
            'upload_photo' => __('Partager une photo', 'mj-member'),
        );
    }

    /**
     * @param array<string, mixed> $model
     */
    private function installDocumentTitleFilters(array $model): void
    {
        $title = isset($model['event']['title']) ? (string) $model['event']['title'] : '';
        if ($title === '') {
            return;
        }

        $this->documentTitleFilter = function () use ($title) {
            return $title;
        };

        add_filter('pre_get_document_title', $this->documentTitleFilter, self::TITLE_FILTER_PRIORITY);
    }

    /**
     * Retourne l'instance du modèle si disponible
     * 
     * @return EventPageModel|null
     */
    public function getModelInstance(): ?EventPageModel
    {
        return $this->modelInstance;
    }
}
