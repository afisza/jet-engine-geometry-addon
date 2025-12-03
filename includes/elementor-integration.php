<?php
/**
 * Elementor integration for geometry fields
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Elementor_Integration
 */
class Jet_Geometry_Elementor_Integration {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add geometry field selector to Map Listing widget (Content tab)
		add_action( 'elementor/element/jet-engine-maps-listing/section_general/after_section_end', array( $this, 'add_geometry_controls' ), 10 );
		
		// Add styling controls to Map Listing widget (Style tab)
		add_action( 'elementor/element/jet-engine-maps-listing/section_general/after_section_end', array( $this, 'add_geometry_style_controls' ), 10 );
		
		// Modify default settings for map listing
		add_filter( 'jet-engine/maps-listing/render/default-settings', array( $this, 'modify_default_settings' ), 99 );
		
		// Pass styling settings to frontend (NOTE: maps-listingS with S!)
		add_filter( 'jet-engine/maps-listings/data-settings', array( $this, 'add_geometry_styling_to_frontend' ), 10, 2 );
		
		// Add frontend script for Elementor editor
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_elementor_preview_scripts' ) );
	}

	/**
	 * Add geometry field controls to Map Listing widget
	 *
	 * @param object $widget Widget instance.
	 */
	public function add_geometry_controls( $widget ) {
		$widget->start_controls_section(
			'section_geometry_field',
			array(
				'label' => __( 'Geometry Field (Addon)', 'jet-geometry-addon' ),
			)
		);

		$widget->add_control(
			'use_geometry_field',
			array(
				'label'       => __( 'Use Map Geometry Field', 'jet-geometry-addon' ),
				'description' => __( 'Use Map Geometry field for coordinates instead of manual lat/lng fields', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
			)
		);

		$widget->add_control(
			'geometry_field_name',
			array(
				'label'       => __( 'Geometry Field Name', 'jet-geometry-addon' ),
				'description' => __( 'Enter the field name of your Map Geometry field (e.g., incident_geometry)', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'incident_geometry',
				'label_block' => true,
				'condition'   => array(
					'use_geometry_field' => 'yes',
				),
			)
		);

		$widget->add_control(
			'geometry_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => __( '<strong>Note:</strong> When using Geometry Field, the "Lat Lng Address Meta Field" setting will be automatically configured.', 'jet-geometry-addon' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'use_geometry_field' => 'yes',
				),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * Add styling controls to Map Listing widget (Style tab)
	 *
	 * @param object $widget Widget instance.
	 */
	public function add_geometry_style_controls( $widget ) {
		$widget->start_controls_section(
			'section_geometry_styling',
			array(
				'label' => __( 'Geometry Styling (Addon)', 'jet-geometry-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$widget->add_control(
			'geometry_polygon_fill_color',
			array(
				'label'       => __( 'Polygon Fill Color', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#ff0000',
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_fill_opacity',
			array(
				'label'       => __( 'Fill Opacity', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1,
						'step' => 0.1,
					),
				),
				'default'     => array(
					'size' => 0.3,
				),
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_line_color',
			array(
				'label'       => __( 'Line/Border Color', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#ff0000',
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_line_width',
			array(
				'label'       => __( 'Line/Border Width', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min'  => 1,
						'max'  => 10,
						'step' => 1,
					),
				),
				'default'     => array(
					'size' => 2,
				),
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_cluster_color',
			array(
				'label'       => __( 'Marker Cluster Color', 'jet-geometry-addon' ),
				'description' => __( 'Background color for grouped markers (clusters)', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#51bbd6',
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_cluster_text_color',
			array(
				'label'       => __( 'Cluster Text Color', 'jet-geometry-addon' ),
				'description' => __( 'Text color for cluster count number', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#ffffff',
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_preloader_heading',
			array(
				'label'     => __( 'Map Preloader', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_control(
			'geometry_enable_preloader',
			array(
				'label'        => __( 'Show Preloader', 'jet-geometry-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'jet-geometry-addon' ),
				'label_off'    => __( 'Hide', 'jet-geometry-addon' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$widget->add_control(
			'geometry_preloader_color',
			array(
				'label'       => __( 'Preloader Spinner Color', 'jet-geometry-addon' ),
				'description' => __( 'Color of the loading spinner', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#3498db',
				'condition'   => array(
					'geometry_enable_preloader' => 'yes',
				),
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_map_colors_heading',
			array(
				'label'     => __( 'Map Theme Colors', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$widget->add_control(
			'geometry_water_color',
			array(
				'label'       => __( 'Water Color', 'jet-geometry-addon' ),
				'description' => __( 'Color for water/ocean areas on the map', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#133437',
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_land_color',
			array(
				'label'       => __( 'Land Color', 'jet-geometry-addon' ),
				'description' => __( 'Color for land areas on the map', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#1b4143',
				'render_type' => 'template',
			)
		);

		$widget->add_control(
			'geometry_boundary_color',
			array(
				'label'       => __( 'Boundary/Border Color', 'jet-geometry-addon' ),
				'description' => __( 'Color for country boundaries', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#275e61',
				'render_type' => 'template',
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * Modify default settings to add geometry field support
	 *
	 * @param array $settings Default settings.
	 * @return array
	 */
	public function modify_default_settings( $settings ) {
		// Add our custom settings to defaults
		$settings['use_geometry_field'] = '';
		$settings['geometry_field_name'] = 'incident_geometry';
		$settings['geometry_polygon_fill_color'] = '#ff0000';
		$settings['geometry_fill_opacity'] = array( 'size' => 0.3 );
		$settings['geometry_line_color'] = '#ff0000';
		$settings['geometry_line_width'] = array( 'size' => 2 );
		$settings['geometry_cluster_color'] = '#51bbd6';
		$settings['geometry_cluster_text_color'] = '#ffffff';
		$settings['geometry_preloader_color'] = '#3498db';
		$settings['geometry_enable_preloader'] = 'yes';
		$settings['geometry_water_color'] = '#133437';
		$settings['geometry_land_color'] = '#1b4143';
		$settings['geometry_boundary_color'] = '#275e61';
		
		return $settings;
	}

	/**
	 * Add geometry styling to frontend data settings
	 *
	 * @param array $general General settings array.
	 * @param array $settings Widget settings.
	 * @return array
	 */
	public function add_geometry_styling_to_frontend( $general, $settings ) {
		
		// Add geometry styling settings to frontend
		$general['geometryStyling'] = array(
			'polygonFillColor'   => ! empty( $settings['geometry_polygon_fill_color'] ) ? $settings['geometry_polygon_fill_color'] : '#ff0000',
			'fillOpacity'        => ! empty( $settings['geometry_fill_opacity']['size'] ) ? floatval( $settings['geometry_fill_opacity']['size'] ) : 0.3,
			'lineColor'          => ! empty( $settings['geometry_line_color'] ) ? $settings['geometry_line_color'] : '#ff0000',
			'lineWidth'          => ! empty( $settings['geometry_line_width']['size'] ) ? intval( $settings['geometry_line_width']['size'] ) : 2,
			'clusterColor'       => ! empty( $settings['geometry_cluster_color'] ) ? $settings['geometry_cluster_color'] : '#51bbd6',
			'clusterTextColor'   => ! empty( $settings['geometry_cluster_text_color'] ) ? $settings['geometry_cluster_text_color'] : '#ffffff',
			'preloaderColor'     => ! empty( $settings['geometry_preloader_color'] ) ? $settings['geometry_preloader_color'] : '#3498db',
			'showPreloader'      => ! isset( $settings['geometry_enable_preloader'] ) || 'yes' === $settings['geometry_enable_preloader'],
			'waterColor'         => ! empty( $settings['geometry_water_color'] ) ? $settings['geometry_water_color'] : '#133437',
			'landColor'          => ! empty( $settings['geometry_land_color'] ) ? $settings['geometry_land_color'] : '#1b4143',
			'boundaryColor'      => ! empty( $settings['geometry_boundary_color'] ) ? $settings['geometry_boundary_color'] : '#275e61',
		);
		
		// Set default center to Europe if not set and auto_center is false
		if ( empty( $general['customCenter'] ) && empty( $general['autoCenter'] ) ) {
			$general['customCenter'] = array(
				'lat' => 50.0,  // Central Europe latitude
				'lng' => 15.0,  // Central Europe longitude
			);
			$general['customZoom'] = 4; // Zoom level to see most of Europe
		}
		
		
		return $general;
	}

	/**
	 * Enqueue scripts for Elementor preview
	 */
	public function enqueue_elementor_preview_scripts() {
		wp_enqueue_script(
			'jet-geometry-elementor-preview',
			jet_geometry_addon()->plugin_url( 'assets/js/admin/elementor-preview.js' ),
			array( 'jquery', 'elementor-frontend' ),
			JET_GEOMETRY_ADDON_VERSION,
			true
		);
	}
}

