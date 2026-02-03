<?php

namespace Mj\Member\Admin\Locations;

if (!defined('ABSPATH')) {
    exit;
}

final class LocationsActionState
{
    /** @var string|null */
    private $forcedAction = null;

    /** @var int|null */
    private $forcedLocationId = null;

    /** @var array<int,string> */
    private $errors = array();

    /** @var array<int,string> */
    private $formErrors = array();

    /** @var array<string,mixed> */
    private $formValues = array();

    /** @var bool */
    private $hasCustomFormValues = false;

    /** @var string|null */
    private $mapEmbedUrl = null;

    /** @var bool */
    private $hasCustomMapEmbed = false;

    public static function create(): self
    {
        return new self();
    }

    public function forceAction(string $action, int $locationId = 0): void
    {
        $this->forcedAction = $action;
        $this->forcedLocationId = $locationId > 0 ? $locationId : null;
    }

    public function addError(string $message): void
    {
        $message = trim(wp_strip_all_tags($message));
        if ($message === '') {
            return;
        }
        $this->errors[] = $message;
    }

    public function appendFormError(string $message): void
    {
        $message = trim(wp_strip_all_tags($message));
        if ($message === '') {
            return;
        }
        $this->formErrors[] = $message;
    }

    /**
     * @param array<int,string> $messages
     */
    public function mergeFormErrors(array $messages): void
    {
        foreach ($messages as $message) {
            $this->appendFormError($message);
        }
    }

    /**
     * @param array<string,mixed> $values
     */
    public function setFormValues(array $values): void
    {
        $this->formValues = $values;
        $this->hasCustomFormValues = true;
    }

    public function setMapEmbedUrl(?string $url): void
    {
        $this->mapEmbedUrl = $url;
        $this->hasCustomMapEmbed = true;
    }

    public function hasForcedAction(): bool
    {
        return $this->forcedAction !== null;
    }

    public function getForcedAction(): ?string
    {
        return $this->forcedAction;
    }

    public function getForcedLocationId(): ?int
    {
        return $this->forcedLocationId;
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

    public function hasCustomFormValues(): bool
    {
        return $this->hasCustomFormValues;
    }

    public function getMapEmbedUrl(): ?string
    {
        return $this->mapEmbedUrl;
    }

    public function hasCustomMapEmbed(): bool
    {
        return $this->hasCustomMapEmbed;
    }
}
