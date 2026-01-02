<?php

namespace Mj\Member\Classes\Value;

use JsonSerializable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Représente un lieu d'événement en lecture seule.
 */
final class EventLocationData implements JsonSerializable
{
    /**
     * @var array<int,string>
     */
    private const KNOWN_KEYS = array(
        'id',
        'name',
        'slug',
        'address_line',
        'postal_code',
        'city',
        'country',
        'icon',
        'latitude',
        'longitude',
        'map_query',
        'cover_id',
        'notes',
        'created_at',
        'updated_at',
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
    private function __construct(array $attributes, array $extras)
    {
        $this->attributes = $attributes;
        $this->extras = $extras;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
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
    public static function fromRow($row): self
    {
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
    public function toArray(bool $includeExtras = true): array
    {
        if ($includeExtras) {
            return $this->attributes + $this->extras;
        }

        return $this->attributes;
    }

    /**
     * @param array<string,mixed> $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->attributes, $this->extras, $changes));
    }

    /**
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->extras)) {
            return $this->extras[$key];
        }

        return $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes) || array_key_exists($key, $this->extras);
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function normalizeKeys(array $data): array
    {
        $normalized = array();

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
