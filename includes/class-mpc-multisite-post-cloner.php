<?php
/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 *
 * @package MPC_Multisite_Post_Cloner
 */

if ( ! class_exists( 'MPC_Multisite_Post_Cloner' ) ) {

	/**
	 * The main class for the Multisite Post Cloner plugin.
	 */
	class MPC_Multisite_Post_Cloner {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->load_dependencies();
			$this->define_admin_hooks();
		}

		/**
		 * Load the required dependencies for this plugin.
		 *
		 * @return void
		 */
		private function load_dependencies(): void {
			require_once plugin_dir_path( __FILE__ ) . 'class-mpc-multisite-post-cloner-admin.php';
			require_once plugin_dir_path( __FILE__ ) . 'class-mpc-multisite-post-cloner-actions.php';
		}

		/**
		 * Define the hooks related to the admin area.
		 *
		 * @return void
		 */
		private function define_admin_hooks(): void {
			$plugin_admin = new MPC_Multisite_Post_Cloner_Admin();
			add_action( 'admin_menu', array( $plugin_admin, 'add_settings_page' ) );
			add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );

			$selected_types = get_option( 'mpc_multisite_post_cloner_post_types', array( 'post', 'page' ) );

			add_action( 'admin_notices', array( $plugin_admin, 'bulk_multisite_notices' ) );

			$plugin_actions = new MPC_Multisite_Post_Cloner_Actions();

			foreach ( $selected_types as $post_type ) {
				add_filter( "bulk_actions-edit-{$post_type}", array( $plugin_admin, 'bulk_multisite_actions' ) );
				add_filter( "handle_bulk_actions-edit-{$post_type}", array( $plugin_actions, 'bulk_action_multisite_handler' ), 10, 3 );
			}
		}
	}
}