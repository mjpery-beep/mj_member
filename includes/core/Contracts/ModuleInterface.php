<?php

namespace Mj\Member\Core\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrat pour les modules fonctionnels du plugin.
 *
 * Chaque module doit implémenter cette interface pour être
 * enregistré par Bootstrap et connecter ses hooks WordPress.
 */
interface ModuleInterface
{
    /**
     * Enregistre les hooks WordPress du module (actions, filtres, shortcodes).
     * Appelé une seule fois lors du chargement du plugin.
     */
    public function register(): void;
}
