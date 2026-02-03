<?php

namespace Mj\Member\Admin\Locations;

use Mj\Member\Classes\Value\EventLocationData;

if (!defined('ABSPATH')) {
    exit;
}

final class LocationsPageContext
{
    /** @var string */
    private $action;

    /** @var int */
    private $locationId;

    /** @var array{message:string,type:string}|null */
    private $notice;

    /** @var array<int,string> */
    private $errors;

    /** @var array<int,string> */
    private $formErrors;

    /** @var array<string,mixed> */
    private $formValues;

    /** @var string */
    private $mapEmbedUrl;

    /** @var array<int,mixed> */
    private $locations;

    /** @var string */
    private $addUrl;

    /** @var string */
    private $listUrl;

    /** @var array<string,mixed> */
    private $rawQuery;

    /**
     * @param array<int,string> $errors
     * @param array<int,string> $formErrors
     * @param array<string,mixed> $formValues
     * @param array<int,mixed> $locations
     * @param array<string,mixed> $rawQuery
     */
    private function __construct(
        string $action,
        int $locationId,
        ?array $notice,
        array $errors,
        array $formErrors,
        array $formValues,
        string $mapEmbedUrl,
        array $locations,
        string $addUrl,
        string $listUrl,
        array $rawQuery
    ) {
        $this->action = $action;
        $this->locationId = $locationId;
        $this->notice = $notice;
        $this->errors = $errors;
        $this->formErrors = $formErrors;
        $this->formValues = $formValues;
        $this->mapEmbedUrl = $mapEmbedUrl;
        $this->locations = $locations;
        $this->addUrl = $addUrl;
        $this->listUrl = $listUrl;
        $this->rawQuery = $rawQuery;
    }

    /**
     * @param array<string,mixed> $queryVars
     */
    public static function build(LocationsActionState $actionState, array $queryVars): self
    {
        $rawQuery = $queryVars;

        $action = 'list';
        if (isset($queryVars['action'])) {
            $action = self::sanitizeAction($queryVars['action']);
        }
        if ($actionState->hasForcedAction()) {
            $forced = self::sanitizeAction($actionState->getForcedAction());
            if ($forced !== '') {
                $action = $forced;
            }
        }
        if ($action === '') {
            $action = 'list';
        }

        $locationId = isset($queryVars['location']) ? (int) $queryVars['location'] : 0;
        if ($actionState->getForcedLocationId() !== null) {
            $locationId = (int) $actionState->getForcedLocationId();
        }

        $notice = self::extractNotice($queryVars);

        $errors = $actionState->getErrors();
        $formErrors = $actionState->getFormErrors();

        $defaultValues = \MjEventLocations::get_default_values();
        $formValues = $defaultValues;
        $mapEmbedUrl = '';

        if ($action === 'edit' && $locationId > 0) {
            $existing = \MjEventLocations::find($locationId);
            if (!$existing) {
                $errors[] = 'Lieu introuvable.';
                $action = 'list';
                $locationId = 0;
            } else {
                $existingValues = self::normalizeLocation($existing);
                $formValues = array_merge($formValues, $existingValues);
                $mapEmbedUrl = \MjEventLocations::build_map_embed_src($existingValues);
            }
        }

        if ($actionState->hasCustomFormValues()) {
            $customValues = $actionState->getFormValues();
            $formValues = array_merge($formValues, $customValues);
        }

        if (!isset($formValues['notes']) || $formValues['notes'] === null) {
            $formValues['notes'] = '';
        }

        if ($actionState->hasCustomMapEmbed()) {
            $mapEmbedRaw = $actionState->getMapEmbedUrl();
            $mapEmbedUrl = $mapEmbedRaw !== null ? (string) $mapEmbedRaw : '';
        }

        if ($mapEmbedUrl === '') {
            $mapEmbedUrl = \MjEventLocations::build_map_embed_src($formValues);
        }

        $locations = \MjEventLocations::get_all();

        $addUrl = add_query_arg(array('page' => 'mj_locations', 'action' => 'add'), admin_url('admin.php'));
        $listUrl = add_query_arg(array('page' => 'mj_locations'), admin_url('admin.php'));

        return new self(
            $action,
            $locationId,
            $notice,
            $errors,
            $formErrors,
            $formValues,
            $mapEmbedUrl,
            $locations,
            $addUrl,
            $listUrl,
            $rawQuery
        );
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getLocationId(): int
    {
        return $this->locationId;
    }

    /**
     * @return array{message:string,type:string}|null
     */
    public function getNotice(): ?array
    {
        return $this->notice;
    }

    /**
     * @return array<int,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int,string>
     */
    public function getFormErrors(): array
    {
        return $this->formErrors;
    }

    /**
     * @return array<string,mixed>
     */
    public function getFormValues(): array
    {
        return $this->formValues;
    }

    public function getMapEmbedUrl(): string
    {
        return $this->mapEmbedUrl;
    }

    /**
     * @return array<int,mixed>
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    public function getAddUrl(): string
    {
        return $this->addUrl;
    }

    public function getListUrl(): string
    {
        return $this->listUrl;
    }

    public function isFormAction(): bool
    {
        return in_array($this->action, array('add', 'edit'), true);
    }

    public function isEditAction(): bool
    {
        return $this->action === 'edit';
    }

    /**
     * @return array<string,mixed>
     */
    public function getRawQuery(): array
    {
        return $this->rawQuery;
    }

    private static function sanitizeAction($value): string
    {
        $action = sanitize_key(is_scalar($value) ? (string) $value : '');
        if (!in_array($action, array('list', 'add', 'edit'), true)) {
            return $action === '' ? '' : 'list';
        }
        return $action;
    }

    /**
     * @param array<string,mixed> $queryVars
     * @return array{message:string,type:string}|null
     */
    private static function extractNotice(array $queryVars): ?array
    {
        $noticeRaw = isset($queryVars['mj_locations_message']) ? wp_unslash($queryVars['mj_locations_message']) : '';
        if ($noticeRaw === '') {
            return null;
        }
        $message = sanitize_text_field(rawurldecode($noticeRaw));
        if ($message === '') {
            return null;
        }
        $type = isset($queryVars['mj_locations_message_type']) ? sanitize_key(wp_unslash($queryVars['mj_locations_message_type'])) : 'success';
        $type = in_array($type, array('success', 'error', 'warning', 'info'), true) ? $type : 'success';

        return array(
            'message' => $message,
            'type' => $type,
        );
    }

    /**
     * @param mixed $location
     * @return array<string,mixed>
     */
    private static function normalizeLocation($location): array
    {
        if ($location instanceof EventLocationData) {
            return $location->toArray();
        }
        if (is_array($location)) {
            return $location;
        }
        if (is_object($location)) {
            return get_object_vars($location);
        }
        return array();
    }
}
