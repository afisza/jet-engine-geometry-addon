<?php
/**
 * Country layers handler
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Country_Layers
 */
class Jet_Country_Layers {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Add country layers to map
		add_action( 'jet-engine/listings/frontend-scripts', array( $this, 'maybe_enqueue_country_layers' ) );

		// Add controls to map
		add_filter( 'jet-engine/maps-listing/content', array( $this, 'add_country_controls' ), 5, 2 );
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
	 * Register frontend assets
	 */
	public function register_assets() {
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		wp_register_script(
			'jet-country-layers',
			jet_geometry_addon()->plugin_url( 'assets/js/public/country-layers.js' ),
			array( 'jquery' ),
			JET_GEOMETRY_ADDON_VERSION,
			true
		);

		wp_register_style(
			'jet-country-layers',
			jet_geometry_addon()->plugin_url( 'assets/css/country-layers.css' ),
			array(),
			JET_GEOMETRY_ADDON_VERSION
		);
	}

	/**
	 * Maybe enqueue country layers
	 *
	 * @param object $render Render instance.
	 */
	public function maybe_enqueue_country_layers( $render ) {
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		if ( ! $render || ! method_exists( $render, 'get_render_type' ) ) {
			return;
		}

		if ( 'maps-listing' !== $render->get_render_type() ) {
			return;
		}

		// Check if country layers are enabled
		$settings = $render->get_settings();
		$enable_country_layers = ! empty( $settings['enable_country_layers'] );

		if ( ! $enable_country_layers ) {
			// Check if there's a global setting
			$enable_country_layers = get_option( 'jet_geometry_enable_country_layers', false );
		}

		if ( $enable_country_layers ) {
			wp_enqueue_script( 'jet-country-layers' );
			wp_enqueue_style( 'jet-country-layers' );

			// Localize with countries data
			$this->localize_countries_data();
		}
	}

	/**
	 * Localize countries data
	 */
	private function localize_countries_data() {
		// Try to ensure file exists
		$file_created = Jet_Geometry_Country_Geojson_File::ensure_exists();
		
		$file_url  = Jet_Geometry_Country_Geojson_File::get_file_url();
		$file_path = Jet_Geometry_Country_Geojson_File::get_file_path();
		$timestamp = Jet_Geometry_Country_Geojson_File::get_last_modified();
		$file_exists = file_exists( $file_path );

		error_log( '[Jet_Geometry] Localizing countries data: url=' . $file_url . ' path=' . $file_path . ' exists=' . ( $file_exists ? 'yes' : 'no' ) . ' created=' . ( $file_created ? 'yes' : 'no' ) . ' updated=' . $timestamp );

		wp_localize_script(
			'jet-country-layers',
			'JetCountryLayersData',
			array(
				'countries'        => array(), // kept for backward compatibility
				'countriesUrl'     => $file_url,
				'countriesUpdated' => $timestamp ? intval( $timestamp ) : null,
				'restUrl'          => rest_url( 'jet-geometry/v1/' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'defaultColor'     => '#3b82f6',
				'fillOpacity'      => floatval( get_option( 'jet_geometry_country_opacity', 0.3 ) ),
				'taxonomy'         => 'countries',
				'incidentColor'    => get_option( 'jet_geometry_country_incident_color', '#ef4444' ),
				'noIncidentColor'  => '#3b82f6',
				'incidentBorderColor'   => get_option( 'jet_geometry_country_incident_border_color', '#ef4444' ),
				'noIncidentBorderColor' => get_option( 'jet_geometry_country_no_incident_border_color', '#3b82f6' ),
				'incidentBorderWidth'   => floatval( get_option( 'jet_geometry_country_incident_border_width', 2 ) ),
				'noIncidentBorderWidth' => floatval( get_option( 'jet_geometry_country_no_incident_border_width', 1 ) ),
				'opacityMin'            => floatval( get_option( 'jet_geometry_opacity_min', 0.2 ) ),
				'opacityMax'            => floatval( get_option( 'jet_geometry_opacity_max', 0.9 ) ),
				'opacityMaxIncidents'   => intval( get_option( 'jet_geometry_opacity_max_incidents', 50 ) ),
				'incidentCounts'   => $this->get_country_incident_counts(),
				'highlightColor'   => get_option( 'jet_geometry_country_highlight_fill_color', '#f25f5c' ),
				'highlightOpacity' => floatval( get_option( 'jet_geometry_country_highlight_fill_opacity', 0.45 ) ),
				'highlightOutline' => array(
					'enabled' => get_option( 'jet_geometry_country_highlight_outline_enabled', true ),
					'color'   => get_option( 'jet_geometry_country_highlight_outline_color', '#f25f5c' ),
					'width'   => floatval( get_option( 'jet_geometry_country_highlight_outline_width', 2.5 ) ),
				),
				'i18n'             => array(
					'loading'          => __( 'Loading', 'jet-geometry-addon' ),
					'loadError'        => __( 'Failed to load incidents', 'jet-geometry-addon' ),
					'showCountryLayers' => __( 'Show Country Layers', 'jet-geometry-addon' ),
					'hideCountryLayers' => __( 'Hide Country Layers', 'jet-geometry-addon' ),
					'incidents'         => __( 'incidents', 'jet-geometry-addon' ),
					'viewAll'           => __( 'View all', 'jet-geometry-addon' ),
					'noIncidents'       => __( 'No incidents in this country', 'jet-geometry-addon' ),
					'incidentTypes'     => __( 'Incident types', 'jet-geometry-addon' ),
					'incidentTypeCount' => __( 'incidents', 'jet-geometry-addon' ),
				),
			)
		);
	}

	/**
	 * Get countries GeoJSON data
	 *
	 * @return array
	 */
	private function get_countries_geojson() {
		// Get all terms from countries taxonomy
		$terms = get_terms(
			array(
				'taxonomy'   => 'countries',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$features = array();

		foreach ( $terms as $term ) {
			// Get GeoJSON data from term meta
			$geojson = get_term_meta( $term->term_id, '_country_geojson_simplified', true );

			// Fallback to full GeoJSON if simplified is not available
			if ( empty( $geojson ) ) {
				$geojson = get_term_meta( $term->term_id, '_country_geojson', true );
			}

			if ( empty( $geojson ) ) {
				continue;
			}

			// Parse GeoJSON
			$geometry = json_decode( $geojson, true );

			if ( ! $geometry || ! isset( $geometry['coordinates'] ) ) {
				continue;
			}

			$features[] = array(
				'type'       => 'Feature',
				'properties' => array(
					'term_id'   => $term->term_id,
					'name'      => $term->name,
					'slug'      => $term->slug,
					'iso_code'  => get_term_meta( $term->term_id, '_country_iso_code', true ),
				),
				'geometry'   => $geometry,
			);
		}

		return array(
			'type'     => 'FeatureCollection',
			'features' => $features,
		);
	}

	/**
	 * Get incident counts for countries.
	 *
	 * @return array
	 */
	private function get_country_incident_counts() {
		// Check if JSON cache mode is enabled
		$cache_mode = get_option( 'jet_geometry_cache_mode', 'standard' );
		if ( $cache_mode === 'json' && class_exists( 'Jet_Geometry_Markers_Cache' ) ) {
			$cache_manager = new Jet_Geometry_Markers_Cache();
			$cached_counts = $cache_manager->get_incident_counts();
			if ( ! empty( $cached_counts ) ) {
				return $cached_counts;
			}
			// Cache miss - fall through to standard mode
		}
		
		// Standard mode - original logic
		$terms = get_terms(
			array(
				'taxonomy'   => 'countries',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array(
				'byId'   => array(),
				'bySlug' => array(),
			);
		}

		$counts = Jet_Geometry_Country_Geojson_File::get_incident_counts_for_terms( $terms );

		return array(
			'byId'   => isset( $counts['byId'] ) ? $counts['byId'] : array(),
			'bySlug' => isset( $counts['bySlug'] ) ? $counts['bySlug'] : array(),
		);
	}

	/**
	 * Add country controls to map
	 *
	 * @param string $content Map content.
	 * @param object $render  Render instance.
	 * @return string
	 */
	public function add_country_controls( $content, $render ) {
		if ( ! $render || 'maps-listing' !== $render->get_render_type() ) {
			return $content;
		}

		$settings = $render->get_settings();
		$enable_country_layers = ! empty( $settings['enable_country_layers'] ) || get_option( 'jet_geometry_enable_country_layers', false );

		if ( ! $enable_country_layers ) {
			return $content;
		}

		// Get total incidents count
		$incidents_count = $this->get_total_incidents_count();

		ob_start();
		?>
		<div class="jet-geometry-map-controls">
			<button type="button" class="jet-geometry-reset-zoom">
				<?php
				\Elementor\Icons_Manager::render_icon(
					array(
						'value'   => 'fas fa-undo',
						'library' => 'fa-solid',
					),
					array(
						'aria-hidden' => 'true',
						'class'       => 'jet-geometry-reset-zoom__icon',
					)
				);
				?>
				<span><?php esc_html_e( 'Reset Map Zoom', 'jet-geometry-addon' ); ?></span>
			</button>

			<label class="jet-country-layers-toggle">
				<input type="checkbox" id="show-country-layers" class="toggle-checkbox jet-country-layers-checkbox">
				<span class="toggle-slider"></span>
				<span class="toggle-label"><?php esc_html_e( 'Show Country Layers', 'jet-geometry-addon' ); ?></span>
			</label>

			<div class="jet-incidents-counter">
				<span class="count"><?php echo esc_html( $incidents_count ); ?></span>
				<span class="label"><?php esc_html_e( 'incidents', 'jet-geometry-addon' ); ?></span>
			</div>
		</div>
		<?php
		$controls = ob_get_clean();

		// Prepend controls to map content
		return $controls . $content;
	}

	/**
	 * Get total incidents count
	 *
	 * @return int
	 */
	private function get_total_incidents_count() {
		$count = wp_count_posts( 'incidents' );

		if ( ! $count ) {
			return 0;
		}

		return isset( $count->publish ) ? intval( $count->publish ) : 0;
	}
}




