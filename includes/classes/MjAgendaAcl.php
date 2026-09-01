<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjMembers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Access-control matrix for the unified agenda widget.
 *
 * Stored in a single option ({@see MjAgendaAcl::OPTION}) namespaced by domain so
 * the same store can serve other modules later:
 *
 *   [ 'agenda' => [ <role> => [ <layer> => [ <action>, ... ] ] ] ]
 *
 * The server side ({@see MjAgendaAcl::assert()}) is authoritative. The client
 * only receives a compact snapshot ({@see MjAgendaAcl::snapshotForUser()}) to
 * grey out UI it may not use.
 */
class MjAgendaAcl
{
    public const OPTION = 'mj_member_acl';
    public const DOMAIN = 'agenda';

    public const LAYERS = array(
        'event_occurrences',
        'todos',
        'leave_requests',
        'work_schedules',
        'worked_hours',
        'requests',
        'internal_notes',
    );

    public const ACTIONS = array(
        'view',
        'view_others',
        'create',
        'edit_own',
        'edit_others',
        'move',
        'delete',
    );

    /**
     * @return string[]
     */
    public static function roles(): array
    {
        return array(
            MjRoles::COORDINATEUR,
            MjRoles::ANIMATEUR,
            MjRoles::BENEVOLE,
            MjRoles::JEUNE,
        );
    }

    /**
     * Factory defaults. Deliberately conservative for non-coordinators.
     *
     * @return array<string,array<string,string[]>>
     */
    public static function defaults(): array
    {
        $all = self::ACTIONS;
        $view = array('view', 'view_others');

        $coordinator = array();
        foreach (self::LAYERS as $layer) {
            $coordinator[$layer] = $all;
        }

        $animator = array(
            'event_occurrences' => array('view', 'view_others', 'edit_own'),
            'todos' => array('view', 'view_others', 'create', 'edit_own', 'move', 'delete'),
            'leave_requests' => $view,
            'work_schedules' => $view,
            'worked_hours' => array('view', 'view_others', 'create', 'edit_own', 'move', 'delete'),
            'requests' => array('view', 'view_others', 'create', 'edit_own'),
            'internal_notes' => array('view', 'view_others', 'create', 'edit_own', 'move', 'delete'),
        );

        $volunteer = array(
            'event_occurrences' => array('view'),
            'todos' => array('view'),
            'leave_requests' => array(),
            'work_schedules' => array(),
            'worked_hours' => array('view', 'create', 'edit_own'),
            'requests' => array('view', 'create', 'edit_own'),
            'internal_notes' => array('view'),
        );

        $young = array(
            'event_occurrences' => array('view'),
            'todos' => array(),
            'leave_requests' => array(),
            'work_schedules' => array(),
            'worked_hours' => array(),
            'requests' => array(),
            'internal_notes' => array(),
        );

        return array(
            MjRoles::COORDINATEUR => $coordinator,
            MjRoles::ANIMATEUR => $animator,
            MjRoles::BENEVOLE => $volunteer,
            MjRoles::JEUNE => $young,
        );
    }

    /**
     * Full stored matrix for the agenda domain, merged over defaults so newly
     * added layers/roles always have an entry.
     *
     * @return array<string,array<string,string[]>>
     */
    public static function all(): array
    {
        $store = get_option(self::OPTION, array());
        $stored = (is_array($store) && isset($store[self::DOMAIN]) && is_array($store[self::DOMAIN]))
            ? $store[self::DOMAIN]
            : array();

        $defaults = self::defaults();
        $merged = array();

        foreach (self::roles() as $role) {
            $roleStored = isset($stored[$role]) && is_array($stored[$role]) ? $stored[$role] : array();
            $roleDefault = $defaults[$role] ?? array();
            $merged[$role] = array();

            foreach (self::LAYERS as $layer) {
                if (isset($roleStored[$layer]) && is_array($roleStored[$layer])) {
                    $merged[$role][$layer] = array_values(array_intersect(self::ACTIONS, $roleStored[$layer]));
                } else {
                    $merged[$role][$layer] = $roleDefault[$layer] ?? array();
                }
            }
        }

        return $merged;
    }

    /**
     * Persist a sanitised matrix for the agenda domain.
     *
     * @param array<string,array<string,mixed>> $matrix
     */
    public static function save(array $matrix): void
    {
        $clean = array();
        foreach (self::roles() as $role) {
            $clean[$role] = array();
            $roleIn = isset($matrix[$role]) && is_array($matrix[$role]) ? $matrix[$role] : array();
            foreach (self::LAYERS as $layer) {
                $layerIn = isset($roleIn[$layer]) && is_array($roleIn[$layer]) ? $roleIn[$layer] : array();
                $clean[$role][$layer] = array_values(array_intersect(self::ACTIONS, array_map('strval', $layerIn)));
            }
        }

        $store = get_option(self::OPTION, array());
        if (!is_array($store)) {
            $store = array();
        }
        $store[self::DOMAIN] = $clean;
        update_option(self::OPTION, $store);
    }

    public static function reset(): void
    {
        $store = get_option(self::OPTION, array());
        if (!is_array($store)) {
            $store = array();
        }
        $store[self::DOMAIN] = self::defaults();
        update_option(self::OPTION, $store);
    }

    /**
     * Seed factory defaults once (mirrors other mj_member_seed_default_* hooks).
     */
    public static function seedDefaults(): void
    {
        $store = get_option(self::OPTION, array());
        if (is_array($store) && isset($store[self::DOMAIN]) && is_array($store[self::DOMAIN]) && !empty($store[self::DOMAIN])) {
            return;
        }

        if (!is_array($store)) {
            $store = array();
        }
        $store[self::DOMAIN] = self::defaults();
        update_option(self::OPTION, $store);
    }

    /**
     * Normalise a raw MJ role, treating anything unknown but capability-bearing
     * as coordinator, and everything else as 'jeune'.
     */
    public static function normalizeRole(?string $role): string
    {
        $role = is_string($role) ? strtolower(trim($role)) : '';
        if (in_array($role, self::roles(), true)) {
            return $role;
        }

        if (function_exists('current_user_can') && current_user_can(Config::capability())) {
            return MjRoles::COORDINATEUR;
        }

        return MjRoles::JEUNE;
    }

    /**
     * Is <role> allowed to <action> on <layer>?
     *
     * For edit/move/delete, pass $isOwner so 'edit_own' vs 'edit_others' can be
     * resolved. 'move' and 'delete' are additionally gated by the matching edit
     * grant (edit_own when owner, edit_others otherwise).
     */
    public static function can(string $role, string $layer, string $action, bool $isOwner = false): bool
    {
        $role = self::normalizeRole($role);
        if (!in_array($layer, self::LAYERS, true) || !in_array($action, self::ACTIONS, true)) {
            return false;
        }

        $grants = self::all()[$role][$layer] ?? array();

        switch ($action) {
            case 'view':
                return in_array('view', $grants, true);
            case 'view_others':
                return in_array('view_others', $grants, true);
            case 'create':
                return in_array('create', $grants, true);
            case 'edit_own':
                return in_array('edit_own', $grants, true);
            case 'edit_others':
                return in_array('edit_others', $grants, true);
            case 'move':
            case 'delete':
                if (!in_array($action, $grants, true)) {
                    return false;
                }
                return $isOwner
                    ? in_array('edit_own', $grants, true)
                    : in_array('edit_others', $grants, true);
        }

        return false;
    }

    /**
     * Send a 403 JSON error unless the current role may perform the action.
     */
    public static function assert(string $role, string $layer, string $action, bool $isOwner = false): void
    {
        if (!self::can($role, $layer, $action, $isOwner)) {
            wp_send_json_error(
                array('message' => __('Action non autorisée pour votre rôle.', 'mj-member')),
                403
            );
        }
    }

    /**
     * Compact client mirror. UI-only — never trusted for enforcement.
     *
     * @return array{role:string,layers:array<string,string[]>}
     */
    public static function snapshotForUser(int $userId): array
    {
        $role = MjRoles::JEUNE;
        if ($userId > 0 && class_exists(MjMembers::class)) {
            $member = MjMembers::getByWpUserId($userId);
            if ($member) {
                $arr = method_exists($member, 'toArray') ? $member->toArray() : (array) $member;
                $role = self::normalizeRole($arr['role'] ?? '');
            }
        }
        $role = self::normalizeRole($role);

        return array(
            'role' => $role,
            'layers' => self::all()[$role] ?? array(),
        );
    }
}
