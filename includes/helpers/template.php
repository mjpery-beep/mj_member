<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mj_member_event_build_attr_string')) {
    /**
     * Transforme un tableau d'attributs HTML en chaîne sécurisée.
     *
     * @param array<string, mixed> $attributes
     * @return string
     */
    function mj_member_event_build_attr_string(array $attributes = array()): string
    {
        if (empty($attributes)) {
            return '';
        }

        $tokens = array();
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $tokens[] = sprintf(' %s="%s"', esc_attr($name), esc_attr((string) $value));
        }

        return implode('', $tokens);
    }
}