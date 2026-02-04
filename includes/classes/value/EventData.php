<?php

namespace Mj\Member\Classes\Value;

use JsonSerializable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Représente un enregistrement d'événement en lecture seule.
 *
 * @internal Les propriétés exposées via __get correspondent aux colonnes connues.
 *
 * @property-read int|null $id
 * @property-read string|null $title
 * @property-read string|null $slug
 * @property-read string|null $status
 * @property-read string|null $type
 * @property-read string|null $accent_color
 * @property-read string|null $emoji
 * @property-read int|null $cover_id
 * @property-read int|null $location_id
 * @property-read int|null $animateur_id
 * @property-read int|null $article_id
 * @property-read int|null $allow_guardian_registration
 * @property-read string|null $description
 * @property-read string|null $registration_document
 * @property-read int|null $age_min
 * @property-read int|null $age_max
 * @property-read string|null $date_debut
 * @property-read string|null $date_fin
 * @property-read string|null $date_fin_inscription
 * @property-read float|null $prix
 * @property-read string|null $schedule_mode
 * @property-read string|null $schedule_payload
 * @property-read string|null $schedule_timezone
 * @property-read string|null $recurrence_until
 * @property-read string|null $recurrence_frequency
 * @property-read string|null $recurrence_payload
 * @property-read int|null $capacity_total
 * @property-read int|null $capacity_waitlist
 * @property-read int|null $capacity_notify_threshold
 * @property-read int|null $capacity_notified
 * @property-read int|null $free_participation
 * @property-read string|null $created_at
 * @property-read string|null $updated_at
 */
final class EventData implements JsonSerializable {
    /**
     * @var array<int,string>
     */
    private const KNOWN_KEYS = array(
        'id',
        'title',
        'slug',
        'status',
        'type',
        'accent_color',
        'emoji',
        'cover_id',
        'location_id',
        'animateur_id',
        'article_id',
        'allow_guardian_registration',
        'requires_validation',
        'free_participation',
        'description',
        'registration_document',
        'age_min',
        'age_max',
        'date_debut',
        'date_fin',
        'date_fin_inscription',
        'prix',
        'schedule_mode',
        'schedule_payload',
        'occurrence_selection_mode',
        'schedule_timezone',
        'recurrence_until',
        'recurrence_frequency',
        'recurrence_payload',
        'capacity_total',
        'capacity_waitlist',
        'capacity_notify_threshold',
        'capacity_notified',
        'created_at',
        'updated_at',
        'slug_reference',
        'registration_payload',
        'closing_reason',
        'closing_message',
        'primary_contact_id',
        'secondary_contact_id',
        'wp_post_id',
    );

    /**
     * @var array<string,mixed>
     */
    private $attributes;

    /**
     * @var array<string,mixed>
     */
    private $extras;

    /**
     * @param array<string,mixed> $attributes
     * @param array<string,mixed> $extras
     */
    private function __construct(array $attributes, array $extras) {
        $this->attributes = $attributes;
        $this->extras = $extras;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self {
        $normalized = self::normalizeKeys($data);
        $attributes = array();
        $extras = array();

        foreach ($normalized as $key => $value) {
            if (in_array($key, self::KNOWN_KEYS, true)) {
                $attributes[$key] = $value;
            } else {
                $extras[$key] = $value;
            }
        }

        return new self($attributes, $extras);
    }

    /**
     * @param object|array<string,mixed>|self|null $row
     */
    public static function fromRow($row): self {
        if ($row instanceof self) {
            return $row;
        }

        if (is_object($row)) {
            return self::fromArray(get_object_vars($row));
        }

        if (is_array($row)) {
            return self::fromArray($row);
        }

        return new self(array(), array());
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(bool $includeExtras = true): array {
        if ($includeExtras) {
            return $this->attributes + $this->extras;
        }

        return $this->attributes;
    }

    /**
     * @return array<string,mixed>
     */
    public function toDatabaseArray(): array {
        return $this->attributes;
    }

    /**
     * @param array<string,mixed> $changes
     */
    public function with(array $changes): self {
        return self::fromArray(array_merge($this->attributes, $this->extras, $changes));
    }

    /**
     * @return mixed
     */
    public function get(string $key, $default = null) {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->extras)) {
            return $this->extras[$key];
        }

        return $default;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->attributes) || array_key_exists($key, $this->extras);
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array {
        return $this->toArray();
    }

    public function __get(string $name) {
        return $this->get($name);
    }

    public function __isset(string $name): bool {
        return $this->has($name);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function normalizeKeys(array $data): array {
        $normalized = array();
        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
