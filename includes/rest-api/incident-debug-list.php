<?php
/**
 * Incident Debug List REST API endpoint
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Incident_Debug_List
 */
class Jet_Geometry_REST_Incident_Debug_List extends Jet_Geometry_REST_Base {

	/**
	 * REST route name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'incident-debug-list';
	}

	/**
	 * Register route.
	 */
	public function register_route() {
		register_rest_route(
			$this->namespace,
			$this->get_name(),
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'callback' ),
					'permission_callback' => array( $this, 'admin_permission_callback' ),
					'args'                => array(
						'per_page' => array(
							'default'           => 100,
							'sanitize_callback' => 'absint',
						),
						'page'     => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Callback to get incident debug list.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function callback( $request ) {
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$query_args = array(
			'post_type'      => 'incidents',
			'posts_per_page' => $per_page,
			'paged'           => $page,
			'post_status'     => 'any',
			'orderby'         => 'ID',
			'order'           => 'DESC',
		);

		$query = new WP_Query( $query_args );

		$posts = array();

		foreach ( $query->posts as $post ) {
			$post_data = $this->analyze_post( $post );
			$posts[]   = $post_data;
		}

		$response_data = array(
			'posts'      => $posts,
			'total'      => $query->found_posts,
			'total_pages' => $query->max_num_pages,
			'per_page'   => $per_page,
			'page'       => $page,
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Analyze a post and return debug information.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Debug information array.
	 */
	private function analyze_post( $post ) {
		$post_id = $post->ID;

		// Get country taxonomy
		$countries = wp_get_post_terms( $post_id, 'countries', array( 'fields' => 'names' ) );
		$country   = ! empty( $countries ) ? implode( ', ', $countries ) : '—';

		// Get address field
		$address = get_post_meta( $post_id, 'address', true );
		if ( is_array( $address ) && ! empty( $address[0] ) ) {
			$address = $address[0];
		}

		// Get incident_geometry
		$incident_geometry = get_post_meta( $post_id, 'incident_geometry', true );
		if ( is_array( $incident_geometry ) && ! empty( $incident_geometry[0] ) ) {
			$incident_geometry = $incident_geometry[0];
		}

		// Check for hash-prefixed fields
		$all_meta      = get_post_meta( $post_id );
		$has_hash_lat  = false;
		$has_hash_lng  = false;
		$hash_prefix   = '';
		foreach ( $all_meta as $meta_key => $meta_value ) {
			if ( preg_match( '/^([a-f0-9]{32})_lat$/i', $meta_key, $matches ) ) {
				$has_hash_lat = true;
				$hash_prefix  = $matches[1];
				break;
			}
		}
		if ( $hash_prefix ) {
			$has_hash_lng = metadata_exists( 'post', $post_id, $hash_prefix . '_lng' );
		}

		// Analyze address issues
		$address_issue      = '';
		$address_issue_code = 'none';
		$additional_info    = array();

		if ( empty( $address ) ) {
			$address_issue      = 'Brak adresu';
			$address_issue_code = 'no_address';
		} else {
			// Check for encoding issues (special characters like ø, æ, å)
			if ( preg_match( '/[øæåØÆÅ]/u', $address ) ) {
				// Check if address contains special characters
				$address_issue      = 'Adres zawiera znaki specjalne (ø, æ, å) - możliwe problemy z encoding';
				$address_issue_code = 'encoding_issue';
			}

			// Check if address is valid UTF-8
			if ( ! mb_check_encoding( $address, 'UTF-8' ) ) {
				$address_issue      = 'Adres nie jest poprawnym UTF-8';
				$address_issue_code = 'invalid_encoding';
			}
		}

		// Analyze geometry
		$has_valid_geometry = false;
		$geometry_type      = '';
		$geometry_issue      = '';

		if ( $incident_geometry ) {
			$geometry = json_decode( $incident_geometry, true );
			if ( $geometry && isset( $geometry['type'] ) && isset( $geometry['coordinates'] ) ) {
				$geometry_type = $geometry['type'];
				$has_valid_geometry = $this->validate_geometry( $geometry );
				if ( ! $has_valid_geometry ) {
					$geometry_issue = 'Nieprawidłowa struktura geometry';
				}
			} else {
				$geometry_issue = 'Nieprawidłowy format JSON';
			}
		} else {
			$geometry_issue = 'Brak danych geometry';
		}

		// Check if post has coordinates from hash-prefixed fields
		if ( $has_hash_lat && $has_hash_lng ) {
			$hash_lat = get_post_meta( $post_id, $hash_prefix . '_lat', true );
			$hash_lng = get_post_meta( $post_id, $hash_prefix . '_lng', true );
			if ( is_array( $hash_lat ) && ! empty( $hash_lat[0] ) ) {
				$hash_lat = $hash_lat[0];
			}
			if ( is_array( $hash_lng ) && ! empty( $hash_lng[0] ) ) {
				$hash_lng = $hash_lng[0];
			}
			if ( $hash_lat && $hash_lng ) {
				$additional_info[] = sprintf( 'Hash-prefixed coordinates: lat=%s, lng=%s', $hash_lat, $hash_lng );
			}
		}

		// Determine if post should appear on map
		$should_appear_on_map = false;
		$map_issue            = '';

		if ( $has_valid_geometry ) {
			$should_appear_on_map = true;
		} elseif ( $has_hash_lat && $has_hash_lng ) {
			$hash_lat = get_post_meta( $post_id, $hash_prefix . '_lat', true );
			$hash_lng = get_post_meta( $post_id, $hash_prefix . '_lng', true );
			if ( is_array( $hash_lat ) && ! empty( $hash_lat[0] ) ) {
				$hash_lat = $hash_lat[0];
			}
			if ( is_array( $hash_lng ) && ! empty( $hash_lng[0] ) ) {
				$hash_lng = $hash_lng[0];
			}
			if ( floatval( $hash_lat ) && floatval( $hash_lng ) ) {
				$should_appear_on_map = true;
			} else {
				$map_issue = 'Hash-prefixed coordinates są puste lub nieprawidłowe';
			}
		} elseif ( ! empty( $address ) ) {
			// Post has address but no geometry - might need geocoding
			$map_issue = 'Brak geometry - wymaga geokodowania adresu';
		} else {
			$map_issue = 'Brak adresu i geometry';
		}

		// Build additional info string
		$additional_info_string = '';
		if ( ! empty( $additional_info ) ) {
			$additional_info_string = implode( '; ', $additional_info );
		}
		if ( $geometry_issue ) {
			$additional_info_string .= ( $additional_info_string ? '; ' : '' ) . $geometry_issue;
		}
		if ( $map_issue && ! $should_appear_on_map ) {
			$additional_info_string .= ( $additional_info_string ? '; ' : '' ) . $map_issue;
		}
		if ( empty( $additional_info_string ) ) {
			$additional_info_string = 'OK';
		}

		return array(
			'id'                    => $post_id,
			'title'                 => $post->post_title,
			'status'                 => $post->post_status,
			'country'                => $country,
			'address'                => $address ? $address : '—',
			'address_issue'          => $address_issue,
			'address_issue_code'     => $address_issue_code,
			'has_geometry'           => ! empty( $incident_geometry ),
			'geometry_type'          => $geometry_type ? $geometry_type : '—',
			'has_valid_geometry'     => $has_valid_geometry,
			'has_hash_fields'        => $has_hash_lat && $has_hash_lng,
			'should_appear_on_map'   => $should_appear_on_map,
			'additional_info'        => $additional_info_string,
		);
	}

	/**
	 * Validate geometry structure.
	 *
	 * @param array $geometry Geometry array.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_geometry( $geometry ) {
		if ( ! isset( $geometry['type'] ) || ! isset( $geometry['coordinates'] ) ) {
			return false;
		}

		switch ( $geometry['type'] ) {
			case 'Point':
				if ( ! is_array( $geometry['coordinates'] ) || count( $geometry['coordinates'] ) < 2 ) {
					return false;
				}
				$lng = floatval( $geometry['coordinates'][0] );
				$lat = floatval( $geometry['coordinates'][1] );
				return ( $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 );

			case 'LineString':
				if ( ! is_array( $geometry['coordinates'] ) || count( $geometry['coordinates'] ) < 2 ) {
					return false;
				}
				foreach ( $geometry['coordinates'] as $coord ) {
					if ( ! is_array( $coord ) || count( $coord ) < 2 ) {
						return false;
					}
					$lng = floatval( $coord[0] );
					$lat = floatval( $coord[1] );
					if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
						return false;
					}
				}
				return true;

			case 'Polygon':
				if ( ! is_array( $geometry['coordinates'] ) || empty( $geometry['coordinates'] ) ) {
					return false;
				}
				foreach ( $geometry['coordinates'] as $ring ) {
					if ( ! is_array( $ring ) || count( $ring ) < 3 ) {
						return false;
					}
					foreach ( $ring as $coord ) {
						if ( ! is_array( $coord ) || count( $coord ) < 2 ) {
							return false;
						}
						$lng = floatval( $coord[0] );
						$lat = floatval( $coord[1] );
						if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
							return false;
						}
					}
				}
				return true;

			default:
				return false;
		}
	}

	/**
	 * Admin permission callback.
	 *
	 * @return bool
	 */
	public function admin_permission_callback() {
		return current_user_can( 'manage_options' );
	}
}













