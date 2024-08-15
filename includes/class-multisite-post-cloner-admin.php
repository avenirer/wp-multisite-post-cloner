<?php
/**
 * Admin-specific functionality for the plugin.
 *
 * @package Multisite_Post_Cloner
 */

if ( ! class_exists( 'Multisite_Post_Cloner_Admin' ) ) {
	/**
	 * Class responsible for handling the admin actions and notices.
	 */
	class Multisite_Post_Cloner_Admin {

		/**
		 * Add bulk actions for moving posts to another site.
		 *
		 * @param array $bulk_array The current bulk actions.
		 * @return array Modified bulk actions.
		 */
		public function bulk_multisite_actions( array $bulk_array ): array {
			$sites = $this->get_available_sites();

			if ( $sites ) {
				$bulk_array = $this->add_sites_to_bulk_actions( $bulk_array, $sites );
			}

			return $bulk_array;
		}

		/**
		 * Display an admin notice after posts are cloned.
		 *
		 * @return void
		 */
		public function bulk_multisite_notices(): void {

			// Check if the nonce is set.
			if ( ! isset( $_REQUEST['mpc_nonce'] ) ) {
				return;
			}

			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['mpc_nonce'] ) );

			if ( ! wp_verify_nonce( $nonce, 'mpc_bulk_action_nonce' ) ) {
				return;
			}

			// Ensure we have the correct data before proceeding.
			if ( ! empty( $_REQUEST['mpc_posts_moved'] ) && ! empty( $_REQUEST['mpc_blogid'] ) ) {
				$posts_moved = (int) $_REQUEST['mpc_posts_moved'];
				$blog        = $this->get_blog_details_from_request();

				if ( $blog && $posts_moved > 0 ) {
					$this->display_notice( $posts_moved, $blog->blogname );
				}
			}
		}

		/**
		 * Register the settings page.
		 *
		 * @return void
		 */
		public function add_settings_page(): void {
			add_options_page(
				'Multisite Post Cloner Settings',
				'Multisite Post Cloner Settings',
				'manage_options',
				'multisite-post-cloner-settings',
				array( $this, 'render_settings_page' )
			);
		}

		/**
		 * Render the settings page.
		 *
		 * @return void
		 */
		public function render_settings_page(): void {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Multisite Post Cloner Settings', 'multisite-post-cloner' ); ?></h1>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'multisite_post_cloner_settings_group' );
					do_settings_sections( 'multisite-post-cloner-settings' );
					submit_button();
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Register settings and fields for the options page.
		 *
		 * @return void
		 */
		public function register_settings(): void {
			register_setting(
				'multisite_post_cloner_settings_group',
				'multisite_post_cloner_post_types',
				array( 'sanitize_callback' => array( $this, 'sanitize_post_types' ) )
			);

			add_settings_section(
				'multisite_post_cloner_settings_section',
				__( 'Post Types', 'multisite-post-cloner' ),
				'__return_null',
				'multisite-post-cloner-settings'
			);

			add_settings_field(
				'multisite_post_cloner_post_types',
				__( 'Select Post Types', 'multisite-post-cloner' ),
				array( $this, 'render_post_types_field' ),
				'multisite-post-cloner-settings',
				'multisite_post_cloner_settings_section'
			);
		}

		/**
		 * Sanitize the selected post types.
		 *
		 * @param array $input The input post types.
		 * @return array The sanitized post types.
		 */
		public function sanitize_post_types( array $input ): array {
			return array_map( 'sanitize_text_field', $input );
		}

		/**
		 * Render the post types field.
		 *
		 * @return void
		 */
		public function render_post_types_field(): void {
			$post_types     = get_post_types( array( 'public' => true ), 'objects' );
			$selected_types = get_option( 'multisite_post_cloner_post_types', array( 'post', 'page' ) );

			foreach ( $post_types as $post_type ) {
				?>
				<label>
					<input type="checkbox" name="multisite_post_cloner_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $selected_types, true ) ); ?>>
					<?php echo esc_html( $post_type->labels->name ); ?>
				</label><br>
				<?php
			}
		}

		/**
		 * Get available sites excluding the current blog.
		 *
		 * @return array The list of available sites.
		 */
		private function get_available_sites(): array {
			return get_sites(
				array(
					'site__not_in' => get_current_blog_id(), // Excluding current blog.
					'number'       => 50,
				)
			);
		}

		/**
		 * Add the available sites to the bulk actions array.
		 *
		 * @param array $bulk_array The current bulk actions.
		 * @param array $sites The available sites.
		 * @return array Modified bulk actions with site options.
		 */
		private function add_sites_to_bulk_actions( array $bulk_array, array $sites ): array {
			foreach ( $sites as $site ) {
				$bulk_array[ 'clone_to_' . $site->blog_id ] = 'Clone to "' . $site->blogname . '"';
			}

			return $bulk_array;
		}

		/**
		 * Get blog details from the request.
		 *
		 * @return object|false The blog details object or false on failure.
		 */
		private function get_blog_details_from_request(): object|false {

			if ( ! isset( $_REQUEST['mpc_nonce'] ) && ! isset( $_REQUEST['mpc_blogid'] ) ) {
				return false;
			}

			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['mpc_nonce'] ) );

			if ( ! wp_verify_nonce( $nonce, 'mpc_bulk_action_nonce' ) ) {
				return false;
			}

			$blog_id = sanitize_text_field( wp_unslash( $_REQUEST['mpc_blogid'] ) );

			$blog_details = get_blog_details( $blog_id );
			return $blog_details ? $blog_details : false;
		}

		/**
		 * Display the admin notice after posts are moved.
		 *
		 * @param int    $posts_moved The number of posts moved.
		 * @param string $blog_name The name of the blog the posts were moved to.
		 * @return void
		 */
		private function display_notice( int $posts_moved, string $blog_name ): void {
			echo '<div class="updated notice is-dismissible"><p>';
			printf(
				esc_html(
				/* Translators: 1: The number of posts moved, 2: The blog name. */
					_n(
						'%1$d post has been cloned into "%2$s".',
						'%1$d posts have been cloned into "%2$s".',
						$posts_moved,
						'multisite-post-cloner'
					)
				),
				esc_html( $posts_moved ),
				esc_html( $blog_name )
			);
			echo '</p></div>';
		}

		/**
		 * Validate nonce for bulk actions.
		 *
		 * @param string $nonce_field The name of the nonce field.
		 * @param string $nonce_action The action associated with the nonce.
		 * @return bool True if nonce is valid, false otherwise.
		 */
		private function validate_nonce( string $nonce_field, string $nonce_action ): bool {
			if ( ! isset( $_REQUEST[ $nonce_field ] ) ) {
				return false;
			}

			$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_field ] ) );

			return wp_verify_nonce( $nonce, $nonce_action );
		}
	}
}