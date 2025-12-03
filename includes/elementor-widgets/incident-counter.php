<?php
/**
 * Incident Counter Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Incident Counter Widget
 */
class Jet_Geometry_Incident_Counter_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name
	 */
	public function get_name() {
		return 'jet-geometry-incident-counter';
	}

	/**
	 * Get widget title
	 */
	public function get_title() {
		return __( 'Incident Counter', 'jet-geometry-addon' );
	}

	/**
	 * Get widget icon
	 */
	public function get_icon() {
		return 'eicon-counter';
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
				'label' => __( 'Content', 'jet-geometry-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'singular_text',
			array(
				'label'   => __( 'Singular Text', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'incident', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'plural_text',
			array(
				'label'   => __( 'Plural Text', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'incidents', 'jet-geometry-addon' ),
			)
		);

		$this->end_controls_section();

		// Style Section
		$this->start_controls_section(
			'style_section',
			array(
				'label' => __( 'Style', 'jet-geometry-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs( 'style_tabs' );

		$this->start_controls_tab(
			'style_tab_normal',
			array(
				'label' => __( 'Normal', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'background_color',
			array(
				'label'     => __( 'Background Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#0d6b73',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-counter' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#f1fefb',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-counter .jet-incident-text' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'number_color',
			array(
				'label'     => __( 'Number Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-count' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'border_color',
			array(
				'label'     => __( 'Border Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(241, 254, 251, 0.16)',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-counter' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'border_width',
			array(
				'label'      => __( 'Border Width', 'jet-geometry-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px' ),
				'default'    => array(
					'top'    => 1,
					'right'  => 1,
					'bottom' => 1,
					'left'   => 1,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .jet-incident-counter' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'style_tab_hover',
			array(
				'label' => __( 'Hover', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'hover_background_color',
			array(
				'label'     => __( 'Background Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#11808a',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-counter:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'hover_text_color',
			array(
				'label'     => __( 'Text Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(241, 254, 251, 0.9)',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-counter:hover .jet-incident-text' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'hover_number_color',
			array(
				'label'     => __( 'Number Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-counter:hover .jet-incident-count' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'hover_border_color',
			array(
				'label'     => __( 'Border Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(241, 254, 251, 0.26)',
				'selectors' => array(
					'{{WRAPPER}} .jet-incident-counter:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'hover_border_width',
			array(
				'label'      => __( 'Border Width', 'jet-geometry-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-incident-counter:hover' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .jet-incident-counter',
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => __( 'Border Radius', 'jet-geometry-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-incident-counter' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => __( 'Padding', 'jet-geometry-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-incident-counter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		?>
		<div class="jet-incident-counter" 
		     data-singular="<?php echo esc_attr( $settings['singular_text'] ); ?>"
		     data-plural="<?php echo esc_attr( $settings['plural_text'] ); ?>">
			<span class="jet-incident-count">0</span>
			<span class="jet-incident-text"><?php echo esc_html( $settings['plural_text'] ); ?></span>
		</div>
		<?php
	}
}




