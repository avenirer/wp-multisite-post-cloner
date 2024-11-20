<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Multisite_Post_Cloner
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete the option that stores the post types to clone.
delete_option( 'mpcl_multisite_post_cloner_post_types' );
