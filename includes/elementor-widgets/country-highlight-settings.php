<?php
/**
 * Country Highlight Settings Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Country Highlight Settings Widget
 */
class Jet_Geometry_Country_Highlight_Settings_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name
	 */
	public function get_name() {
		return 'jet-geometry-country-highlight-settings';
	}

	/**
	 * Get widget title
	 */
	public function get_title() {
		return __( 'Country Highlight Settings', 'jet-geometry-addon' );
	}

	/**
	 * Get widget icon
	 */
	public function get_icon() {
		return 'eicon-highlight';
	}

	/**
	 * Get widget categories
	 */
	public function get_categories() {
		return array( 'jet-geometry-widgets' );
	}

	/**
	 * Register widget controls
	 */
	protected function register_controls() {
		
		// Content Section
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Highlight Settings', 'jet-geometry-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'fill_color',
			array(
				'label'   => __( 'Fill Color', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#f25f5c',
				'description' => __( 'Color used to highlight selected country in filters', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'fill_opacity',
			array(
				'label'       => __( 'Fill Opacity', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'default'     => array(
					'size' => 0.45,
				),
				'range'        => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1,
						'step' => 0.01,
					),
				),
				'description' => __( 'Opacity of the highlight fill (0 = transparent, 1 = opaque)', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'outline_enabled',
			array(
				'label'        => __( 'Enable Outline', 'jet-geometry-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'jet-geometry-addon' ),
				'label_off'    => __( 'No', 'jet-geometry-addon' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Show outline border around highlighted country', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'outline_color',
			array(
				'label'       => __( 'Outline Color', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '#f25f5c',
				'condition'   => array(
					'outline_enabled' => 'yes',
				),
				'description' => __( 'Color of the outline border', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'outline_width',
			array(
				'label'       => __( 'Outline Width', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'default'     => array(
					'size' => 2.5,
				),
				'range'        => array(
					'px' => array(
						'min'  => 0,
						'max'  => 10,
						'step' => 0.1,
					),
				),
				'condition'   => array(
					'outline_enabled' => 'yes',
				),
				'description' => __( 'Width of the outline border in pixels', 'jet-geometry-addon' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		
		// This widget is invisible on frontend - it only provides settings
		// Settings are passed via data attributes to JavaScript
		$fill_color = isset( $settings['fill_color'] ) ? esc_attr( $settings['fill_color'] ) : '#f25f5c';
		$fill_opacity = isset( $settings['fill_opacity']['size'] ) ? floatval( $settings['fill_opacity']['size'] ) : 0.45;
		$outline_enabled = isset( $settings['outline_enabled'] ) && 'yes' === $settings['outline_enabled'];
		$outline_color = isset( $settings['outline_color'] ) ? esc_attr( $settings['outline_color'] ) : '#f25f5c';
		$outline_width = isset( $settings['outline_width']['size'] ) ? floatval( $settings['outline_width']['size'] ) : 2.5;
		
		?>
		<div class="jet-country-highlight-settings" 
			data-fill-color="<?php echo esc_attr( $fill_color ); ?>"
			data-fill-opacity="<?php echo esc_attr( $fill_opacity ); ?>"
			data-outline-enabled="<?php echo $outline_enabled ? 'yes' : 'no'; ?>"
			data-outline-color="<?php echo esc_attr( $outline_color ); ?>"
			data-outline-width="<?php echo esc_attr( $outline_width ); ?>"
			style="display: none;">
		</div>
		<?php
	}
}




