<?php

use Mj\Member\Classes\View\EventSingle\EventSingleContextHydrator;
use Mj\Member\Classes\View\EventSingle\EventSingleViewBuilder;

if (!defined('ABSPATH')) {
    exit;
}

if (
    !class_exists('Mj_Member_Event_Single_ContextHydrator')
    && class_exists(EventSingleContextHydrator::class)
) {
    class_alias(EventSingleContextHydrator::class, 'Mj_Member_Event_Single_ContextHydrator');
}

if (!function_exists('mj_member_build_event_single_view')) {
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    function mj_member_build_event_single_view(array $context = array())
    {
        $builder = new EventSingleViewBuilder();

        return $builder->build($context);
    }
}
