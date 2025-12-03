<?php
/**
 * Get country incidents REST API endpoint
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Country_Incidents
 */
class Jet_Geometry_REST_Country_Incidents extends Jet_Geometry_REST_Base {

	/**
	 * Get route name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'country-incidents/(?P<term_id>\d+)';
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
						'term_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'limit'   => array(
							'type'    => 'integer',
							'default' => 5,
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
		$term_id = $request->get_param( 'term_id' );
		$limit   = $request->get_param( 'limit' );

		// Get country term
		$term = get_term( $term_id, 'countries' );

		if ( ! $term || is_wp_error( $term ) ) {
			return $this->error_response( __( 'Country not found', 'jet-geometry-addon' ), 404 );
		}

		// Query incidents for this country
		$query_args = array(
			'post_type'      => 'incidents',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'countries',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);

		$query = new WP_Query( $query_args );

		$incidents = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_id = get_the_ID();

				$incidents[] = array(
					'id'       => $post_id,
					'title'    => get_the_title(),
					'date'     => get_the_date( 'Y-m-d' ),
					'location' => $this->get_incident_location( $post_id ),
					'url'      => get_permalink( $post_id ),
				);
			}

			wp_reset_postdata();
		}

		// Get total count
		$total_query = new WP_Query(
			array_merge(
				$query_args,
				array(
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);

		$total        = $total_query->found_posts;
		$all_post_ids = is_array( $total_query->posts ) ? $total_query->posts : array();

		$types_summary = $this->get_incident_types_summary( $all_post_ids );

		return $this->success_response(
			array(
				'country'   => array(
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
				),
				'incidents' => $incidents,
				'total'     => $total,
				'types'     => $types_summary['types'],
				'types_total' => $types_summary['total'],
			)
		);
	}

	/**
	 * Get incident location
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_incident_location( $post_id ) {
		// Try to get location from meta fields
		$location = get_post_meta( $post_id, 'location', true );

		if ( ! $location ) {
			// Try to extract from geometry
			$all_meta = get_post_meta( $post_id );

			foreach ( $all_meta as $meta_key => $meta_value ) {
				if ( false !== strpos( $meta_key, '_lat' ) && ! empty( $meta_value[0] ) ) {
					$prefix = str_replace( '_lat', '', $meta_key );
					$lat    = $meta_value[0];
					$lng    = get_post_meta( $post_id, $prefix . '_lng', true );

					if ( $lat && $lng ) {
						$location = Jet_Geometry_Utils::format_coordinates( $lat, $lng, 4 );
						break;
					}
				}
			}
		}

		return $location;
	}

	/**
	 * Build incident types summary for given posts.
	 *
	 * @param array $post_ids Post IDs.
	 * @return array
	 */
	private function get_incident_types_summary( $post_ids ) {
		$summary = array(
			'types' => array(),
			'total' => 0,
		);

		if ( empty( $post_ids ) ) {
			return $summary;
		}

		$type_counts = array();

		foreach ( $post_ids as $post_id ) {
			$terms = wp_get_post_terms( $post_id, 'incident-type' );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$term_id = (int) $term->term_id;

				if ( ! isset( $type_counts[ $term_id ] ) ) {
					$type_counts[ $term_id ] = array(
						'term_id' => $term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
						'count'   => 0,
					);
				}

				$type_counts[ $term_id ]['count']++;
				$summary['total']++;
			}
		}

		if ( empty( $type_counts ) ) {
			return $summary;
		}

		usort(
			$type_counts,
			function( $a, $b ) {
				if ( $a['count'] === $b['count'] ) {
					return strcmp( $a['name'], $b['name'] );
				}

				return ( $a['count'] > $b['count'] ) ? -1 : 1;
			}
		);

		$summary['types'] = array_values( $type_counts );

		return $summary;
	}
}




