<?php

namespace Mj\Member\Classes\Front;

use Mj\Member\Classes\Crud\MjEventLocations;
use Mj\Member\Classes\Crud\MjEvents;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Forms\EventFormDataMapper;
use Mj\Member\Classes\Forms\EventFormFactory;
use Mj\Member\Classes\Forms\EventFormOptionsBuilder;
use Mj\Member\Classes\MjRoles;
use Symfony\Component\HttpFoundation\Request;

if (!defined('ABSPATH')) {
    exit;
}

final class EventFormController
{
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function buildContext(array $args = array()): array
    {
        $defaults = MjEvents::get_default_values();
        $statusLabels = MjEvents::get_status_labels();
        $typeLabels = MjEvents::get_type_labels();
        $locations = MjEventLocations::get_all(array('orderby' => 'name'));
        if (is_wp_error($locations)) {
            $locations = array();
        }

        $animateurs = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', array('role' => MjRoles::ANIMATEUR));
        if (!is_array($animateurs)) {
            $animateurs = array();
        }
        $volunteers = MjMembers::getAll(0, 0, 'last_name', 'ASC', '', array('is_volunteer' => 1));
        if (!is_array($volunteers)) {
            $volunteers = array();
        }

        $scheduleWeekdays = array(
            'monday'    => __('Lundi', 'mj-member'),
            'tuesday'   => __('Mardi', 'mj-member'),
            'wednesday' => __('Mercredi', 'mj-member'),
            'thursday'  => __('Jeudi', 'mj-member'),
            'friday'    => __('Vendredi', 'mj-member'),
            'saturday'  => __('Samedi', 'mj-member'),
            'sunday'    => __('Dimanche', 'mj-member'),
        );
        $scheduleMonthOrdinals = array(
            'first'  => __('1er', 'mj-member'),
            'second' => __('2e', 'mj-member'),
            'third'  => __('3e', 'mj-member'),
            'fourth' => __('4e', 'mj-member'),
            'last'   => __('Dernier', 'mj-member'),
        );

        $typeColors = method_exists(MjEvents::class, 'get_type_colors') ? MjEvents::get_type_colors() : array();

        $formValues = $defaults;
        $formValues['animateur_ids'] = array();
        $formValues['volunteer_ids'] = array();
        $formValues['schedule_mode'] = isset($defaults['schedule_mode']) ? $defaults['schedule_mode'] : 'fixed';
        $formValues['schedule_series_items'] = array();
        $formValues['schedule_recurring_weekdays'] = array();
        $formValues['schedule_recurring_month_ordinal'] = 'first';
        $formValues['schedule_recurring_month_weekday'] = 'saturday';

        $formDefaults = EventFormDataMapper::fromValues($formValues);

        $factory = new EventFormFactory();
        $options = EventFormOptionsBuilder::build(array(
            'status_labels' => $statusLabels,
            'type_labels' => $typeLabels,
            'type_colors' => $typeColors,
            'current_type' => isset($formValues['type']) ? $formValues['type'] : '',
            'article_categories' => get_categories(array('hide_empty' => false)),
            'articles' => get_posts(array(
                'numberposts' => 20,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC',
            )),
            'locations' => $locations,
            'animateurs' => $animateurs,
            'volunteers' => $volunteers,
            'schedule_weekdays' => $scheduleWeekdays,
            'schedule_month_ordinals' => $scheduleMonthOrdinals,
        ));

        $form = $factory->create($formDefaults, $options);

        $request = Request::createFromGlobals();
        if ($request->isMethod('POST') && isset($_POST['mj_event_front_nonce'])) {
            if (!wp_verify_nonce($_POST['mj_event_front_nonce'], 'mj_event_front_form')) {
                wp_die('Verification de securite echouee');
            }
            $form->handleRequest($request);
        }

        return array(
            'form' => $form,
            'form_view' => $form->createView(),
            'form_values' => $formValues,
            'options' => $options,
            'status_labels' => $statusLabels,
            'type_labels' => $typeLabels,
            'schedule_weekdays' => $scheduleWeekdays,
            'schedule_month_ordinals' => $scheduleMonthOrdinals,
        );
    }
}
