<?php
/**
 * Plugin Name: Multisite Post Cloner
 * Plugin URI: http://wordpress.org/plugins/multisite-post-cloner/
 * Description: Multisite Post Cloner allows you to clone posts and pages across sites in your WordPress multisite network.
 * Version: 1.0.0
 * Author: amurin
 * Text Domain: multisite-post-cloner
 * License:  GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MPCL_Multisite_Post_Cloner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-mpcl-multisite-post-cloner.php';

/**
 * Activate the plugin.
 *
 * @return void
 */
function mpcl_activate_multisite_post_cloner(): void {
	$mpcl_default_types = array( 'post', 'page' );
	if ( false === get_option( 'mpcl_multisite_post_cloner_post_types' ) ) {
		update_option( 'mpcl_multisite_post_cloner_post_types', $mpcl_default_types );
	}
}
register_activation_hook( __FILE__, 'mpcl_activate_multisite_post_cloner' );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function mpcl_run_multisite_post_cloner(): void {
	// Only run the plugin in the admin.
	if ( is_admin() ) {
		MPCL_Multisite_Post_Cloner::get_instance();
	}
}
add_action( 'plugins_loaded', 'mpcl_run_multisite_post_cloner' );
