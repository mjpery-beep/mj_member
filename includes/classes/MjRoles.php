<?php
/**
 * MjRoles - Gestion centralisée des rôles MJ Member
 *
 * Cette classe centralise toutes les constantes, labels et helpers liés aux rôles.
 * Elle sert de source unique de vérité pour éviter les strings en dur dispersés.
 *
 * @package Mj\Member\Classes
 */

namespace Mj\Member\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MjRoles
 *
 * Gestion centralisée des rôles du plugin MJ Member.
 *
 * Rôles disponibles:
 * - COORDINATEUR : Responsable de la structure, accès complet
 * - ANIMATEUR    : Personnel encadrant, accès aux événements et présences
 * - BENEVOLE     : Aide ponctuelle, accès limité
 * - TUTEUR       : Parent/responsable légal d'un jeune
 * - JEUNE        : Membre principal de la MJ
 */
class MjRoles
{
    // =========================================================================
    // CONSTANTES DE RÔLES MJ MEMBER (base de données mj_members)
    // =========================================================================

    /** Rôle Coordinateur - Responsable de la MJ */
    const COORDINATEUR = 'coordinateur';

    /** Rôle Animateur - Personnel encadrant */
    const ANIMATEUR = 'animateur';

    /** Rôle Bénévole - Aide ponctuelle */
    const BENEVOLE = 'benevole';

    /** Rôle Tuteur - Parent/responsable légal */
    const TUTEUR = 'tuteur';

    /** Rôle Jeune - Membre de la MJ */
    const JEUNE = 'jeune';

    // =========================================================================
    // GROUPES DE RÔLES (pour permissions)
    // =========================================================================

    /**
     * Rôles avec accès staff (admin, gestion)
     * @return string[]
     */
    public static function getStaffRoles(): array
    {
        return [
            self::COORDINATEUR,
            self::ANIMATEUR,
        ];
    }

    /**
     * Rôles autorisés à voir les événements internes
     * @return string[]
     */
    public static function getInternalEventViewerRoles(): array
    {
        return [
            self::COORDINATEUR,
            self::ANIMATEUR,
            self::BENEVOLE,
        ];
    }

    /**
     * Rôles autorisés à gérer les présences
     * @return string[]
     */
    public static function getAttendanceManagerRoles(): array
    {
        return [
            self::COORDINATEUR,
            self::ANIMATEUR,
        ];
    }

    /**
     * Rôles avec approbation automatique des photos
     * @return string[]
     */
    public static function getPhotoAutoApproveRoles(): array
    {
        return apply_filters('mj_member_event_photo_auto_approve_roles', [
            self::ANIMATEUR,
            self::COORDINATEUR,
        ]);
    }

    /**
     * Tous les rôles disponibles
     * @return string[]
     */
    public static function getAllRoles(): array
    {
        return [
            self::JEUNE,
            self::TUTEUR,
            self::ANIMATEUR,
            self::COORDINATEUR,
            self::BENEVOLE,
        ];
    }

    /**
     * Rôles des membres (jeunes et tuteurs)
     * @return string[]
     */
    public static function getMemberRoles(): array
    {
        return [
            self::JEUNE,
            self::TUTEUR,
        ];
    }

    /**
     * Rôles du personnel (animateurs, coordinateurs, bénévoles)
     * @return string[]
     */
    public static function getPersonnelRoles(): array
    {
        return [
            self::COORDINATEUR,
            self::ANIMATEUR,
            self::BENEVOLE,
        ];
    }

    // =========================================================================
    // LABELS ET TRADUCTIONS
    // =========================================================================

    /**
     * Obtenir tous les labels des rôles (traduits)
     * @return array<string, string>
     */
    public static function getRoleLabels(): array
    {
        return [
            self::JEUNE        => __('Jeune', 'mj-member'),
            self::TUTEUR       => __('Tuteur', 'mj-member'),
            self::ANIMATEUR    => __('Animateur', 'mj-member'),
            self::COORDINATEUR => __('Coordinateur', 'mj-member'),
            self::BENEVOLE     => __('Bénévole', 'mj-member'),
        ];
    }

    /**
     * Obtenir le label d'un rôle spécifique
     * @param string $role
     * @return string
     */
    public static function getRoleLabel(string $role): string
    {
        $labels = self::getRoleLabels();
        $normalized = self::normalize($role);
        return $labels[$normalized] ?? ucfirst($role);
    }

    /**
     * Labels pour les options de sélection (select/dropdown)
     * @return array<string, string>
     */
    public static function getRoleOptions(): array
    {
        return self::getRoleLabels();
    }

    // =========================================================================
    // VALIDATION ET NORMALISATION
    // =========================================================================

    /**
     * Vérifie si un rôle est valide
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array(self::normalize($role), self::getAllRoles(), true);
    }

    /**
     * Normalise un rôle (minuscules, sans accents)
     * @param string $role
     * @return string
     */
    public static function normalize(string $role): string
    {
        $role = strtolower(trim($role));
        $role = sanitize_key($role);

        // Mapping des variantes
        $aliases = [
            'bénévole'     => self::BENEVOLE,
            'benevoles'    => self::BENEVOLE,
            'bénévoles'    => self::BENEVOLE,
            'volunteer'    => self::BENEVOLE,
            'youth'        => self::JEUNE,
            'jeunes'       => self::JEUNE,
            'parent'       => self::TUTEUR,
            'tuteurs'      => self::TUTEUR,
            'guardian'     => self::TUTEUR,
            'animateurs'   => self::ANIMATEUR,
            'animator'     => self::ANIMATEUR,
            'coordinateurs' => self::COORDINATEUR,
            'coordinator'  => self::COORDINATEUR,
            'coord'        => self::COORDINATEUR,
        ];

        return $aliases[$role] ?? $role;
    }

    /**
     * Convertit un rôle en constante sûre (pour import CSV, etc.)
     * @param string $input
     * @return string|null Retourne null si le rôle n'est pas reconnu
     */
    public static function fromInput(string $input): ?string
    {
        $normalized = self::normalize($input);
        return self::isValid($normalized) ? $normalized : null;
    }

    // =========================================================================
    // VÉRIFICATIONS DE PERMISSIONS
    // =========================================================================

    /**
     * Vérifie si le rôle est un rôle staff
     * @param string $role
     * @return bool
     */
    public static function isStaff(string $role): bool
    {
        return in_array(self::normalize($role), self::getStaffRoles(), true);
    }

    /**
     * Vérifie si le rôle est coordinateur
     * @param string $role
     * @return bool
     */
    public static function isCoordinateur(string $role): bool
    {
        return self::normalize($role) === self::COORDINATEUR;
    }

    /**
     * Vérifie si le rôle est animateur
     * @param string $role
     * @return bool
     */
    public static function isAnimateur(string $role): bool
    {
        return self::normalize($role) === self::ANIMATEUR;
    }

    /**
     * Vérifie si le rôle est animateur OU coordinateur
     * @param string $role
     * @return bool
     */
    public static function isAnimateurOrCoordinateur(string $role): bool
    {
        $normalized = self::normalize($role);
        return $normalized === self::ANIMATEUR || $normalized === self::COORDINATEUR;
    }

    /**
     * Vérifie si le rôle est bénévole
     * @param string $role
     * @return bool
     */
    public static function isBenevole(string $role): bool
    {
        return self::normalize($role) === self::BENEVOLE;
    }

    /**
     * Vérifie si le rôle est tuteur
     * @param string $role
     * @return bool
     */
    public static function isTuteur(string $role): bool
    {
        return self::normalize($role) === self::TUTEUR;
    }

    /**
     * Vérifie si le rôle est jeune
     * @param string $role
     * @return bool
     */
    public static function isJeune(string $role): bool
    {
        return self::normalize($role) === self::JEUNE;
    }

    /**
     * Vérifie si le rôle peut voir les événements internes
     * @param string $role
     * @return bool
     */
    public static function canViewInternalEvents(string $role): bool
    {
        return in_array(self::normalize($role), self::getInternalEventViewerRoles(), true);
    }

    /**
     * Vérifie si le rôle peut gérer les présences
     * @param string $role
     * @return bool
     */
    public static function canManageAttendance(string $role): bool
    {
        return in_array(self::normalize($role), self::getAttendanceManagerRoles(), true);
    }

    /**
     * Vérifie si le rôle a l'approbation automatique des photos
     * @param string $role
     * @return bool
     */
    public static function hasPhotoAutoApproval(string $role): bool
    {
        return in_array(self::normalize($role), self::getPhotoAutoApproveRoles(), true);
    }

    // =========================================================================
    // COMPATIBILITÉ AVEC MjMembers (pour migration progressive)
    // =========================================================================

    /**
     * Alias pour getAllRoles() - compatibilité avec MjMembers::getAllowedRoles()
     * @return string[]
     */
    public static function getAllowedRoles(): array
    {
        return self::getAllRoles();
    }

    // =========================================================================
    // RÔLES WORDPRESS (différents des rôles MJ Member)
    // =========================================================================

    /**
     * Rôles WordPress qui ont accès à l'admin MJ Member
     * @return string[]
     */
    public static function getWordPressAdminRoles(): array
    {
        return apply_filters('mj_member_capability_roles', [
            'administrator',
            'animateur',
            'coordinateur',
        ]);
    }

    /**
     * Rôles WordPress pour la gestion des heures
     * @return string[]
     */
    public static function getWordPressHoursRoles(): array
    {
        return apply_filters('mj_member_hours_capability_roles', [
            'administrator',
            'animateur',
            'coordinateur',
        ]);
    }

    /**
     * Rôles WordPress pour la gestion des todos
     * @return string[]
     */
    public static function getWordPressTodosRoles(): array
    {
        return apply_filters('mj_member_todos_capability_roles', [
            'administrator',
            'animateur',
            'coordinateur',
        ]);
    }

    /**
     * Rôles WordPress pour la gestion des documents
     * @return string[]
     */
    public static function getWordPressDocumentsRoles(): array
    {
        return apply_filters('mj_member_documents_capability_roles', [
            'administrator',
            'animateur',
        ]);
    }

    // =========================================================================
    // EXPORT POUR JAVASCRIPT
    // =========================================================================

    /**
     * Retourne les données de rôles pour wp_localize_script
     * @return array
     */
    public static function getJsConfig(): array
    {
        return [
            'roles' => [
                'COORDINATEUR' => self::COORDINATEUR,
                'ANIMATEUR'    => self::ANIMATEUR,
                'BENEVOLE'     => self::BENEVOLE,
                'TUTEUR'       => self::TUTEUR,
                'JEUNE'        => self::JEUNE,
            ],
            'labels' => self::getRoleLabels(),
            'staffRoles' => self::getStaffRoles(),
            'personnelRoles' => self::getPersonnelRoles(),
            'memberRoles' => self::getMemberRoles(),
        ];
    }
}
