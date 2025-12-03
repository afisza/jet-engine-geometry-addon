<?php
/**
 * Base REST API endpoint
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_REST_Base
 */
abstract class Jet_Geometry_REST_Base {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace = 'jet-geometry/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Route registration is handled by register_rest_routes() in the main plugin file
		// to ensure it's called on the 'rest_api_init' hook
	}

	/**
	 * Register route
	 */
	abstract public function register_route();

	/**
	 * Get route name
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Get callback
	 *
	 * @return callable
	 */
	abstract public function callback( $request );

	/**
	 * Check permissions
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Public permission callback
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function public_permission_callback( $request ) {
		return true;
	}

	/**
	 * Success response
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success_response( $data, $status = 200 ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Error response
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	protected function error_response( $message, $status = 400 ) {
		return new WP_Error( 'error', $message, array( 'status' => $status ) );
	}
}













