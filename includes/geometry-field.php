<?php
/**
 * Geometry field type for JetEngine
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Field
 */
class Jet_Geometry_Field {

	public $field_type = 'map_geometry';
	public $assets_added = false;
	public $cct_geometry_cols = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register field type
		add_filter( 'jet-engine/meta-fields/config', array( $this, 'register_field_type' ) );

		// Prepare field arguments
		add_filter( 'jet-engine/meta-fields/' . $this->field_type . '/args', array( $this, 'prepare_field_args' ), 10, 3 );
		add_filter( 'jet-engine/meta-fields/repeater/' . $this->field_type . '/args', array( $this, 'prepare_field_args' ), 10, 3 );

		// Add additional fields for storing geometry data
		add_filter( 'jet-engine/meta-boxes/raw-fields', array( $this, 'add_geometry_fields' ), 10, 2 );
		add_filter( 'jet-engine/options-pages/raw-fields', array( $this, 'add_geometry_fields' ), 10, 2 );
		add_filter( 'jet-engine/custom-content-types/factory/raw-fields', array( $this, 'add_geometry_fields' ), 10, 2 );

		// Add Vue.js controls
		add_action( 'jet-engine/meta-boxes/templates/fields/controls', array( $this, 'add_controls' ) );
		add_action( 'jet-engine/meta-boxes/templates/fields/repeater/controls', array( $this, 'add_repeater_controls' ) );

		// Handle CCT data
		add_filter( 'jet-engine/custom-content-types/item-to-update', array( $this, 'ensure_cct_data_on_save' ), 10, 2 );
		add_filter( 'jet-engine/custom-content-types/db/exclude-fields', array( $this, 'exclude_cct_geometry_fields' ) );

		// Initialize storage handler
		new Jet_Geometry_Field_Storage( $this );

		// DON'T initialize admin assets here - will be done in main plugin
	}
	
	/**
	 * Get field instance for admin assets
	 *
	 * @return Jet_Geometry_Field
	 */
	public function get_instance() {
		return $this;
	}

	/**
	 * Register field type in JetEngine
	 *
	 * @param array $config Field config.
	 * @return array
	 */
	public function register_field_type( $config ) {
		$config['field_types'][] = array(
			'value' => $this->field_type,
			'label' => 'Map Geometry',
		);

		// Add default values for new fields
		if ( ! isset( $config['defaults'] ) ) {
			$config['defaults'] = array();
		}

		$config['defaults']['geometry_types'] = array( 'pin', 'line', 'polygon' );
		$config['defaults']['default_geometry_type'] = 'pin';
		$config['defaults']['geometry_value_format'] = 'geojson';
		$config['defaults']['geometry_map_height'] = '400';
		$config['defaults']['geometry_line_color'] = '#ff0000';
		$config['defaults']['geometry_polygon_color'] = '#ff0000';
		$config['defaults']['geometry_fill_opacity'] = '0.3';

		// Add field type to condition operators
		foreach ( $config['condition_operators'] as &$condition_operator ) {
			if ( empty( $condition_operator['value'] ) ) {
				continue;
			}

			if ( in_array( $condition_operator['value'], array( 'equal', 'not_equal' ), true ) && isset( $condition_operator['not_fields'] ) ) {
				$condition_operator['not_fields'][] = $this->field_type;
			}

			if ( in_array( $condition_operator['value'], array( 'contains', '!contains' ), true ) && isset( $condition_operator['fields'] ) ) {
				$condition_operator['fields'][] = $this->field_type;
			}
		}

		unset( $condition_operator );

		return $config;
	}

	/**
	 * Prepare field arguments
	 *
	 * @param array  $args Field args.
	 * @param array  $field Field data.
	 * @param object $instance Instance.
	 * @return array
	 */
	public function prepare_field_args( $args, $field, $instance ) {
		$args['type']         = 'text';
		$args['input_type']   = 'hidden';
		$args['autocomplete'] = 'off';
		$args['class']        = 'jet-geometry-field';

		$geometry_types = ! empty( $field['geometry_types'] ) ? $field['geometry_types'] : array( 'pin', 'line', 'polygon' );
		$default_type   = ! empty( $field['default_geometry_type'] ) ? $field['default_geometry_type'] : 'pin';
		$value_format   = ! empty( $field['geometry_value_format'] ) ? $field['geometry_value_format'] : 'geojson';

		$is_cct_field      = 'Jet_Engine\Modules\Custom_Content_Types\Pages\Edit_Item_Page' === get_class( $instance );
		$is_repeater_field = 'jet-engine/meta-fields/repeater/' . $this->field_type . '/args' === current_filter();

		if ( $is_cct_field || $is_repeater_field ) {
			$field_prefix = $field['name'];
		} else {
			$field_prefix = Jet_Geometry_Utils::get_field_prefix( $field['name'] );
		}

		if ( ! $is_repeater_field ) {
			if ( empty( $args['description'] ) ) {
				$args['description'] = '';
			}
			$args['description'] .= $this->get_field_description( $field_prefix );
		}

		$field_settings = array(
			'height'          => ! empty( $field['geometry_map_height'] ) ? $field['geometry_map_height'] : '400',
			'format'          => $value_format,
			'field_prefix'    => $field_prefix,
			'geometry_types'  => $geometry_types,
			'default_type'    => $default_type,
			'line_color'      => ! empty( $field['geometry_line_color'] ) ? $field['geometry_line_color'] : '#ff0000',
			'polygon_color'   => ! empty( $field['geometry_polygon_color'] ) ? $field['geometry_polygon_color'] : '#ff0000',
			'fill_opacity'    => ! empty( $field['geometry_fill_opacity'] ) ? floatval( $field['geometry_fill_opacity'] ) : 0.3,
		);

		$args['extra_attr'] = array(
			'data-geometry-settings' => htmlentities( wp_json_encode( $field_settings ) ),
		);

		return $args;
	}

	/**
	 * Get field description with meta field names
	 *
	 * @param string $prefix Field prefix.
	 * @return string
	 */
	public function get_field_description( $prefix = '' ) {
		$result  = '<p><b>' . esc_html__( 'Geometry data is stored in the following fields:', 'jet-geometry-addon' ) . '</b></p>';
		$result .= '<ul>';
		$result .= sprintf( '<li>%1$s: <span class="je-field-name">%2$s</span></li>', esc_html__( 'Type', 'jet-geometry-addon' ), $prefix . '_geometry_type' );
		$result .= sprintf( '<li>%1$s: <span class="je-field-name">%2$s</span></li>', esc_html__( 'Data', 'jet-geometry-addon' ), $prefix . '_geometry_data' );
		$result .= sprintf( '<li>%1$s: <span class="je-field-name">%2$s</span></li>', esc_html__( 'Lat', 'jet-geometry-addon' ), $prefix . '_lat' );
		$result .= sprintf( '<li>%1$s: <span class="je-field-name">%2$s</span></li>', esc_html__( 'Lng', 'jet-geometry-addon' ), $prefix . '_lng' );
		$result .= '</ul>';

		return $result;
	}

	/**
	 * Add geometry storage fields
	 *
	 * @param array  $fields Fields array.
	 * @param object $instance Instance.
	 * @return array
	 */
	public function add_geometry_fields( $fields = array(), $instance = null ) {
		if ( empty( $fields ) ) {
			return $fields;
		}

		$_fields = $fields;

		foreach ( $_fields as $index => $field ) {
			if ( empty( $field['object_type'] ) || 'field' !== $field['object_type'] ) {
				continue;
			}

			if ( empty( $field['type'] ) || $this->field_type !== $field['type'] ) {
				continue;
			}

			$field_prefix = Jet_Geometry_Utils::get_field_prefix( $field['name'] );

			// Handle CCT columns
			if ( 'Jet_Engine\Modules\Custom_Content_Types\Factory' === get_class( $instance ) ) {
				$field_prefix = $field['name'];

				$cols = array(
					$field_prefix . '_geometry_type' => 'text',
					$field_prefix . '_geometry_data' => 'longtext',
					$field_prefix . '_lat'           => 'text',
					$field_prefix . '_lng'           => 'text',
				);

				foreach ( $cols as $col_name => $col_type ) {
					$this->cct_geometry_cols[] = $col_name;

					if ( ! $instance->db->column_exists( $col_name ) ) {
						$instance->db->insert_table_columns( array( $col_name => $col_type ) );
					}
				}
			}

			// Add hidden fields for storage
			$fields[] = array(
				'title'       => $field['title'] . ' - Geometry Type',
				'name'        => $field_prefix . '_geometry_type',
				'object_type' => 'field',
				'type'        => 'hidden',
			);

			$fields[] = array(
				'title'       => $field['title'] . ' - Geometry Data',
				'name'        => $field_prefix . '_geometry_data',
				'object_type' => 'field',
				'type'        => 'hidden',
			);

			$fields[] = array(
				'title'       => $field['title'] . ' - Lat',
				'name'        => $field_prefix . '_lat',
				'object_type' => 'field',
				'type'        => 'hidden',
			);

			$fields[] = array(
				'title'       => $field['title'] . ' - Lng',
				'name'        => $field_prefix . '_lng',
				'object_type' => 'field',
				'type'        => 'hidden',
			);
		}

		return $fields;
	}

	/**
	 * Ensure CCT data on save
	 *
	 * @param array $item Item data.
	 * @param array $fields Fields config.
	 * @return array
	 */
	public function ensure_cct_data_on_save( $item, $fields ) {
		foreach ( $fields as $field_id => $field_data ) {
			if ( $this->field_type === $field_data['type'] ) {
				$prefix = $field_id;

				// Ensure geometry_type is set
				if ( ! isset( $item[ $prefix . '_geometry_type' ] ) || empty( $item[ $prefix . '_geometry_type' ] ) ) {
					if ( ! empty( $item[ $prefix . '_geometry_data' ] ) ) {
						$geojson = json_decode( $item[ $prefix . '_geometry_data' ], true );
						if ( $geojson && isset( $geojson['type'] ) ) {
							$item[ $prefix . '_geometry_type' ] = Jet_Geometry_Utils::get_geometry_type( $geojson );
						}
					}
				}
			}
		}

		return $item;
	}

	/**
	 * Exclude geometry fields from CCT
	 *
	 * @param array $exclude Fields to exclude.
	 * @return array
	 */
	public function exclude_cct_geometry_fields( $exclude ) {
		if ( empty( $this->cct_geometry_cols ) ) {
			return $exclude;
		}

		return array_merge( $exclude, array_unique( $this->cct_geometry_cols ) );
	}

	/**
	 * Add Vue.js controls for field settings
	 */
	public function add_controls() {
		?>
		<!-- Geometry Types Selection -->
		<cx-vui-select
			label="<?php esc_html_e( 'Allowed Geometry Types', 'jet-geometry-addon' ); ?>"
			description="<?php esc_html_e( 'Select which geometry types users can create', 'jet-geometry-addon' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:multiple="true"
			:options-list="[
				{
					value: 'pin',
					label: '<?php esc_html_e( 'Pin (Point)', 'jet-geometry-addon' ); ?>'
				},
				{
					value: 'line',
					label: '<?php esc_html_e( 'Line (LineString)', 'jet-geometry-addon' ); ?>'
				},
				{
					value: 'polygon',
					label: '<?php esc_html_e( 'Polygon', 'jet-geometry-addon' ); ?>'
				}
			]"
			:value="field.geometry_types"
			@input="setFieldProp( 'geometry_types', $event )"
			:conditions="[
				{
					'input':   field.object_type,
					'compare': 'equal',
					'value':   'field',
				},
				{
					'input':   field.type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-select>

		<!-- Default Geometry Type -->
		<cx-vui-select
			label="<?php esc_html_e( 'Default Geometry Type', 'jet-geometry-addon' ); ?>"
			description="<?php esc_html_e( 'Initial geometry type when creating new entry', 'jet-geometry-addon' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:options-list="[
				{
					value: 'pin',
					label: '<?php esc_html_e( 'Pin', 'jet-geometry-addon' ); ?>'
				},
				{
					value: 'line',
					label: '<?php esc_html_e( 'Line', 'jet-geometry-addon' ); ?>'
				},
				{
					value: 'polygon',
					label: '<?php esc_html_e( 'Polygon', 'jet-geometry-addon' ); ?>'
				}
			]"
			:value="field.default_geometry_type"
			@input="setFieldProp( 'default_geometry_type', $event )"
			:conditions="[
				{
					'input':   field.object_type,
					'compare': 'equal',
					'value':   'field',
				},
				{
					'input':   field.type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-select>

		<!-- Value Format -->
		<cx-vui-select
			label="<?php esc_html_e( 'Value Format', 'jet-geometry-addon' ); ?>"
			description="<?php esc_html_e( 'Format for storing geometry data', 'jet-geometry-addon' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:options-list="[
				{
					value: 'geojson',
					label: '<?php esc_html_e( 'GeoJSON (recommended)', 'jet-geometry-addon' ); ?>'
				},
				{
					value: 'coordinates_array',
					label: '<?php esc_html_e( 'Coordinates Array', 'jet-geometry-addon' ); ?>'
				}
			]"
			:value="field.geometry_value_format"
			@input="setFieldProp( 'geometry_value_format', $event )"
			:conditions="[
				{
					'input':   field.object_type,
					'compare': 'equal',
					'value':   'field',
				},
				{
					'input':   field.type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-select>

		<!-- Map Height -->
		<cx-vui-input
			label="<?php esc_html_e( 'Map Height (px)', 'jet-geometry-addon' ); ?>"
			description="<?php esc_html_e( 'Height of the map in the editor', 'jet-geometry-addon' ); ?>"
			type="number"
			:min="Number(200)"
			:step="Number(10)"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:value="field.geometry_map_height"
			@input="setFieldProp( 'geometry_map_height', $event )"
			:conditions="[
				{
					'input':   field.object_type,
					'compare': 'equal',
					'value':   'field',
				},
				{
					'input':   field.type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-input>

		<!-- Line Color -->
		<cx-vui-colorpicker
			label="<?php esc_html_e( 'Line Color', 'jet-geometry-addon' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:value="field.geometry_line_color"
			@input="setFieldProp( 'geometry_line_color', $event )"
			:conditions="[
				{
					'input':   field.object_type,
					'compare': 'equal',
					'value':   'field',
				},
				{
					'input':   field.type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-colorpicker>

		<!-- Polygon Color -->
		<cx-vui-colorpicker
			label="<?php esc_html_e( 'Polygon Fill Color', 'jet-geometry-addon' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:value="field.geometry_polygon_color"
			@input="setFieldProp( 'geometry_polygon_color', $event )"
			:conditions="[
				{
					'input':   field.object_type,
					'compare': 'equal',
					'value':   'field',
				},
				{
					'input':   field.type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-colorpicker>

		<!-- Fill Opacity -->
		<cx-vui-input
			label="<?php esc_html_e( 'Fill Opacity', 'jet-geometry-addon' ); ?>"
			description="<?php esc_html_e( 'Polygon fill opacity (0.0 - 1.0)', 'jet-geometry-addon' ); ?>"
			type="number"
			:min="Number(0)"
			:max="Number(1)"
			:step="Number(0.1)"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:value="field.geometry_fill_opacity"
			@input="setFieldProp( 'geometry_fill_opacity', $event )"
			:conditions="[
				{
					'input':   field.object_type,
					'compare': 'equal',
					'value':   'field',
				},
				{
					'input':   field.type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-input>
		<?php
	}

	/**
	 * Add Vue.js controls for repeater fields
	 */
	public function add_repeater_controls() {
		?>
		<cx-vui-select
			label="<?php esc_html_e( 'Allowed Geometry Types', 'jet-geometry-addon' ); ?>"
			description="<?php esc_html_e( 'Select which geometry types users can create', 'jet-geometry-addon' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:multiple="true"
			:options-list="[
				{ value: 'pin', label: '<?php esc_html_e( 'Pin', 'jet-geometry-addon' ); ?>' },
				{ value: 'line', label: '<?php esc_html_e( 'Line', 'jet-geometry-addon' ); ?>' },
				{ value: 'polygon', label: '<?php esc_html_e( 'Polygon', 'jet-geometry-addon' ); ?>' }
			]"
			:value="field['repeater-fields'][ rFieldIndex ].geometry_types"
			@input="setRepeaterFieldProp( rFieldIndex, 'geometry_types', $event )"
			:conditions="[
				{
					'input':   field['repeater-fields'][ rFieldIndex ].type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-select>

		<cx-vui-input
			label="<?php esc_html_e( 'Map Height (px)', 'jet-geometry-addon' ); ?>"
			type="number"
			:min="Number(200)"
			:step="Number(10)"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:value="field['repeater-fields'][ rFieldIndex ].geometry_map_height"
			@input="setRepeaterFieldProp( rFieldIndex, 'geometry_map_height', $event )"
			:conditions="[
				{
					'input':   field['repeater-fields'][ rFieldIndex ].type,
					'compare': 'equal',
					'value':   'map_geometry',
				}
			]"
		></cx-vui-input>
		<?php
	}
}

