<?php

use Mj\Member\Classes\Front\EventSingleController;
use Mj\Member\Core\TemplateEngine;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(EventSingleController::class)) {
    require_once dirname(__DIR__) . '/includes/classes/front/EventSingleController.php';
}

if (!function_exists('mj_member_event_build_attr_string')) {
    require_once dirname(__DIR__) . '/includes/helpers/template.php';
}

$context = array();
if (isset($GLOBALS['mj_member_event_context']) && is_array($GLOBALS['mj_member_event_context'])) {
    $context = $GLOBALS['mj_member_event_context'];
}

$controller = new EventSingleController($context);
$payload = $controller->build();

$legacy = isset($payload['legacy']) && is_array($payload['legacy']) ? $payload['legacy'] : array();
$model = isset($payload['model']) && is_array($payload['model']) ? $payload['model'] : array();
$view = isset($payload['view']) && is_array($payload['view']) ? $payload['view'] : array();
$localization = isset($payload['localization']) && is_array($payload['localization']) ? $payload['localization'] : array();

$page_view = isset($view['page']) && is_array($view['page']) ? $view['page'] : array();
$partials_view = isset($view['partials']) && is_array($view['partials']) ? $view['partials'] : array();
$container_attributes = isset($page_view['attributes']) && is_array($page_view['attributes'])
    ? $page_view['attributes']
    : array('class' => 'mj-member-event-single');

if (!isset($container_attributes['class']) || trim((string) $container_attributes['class']) === '') {
    $container_attributes['class'] = 'mj-member-event-single';
}

$normalized_container_attributes = array();
foreach ($container_attributes as $attribute_name => $attribute_value) {
    if ($attribute_value === null || $attribute_value === '') {
        continue;
    }

    $normalized_container_attributes[$attribute_name] = $attribute_value;
}

if (!isset($normalized_container_attributes['class'])) {
    $normalized_container_attributes['class'] = 'mj-member-event-single';
}

$container_attr_string = function_exists('mj_member_event_build_attr_string')
    ? mj_member_event_build_attr_string($normalized_container_attributes)
    : '';

if ($container_attr_string === '' && !empty($normalized_container_attributes)) {
    $fallback_tokens = array();
    foreach ($normalized_container_attributes as $attribute_name => $attribute_value) {
        $fallback_tokens[] = sprintf(' %s="%s"', esc_attr($attribute_name), esc_attr((string) $attribute_value));
    }
    $container_attr_string = implode('', $fallback_tokens);
}

$user_is_logged_in = is_user_logged_in();

$event_single_assets_url = trailingslashit(plugin_dir_url(__FILE__));

$photo_redirect_url = '';
if (function_exists('mj_member_get_current_url')) {
    $photo_redirect_url = (string) mj_member_get_current_url();
} else {
    $photo_redirect_url = (string) get_permalink();
}
$photo_redirect_url = esc_url_raw($photo_redirect_url);

if (!isset($partials_view['photos']) || !is_array($partials_view['photos'])) {
    $partials_view['photos'] = array();
}

$partials_view['photos']['redirect_url'] = $photo_redirect_url;

$page_view['attr_string'] = $container_attr_string;
$page_view['attributes'] = $normalized_container_attributes;

$cover_fallback = array(
    'cover_url' => isset($legacy['cover_url']) ? (string) $legacy['cover_url'] : '',
    'cover_thumb' => isset($legacy['cover_thumb']) ? (string) $legacy['cover_thumb'] : '',
    'title' => isset($legacy['title'])
        ? (string) $legacy['title']
        : (isset($model['event']['title']) ? (string) $model['event']['title'] : ''),
);

$view_context = array(
    'page' => $page_view,
    'partials' => $partials_view,
    'cover_fallback' => $cover_fallback,
);

$template_context = array(
    'view' => $view_context,
    'model' => $model,
    'legacy' => $legacy,
    'localization' => $localization,
    'user' => array(
        'is_logged_in' => $user_is_logged_in,
    ),
    'assets' => array(
        'event_single' => array(
            'base_url' => $event_single_assets_url,
        ),
    ),
);

get_header();

if (!$user_is_logged_in && function_exists('mj_member_render_login_modal_component')) {
    echo '<div class="mj-member-event-single__login-helper">' . mj_member_render_login_modal_component(array(
        'extra_class' => 'mj-member-event-single__login-modal'
    )) . '</div>';
}

TemplateEngine::display('event-single/event-single.html.twig', $template_context);

get_footer();

$controller->teardown();

unset($GLOBALS['mj_member_event_context']);
