<?php

/**
 * Nextcloud wrapper – redirects documents page links through to Nextcloud.
 *
 * When a user clicks a link with ?link= parameter,
 * this handler decodes it and redirects to the actual Nextcloud URL.
 */

use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handle ?link= parameter redirects to Nextcloud.
 */
function mj_member_handle_nextcloud_link_redirect() {
	// Only process on the /mon-compte/documents/ page
	if (!is_page('documents')) {
		return;
	}

	// Check if the ?link= parameter exists
	if (empty($_GET['link'])) {
		return;
	}

	// Get the link parameter (WordPress auto-decodes it from URL encoding)
	// We rely on WordPress's sanitization of $_GET, so we just get the raw value
	$nc_link = isset($_GET['link']) ? wp_unslash($_GET['link']) : '';

	if (empty($nc_link)) {
		return;
	}

	// Build the full Nextcloud URL
	$nextcloud_url = Config::nextcloudUrl();
	if (empty($nextcloud_url)) {
		return;
	}

	// Ensure the link starts with /
	if (strpos($nc_link, '/') !== 0) {
		$nc_link = '/' . $nc_link;
	}

	// Redirect the user to Nextcloud
	$full_url = rtrim($nextcloud_url, '/') . $nc_link;
	wp_redirect($full_url);
	exit;
}

add_action('template_redirect', 'mj_member_handle_nextcloud_link_redirect', 5);
