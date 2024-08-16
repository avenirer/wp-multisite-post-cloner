<?php
/**
 * Handles the bulk action for moving posts between sites.
 *
 * @package MPC_Multisite_Post_Cloner
 */

if ( ! class_exists( 'MPC_Multisite_Post_Cloner_Actions' ) ) {
	/**
	 * Class responsible for handling the bulk move actions.
	 */
	class MPC_Multisite_Post_Cloner_Actions {

		/**
		 * Handle the bulk action to clone posts to another site.
		 *
		 * @param string $redirect The redirect URL.
		 * @param string $doaction The action being performed.
		 * @param array  $object_ids The IDs of the objects being acted upon.
		 * @return string Modified redirect URL.
		 */
		public function bulk_action_multisite_handler( string $redirect, string $doaction, array $object_ids ): string {

			// Remove existing query args.
			$redirect = remove_query_arg( array( 'mpc_posts_moved', 'mpc_blogid' ), $redirect );

			if ( str_starts_with( $doaction, 'clone_to_' ) ) {
				$blog_id = str_replace( 'clone_to_', '', $doaction );

				foreach ( $object_ids as $post_id ) {
					$this->clone_post_to_blog( $post_id, $blog_id );
				}

				$redirect = $this->add_redirect_args( $redirect, $object_ids, $blog_id );
			}

			return $redirect;
		}

		/**
		 * Clone a single post to a specified blog.
		 *
		 * @param int $post_id The ID of the post to clone.
		 * @param int $blog_id The ID of the target blog.
		 * @return void
		 */
		private function clone_post_to_blog( int $post_id, int $blog_id ): void {
			$post       = get_post( $post_id, ARRAY_A );
			$post_terms = wp_get_object_terms( $post_id, 'category', array( 'fields' => 'slugs' ) );
			$data       = get_post_custom( $post_id );
			$post['ID'] = ''; // Ensure a new post is created by clearing the ID.

			switch_to_blog( $blog_id );

			$inserted_post_id = wp_insert_post( $post );
			$this->set_post_terms_and_meta( $inserted_post_id, $post_terms, $data );

			restore_current_blog();
		}

		/**
		 * Set post terms and metadata for a newly inserted post.
		 *
		 * @param int   $post_id The ID of the newly inserted post.
		 * @param array $post_terms The terms associated with the original post.
		 * @param array $meta_data The metadata associated with the original post.
		 * @return void
		 */
		private function set_post_terms_and_meta( int $post_id, array $post_terms, array $meta_data ): void {
			wp_set_object_terms( $post_id, $post_terms, 'category', false );

			foreach ( $meta_data as $key => $values ) {
				if ( '_wp_old_slug' === $key ) {
					continue;
				}
				foreach ( $values as $value ) {
					add_post_meta( $post_id, $key, $value );
				}
			}
		}

		/**
		 * Add query arguments for the redirect URL.
		 *
		 * @param string $redirect The original redirect URL.
		 * @param array  $object_ids The IDs of the moved objects.
		 * @param int    $blog_id The ID of the target blog.
		 * @return string Modified redirect URL.
		 */
		private function add_redirect_args( string $redirect, array $object_ids, int $blog_id ): string {
			$nonce = wp_create_nonce( 'mpc_bulk_action_nonce' );

			return add_query_arg(
				array(
					'mpc_posts_moved' => count( $object_ids ),
					'mpc_blogid'      => $blog_id,
					'mpc_nonce'       => $nonce,
				),
				$redirect
			);
		}
	}
}