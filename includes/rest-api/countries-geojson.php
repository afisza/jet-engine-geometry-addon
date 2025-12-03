<?php
/**
 * Get countries GeoJSON REST API endpoint
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Countries_Geojson
 */
class Jet_Geometry_REST_Countries_Geojson extends Jet_Geometry_REST_Base {

	/**
	 * Get route name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'countries/geojson';
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
					'args'                => array(
						'region'     => array(
							'type'    => 'string',
							'default' => 'all',
						),
						'simplified' => array(
							'type'    => 'boolean',
							'default' => true,
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
		$region     = $request->get_param( 'region' );
		$simplified = $request->get_param( 'simplified' );

		// Get countries from taxonomy
		$terms = get_terms(
			array(
				'taxonomy'   => 'countries',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $this->success_response(
				array(
					'type'     => 'FeatureCollection',
					'features' => array(),
				)
			);
		}

		$features = array();

		foreach ( $terms as $term ) {
			// Get GeoJSON
			$meta_key = $simplified ? '_country_geojson_simplified' : '_country_geojson';
			$geojson  = get_term_meta( $term->term_id, $meta_key, true );

			// Fallback to full version if simplified not available
			if ( empty( $geojson ) && $simplified ) {
				$geojson = get_term_meta( $term->term_id, '_country_geojson', true );
			}

			if ( empty( $geojson ) ) {
				continue;
			}

			$geometry = json_decode( $geojson, true );

			if ( ! $geometry ) {
				continue;
			}

			$features[] = array(
				'type'       => 'Feature',
				'properties' => array(
					'term_id'  => $term->term_id,
					'name'     => $term->name,
					'slug'     => $term->slug,
					'iso_code' => get_term_meta( $term->term_id, '_country_iso_code', true ),
				),
				'geometry'   => $geometry,
			);
		}

		$geojson = array(
			'type'     => 'FeatureCollection',
			'features' => $features,
		);

		return new WP_REST_Response( $geojson, 200 );
	}
}













