<?php
/**
 * REST endpoint to log frontend map statistics.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Markers_Debug
 */
class Jet_Geometry_REST_Markers_Debug extends Jet_Geometry_REST_Base {

	/**
	 * REST route name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'markers-debug';
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
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'callback' ),
					'permission_callback' => array( $this, 'public_permission_callback' ),
				),
			)
		);
	}

	/**
	 * Default callback proxy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_REST_Response|WP_Error
	 */
	public function callback( $request ) {
		return $this->log_markers( $request );
	}

	/**
	 * Handle log request.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|array
	 */
	public function log_markers( $request ) {
		$params = $request->get_params();

		$total     = isset( $params['total'] ) ? intval( $params['total'] ) : 0;
		$breakdown = isset( $params['breakdown'] ) && is_array( $params['breakdown'] ) ? $params['breakdown'] : array();
		$missing   = isset( $params['missing'] ) ? intval( $params['missing'] ) : 0;
		$page      = isset( $params['page'] ) ? esc_url_raw( $params['page'] ) : '';
		$map_id    = isset( $params['mapId'] ) ? sanitize_text_field( wp_unslash( $params['mapId'] ) ) : '';

		$message = sprintf(
			'[JetGeometry][MapStats] total=%1$d missing_country=%2$d map=%3$s page=%4$s breakdown=%5$s',
			$total,
			$missing,
			$map_id ? $map_id : 'n/a',
			$page ? $page : 'n/a',
			wp_json_encode( $breakdown )
		);

		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		return array(
			'success' => true,
		);
	}
}

