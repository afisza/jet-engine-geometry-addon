<?php
/**
 * Timeline Filter Widget
 *
 * Custom wrapper around JetSmartFilters range filter styled for the map timeline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure Elementor and JetSmartFilters base widget are available.
if ( ! class_exists( '\Elementor\Widget_Base' ) || ! function_exists( 'jet_smart_filters' ) || ! class_exists( '\Elementor\Jet_Smart_Filters_Base_Widget' ) ) {
	return;
}

/**
 * Elementor Timeline Filter Widget
 */
class Jet_Geometry_Timeline_Filter_Widget extends \Elementor\Jet_Smart_Filters_Base_Widget {

	/**
	 * Widget slug.
	 */
	public function get_name() {
		return 'jet-geometry-timeline-filter';
	}

	/**
	 * Widget title.
	 */
	public function get_title() {
		return __( 'Timeline Filter', 'jet-geometry-addon' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon() {
		return 'eicon-time-line';
	}

	/**
	 * Widget categories.
	 */
	public function get_categories() {
		return array( 'jet-geometry-widgets', jet_smart_filters()->widgets->get_category() );
	}

	/**
	 * Register controls.
	 */
	protected function register_controls() {
		parent::register_controls();

		$this->start_controls_section(
			'section_timeline_content',
			array(
				'label' => __( 'Timeline Content', 'jet-geometry-addon' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'title_text',
			array(
				'label'       => __( 'Title', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Timeline', 'jet-geometry-addon' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'title_icon',
			array(
				'label'   => __( 'Icon', 'jet-geometry-addon' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'eicon-calendar',
					'library' => 'elementor',
				),
			)
		);

		$this->add_control(
			'toggle_label',
			array(
				'label'       => __( 'Toggle Label', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Enable Timeline Filter', 'jet-geometry-addon' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'toggle_default',
			array(
				'label'        => __( 'Toggle Default State', 'jet-geometry-addon' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'jet-geometry-addon' ),
				'label_off'    => __( 'Off', 'jet-geometry-addon' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'range_heading',
			array(
				'label'       => __( 'Range Heading', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Select Year Range', 'jet-geometry-addon' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'range_hint',
			array(
				'label'       => __( 'Hint Text', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => __( 'Select a single year to filter by months', 'jet-geometry-addon' ),
				'rows'        => 2,
				'label_block' => true,
			)
		);

		$this->add_control(
			'incident_singular',
			array(
				'label'       => __( 'Incident Label (Singular)', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'incident', 'jet-geometry-addon' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'incident_plural',
			array(
				'label'       => __( 'Incident Label (Plural)', 'jet-geometry-addon' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'incidents', 'jet-geometry-addon' ),
				'label_block' => true,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 */
	protected function render() {
		$settings  = $this->get_settings_for_display();
		$filter_id = ! empty( $settings['filter_id'] ) ? absint( $settings['filter_id'] ) : 0;

		if ( ! $filter_id ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="jet-timeline-filter jet-timeline-filter--notice">';
				esc_html_e( 'Please select a JetSmartFilters range filter.', 'jet-geometry-addon' );
				echo '</div>';
			}
			return;
		}

		$filter_type = get_post_meta( $filter_id, '_filter_type', true );
		if ( ! in_array( $filter_type, array( 'range', 'date-range' ), true ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				printf(
					'<div class="jet-timeline-filter jet-timeline-filter--notice">%s</div>',
					esc_html__( 'Timeline Filter widget works with JetSmartFilters Range filter type. Please select a Range filter.', 'jet-geometry-addon' )
				);
			}
			return;
		}

		$args = $settings;
		$args['filter_id'] = $filter_id;

		$filter_instance = jet_smart_filters()->filter_types->get_filter_instance( $filter_id, null, $args );

		if ( ! $filter_instance ) {
			return;
		}

		$filter_args = $filter_instance->get_args();
		if ( empty( $filter_args ) ) {
			return;
		}

		$min_value = isset( $filter_args['min'] ) ? floatval( $filter_args['min'] ) : 0;
		$max_value = isset( $filter_args['max'] ) ? floatval( $filter_args['max'] ) : 0;

		$current_value = $filter_instance->get_current_filter_value( $filter_args );
		if ( $current_value ) {
			$current_parts = explode( '_', $current_value );
			$current_min   = isset( $current_parts[0] ) ? floatval( $current_parts[0] ) : $min_value;
			$current_max   = isset( $current_parts[1] ) ? floatval( $current_parts[1] ) : $max_value;
		} else {
			$current_min = $min_value;
			$current_max = $max_value;
		}

		$step       = isset( $filter_args['step'] ) ? floatval( $filter_args['step'] ) : 1;
		$is_enabled = ( 'yes' === $settings['toggle_default'] );

		// Buffer JetSmartFilters markup.
		ob_start();
		jet_smart_filters()->filter_types->render_filter_template( $filter_type, $args );
		$filter_markup = ob_get_clean();

		$widget_id = 'jet-timeline-filter-' . $this->get_id();
		$title     = ! empty( $settings['title_text'] ) ? $settings['title_text'] : __( 'Timeline', 'jet-geometry-addon' );
		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>"
			class="jet-timeline-filter <?php echo $is_enabled ? 'is-enabled' : 'is-disabled'; ?>"
			data-filter-id="<?php echo esc_attr( $filter_id ); ?>"
			data-query-id="<?php echo esc_attr( isset( $settings['query_id'] ) ? $settings['query_id'] : 'default' ); ?>"
			data-default-enabled="<?php echo esc_attr( $is_enabled ? 'yes' : 'no' ); ?>"
			data-min="<?php echo esc_attr( $min_value ); ?>"
			data-max="<?php echo esc_attr( $max_value ); ?>"
			data-step="<?php echo esc_attr( $step ); ?>"
			data-from-template="<?php echo esc_attr__( 'From: %s', 'jet-geometry-addon' ); ?>"
			data-to-template="<?php echo esc_attr__( 'To: %s', 'jet-geometry-addon' ); ?>"
			data-incident-singular="<?php echo esc_attr( $settings['incident_singular'] ); ?>"
			data-incident-plural="<?php echo esc_attr( $settings['incident_plural'] ); ?>">

			<div class="jet-timeline-filter__header">
				<div class="jet-timeline-filter__title">
					<?php
					if ( ! empty( $settings['title_icon']['value'] ) ) {
						echo '<span class="jet-timeline-filter__title-icon" aria-hidden="true">';
						\Elementor\Icons_Manager::render_icon(
							$settings['title_icon'],
							array( 'aria-hidden' => 'true' )
						);
						echo '</span>';
					}
					?>
					<span class="jet-timeline-filter__title-text"><?php echo esc_html( $title ); ?></span>
				</div>

				<div class="jet-timeline-filter__toggle-wrapper">
					<label class="jet-timeline-filter__toggle-label" for="<?php echo esc_attr( $widget_id . '-toggle' ); ?>">
						<?php echo esc_html( $settings['toggle_label'] ); ?>
					</label>
					<button
						class="jet-timeline-filter__toggle"
						type="button"
						role="switch"
						id="<?php echo esc_attr( $widget_id . '-toggle' ); ?>"
						aria-checked="<?php echo esc_attr( $is_enabled ? 'true' : 'false' ); ?>"
						data-state="<?php echo esc_attr( $is_enabled ? 'checked' : 'unchecked' ); ?>">
						<span class="jet-timeline-filter__toggle-thumb"></span>
					</button>
				</div>
			</div>

			<div class="jet-timeline-filter__body">
				<div class="jet-timeline-filter__range-heading">
					<span class="jet-timeline-filter__range-label"><?php echo esc_html( $settings['range_heading'] ); ?></span>
					<div class="jet-timeline-filter__range-summary">
						<span data-range-summary><?php echo esc_html( $current_min . ' - ' . $current_max ); ?></span>
						<span class="jet-timeline-filter__incidents">
							<span data-incidents-count>0</span>
							<span data-incidents-label><?php echo esc_html( $settings['incident_plural'] ); ?></span>
						</span>
					</div>
				</div>

				<div class="jet-timeline-filter__slider">
					<button class="jet-timeline-filter__nav jet-timeline-filter__nav--prev" type="button" aria-hidden="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Previous period', 'jet-geometry-addon' ); ?></span>
						<?php
						\Elementor\Icons_Manager::render_icon(
							array(
								'value'   => 'fas fa-chevron-left',
								'library' => 'fa-solid',
							),
							array(
								'aria-hidden' => 'true',
								'class'       => 'jet-timeline-filter__nav-icon',
							)
						);
						?>
					</button>

					<div class="jet-timeline-filter__slider-inner">
						<?php echo $filter_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div class="jet-timeline-filter__ticks" data-range-ticks></div>
					</div>

					<button class="jet-timeline-filter__nav jet-timeline-filter__nav--next" type="button" aria-hidden="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Next period', 'jet-geometry-addon' ); ?></span>
						<?php
						\Elementor\Icons_Manager::render_icon(
							array(
								'value'   => 'fas fa-chevron-right',
								'library' => 'fa-solid',
							),
							array(
								'aria-hidden' => 'true',
								'class'       => 'jet-timeline-filter__nav-icon',
							)
						);
						?>
					</button>
				</div>

				<div class="jet-timeline-filter__range-footer">
					<span class="jet-timeline-filter__from" data-range-from><?php printf( esc_html__( 'From: %s', 'jet-geometry-addon' ), esc_html( $current_min ) ); ?></span>
					<button class="jet-timeline-filter__reset" type="button"><?php esc_html_e( 'Reset to Full Range', 'jet-geometry-addon' ); ?></button>
					<span class="jet-timeline-filter__to" data-range-to><?php printf( esc_html__( 'To: %s', 'jet-geometry-addon' ), esc_html( $current_max ) ); ?></span>
				</div>

				<?php if ( ! empty( $settings['range_hint'] ) ) : ?>
					<div class="jet-timeline-filter__hint">
						<?php echo esc_html( $settings['range_hint'] ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}


