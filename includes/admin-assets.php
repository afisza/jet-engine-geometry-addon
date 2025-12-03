<?php
/**
 * Admin assets handler
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Admin_Assets
 */
class Jet_Geometry_Admin_Assets {

	/**
	 * Geometry field instance
	 *
	 * @var Jet_Geometry_Field
	 */
	private $field;

	/**
	 * Assets loaded flag
	 *
	 * @var bool
	 */
	private $assets_loaded = false;

	/**
	 * Constructor
	 *
	 * @param Jet_Geometry_Field $field Geometry field instance.
	 */
	public function __construct( $field ) {
		$this->field = $field;

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
	}

	/**
	 * Check if we are in Elementor editor mode
	 *
	 * @return bool True if in Elementor editor, false otherwise.
	 */
	private function is_elementor_editor() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		
		// Check if in Elementor editor via GET parameter
		if ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) {
			return true;
		}
		
		// Check Elementor editor mode
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return true;
		}
		
		return false;
	}

	/**
	 * Maybe enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function maybe_enqueue_assets( $hook ) {
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		// For now, load on ALL admin pages (we can optimize later)
		if ( ! is_admin() ) {
			return;
		}

		if ( $this->assets_loaded ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Enqueue assets - public method
	 */
	public function enqueue_assets() {
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		error_log( '=== JET GEOMETRY: enqueue_assets() called ===' );
		
		$this->assets_loaded = true;
		
		// Print template in footer
		add_action( 'admin_print_footer_scripts', array( $this, 'print_field_template' ), 30 );
		
		error_log( '=== JET GEOMETRY: About to enqueue scripts ===' );
		
		// Ensure JetEngine Mapbox provider assets are loaded
		if ( function_exists( 'jet_engine' ) && jet_engine()->modules->is_module_active( 'maps-listings' ) ) {
			$provider = \Jet_Engine\Modules\Maps_Listings\Module::instance()->providers->get_active_map_provider();

			if ( $provider ) {
				$provider->register_public_assets();
				$provider->public_assets( null, array( 'marker_clustering' => false ), null );
			}
		}

		// Admin geometry field CSS (no dependencies)
		wp_enqueue_style(
			'jet-geometry-admin-field',
			jet_geometry_addon()->plugin_url( 'assets/css/admin-geometry-field.css' ),
			array(),
			JET_GEOMETRY_ADDON_VERSION
		);

		// Admin geometry field JS - NO dependencies for now
		$script_url = jet_geometry_addon()->plugin_url( 'assets/js/admin/geometry-field.js' );
		error_log( '=== JET GEOMETRY: Enqueuing script from: ' . $script_url );
		
		wp_enqueue_script(
			'jet-geometry-admin-field',
			$script_url,
			array( 'jquery', 'wp-util' ), // Removed mapbox-gl-draw dependency
			JET_GEOMETRY_ADDON_VERSION,
			true
		);
		
		// Mapbox Draw CSS - load after
		wp_enqueue_style(
			'mapbox-gl-draw',
			'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.css',
			array(),
			'1.4.3'
		);

		// Mapbox Draw JS - load after
		wp_enqueue_script(
			'mapbox-gl-draw',
			'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.js',
			array(),
			'1.4.3',
			true
		);

		error_log( '=== JET GEOMETRY: Script enqueued ===' );

		// Localize script
		$this->localize_script();
		
		error_log( '=== JET GEOMETRY: Script localized ===' );
	}

	/**
	 * Localize script with settings
	 */
	private function localize_script() {
		$mapbox_token = '';

		if ( function_exists( 'jet_engine' ) && jet_engine()->modules->is_module_active( 'maps-listings' ) ) {
			$settings     = \Jet_Engine\Modules\Maps_Listings\Module::instance()->settings;
			$mapbox_token = $settings->get( 'mapbox_access_token' );
		}

		wp_localize_script(
			'jet-geometry-admin-field',
			'JetGeometrySettings',
			array(
				'mapboxToken'     => $mapbox_token,
				'restUrl'         => rest_url( 'jet-geometry/v1/' ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'defaultCenter'   => array( 0, 0 ),
				'defaultZoom'     => 2,
				'i18n'            => array(
					'searchPlaceholder' => __( 'Search for location...', 'jet-geometry-addon' ),
					'loading'           => __( 'Loading...', 'jet-geometry-addon' ),
					'notFound'          => __( 'Location not found', 'jet-geometry-addon' ),
					'drawPin'           => __( 'Click to place pin', 'jet-geometry-addon' ),
					'drawLine'          => __( 'Click to start line', 'jet-geometry-addon' ),
					'drawPolygon'       => __( 'Click to start polygon', 'jet-geometry-addon' ),
					'resetLocation'     => __( 'Reset location', 'jet-geometry-addon' ),
					'deleteGeometry'    => __( 'Delete geometry', 'jet-geometry-addon' ),
					'editGeometry'      => __( 'Edit geometry', 'jet-geometry-addon' ),
					'typePin'           => __( 'Pin', 'jet-geometry-addon' ),
					'typeLine'          => __( 'Line', 'jet-geometry-addon' ),
					'typePolygon'       => __( 'Polygon', 'jet-geometry-addon' ),
					'enterCoordinates'  => __( 'Enter coordinates (lat, lng)', 'jet-geometry-addon' ),
					'invalidCoordinates' => __( 'Invalid coordinates format', 'jet-geometry-addon' ),
				),
			)
		);
	}

	/**
	 * Print field template
	 */
	public function print_field_template() {
		if ( ! $this->assets_loaded ) {
			return;
		}
		?>
		<script type="text/html" id="tmpl-jet-geometry-field">
			<div class="jet-geometry-field__container">
				<!-- Geometry Type Selector -->
				<# if ( data.geometryTypes && data.geometryTypes.length > 1 ) { #>
				<div class="jet-geometry-field__type-selector">
					<label><?php esc_html_e( 'Geometry Type:', 'jet-geometry-addon' ); ?></label>
					<# _.each( data.geometryTypes, function( type ) { #>
						<button 
							type="button"
							class="button jet-geometry-type-btn" 
							data-type="{{ type }}"
							<# if ( type === data.currentType ) { #>aria-pressed="true"<# } #>
						>
							<# if ( type === 'pin' ) { #>
								<span class="dashicons dashicons-location"></span> <?php esc_html_e( 'Pin', 'jet-geometry-addon' ); ?>
							<# } else if ( type === 'line' ) { #>
								<span class="dashicons dashicons-minus"></span> <?php esc_html_e( 'Line', 'jet-geometry-addon' ); ?>
							<# } else if ( type === 'polygon' ) { #>
								<span class="dashicons dashicons-admin-site"></span> <?php esc_html_e( 'Polygon', 'jet-geometry-addon' ); ?>
							<# } #>
						</button>
					<# }); #>
				</div>
				<# } #>

				<!-- Search Box -->
				<div class="jet-geometry-field__search">
					<input 
						type="text" 
						class="widefat jet-geometry-search-input" 
						placeholder="<?php esc_attr_e( 'Search for location...', 'jet-geometry-addon' ); ?>"
					>
					<div class="jet-geometry-field__search-results"></div>
				</div>

				<!-- Coordinates Input -->
				<div class="jet-geometry-field__coords">
					<input 
						type="text" 
						class="jet-geometry-coords-input" 
						placeholder="<?php esc_attr_e( 'Or enter coordinates (lat, lng)', 'jet-geometry-addon' ); ?>"
					>
					<button type="button" class="button jet-geometry-set-coords-btn">
						<?php esc_html_e( 'Set', 'jet-geometry-addon' ); ?>
					</button>
				</div>

				<!-- Map Container -->
				<div class="jet-geometry-field__map" style="height: {{{ data.height }}}px;"></div>

				<!-- Geometry Info -->
				<div class="jet-geometry-field__info">
					<span class="jet-geometry-current-type"></span>
					<span class="jet-geometry-current-coords"></span>
				</div>

				<!-- Actions -->
				<div class="jet-geometry-field__actions">
					<button type="button" class="button jet-geometry-reset-btn">
						<span class="dashicons dashicons-image-rotate"></span>
						<?php esc_html_e( 'Reset', 'jet-geometry-addon' ); ?>
					</button>
					<button type="button" class="button jet-geometry-delete-btn">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Delete', 'jet-geometry-addon' ); ?>
					</button>
				</div>
			</div>
		</script>
		<?php
	}
}

