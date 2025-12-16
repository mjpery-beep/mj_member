<?php

namespace Mj\Member\Classes\Front;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe abstraite Controller - Base pour tous les contrôleurs front-end
 * 
 * Centralise la logique métier commune:
 * - Gestion du contexte legacy
 * - Pattern MVC standardisé (Model, View, Localization)
 * - Méthodes utilitaires pour normalisation de données
 * - Gestion du cycle de vie (build, teardown)
 */
abstract class Controller
{
    /**
     * @var array<string, mixed>
     */
    protected $context;

    /**
     * @var array<string, mixed>
     */
    protected $payload;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(array $context = array())
    {
        $this->context = $context;
        $this->payload = array();
    }

    /**
     * Point d'entrée principal - construit le payload complet
     * 
     * @return array<string, mixed> Payload avec keys: legacy, model, view, localization
     */
    public function build(): array
    {
        $legacy = $this->buildLegacyPayload();
        $model = $this->buildModel($legacy);
        $view = $this->buildView($legacy, $model);
        $localization = $this->buildLocalization($legacy, $model);

        $this->payload = array(
            'legacy' => $legacy,
            'model' => $model,
            'view' => $view,
            'localization' => $localization,
        );

        $this->enqueueAssets();

        return $this->payload;
    }

    /**
     * Construit le payload legacy à partir du contexte
     * Par défaut, retourne le contexte tel quel avec defaults
     * 
     * @return array<string, mixed>
     */
    protected function buildLegacyPayload(): array
    {
        return !empty($this->context) && is_array($this->context)
            ? $this->context
            : array();
    }

    /**
     * Construit le modèle métier normalisé
     * À implémenter dans les classes enfants
     * 
     * @param array<string, mixed> $legacy
     * @return array<string, mixed>
     */
    abstract protected function buildModel(array $legacy): array;

    /**
     * Construit la couche de vue (page + partials)
     * À implémenter dans les classes enfants
     * 
     * @param array<string, mixed> $legacy
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    abstract protected function buildView(array $legacy, array $model): array;

    /**
     * Construit les données de localisation/i18n
     * À implémenter dans les classes enfants
     * 
     * @param array<string, mixed> $legacy
     * @param array<string, mixed> $model
     * @return array<string, mixed>
     */
    abstract protected function buildLocalization(array $legacy, array $model): array;

    /**
     * Enregistre les assets (scripts/styles) nécessaires
     * À implémenter dans les classes enfants si besoin
     */
    protected function enqueueAssets(): void
    {
        // Par défaut: aucune action
    }

    /**
     * Nettoyage après utilisation
     * À surcharger dans les classes enfants si besoin
     */
    public function teardown(): void
    {
        // Par défaut: aucune action
    }

    // =====================================================================
    // MÉTHODES UTILITAIRES POUR NORMALISATION DE DONNÉES
    // =====================================================================

    /**
     * Normalise une chaîne de caractères
     * 
     * @param mixed $value
     * @param string $default
     * @return string
     */
    protected function normalizeString($value, string $default = ''): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Normalise un entier
     * 
     * @param mixed $value
     * @param int $default
     * @return int
     */
    protected function normalizeInt($value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Normalise un booléen
     * 
     * @param mixed $value
     * @param bool $default
     * @return bool
     */
    protected function normalizeBool($value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return !empty($value);
    }

    /**
     * Normalise un tableau
     * 
     * @param mixed $value
     * @param array<mixed> $default
     * @return array<mixed>
     */
    protected function normalizeArray($value, array $default = array()): array
    {
        if (!is_array($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Extrait une valeur d'un tableau avec fallback
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function extractValue(array $array, string $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Extrait une chaîne d'un tableau avec fallback multiple
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * @param string $fallbackKey
     * @param string $default
     * @return string
     */
    protected function extractString(array $array, string $key, string $fallbackKey = '', string $default = ''): string
    {
        if (isset($array[$key]) && $array[$key] !== '') {
            return (string) $array[$key];
        }

        if ($fallbackKey !== '' && isset($array[$fallbackKey]) && $array[$fallbackKey] !== '') {
            return (string) $array[$fallbackKey];
        }

        return $default;
    }

    /**
     * Extrait un entier d'un tableau avec fallback
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * @param int $default
     * @return int
     */
    protected function extractInt(array $array, string $key, int $default = 0): int
    {
        return isset($array[$key]) ? (int) $array[$key] : $default;
    }

    /**
     * Extrait un booléen d'un tableau
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * @param bool $default
     * @return bool
     */
    protected function extractBool(array $array, string $key, bool $default = false): bool
    {
        return isset($array[$key]) ? !empty($array[$key]) : $default;
    }

    /**
     * Extrait un tableau d'un tableau
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * @param array<mixed> $default
     * @return array<mixed>
     */
    protected function extractArray(array $array, string $key, array $default = array()): array
    {
        if (!isset($array[$key])) {
            return $default;
        }

        return is_array($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Normalise une liste d'items en appliquant une callback sur chaque item
     * 
     * @param array<mixed> $items
     * @param callable $normalizer
     * @return array<mixed>
     */
    protected function normalizeList(array $items, callable $normalizer): array
    {
        $normalized = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalizedItem = $normalizer($item);
            if ($normalizedItem !== null) {
                $normalized[] = $normalizedItem;
            }
        }

        return $normalized;
    }

    /**
     * Fusionne des attributs HTML en chaîne
     * 
     * @param array<string, mixed> $attributes
     * @return string
     */
    protected function buildAttributesString(array $attributes): string
    {
        $tokens = array();

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $tokens[] = sprintf('%s="%s"', esc_attr($name), esc_attr((string) $value));
        }

        return !empty($tokens) ? ' ' . implode(' ', $tokens) : '';
    }

    /**
     * Construit une notice structurée
     * 
     * @param string $type 'info'|'success'|'warning'|'error'
     * @param string $message
     * @return array<string, string>|null
     */
    protected function buildNotice(string $type, string $message): ?array
    {
        if ($message === '') {
            return null;
        }

        return array(
            'type' => $type,
            'message' => $message,
        );
    }

    /**
     * Récupère le payload construit
     * 
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Récupère le contexte
     * 
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
