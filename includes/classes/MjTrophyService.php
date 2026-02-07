<?php

namespace Mj\Member\Classes;

use Mj\Member\Classes\Crud\MjTrophies;
use Mj\Member\Classes\Crud\MjMemberTrophies;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service métier pour l'attribution automatique des trophées.
 *
 * Ce service fournit une couche d'abstraction pour attribuer des trophées
 * aux membres via leur slug plutôt que leur ID. Cela permet de découpler
 * le code appelant de la connaissance des IDs de trophées en base.
 *
 * @example
 * // Attribuer le trophée "Compte créé" à un membre
 * MjTrophyService::assignBySlug($memberId, MjTrophyService::AUTO_ACCOUNT_CREATED);
 */
final class MjTrophyService
{
    /**
     * Slug du trophée attribué lors de la création d'un compte WordPress.
     */
    public const AUTO_ACCOUNT_CREATED = 'auto-account-created';

    /**
     * Slug du trophée attribué lors de la première inscription à un événement.
     */
    public const AUTO_FIRST_EVENT_REGISTRATION = 'auto-first-event-registration';

    /**
     * Slug du trophée attribué lors de la première présence validée à un événement.
     */
    public const AUTO_FIRST_ATTENDANCE = 'auto-first-attendance';

    /**
     * Slug du trophée attribué lors du premier paiement effectué.
     */
    public const AUTO_FIRST_PAYMENT = 'auto-first-payment';

    /**
     * Slug du trophée attribué lorsque le membre complète son profil.
     */
    public const AUTO_PROFILE_COMPLETED = 'auto-profile-completed';

    /**
     * Slug du trophée attribué lors du paiement de la cotisation annuelle.
     */
    public const MEMBERSHIP_PAID = 'cotisation-reglee';

    /**
     * Slug du trophée attribué lors de la première photo publiée.
     */
    public const FIRST_PHOTO_PUBLISHED = 'premiere-photo-publiee';

    /**
     * Attribue un trophée à un membre via le slug du trophée.
     *
     * Cette méthode recherche le trophée par son slug et l'attribue au membre
     * s'il existe et est actif. Si le trophée n'existe pas ou est archivé,
     * la méthode retourne silencieusement null sans erreur.
     *
     * @param int    $memberId ID du membre
     * @param string $slug     Slug du trophée (utiliser les constantes AUTO_*)
     * @param array  $options  Options supplémentaires pour l'attribution
     *                         - notes: string, notes à associer à l'attribution
     *                         - awarded_by_user_id: int, ID de l'utilisateur qui attribue
     *
     * @return int|null ID de l'attribution créée, ou null si le trophée n'existe pas
     *
     * @example
     * // Attribution basique
     * MjTrophyService::assignBySlug(123, MjTrophyService::AUTO_ACCOUNT_CREATED);
     *
     * // Attribution avec notes
     * MjTrophyService::assignBySlug(123, 'custom-trophy', [
     *     'notes' => 'Attribué automatiquement lors de la création du compte',
     * ]);
     */
    public static function assignBySlug(int $memberId, string $slug, array $options = array()): ?int
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return null;
        }

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

        // Récupérer le trophée par son slug
        $trophy = MjTrophies::get_by_slug($slug);
        if (!$trophy) {
            // Le trophée n'existe pas, on ignore silencieusement
            return null;
        }

        // Vérifier que le trophée est actif
        if ($trophy['status'] !== MjTrophies::STATUS_ACTIVE) {
            return null;
        }

        // Vérifier si le membre a déjà ce trophée actif (évite les notifications dupliquées)
        $existingAssignment = MjMemberTrophies::get_assignment($memberId, $trophy['id']);
        $wasAlreadyAwarded = $existingAssignment && $existingAssignment['status'] === MjMemberTrophies::STATUS_AWARDED;

        // Préparer les options d'attribution
        $awardOptions = array();
        if (isset($options['notes'])) {
            $awardOptions['notes'] = sanitize_textarea_field((string) $options['notes']);
        }
        if (isset($options['awarded_by_user_id'])) {
            $awardOptions['awarded_by_user_id'] = (int) $options['awarded_by_user_id'];
        }

        // Attribuer le trophée
        $result = MjMemberTrophies::award($memberId, $trophy['id'], $awardOptions);

        if (is_wp_error($result)) {
            // Erreur lors de l'attribution, on log mais on ne bloque pas
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[MjTrophyService] Échec attribution trophée "%s" au membre %d: %s',
                    $slug,
                    $memberId,
                    $result->get_error_message()
                ));
            }
            return null;
        }

        // Le hook mj_member_trophy_awarded est déclenché directement dans MjMemberTrophies::award()

        return (int) $result;
    }

    /**
     * Vérifie si un membre possède un trophée via son slug.
     *
     * @param int    $memberId ID du membre
     * @param string $slug     Slug du trophée
     *
     * @return bool True si le membre possède le trophée, false sinon
     */
    public static function hasTrophyBySlug(int $memberId, string $slug): bool
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return false;
        }

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return false;
        }

        $trophy = MjTrophies::get_by_slug($slug);
        if (!$trophy) {
            return false;
        }

        return MjMemberTrophies::has_trophy($memberId, $trophy['id']);
    }

    /**
     * Révoque un trophée d'un membre via son slug.
     *
     * @param int    $memberId ID du membre
     * @param string $slug     Slug du trophée
     *
     * @return bool True si révoqué avec succès, false sinon
     */
    public static function revokeBySlug(int $memberId, string $slug): bool
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            return false;
        }

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return false;
        }

        $trophy = MjTrophies::get_by_slug($slug);
        if (!$trophy) {
            return false;
        }

        return MjMemberTrophies::revoke($memberId, $trophy['id']);
    }

    /**
     * Retourne la liste des slugs de trophées automatiques définis.
     *
     * @return array<string,string> Tableau associatif slug => description
     */
    public static function getAutoSlugs(): array
    {
        return array(
            self::AUTO_ACCOUNT_CREATED => __('Compte activé', 'mj-member'),
            self::AUTO_FIRST_EVENT_REGISTRATION => __('Première inscription à un événement', 'mj-member'),
            self::AUTO_FIRST_ATTENDANCE => __('Première présence validée', 'mj-member'),
            self::AUTO_FIRST_PAYMENT => __('Premier paiement effectué', 'mj-member'),
            self::AUTO_PROFILE_COMPLETED => __('Profil complété', 'mj-member'),
            self::MEMBERSHIP_PAID => __('Cotisation réglée', 'mj-member'),
            self::FIRST_PHOTO_PUBLISHED => __('Première photo publiée', 'mj-member'),
        );
    }
}
