<?php

namespace Mj\Member\Classes\Value;

use JsonSerializable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Représente l'état d'un membre MJ sous forme immuable.
 *
 * @internal Accessible en lecture via __get et get().
 *
 * @property-read int|null $id
 * @property-read string|null $first_name
 * @property-read string|null $last_name
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $birth_date
 * @property-read string|null $role
 * @property-read int|null $guardian_id
 * @property-read int|null $is_autonomous
 * @property-read int|null $is_volunteer
 * @property-read int|null $requires_payment
 * @property-read string|null $address
 * @property-read string|null $city
 * @property-read string|null $postal_code
 * @property-read string|null $country
 * @property-read string|null $school
 * @property-read string|null $birth_country
 * @property-read string|null $nationality
 * @property-read string|null $why_mj
 * @property-read string|null $how_mj
 * @property-read string|null $status
 * @property-read string|null $description_courte
 * @property-read string|null $description_longue
 * @property-read int|null $wp_user_id
 * @property-read string|null $date_inscription
 * @property-read string|null $date_last_payement
 * @property-read string|null $membership_paid_year
 * @property-read string|null $membership_paid_reference
 * @property-read string|null $membership_number
 * @property-read string|null $membership_card_url
 * @property-read string|null $card_access_key
 * @property-read int|null $newsletter_opt_in
 * @property-read int|null $sms_opt_in
 * @property-read int|null $whatsapp_opt_in
 * @property-read string|null $preferences
 * @property-read string|null $preferences_payload
 * @property-read int|null $avatar_id
 * @property-read string|null $nickname
 * @property-read string|null $created_at
 * @property-read string|null $updated_at
 * @property-read int|null $xp_total
 */
final class MemberData implements JsonSerializable {
    /**
     * @var array<int,string>
     */
    private const KNOWN_KEYS = array(
        'id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'role',
        'guardian_id',
        'is_autonomous',
        'is_volunteer',
        'requires_payment',
        'address',
        'city',
        'postal_code',
        'country',
        'school',
        'birth_country',
        'nationality',
        'why_mj',
        'how_mj',
        'status',
        'description_courte',
        'description_longue',
        'wp_user_id',
        'date_inscription',
        'date_last_payement',
        'membership_paid_year',
        'membership_paid_reference',
        'membership_number',
        'membership_card_url',
        'card_access_key',
        'newsletter_opt_in',
        'sms_opt_in',
        'whatsapp_opt_in',
        'preferences',
        'preferences_payload',
        'avatar_id',
        'nickname',
        'created_at',
        'updated_at',
        'gender',
        'age_category',
        'national_register',
        'wp_user_login',
        'wp_user_email',
        'wp_user',
        'last_login_at',
        'notes',
        'extra_fields',
        'xp_total',
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
