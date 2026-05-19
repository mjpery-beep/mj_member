<?php

namespace Mj\Member\Core\Ajax\Admin;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class TodosController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        // Les actions AJAX d'administration pour les todos pourront être ajoutées ici si nécessaire.
    }
}

// d’
