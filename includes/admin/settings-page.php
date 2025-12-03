<?php
/**
 * Admin settings page
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Admin_Settings
 */
class Jet_Geometry_Admin_Settings {

	/**
	 * Page slug
	 *
	 * @var string
	 */
	private $page_slug = 'jet-geometry-settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Use later priority to ensure JetEngine menu exists
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 30 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'wp_ajax_jet_geometry_preview_incidents', array( $this, 'ajax_preview_incidents' ) );
		add_action( 'wp_ajax_jet_geometry_import_incidents', array( $this, 'ajax_import_incidents' ) );
		add_action( 'wp_ajax_jet_geometry_import_progress', array( $this, 'ajax_import_progress' ) );
		add_action( 'wp_ajax_jet_geometry_save_incident_mapping', array( $this, 'ajax_save_incident_mapping' ) );
		add_action( 'wp_ajax_jet_geometry_delete_incident_mapping', array( $this, 'ajax_delete_incident_mapping' ) );
		add_action( 'wp_ajax_jet_geometry_sync_all_posts', array( $this, 'ajax_sync_all_posts' ) );
		add_action( 'wp_ajax_jet_geometry_get_debug_list', array( $this, 'ajax_get_debug_list' ) );
		add_action( 'wp_ajax_jet_geometry_save_debug_json', array( $this, 'ajax_save_debug_json' ) );
		add_action( 'wp_ajax_jet_geometry_regenerate_cache', array( $this, 'ajax_regenerate_cache' ) );
		
		// Filter to preserve post date during updates
		add_filter( 'wp_insert_post_data', array( $this, 'preserve_post_date_on_update' ), 10, 3 );
		
		// Auto-generate geometry from location_csv when saving posts
		add_action( 'save_post', array( $this, 'auto_generate_geometry_on_save' ), 20, 2 );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		// Get JetEngine admin page slug
		$parent_slug = 'jet-engine';
		
		// Check if JetEngine menu exists
		if ( function_exists( 'jet_engine' ) && isset( jet_engine()->admin_page ) ) {
			$parent_slug = jet_engine()->admin_page;
		}
		
		// Add submenu page
		add_submenu_page(
			$parent_slug,
			__( 'Geometry Addon', 'jet-geometry-addon' ),
			__( 'Geometry Addon', 'jet-geometry-addon' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// General settings with permission callback
		register_setting( 'jet_geometry_general', 'jet_geometry_enable_country_layers', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'show_in_rest'      => false,
		) );
		
		register_setting( 'jet_geometry_general', 'jet_geometry_country_color', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#ff0000',
		) );
		
		register_setting( 'jet_geometry_general', 'jet_geometry_country_opacity', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '0.3',
		) );

		// Styling settings - Opacity scale
		register_setting( 'jet_geometry_styling', 'jet_geometry_opacity_min', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '0.2',
		) );

		register_setting( 'jet_geometry_styling', 'jet_geometry_opacity_max', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '0.9',
		) );

		register_setting( 'jet_geometry_styling', 'jet_geometry_opacity_max_incidents', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '50',
		) );

		// Country highlight settings (for filter selection)
		register_setting( 'jet_geometry_general', 'jet_geometry_country_highlight_fill_color', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#f25f5c',
		) );

		register_setting( 'jet_geometry_general', 'jet_geometry_country_highlight_fill_opacity', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '0.45',
		) );

		register_setting( 'jet_geometry_general', 'jet_geometry_country_highlight_outline_enabled', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'jet_geometry_general', 'jet_geometry_country_highlight_outline_color', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#f25f5c',
		) );

		register_setting( 'jet_geometry_general', 'jet_geometry_country_highlight_outline_width', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '2.5',
		) );
		
		// Cache mode setting
		register_setting( 'jet_geometry_general', 'jet_geometry_cache_mode', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_cache_mode' ),
			'default'           => 'standard',
		) );
		
		// Chunk loading settings for performance
		register_setting( 'jet_geometry_general', 'jet_geometry_chunk_loading_enabled', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );
		
		register_setting( 'jet_geometry_general', 'jet_geometry_chunk_size', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 20,
		) );
		
		register_setting( 'jet_geometry_general', 'jet_geometry_chunk_delay', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 50,
		) );

		// Country layers settings
		add_settings_section(
			'jet_geometry_country_section',
			__( 'Country Layers Settings', 'jet-geometry-addon' ),
			array( $this, 'render_country_section' ),
			$this->page_slug
		);

		add_settings_field(
			'enable_country_layers',
			__( 'Enable Country Layers', 'jet-geometry-addon' ),
			array( $this, 'render_checkbox_field' ),
			$this->page_slug,
			'jet_geometry_country_section',
			array(
				'option_name' => 'jet_geometry_enable_country_layers',
				'description' => __( 'Enable country layers on all maps', 'jet-geometry-addon' ),
			)
		);

		add_settings_field(
			'country_color',
			__( 'Country Fill Color', 'jet-geometry-addon' ),
			array( $this, 'render_color_field' ),
			$this->page_slug,
			'jet_geometry_country_section',
			array(
				'option_name' => 'jet_geometry_country_color',
				'default'     => '#ff0000',
			)
		);

		add_settings_field(
			'country_opacity',
			__( 'Fill Opacity', 'jet-geometry-addon' ),
			array( $this, 'render_number_field' ),
			$this->page_slug,
			'jet_geometry_country_section',
			array(
				'option_name' => 'jet_geometry_country_opacity',
				'default'     => '0.3',
				'min'         => '0',
				'max'         => '1',
				'step'        => '0.1',
			)
		);

		// Country highlight settings section
		add_settings_section(
			'jet_geometry_country_highlight_section',
			__( 'Country Highlight Settings', 'jet-geometry-addon' ),
			array( $this, 'render_country_highlight_section' ),
			$this->page_slug
		);

		add_settings_field(
			'country_highlight_fill_color',
			__( 'Highlight Fill Color', 'jet-geometry-addon' ),
			array( $this, 'render_color_field' ),
			$this->page_slug,
			'jet_geometry_country_highlight_section',
			array(
				'option_name' => 'jet_geometry_country_highlight_fill_color',
				'default'     => '#f25f5c',
				'description' => __( 'Color used to highlight selected country in filters', 'jet-geometry-addon' ),
			)
		);

		add_settings_field(
			'country_highlight_fill_opacity',
			__( 'Highlight Fill Opacity', 'jet-geometry-addon' ),
			array( $this, 'render_number_field' ),
			$this->page_slug,
			'jet_geometry_country_highlight_section',
			array(
				'option_name' => 'jet_geometry_country_highlight_fill_opacity',
				'default'     => '0.45',
				'min'         => '0',
				'max'         => '1',
				'step'        => '0.01',
				'description' => __( 'Opacity of the highlight fill (0 = transparent, 1 = opaque)', 'jet-geometry-addon' ),
			)
		);

		add_settings_field(
			'country_highlight_outline_enabled',
			__( 'Enable Outline', 'jet-geometry-addon' ),
			array( $this, 'render_checkbox_field' ),
			$this->page_slug,
			'jet_geometry_country_highlight_section',
			array(
				'option_name' => 'jet_geometry_country_highlight_outline_enabled',
				'description' => __( 'Show outline border around highlighted country', 'jet-geometry-addon' ),
			)
		);

		add_settings_field(
			'country_highlight_outline_color',
			__( 'Outline Color', 'jet-geometry-addon' ),
			array( $this, 'render_color_field' ),
			$this->page_slug,
			'jet_geometry_country_highlight_section',
			array(
				'option_name' => 'jet_geometry_country_highlight_outline_color',
				'default'     => '#f25f5c',
				'description' => __( 'Color of the outline border', 'jet-geometry-addon' ),
			)
		);

		add_settings_field(
			'country_highlight_outline_width',
			__( 'Outline Width', 'jet-geometry-addon' ),
			array( $this, 'render_number_field' ),
			$this->page_slug,
			'jet_geometry_country_highlight_section',
			array(
				'option_name' => 'jet_geometry_country_highlight_outline_width',
				'default'     => '2.5',
				'min'         => '0',
				'max'         => '10',
				'step'        => '0.1',
				'description' => __( 'Width of the outline border in pixels', 'jet-geometry-addon' ),
			)
		);
		
		// Performance / Cache section
		add_settings_section(
			'jet_geometry_cache_section',
			__( 'Performance & Cache', 'jet-geometry-addon' ),
			array( $this, 'render_cache_section_description' ),
			$this->page_slug
		);
		
		add_settings_field(
			'cache_mode',
			__( 'Markers Cache Mode', 'jet-geometry-addon' ),
			array( $this, 'render_cache_mode_field' ),
			$this->page_slug,
			'jet_geometry_cache_section'
		);
		
		add_settings_field(
			'chunk_loading_enabled',
			__( 'Enable Chunk Loading', 'jet-geometry-addon' ),
			array( $this, 'render_chunk_loading_enabled_field' ),
			$this->page_slug,
			'jet_geometry_cache_section'
		);
		
		add_settings_field(
			'chunk_size',
			__( 'Chunk Size', 'jet-geometry-addon' ),
			array( $this, 'render_chunk_size_field' ),
			$this->page_slug,
			'jet_geometry_cache_section'
		);
		
		add_settings_field(
			'chunk_delay',
			__( 'Chunk Delay (ms)', 'jet-geometry-addon' ),
			array( $this, 'render_chunk_delay_field' ),
			$this->page_slug,
			'jet_geometry_cache_section'
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, $this->page_slug ) === false ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Enqueue Select2 for searchable dropdowns
		wp_enqueue_style(
			'select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
			array(),
			'4.1.0'
		);
		
		wp_enqueue_script(
			'select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
			array( 'jquery' ),
			'4.1.0',
			true
		);

		wp_enqueue_style(
			'jet-geometry-admin-settings',
			jet_geometry_addon()->plugin_url( 'assets/css/admin-settings.css' ),
			array(),
			JET_GEOMETRY_ADDON_VERSION . '-' . time() // Cache busting
		);

		wp_enqueue_script(
			'jet-geometry-admin-settings',
			jet_geometry_addon()->plugin_url( 'assets/js/admin/settings-page.js' ),
			array( 'jquery', 'wp-color-picker', 'select2' ),
			JET_GEOMETRY_ADDON_VERSION . '-' . time(), // Cache busting
			false // Load in header, not footer, to ensure it's available early
		);

		wp_localize_script(
			'jet-geometry-admin-settings',
			'JetGeometryAdminSettings',
			array(
				'restUrl' => rest_url( 'jet-geometry/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'ajaxNonce' => wp_create_nonce( 'jet_geometry_admin' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'adminUrl' => admin_url(),
				'mappingPresets' => $this->get_incident_mapping_presets(),
				'i18n'    => array(
					'importing'       => __( 'Importing...', 'jet-geometry-addon' ),
					'importSuccess'   => __( 'Import completed successfully', 'jet-geometry-addon' ),
					'importError'     => __( 'Import failed', 'jet-geometry-addon' ),
					'confirmImport'   => __( 'This will import country data. Continue?', 'jet-geometry-addon' ),
					'previewLoading'  => __( 'Generating preview...', 'jet-geometry-addon' ),
					'noFileSelected'  => __( 'Please select a CSV file first.', 'jet-geometry-addon' ),
					'skipOption'      => __( 'Skip this column', 'jet-geometry-addon' ),
					'customMeta'      => __( 'Custom Meta Field…', 'jet-geometry-addon' ),
					'mappingTitleRequired' => __( 'Please map at least one column to Post Title.', 'jet-geometry-addon' ),
					'customMetaKeyRequired' => __( 'Enter a meta key for the selected custom meta column.', 'jet-geometry-addon' ),
					'importCompleted' => __( 'Import completed.', 'jet-geometry-addon' ),
					'previewReady'    => __( 'Preview generated. Map the columns below.', 'jet-geometry-addon' ),
					'previewFirst'    => __( 'Generate preview and mapping first.', 'jet-geometry-addon' ),
					'mappingSaved'    => __( 'Mapping saved.', 'jet-geometry-addon' ),
					'mappingDeleted'  => __( 'Mapping deleted.', 'jet-geometry-addon' ),
					'mappingNameRequired' => __( 'Enter a name for the mapping.', 'jet-geometry-addon' ),
					'confirmDeleteMapping' => __( 'Delete the selected mapping?', 'jet-geometry-addon' ),
					'applyMappingFirst' => __( 'Select a saved mapping to apply.', 'jet-geometry-addon' ),
					'noMappingSelected' => __( 'No saved mapping selected.', 'jet-geometry-addon' ),
					'selectSavedMapping' => __( 'Select saved mapping…', 'jet-geometry-addon' ),
					'addMappingTarget'   => __( 'Add mapping', 'jet-geometry-addon' ),
					'removeMappingTarget'=> __( 'Remove', 'jet-geometry-addon' ),
					'importedItemsLabel' => __( 'Imported items', 'jet-geometry-addon' ),
					'skippedItemsLabel'  => __( 'Skipped items', 'jet-geometry-addon' ),
					'rowLabel'           => __( 'Row', 'jet-geometry-addon' ),
					'titleLabel'         => __( 'Title', 'jet-geometry-addon' ),
					'reasonLabel'        => __( 'Reason', 'jet-geometry-addon' ),
					'changelogLabel'     => __( 'Detailed Changelog', 'jet-geometry-addon' ),
					'editPost'           => __( 'Edit post', 'jet-geometry-addon' ),
					'selectCountry'      => __( 'Select a country...', 'jet-geometry-addon' ),
					'noResultsFound'     => __( 'No results found', 'jet-geometry-addon' ),
					'searching'          => __( 'Searching...', 'jet-geometry-addon' ),
				),
			)
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jet-geometry-addon' ) );
		}
		
		?>
		<div class="wrap jet-geometry-settings">
			<h1><?php esc_html_e( 'JetEngine Geometry Addon Settings', 'jet-geometry-addon' ); ?></h1>

			<?php settings_errors(); ?>

			<div class="jet-geometry-tabs">
				<nav class="nav-tab-wrapper">
					<a href="#country-layers" class="nav-tab nav-tab-active" data-tab="country-layers">
						<?php esc_html_e( 'Country Layers', 'jet-geometry-addon' ); ?>
					</a>
					<a href="#general" class="nav-tab" data-tab="general">
						<?php esc_html_e( 'General Settings', 'jet-geometry-addon' ); ?>
					</a>
					<a href="#performance" class="nav-tab" data-tab="performance">
						<?php esc_html_e( 'Performance & Cache', 'jet-geometry-addon' ); ?>
					</a>
					<a href="#styling" class="nav-tab" data-tab="styling">
						<?php esc_html_e( 'Styling', 'jet-geometry-addon' ); ?>
					</a>
					<a href="#import-export" class="nav-tab" data-tab="import-export">
						<?php esc_html_e( 'Import/Export', 'jet-geometry-addon' ); ?>
					</a>
					<a href="#debug" class="nav-tab" data-tab="debug">
						<?php esc_html_e( 'Debug', 'jet-geometry-addon' ); ?>
					</a>
				</nav>

				<!-- Country Layers Tab -->
				<div id="country-layers" class="tab-content active">
					<h2><?php esc_html_e( 'Country Layers Import', 'jet-geometry-addon' ); ?></h2>
					<p><?php esc_html_e( 'Import country boundaries from external GeoJSON sources.', 'jet-geometry-addon' ); ?></p>

					<div class="jet-import-section">
						<div class="jet-import-form">
							<h3><?php esc_html_e( 'Import Settings', 'jet-geometry-addon' ); ?></h3>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="import-source"><?php esc_html_e( 'Source', 'jet-geometry-addon' ); ?></label>
									</th>
									<td>
										<select id="import-source" name="import_source">
											<option value="natural-earth"><?php esc_html_e( 'Natural Earth Data', 'jet-geometry-addon' ); ?></option>
											<option value="custom"><?php esc_html_e( 'Custom URL', 'jet-geometry-addon' ); ?></option>
										</select>
									</td>
								</tr>
								<tr id="resolution-row">
									<th scope="row">
										<label for="import-resolution"><?php esc_html_e( 'Resolution', 'jet-geometry-addon' ); ?></label>
									</th>
									<td>
										<select id="import-resolution" name="import_resolution">
											<option value="110m"><?php esc_html_e( '1:110m (Low detail, faster)', 'jet-geometry-addon' ); ?></option>
											<option value="50m" selected><?php esc_html_e( '1:50m (Medium detail)', 'jet-geometry-addon' ); ?></option>
											<option value="10m"><?php esc_html_e( '1:10m (High detail, slower)', 'jet-geometry-addon' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="import-region"><?php esc_html_e( 'Region', 'jet-geometry-addon' ); ?></label>
									</th>
									<td>
										<select id="import-region" name="import_region">
											<option value="world"><?php esc_html_e( 'World', 'jet-geometry-addon' ); ?></option>
											<option value="europe"><?php esc_html_e( 'Europe Only', 'jet-geometry-addon' ); ?></option>
										</select>
									</td>
								</tr>
								<tr id="custom-url-row" style="display: none;">
									<th scope="row">
										<label for="custom-url"><?php esc_html_e( 'Custom GeoJSON URL', 'jet-geometry-addon' ); ?></label>
									</th>
									<td>
										<input type="url" id="custom-url" name="custom_url" class="regular-text" placeholder="https://example.com/countries.geojson">
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="button" id="start-import" class="button button-primary button-large">
									<?php esc_html_e( 'Start Import', 'jet-geometry-addon' ); ?>
								</button>
							</p>

							<div id="import-progress" style="display: none;">
								<div class="import-status">
									<span class="spinner is-active"></span>
									<span class="status-text"><?php esc_html_e( 'Importing countries...', 'jet-geometry-addon' ); ?></span>
								</div>
							</div>

							<div id="import-results" style="display: none;">
								<div class="notice notice-success">
									<p><strong><?php esc_html_e( 'Import Results:', 'jet-geometry-addon' ); ?></strong></p>
									<ul id="results-list"></ul>
								</div>
							</div>
						</div>

						<div class="jet-import-info">
							<h3><?php esc_html_e( 'About Country Data', 'jet-geometry-addon' ); ?></h3>
							<p><?php esc_html_e( 'Country boundaries are imported from Natural Earth, a public domain map dataset.', 'jet-geometry-addon' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Low resolution (1:110m): Fastest, suitable for world maps', 'jet-geometry-addon' ); ?></li>
								<li><?php esc_html_e( 'Medium resolution (1:50m): Balanced detail and performance', 'jet-geometry-addon' ); ?></li>
								<li><?php esc_html_e( 'High resolution (1:10m): Most detailed, may be slow', 'jet-geometry-addon' ); ?></li>
							</ul>
						</div>
					</div>

					<!-- Country List -->
					<div class="jet-countries-list">
						<h3><?php esc_html_e( 'Imported Countries', 'jet-geometry-addon' ); ?></h3>
						<?php $this->render_countries_table(); ?>
					</div>
				</div>

				<!-- General Settings Tab -->
				<div id="general" class="tab-content">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'jet_geometry_general' );
						// Render all sections except cache section
						global $wp_settings_sections, $wp_settings_fields;
						$sections = isset( $wp_settings_sections[ $this->page_slug ] ) ? $wp_settings_sections[ $this->page_slug ] : array();
						foreach ( $sections as $section_id => $section ) {
							// Skip cache section (it's in Performance tab)
							if ( $section_id === 'jet_geometry_cache_section' ) {
								continue;
							}
							
							// Render section manually
							if ( $section['title'] ) {
								echo "<h3>{$section['title']}</h3>\n";
							}
							
							if ( $section['callback'] ) {
								call_user_func( $section['callback'], $section );
							}
							
							if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $this->page_slug ] ) || ! isset( $wp_settings_fields[ $this->page_slug ][ $section_id ] ) ) {
								continue;
							}
							
							echo '<table class="form-table" role="presentation">';
							do_settings_fields( $this->page_slug, $section_id );
							echo '</table>';
						}
						submit_button();
						?>
					</form>

					<!-- Geometry Sync Section -->
					<div class="jet-geometry-sync-section" style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
						<h2><?php esc_html_e( 'Geometry Synchronization', 'jet-geometry-addon' ); ?></h2>
						<p><?php esc_html_e( 'Synchronize geometry data for all incident posts. This will:', 'jet-geometry-addon' ); ?></p>
						<ul style="list-style: disc; margin-left: 20px;">
							<li><?php esc_html_e( 'Generate geometry from location_csv/_incident_location for posts missing geometry', 'jet-geometry-addon' ); ?></li>
							<li><?php esc_html_e( 'Sync hidden JetEngine meta fields for posts with existing geometry', 'jet-geometry-addon' ); ?></li>
						</ul>
						<p style="color: #2271b1; font-weight: bold;">
							<strong><?php esc_html_e( 'Important:', 'jet-geometry-addon' ); ?></strong> 
							<?php esc_html_e( 'This operation will NOT overwrite existing geometry. It only generates geometry for posts that are missing it, and syncs hidden meta fields for posts that already have geometry.', 'jet-geometry-addon' ); ?>
						</p>
						<p>
							<button type="button" id="jet-geometry-sync-all" class="button button-primary button-large">
								<?php esc_html_e( 'Synchronize All Posts', 'jet-geometry-addon' ); ?>
							</button>
							<span class="spinner" id="jet-geometry-sync-spinner" style="float: none; margin-left: 10px; visibility: hidden;"></span>
						</p>
						<div id="jet-geometry-sync-progress" style="display: none; margin-top: 15px;">
							<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
								<div class="progress-bar-container" style="flex: 1; height: 30px; background: #f0f0f0; border-radius: 4px; overflow: hidden; position: relative; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
									<div id="jet-geometry-sync-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); transition: width 0.5s ease-out; position: relative; overflow: hidden;">
										<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%); animation: shimmer 2s infinite;"></div>
									</div>
									<span id="jet-geometry-sync-percent" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #333; font-size: 12px; text-shadow: 0 0 3px rgba(255,255,255,0.8); z-index: 1;">0%</span>
								</div>
							</div>
							<p id="jet-geometry-sync-status" style="margin-top: 5px; font-weight: bold; color: #2271b1;"></p>
							<div id="jet-geometry-sync-current-batch" style="margin-top: 5px; font-size: 12px; color: #666;"></div>
						</div>
						<div id="jet-geometry-sync-results" style="display: none; margin-top: 20px;">
							<h3 style="margin-bottom: 10px;"><?php esc_html_e( 'Synchronization Results', 'jet-geometry-addon' ); ?></h3>
							<div id="jet-geometry-sync-results-summary" style="margin-bottom: 15px;"></div>
							<div id="jet-geometry-sync-results-details" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; font-family: monospace; font-size: 11px; line-height: 1.6;"></div>
						</div>
						<style>
							@keyframes shimmer {
								0% { transform: translateX(-100%); }
								100% { transform: translateX(100%); }
							}
						</style>
					</div>
				</div>

				<!-- Performance & Cache Tab -->
				<div id="performance" class="tab-content">
					<h2><?php esc_html_e( 'Performance & Cache', 'jet-geometry-addon' ); ?></h2>
					<p><?php esc_html_e( 'Configure performance and caching options for map markers.', 'jet-geometry-addon' ); ?></p>
					
					<form method="post" action="options.php">
						<?php
						settings_fields( 'jet_geometry_general' );
						
						// Manually render cache section (do_settings_section doesn't exist in WordPress)
						global $wp_settings_sections, $wp_settings_fields;
						
						if ( isset( $wp_settings_sections[ $this->page_slug ]['jet_geometry_cache_section'] ) ) {
							$section = $wp_settings_sections[ $this->page_slug ]['jet_geometry_cache_section'];
							
							if ( $section['title'] ) {
								echo "<h3>{$section['title']}</h3>\n";
							}
							
							if ( $section['callback'] ) {
								call_user_func( $section['callback'], $section );
							}
							
							if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $this->page_slug ] ) || ! isset( $wp_settings_fields[ $this->page_slug ][ $section['id'] ] ) ) {
								return;
							}
							
							echo '<table class="form-table" role="presentation">';
							do_settings_fields( $this->page_slug, $section['id'] );
							echo '</table>';
						}
						
						submit_button();
						?>
					</form>
				</div>

				<!-- Styling Tab -->
				<div id="styling" class="tab-content">
					<h2><?php esc_html_e( 'Visual Styling', 'jet-geometry-addon' ); ?></h2>
					<p><?php esc_html_e( 'Configure opacity scale for country layers based on incident count.', 'jet-geometry-addon' ); ?></p>
					
					<form method="post" action="options.php">
						<?php
						settings_fields( 'jet_geometry_styling' );
						?>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="jet_geometry_opacity_min"><?php esc_html_e( 'Minimum Opacity', 'jet-geometry-addon' ); ?></label>
								</th>
								<td>
									<input type="number" id="jet_geometry_opacity_min" name="jet_geometry_opacity_min" 
										value="<?php echo esc_attr( get_option( 'jet_geometry_opacity_min', '0.2' ) ); ?>" 
										min="0" max="1" step="0.05" class="small-text">
									<p class="description">
										<?php esc_html_e( 'Opacity for countries with no incidents (0.0 - 1.0). Default: 0.2', 'jet-geometry-addon' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="jet_geometry_opacity_max"><?php esc_html_e( 'Maximum Opacity', 'jet-geometry-addon' ); ?></label>
								</th>
								<td>
									<input type="number" id="jet_geometry_opacity_max" name="jet_geometry_opacity_max" 
										value="<?php echo esc_attr( get_option( 'jet_geometry_opacity_max', '0.9' ) ); ?>" 
										min="0" max="1" step="0.05" class="small-text">
									<p class="description">
										<?php esc_html_e( 'Maximum opacity for countries with many incidents (0.0 - 1.0). Default: 0.9', 'jet-geometry-addon' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="jet_geometry_opacity_max_incidents"><?php esc_html_e( 'Maximum Incidents for Scale', 'jet-geometry-addon' ); ?></label>
								</th>
								<td>
									<input type="number" id="jet_geometry_opacity_max_incidents" name="jet_geometry_opacity_max_incidents" 
										value="<?php echo esc_attr( get_option( 'jet_geometry_opacity_max_incidents', '50' ) ); ?>" 
										min="1" step="1" class="small-text">
									<p class="description">
										<?php esc_html_e( 'Number of incidents at which maximum opacity is reached. Countries with more incidents will use maximum opacity. Default: 50', 'jet-geometry-addon' ); ?>
									</p>
								</td>
							</tr>
						</table>
						
						<?php submit_button(); ?>
					</form>
					
					<div class="jet-styling-info" style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1;">
						<h3><?php esc_html_e( 'How it works', 'jet-geometry-addon' ); ?></h3>
						<p><?php esc_html_e( 'The opacity of country polygons will scale based on the number of incidents:', 'jet-geometry-addon' ); ?></p>
						<ul style="list-style: disc; margin-left: 20px;">
							<li><?php esc_html_e( '0 incidents: Uses minimum opacity (very transparent)', 'jet-geometry-addon' ); ?></li>
							<li><?php esc_html_e( '1-5 incidents: Gradual increase in opacity', 'jet-geometry-addon' ); ?></li>
							<li><?php esc_html_e( '6-10 incidents: Higher opacity', 'jet-geometry-addon' ); ?></li>
							<li><?php esc_html_e( '11-20 incidents: Even higher opacity', 'jet-geometry-addon' ); ?></li>
							<li><?php esc_html_e( '50+ incidents: Maximum opacity (most visible)', 'jet-geometry-addon' ); ?></li>
						</ul>
					</div>
				</div>

				<!-- Import/Export Tab -->
				<div id="import-export" class="tab-content">
					<h2><?php esc_html_e( 'Import / Export', 'jet-geometry-addon' ); ?></h2>
					<p><?php esc_html_e( 'Upload a CSV file to import incidents. You can map columns to incident fields before running the import.', 'jet-geometry-addon' ); ?></p>

					<div class="jet-import-export-wrapper">
						<form id="jet-incidents-import-form" enctype="multipart/form-data">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="jet-incidents-import-file"><?php esc_html_e( 'CSV File', 'jet-geometry-addon' ); ?></label>
									</th>
									<td>
										<input type="file" id="jet-incidents-import-file" name="import_file" accept=".csv" required>
										<p class="description"><?php esc_html_e( 'Supported format: UTF-8 CSV with header row.', 'jet-geometry-addon' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="jet-incidents-import-country"><?php esc_html_e( 'Assign Country', 'jet-geometry-addon' ); ?></label>
									</th>
									<td>
										<?php
										if ( taxonomy_exists( 'countries' ) ) {
											wp_dropdown_categories( array(
												'taxonomy'         => 'countries',
												'hide_empty'      => false,
												'name'            => 'jet-incidents-import-country',
												'id'              => 'jet-incidents-import-country',
												'show_option_none' => __( 'Do not assign automatically', 'jet-geometry-addon' ),
												'option_none_value' => '',
											) );
										} else {
											echo '<em>' . esc_html__( 'Countries taxonomy not found.', 'jet-geometry-addon' ) . '</em>';
										}
										?>
									</td>
								</tr>
							</table>
							<p class="submit">
								<button type="button" class="button button-primary" id="jet-incidents-import-preview"><?php esc_html_e( 'Preview & Map', 'jet-geometry-addon' ); ?></button>
							</p>
						</form>

						<div id="jet-incidents-import-preview-table" style="display: none;">
							<h3><?php esc_html_e( 'Preview (first rows)', 'jet-geometry-addon' ); ?></h3>
							<div class="jet-incidents-preview-scroll"></div>
						</div>

						<div id="jet-incidents-import-mapping" style="display: none;">
							<h3><?php esc_html_e( 'Column Mapping', 'jet-geometry-addon' ); ?></h3>
							<div class="jet-incidents-mapping-toolbar">
								<div class="jet-incidents-mapping-presets">
									<label for="jet-incidents-mapping-presets">
										<?php esc_html_e( 'Saved mappings', 'jet-geometry-addon' ); ?>
										<select id="jet-incidents-mapping-presets">
											<option value=""><?php esc_html_e( 'Select saved mapping…', 'jet-geometry-addon' ); ?></option>
										</select>
									</label>
									<button type="button" class="button" id="jet-incidents-mapping-apply"><?php esc_html_e( 'Apply', 'jet-geometry-addon' ); ?></button>
									<button type="button" class="button" id="jet-incidents-mapping-delete"><?php esc_html_e( 'Delete', 'jet-geometry-addon' ); ?></button>
								</div>
								<div class="jet-incidents-mapping-save">
									<input type="text" id="jet-incidents-mapping-name" placeholder="<?php esc_attr_e( 'Mapping name', 'jet-geometry-addon' ); ?>">
									<button type="button" class="button" id="jet-incidents-mapping-save"><?php esc_html_e( 'Save Mapping', 'jet-geometry-addon' ); ?></button>
								</div>
							</div>
							<table class="widefat fixed" id="jet-incidents-mapping-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'CSV Column', 'jet-geometry-addon' ); ?></th>
										<th><?php esc_html_e( 'Map To', 'jet-geometry-addon' ); ?></th>
										<th><?php esc_html_e( 'Sample Value', 'jet-geometry-addon' ); ?></th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
							<p class="submit">
								<label for="jet-incidents-duplicate-action" style="margin-right: 10px;">
									<strong><?php esc_html_e( 'Duplicate handling:', 'jet-geometry-addon' ); ?></strong>
									<select id="jet-incidents-duplicate-action" name="duplicate_action" style="margin-left: 5px;">
										<option value="skip"><?php esc_html_e( 'Skip duplicates', 'jet-geometry-addon' ); ?></option>
										<option value="update" selected><?php esc_html_e( 'Update existing', 'jet-geometry-addon' ); ?></option>
									</select>
								</label>
								<label for="jet-incidents-post-status" style="margin-right: 10px;">
									<strong><?php esc_html_e( 'Post status:', 'jet-geometry-addon' ); ?></strong>
									<select id="jet-incidents-post-status" name="post_status" style="margin-left: 5px;">
										<option value="draft" selected><?php esc_html_e( 'Draft', 'jet-geometry-addon' ); ?></option>
										<option value="publish"><?php esc_html_e( 'Publish', 'jet-geometry-addon' ); ?></option>
										<option value="pending"><?php esc_html_e( 'Pending Review', 'jet-geometry-addon' ); ?></option>
										<option value="private"><?php esc_html_e( 'Private', 'jet-geometry-addon' ); ?></option>
									</select>
								</label>
								<label for="jet-incidents-update-status" style="margin-right: 10px;">
									<input type="checkbox" id="jet-incidents-update-status" name="update_status" value="1" />
									<strong><?php esc_html_e( 'Update status for existing posts', 'jet-geometry-addon' ); ?></strong>
								</label>
								<button type="button" class="button button-primary" id="jet-incidents-import-start"><?php esc_html_e( 'Start Import', 'jet-geometry-addon' ); ?></button>
								<button type="button" class="button" id="jet-incidents-import-reset"><?php esc_html_e( 'Reset', 'jet-geometry-addon' ); ?></button>
							</p>
						</div>

						<div id="jet-incidents-import-log"></div>
					</div>
				</div>

				<!-- Debug Tab -->
				<div id="debug" class="tab-content">
					<h2><?php esc_html_e( 'Incident Debug', 'jet-geometry-addon' ); ?></h2>
					<p><?php esc_html_e( 'Debug information for all incidents. This table helps identify why some incidents are not appearing on the map.', 'jet-geometry-addon' ); ?></p>
					
					<div class="jet-debug-wrapper">
						<div class="jet-debug-toolbar">
							<button type="button" class="button button-primary" id="jet-debug-start">
								<?php esc_html_e( 'Start Debug', 'jet-geometry-addon' ); ?>
							</button>
							<button type="button" class="button" id="jet-debug-refresh" style="display: none;">
								<?php esc_html_e( 'Refresh', 'jet-geometry-addon' ); ?>
							</button>
							<button type="button" class="button" id="jet-debug-download" style="display: none;">
								<?php esc_html_e( 'Download debug.json', 'jet-geometry-addon' ); ?>
							</button>
							<span class="spinner" id="jet-debug-spinner" style="float: none; margin-left: 10px; display: none;"></span>
						</div>
						
						<div id="jet-debug-progress" style="display: none; margin: 15px 0;">
							<div style="width: 100%; height: 30px; background: #f0f0f0; border-radius: 4px; overflow: hidden; position: relative;">
								<div id="jet-debug-progress-fill" style="height: 100%; background: #2271b1; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">
									<span id="jet-debug-progress-text" style="z-index: 2;">0%</span>
								</div>
								<div id="jet-debug-progress-status" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 12px; font-weight: bold; color: #333; z-index: 1; white-space: nowrap;">
									<?php esc_html_e( 'Initializing...', 'jet-geometry-addon' ); ?>
								</div>
							</div>
						</div>
						
						<div id="jet-debug-filters" style="display: none; margin: 15px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
							<label for="jet-debug-country-filter" style="font-weight: bold; margin-right: 10px;">
								<?php esc_html_e( 'Filter by Country:', 'jet-geometry-addon' ); ?>
							</label>
							<select id="jet-debug-country-filter" style="min-width: 200px;">
								<option value=""><?php esc_html_e( 'All Countries', 'jet-geometry-addon' ); ?></option>
								<?php
								if ( taxonomy_exists( 'countries' ) ) {
									$countries = get_terms( array(
										'taxonomy'   => 'countries',
										'hide_empty' => false,
										'orderby'    => 'name',
										'order'      => 'ASC',
									) );
									if ( ! empty( $countries ) && ! is_wp_error( $countries ) ) {
										foreach ( $countries as $country ) {
											echo '<option value="' . esc_attr( $country->name ) . '">' . esc_html( $country->name ) . '</option>';
										}
									}
								}
								?>
							</select>
							<span id="jet-debug-filter-count" style="margin-left: 15px; color: #666; font-size: 13px;"></span>
						</div>
						
						<div id="jet-debug-table-container">
							<?php $this->render_debug_table(); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<script type="text/javascript">
		// Ensure tabs work even if main script fails to load
		jQuery(document).ready(function($) {
			// Simple tab switching as fallback
			if ( typeof JetGeometrySettings === 'undefined' ) {
				console.warn('JetGeometrySettings not found, using fallback tab handler');
				
				$('.nav-tab').on('click', function(e) {
					e.preventDefault();
					var tab = $(this).data('tab');
					if ( ! tab ) return;
					
					$('.nav-tab').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					$('.tab-content').removeClass('active');
					$('#' + tab).addClass('active');
					
					if ( window.history && window.history.pushState ) {
						window.history.pushState(null, null, '#' + tab);
					} else {
						window.location.hash = tab;
					}
				});
				
				// Handle initial hash
				var hash = window.location.hash.substring(1);
				if ( hash ) {
					var $tab = $('.nav-tab[data-tab="' + hash + '"]');
					if ( $tab.length ) {
						$tab.trigger('click');
					}
				}
			}
		});
		</script>
		<?php
	}

	/**
	 * Render country section description
	 */
	public function render_country_section() {
		echo '<p>' . esc_html__( 'Configure country layers settings for maps.', 'jet-geometry-addon' ) . '</p>';
	}

	/**
	 * Render country highlight section description
	 */
	public function render_country_highlight_section() {
		echo '<p>' . esc_html__( 'Configure how selected countries are highlighted when using filters. These settings can be overridden by widget settings on individual pages.', 'jet-geometry-addon' ) . '</p>';
	}

	/**
	 * Render cache section description
	 */
	public function render_cache_section_description() {
		// Description is already in tab header, so we can leave this empty or add additional info
		echo '<p class="description">' . esc_html__( 'Use JSON Cache Mode to improve performance by caching marker data in JSON files instead of querying the database on every page load.', 'jet-geometry-addon' ) . '</p>';
	}

	/**
	 * Render cache mode field
	 */
	public function render_cache_mode_field() {
		$current = get_option( 'jet_geometry_cache_mode', 'standard' );
		$cache_all_file = JET_GEOMETRY_ADDON_PATH . 'cache/markers-all.json';
		$cache_indexes_file = JET_GEOMETRY_ADDON_PATH . 'cache/markers-indexes.json';
		$cache_all_exists = file_exists( $cache_all_file );
		$cache_indexes_exists = file_exists( $cache_indexes_file );
		
		// Get cache info if files exist
		$cache_info = null;
		if ( $cache_all_exists ) {
			$cache_data = json_decode( file_get_contents( $cache_all_file ), true );
			if ( $cache_data ) {
				$cache_info = array(
					'total_posts' => isset( $cache_data['total_posts'] ) ? $cache_data['total_posts'] : 0,
					'markers_count' => isset( $cache_data['markers'] ) ? count( $cache_data['markers'] ) : 0,
					'last_update' => isset( $cache_data['last_update'] ) ? $cache_data['last_update'] : '',
					'version' => isset( $cache_data['version'] ) ? $cache_data['version'] : '',
				);
			}
		}
		
		// Get file URLs
		$cache_all_url = JET_GEOMETRY_ADDON_URL . 'cache/markers-all.json';
		$cache_indexes_url = JET_GEOMETRY_ADDON_URL . 'cache/markers-indexes.json';
		?>
		<select name="jet_geometry_cache_mode" id="jet_geometry_cache_mode">
			<option value="standard" <?php selected( $current, 'standard' ); ?>>
				<?php esc_html_e( 'Standard Mode', 'jet-geometry-addon' ); ?>
			</option>
			<option value="json" <?php selected( $current, 'json' ); ?>>
				<?php esc_html_e( 'JSON Cache Mode', 'jet-geometry-addon' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'JSON Mode uses cached files for faster loading. Standard Mode queries database directly.', 'jet-geometry-addon' ); ?>
		</p>
		<?php if ( $current === 'json' ): ?>
			<div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
				<h4 style="margin-top: 0;"><?php esc_html_e( 'Cache Management', 'jet-geometry-addon' ); ?></h4>
				
				<div style="margin-bottom: 15px;">
					<button type="button" class="button button-primary" id="regenerate-cache">
						<?php esc_html_e( 'Regenerate Cache', 'jet-geometry-addon' ); ?>
					</button>
					<span class="spinner" id="cache-regenerate-spinner" style="float: none; margin-left: 10px; display: none;"></span>
				</div>
				
				<?php if ( $cache_all_exists && $cache_info ): ?>
					<div style="margin-bottom: 15px; padding: 10px; background: #fff; border-left: 4px solid #46b450;">
						<p style="margin: 0 0 5px 0;"><strong><?php esc_html_e( 'Cache Status:', 'jet-geometry-addon' ); ?></strong></p>
						<ul style="margin: 5px 0; padding-left: 20px;">
							<li><?php printf( esc_html__( 'Wygenerowano %d incidentów', 'jet-geometry-addon' ), $cache_info['markers_count'] ); ?></li>
							<li><?php printf( esc_html__( 'Total posts in database: %d', 'jet-geometry-addon' ), $cache_info['total_posts'] ); ?></li>
							<?php if ( $cache_info['last_update'] ): ?>
								<li><?php printf( esc_html__( 'Last updated: %s', 'jet-geometry-addon' ), esc_html( $cache_info['last_update'] ) ); ?></li>
							<?php endif; ?>
							<?php if ( $cache_info['version'] ): ?>
								<li><?php printf( esc_html__( 'Cache version: %s', 'jet-geometry-addon' ), esc_html( $cache_info['version'] ) ); ?></li>
							<?php endif; ?>
						</ul>
					</div>
				<?php elseif ( $cache_all_exists ): ?>
					<div style="margin-bottom: 15px; padding: 10px; background: #fff; border-left: 4px solid #46b450;">
						<p style="margin: 0;"><strong style="color: #46b450;"><?php esc_html_e( 'Cache file exists', 'jet-geometry-addon' ); ?></strong></p>
					</div>
				<?php else: ?>
					<div style="margin-bottom: 15px; padding: 10px; background: #fff; border-left: 4px solid #dc3232;">
						<p style="margin: 0;"><strong style="color: #dc3232;"><?php esc_html_e( 'Cache file not found - click "Regenerate Cache" to create it', 'jet-geometry-addon' ); ?></strong></p>
					</div>
				<?php endif; ?>
				
				<div style="margin-top: 15px;">
					<h4 style="margin-bottom: 10px;"><?php esc_html_e( 'View Cache Files', 'jet-geometry-addon' ); ?></h4>
					<p style="margin-bottom: 10px; color: #666;">
						<?php esc_html_e( 'Open cache files in a new browser tab to inspect the data:', 'jet-geometry-addon' ); ?>
					</p>
					<div style="display: flex; gap: 10px; flex-wrap: wrap;">
						<?php if ( $cache_all_exists ): ?>
							<a href="<?php echo esc_url( $cache_all_url ); ?>" target="_blank" class="button" style="text-decoration: none;">
								<span class="dashicons dashicons-external" style="vertical-align: middle; margin-right: 5px;"></span>
								<?php esc_html_e( 'View markers-all.json', 'jet-geometry-addon' ); ?>
								<?php if ( $cache_info ): ?>
									<span style="color: #666; font-size: 11px; margin-left: 5px;">
										(<?php echo esc_html( number_format( filesize( $cache_all_file ) / 1024, 2 ) ); ?> KB)
									</span>
								<?php endif; ?>
							</a>
						<?php else: ?>
							<button type="button" class="button" disabled>
								<?php esc_html_e( 'View markers-all.json', 'jet-geometry-addon' ); ?>
								<span style="color: #999; font-size: 11px; margin-left: 5px;">(<?php esc_html_e( 'not generated', 'jet-geometry-addon' ); ?>)</span>
							</button>
						<?php endif; ?>
						
						<?php if ( $cache_indexes_exists ): ?>
							<a href="<?php echo esc_url( $cache_indexes_url ); ?>" target="_blank" class="button" style="text-decoration: none;">
								<span class="dashicons dashicons-external" style="vertical-align: middle; margin-right: 5px;"></span>
								<?php esc_html_e( 'View markers-indexes.json', 'jet-geometry-addon' ); ?>
								<span style="color: #666; font-size: 11px; margin-left: 5px;">
									(<?php echo esc_html( number_format( filesize( $cache_indexes_file ) / 1024, 2 ) ); ?> KB)
								</span>
							</a>
						<?php else: ?>
							<button type="button" class="button" disabled>
								<?php esc_html_e( 'View markers-indexes.json', 'jet-geometry-addon' ); ?>
								<span style="color: #999; font-size: 11px; margin-left: 5px;">(<?php esc_html_e( 'not generated', 'jet-geometry-addon' ); ?>)</span>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Sanitize cache mode value
	 *
	 * @param string $value Cache mode value.
	 * @return string
	 */
	public function sanitize_cache_mode( $value ) {
		$allowed = array( 'standard', 'json' );
		return in_array( $value, $allowed, true ) ? $value : 'standard';
	}

	/**
	 * Render chunk loading enabled field
	 */
	public function render_chunk_loading_enabled_field() {
		$value = get_option( 'jet_geometry_chunk_loading_enabled', true );
		?>
		<label>
			<input type="checkbox" name="jet_geometry_chunk_loading_enabled" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Load markers progressively in chunks for better performance', 'jet-geometry-addon' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, markers will be loaded and rendered in smaller batches instead of all at once, improving initial page load time.', 'jet-geometry-addon' ); ?>
		</p>
		<?php
	}

	/**
	 * Render chunk size field
	 */
	public function render_chunk_size_field() {
		$value = get_option( 'jet_geometry_chunk_size', 20 );
		?>
		<input type="number" name="jet_geometry_chunk_size" value="<?php echo esc_attr( $value ); ?>" min="5" max="100" step="5" class="small-text">
		<p class="description">
			<?php esc_html_e( 'Number of markers to load in each chunk. Recommended: 20-50 markers per chunk.', 'jet-geometry-addon' ); ?>
		</p>
		<?php
	}

	/**
	 * Render chunk delay field
	 */
	public function render_chunk_delay_field() {
		$value = get_option( 'jet_geometry_chunk_delay', 50 );
		?>
		<input type="number" name="jet_geometry_chunk_delay" value="<?php echo esc_attr( $value ); ?>" min="10" max="500" step="10" class="small-text">
		<p class="description">
			<?php esc_html_e( 'Delay in milliseconds between loading each chunk. Lower values = faster loading but more CPU usage. Recommended: 50-100ms.', 'jet-geometry-addon' ); ?>
		</p>
		<?php
	}

	/**
	 * Render checkbox field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$option_name = $args['option_name'];
		$value       = get_option( $option_name, false );
		$description = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>" value="1" <?php checked( $value, true ); ?>>
			<?php echo esc_html( $description ); ?>
		</label>
		<?php
	}

	/**
	 * Render color field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_color_field( $args ) {
		$option_name = $args['option_name'];
		$default     = isset( $args['default'] ) ? $args['default'] : '#000000';
		$value       = get_option( $option_name, $default );
		$description = isset( $args['description'] ) ? $args['description'] : '';
		?>
		<input type="text" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="jet-color-picker">
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$option_name = $args['option_name'];
		$default     = isset( $args['default'] ) ? $args['default'] : '0';
		$value       = get_option( $option_name, $default );
		$min         = isset( $args['min'] ) ? $args['min'] : '0';
		$max         = isset( $args['max'] ) ? $args['max'] : '100';
		$step        = isset( $args['step'] ) ? $args['step'] : '1';
		?>
		<input type="number" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" class="small-text">
		<?php
	}

	/**
	 * Render countries table
	 */
	private function render_countries_table() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'countries',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'No countries imported yet.', 'jet-geometry-addon' ) . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 50px;"><?php esc_html_e( '#', 'jet-geometry-addon' ); ?></th>
					<th><?php esc_html_e( 'Country Name', 'jet-geometry-addon' ); ?></th>
					<th><?php esc_html_e( 'ISO Code', 'jet-geometry-addon' ); ?></th>
					<th><?php esc_html_e( 'GeoJSON Status', 'jet-geometry-addon' ); ?></th>
					<th><?php esc_html_e( 'Last Updated', 'jet-geometry-addon' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'jet-geometry-addon' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php 
				$counter = 1;
				foreach ( $terms as $term ) : ?>
					<?php
					$iso_code = get_term_meta( $term->term_id, '_country_iso_code', true );
					$geojson  = get_term_meta( $term->term_id, '_country_geojson', true );
					$imported = get_term_meta( $term->term_id, '_country_geojson_imported', true );
					$status   = ! empty( $geojson ) ? '✓ ' . __( 'Imported', 'jet-geometry-addon' ) : '✗ ' . __( 'Missing', 'jet-geometry-addon' );
					?>
					<tr>
						<td><?php echo esc_html( $counter ); ?></td>
						<td><strong><?php echo esc_html( $term->name ); ?></strong></td>
						<td><?php echo esc_html( $iso_code ); ?></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $imported ); ?></td>
						<td>
							<?php if ( ! empty( $geojson ) ) : ?>
								<a href="#" class="button button-small delete-country-data" data-term-id="<?php echo esc_attr( $term->term_id ); ?>">
									<?php esc_html_e( 'Delete', 'jet-geometry-addon' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php 
					$counter++;
				endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX: preview incidents CSV.
	 */
	public function ajax_preview_incidents() {
		$this->verify_ajax_request();

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'jet-geometry-addon' ) );
		}

		$parsed = $this->parse_csv_file( $_FILES['import_file']['tmp_name'], 5 );
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( $parsed->get_error_message() );
		}

		wp_send_json_success( $parsed );
	}

	/**
	 * AJAX: run incidents import.
	 */
	public function ajax_import_incidents() {
		$this->verify_ajax_request();

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'jet-geometry-addon' ) );
		}

		$mapping_raw = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '';
		$mapping      = json_decode( $mapping_raw, true );

		if ( empty( $mapping ) || ! is_array( $mapping ) ) {
			wp_send_json_error( __( 'Invalid column mapping data.', 'jet-geometry-addon' ) );
		}

		$column_map = array();
		foreach ( $mapping as $item ) {
			if ( ! isset( $item['column'], $item['target'] ) ) {
				continue;
			}
			$column = intval( $item['column'] );
			$target = sanitize_text_field( $item['target'] );
			if ( '' === $target ) {
				continue;
			}
			if ( ! isset( $column_map[ $column ] ) ) {
				$column_map[ $column ] = array();
			}
			$column_map[ $column ][] = $target;
		}

		if ( empty( $column_map ) ) {
			wp_send_json_error( __( 'Please map at least one column.', 'jet-geometry-addon' ) );
		}

		$parsed = $this->parse_csv_file( $_FILES['import_file']['tmp_name'], 0 );
		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( $parsed->get_error_message() );
		}

		if ( empty( $parsed['rows'] ) ) {
			wp_send_json_success( array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'geocoded' => 0,
				'geocoding_failed' => 0,
				'errors'   => array(),
				'changelog' => array(),
			) );
		}

		$default_country = isset( $_POST['default_country'] ) ? absint( $_POST['default_country'] ) : 0;
		$duplicate_action = isset( $_POST['duplicate_action'] ) ? sanitize_text_field( $_POST['duplicate_action'] ) : 'skip';
		
		// Validate duplicate_action
		if ( ! in_array( $duplicate_action, array( 'skip', 'update' ), true ) ) {
			$duplicate_action = 'skip';
		}

		$post_status = isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : 'draft';
		$update_status = isset( $_POST['update_status'] ) && '1' === $_POST['update_status'];
		
		// Validate post_status
		$allowed_statuses = array( 'draft', 'publish', 'pending', 'private', 'future' );
		if ( ! in_array( $post_status, $allowed_statuses, true ) ) {
			$post_status = 'draft';
		}

		@set_time_limit( 0 );

		$total_rows = count( $parsed['rows'] );
		$progress_key = 'jet_geometry_import_progress_' . get_current_user_id();
		
		// Initialize progress
		set_transient( $progress_key, array(
			'total'    => $total_rows,
			'current'  => 0,
			'percent'  => 0,
			'status'   => 'processing',
		), 300 ); // 5 minutes expiry

		$results = array(
			'imported'       => 0,
			'updated'        => 0,
			'skipped'        => 0,
			'geocoded'       => 0,
			'geocoding_failed' => 0,
			'errors'         => array(),
			'imported_items' => array(),
			'updated_items'  => array(),
			'skipped_items'  => array(),
			'changelog'      => array(), // Detailed changelog of all operations
		);

		// Track Year and Month across rows (for CSV files where Year/Month are in separate header rows)
		$carried_year = '';
		$carried_month = '';

		foreach ( $parsed['rows'] as $row_index => $row ) {
			$row = $this->normalize_row( $row, count( $parsed['headers'] ) );
			
			// Check if this row contains Year or Month (but no Title) - carry forward for next rows
			$row_year = '';
			$row_month = '';
			$row_has_title = false;
			
			// Check column_map to see if this row has Year, Month, or Title
			foreach ( $column_map as $column_index => $targets ) {
				$value = isset( $row[ $column_index ] ) ? trim( $row[ $column_index ] ) : '';
				if ( '' === $value ) {
					continue;
				}
				
				foreach ( $targets as $target ) {
					if ( 'incident_year' === $target ) {
						$row_year = $value;
					} elseif ( 'incident_month' === $target ) {
						$row_month = $value;
					} elseif ( 'post_title' === $target ) {
						$row_has_title = true;
					}
				}
			}
			
			// Update carried values if found in this row (and row doesn't have title - it's a header row)
			if ( ! $row_has_title ) {
				if ( ! empty( $row_year ) ) {
					$carried_year = $row_year;
					error_log( sprintf( '[JetGeometry] Row %d: Carrying forward Year: %s', $row_index + 1, $carried_year ) );
				}
				if ( ! empty( $row_month ) ) {
					$carried_month = $row_month;
					error_log( sprintf( '[JetGeometry] Row %d: Carrying forward Month: %s', $row_index + 1, $carried_month ) );
				}
			}
			
			// If row has title but no year/month, use carried values
			if ( $row_has_title ) {
				// Inject carried Year/Month into row data if missing
				foreach ( $column_map as $column_index => $targets ) {
					foreach ( $targets as $target ) {
						$current_value = isset( $row[ $column_index ] ) ? trim( $row[ $column_index ] ) : '';
						if ( 'incident_year' === $target && empty( $current_value ) && ! empty( $carried_year ) ) {
							$row[ $column_index ] = $carried_year;
							error_log( sprintf( '[JetGeometry] Row %d: Injected carried Year %s into column index %d', $row_index + 1, $carried_year, $column_index ) );
						} elseif ( 'incident_month' === $target && empty( $current_value ) && ! empty( $carried_month ) ) {
							$row[ $column_index ] = $carried_month;
							error_log( sprintf( '[JetGeometry] Row %d: Injected carried Month %s into column index %d', $row_index + 1, $carried_month, $column_index ) );
						}
					}
				}
			}
			
			if ( $this->is_row_empty( $row ) ) {
				// Update progress even for empty rows
				$current_row = $row_index + 1;
				$percent = round( ( $current_row / $total_rows ) * 100, 1 );
				if ( $current_row % 5 === 0 || $current_row === $total_rows ) {
					set_transient( $progress_key, array(
						'total'    => $total_rows,
						'current'  => $current_row,
						'percent'  => $percent,
						'status'   => 'processing',
					), 300 );
				}
				continue;
			}

			$row_number = $row_index + 1;
			$current_row = $row_index + 1;
			$percent = round( ( $current_row / $total_rows ) * 100, 1 );
			
			// Update progress every 5 rows for performance (or on last row)
			if ( $current_row % 5 === 0 || $current_row === $total_rows ) {
				set_transient( $progress_key, array(
					'total'    => $total_rows,
					'current'  => $current_row,
					'percent'  => $percent,
					'status'   => 'processing',
				), 300 );
			}

			$post_title   = '';
			$post_content = '';
			$taxonomies   = array();
			$meta         = array();
			$date_parts   = array();
			$main_category_value = ''; // Store main category (Type of Incident) for hierarchy
			$subcategory_value = '';   // Store subcategory for hierarchy

			foreach ( $column_map as $column_index => $targets ) {
				$raw_value = isset( $row[ $column_index ] ) ? $row[ $column_index ] : '';
				$value = is_string( $raw_value ) ? trim( $raw_value ) : $raw_value;
				if ( '' === $value && 0 !== $value && '0' !== $value ) {
					continue;
				}

				foreach ( $targets as $target ) {
					if ( 'post_title' === $target ) {
						if ( '' === $post_title ) {
							$post_title = $value;
						} else {
							$post_title .= ' ' . $value;
						}
						continue;
					}

					if ( 'post_content' === $target ) {
						if ( '' === $post_content ) {
							$post_content = $value;
						} else {
							$post_content .= "\n\n" . $value;
						}
						continue;
					}

					if ( 'incident_year' === $target ) {
						$date_parts['year'] = $value;
						error_log( sprintf( '[JetGeometry] Row %d: Found year in CSV: %s', $row_number, $value ) );
						continue;
					}

					if ( 'incident_month' === $target ) {
						$date_parts['month'] = $value;
						error_log( sprintf( '[JetGeometry] Row %d: Found month in CSV: %s', $row_number, $value ) );
						continue;
					}

					if ( 'incident_day' === $target ) {
						// Handle date ranges like "22–26" by taking the first number
						if ( preg_match( '/^(\d+)/', $value, $matches ) ) {
							$date_parts['day'] = $matches[1];
						} else {
							$date_parts['day'] = $value;
						}
						error_log( sprintf( '[JetGeometry] Row %d: Found day in CSV: %s (parsed: %s)', $row_number, $value, $date_parts['day'] ) );
						continue;
					}

					if ( 0 === strpos( $target, 'taxonomy:' ) ) {
						$taxonomy = substr( $target, 9 );
						if ( $taxonomy ) {
							// Store main category and subcategory for hierarchical processing
							if ( 'incident-type' === $taxonomy ) {
								$main_category_value = $value;
							} elseif ( 'incident-subtype' === $taxonomy ) {
								$subcategory_value = $value;
							} else {
								// Process other taxonomies normally
								$terms = $this->split_terms( $value );
								if ( ! isset( $taxonomies[ $taxonomy ] ) ) {
									$taxonomies[ $taxonomy ] = array();
								}
								$taxonomies[ $taxonomy ] = array_merge( $taxonomies[ $taxonomy ], $terms );
							}
						}
						continue;
					}

					if ( 0 === strpos( $target, 'meta:' ) ) {
						$meta_key = substr( $target, 5 );
						if ( $meta_key ) {
							// Normalize location field - remove trailing dot
							if ( '_incident_location' === $meta_key ) {
								$value = $this->normalize_location_for_import( $value );
							}
							// Sanitize and trim value for all meta fields
							$value = sanitize_text_field( trim( $value ) );
							$meta[ $meta_key ] = $value;
							error_log( sprintf( '[JetGeometry] Row %d: Mapped column %d to meta field "%s" with value: %s', $row_number, $column_index, $meta_key, $value ) );
						}
						continue;
					}
				}
			}

			// Process hierarchical taxonomies (incident-type and incident-subtype)
			if ( ! empty( $main_category_value ) || ! empty( $subcategory_value ) ) {
				$incident_type_terms = $this->resolve_incident_type_hierarchy( $main_category_value, $subcategory_value, $results['changelog'], $row_number );
				if ( ! isset( $taxonomies['incident-type'] ) ) {
					$taxonomies['incident-type'] = array();
				}
				$taxonomies['incident-type'] = array_merge( $taxonomies['incident-type'], $incident_type_terms );
			}

			$post_title = trim( $post_title );

			// If no title, skip this row - no incident to import
			if ( '' === $post_title ) {
				$results['skipped']++;
				$results['skipped_items'][] = array(
					'row'    => $row_number,
					'title'  => '',
					'reason' => __( 'Missing Post Title - no incident to import', 'jet-geometry-addon' ),
				);
				continue;
			}

			$existing_post_id = $this->incident_exists( $post_title );
			$is_update = false;
			
			if ( $existing_post_id ) {
				if ( 'skip' === $duplicate_action ) {
					$results['skipped']++;
					$results['skipped_items'][] = array(
						'row'    => $row_number,
						'title'  => $post_title,
						'reason' => sprintf( __( 'Duplicate title (post ID %d)', 'jet-geometry-addon' ), $existing_post_id ),
					);
					$results['changelog'][] = array(
						'row'      => $row_number,
						'action'   => 'post_skipped',
						'type'     => 'post',
						'title'    => $post_title,
						'post_id'  => $existing_post_id,
						'message'  => sprintf( __( 'Skipped post "%s" (ID: %d) - duplicate (skip mode)', 'jet-geometry-addon' ), $post_title, $existing_post_id ),
					);
					continue;
				} else {
					// Update existing post
					$is_update = true;
					$post_id = $existing_post_id;
					$results['changelog'][] = array(
						'row'      => $row_number,
						'action'   => 'post_update_start',
						'type'     => 'post',
						'title'    => $post_title,
						'post_id'  => $post_id,
						'message'  => sprintf( __( 'Updating existing post "%s" (ID: %d)', 'jet-geometry-addon' ), $post_title, $post_id ),
					);
				}
			} else {
				$results['changelog'][] = array(
					'row'      => $row_number,
					'action'   => 'post_create_start',
					'type'     => 'post',
					'title'    => $post_title,
					'message'  => sprintf( __( 'Creating new post "%s"', 'jet-geometry-addon' ), $post_title ),
				);
			}

			$postarr = array(
				'post_type'    => 'incidents',
				'post_title'   => $post_title,
				'post_content' => $post_content,
			);

			// Set status for new posts or updates (if update_status is enabled)
			if ( ! $is_update ) {
				$postarr['post_status'] = $post_status;
			} else {
				$postarr['ID'] = $post_id;
				// Update status only if checkbox is checked
				if ( $update_status ) {
					$postarr['post_status'] = $post_status;
				}
			}

			// Set date from CSV if available (MUST be done before wp_update_post to ensure it's not overwritten)
			error_log( sprintf( '[JetGeometry] Row %d: Date parts before maybe_set_post_date: year=%s, month=%s, day=%s', $row_number, isset( $date_parts['year'] ) ? $date_parts['year'] : 'empty', isset( $date_parts['month'] ) ? $date_parts['month'] : 'empty', isset( $date_parts['day'] ) ? $date_parts['day'] : 'empty' ) );
			$this->maybe_set_post_date( $postarr, $date_parts );
			
			if ( $is_update ) {
				// For updates, ensure date handling is correct
				$existing_post = get_post( $post_id );
				if ( $existing_post ) {
					if ( empty( $postarr['post_date'] ) || ! isset( $postarr['post_date'] ) ) {
						// No date from CSV - preserve existing date
						$postarr['post_date']     = $existing_post->post_date;
						$postarr['post_date_gmt'] = $existing_post->post_date_gmt;
						$postarr['edit_date']     = true; // Important: tells WordPress to preserve this date
						error_log( sprintf( '[JetGeometry] Preserving existing post date for post ID %d: %s (edit_date=true)', $post_id, $existing_post->post_date ) );
					} else {
						// Date from CSV is set - ensure edit_date is true to prevent WordPress from overwriting it
						// Also ensure post_date_gmt is set correctly
						if ( ! isset( $postarr['edit_date'] ) || ! $postarr['edit_date'] ) {
							$postarr['edit_date'] = true;
						}
						if ( empty( $postarr['post_date_gmt'] ) ) {
							$postarr['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'] );
						}
						error_log( sprintf( '[JetGeometry] Using date from CSV for post ID %d: %s, GMT: %s (edit_date=true)', $post_id, $postarr['post_date'], $postarr['post_date_gmt'] ) );
					}
				}
			}
			
			error_log( sprintf( '[JetGeometry] Row %d: Final postarr before wp_update_post/wp_insert_post: post_date=%s, post_date_gmt=%s, edit_date=%s', $row_number, isset( $postarr['post_date'] ) ? $postarr['post_date'] : 'not set', isset( $postarr['post_date_gmt'] ) ? $postarr['post_date_gmt'] : 'not set', isset( $postarr['edit_date'] ) ? ( $postarr['edit_date'] ? 'true' : 'false' ) : 'not set' ) );

			if ( $is_update ) {
				// Update existing post
				$update_result = wp_update_post( $postarr, true );
				if ( is_wp_error( $update_result ) ) {
					$results['skipped']++;
					$results['errors'][] = $update_result->get_error_message();
					$results['skipped_items'][] = array(
						'row'    => $row_number,
						'title'  => $post_title,
						'reason' => $update_result->get_error_message(),
					);
					$results['changelog'][] = array(
						'row'      => $row_number,
						'action'   => 'post_error',
						'type'     => 'post',
						'title'    => $post_title,
						'post_id'  => $post_id,
						'message'  => sprintf( __( 'Failed to update post "%s" (ID: %d): %s', 'jet-geometry-addon' ), $post_title, $post_id, $update_result->get_error_message() ),
					);
					continue;
				} else {
					$results['changelog'][] = array(
						'row'      => $row_number,
						'action'   => 'post_updated',
						'type'     => 'post',
						'title'    => $post_title,
						'post_id'  => $post_id,
						'message'  => sprintf( __( 'Updated post "%s" (ID: %d)', 'jet-geometry-addon' ), $post_title, $post_id ),
					);
				}
			} else {
				// Insert new post
				$post_id = wp_insert_post( $postarr, true );
				if ( is_wp_error( $post_id ) ) {
					$results['skipped']++;
					$results['errors'][] = $post_id->get_error_message();
					$results['skipped_items'][] = array(
						'row'    => $row_number,
						'title'  => $post_title,
						'reason' => $post_id->get_error_message(),
					);
					$results['changelog'][] = array(
						'row'      => $row_number,
						'action'   => 'post_error',
						'type'     => 'post',
						'title'    => $post_title,
						'message'  => sprintf( __( 'Failed to create post "%s": %s', 'jet-geometry-addon' ), $post_title, $post_id->get_error_message() ),
					);
					continue;
				} else {
					$results['changelog'][] = array(
						'row'      => $row_number,
						'action'   => 'post_created',
						'type'     => 'post',
						'title'    => $post_title,
						'post_id'  => $post_id,
						'message'  => sprintf( __( 'Created new post "%s" (ID: %d)', 'jet-geometry-addon' ), $post_title, $post_id ),
					);
				}
			}

			if ( ! empty( $meta ) ) {
				error_log( sprintf( '[JetGeometry] Row %d: Saving meta fields for post ID %d: %s', $row_number, $post_id, implode( ', ', array_keys( $meta ) ) ) );
				$meta_updated = array();
				foreach ( $meta as $key => $value ) {
					$old_value = get_post_meta( $post_id, $key, true );
					$result = update_post_meta( $post_id, $key, $value );
					error_log( sprintf( '[JetGeometry] Row %d: Saved meta field "%s" = "%s" (old: "%s") for post ID %d (result: %s)', $row_number, $key, $value, $old_value, $post_id, $result ? 'success' : 'failed' ) );
					if ( $old_value !== $value && $result ) {
						$meta_updated[] = $key;
					}
				}
				if ( ! empty( $meta_updated ) && is_array( $results['changelog'] ) ) {
					$results['changelog'][] = array(
						'row'      => $row_number,
						'action'   => 'meta_updated',
						'type'     => 'meta',
						'post_id'  => $post_id,
						'fields'   => $meta_updated,
						'message'  => sprintf( __( 'Updated meta fields for post ID %d: %s', 'jet-geometry-addon' ), $post_id, implode( ', ', $meta_updated ) ),
					);
				}
			}

			// Generate geometry from location if location is provided
			// Only generate if geometry doesn't exist (don't overwrite existing geometry)
			$location_source = '';
			if ( ! empty( $meta['_incident_location'] ) ) {
				$location_source = $meta['_incident_location'];
			} elseif ( ! empty( $meta['location_csv'] ) ) {
				$location_source = $meta['location_csv'];
			}

			if ( '' !== $location_source ) {
				$location_before = $location_source;
				$geometry_before = get_post_meta( $post_id, 'incident_geometry', true );
				
				// Always check if geometry exists first - don't overwrite existing geometry
				$this->maybe_generate_geometry_from_location( $post_id, $location_source );
				
				// Check if geometry was successfully created
				$geometry_after = get_post_meta( $post_id, 'incident_geometry', true );
				if ( ! empty( $geometry_after ) ) {
					$results['geocoded']++;
					$geometry_data = json_decode( $geometry_after, true );
					$coords = isset( $geometry_data['geometry']['coordinates'] ) ? $geometry_data['geometry']['coordinates'] : null;
					if ( is_array( $results['changelog'] ) ) {
						if ( ! empty( $geometry_before ) ) {
							$results['changelog'][] = array(
								'row'      => $row_number,
								'action'   => 'geometry_updated',
								'type'     => 'geometry',
								'post_id'  => $post_id,
								'location' => $location_before,
								'coordinates' => $coords,
								'message'  => sprintf( __( 'Updated geometry for post ID %d from location "%s" (coordinates: %s)', 'jet-geometry-addon' ), $post_id, $location_before, $coords ? implode( ', ', $coords ) : 'N/A' ),
							);
						} else {
							$results['changelog'][] = array(
								'row'      => $row_number,
								'action'   => 'geometry_created',
								'type'     => 'geometry',
								'post_id'  => $post_id,
								'location' => $location_before,
								'coordinates' => $coords,
								'message'  => sprintf( __( 'Created geometry for post ID %d from location "%s" (coordinates: %s)', 'jet-geometry-addon' ), $post_id, $location_before, $coords ? implode( ', ', $coords ) : 'N/A' ),
							);
						}
					}
				} else {
					$results['geocoding_failed']++;
					error_log( sprintf( '[JetGeometry] Failed to geocode location for post ID %d: %s', $post_id, $location_before ) );
					if ( is_array( $results['changelog'] ) ) {
						$results['changelog'][] = array(
							'row'      => $row_number,
							'action'   => 'geocoding_failed',
							'type'     => 'geometry',
							'post_id'  => $post_id,
							'location' => $location_before,
							'message'  => sprintf( __( 'Failed to geocode location "%s" for post ID %d', 'jet-geometry-addon' ), $location_before, $post_id ),
						);
					}
				}
			}

			if ( $default_country ) {
				if ( ! isset( $taxonomies['countries'] ) ) {
					$taxonomies['countries'] = array();
				}
				$taxonomies['countries'][] = intval( $default_country );
			}

			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy => $terms ) {
					$terms = array_filter( $terms, array( $this, 'filter_empty' ) );
					if ( empty( $terms ) ) {
						continue;
					}
					
					// Get existing terms before update
					$existing_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
					if ( is_wp_error( $existing_terms ) ) {
						$existing_terms = array();
					}
					
					// Replace existing terms (false = replace, true = append)
					$result = wp_set_object_terms( $post_id, $terms, $taxonomy, false );
					
					if ( ! is_wp_error( $result ) && is_array( $results['changelog'] ) ) {
						$term_names = array();
						foreach ( $terms as $term_id ) {
							$term_obj = get_term( $term_id, $taxonomy );
							if ( $term_obj && ! is_wp_error( $term_obj ) ) {
								$term_names[] = $term_obj->name;
							}
						}
						
						$existing_term_names = array();
						foreach ( $existing_terms as $term_id ) {
							$term_obj = get_term( $term_id, $taxonomy );
							if ( $term_obj && ! is_wp_error( $term_obj ) ) {
								$existing_term_names[] = $term_obj->name;
							}
						}
						
						$added = array_diff( $term_names, $existing_term_names );
						$removed = array_diff( $existing_term_names, $term_names );
						
						if ( ! empty( $added ) || ! empty( $removed ) ) {
							$changes = array();
							if ( ! empty( $added ) ) {
								$changes[] = sprintf( __( 'Added: %s', 'jet-geometry-addon' ), implode( ', ', $added ) );
							}
							if ( ! empty( $removed ) ) {
								$changes[] = sprintf( __( 'Removed: %s', 'jet-geometry-addon' ), implode( ', ', $removed ) );
							}
							if ( empty( $added ) && empty( $removed ) ) {
								$changes[] = __( 'No changes', 'jet-geometry-addon' );
							}
							
							$results['changelog'][] = array(
								'row'      => $row_number,
								'action'   => 'taxonomy_assigned',
								'type'     => 'taxonomy',
								'post_id'  => $post_id,
								'taxonomy' => $taxonomy,
								'terms'    => $term_names,
								'added'    => $added,
								'removed'  => $removed,
								'message'  => sprintf( __( 'Assigned taxonomy "%s" to post ID %d: %s', 'jet-geometry-addon' ), $taxonomy, $post_id, implode( '; ', $changes ) ),
							);
						}
					}
				}
			}

			if ( $is_update ) {
				$results['updated']++;
				$results['updated_items'][] = array(
					'row'     => $row_number,
					'title'   => $post_title,
					'post_id' => $post_id,
					'date'    => isset( $postarr['post_date'] ) ? $postarr['post_date'] : '',
				);
			} else {
				$results['imported']++;
				$results['imported_items'][] = array(
					'row'     => $row_number,
					'title'   => $post_title,
					'post_id' => $post_id,
					'date'    => isset( $postarr['post_date'] ) ? $postarr['post_date'] : '',
				);
			}
		}

		// Mark progress as completed
		$progress_key = 'jet_geometry_import_progress_' . get_current_user_id();
		set_transient( $progress_key, array(
			'total'    => $total_rows,
			'current'  => $total_rows,
			'percent'  => 100,
			'status'   => 'completed',
		), 300 );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: get import progress.
	 */
	public function ajax_import_progress() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied', 'jet-geometry-addon' ) ) );
		}

		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( $_REQUEST['nonce'] ) : '';
		// Check both possible nonce names
		if ( ! wp_verify_nonce( $nonce, 'jet_geometry_admin_settings' ) && ! wp_verify_nonce( $nonce, 'jet_geometry_admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce verification failed', 'jet-geometry-addon' ) ) );
		}

		$progress_key = 'jet_geometry_import_progress_' . get_current_user_id();
		$progress = get_transient( $progress_key );

		if ( false === $progress ) {
			wp_send_json_success( array(
				'total'    => 0,
				'current'  => 0,
				'percent'  => 0,
				'status'   => 'not_started',
			) );
		}

		wp_send_json_success( $progress );
	}

	/**
	 * AJAX: save incident mapping.
	 */
	public function ajax_save_incident_mapping() {
		$this->verify_ajax_request();

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( __( 'Mapping name is required.', 'jet-geometry-addon' ) );
		}

		$mapping_raw = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '';
		$mapping     = json_decode( $mapping_raw, true );

		if ( empty( $mapping ) || ! is_array( $mapping ) ) {
			wp_send_json_error( __( 'Invalid mapping data.', 'jet-geometry-addon' ) );
		}

		$normalized = array();
		foreach ( $mapping as $item ) {
			if ( empty( $item['header'] ) || empty( $item['target'] ) ) {
				continue;
			}
			$normalized[] = array(
				'header' => sanitize_text_field( $item['header'] ),
				'target' => sanitize_text_field( $item['target'] ),
			);
		}

		if ( empty( $normalized ) ) {
			wp_send_json_error( __( 'Nothing to save. Please map at least one column.', 'jet-geometry-addon' ) );
		}

		$slug = sanitize_title( $name );
		if ( '' === $slug ) {
			$slug = 'mapping_' . wp_generate_uuid4();
		}

		$presets            = $this->get_incident_mapping_presets();
		$presets[ $slug ] = array(
			'name'    => $name,
			'mapping' => $normalized,
		);

		$this->set_incident_mapping_presets( $presets );

		wp_send_json_success(
			array(
				'presets' => $presets,
				'slug'    => $slug,
			)
		);
	}

	/**
	 * AJAX: delete incident mapping.
	 */
	public function ajax_delete_incident_mapping() {
		$this->verify_ajax_request();

		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		if ( '' === $slug ) {
			wp_send_json_error( __( 'Mapping not found.', 'jet-geometry-addon' ) );
		}

		$presets = $this->get_incident_mapping_presets();
		if ( isset( $presets[ $slug ] ) ) {
			unset( $presets[ $slug ] );
			$this->set_incident_mapping_presets( $presets );
		}

		wp_send_json_success( array( 'presets' => $presets ) );
	}

	/**
	 * AJAX handler for syncing geometry for all posts.
	 */
	public function ajax_sync_all_posts() {
		error_log( '[JetGeometry] ajax_sync_all_posts called' );
		error_log( '[JetGeometry] POST data: ' . print_r( $_POST, true ) );
		
		$this->verify_ajax_request();
		
		error_log( '[JetGeometry] AJAX request verified' );

		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		$total = isset( $_POST['total'] ) ? intval( $_POST['total'] ) : 0;

		// Get total count on first request (when total is 0)
		if ( 0 === $total && 0 === $offset ) {
			$total = $this->get_posts_needing_sync_count();
			if ( $total === 0 ) {
				wp_send_json_success( array(
					'total' => 0,
					'processed' => array(),
					'synced' => 0,
					'generated' => 0,
					'skipped' => 0,
					'errors' => array(),
					'details' => array(),
					'offset' => 0,
					'continue' => false,
				) );
			}
			// Continue to process first batch
		}

		// Process batch
		$results = $this->sync_posts_batch( $offset, $batch_size );
		$processed = $offset + count( $results['processed'] );
		$continue = $processed < $total;

		wp_send_json_success( array(
			'processed' => $results['processed'],
			'synced' => $results['synced'],
			'generated' => $results['generated'],
			'skipped' => $results['skipped'],
			'errors' => $results['errors'],
			'details' => isset( $results['details'] ) ? $results['details'] : array(),
			'offset' => $processed,
			'total' => $total,
			'continue' => $continue,
		) );
	}

	/**
	 * Get count of posts that need geometry sync.
	 *
	 * @return int
	 */
	private function get_posts_needing_sync_count() {
		$query = new WP_Query( array(
			'post_type' => 'incidents',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'location_csv',
					'compare' => 'EXISTS',
				),
				array(
					'key' => '_incident_location',
					'compare' => 'EXISTS',
				),
				array(
					'key' => 'incident_geometry',
					'compare' => 'EXISTS',
				),
			),
		) );

		return $query->found_posts;
	}

	/**
	 * Sync geometry for a batch of posts.
	 *
	 * @param int $offset Offset.
	 * @param int $batch_size Batch size.
	 * @return array
	 */
	private function sync_posts_batch( $offset, $batch_size ) {
		$results = array(
			'processed' => array(),
			'synced' => 0,
			'generated' => 0,
			'skipped' => 0,
			'errors' => array(),
			'details' => array(), // Detailed info for each post
		);

		$query = new WP_Query( array(
			'post_type' => 'incidents',
			'post_status' => 'any',
			'posts_per_page' => $batch_size,
			'offset' => $offset,
			'orderby' => 'ID',
			'order' => 'ASC',
		) );

		if ( empty( $query->posts ) ) {
			return $results;
		}

		$geometry_meta_key = 'incident_geometry';
		$prefix = Jet_Geometry_Utils::get_field_prefix( $geometry_meta_key );

		foreach ( $query->posts as $post ) {
			$post_id = $post->ID;
			$post_title = get_the_title( $post_id );
			$results['processed'][] = $post_id;

			$detail = array(
				'id' => $post_id,
				'title' => $post_title,
				'action' => '',
				'message' => '',
				'location' => '',
				'coordinates' => '',
			);

			try {
				$existing_geometry = get_post_meta( $post_id, $geometry_meta_key, true );
				$existing_hidden_data = get_post_meta( $post_id, $prefix . '_geometry_data', true );
				$location_csv = get_post_meta( $post_id, 'location_csv', true );
				$incident_location = get_post_meta( $post_id, '_incident_location', true );

				// Case 1: Geometry exists but hidden meta is missing - sync it
				if ( ! empty( $existing_geometry ) && empty( $existing_hidden_data ) ) {
					$geometry = json_decode( $existing_geometry, true );
					if ( $geometry && isset( $geometry['geometry'] ) ) {
						$lat = get_post_meta( $post_id, 'incident_lat', true );
						$lng = get_post_meta( $post_id, 'incident_lng', true );
						Jet_Geometry_Utils::sync_map_field_meta(
							$post_id,
							$geometry_meta_key,
							$geometry['geometry'],
							$lat ? floatval( $lat ) : null,
							$lng ? floatval( $lng ) : null
						);
						
						// Verify hidden meta were created
						$new_hidden_data = get_post_meta( $post_id, $prefix . '_geometry_data', true );
						$new_hidden_type = get_post_meta( $post_id, $prefix . '_geometry_type', true );
						$new_hidden_lat = get_post_meta( $post_id, $prefix . '_lat', true );
						$new_hidden_lng = get_post_meta( $post_id, $prefix . '_lng', true );
						
						$hidden_meta_status = array();
						if ( ! empty( $new_hidden_data ) ) {
							$hidden_meta_status[] = 'data: ✓';
						} else {
							$hidden_meta_status[] = 'data: ✗';
						}
						if ( ! empty( $new_hidden_type ) ) {
							$hidden_meta_status[] = 'type: ✓';
						} else {
							$hidden_meta_status[] = 'type: ✗';
						}
						if ( ! empty( $new_hidden_lat ) && ! empty( $new_hidden_lng ) ) {
							$hidden_meta_status[] = 'lat/lng: ✓';
						} else {
							$hidden_meta_status[] = 'lat/lng: ✗';
						}
						
						$detail['hidden_meta'] = implode(', ', $hidden_meta_status);
						
						if ( ! empty( $new_hidden_data ) && ! empty( $new_hidden_type ) ) {
							$detail['message'] = 'Synced hidden meta fields';
						} else {
							$detail['message'] = 'Synced hidden meta fields (WARNING: some fields missing)';
						}
						
						$results['synced']++;
						$detail['action'] = 'synced';
						if ( $lat && $lng ) {
							$detail['coordinates'] = sprintf( '%.6f, %.6f', floatval( $lat ), floatval( $lng ) );
						}
					} else {
						$results['skipped']++;
						$detail['action'] = 'skipped';
						$detail['message'] = 'Invalid geometry data format';
					}
				}
				// Case 2: No geometry but has location - generate it
				elseif ( empty( $existing_geometry ) && empty( $existing_hidden_data ) ) {
					$location_source = '';

					if ( ! empty( $location_csv ) ) {
						$location_source = $location_csv;
					} elseif ( ! empty( $incident_location ) ) {
						$location_source = $incident_location;
					}

					if ( '' !== $location_source ) {
						$detail['location'] = $location_source;
						$this->maybe_generate_geometry_from_location( $post_id, $location_source );
						
						// Check if geometry was created (both main and hidden meta)
						$new_geometry = get_post_meta( $post_id, $geometry_meta_key, true );
						$new_hidden_data = get_post_meta( $post_id, $prefix . '_geometry_data', true );
						$new_hidden_type = get_post_meta( $post_id, $prefix . '_geometry_type', true );
						$new_hidden_lat = get_post_meta( $post_id, $prefix . '_lat', true );
						$new_hidden_lng = get_post_meta( $post_id, $prefix . '_lng', true );
						
						if ( ! empty( $new_geometry ) ) {
							$geometry = json_decode( $new_geometry, true );
							if ( $geometry && isset( $geometry['geometry']['coordinates'] ) ) {
								$coords = $geometry['geometry']['coordinates'];
								$detail['coordinates'] = sprintf( '%.6f, %.6f', $coords[1], $coords[0] );
							}
							
							// Verify hidden meta were created
							$hidden_meta_status = array();
							if ( ! empty( $new_hidden_data ) ) {
								$hidden_meta_status[] = 'data: ✓';
							} else {
								$hidden_meta_status[] = 'data: ✗';
							}
							if ( ! empty( $new_hidden_type ) ) {
								$hidden_meta_status[] = 'type: ✓';
							} else {
								$hidden_meta_status[] = 'type: ✗';
							}
							if ( ! empty( $new_hidden_lat ) && ! empty( $new_hidden_lng ) ) {
								$hidden_meta_status[] = 'lat/lng: ✓';
							} else {
								$hidden_meta_status[] = 'lat/lng: ✗';
							}
							
							$detail['hidden_meta'] = implode(', ', $hidden_meta_status);
							
							// If main geometry exists but hidden meta is missing, try to sync again
							if ( ! empty( $new_geometry ) && empty( $new_hidden_data ) ) {
								$geometry = json_decode( $new_geometry, true );
								if ( $geometry && isset( $geometry['geometry'] ) ) {
									$lat = get_post_meta( $post_id, 'incident_lat', true );
									$lng = get_post_meta( $post_id, 'incident_lng', true );
									Jet_Geometry_Utils::sync_map_field_meta(
										$post_id,
										$geometry_meta_key,
										$geometry['geometry'],
										$lat ? floatval( $lat ) : null,
										$lng ? floatval( $lng ) : null
									);
									$detail['message'] = sprintf( 'Generated geometry from location: %s (hidden meta synced)', $location_source );
								} else {
									$detail['message'] = sprintf( 'Generated geometry from location: %s (WARNING: hidden meta missing)', $location_source );
								}
							} else {
								$detail['message'] = sprintf( 'Generated geometry from location: %s', $location_source );
							}
							
							$results['generated']++;
							$detail['action'] = 'generated';
						} else {
							$results['skipped']++;
							$detail['action'] = 'skipped';
							$detail['message'] = sprintf( 'Failed to geocode location: %s', $location_source );
						}
					} else {
						$results['skipped']++;
						$detail['action'] = 'skipped';
						$detail['message'] = 'No location data found';
					}
				}
				// Case 3: Both exist - skip
				else {
					$results['skipped']++;
					$detail['action'] = 'skipped';
					$detail['message'] = 'Geometry already complete';
					if ( ! empty( $existing_geometry ) ) {
						$geometry = json_decode( $existing_geometry, true );
						if ( $geometry && isset( $geometry['geometry']['coordinates'] ) ) {
							$coords = $geometry['geometry']['coordinates'];
							$detail['coordinates'] = sprintf( '%.6f, %.6f', $coords[1], $coords[0] );
						}
					}
				}
			} catch ( Exception $e ) {
				$error_msg = sprintf( 'Post %d: %s', $post_id, $e->getMessage() );
				$results['errors'][] = $error_msg;
				$detail['action'] = 'error';
				$detail['message'] = $e->getMessage();
			}

			$results['details'][] = $detail;
		}

		return $results;
	}

	/**
	 * Filter to preserve post date during updates when edit_date is set.
	 * This prevents WordPress from overwriting the date when status changes.
	 *
	 * @param array $data    Array of slashed post data.
	 * @param array $postarr Array of sanitized, but otherwise unmodified post data.
	 * @param array $unsanitized_postarr Array of post data as originally passed to wp_insert_post().
	 * @return array Modified post data.
	 */
	public function preserve_post_date_on_update( $data, $postarr, $unsanitized_postarr ) {
		// Only apply during our import process (check if edit_date is set by our code)
		if ( ! isset( $postarr['edit_date'] ) || ! $postarr['edit_date'] ) {
			return $data;
		}
		
		// If edit_date is true and post_date is set, preserve it
		if ( isset( $postarr['post_date'] ) && ! empty( $postarr['post_date'] ) && '0000-00-00 00:00:00' !== $postarr['post_date'] ) {
			$data['post_date'] = $postarr['post_date'];
			if ( isset( $postarr['post_date_gmt'] ) && ! empty( $postarr['post_date_gmt'] ) ) {
				$data['post_date_gmt'] = $postarr['post_date_gmt'];
			} else {
				$data['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'] );
			}
			error_log( sprintf( '[JetGeometry] Filter preserve_post_date_on_update: Preserving date %s (GMT: %s)', $data['post_date'], $data['post_date_gmt'] ) );
		}
		
		return $data;
	}

	/**
	 * Verify AJAX request.
	 */
	private function verify_ajax_request() {
		check_ajax_referer( 'jet_geometry_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'jet-geometry-addon' ), 403 );
		}
	}

	/**
	 * Parse CSV file.
	 *
	 * @param string $file_path Path to CSV file.
	 * @param int    $limit     Number of rows to return (0 for all).
	 *
	 * @return array|WP_Error
	 */
	private function parse_csv_file( $file_path, $limit = 5 ) {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return new WP_Error( 'missing_file', __( 'Uploaded file not found.', 'jet-geometry-addon' ) );
		}

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'open_failed', __( 'Unable to open uploaded file.', 'jet-geometry-addon' ) );
		}

		$headers = null;
		$rows    = array();

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row = $this->normalize_row_values( $row );

			if ( $this->is_row_empty( $row ) ) {
				continue;
			}

			if ( ! $headers ) {
				if ( $this->should_skip_row_as_header( $row ) ) {
					continue;
				}
				$headers = $this->normalize_headers( $row );
				continue;
			}

			$rows[] = $this->normalize_row( $row, count( $headers ) );

			if ( $limit > 0 && count( $rows ) >= $limit ) {
				break;
			}
		}

		fclose( $handle );

		if ( ! $headers ) {
			return new WP_Error( 'no_headers', __( 'Unable to detect header row in the uploaded file.', 'jet-geometry-addon' ) );
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Ensure row has consistent length and trimmed values.
	 */
	private function normalize_row( $row, $length ) {
		$row = $this->normalize_row_values( $row );
		$diff = $length - count( $row );
		if ( $diff > 0 ) {
			$row = array_pad( $row, $length, '' );
		}
		return $row;
	}

	/**
	 * Trim row values.
	 */
	private function normalize_row_values( $row ) {
		return array_map( function( $value ) {
			if ( is_string( $value ) ) {
				$value = trim( $value );
				$value = preg_replace( "/^\xEF\xBB\xBF/", '', $value );
			}
			return $value;
		}, $row );
	}

	/**
	 * Determine if row is empty.
	 */
	private function is_row_empty( $row ) {
		foreach ( $row as $value ) {
			if ( '' !== $value && null !== $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Skip rows that are likely sheet titles.
	 */
	private function should_skip_row_as_header( $row ) {
		$non_empty = 0;
		foreach ( $row as $value ) {
			if ( '' !== $value && null !== $value ) {
				$non_empty++;
			}
		}
		return ( $non_empty <= 1 );
	}

	/**
	 * Normalize header labels and ensure uniqueness.
	 */
	private function normalize_headers( $row ) {
		$headers  = array();
		$used     = array();
		foreach ( $row as $index => $label ) {
			$label = trim( $label );
			if ( '' === $label ) {
				$label = sprintf( __( 'Column %d', 'jet-geometry-addon' ), $index + 1 );
			}
			$base = $label;
			$counter = 1;
			while ( isset( $used[ $label ] ) ) {
				$label = $base . ' (' . ++$counter . ')';
			}
			$used[ $label ] = true;
			$headers[] = $label;
		}
		return $headers;
	}

	/**
	 * Split taxonomy terms.
	 */
	private function split_terms( $value ) {
		$parts = preg_split( '/[,;|]+/', $value );
		$parts = array_map( 'trim', $parts );
		return array_filter( $parts, array( $this, 'filter_empty' ) );
	}

	/**
	 * Helper callback for array_filter.
	 */
	private function filter_empty( $value ) {
		return ( '' !== $value && null !== $value );
	}

	/**
	 * Determine if an incident post already exists.
	 */
	private function incident_exists( $title ) {
		$post = get_page_by_title( $title, OBJECT, 'incidents' );
		return ( $post ) ? intval( $post->ID ) : 0;
	}

	/**
	 * Resolve incident type hierarchy: main category and subcategories with parent-child relationship.
	 *
	 * @param string $main_category Main category name (Type of Incident).
	 * @param string $subcategory    Subcategory name (Subcategory).
	 * @param array  $changelog      Reference to changelog array to log operations.
	 * @param int    $row_number     Row number for changelog context.
	 * @return array Array of term IDs.
	 */
	private function resolve_incident_type_hierarchy( $main_category, $subcategory, &$changelog = null, $row_number = 0 ) {
		$resolved = array();
		$parent_term_id = 0;

		// Process main category first
		if ( ! empty( $main_category ) ) {
			$main_category = trim( $main_category );
			$term = term_exists( $main_category, 'incident-type' );
			
			if ( $term && ! is_wp_error( $term ) ) {
				$parent_term_id = intval( $term['term_id'] );
				$resolved[] = $parent_term_id;
				if ( is_array( $changelog ) ) {
					$changelog[] = array(
						'row'      => $row_number,
						'action'   => 'category_found',
						'type'     => 'main_category',
						'name'     => $main_category,
						'term_id'  => $parent_term_id,
						'message'  => sprintf( __( 'Found existing main category "%s" (ID: %d)', 'jet-geometry-addon' ), $main_category, $parent_term_id ),
					);
				}
			} else {
				// Create main category (parent = 0)
				$created = wp_insert_term( $main_category, 'incident-type', array( 'parent' => 0 ) );
				if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
					$parent_term_id = intval( $created['term_id'] );
					$resolved[] = $parent_term_id;
					if ( is_array( $changelog ) ) {
						$changelog[] = array(
							'row'      => $row_number,
							'action'   => 'category_created',
							'type'     => 'main_category',
							'name'     => $main_category,
							'term_id'  => $parent_term_id,
							'message'  => sprintf( __( 'Created main category "%s" (ID: %d)', 'jet-geometry-addon' ), $main_category, $parent_term_id ),
						);
					}
				} elseif ( is_array( $changelog ) ) {
					$changelog[] = array(
						'row'      => $row_number,
						'action'   => 'category_error',
						'type'     => 'main_category',
						'name'     => $main_category,
						'message'  => sprintf( __( 'Failed to create main category "%s": %s', 'jet-geometry-addon' ), $main_category, is_wp_error( $created ) ? $created->get_error_message() : 'Unknown error' ),
					);
				}
			}
		}

		// Process subcategory as child of main category
		if ( ! empty( $subcategory ) ) {
			$subcategory_labels = $this->split_terms( $subcategory );
			
			foreach ( $subcategory_labels as $label ) {
				if ( '' === $label ) {
					continue;
				}

				$label = trim( $label );
				
				// Check if subcategory exists (with or without parent)
				$term = term_exists( $label, 'incident-type' );
				
				if ( $term && ! is_wp_error( $term ) ) {
					$term_id = intval( $term['term_id'] );
					$term_obj = get_term( $term_id, 'incident-type' );
					
					// If term exists but has wrong parent, update it
					if ( $term_obj && ! is_wp_error( $term_obj ) ) {
						$old_parent = intval( $term_obj->parent );
						if ( $parent_term_id > 0 && $old_parent !== $parent_term_id ) {
							// Update parent if main category is specified
							$updated = wp_update_term( $term_id, 'incident-type', array( 'parent' => $parent_term_id ) );
							if ( ! is_wp_error( $updated ) && is_array( $changelog ) ) {
								$parent_name = $parent_term_id > 0 ? get_term( $parent_term_id, 'incident-type' )->name : __( 'None', 'jet-geometry-addon' );
								$changelog[] = array(
									'row'      => $row_number,
									'action'   => 'category_updated',
									'type'     => 'subcategory',
									'name'     => $label,
									'term_id'  => $term_id,
									'parent_id' => $parent_term_id,
									'parent_name' => $parent_name,
									'message'  => sprintf( __( 'Updated subcategory "%s" (ID: %d) - changed parent from %d to %d (%s)', 'jet-geometry-addon' ), $label, $term_id, $old_parent, $parent_term_id, $parent_name ),
								);
							}
						} elseif ( is_array( $changelog ) ) {
							$changelog[] = array(
								'row'      => $row_number,
								'action'   => 'category_found',
								'type'     => 'subcategory',
								'name'     => $label,
								'term_id'  => $term_id,
								'parent_id' => $old_parent,
								'message'  => sprintf( __( 'Found existing subcategory "%s" (ID: %d)', 'jet-geometry-addon' ), $label, $term_id ),
							);
						}
					}
					$resolved[] = $term_id;
				} else {
					// Create subcategory with parent
					$parent = ( $parent_term_id > 0 ) ? $parent_term_id : 0;
					$created = wp_insert_term( $label, 'incident-type', array( 'parent' => $parent ) );
					if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
						$resolved[] = intval( $created['term_id'] );
						if ( is_array( $changelog ) ) {
							$parent_name = $parent > 0 ? get_term( $parent, 'incident-type' )->name : __( 'None', 'jet-geometry-addon' );
							$changelog[] = array(
								'row'      => $row_number,
								'action'   => 'category_created',
								'type'     => 'subcategory',
								'name'     => $label,
								'term_id'  => intval( $created['term_id'] ),
								'parent_id' => $parent,
								'parent_name' => $parent_name,
								'message'  => sprintf( __( 'Created subcategory "%s" (ID: %d) under parent "%s" (ID: %d)', 'jet-geometry-addon' ), $label, intval( $created['term_id'] ), $parent_name, $parent ),
							);
						}
					} elseif ( is_array( $changelog ) ) {
						$changelog[] = array(
							'row'      => $row_number,
							'action'   => 'category_error',
							'type'     => 'subcategory',
							'name'     => $label,
							'message'  => sprintf( __( 'Failed to create subcategory "%s": %s', 'jet-geometry-addon' ), $label, is_wp_error( $created ) ? $created->get_error_message() : 'Unknown error' ),
						);
					}
				}
			}
		}

		return $resolved;
	}

	/**
	 * Resolve subcategory labels to incident-type terms, creating them if needed.
	 * @deprecated Use resolve_incident_type_hierarchy instead for hierarchical support.
	 */
	private function resolve_incident_subcategories( $labels ) {
		if ( empty( $labels ) ) {
			return array();
		}

		$resolved = array();

		foreach ( $labels as $label ) {
			if ( '' === $label ) {
				continue;
			}

			$term = term_exists( $label, 'incident-type' );
			if ( $term && ! is_wp_error( $term ) ) {
				$resolved[] = intval( $term['term_id'] );
				continue;
			}

			$created = wp_insert_term( $label, 'incident-type' );
			if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
				$resolved[] = intval( $created['term_id'] );
			}
		}

		return $resolved;
	}

	/**
	 * Maybe set post date based on meta values.
	 */
	/**
	 * Set post date from date parts if available.
	 * For updates, only sets date if date parts are provided (preserves existing date otherwise).
	 *
	 * @param array $postarr Post array to modify.
	 * @param array $date_parts Array with 'year', 'month', 'day' keys.
	 */
	private function maybe_set_post_date( &$postarr, $date_parts ) {
		$year  = isset( $date_parts['year'] ) ? trim( (string) $date_parts['year'] ) : '';
		$month = isset( $date_parts['month'] ) ? trim( (string) $date_parts['month'] ) : '';
		$day   = isset( $date_parts['day'] ) ? trim( (string) $date_parts['day'] ) : '';

		// Only set date if year is provided (date data exists in CSV)
		if ( '' === $year ) {
			// For new posts without date, WordPress will use current date (default behavior)
			// For updates without date, existing date is already preserved above
			return;
		}

		$year  = intval( $year );
		$month_num = $this->parse_month_value( $month );
		$day_num   = ( '' !== $day ) ? intval( $day ) : 1;

		if ( $month_num <= 0 ) {
			$month_num = 1;
		}
		if ( $day_num <= 0 ) {
			$day_num = 1;
		}

		// Validate date
		if ( $year < 1970 || $year > 2100 ) {
			error_log( sprintf( '[JetGeometry] Invalid year %d, skipping date setting', $year ) );
			return;
		}

		$date_string = sprintf( '%04d-%02d-%02d 00:00:00', $year, $month_num, $day_num );
		
		// Always set date from CSV if provided (overrides any preserved date)
		$postarr['post_date']     = $date_string;
		$postarr['post_date_gmt'] = get_gmt_from_date( $date_string );
		$postarr['edit_date']     = true; // Important: tells WordPress to preserve this date even when status changes
		
		error_log( sprintf( '[JetGeometry] Setting post date from CSV: %s (edit_date=true)', $date_string ) );
	}

	/**
	 * Parse month string into number (1-12).
	 */
	private function parse_month_value( $value ) {
		if ( '' === $value ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			return intval( $value );
		}

		$map = array(
			'january'   => 1,
			'jan'       => 1,
			'february'  => 2,
			'feb'       => 2,
			'march'     => 3,
			'mar'       => 3,
			'april'     => 4,
			'apr'       => 4,
			'may'       => 5,
			'june'      => 6,
			'jun'       => 6,
			'july'      => 7,
			'jul'       => 7,
			'august'    => 8,
			'aug'       => 8,
			'september' => 9,
			'sep'       => 9,
			'october'   => 10,
			'oct'       => 10,
			'november'  => 11,
			'nov'       => 11,
			'december'  => 12,
			'dec'       => 12,
		);

		$lower = strtolower( $value );
		return isset( $map[ $lower ] ) ? $map[ $lower ] : 0;
	}

	/**
	 * Normalize location text for import - remove trailing dot and clean up.
	 *
	 * @param string $location_text Location text from CSV.
	 * @return string Normalized location text.
	 */
	private function normalize_location_for_import( $location_text ) {
		$location_text = trim( (string) $location_text );
		
		// Remove trailing dot
		$location_text = rtrim( $location_text, '.' );
		
		// Remove common prefixes
		$location_text = preg_replace( '/^Location\s*\([^)]*\):\s*/i', '', $location_text );
		
		// Remove trailing parentheses
		$location_text = preg_replace( '/\s*\([^)]*\)\s*$/', '', $location_text );
		
		$location_text = trim( $location_text );
		
		return $location_text;
	}

	/**
	 * Generate geometry meta from textual location if geometry is missing.
	 */
	private function maybe_generate_geometry_from_location( $post_id, $location_text ) {
		$geometry_meta_key = 'incident_geometry';
		$existing_geometry = get_post_meta( $post_id, $geometry_meta_key, true );
		
		// Also check hidden meta fields to ensure we don't overwrite existing geometry
		$prefix = Jet_Geometry_Utils::get_field_prefix( $geometry_meta_key );
		$existing_hidden_data = get_post_meta( $post_id, $prefix . '_geometry_data', true );

		// If geometry exists in either main field or hidden meta, don't overwrite
		if ( ! empty( $existing_geometry ) || ! empty( $existing_hidden_data ) ) {
			error_log( sprintf( '[JetGeometry] Geometry already exists for post ID %d (main: %s, hidden: %s), skipping geocoding to preserve existing geometry', 
				$post_id, 
				! empty( $existing_geometry ) ? 'yes' : 'no',
				! empty( $existing_hidden_data ) ? 'yes' : 'no'
			) );
			return;
		}

		// Use the same normalization function
		$location_text = $this->normalize_location_for_import( $location_text );
		
		if ( '' === $location_text ) {
			error_log( sprintf( '[JetGeometry] Empty location text for post ID %d', $post_id ) );
			return;
		}

		error_log( sprintf( '[JetGeometry] Geocoding location for post ID %d: %s', $post_id, $location_text ) );
		
		$coords = $this->geocode_location( $location_text );
		if ( empty( $coords ) || ! isset( $coords['lat'], $coords['lng'] ) ) {
			error_log( sprintf( '[JetGeometry] Failed to geocode location for post ID %d: %s', $post_id, $location_text ) );
			return;
		}

		error_log( sprintf( '[JetGeometry] Geocoded location for post ID %d: lat=%f, lng=%f', $post_id, $coords['lat'], $coords['lng'] ) );

		// Apply offset if JetEngine's "Avoid markers overlapping" option is enabled
		$coords = $this->maybe_add_offset_to_coordinates( $coords );

		$geometry = array(
			'type'       => 'Feature',
			'properties' => array(
				'pin' => array(
					'label' => $post_id,
				),
			),
			'geometry'   => array(
				'type'        => 'Point',
			'coordinates' => array( floatval( $coords['lng'] ), floatval( $coords['lat'] ) ),
		),
	);

	$geometry_json = wp_json_encode( $geometry );
	update_post_meta( $post_id, $geometry_meta_key, $geometry_json );
	
	// Also save lat/lng separately for compatibility with JetEngine
	update_post_meta( $post_id, 'incident_lat', $coords['lat'] );
	update_post_meta( $post_id, 'incident_lng', $coords['lng'] );

	// Mirror hidden JetEngine map field meta so the editor loads data correctly.
	if ( class_exists( 'Jet_Geometry_Utils' ) ) {
		Jet_Geometry_Utils::sync_map_field_meta(
			$post_id,
			'incident_geometry',
			$geometry['geometry'],
			floatval( $coords['lat'] ),
			floatval( $coords['lng'] )
		);
	}
	
	error_log( sprintf( '[JetGeometry] Saved geometry for post ID %d', $post_id ) );
	}

	/**
	 * Auto-generate geometry from location_csv when saving posts.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function auto_generate_geometry_on_save( $post_id, $post ) {
		// Skip autosave, revisions, and trash
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'trash' === $post->post_status ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if geometry already exists (including hidden meta)
		$geometry_meta_key = 'incident_geometry';
		$existing_geometry = get_post_meta( $post_id, $geometry_meta_key, true );
		$prefix = Jet_Geometry_Utils::get_field_prefix( $geometry_meta_key );
		$existing_hidden_data = get_post_meta( $post_id, $prefix . '_geometry_data', true );

		// If main geometry exists but hidden meta is missing, sync them
		if ( ! empty( $existing_geometry ) && empty( $existing_hidden_data ) ) {
			if ( class_exists( 'Jet_Geometry_Utils' ) ) {
				$geometry = json_decode( $existing_geometry, true );
				if ( $geometry && isset( $geometry['geometry'] ) ) {
					$lat = get_post_meta( $post_id, 'incident_lat', true );
					$lng = get_post_meta( $post_id, 'incident_lng', true );
					error_log( sprintf( '[JetGeometry] Syncing hidden meta for post ID %d (geometry exists but hidden meta missing)', $post_id ) );
					Jet_Geometry_Utils::sync_map_field_meta(
						$post_id,
						$geometry_meta_key,
						$geometry['geometry'],
						$lat ? floatval( $lat ) : null,
						$lng ? floatval( $lng ) : null
					);
				}
			}
			return;
		}

		// If both exist, skip
		if ( ! empty( $existing_geometry ) && ! empty( $existing_hidden_data ) ) {
			return;
		}

		// Check for location_csv or _incident_location
		$location_source = '';
		$location_csv = get_post_meta( $post_id, 'location_csv', true );
		$incident_location = get_post_meta( $post_id, '_incident_location', true );

		if ( ! empty( $location_csv ) ) {
			$location_source = $location_csv;
		} elseif ( ! empty( $incident_location ) ) {
			$location_source = $incident_location;
		}

		// If we have a location but no geometry, generate it
		if ( '' !== $location_source ) {
			error_log( sprintf( '[JetGeometry] Auto-generating geometry for post ID %d from location: %s', $post_id, $location_source ) );
			$this->maybe_generate_geometry_from_location( $post_id, $location_source );
		}
	}

	/**
	 * Force regenerate geometry meta from textual location (always updates, even if geometry exists).
	 * @deprecated Use maybe_generate_geometry_from_location() instead to preserve existing geometry.
	 */
	private function force_generate_geometry_from_location( $post_id, $location_text ) {
		// Check if geometry already exists - don't overwrite
		$geometry_meta_key = 'incident_geometry';
		$existing_geometry = get_post_meta( $post_id, $geometry_meta_key, true );

		if ( ! empty( $existing_geometry ) ) {
			error_log( sprintf( '[JetGeometry] Geometry already exists for post ID %d, skipping geocoding (preserving existing geometry)', $post_id ) );
			return;
		}

		// Use the same normalization function
		$location_text = $this->normalize_location_for_import( $location_text );
		
		if ( '' === $location_text ) {
			error_log( sprintf( '[JetGeometry] Empty location text for post ID %d', $post_id ) );
			return;
		}

		error_log( sprintf( '[JetGeometry] Geocoding location for post ID %d: %s', $post_id, $location_text ) );
		
		$coords = $this->geocode_location( $location_text );
		if ( empty( $coords ) || ! isset( $coords['lat'], $coords['lng'] ) ) {
			error_log( sprintf( '[JetGeometry] Failed to geocode location for post ID %d: %s', $post_id, $location_text ) );
			return;
		}

		error_log( sprintf( '[JetGeometry] Geocoded location for post ID %d: lat=%f, lng=%f', $post_id, $coords['lat'], $coords['lng'] ) );

		// Apply offset if JetEngine's "Avoid markers overlapping" option is enabled
		$coords = $this->maybe_add_offset_to_coordinates( $coords );

		$geometry = array(
			'type'       => 'Feature',
			'properties' => array(
				'pin' => array(
					'label' => $post_id,
				),
			),
			'geometry'   => array(
				'type'        => 'Point',
				'coordinates' => array( floatval( $coords['lng'] ), floatval( $coords['lat'] ) ),
			),
		);

		$geometry_json = wp_json_encode( $geometry );
		update_post_meta( $post_id, $geometry_meta_key, $geometry_json );
		
		// Also save lat/lng separately for compatibility with JetEngine
		update_post_meta( $post_id, 'incident_lat', $coords['lat'] );
		update_post_meta( $post_id, 'incident_lng', $coords['lng'] );

		if ( class_exists( 'Jet_Geometry_Utils' ) ) {
			Jet_Geometry_Utils::sync_map_field_meta(
				$post_id,
				'incident_geometry',
				$geometry['geometry'],
				floatval( $coords['lat'] ),
				floatval( $coords['lng'] )
			);
		}
		
		error_log( sprintf( '[JetGeometry] Saved geometry for post ID %d', $post_id ) );
	}

	/**
	 * Apply offset to coordinates if JetEngine's "Avoid markers overlapping" option is enabled.
	 * Uses the same logic as JetEngine to ensure consistency.
	 *
	 * @param array $coordinates Array with 'lat' and 'lng' keys.
	 * @return array Modified coordinates with offset applied if option is enabled.
	 */
	private function maybe_add_offset_to_coordinates( $coordinates = array() ) {
		if ( ! is_array( $coordinates ) || empty( $coordinates['lat'] ) || empty( $coordinates['lng'] ) ) {
			return $coordinates;
		}

		// Check if JetEngine is available and offset option is enabled
		if ( ! class_exists( '\Jet_Engine\Modules\Maps_Listings\Module' ) ) {
			return $coordinates;
		}

		$module = \Jet_Engine\Modules\Maps_Listings\Module::instance();
		if ( ! $module || ! $module->settings ) {
			return $coordinates;
		}

		$add_offset = $module->settings->get( 'add_offset' );

		if ( ! $add_offset ) {
			return $coordinates;
		}

		// Use the same offset calculation as JetEngine
		$offset_rate = apply_filters( 'jet-engine/maps-listing/offset-rate', 100000 );

		$offset_lat = ( 10 - rand( 0, 20 ) ) / $offset_rate;
		$offset_lng = ( 10 - rand( 0, 20 ) ) / $offset_rate;

		$coordinates['lat'] = floatval( $coordinates['lat'] ) + $offset_lat;
		$coordinates['lng'] = floatval( $coordinates['lng'] ) + $offset_lng;

		error_log( sprintf( '[JetGeometry] Applied offset to coordinates: lat_offset=%f, lng_offset=%f', $offset_lat, $offset_lng ) );

		return $coordinates;
	}

	/**
	 * Enhanced geocoding with JetEngine integration and fallback strategies.
	 * Uses JetEngine's geocoding provider settings if available, otherwise falls back to Nominatim.
	 */
	private function geocode_location( $query ) {
		$query = trim( $query );
		if ( '' === $query ) {
			return null;
		}

		// Strategy 1: Try JetEngine's geocoding provider if available
		if ( class_exists( '\Jet_Engine\Modules\Maps_Listings\Module' ) ) {
			$module = \Jet_Engine\Modules\Maps_Listings\Module::instance();
			if ( $module && $module->settings ) {
				$geocode_provider_id = $module->settings->get( 'geocode_provider' );
				
				if ( $geocode_provider_id ) {
					$geocode_provider = $module->providers->get_providers( 'geocode', $geocode_provider_id );
					
					if ( $geocode_provider ) {
						error_log( sprintf( '[JetGeometry] Attempting geocoding via JetEngine provider: %s for query: %s', $geocode_provider_id, $query ) );
						$result = $geocode_provider->get_location_data( $query );
						
						if ( $result && ! empty( $result['lat'] ) && ! empty( $result['lng'] ) ) {
							error_log( sprintf( '[JetGeometry] Geocoding success via JetEngine (%s): %s -> lat=%f, lng=%f', $geocode_provider_id, $query, $result['lat'], $result['lng'] ) );
							return array(
								'lat' => floatval( $result['lat'] ),
								'lng' => floatval( $result['lng'] ),
							);
						} else {
							error_log( sprintf( '[JetGeometry] JetEngine provider (%s) returned no results for query: %s', $geocode_provider_id, $query ) );
						}
					}
				}
			}
		}

		// Strategy 2: Try original query with Nominatim (fallback)
		$result = $this->geocode_with_nominatim( $query );
		if ( $result ) {
			return $result;
		}

		// Strategy 3: Try query variations with Nominatim
		$variations = $this->generate_location_variations( $query );
		foreach ( $variations as $variation ) {
			$result = $this->geocode_with_nominatim( $variation );
			if ( $result ) {
				error_log( sprintf( '[JetGeometry] Geocoding succeeded with variation: "%s" (original: "%s")', $variation, $query ) );
				return $result;
			}
		}

		error_log( sprintf( '[JetGeometry] All geocoding strategies failed for query: %s', $query ) );
		return null;
	}

	/**
	 * Generate location query variations to improve geocoding success rate.
	 *
	 * @param string $query Original location query.
	 * @return array Array of query variations.
	 */
	private function generate_location_variations( $query ) {
		$variations = array();
		
		// Remove common prefixes/suffixes that might confuse geocoding
		$patterns_to_remove = array(
			'/^Island of\s+/i',
			'/^Island\s+/i',
			'/,\s*Baltic Sea$/i',
			'/,\s*North Sea$/i',
			'/,\s*Mediterranean Sea$/i',
			'/,\s*Atlantic Ocean$/i',
		);
		
		$cleaned = $query;
		foreach ( $patterns_to_remove as $pattern ) {
			$cleaned = preg_replace( $pattern, '', $cleaned );
		}
		$cleaned = trim( $cleaned );
		if ( $cleaned !== $query && ! empty( $cleaned ) ) {
			$variations[] = $cleaned;
		}
		
		// Try adding country if not present (common countries for Baltic Sea)
		if ( stripos( $query, 'Denmark' ) === false && stripos( $query, 'Poland' ) === false && stripos( $query, 'Germany' ) === false ) {
			// Try with Denmark (for Bornholm)
			if ( stripos( $query, 'Bornholm' ) !== false ) {
				$variations[] = $cleaned . ', Denmark';
				$variations[] = 'Bornholm, Denmark';
			}
		}
		
		// Try just the main location name (first part before comma)
		$parts = explode( ',', $query );
		if ( count( $parts ) > 1 ) {
			$main_part = trim( $parts[0] );
			if ( ! empty( $main_part ) && $main_part !== $query ) {
				$variations[] = $main_part;
			}
		}
		
		return array_unique( $variations );
	}

	/**
	 * Geocode using Nominatim (OpenStreetMap) - fallback method.
	 *
	 * @param string $query Location query string.
	 * @return array|null Array with 'lat' and 'lng' keys, or null on failure.
	 */
	private function geocode_with_nominatim( $query ) {
		$query = trim( $query );
		if ( '' === $query ) {
			return null;
		}

		// Add a small delay to respect Nominatim's rate limiting (1 request per second)
		static $last_request_time = 0;
		$current_time = microtime( true );
		$time_since_last = $current_time - $last_request_time;
		if ( $time_since_last < 1.0 ) {
			usleep( ( 1.0 - $time_since_last ) * 1000000 );
		}
		$last_request_time = microtime( true );

		// Ensure UTF-8 encoding before URL encoding
		if ( ! mb_check_encoding( $query, 'UTF-8' ) ) {
			$query = mb_convert_encoding( $query, 'UTF-8', 'auto' );
		}

		$request_url = add_query_arg(
			array(
				'q'             => rawurlencode( $query ),
				'format'        => 'json',
				'limit'         => 5, // Get more results to find better match
				'addressdetails' => 1,
				'extratags'     => 1,
				'namedetails'   => 1,
			),
			'https://nominatim.openstreetmap.org/search'
		);

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'JetGeometryAddonImporter/1.0 (WordPress Plugin)',
			),
		);

		error_log( sprintf( '[JetGeometry] Nominatim geocoding request: %s', $request_url ) );
		
		$response = wp_remote_get( $request_url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( sprintf( '[JetGeometry] Nominatim geocoding error: %s', $response->get_error_message() ) );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			error_log( sprintf( '[JetGeometry] Nominatim HTTP error: %d', $code ) );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			error_log( '[JetGeometry] Nominatim empty response body' );
			return null;
		}

		$data = json_decode( $body, true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			error_log( sprintf( '[JetGeometry] Nominatim invalid JSON response for query: %s', $query ) );
			return null;
		}

		if ( empty( $data ) ) {
			error_log( sprintf( '[JetGeometry] Nominatim no results for query: %s', $query ) );
			return null;
		}

		// Try to find the best match (prefer places over administrative boundaries)
		$best = null;
		foreach ( $data as $result ) {
			if ( empty( $result['lat'] ) || empty( $result['lon'] ) ) {
				continue;
			}
			
			// Prefer results with 'place' type or high importance
			$importance = isset( $result['importance'] ) ? floatval( $result['importance'] ) : 0;
			
			if ( ! $best || $importance > ( isset( $best['importance'] ) ? floatval( $best['importance'] ) : 0 ) ) {
				$best = $result;
			}
		}

		if ( empty( $best ) ) {
			// Fallback to first result
			$best = array_shift( $data );
		}

		if ( empty( $best ) || empty( $best['lat'] ) || empty( $best['lon'] ) ) {
			error_log( sprintf( '[JetGeometry] Nominatim invalid result structure for query: %s', $query ) );
			return null;
		}

		$result = array(
			'lat' => floatval( $best['lat'] ),
			'lng' => floatval( $best['lon'] ),
		);
		
		error_log( sprintf( '[JetGeometry] Nominatim geocoding success: %s -> lat=%f, lng=%f', $query, $result['lat'], $result['lng'] ) );
		
		return $result;
	}

	/**
	 * Get saved mapping presets.
	 */
	private function get_incident_mapping_presets() {
		$presets = get_option( 'jet_geometry_incident_mappings', array() );
		if ( ! is_array( $presets ) ) {
			$presets = array();
		}
		return $presets;
	}

	/**
	 * Persist mapping presets.
	 */
	private function set_incident_mapping_presets( $presets ) {
		update_option( 'jet_geometry_incident_mappings', $presets );
	}

	/**
	 * Render debug table.
	 */
	private function render_debug_table() {
		?>
		<table class="wp-list-table widefat fixed striped" id="jet-debug-table">
			<thead>
				<tr>
					<th style="width: 60px;"><?php esc_html_e( 'ID', 'jet-geometry-addon' ); ?></th>
					<th><?php esc_html_e( 'Title', 'jet-geometry-addon' ); ?></th>
					<th style="width: 120px;"><?php esc_html_e( 'Status', 'jet-geometry-addon' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Country', 'jet-geometry-addon' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'Address Issue', 'jet-geometry-addon' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Geometry Type', 'jet-geometry-addon' ); ?></th>
					<th style="width: 80px;"><?php esc_html_e( 'Valid', 'jet-geometry-addon' ); ?></th>
					<th><?php esc_html_e( 'Additional Info', 'jet-geometry-addon' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="8" style="text-align: center; padding: 20px; color: #666;">
						<?php esc_html_e( 'Click "Start Debug" to begin analysis.', 'jet-geometry-addon' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<div id="jet-debug-pagination" style="margin-top: 10px;"></div>
		<?php
	}

	/**
	 * AJAX handler for getting debug list.
	 */
	public function ajax_get_debug_list() {
		check_ajax_referer( 'jet_geometry_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'jet-geometry-addon' ) ) );
		}

		$per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 100;
		$page     = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;

		// Call REST API endpoint directly instead of HTTP request
		$rest_endpoint = new Jet_Geometry_REST_Incident_Debug_List();
		$request       = new WP_REST_Request( 'GET', '/jet-geometry/v1/incident-debug-list' );
		$request->set_param( 'per_page', $per_page );
		$request->set_param( 'page', $page );

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			error_log( '[JetGeometry Debug] REST API error: ' . $response->get_error_message() );
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$data = $response->get_data();

		if ( isset( $data['posts'] ) && isset( $data['total'] ) ) {
			wp_send_json_success( $data );
		} else {
			error_log( '[JetGeometry Debug] Invalid response structure. Data keys: ' . implode( ', ', array_keys( $data ? $data : array() ) ) );
			wp_send_json_error( array( 'message' => __( 'Invalid response structure', 'jet-geometry-addon' ) . '. Response keys: ' . implode( ', ', array_keys( $data ? $data : array() ) ) ) );
		}
	}

	/**
	 * AJAX handler for saving debug JSON file to server.
	 */
	public function ajax_save_debug_json() {
		check_ajax_referer( 'jet_geometry_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'jet-geometry-addon' ) ) );
		}

		if ( ! isset( $_POST['json_data'] ) || empty( $_POST['json_data'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No JSON data provided', 'jet-geometry-addon' ) ) );
		}

		// Get plugin directory path
		$plugin_dir = JET_GEOMETRY_ADDON_PATH;
		$debug_dir  = $plugin_dir . 'debug';

		// Create debug directory if it doesn't exist
		if ( ! file_exists( $debug_dir ) ) {
			wp_mkdir_p( $debug_dir );
		}

		// Check if directory is writable
		if ( ! is_writable( $debug_dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Debug directory is not writable', 'jet-geometry-addon' ) ) );
		}

		// Generate filename with timestamp
		$filename = 'incident-debug-' . date( 'Y-m-d-H-i-s' ) . '.json';
		$filepath = $debug_dir . '/' . $filename;

		// Get JSON data (it's already a string from JavaScript)
		$json_data = wp_unslash( $_POST['json_data'] );

		// Validate JSON
		$decoded = json_decode( $json_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON data', 'jet-geometry-addon' ) . ': ' . json_last_error_msg() ) );
		}

		// Write file
		$result = file_put_contents( $filepath, $json_data );

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save file', 'jet-geometry-addon' ) ) );
		}

		// Return success with file info
		wp_send_json_success( array(
			'message'  => __( 'File saved successfully', 'jet-geometry-addon' ),
			'filename' => $filename,
			'filepath' => str_replace( ABSPATH, '', $filepath ),
			'size'     => size_format( $result ),
		) );
	}

	/**
	 * AJAX handler for regenerating markers cache
	 */
	public function ajax_regenerate_cache() {
		check_ajax_referer( 'jet_geometry_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'jet-geometry-addon' ) ) );
		}

		if ( ! class_exists( 'Jet_Geometry_Markers_Cache' ) ) {
			wp_send_json_error( array( 'message' => __( 'Cache class not found', 'jet-geometry-addon' ) ) );
		}

		$cache_manager = new Jet_Geometry_Markers_Cache();
		$result = $cache_manager->generate_cache();

		if ( $result ) {
			// Get cache info after generation
			$cache_all_file = JET_GEOMETRY_ADDON_PATH . 'cache/markers-all.json';
			$cache_info = null;
			if ( file_exists( $cache_all_file ) ) {
				$cache_data = json_decode( file_get_contents( $cache_all_file ), true );
				if ( $cache_data ) {
					$cache_info = array(
						'total_posts' => isset( $cache_data['total_posts'] ) ? $cache_data['total_posts'] : 0,
						'markers_count' => isset( $cache_data['markers'] ) ? count( $cache_data['markers'] ) : 0,
						'last_update' => isset( $cache_data['last_update'] ) ? $cache_data['last_update'] : '',
					);
				}
			}
			
			$message = __( 'Cache regenerated successfully', 'jet-geometry-addon' );
			if ( $cache_info ) {
				$message .= sprintf( ' - %s: %d', __( 'Wygenerowano incidentów', 'jet-geometry-addon' ), $cache_info['markers_count'] );
			}
			
			wp_send_json_success( array(
				'message' => $message,
				'cache_info' => $cache_info,
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Failed to regenerate cache', 'jet-geometry-addon' ),
			) );
		}
	}
}

