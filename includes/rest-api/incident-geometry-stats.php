<?php
/**
 * Incident Geometry Statistics REST API endpoint
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Incident_Geometry_Stats
 */
class Jet_Geometry_REST_Incident_Geometry_Stats extends Jet_Geometry_REST_Base {

	/**
	 * Get route name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'incident-geometry-stats';
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'callback' ),
					'permission_callback' => array( $this, 'public_permission_callback' ),
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
		// Get all published incidents
		$query_args = array(
			'post_type'      => 'incidents',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$query = new WP_Query( $query_args );
		$total_posts = $query->found_posts;

		$stats = array(
			'total'              => $total_posts,
			'with_geometry'      => 0,
			'without_geometry'   => 0,
			'invalid_geometry'   => 0,
			'with_hash_fields'   => 0,
			'without_hash_fields' => 0,
		);

		// Check each post
		foreach ( $query->posts as $post_id ) {
			// Check incident_geometry field
			$incident_geometry = get_post_meta( $post_id, 'incident_geometry', true );
			
			// Normalize if array
			if ( is_array( $incident_geometry ) && ! empty( $incident_geometry[0] ) ) {
				$incident_geometry = $incident_geometry[0];
			}

			if ( $incident_geometry ) {
				// Try to decode JSON
				$geometry = json_decode( $incident_geometry, true );
				
				if ( $geometry && isset( $geometry['type'] ) && isset( $geometry['coordinates'] ) ) {
					// Validate geometry based on type (Pin=Point, Line=LineString, Polygon=Polygon)
					$is_valid = false;
					
					switch ( $geometry['type'] ) {
						case 'Point':
							// Point: coordinates should be [lng, lat] array with 2 elements
							if ( is_array( $geometry['coordinates'] ) && count( $geometry['coordinates'] ) >= 2 ) {
								$lng = floatval( $geometry['coordinates'][0] );
								$lat = floatval( $geometry['coordinates'][1] );
								// Validate coordinate ranges
								if ( $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 ) {
									$is_valid = true;
								}
							}
							break;
							
						case 'LineString':
							// LineString: coordinates should be array of [lng, lat] arrays
							if ( is_array( $geometry['coordinates'] ) && count( $geometry['coordinates'] ) >= 2 ) {
								$all_valid = true;
								foreach ( $geometry['coordinates'] as $coord ) {
									if ( ! is_array( $coord ) || count( $coord ) < 2 ) {
										$all_valid = false;
										break;
									}
									$lng = floatval( $coord[0] );
									$lat = floatval( $coord[1] );
									if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
										$all_valid = false;
										break;
									}
								}
								$is_valid = $all_valid;
							}
							break;
							
						case 'Polygon':
							// Polygon: coordinates should be array of rings (each ring is array of [lng, lat] arrays)
							if ( is_array( $geometry['coordinates'] ) && ! empty( $geometry['coordinates'] ) ) {
								$all_valid = true;
								foreach ( $geometry['coordinates'] as $ring ) {
									if ( ! is_array( $ring ) || count( $ring ) < 3 ) { // Polygon needs at least 3 points
										$all_valid = false;
										break;
									}
									foreach ( $ring as $coord ) {
										if ( ! is_array( $coord ) || count( $coord ) < 2 ) {
											$all_valid = false;
											break 2;
										}
										$lng = floatval( $coord[0] );
										$lat = floatval( $coord[1] );
										if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
											$all_valid = false;
											break 2;
										}
									}
								}
								$is_valid = $all_valid;
							}
							break;
							
						default:
							// Unknown geometry type
							$is_valid = false;
							break;
					}
					
					if ( $is_valid ) {
						$stats['with_geometry']++;
					} else {
						$stats['invalid_geometry']++;
					}
				} else {
					$stats['invalid_geometry']++;
				}
			} else {
				$stats['without_geometry']++;
			}

			// Also check for hash-prefixed fields (e.g., 4e3d8c7b47e499ebe6983d9555fa1bb8_lat)
			$all_meta = get_post_meta( $post_id );
			$has_hash_fields = false;
			
			foreach ( $all_meta as $meta_key => $meta_value ) {
				if ( preg_match( '/^[a-f0-9]{32}_(lat|lng|geometry_data|geometry_type)$/i', $meta_key ) ) {
					$has_hash_fields = true;
					break;
				}
			}

			if ( $has_hash_fields ) {
				$stats['with_hash_fields']++;
			} else {
				$stats['without_hash_fields']++;
			}
		}

		// Calculate percentages
		$stats['with_geometry_percent'] = $total_posts > 0 ? round( ( $stats['with_geometry'] / $total_posts ) * 100, 2 ) : 0;
		$stats['without_geometry_percent'] = $total_posts > 0 ? round( ( $stats['without_geometry'] / $total_posts ) * 100, 2 ) : 0;
		$stats['with_hash_fields_percent'] = $total_posts > 0 ? round( ( $stats['with_hash_fields'] / $total_posts ) * 100, 2 ) : 0;

		return new WP_REST_Response( $stats, 200 );
	}
}

