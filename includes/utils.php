<?php
/**
 * Utility functions for geometry operations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Utils
 */
class Jet_Geometry_Utils {

	/**
	 * Calculate centroid of a LineString
	 *
	 * @param array $coordinates Array of [lng, lat] coordinates.
	 * @return array [lng, lat]
	 */
	public static function calculate_line_centroid( $coordinates ) {
		if ( empty( $coordinates ) || ! is_array( $coordinates ) ) {
			return array( 0, 0 );
		}

		$count = count( $coordinates );
		$lng   = 0;
		$lat   = 0;

		foreach ( $coordinates as $coord ) {
			$lng += $coord[0];
			$lat += $coord[1];
		}

		return array(
			$lng / $count,
			$lat / $count,
		);
	}

	/**
	 * Calculate centroid of a Polygon (proper algorithm)
	 *
	 * @param array $coordinates Array of polygon rings (first is outer ring).
	 * @return array [lng, lat]
	 */
	public static function calculate_polygon_centroid( $coordinates ) {
		if ( empty( $coordinates ) || ! is_array( $coordinates ) || ! isset( $coordinates[0] ) ) {
			return array( 0, 0 );
		}

		// Use only the outer ring (first array)
		$outer_ring = $coordinates[0];
		
		if ( empty( $outer_ring ) || count( $outer_ring ) < 3 ) {
			return array( 0, 0 );
		}

		// Calculate centroid using proper formula
		$area = 0;
		$cx = 0;
		$cy = 0;
		$num_points = count( $outer_ring ) - 1; // Exclude closing point

		for ( $i = 0; $i < $num_points; $i++ ) {
			$j = ( $i + 1 ) % $num_points;
			
			$xi = $outer_ring[ $i ][0];
			$yi = $outer_ring[ $i ][1];
			$xj = $outer_ring[ $j ][0];
			$yj = $outer_ring[ $j ][1];
			
			$cross = ( $xi * $yj ) - ( $xj * $yi );
			$area += $cross;
			$cx += ( $xi + $xj ) * $cross;
			$cy += ( $yi + $yj ) * $cross;
		}

		if ( abs( $area ) < 0.000001 ) {
			// Fallback to simple average
			return self::calculate_line_centroid( $outer_ring );
		}

		$area = $area / 2.0;
		$cx = $cx / ( 6.0 * $area );
		$cy = $cy / ( 6.0 * $area );

		return array( $cx, $cy );
	}

	/**
	 * Calculate centroid for any supported GeoJSON geometry.
	 *
	 * @param array $geojson Geometry array.
	 * @return array|false [lng, lat] or false.
	 */
	public static function calculate_geometry_centroid( $geojson ) {
		if ( empty( $geojson['type'] ) || empty( $geojson['coordinates'] ) ) {
			return false;
		}

		switch ( $geojson['type'] ) {
			case 'Point':
				return $geojson['coordinates'];

			case 'LineString':
				return self::calculate_line_centroid( $geojson['coordinates'] );

			case 'Polygon':
				return self::calculate_polygon_centroid( $geojson['coordinates'] );

			case 'MultiPoint':
				return self::calculate_line_centroid( $geojson['coordinates'] );

			case 'MultiLineString':
				$all_points = array();
				foreach ( $geojson['coordinates'] as $line ) {
					$all_points = array_merge( $all_points, $line );
				}
				return self::calculate_line_centroid( $all_points );

			case 'MultiPolygon':
				$all_points = array();
				foreach ( $geojson['coordinates'] as $polygon ) {
					foreach ( $polygon as $ring ) {
						$all_points = array_merge( $all_points, $ring );
					}
				}
				return self::calculate_line_centroid( $all_points );
		}

		return false;
	}

	/**
	 * Validate GeoJSON geometry
	 *
	 * @param mixed $geojson GeoJSON string or array.
	 * @return array|WP_Error Valid geometry array or WP_Error on failure.
	 */
	public static function validate_geojson( $geojson ) {
		// Decode if string
		if ( is_string( $geojson ) ) {
			$geojson = json_decode( $geojson, true );
		}

		if ( ! is_array( $geojson ) ) {
			return new WP_Error( 'invalid_geojson', __( 'Invalid GeoJSON format', 'jet-geometry-addon' ) );
		}

		// Check required properties
		if ( ! isset( $geojson['type'] ) || ! isset( $geojson['coordinates'] ) ) {
			return new WP_Error( 'missing_properties', __( 'GeoJSON must have type and coordinates', 'jet-geometry-addon' ) );
		}

		// Validate type
		$valid_types = array( 'Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon' );
		if ( ! in_array( $geojson['type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid geometry type', 'jet-geometry-addon' ) );
		}

		// Basic coordinate validation
		if ( ! is_array( $geojson['coordinates'] ) || empty( $geojson['coordinates'] ) ) {
			return new WP_Error( 'invalid_coordinates', __( 'Invalid coordinates', 'jet-geometry-addon' ) );
		}

		return $geojson;
	}

	/**
	 * Get geometry type from GeoJSON
	 *
	 * @param mixed $geojson GeoJSON string or array.
	 * @return string Geometry type (pin, line, polygon, or empty).
	 */
	public static function get_geometry_type( $geojson ) {
		if ( is_string( $geojson ) ) {
			$geojson = json_decode( $geojson, true );
		}

		if ( ! is_array( $geojson ) || ! isset( $geojson['type'] ) ) {
			return '';
		}

		$type_map = array(
			'Point'        => 'pin',
			'LineString'   => 'line',
			'Polygon'      => 'polygon',
			'MultiPoint'   => 'pin',
			'MultiLineString' => 'line',
			'MultiPolygon' => 'polygon',
		);

		return isset( $type_map[ $geojson['type'] ] ) ? $type_map[ $geojson['type'] ] : '';
	}

	/**
	 * Convert geometry type to GeoJSON type
	 *
	 * @param string $geometry_type Geometry type (pin, line, polygon).
	 * @return string GeoJSON type.
	 */
	public static function geometry_type_to_geojson( $geometry_type ) {
		$map = array(
			'pin'     => 'Point',
			'line'    => 'LineString',
			'polygon' => 'Polygon',
		);

		return isset( $map[ $geometry_type ] ) ? $map[ $geometry_type ] : 'Point';
	}

	/**
	 * Sanitize GeoJSON data
	 *
	 * @param mixed $geojson GeoJSON data.
	 * @return string Sanitized JSON string.
	 */
	public static function sanitize_geojson( $geojson ) {
		if ( is_string( $geojson ) ) {
			$geojson = json_decode( $geojson, true );
		}

		if ( ! is_array( $geojson ) ) {
			return '';
		}

		// Recursively sanitize arrays
		array_walk_recursive( $geojson, function( &$value ) {
			if ( is_numeric( $value ) ) {
				$value = floatval( $value );
			} elseif ( is_string( $value ) ) {
				$value = sanitize_text_field( $value );
			}
		});

		return wp_json_encode( $geojson );
	}

	/**
	 * Get field prefix (hash) for meta keys
	 *
	 * @param string $field_name Field name.
	 * @return string Field prefix.
	 */
	public static function get_field_prefix( $field_name ) {
		return apply_filters( 'jet-geometry-addon/field-prefix', md5( $field_name ), $field_name );
	}

	/**
	 * Check if point is inside polygon
	 *
	 * @param array $point [lng, lat].
	 * @param array $polygon Polygon coordinates.
	 * @return bool
	 */
	public static function point_in_polygon( $point, $polygon ) {
		$vertices = $polygon[0]; // Outer ring
		$vertices_count = count( $vertices );
		$lng = $point[0];
		$lat = $point[1];
		$inside = false;

		for ( $i = 0, $j = $vertices_count - 1; $i < $vertices_count; $j = $i++ ) {
			$xi = $vertices[ $i ][0];
			$yi = $vertices[ $i ][1];
			$xj = $vertices[ $j ][0];
			$yj = $vertices[ $j ][1];

			$intersect = ( ( $yi > $lat ) !== ( $yj > $lat ) )
				&& ( $lng < ( $xj - $xi ) * ( $lat - $yi ) / ( $yj - $yi ) + $xi );

			if ( $intersect ) {
				$inside = ! $inside;
			}
		}

		return $inside;
	}

	/**
	 * Match country name to taxonomy term
	 *
	 * @param string $country_name Country name from GeoJSON.
	 * @param string $iso_code ISO code.
	 * @return int|false Term ID or false.
	 */
	public static function match_country_term( $country_name, $iso_code = '' ) {
		// Map of alternative country names (GeoJSON name => WordPress term name)
		$country_name_mapping = array(
			'Czechia'                => 'Czech Republic',
			'Czech Republic'         => 'Czech Republic', // Also handle reverse
			'Bosnia and Herz.'       => 'Bosnia and Herzegovina',
			'Bosnia and Herzegovina' => 'Bosnia and Herzegovina', // Also handle reverse
			'United States'          => 'United States of America',
			'USA'                    => 'United States of America',
			'United Kingdom'         => 'United Kingdom',
			'UK'                     => 'United Kingdom',
			'Russia'                 => 'Russian Federation',
			'Russian Federation'     => 'Russian Federation',
			'Iran'                   => 'Iran, Islamic Republic of',
			'Korea, North'           => 'North Korea',
			'Korea, South'           => 'South Korea',
			'North Korea'            => 'North Korea',
			'South Korea'            => 'South Korea',
			'Vatican'                => 'Vatican City',
			'Vatican City'           => 'Vatican City',
			'Myanmar'                => 'Myanmar',
			'Burma'                  => 'Myanmar',
		);

		// Check if we have a mapping for this country name
		if ( isset( $country_name_mapping[ $country_name ] ) ) {
			$mapped_name = $country_name_mapping[ $country_name ];
			$term = get_term_by( 'name', $mapped_name, 'countries' );
			if ( $term ) {
				return $term->term_id;
			}
		}

		// First try exact match by name
		$term = get_term_by( 'name', $country_name, 'countries' );
		if ( $term ) {
			return $term->term_id;
		}

		// Try slug match
		$slug = sanitize_title( $country_name );
		$term = get_term_by( 'slug', $slug, 'countries' );
		if ( $term ) {
			return $term->term_id;
		}

		// Try ISO code in meta (most reliable for matching)
		if ( ! empty( $iso_code ) ) {
			$terms = get_terms( array(
				'taxonomy'   => 'countries',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => '_country_iso_code',
						'value' => $iso_code,
					),
				),
			) );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return $terms[0]->term_id;
			}
		}

		// Try reverse mapping (check if any term name maps to this GeoJSON name)
		foreach ( $country_name_mapping as $geojson_name => $wp_name ) {
			if ( $geojson_name === $country_name ) {
				$term = get_term_by( 'name', $wp_name, 'countries' );
				if ( $term ) {
					return $term->term_id;
				}
			}
		}

		// Try fuzzy matching on term names
		$all_terms = get_terms( array(
			'taxonomy'   => 'countries',
			'hide_empty' => false,
		) );

		if ( ! empty( $all_terms ) && ! is_wp_error( $all_terms ) ) {
			foreach ( $all_terms as $term ) {
				$similarity = 0;
				similar_text( strtolower( $country_name ), strtolower( $term->name ), $similarity );
				if ( $similarity > 80 ) { // 80% similarity threshold
					return $term->term_id;
				}
			}
		}

		return false;
	}

	/**
	 * Format coordinates for display
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @param int   $decimals Number of decimal places.
	 * @return string Formatted coordinates.
	 */
	public static function format_coordinates( $lat, $lng, $decimals = 6 ) {
		return sprintf(
			'%s, %s',
			number_format( $lat, $decimals ),
			number_format( $lng, $decimals )
		);
	}

	/**
	 * Ensure JetEngine map field meta mirrors stored geometry.
	 *
	 * @param int          $post_id    Post ID.
	 * @param string       $field_name Field name (e.g., incident_geometry).
	 * @param array|string $geometry   Geometry array or JSON (can be Feature or bare geometry).
	 * @param float|null   $lat        Optional latitude override.
	 * @param float|null   $lng        Optional longitude override.
	 */
	public static function sync_map_field_meta( $post_id, $field_name, $geometry, $lat = null, $lng = null ) {
		if ( empty( $post_id ) || empty( $field_name ) || empty( $geometry ) ) {
			return;
		}

		if ( is_string( $geometry ) ) {
			$geometry = json_decode( $geometry, true );
		}

		if ( ! is_array( $geometry ) ) {
			return;
		}

		$geometry_data = $geometry;

		if ( isset( $geometry['geometry'] ) && is_array( $geometry['geometry'] ) ) {
			$geometry_data = $geometry['geometry'];
		}

		if ( empty( $geometry_data['type'] ) || empty( $geometry_data['coordinates'] ) ) {
			return;
		}

		$prefix        = self::get_field_prefix( $field_name );
		$geometry_type = self::get_geometry_type( $geometry_data );

		if ( $geometry_type ) {
			update_post_meta( $post_id, $prefix . '_geometry_type', $geometry_type );
		}

		update_post_meta( $post_id, $prefix . '_geometry_data', wp_json_encode( $geometry_data ) );

		if ( null === $lat || null === $lng ) {
			$centroid = self::calculate_geometry_centroid( $geometry_data );
			if ( $centroid ) {
				if ( null === $lng ) {
					$lng = $centroid[0];
				}
				if ( null === $lat ) {
					$lat = $centroid[1];
				}
			}
		}

		if ( null !== $lat ) {
			update_post_meta( $post_id, $prefix . '_lat', floatval( $lat ) );
		}

		if ( null !== $lng ) {
			update_post_meta( $post_id, $prefix . '_lng', floatval( $lng ) );
		}
	}

	/**
	 * Update post meta with support for JetEngine custom storage.
	 * 
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool|int Result of update operation.
	 */
	public static function update_post_meta_for_custom_storage( $post_id, $meta_key, $meta_value ) {
		// Check if post type uses custom storage
		if ( ! class_exists( '\Jet_Engine\CPT\Custom_Tables\Manager' ) ) {
			// Standard WordPress meta storage
			return update_post_meta( $post_id, $meta_key, $meta_value );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$post_type = $post->post_type;
		$manager = \Jet_Engine\CPT\Custom_Tables\Manager::instance();

		// Check if this post type uses custom storage
		if ( empty( $manager->storages ) ) {
			// Standard WordPress meta storage
			return update_post_meta( $post_id, $meta_key, $meta_value );
		}

		// Find storage for this post type
		$storage_data = null;
		foreach ( $manager->storages as $data ) {
			if ( 'post' === $data['object_type'] && $post_type === $data['object_slug'] ) {
				$storage_data = $data;
				break;
			}
		}

		if ( ! $storage_data ) {
			// Standard WordPress meta storage
			return update_post_meta( $post_id, $meta_key, $meta_value );
		}

		// Post type uses custom storage
		$fields = isset( $storage_data['fields'] ) ? $storage_data['fields'] : array();
		
		// Check if this meta key is in the list of fields for custom storage
		$is_custom_field = in_array( $meta_key, $fields, true );

		// Post type uses custom storage - update directly in custom table
		$db = $manager->get_db_instance( $post_type, $fields );
		
		if ( ! $db || ! $db->is_table_exists() ) {
			// Table doesn't exist yet, use standard storage
			return update_post_meta( $post_id, $meta_key, $meta_value );
		}

		// Serialize array/object values
		$serialized_value = $meta_value;
		if ( is_array( $meta_value ) || is_object( $meta_value ) ) {
			$serialized_value = maybe_serialize( $meta_value );
		}

		$result = false;

		// Always save to standard table first for compatibility
		$standard_result = update_post_meta( $post_id, $meta_key, $meta_value );
		
		// Clear WordPress meta cache
		wp_cache_delete( $post_id, 'post_meta' );

		// If field is in custom storage list, also save to custom table
		if ( $is_custom_field ) {
			error_log( sprintf( '[JetGeometry] Saving meta field "%s" to custom storage table for post ID %d (is in fields list)', $meta_key, $post_id ) );
			
			// Check if row exists in custom table
			$obj_row = $db->get_item( $post_id, 'object_ID' );
			
			if ( $obj_row ) {
				// Update existing row
				$custom_result = $db->update( 
					array( $meta_key => $serialized_value ),
					array( 'object_ID' => $post_id )
				);
			} else {
				// Insert new row with this field
				$insert_data = array( 'object_ID' => $post_id, $meta_key => $serialized_value );
				$custom_result = $db->insert( $insert_data );
			}

			// Clear cache using correct cache key format (same as JetEngine)
			$cache_key = $db->table() . '_' . $post_id;
			wp_cache_delete( $cache_key, 'jet_engine_custom_tables' );

			error_log( sprintf( '[JetGeometry] Meta field "%s" saved to custom storage for post ID %d (result: %s)', $meta_key, $post_id, $custom_result !== false ? 'success' : 'failed' ) );
			
			$result = ( $custom_result !== false ) ? $standard_result : false;
		} else {
			error_log( sprintf( '[JetGeometry] Meta field "%s" is not in custom storage list (fields: %s), saving to standard table only for post ID %d', $meta_key, implode( ', ', array_slice( $fields, 0, 10 ) ) . ( count( $fields ) > 10 ? '...' : '' ), $post_id ) );
			$result = $standard_result;
		}

		// Trigger WordPress hooks for compatibility
		do_action( 'updated_post_meta', 0, $post_id, $meta_key, $meta_value );
		do_action( 'updated_postmeta', 0, $post_id, $meta_key, $meta_value );

		return $result !== false;
	}
}

