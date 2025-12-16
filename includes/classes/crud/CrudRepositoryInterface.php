<?php

namespace Mj\Member\Classes\Crud;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrat minimal pour les classes CRUD du module MJ Member.
 */
interface CrudRepositoryInterface
{
    /**
     * @param array<string,mixed> $args
     * @return array<int,object>
     */
    public static function get_all(array $args = array());

    /**
     * @param array<string,mixed> $args
     */
    public static function count(array $args = array());

    /**
     * @param mixed $data
     * @return int|WP_Error
     */
    public static function create($data);

    /**
     * @param int $id
     * @param mixed $data
     * @return true|WP_Error
     */
    public static function update($id, $data);

    /**
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete($id);
}
