<?php
/**
 * Reset Map Zoom Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Reset Map Zoom Widget
 */
class Jet_Geometry_Reset_Map_Zoom_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name
	 */
	public function get_name() {
		return 'jet-geometry-reset-map-zoom';
	}

	/**
	 * Get widget title
	 */
	public function get_title() {
		return __( 'Reset Map Zoom', 'jet-geometry-addon' );
	}

	/**
	 * Get widget icon
	 */
	public function get_icon() {
		return 'eicon-refresh';
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
			'button_text',
			array(
				'label'   => __( 'Button Text', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Reset Map Zoom', 'jet-geometry-addon' ),
			)
		);

		$this->add_control(
			'show_icon',
			array(
				'label'        => __( 'Show Icon', 'jet-geometry-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'jet-geometry-addon' ),
				'label_off'    => __( 'No', 'jet-geometry-addon' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'selected_icon',
			array(
				'label'     => __( 'Icon', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'eicon-refresh',
					'library' => 'elementor',
				),
				'condition' => array(
					'show_icon' => 'yes',
				),
			)
		);

		$this->add_control(
			'reset_zoom_level',
			array(
				'label'       => __( 'Reset Zoom Level', 'jet-geometry-addon' ),
				'description' => __( 'Zoom level when resetting the map. The map will center on Europe. Lower values show more area (e.g., 3 = wider view, 5 = closer view).', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 4,
				'min'         => 1,
				'max'         => 10,
				'step'        => 0.5,
				'separator'   => 'before',
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
					'{{WRAPPER}} .jet-reset-map-zoom' => 'background-color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-reset-map-zoom' => 'color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-reset-map-zoom' => 'border-color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-reset-map-zoom' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Icon Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .jet-reset-map-zoom .jet-reset-icon'      => 'color: {{VALUE}};',
					'{{WRAPPER}} .jet-reset-map-zoom .jet-reset-icon i'    => 'color: {{VALUE}};',
					'{{WRAPPER}} .jet-reset-map-zoom .jet-reset-icon svg'  => 'fill: {{VALUE}}; stroke: {{VALUE}};',
				),
				'condition' => array(
					'show_icon' => 'yes',
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
					'{{WRAPPER}} .jet-reset-map-zoom:hover' => 'background-color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-reset-map-zoom:hover' => 'color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-reset-map-zoom:hover' => 'border-color: {{VALUE}};',
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
					'{{WRAPPER}} .jet-reset-map-zoom:hover' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'hover_icon_color',
			array(
				'label'     => __( 'Icon Color', 'jet-geometry-addon' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .jet-reset-map-zoom:hover .jet-reset-icon'     => 'color: {{VALUE}};',
					'{{WRAPPER}} .jet-reset-map-zoom:hover .jet-reset-icon i'   => 'color: {{VALUE}};',
					'{{WRAPPER}} .jet-reset-map-zoom:hover .jet-reset-icon svg' => 'fill: {{VALUE}}; stroke: {{VALUE}};',
				),
				'condition' => array(
					'show_icon' => 'yes',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .jet-reset-map-zoom',
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => __( 'Border Radius', 'jet-geometry-addon' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jet-reset-map-zoom' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .jet-reset-map-zoom' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		$reset_zoom = isset( $settings['reset_zoom_level'] ) ? floatval( $settings['reset_zoom_level'] ) : 4;
		?>
		<button class="jet-reset-map-zoom" type="button" data-reset-zoom="<?php echo esc_attr( $reset_zoom ); ?>">
			<?php if ( 'yes' === $settings['show_icon'] ) : ?>
				<span class="jet-reset-icon" aria-hidden="true">
					<?php
					$icon_settings = ! empty( $settings['selected_icon']['value'] )
						? $settings['selected_icon']
						: array(
							'value'   => 'fas fa-undo-alt',
							'library' => 'fa-solid',
						);

					\Elementor\Icons_Manager::render_icon(
						$icon_settings,
						array(
							'aria-hidden' => 'true',
							'class'       => 'jet-reset-map-zoom__icon',
						)
					);
					?>
				</span>
			<?php endif; ?>
			<span><?php echo esc_html( $settings['button_text'] ); ?></span>
		</button>
		<?php
	}
}




