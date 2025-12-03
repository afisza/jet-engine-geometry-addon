<?php
/**
 * Validate geometry REST API endpoint
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Validate
 */
class Jet_Geometry_REST_Validate extends Jet_Geometry_REST_Base {

	/**
	 * Get route name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'validate-geometry';
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
						'geometry' => array(
							'required'          => true,
							'type'              => 'object',
							'validate_callback' => function( $param, $request, $key ) {
								return is_array( $param ) || is_string( $param );
							},
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
		$geometry = $request->get_param( 'geometry' );

		// Validate GeoJSON
		$validated = Jet_Geometry_Utils::validate_geojson( $geometry );

		if ( is_wp_error( $validated ) ) {
			return $this->error_response( $validated->get_error_message() );
		}

		// Calculate centroid
		$centroid = $this->calculate_centroid( $validated );

		// Get geometry type
		$type = Jet_Geometry_Utils::get_geometry_type( $validated );

		return $this->success_response(
			array(
				'geometry' => $validated,
				'centroid' => $centroid,
				'type'     => $type,
			)
		);
	}

	/**
	 * Calculate centroid
	 *
	 * @param array $geojson GeoJSON data.
	 * @return array [lng, lat]
	 */
	private function calculate_centroid( $geojson ) {
		if ( empty( $geojson['type'] ) || empty( $geojson['coordinates'] ) ) {
			return array( 0, 0 );
		}

		switch ( $geojson['type'] ) {
			case 'Point':
				return $geojson['coordinates'];

			case 'LineString':
				return Jet_Geometry_Utils::calculate_line_centroid( $geojson['coordinates'] );

			case 'Polygon':
				return Jet_Geometry_Utils::calculate_polygon_centroid( $geojson['coordinates'] );

			default:
				return array( 0, 0 );
		}
	}
}













