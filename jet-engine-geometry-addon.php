<?php
/**
 * Plugin Name: JetEngine Geometry Addon
 * Plugin URI: https://github.com/afisza/jet-engine-geometry-addon
 * Description: Extends JetEngine Maps Listing with Line and Polygon geometry support, plus country layers integration with Mapbox API
 * Version: 1.0.3.9
 * Author: Alex Shram
 * Author URI: https://afisza.com
 * Text Domain: jet-geometry-addon
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'JET_GEOMETRY_ADDON_VERSION', '1.0.3.9' );
define( 'JET_GEOMETRY_ADDON_FILE', __FILE__ );
define( 'JET_GEOMETRY_ADDON_PATH', plugin_dir_path( __FILE__ ) );
define( 'JET_GEOMETRY_ADDON_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class Jet_Engine_Geometry_Addon {

	/**
	 * Instance of the class
	 *
	 * @var Jet_Engine_Geometry_Addon
	 */
	private static $instance = null;

	/**
	 * Geometry field handler
	 *
	 * @var Jet_Geometry_Field
	 */
	public $geometry_field = null;

	/**
	 * Geometry renderer
	 *
	 * @var Jet_Geometry_Renderer
	 */
	public $renderer = null;

	/**
	 * Country layers handler
	 *
	 * @var Jet_Country_Layers
	 */
	public $country_layers = null;

	/**
	 * Admin settings
	 *
	 * @var Jet_Geometry_Admin_Settings
	 */
	public $admin_settings = null;

	/**
	 * Filters integration instance
	 *
	 * @var Jet_Geometry_Filters_Integration
	 */
	public $filters_integration = null;

	/**
	 * Markers cache instance
	 *
	 * @var Jet_Geometry_Markers_Cache
	 */
	public $markers_cache = null;

	/**
	 * Constructor
	 */
	private function __construct() {
		// Check if JetEngine is active - do it earlier
		add_action( 'plugins_loaded', array( $this, 'check_jetengine_exists' ), 10 );
		
		// Initialize after JetEngine is fully loaded
		add_action( 'jet-engine/init', array( $this, 'check_dependencies' ), 20 );
	}

	/**
	 * Check if JetEngine exists at all
	 */
	public function check_jetengine_exists() {
		if ( ! function_exists( 'jet_engine' ) || ! class_exists( 'Jet_Engine' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_jetengine' ) );
		}
	}

	/**
	 * Register AJAX handlers
	 */
	public function register_ajax_handlers() {
		// Debug logging removed for performance
	}

	/**
	 * Check if JetEngine Maps module is active
	 */
	public function check_dependencies() {
		// Double check JetEngine exists
		if ( ! function_exists( 'jet_engine' ) || ! class_exists( 'Jet_Engine' ) ) {
			return;
		}

		// Check if Maps Listings module is active
		if ( ! jet_engine()->modules || ! jet_engine()->modules->is_module_active( 'maps-listings' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_maps_module' ) );
			return;
		}

		// All dependencies met, initialize plugin
		$this->init();
	}

	/**
	 * Display admin notice for missing JetEngine
	 */
	public function admin_notice_missing_jetengine() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						__( '<strong>JetEngine Geometry Addon</strong> requires <strong>JetEngine</strong> plugin to be installed and activated. Please install and activate <a href="%s" target="_blank">JetEngine</a>.', 'jet-geometry-addon' ),
						'https://crocoblock.com/plugins/jetengine/'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display admin notice for missing Maps Listings module
	 */
	public function admin_notice_missing_maps_module() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e( 'JetEngine Geometry Addon requires JetEngine Maps Listings module to be active. Please activate it in JetEngine → Modules.', 'jet-geometry-addon' );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Load autoloader
		$this->load_autoloader();

		// Initialize GitHub Updater (must be early)
		if ( class_exists( 'Jet_Geometry_GitHub_Updater' ) ) {
			new Jet_Geometry_GitHub_Updater();
		}

		// Load textdomain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register AJAX handlers
		$this->register_ajax_handlers();

		// Enqueue admin assets VERY EARLY if in admin
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'early_enqueue_assets' ), 1 );
		}

		// Initialize components
		$this->init_components();

		// Register REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add settings link on plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( JET_GEOMETRY_ADDON_FILE ), array( $this, 'add_plugin_action_links' ) );
		
		// Handle manual update check
		add_action( 'admin_post_jet_geometry_check_updates', array( $this, 'handle_manual_update_check' ) );
		
		// Show update check notice
		add_action( 'admin_notices', array( $this, 'show_update_check_notice' ) );
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
	 * Early enqueue assets
	 */
	public function early_enqueue_assets( $hook ) {
		// error_log( '=== EARLY ENQUEUE called with hook: ' . $hook );
		
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		// Directly enqueue our scripts and styles
		$this->direct_enqueue();
	}
	
	/**
	 * Directly enqueue assets without admin-assets class
	 */
	private function direct_enqueue() {
		// Get Mapbox token
		$mapbox_token = '';
		if ( function_exists( 'jet_engine' ) && jet_engine()->modules->is_module_active( 'maps-listings' ) ) {
			$settings = \Jet_Engine\Modules\Maps_Listings\Module::instance()->settings;
			$mapbox_token = $settings->get( 'mapbox_access_token' );
		}
		
		// Enqueue Mapbox GL if not already
		if ( ! wp_script_is( 'jet-mapbox', 'enqueued' ) ) {
			wp_enqueue_style(
				'jet-mapbox',
				jet_engine()->plugin_url( 'includes/modules/maps-listings/assets/lib/mapbox/mapbox-gl.css' ),
				array(),
				jet_engine()->get_version()
			);
			
			wp_enqueue_script(
				'jet-mapbox',
				jet_engine()->plugin_url( 'includes/modules/maps-listings/assets/lib/mapbox/mapbox-gl.js' ),
				array(),
				jet_engine()->get_version(),
				false
			);
		}
		
		// Enqueue our CSS
		wp_enqueue_style(
			'jet-geometry-admin-field',
			$this->plugin_url( 'assets/css/admin-geometry-field.css' ),
			array(),
			JET_GEOMETRY_ADDON_VERSION
		);
		
		// Enqueue Mapbox Draw
		wp_enqueue_style(
			'mapbox-gl-draw',
			'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.css',
			array(),
			'1.4.3'
		);
		
		wp_enqueue_script(
			'mapbox-gl-draw',
			'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.js',
			array( 'jet-mapbox' ),
			'1.4.3',
			true
		);
		
		// Enqueue our JS
		wp_enqueue_script(
			'jet-geometry-admin-field',
			$this->plugin_url( 'assets/js/admin/geometry-field.js' ),
			array( 'jquery', 'wp-util' ),
			JET_GEOMETRY_ADDON_VERSION,
			true
		);
		
		// error_log( '=== SCRIPT ENQUEUED: jet-geometry-admin-field ===' );
		
		// Localize
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
					'searchPlaceholder'  => __( 'Search for location...', 'jet-geometry-addon' ),
					'loading'            => __( 'Loading...', 'jet-geometry-addon' ),
					'notFound'           => __( 'Location not found', 'jet-geometry-addon' ),
					'drawPin'            => __( 'Click to place pin', 'jet-geometry-addon' ),
					'drawLine'           => __( 'Click to start line', 'jet-geometry-addon' ),
					'drawPolygon'        => __( 'Click to start polygon', 'jet-geometry-addon' ),
					'resetLocation'      => __( 'Reset location', 'jet-geometry-addon' ),
					'deleteGeometry'     => __( 'Delete geometry', 'jet-geometry-addon' ),
					'editGeometry'       => __( 'Edit geometry', 'jet-geometry-addon' ),
					'typePin'            => __( 'Pin', 'jet-geometry-addon' ),
					'typeLine'           => __( 'Line', 'jet-geometry-addon' ),
					'typePolygon'        => __( 'Polygon', 'jet-geometry-addon' ),
					'enterCoordinates'   => __( 'Enter coordinates (lat, lng)', 'jet-geometry-addon' ),
					'invalidCoordinates' => __( 'Invalid coordinates format', 'jet-geometry-addon' ),
				),
			)
		);
		
		// Add template to footer
		add_action( 'admin_print_footer_scripts', array( $this, 'print_geometry_template' ), 30 );
	}
	
	/**
	 * Print geometry field template
	 */
	public function print_geometry_template() {
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
					<button type="button" class="button jet-geometry-pan-btn" aria-pressed="false">
						<span class="dashicons dashicons-location-alt"></span>
						<?php esc_html_e( 'Move map', 'jet-geometry-addon' ); ?>
					</button>
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

	/**
	 * Add plugin action links
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=jet-geometry-settings' ),
			__( 'Settings', 'jet-geometry-addon' )
		);

		$check_updates_link = sprintf(
			'<a href="%s">%s</a>',
			wp_nonce_url(
				admin_url( 'admin-post.php?action=jet_geometry_check_updates' ),
				'jet_geometry_check_updates',
				'nonce'
			),
			__( 'Check for updates', 'jet-geometry-addon' )
		);

		array_unshift( $links, $settings_link, $check_updates_link );

		return $links;
	}

	/**
	 * Handle manual update check
	 */
	public function handle_manual_update_check() {
		// Verify nonce
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'jet_geometry_check_updates' ) ) {
			wp_die( __( 'Security check failed', 'jet-geometry-addon' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( __( 'You do not have permission to update plugins.', 'jet-geometry-addon' ) );
		}

		// Clear the update cache
		delete_transient( 'jet_geometry_latest_release' );
		
		// Clear WordPress update cache
		delete_site_transient( 'update_plugins' );
		wp_clean_plugins_cache();

		// Redirect back to plugins page with success message
		$redirect_url = add_query_arg(
			array(
				'jet_geometry_update_check' => 'success',
			),
			admin_url( 'plugins.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show update check notice
	 */
	public function show_update_check_notice() {
		// Only show if we're on plugins.php
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		// Check if update check was successful
		if ( isset( $_GET['jet_geometry_update_check'] ) && 'success' === $_GET['jet_geometry_update_check'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'JetEngine Geometry Addon:', 'jet-geometry-addon' ); ?></strong>
					<?php esc_html_e( 'Update check completed. If an update is available, you will see it above.', 'jet-geometry-addon' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Load autoloader
	 */
	private function load_autoloader() {
		require_once JET_GEOMETRY_ADDON_PATH . 'includes/autoloader.php';
		Jet_Geometry_Autoloader::register();
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'jet-geometry-addon',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components() {
		// Make sure JetEngine is available
		if ( ! function_exists( 'jet_engine' ) ) {
			return;
		}

		// Initialize geometry field
		$this->geometry_field = new Jet_Geometry_Field();

		// Initialize geometry renderer
		$this->renderer = new Jet_Geometry_Renderer();

		// Initialize country layers helper
		$this->country_layers = new Jet_Country_Layers();

		// Ensure consolidated GeoJSON file exists
		Jet_Geometry_Country_Geojson_File::ensure_exists();
		
		// Auto-regenerate countries.json file on taxonomy changes
		$this->setup_auto_regenerate_hooks();

		// Initialize Elementor integration
		if ( did_action( 'elementor/loaded' ) ) {
			new Jet_Geometry_Elementor_Integration();
			new Jet_Geometry_Elementor_Widgets();
		}

		// Initialize filters integration (if Jet Smart Filters is active)
		if ( function_exists( 'jet_smart_filters' ) ) {
			$this->filters_integration = new Jet_Geometry_Filters_Integration();
		}
		
		// Initialize markers cache (always, but only active if JSON mode enabled)
		if ( jet_engine()->modules->is_module_active( 'maps-listings' ) ) {
			$this->markers_cache = new Jet_Geometry_Markers_Cache();
		}

		// Initialize admin settings (only in admin)
		if ( is_admin() ) {
			$this->admin_settings = new Jet_Geometry_Admin_Settings();
			new Jet_Geometry_Admin_Country_Geometry();
			Jet_Geometry_Country_Geometry_Migrator::maybe_run();
			Jet_Geometry_Incident_Geometry_Migrator::maybe_run();
		} else {
			// Still run migration during early requests if needed.
			Jet_Geometry_Country_Geometry_Migrator::maybe_run();
			Jet_Geometry_Incident_Geometry_Migrator::maybe_run();
		}

		// Allow other plugins to extend
		do_action( 'jet-geometry-addon/init', $this );
	}

	/**
	 * Setup hooks for auto-regenerating countries.json file
	 */
	private function setup_auto_regenerate_hooks() {
		// Regenerate when country term is created, edited, or deleted
		add_action( 'created_countries', array( $this, 'regenerate_countries_file' ), 20 );
		add_action( 'edited_countries', array( $this, 'regenerate_countries_file' ), 20 );
		add_action( 'delete_countries', array( $this, 'regenerate_countries_file' ), 20 );
		
		// Regenerate when country term meta is updated
		add_action( 'updated_term_meta', array( $this, 'maybe_regenerate_on_meta_update' ), 10, 4 );
		
		// Regenerate when posts with countries taxonomy are saved
		add_action( 'save_post', array( $this, 'maybe_regenerate_on_post_save' ), 20, 2 );
		
		// Regenerate when post is deleted
		add_action( 'delete_post', array( $this, 'maybe_regenerate_on_post_delete' ), 20 );
		
		// Regenerate on term relationship changes
		add_action( 'set_object_terms', array( $this, 'maybe_regenerate_on_term_relationship' ), 20, 6 );
	}
	
	/**
	 * Regenerate countries.json file
	 */
	public function regenerate_countries_file() {
		Jet_Geometry_Country_Geojson_File::regenerate();
	}
	
	/**
	 * Maybe regenerate countries.json when term meta is updated
	 */
	public function maybe_regenerate_on_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Only regenerate if it's a country-related meta key
		if ( in_array( $meta_key, array( '_country_geojson', '_country_geojson_simplified', '_country_iso_code' ), true ) ) {
			$term = get_term( $object_id );
			if ( $term && ! is_wp_error( $term ) && 'countries' === $term->taxonomy ) {
				Jet_Geometry_Country_Geojson_File::regenerate();
			}
		}
	}
	
	/**
	 * Maybe regenerate countries.json when post is saved
	 */
	public function maybe_regenerate_on_post_save( $post_id, $post ) {
		// Skip autosaves and revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		
		// Only regenerate if post has countries taxonomy
		$terms = wp_get_post_terms( $post_id, 'countries' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			// Use a small delay to ensure all meta is saved
			add_action( 'shutdown', array( $this, 'regenerate_countries_file' ), 5 );
		}
	}
	
	/**
	 * Maybe regenerate countries.json when post is deleted
	 */
	public function maybe_regenerate_on_post_delete( $post_id ) {
		// Check if deleted post had countries taxonomy
		$terms = wp_get_post_terms( $post_id, 'countries' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			Jet_Geometry_Country_Geojson_File::regenerate();
		}
	}
	
	/**
	 * Maybe regenerate countries.json when term relationships change
	 */
	public function maybe_regenerate_on_term_relationship( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( 'countries' === $taxonomy ) {
			Jet_Geometry_Country_Geojson_File::regenerate();
		}
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Validate geometry endpoint
		$validate = new Jet_Geometry_REST_Validate();
		$validate->register_route();

		// Country import endpoint
		$country_import = new Jet_Geometry_REST_Country_Import();
		$country_import->register_route();

		// Get countries GeoJSON endpoint
		$countries_geojson = new Jet_Geometry_REST_Countries_Geojson();
		$countries_geojson->register_route();

		// Get country incidents endpoint
		$country_incidents = new Jet_Geometry_REST_Country_Incidents();
		$country_incidents->register_route();

		// Frontend markers debug logging
		$markers_debug = new Jet_Geometry_REST_Markers_Debug();
		$markers_debug->register_route();

		// Incident geometry statistics endpoint
		$geometry_stats = new Jet_Geometry_REST_Incident_Geometry_Stats();
		$geometry_stats->register_route();

		// Incident debug list endpoint
		$debug_list = new Jet_Geometry_REST_Incident_Debug_List();
		$debug_list->register_route();
	}

	/**
	 * Get plugin URL
	 *
	 * @param string $path Path inside plugin directory.
	 * @return string
	 */
	public function plugin_url( $path = '' ) {
		return JET_GEOMETRY_ADDON_URL . $path;
	}

	/**
	 * Get plugin path
	 *
	 * @param string $path Path inside plugin directory.
	 * @return string
	 */
	public function plugin_path( $path = '' ) {
		return JET_GEOMETRY_ADDON_PATH . $path;
	}

	/**
	 * Get instance
	 *
	 * @return Jet_Engine_Geometry_Addon
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

/**
 * Returns instance of the plugin
 *
 * @return Jet_Engine_Geometry_Addon
 */
function jet_geometry_addon() {
	return Jet_Engine_Geometry_Addon::instance();
}

// Initialize plugin
jet_geometry_addon();

