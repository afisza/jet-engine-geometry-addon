<?php
/**
 * Elementor Widgets Registration
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Elementor_Widgets
 */
class Jet_Geometry_Elementor_Widgets {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register widget category
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_elementor_widget_categories' ) );
		
		// Register widgets
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		
		// Enqueue widget scripts
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_widget_scripts' ) );
		
		// Enqueue widget styles
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_widget_styles' ) );
	}

	/**
	 * Add custom Elementor widget category
	 */
	public function add_elementor_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'jet-geometry-widgets',
			array(
				'title' => __( 'Jet Geometry Widgets', 'jet-geometry-addon' ),
				'icon'  => 'fa fa-map',
			)
		);
	}

	/**
	 * Register Elementor widgets
	 */
	public function register_widgets( $widgets_manager ) {
		// Require widget files
		require_once jet_geometry_addon()->plugin_path( 'includes/elementor-widgets/reset-map-zoom.php' );
		require_once jet_geometry_addon()->plugin_path( 'includes/elementor-widgets/country-layers-toggle.php' );
		require_once jet_geometry_addon()->plugin_path( 'includes/elementor-widgets/incident-counter.php' );
		require_once jet_geometry_addon()->plugin_path( 'includes/elementor-widgets/timeline-filter.php' );
		require_once jet_geometry_addon()->plugin_path( 'includes/elementor-widgets/country-highlight-settings.php' );

		// Register widgets
		$widgets_manager->register( new \Jet_Geometry_Reset_Map_Zoom_Widget() );
		$widgets_manager->register( new \Jet_Geometry_Country_Layers_Toggle_Widget() );
		$widgets_manager->register( new \Jet_Geometry_Incident_Counter_Widget() );
		$widgets_manager->register( new \Jet_Geometry_Country_Highlight_Settings_Widget() );

		if ( class_exists( '\Jet_Geometry_Timeline_Filter_Widget' ) ) {
			$widgets_manager->register( new \Jet_Geometry_Timeline_Filter_Widget() );
		}
	}

	/**
	 * Enqueue widget scripts
	 */
	public function enqueue_widget_scripts() {
		wp_enqueue_script(
			'jet-geometry-widgets',
			jet_geometry_addon()->plugin_url( 'assets/js/elementor-widgets.js' ),
			array( 'jquery', 'jet-country-layers' ),
			JET_GEOMETRY_ADDON_VERSION,
			true
		);
	}

	/**
	 * Enqueue widget styles
	 */
	public function enqueue_widget_styles() {
		wp_enqueue_style(
			'jet-geometry-widgets',
			jet_geometry_addon()->plugin_url( 'assets/css/elementor-widgets.css' ),
			array(),
			JET_GEOMETRY_ADDON_VERSION
		);
	}
}




