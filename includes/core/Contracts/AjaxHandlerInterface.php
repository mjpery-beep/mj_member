<?php

namespace Mj\Member\Core\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrat pour les contrôleurs AJAX du plugin.
 *
 * Chaque contrôleur AJAX doit implémenter cette interface
 * pour enregistrer ses actions wp_ajax_* de façon uniforme.
 */
interface AjaxHandlerInterface
{
    /**
     * Enregistre les actions wp_ajax_* et wp_ajax_nopriv_* du contrôleur.
     * Appelé lors du chargement du plugin.
     */
    public function registerHooks(): void;
}
