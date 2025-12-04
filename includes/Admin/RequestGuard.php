<?php

namespace Mj\Member\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper centralisant la vérification des capacités et des nonces
 * pour les écrans d’administration MJ Member.
 */
final class RequestGuard
{
    /**
     * Vérifie si l’utilisateur courant possède la capacité donnée.
     */
    public static function ensureCapability(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Vérifie la capacité et met fin à l’exécution en cas d’échec.
     */
    public static function ensureCapabilityOrDie(string $capability, string $message = ''): void
    {
        if (self::ensureCapability($capability)) {
            return;
        }

        $message = $message !== '' ? $message : esc_html__('Accès refusé.', 'mj-member');
        wp_die($message);
    }

    /**
     * Valide un nonce (déjà nettoyé) contre l’action WordPress fournie.
     */
    public static function verifyNonce(string $nonceValue, string $action): bool
    {
        if ($nonceValue === '') {
            return false;
        }

        return (bool) wp_verify_nonce($nonceValue, $action);
    }

    /**
     * Récupère et nettoie une valeur de nonce depuis un tableau source.
     */
    public static function readNonce(array $source, string $key): string
    {
        if (!isset($source[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($source[$key]));
    }
}
