<?php
/**
 * Country Layers Toggle Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Country Layers Toggle Widget
 */
class Jet_Geometry_Country_Layers_Toggle_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name
	 */
	public function get_name() {
		return 'jet-geometry-country-layers-toggle';
	}

	/**
	 * Get widget title
	 */
	public function get_title() {
		return __( 'Country Layers Toggle', 'jet-geometry-addon' );
	}

	/**
	 * Get widget icon
	 */
	public function get_icon() {
		return 'eicon-toggle';
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
			'label_text',
			array(
				'label'   => __( 'Label Text', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Show Country Layers', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'default_state',
			array(
				'label'        => __( 'Default State', 'jet-geometry-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'jet-geometry-addon' ),
				'label_off'    => __( 'Off', 'jet-geometry-addon' ),
				'return_value' => 'on',
				'default'      => 'on',
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
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper' => 'background-color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-country-layers-label' => 'color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper' => 'border-color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'toggle_off_color',
			array(
				'label'     => __( 'Toggle Off Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(255, 255, 255, 0.2)',
				'selectors' => array(
					'{{WRAPPER}} .jet-country-layers-switch' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_on_color',
			array(
				'label'     => __( 'Toggle On Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#19b5a7',
				'selectors' => array(
					'{{WRAPPER}} .jet-country-layers-checkbox:checked + .jet-country-layers-switch' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'no_incident_heading',
			array(
				'label'     => __( 'Countries Without Incidents', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'no_incident_fill_color',
			array(
				'label'   => __( 'Polygon Fill Color', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#3b82f6',
			)
		);

		$this->add_control(
			'no_incident_border_color',
			array(
				'label'   => __( 'Border Color', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#3b82f6',
			)
		);

		$this->add_control(
			'no_incident_border_width',
			array(
				'label'   => __( 'Border Width', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'default' => array(
					'size' => 1,
					'unit' => 'px',
				),
				'range'   => array(
					'px' => array(
						'min'  => 0,
						'max'  => 10,
						'step' => 0.1,
					),
				),
			)
		);

		$this->add_control(
			'incident_heading',
			array(
				'label'     => __( 'Countries With Incidents', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'incident_fill_color',
			array(
				'label'   => __( 'Polygon Fill Color', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#ef4444',
			)
		);

		$this->add_control(
			'incident_border_color',
			array(
				'label'   => __( 'Border Color', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#ef4444',
			)
		);

		$this->add_control(
			'incident_border_width',
			array(
				'label'   => __( 'Border Width', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'default' => array(
					'size' => 2,
					'unit' => 'px',
				),
				'range'   => array(
					'px' => array(
						'min'  => 0,
						'max'  => 10,
						'step' => 0.1,
					),
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
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'hover_text_color',
			array(
				'label'     => __( 'Text Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#f1fefb',
				'selectors' => array(
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper:hover .jet-country-layers-label' => 'color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper:hover' => 'border-color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper:hover' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .jet-country-layers-label',
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => __( 'Border Radius', 'jet-geometry-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .jet-country-layers-toggle-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		$checked  = 'on' === $settings['default_state'] ? 'checked' : '';
		$no_incident_border_width = isset( $settings['no_incident_border_width']['size'] ) ? $settings['no_incident_border_width']['size'] : 1;
		$incident_border_width    = isset( $settings['incident_border_width']['size'] ) ? $settings['incident_border_width']['size'] : 2;
		$wrapper_attrs            = array(
			'class'                             => 'jet-country-layers-toggle-wrapper',
			'data-no-incident-fill'             => ! empty( $settings['no_incident_fill_color'] ) ? $settings['no_incident_fill_color'] : '#3b82f6',
			'data-no-incident-border'           => ! empty( $settings['no_incident_border_color'] ) ? $settings['no_incident_border_color'] : '#3b82f6',
			'data-no-incident-border-width'     => $no_incident_border_width,
			'data-incident-fill'                => ! empty( $settings['incident_fill_color'] ) ? $settings['incident_fill_color'] : '#ef4444',
			'data-incident-border'              => ! empty( $settings['incident_border_color'] ) ? $settings['incident_border_color'] : '#ef4444',
			'data-incident-border-width'        => $incident_border_width,
		);

		$attr_string = '';

		foreach ( $wrapper_attrs as $attr => $value ) {
			$attr_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
		}
		?>
		<div<?php echo $attr_string; ?>>
			<span class="jet-country-layers-label"><?php echo esc_html( $settings['label_text'] ); ?></span>
			<label class="jet-country-layers-toggle">
				<input type="checkbox" class="jet-country-layers-checkbox" <?php echo $checked; ?>>
				<span class="jet-country-layers-switch"></span>
			</label>
		</div>
		<?php
	}
}




