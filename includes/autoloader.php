<?php
/**
 * Autoloader for plugin classes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Autoloader
 */
class Jet_Geometry_Autoloader {

	/**
	 * Class prefix
	 *
	 * @var string
	 */
	private static $prefix = 'Jet_';

	/**
	 * Classes map
	 *
	 * @var array
	 */
	private static $classes_map = array();

	/**
	 * Register autoloader
	 */
	public static function register() {
		self::init_classes_map();
		spl_autoload_register( array( __CLASS__, 'load_class' ) );
	}

	/**
	 * Initialize classes map
	 */
	private static function init_classes_map() {
		self::$classes_map = array(
			// Core classes
			'Jet_Geometry_Field'              => 'includes/geometry-field.php',
			'Jet_Geometry_Field_Storage'      => 'includes/geometry-field-storage.php',
			'Jet_Geometry_Renderer'           => 'includes/geometry-renderer.php',
			'Jet_Geometry_Admin_Assets'       => 'includes/admin-assets.php',
			'Jet_Country_Layers'              => 'includes/country-layers.php',
			'Jet_Geometry_Filters_Integration' => 'includes/filters-integration.php',
			'Jet_Geometry_Elementor_Integration' => 'includes/elementor-integration.php',
			'Jet_Geometry_Elementor_Widgets'  => 'includes/elementor-widgets.php',

			// Admin classes
			'Jet_Geometry_Admin_Settings'     => 'includes/admin/settings-page.php',
			'Jet_Geometry_Admin_Country_List' => 'includes/admin/country-list-table.php',
			'Jet_Geometry_Admin_Country_Geometry' => 'includes/admin/country-geometry-field.php',
			'Jet_Geometry_Country_Geometry_Migrator' => 'includes/admin/country-geometry-migrator.php',
			'Jet_Geometry_Incident_Geometry_Migrator' => 'includes/admin/incident-geometry-migrator.php',

			// REST API classes
			'Jet_Geometry_REST_Base'          => 'includes/rest-api/base.php',
			'Jet_Geometry_REST_Validate'      => 'includes/rest-api/validate-geometry.php',
			'Jet_Geometry_REST_Country_Import' => 'includes/rest-api/country-import.php',
			'Jet_Geometry_REST_Countries_Geojson' => 'includes/rest-api/countries-geojson.php',
			'Jet_Geometry_REST_Country_Incidents' => 'includes/rest-api/country-incidents.php',
			'Jet_Geometry_REST_Markers_Debug' => 'includes/rest-api/markers-debug.php',
			'Jet_Geometry_REST_Incident_Geometry_Stats' => 'includes/rest-api/incident-geometry-stats.php',
			'Jet_Geometry_REST_Incident_Debug_List' => 'includes/rest-api/incident-debug-list.php',

			// Utility classes
			'Jet_Geometry_Utils'              => 'includes/utils.php',
			'Jet_Geometry_GeoJSON_Simplifier' => 'includes/geojson-simplifier.php',
			'Jet_Geometry_Country_Geojson_File' => 'includes/country-geojson-file.php',
			'Jet_Geometry_Markers_Cache' => 'includes/markers-cache.php',
		);
	}

	/**
	 * Load class file
	 *
	 * @param string $class Class name.
	 */
	public static function load_class( $class ) {
		// Check if class starts with our prefix
		if ( 0 !== strpos( $class, self::$prefix ) ) {
			return;
		}

		// Check if class exists in our map
		if ( ! isset( self::$classes_map[ $class ] ) ) {
			return;
		}

		$file_path = JET_GEOMETRY_ADDON_PATH . self::$classes_map[ $class ];

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}

	/**
	 * Get classes map
	 *
	 * @return array
	 */
	public static function get_classes_map() {
		return self::$classes_map;
	}
}

