<?php
/**
 * Geometry field storage handler
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Field_Storage
 */
class Jet_Geometry_Field_Storage {

	/**
	 * Geometry field instance
	 *
	 * @var Jet_Geometry_Field
	 */
	private $field;

	/**
	 * Flag to prevent infinite loops
	 *
	 * @var bool
	 */
	private $saving = false;

	/**
	 * Constructor
	 *
	 * @param Jet_Geometry_Field $field Geometry field instance.
	 */
	public function __construct( $field ) {
		$this->field = $field;

		// DISABLE save_post hook - it causes infinite loop
		// JetEngine handles meta saving differently
		// add_action( 'save_post', array( $this, 'process_post_geometry_save' ), 10, 2 );

		// Hook into CCT save
		add_filter( 'jet-engine/custom-content-types/item-to-update', array( $this, 'process_cct_geometry_save' ), 10, 2 );
	}

	/**
	 * Process geometry save for posts
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function process_post_geometry_save( $post_id, $post ) {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get meta boxes for this post type
		$meta_boxes = $this->get_meta_boxes_for_post_type( $post->post_type );

		if ( empty( $meta_boxes ) ) {
			return;
		}

		foreach ( $meta_boxes as $meta_box ) {
			if ( empty( $meta_box['meta_fields'] ) ) {
				continue;
			}

			foreach ( $meta_box['meta_fields'] as $field ) {
				if ( empty( $field['type'] ) || 'map_geometry' !== $field['type'] ) {
					continue;
				}

				$this->save_geometry_for_field( $post_id, $field, 'post' );
			}
		}
	}

	/**
	 * Process geometry save for CCT
	 *
	 * @param array $item   Item data.
	 * @param array $fields Fields config.
	 * @return array
	 */
	public function process_cct_geometry_save( $item, $fields ) {
		foreach ( $fields as $field_id => $field_data ) {
			if ( 'map_geometry' !== $field_data['type'] ) {
				continue;
			}

			$prefix = $field_id;

			// Process geometry data if present
			if ( ! empty( $item[ $prefix . '_geometry_data' ] ) ) {
				$geojson = $item[ $prefix . '_geometry_data' ];

				if ( is_string( $geojson ) ) {
					$geojson = json_decode( $geojson, true );
				}

				if ( $geojson && isset( $geojson['type'], $geojson['coordinates'] ) ) {
					// Calculate and set centroid
					$centroid = $this->calculate_centroid( $geojson );

					if ( $centroid ) {
						$item[ $prefix . '_lat' ] = $centroid[1];
						$item[ $prefix . '_lng' ] = $centroid[0];
					}

					// Set geometry type if not set
					if ( empty( $item[ $prefix . '_geometry_type' ] ) ) {
						$item[ $prefix . '_geometry_type' ] = Jet_Geometry_Utils::get_geometry_type( $geojson );
					}

					// Ensure data is string
					if ( is_array( $item[ $prefix . '_geometry_data' ] ) ) {
						$item[ $prefix . '_geometry_data' ] = wp_json_encode( $item[ $prefix . '_geometry_data' ] );
					}
				}
			}
		}

		return $item;
	}

	/**
	 * Save geometry data for a specific field
	 *
	 * @param int    $object_id Object ID (post ID, term ID, etc.).
	 * @param array  $field     Field config.
	 * @param string $type      Object type (post, term, user).
	 */
	private function save_geometry_for_field( $object_id, $field, $type = 'post' ) {
		$field_name   = $field['name'];
		$field_prefix = Jet_Geometry_Utils::get_field_prefix( $field_name );

		// Get geometry data from POST
		$geometry_data = null;
		$geometry_type = null;

		// Try to get from POST data
		if ( isset( $_POST[ $field_prefix . '_geometry_data' ] ) ) {
			$geometry_data = wp_unslash( $_POST[ $field_prefix . '_geometry_data' ] );
		}

		if ( isset( $_POST[ $field_prefix . '_geometry_type' ] ) ) {
			$geometry_type = sanitize_text_field( wp_unslash( $_POST[ $field_prefix . '_geometry_type' ] ) );
		}

		if ( empty( $geometry_data ) ) {
			return;
		}

		// Validate and sanitize GeoJSON
		$geojson = Jet_Geometry_Utils::validate_geojson( $geometry_data );

		if ( is_wp_error( $geojson ) ) {
			return;
		}

		// Calculate centroid
		$centroid = $this->calculate_centroid( $geojson );

		// Save all meta fields
		$this->update_meta( $object_id, $field_prefix . '_geometry_type', $geometry_type, $type );
		$this->update_meta( $object_id, $field_prefix . '_geometry_data', wp_json_encode( $geojson ), $type );

		if ( $centroid ) {
			$this->update_meta( $object_id, $field_prefix . '_lat', $centroid[1], $type );
			$this->update_meta( $object_id, $field_prefix . '_lng', $centroid[0], $type );
		}
	}

	/**
	 * Calculate centroid from GeoJSON
	 *
	 * @param array $geojson GeoJSON data.
	 * @return array|false [lng, lat] or false.
	 */
	private function calculate_centroid( $geojson ) {
		return Jet_Geometry_Utils::calculate_geometry_centroid( $geojson );
	}

	/**
	 * Update meta based on object type
	 *
	 * @param int    $object_id Object ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $value     Meta value.
	 * @param string $type      Object type.
	 */
	private function update_meta( $object_id, $meta_key, $value, $type = 'post' ) {
		switch ( $type ) {
			case 'term':
				update_term_meta( $object_id, $meta_key, $value );
				break;

			case 'user':
				update_user_meta( $object_id, $meta_key, $value );
				break;

			case 'post':
			default:
				update_post_meta( $object_id, $meta_key, $value );
				break;
		}
	}

	/**
	 * Get meta boxes for post type
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	private function get_meta_boxes_for_post_type( $post_type ) {
		if ( ! function_exists( 'jet_engine' ) || ! jet_engine()->meta_boxes ) {
			return array();
		}

		// Use correct method to get meta boxes
		$meta_boxes = jet_engine()->meta_boxes->data->get_items();

		if ( empty( $meta_boxes ) ) {
			return array();
		}

		$result = array();

		foreach ( $meta_boxes as $meta_box ) {
			// Unserialize args if needed
			$args = isset( $meta_box['args'] ) ? maybe_unserialize( $meta_box['args'] ) : array();
			$meta_fields = isset( $meta_box['meta_fields'] ) ? maybe_unserialize( $meta_box['meta_fields'] ) : array();

			if ( empty( $args['object_type'] ) || 'post' !== $args['object_type'] ) {
				continue;
			}

			if ( empty( $args['allowed_post_type'] ) ) {
				continue;
			}

			// Check if this meta box applies to our post type
			$allowed_types = $args['allowed_post_type'];

			if ( in_array( $post_type, $allowed_types, true ) ) {
				$result[] = array(
					'args'        => $args,
					'meta_fields' => $meta_fields,
				);
			}
		}

		return $result;
	}
}

