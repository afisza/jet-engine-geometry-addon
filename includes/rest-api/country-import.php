<?php
/**
 * Country import REST API endpoint
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Country_Import
 */
class Jet_Geometry_REST_Country_Import extends Jet_Geometry_REST_Base {

	/**
	 * Get route name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'countries/import';
	}

	/**
	 * Register route
	 */
	public function register_route() {
		register_rest_route(
			$this->namespace,
			'/' . $this->get_name(),
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'callback' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array(
						'source'     => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'natural-earth', 'custom' ),
						),
						'resolution' => array(
							'type'    => 'string',
							'default' => '50m',
							'enum'    => array( '10m', '50m', '110m' ),
						),
						'region'     => array(
							'type'    => 'string',
							'default' => 'world',
							'enum'    => array( 'europe', 'world' ),
						),
						'custom_url' => array(
							'type' => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Callback
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function callback( $request ) {
		$source     = $request->get_param( 'source' );
		$resolution = $request->get_param( 'resolution' );
		$region     = $request->get_param( 'region' );
		$custom_url = $request->get_param( 'custom_url' );

		// Get GeoJSON URL
		$url = $this->get_geojson_url( $source, $resolution, $custom_url );

		if ( ! $url ) {
			return $this->error_response( __( 'Invalid source or URL', 'jet-geometry-addon' ) );
		}

		// Fetch GeoJSON
		$response = wp_remote_get( $url, array( 'timeout' => 60 ) );

		if ( is_wp_error( $response ) ) {
			return $this->error_response( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['features'] ) ) {
			return $this->error_response( __( 'Invalid GeoJSON data', 'jet-geometry-addon' ) );
		}

		// Process features
		$result = $this->process_countries( $data['features'], $region );

		return $this->success_response( $result );
	}

	/**
	 * Get GeoJSON URL
	 *
	 * @param string $source     Source name.
	 * @param string $resolution Resolution.
	 * @param string $custom_url Custom URL.
	 * @return string|false
	 */
	private function get_geojson_url( $source, $resolution, $custom_url ) {
		if ( 'custom' === $source && ! empty( $custom_url ) ) {
			return esc_url_raw( $custom_url );
		}

		if ( 'natural-earth' === $source ) {
			$file = 'ne_' . $resolution . '_admin_0_countries.geojson';
			return 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/' . $file;
		}

		return false;
	}

	/**
	 * Get ISO code from feature properties
	 * Natural Earth uses -99 as placeholder for missing ISO codes
	 * ISO_A2_EH is more flexible and usually has valid codes
	 *
	 * @param array $properties Feature properties.
	 * @return string ISO code or empty string.
	 */
	private function get_iso_code( $properties ) {
		// First try ISO_A2_EH (more flexible interpretation)
		if ( isset( $properties['ISO_A2_EH'] ) && ! empty( $properties['ISO_A2_EH'] ) && $properties['ISO_A2_EH'] !== '-99' ) {
			$iso_code = trim( $properties['ISO_A2_EH'] );
			// Validate: should be 2 uppercase letters
			if ( strlen( $iso_code ) === 2 && ctype_alpha( $iso_code ) ) {
				return strtoupper( $iso_code );
			}
		}

		// Fallback to ISO_A2, but skip -99 values
		if ( isset( $properties['ISO_A2'] ) && ! empty( $properties['ISO_A2'] ) && $properties['ISO_A2'] !== '-99' ) {
			$iso_code = trim( $properties['ISO_A2'] );
			// Validate: should be 2 uppercase letters
			if ( strlen( $iso_code ) === 2 && ctype_alpha( $iso_code ) ) {
				return strtoupper( $iso_code );
			}
		}

		return '';
	}

	/**
	 * Process countries
	 *
	 * @param array  $features Country features.
	 * @param string $region   Region filter.
	 * @return array
	 */
	private function process_countries( $features, $region ) {
		$imported = 0;
		$updated  = 0;
		$errors   = array();

		foreach ( $features as $feature ) {
			if ( empty( $feature['properties']['NAME'] ) || empty( $feature['geometry'] ) ) {
				continue;
			}

			$country_name = $feature['properties']['NAME'];
			$iso_code     = $this->get_iso_code( $feature['properties'] );

			// Filter by region if needed
			if ( 'europe' === $region ) {
				$continent = isset( $feature['properties']['CONTINENT'] ) ? $feature['properties']['CONTINENT'] : '';
				if ( 'Europe' !== $continent ) {
					continue;
				}
			}

			// Match with taxonomy term
			$term_id = Jet_Geometry_Utils::match_country_term( $country_name, $iso_code );

			if ( ! $term_id ) {
				// Try to create term
				$term = wp_insert_term( $country_name, 'countries' );
				if ( is_wp_error( $term ) ) {
					$errors[] = sprintf( 'Failed to create term for %s: %s', $country_name, $term->get_error_message() );
					continue;
				}
				$term_id = $term['term_id'];
				$imported++;
			} else {
				$updated++;
			}

			// Extract main polygon from MultiPolygon if needed (for countries with overseas territories)
			$geometry_to_save = $this->extract_main_polygon( $feature['geometry'] );

			// Save GeoJSON to term meta
			$geojson = wp_json_encode( $geometry_to_save );
			update_term_meta( $term_id, '_country_geojson', $geojson );

			// Simplify and save
			$simplified = $this->simplify_geometry( $geometry_to_save );
			if ( $simplified ) {
				update_term_meta( $term_id, '_country_geojson_simplified', wp_json_encode( $simplified ) );
			}

			// Save ISO code (only if valid)
			if ( $iso_code ) {
				update_term_meta( $term_id, '_country_iso_code', $iso_code );
			}

			// Save metadata
			update_term_meta( $term_id, '_country_geojson_source', 'Natural Earth ' . $region );
			update_term_meta( $term_id, '_country_geojson_imported', current_time( 'mysql' ) );
		}

		Jet_Geometry_Country_Geojson_File::regenerate();

		return array(
			'imported' => $imported,
			'updated'  => $updated,
			'errors'   => $errors,
		);
	}

	/**
	 * Extract main polygon from MultiPolygon.
	 * For countries with multiple territories, selects the largest one (usually the mainland).
	 *
	 * @param array $geometry Geometry data.
	 * @return array Geometry with main polygon only.
	 */
	private function extract_main_polygon( $geometry ) {
		if ( ! isset( $geometry['type'], $geometry['coordinates'] ) ) {
			return $geometry;
		}

		// If it's already a Polygon, return as is
		if ( 'Polygon' === $geometry['type'] ) {
			return $geometry;
		}

		// If it's MultiPolygon, find the largest part
		if ( 'MultiPolygon' === $geometry['type'] ) {
			$polygons = $geometry['coordinates'];
			
			if ( empty( $polygons ) ) {
				return $geometry;
			}

			// If only one polygon, convert to Polygon
			if ( count( $polygons ) === 1 ) {
				return array(
					'type'        => 'Polygon',
					'coordinates' => $polygons[0],
				);
			}

			// Find the largest polygon by counting points in the outer ring
			$largest_index = 0;
			$largest_size  = 0;

			foreach ( $polygons as $index => $polygon ) {
				if ( ! empty( $polygon[0] ) && is_array( $polygon[0] ) ) {
					$size = count( $polygon[0] );
					if ( $size > $largest_size ) {
						$largest_size  = $size;
						$largest_index = $index;
					}
				}
			}

			// Return the largest polygon as a single Polygon
			return array(
				'type'        => 'Polygon',
				'coordinates' => $polygons[ $largest_index ],
			);
		}

		// For other types, return as is
		return $geometry;
	}

	/**
	 * Simplify geometry (basic implementation)
	 *
	 * @param array $geometry Geometry data.
	 * @return array|false
	 */
	private function simplify_geometry( $geometry ) {
		// Basic simplification: reduce points by taking every Nth point
		if ( ! isset( $geometry['type'], $geometry['coordinates'] ) ) {
			return false;
		}

		$type = $geometry['type'];

		if ( 'Polygon' === $type ) {
			$simplified_coords = array();
			foreach ( $geometry['coordinates'] as $ring ) {
				$simplified_coords[] = $this->reduce_points( $ring, 5 );
			}

			return array(
				'type'        => 'Polygon',
				'coordinates' => $simplified_coords,
			);
		}

		if ( 'MultiPolygon' === $type ) {
			$simplified_coords = array();
			foreach ( $geometry['coordinates'] as $polygon ) {
				$simplified_polygon = array();
				foreach ( $polygon as $ring ) {
					$simplified_polygon[] = $this->reduce_points( $ring, 5 );
				}
				$simplified_coords[] = $simplified_polygon;
			}

			return array(
				'type'        => 'MultiPolygon',
				'coordinates' => $simplified_coords,
			);
		}

		return $geometry;
	}

	/**
	 * Reduce points in coordinate array
	 *
	 * @param array $coords Coordinates.
	 * @param int   $factor Reduction factor.
	 * @return array
	 */
	private function reduce_points( $coords, $factor ) {
		if ( count( $coords ) <= $factor * 2 ) {
			return $coords;
		}

		$reduced = array();
		$total   = count( $coords );

		for ( $i = 0; $i < $total; $i += $factor ) {
			$reduced[] = $coords[ $i ];
		}

		// Always include last point to close polygon
		if ( end( $reduced ) !== end( $coords ) ) {
			$reduced[] = end( $coords );
		}

		return $reduced;
	}
}




