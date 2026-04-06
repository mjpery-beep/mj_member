<?php

namespace Mj\Member\Core\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrat pour les pages d'administration du plugin.
 *
 * Chaque page admin doit implémenter cette interface
 * pour standardiser le rendu HTML des écrans WordPress.
 */
interface AdminPageInterface
{
    /**
     * Affiche le contenu HTML de la page d'administration.
     * Appelé par le callback passé à add_menu_page() ou add_submenu_page().
     */
    public function render(): void;
}
