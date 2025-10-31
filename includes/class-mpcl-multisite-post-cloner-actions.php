<?php
/**
 * Handles the bulk action for moving posts between sites.
 *
 * @package MPCL_Multisite_Post_Cloner
 */

if ( ! class_exists( 'MPCL_Multisite_Post_Cloner_Actions' ) ) {
	/**
	 * Class responsible for handling the bulk move actions.
	 */
	class MPCL_Multisite_Post_Cloner_Actions {

		private const SKIP_META = '__mpcl_skip_meta__';

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
			$redirect = remove_query_arg( array( 'mpcl_posts_moved', 'mpcl_blogid' ), $redirect );

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

			$process_beaver_builder    = $this->is_beaver_builder_active();
			$attachments_to_clone      = array();
			$layout_data_published     = array();
			$layout_data_draft         = array();
			$layout_settings_published = new StdClass();
			$layout_settings_draft     = new StdClass();

			if ( $process_beaver_builder ) {
				$attachments_to_clone = $this->get_post_attachments_for_clone( $post_id );
				$layout_data_published = FLBuilderModel::get_layout_data( 'published', $post_id );
				$layout_data_draft     = FLBuilderModel::get_layout_data( 'draft', $post_id );
				$layout_settings_published = FLBuilderModel::get_layout_settings( 'published', $post_id );
				$layout_settings_draft     = FLBuilderModel::get_layout_settings( 'draft', $post_id );
			}

			switch_to_blog( $blog_id );

			$inserted_post_id = wp_insert_post( $post );

			if ( is_wp_error( $inserted_post_id ) ) {
				restore_current_blog();
				return;
			}

			$attachment_id_map  = array();
			$attachment_url_map = array();

			if ( $process_beaver_builder && ! empty( $attachments_to_clone ) ) {
				$attachment_maps   = $this->clone_attachments_to_blog( $attachments_to_clone, $inserted_post_id );
				$attachment_id_map = $attachment_maps['id_map'];
				$attachment_url_map = $attachment_maps['url_map'];

				if ( ! empty( $attachment_url_map ) && ! empty( $post['post_content'] ) ) {
					$updated_content = str_replace( array_keys( $attachment_url_map ), array_values( $attachment_url_map ), $post['post_content'] );

					if ( $updated_content !== $post['post_content'] ) {
						wp_update_post(
							array(
								'ID'           => $inserted_post_id,
								'post_content' => $updated_content,
							)
						);

						$post['post_content'] = $updated_content;
					}
				}
			}

			$this->set_post_terms_and_meta( $inserted_post_id, $post_terms, $data, $attachment_id_map, $attachment_url_map );

			if ( $process_beaver_builder ) {
				$this->apply_beaver_builder_layout(
					$inserted_post_id,
					$post_id,
					$layout_data_published,
					$layout_data_draft,
					$layout_settings_published,
					$layout_settings_draft,
					$attachment_id_map,
					$attachment_url_map
				);
			}

			restore_current_blog();
		}

		/**
		 * Set post terms and metadata for a newly inserted post.
		 *
		 * @param int   $post_id             The ID of the newly inserted post.
		 * @param array $post_terms          The terms associated with the original post.
		 * @param array $meta_data           The metadata associated with the original post.
		 * @param array $attachment_id_map   Original attachment IDs mapped to new IDs.
		 * @param array $attachment_url_map  Original attachment URLs mapped to new URLs.
		 * @return void
		 */
		private function set_post_terms_and_meta( int $post_id, array $post_terms, array $meta_data, array $attachment_id_map = array(), array $attachment_url_map = array() ): void {
			wp_set_object_terms( $post_id, $post_terms, 'category', false );

			foreach ( $meta_data as $key => $values ) {
				if ( '_wp_old_slug' === $key ) {
					continue;
				}
				foreach ( $values as $value ) {
					$transformed_value = $this->transform_meta_value( $key, $value, $attachment_id_map, $attachment_url_map );
					if ( self::SKIP_META === $transformed_value ) {
						continue;
					}
					add_post_meta( $post_id, $key, $transformed_value );
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
			$nonce = wp_create_nonce( 'mpcl_bulk_action_nonce' );

			return add_query_arg(
				array(
					'mpcl_posts_moved' => count( $object_ids ),
					'mpcl_blogid'      => $blog_id,
					'mpcl_nonce'       => $nonce,
				),
				$redirect
			);
		}

		/**
		 * Determine if Beaver Builder is available.
		 *
		 * @return bool
		 */
		private function is_beaver_builder_active(): bool {
			return class_exists( 'FLBuilderModel' );
		}

		/**
		 * Collect attachment data needed for cloning.
		 *
		 * @param int $post_id The source post ID.
		 * @return array
		 */
		private function get_post_attachments_for_clone( int $post_id ): array {
			$args        = array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'numberposts'    => -1,
				'post_status'    => 'any',
				'post_mime_type' => 'image',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			);
			$attachments = get_posts( $args );
			$results     = array();

			if ( empty( $attachments ) ) {
				return $results;
			}

			foreach ( $attachments as $attachment ) {
				$original_file = wp_get_original_image_path( $attachment->ID );

				$results[] = array(
					'id'            => $attachment->ID,
					'file'          => ( $original_file && file_exists( $original_file ) ) ? $original_file : '',
					'url'           => wp_get_attachment_url( $attachment->ID ),
					'metadata'      => wp_get_attachment_metadata( $attachment->ID ),
					'alt'           => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
					'title'         => get_the_title( $attachment->ID ),
					'post_mime_type'=> get_post_mime_type( $attachment->ID ),
				);
			}

			return $results;
		}

		/**
		 * Clone attachments into the current blog uploads directory.
		 *
		 * @param array $attachments Attachment context collected from the source blog.
		 * @param int   $post_id     The destination post ID.
		 * @return array{ id_map: array<int,int>, url_map: array<string,string> }
		 */
		private function clone_attachments_to_blog( array $attachments, int $post_id ): array {
			$attachment_id_map  = array();
			$attachment_url_map = array();

			if ( empty( $attachments ) ) {
				return array(
					'id_map'  => $attachment_id_map,
					'url_map' => $attachment_url_map,
				);
			}

			$uploads = wp_upload_dir();

			if ( ! empty( $uploads['error'] ) || empty( $uploads['path'] ) || empty( $uploads['url'] ) ) {
				return array(
					'id_map'  => $attachment_id_map,
					'url_map' => $attachment_url_map,
				);
			}

			wp_mkdir_p( $uploads['path'] );

			require_once ABSPATH . 'wp-admin/includes/image.php';

			$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

			foreach ( $attachments as $attachment ) {
				$attachment_url  = isset( $attachment['url'] ) ? $attachment['url'] : '';
				$attachment_host = $attachment_url ? wp_parse_url( $attachment_url, PHP_URL_HOST ) : '';

				if ( $attachment_url && $site_host && $attachment_host && strtolower( $attachment_host ) !== strtolower( $site_host ) ) {
					$attachment_url_map[ $attachment_url ] = $attachment_url;
					continue;
				}

				$original_file = isset( $attachment['file'] ) ? $attachment['file'] : '';

				if ( ! $original_file || ! file_exists( $original_file ) ) {
					if ( $attachment_url ) {
						$attachment_url_map[ $attachment_url ] = $attachment_url;
					}
					continue;
				}

				$filename      = basename( $original_file );
				$unique_name   = wp_unique_filename( $uploads['path'], $filename );
				$destination   = trailingslashit( $uploads['path'] ) . $unique_name;
				$destination_url = trailingslashit( $uploads['url'] ) . $unique_name;

				if ( ! copy( $original_file, $destination ) ) {
					continue;
				}

				$filetype = wp_check_filetype( $unique_name, null );

				$attachment_post = array(
					'guid'           => $destination_url,
					'post_mime_type' => $filetype['type'] ? $filetype['type'] : $attachment['post_mime_type'],
					'post_title'     => $attachment['title'] ? $attachment['title'] : preg_replace( '/\.[^.]+$/', '', $unique_name ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$new_attachment_id = wp_insert_attachment( $attachment_post, $destination, $post_id );

				if ( is_wp_error( $new_attachment_id ) ) {
					continue;
				}

				if ( ! empty( $attachment['alt'] ) ) {
					update_post_meta( $new_attachment_id, '_wp_attachment_image_alt', $attachment['alt'] );
				}

				$new_metadata = wp_generate_attachment_metadata( $new_attachment_id, $destination );

				if ( ! empty( $new_metadata ) ) {
					wp_update_attachment_metadata( $new_attachment_id, $new_metadata );
				}

				$new_url = wp_get_attachment_url( $new_attachment_id );

				$attachment_id_map[ $attachment['id'] ] = $new_attachment_id;

				if ( empty( $attachment['url'] ) || ! $new_url ) {
					continue;
				}

				$attachment_url_map[ $attachment['url'] ] = $new_url;

				$old_metadata = is_array( $attachment['metadata'] ) ? $attachment['metadata'] : array();
				$new_metadata = is_array( $new_metadata ) ? $new_metadata : array();

				$old_base_url = trailingslashit( str_replace( basename( $attachment['url'] ), '', $attachment['url'] ) );
				$new_base_url = trailingslashit( str_replace( basename( $new_url ), '', $new_url ) );

				if ( $old_base_url && $new_base_url && ! empty( $old_metadata['sizes'] ) && ! empty( $new_metadata['sizes'] ) ) {
					foreach ( $old_metadata['sizes'] as $size_key => $size_data ) {
						if ( empty( $size_data['file'] ) || empty( $new_metadata['sizes'][ $size_key ]['file'] ) ) {
							continue;
						}

						$attachment_url_map[ $old_base_url . $size_data['file'] ] = $new_base_url . $new_metadata['sizes'][ $size_key ]['file'];
					}
				}
			}

			return array(
				'id_map'  => $attachment_id_map,
				'url_map' => $attachment_url_map,
			);
		}

		/**
		 * Transform a meta value to reference cloned attachments when needed.
		 *
		 * @param string $meta_key Meta key.
		 * @param mixed  $meta_value Meta value.
		 * @param array  $attachment_id_map Original attachment IDs mapped to new IDs.
		 * @param array  $attachment_url_map Original attachment URLs mapped to new URLs.
		 * @return mixed
		 */
		private function transform_meta_value( string $meta_key, $meta_value, array $attachment_id_map, array $attachment_url_map ) {
			if ( '_fl_builder_template_id' === $meta_key && class_exists( 'FLBuilderModel' ) ) {
				return FLBuilderModel::generate_node_id();
			}

			if ( empty( $attachment_id_map ) && empty( $attachment_url_map ) ) {
				return $meta_value;
			}

			if ( '_thumbnail_id' === $meta_key ) {
				$original_id = (int) maybe_unserialize( $meta_value );
				if ( isset( $attachment_id_map[ $original_id ] ) ) {
					return (string) $attachment_id_map[ $original_id ];
				}

				return $meta_value;
			}

			$builder_meta_keys = array(
				'_fl_builder_data',
				'_fl_builder_draft',
				'_fl_builder_data_settings',
				'_fl_builder_draft_settings',
				'_fl_builder_data_settings_compressed',
				'_fl_builder_draft_settings_compressed',
			);

			if ( in_array( $meta_key, $builder_meta_keys, true ) ) {
				return self::SKIP_META;
			}

			if ( is_string( $meta_value ) && ! empty( $attachment_url_map ) ) {
				if ( is_serialized( $meta_value ) ) {
					$unserialized = maybe_unserialize( $meta_value );
					$unserialized = $this->replace_cloned_attachment_references( $unserialized, $attachment_id_map, $attachment_url_map );
					return maybe_serialize( $unserialized );
				}

				return str_replace( array_keys( $attachment_url_map ), array_values( $attachment_url_map ), $meta_value );
			}

			return $meta_value;
		}

		/**
		 * Recursively replace attachment references within data structures.
		 *
		 * @param mixed $data                Data to inspect.
		 * @param array $attachment_id_map   Original attachment IDs mapped to new IDs.
		 * @param array $attachment_url_map  Original attachment URLs mapped to new URLs.
		 * @return mixed
		 */
		private function replace_cloned_attachment_references( $data, array $attachment_id_map, array $attachment_url_map ) {
			if ( is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data[ $key ] = $this->replace_cloned_attachment_references( $value, $attachment_id_map, $attachment_url_map );
				}
				return $data;
			}

			if ( is_object( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data->$key = $this->replace_cloned_attachment_references( $value, $attachment_id_map, $attachment_url_map );
				}
				return $data;
			}

			if ( is_bool( $data ) || null === $data ) {
				return $data;
			}

			if ( is_int( $data ) ) {
				return isset( $attachment_id_map[ $data ] ) ? $attachment_id_map[ $data ] : $data;
			}

			if ( is_float( $data ) ) {
				$int_value = (int) $data;
				return isset( $attachment_id_map[ $int_value ] ) ? $attachment_id_map[ $int_value ] : $data;
			}

			if ( is_string( $data ) ) {
				$trimmed = trim( $data );

				if ( ctype_digit( $trimmed ) ) {
					$int_value = (int) $trimmed;
					if ( isset( $attachment_id_map[ $int_value ] ) ) {
						return (string) $attachment_id_map[ $int_value ];
					}
				}

				if ( isset( $attachment_id_map[ $data ] ) ) {
					return (string) $attachment_id_map[ $data ];
				}

				if ( ! empty( $attachment_url_map ) ) {
					foreach ( $attachment_url_map as $old_url => $new_url ) {
						if ( false !== strpos( $data, $old_url ) ) {
							$data = str_replace( $old_url, $new_url, $data );
						}
					}
				}

				return $data;
			}

			return $data;
		}

		private function apply_beaver_builder_layout(
			int $post_id,
			int $source_post_id,
			$layout_data_published,
			$layout_data_draft,
			$layout_settings_published,
			$layout_settings_draft,
			array $attachment_id_map,
			array $attachment_url_map
		): void {
			if ( ! class_exists( 'FLBuilderModel' ) ) {
				return;
			}

			$published_data = is_array( $layout_data_published ) ? $layout_data_published : array();
			$draft_data     = is_array( $layout_data_draft ) ? $layout_data_draft : array();
			$layout_settings_published = $this->clone_value( $layout_settings_published );
			$layout_settings_draft     = $this->clone_value( $layout_settings_draft );

			if ( ! empty( $attachment_id_map ) || ! empty( $attachment_url_map ) ) {
				$published_data = $this->replace_cloned_attachment_references( $published_data, $attachment_id_map, $attachment_url_map );
				$draft_data     = $this->replace_cloned_attachment_references( $draft_data, $attachment_id_map, $attachment_url_map );
				$layout_settings_published = $this->replace_cloned_attachment_references( $layout_settings_published, $attachment_id_map, $attachment_url_map );
				$layout_settings_draft     = $this->replace_cloned_attachment_references( $layout_settings_draft, $attachment_id_map, $attachment_url_map );
			}

			$generated = $this->generate_new_node_ids_with_map( $published_data );
			$new_published_data = $generated['data'];
			$node_id_map        = $generated['map'];

			if ( empty( $draft_data ) ) {
				$new_draft_data = $new_published_data;
			} else {
				$new_draft_data = $this->remap_node_ids_with_map( $draft_data, $node_id_map );
			}

			$template_id = get_post_meta( $post_id, '_fl_builder_template_id', true );
			$global      = get_post_meta( $source_post_id, '_fl_builder_template_global', true );

			if ( $template_id && $global ) {
				foreach ( $new_published_data as $node_id => $node ) {
					if ( is_object( $node ) ) {
						$node->template_id      = $template_id;
						$node->template_node_id = $node_id;
					}
				}

				foreach ( $new_draft_data as $node_id => $node ) {
					if ( is_object( $node ) ) {
						$node->template_id      = $template_id;
						$node->template_node_id = $node_id;
					}
				}
			}

			wp_cache_flush();

			FLBuilderModel::update_layout_data( $new_published_data, 'published', $post_id );
			FLBuilderModel::update_layout_data( $new_draft_data, 'draft', $post_id );

			if ( $layout_settings_published instanceof StdClass || is_array( $layout_settings_published ) ) {
				FLBuilderModel::update_layout_settings( $layout_settings_published, 'published', $post_id );
			}

			if ( $layout_settings_draft instanceof StdClass || is_array( $layout_settings_draft ) ) {
				FLBuilderModel::update_layout_settings( $layout_settings_draft, 'draft', $post_id );
			}

			if ( method_exists( 'FLBuilderModel', 'delete_all_asset_cache' ) ) {
				FLBuilderModel::delete_all_asset_cache( $post_id );
			}
		}

		private function generate_new_node_ids_with_map( array $data ): array {
			$map   = array();
			$nodes = array();

			foreach ( $data as $node_id => $node ) {
				$map[ $node_id ] = FLBuilderModel::generate_node_id();
			}

			foreach ( $data as $node_id => $node ) {
				$new_id = $map[ $node_id ];
				$copy   = $this->clone_value( $node );

				if ( is_object( $copy ) ) {
					$copy->node = $new_id;

					if ( ! empty( $copy->parent ) && isset( $map[ $copy->parent ] ) ) {
						$copy->parent = $map[ $copy->parent ];
					}
				}

				$nodes[ $new_id ] = $copy;
			}

			return array(
				'data' => $nodes,
				'map'  => $map,
			);
		}

		private function remap_node_ids_with_map( array $data, array $map ): array {
			$nodes = array();

			foreach ( $data as $node_id => $node ) {
				$new_id = isset( $map[ $node_id ] ) ? $map[ $node_id ] : FLBuilderModel::generate_node_id();
				$copy   = $this->clone_value( $node );

				if ( is_object( $copy ) ) {
					$copy->node = $new_id;

					if ( ! empty( $copy->parent ) ) {
						$parent_id = (string) $copy->parent;

						if ( isset( $map[ $parent_id ] ) ) {
							$copy->parent = $map[ $parent_id ];
						} elseif ( isset( $map[ $copy->parent ] ) ) {
							$copy->parent = $map[ $copy->parent ];
						}
					}
				}

				$nodes[ $new_id ] = $copy;
			}

			return $nodes;
		}

		private function clone_value( $value ) {
			if ( is_object( $value ) || is_array( $value ) ) {
				return unserialize( serialize( $value ) );
			}

			return $value;
		}
	}
}
