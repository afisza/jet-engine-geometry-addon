<?php
/**
 * Country taxonomy geometry editor
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Class Jet_Geometry_Admin_Country_Geometry
 *
 * Provides geometry editor for `countries` taxonomy terms.
 */
class Jet_Geometry_Admin_Country_Geometry {

	/**
	 * Field prefix used for hidden inputs.
	 */
	const FIELD_PREFIX = 'jet_country_geometry';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'countries_add_form_fields', array( $this, 'render_add_field' ) );
		add_action( 'countries_edit_form_fields', array( $this, 'render_edit_field' ), 10, 2 );
		add_action( 'created_countries', array( $this, 'save_term_geometry' ), 10, 2 );
		add_action( 'edited_countries', array( $this, 'save_term_geometry' ), 10, 2 );
	}

	/**
	 * Render field on "Add New Country" screen.
	 */
	public function render_add_field() {
		?>
		<div class="form-field term-geometry-wrap">
			<label for="<?php echo esc_attr( self::FIELD_PREFIX ); ?>"><?php esc_html_e( 'Country Geometry', 'jet-geometry-addon' ); ?></label>
			<?php $this->render_geometry_field(); ?>
		</div>
		<?php
	}

	/**
	 * Render field on "Edit Country" screen.
	 *
	 * @param WP_Term $term Current term.
	 */
	public function render_edit_field( $term ) {
		?>
		<tr class="form-field term-geometry-wrap">
			<th scope="row">
				<label for="<?php echo esc_attr( self::FIELD_PREFIX ); ?>"><?php esc_html_e( 'Country Geometry', 'jet-geometry-addon' ); ?></label>
			</th>
			<td>
				<?php $this->render_geometry_field( $term ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save submitted geometry when term is created/updated.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_term_geometry( $term_id ) {
		if ( empty( $_POST['jet_country_geometry_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['jet_country_geometry_nonce'] ), 'jet_country_geometry_save' ) ) { // phpcs:ignore WordPress.Security
			return;
		}

		$field_prefix   = self::FIELD_PREFIX;
		$geometry_field = $field_prefix . '_geometry_data';

		if ( empty( $_POST[ $geometry_field ] ) ) { // phpcs:ignore WordPress.Security
			$this->delete_geometry_meta( $term_id );
			return;
		}

		$raw_geojson = wp_unslash( $_POST[ $geometry_field ] ); // phpcs:ignore WordPress.Security
		$validated   = Jet_Geometry_Utils::validate_geojson( $raw_geojson );

		if ( is_wp_error( $validated ) ) {
			return;
		}

		$geometry_type = Jet_Geometry_Utils::get_geometry_type( $validated );
		$centroid      = $this->calculate_centroid( $validated );
		$geojson_str   = wp_json_encode( $validated );
		$simplified    = Jet_Geometry_GeoJSON_Simplifier::simplify( $validated, 0.05 );
		$simplified_str = $simplified ? wp_json_encode( $simplified ) : '';

		update_term_meta( $term_id, '_country_geojson', $geojson_str );

		if ( ! empty( $simplified_str ) ) {
			update_term_meta( $term_id, '_country_geojson_simplified', $simplified_str );
		}

		update_term_meta( $term_id, '_country_geometry_type', $geometry_type );

		if ( $centroid ) {
			update_term_meta( $term_id, '_country_lat', $centroid[1] );
			update_term_meta( $term_id, '_country_lng', $centroid[0] );
		}

		Jet_Geometry_Country_Geojson_File::regenerate();
	}

	/**
	 * Render geometry field markup (shared between add/edit screens).
	 *
	 * @param WP_Term|null $term Current term (optional).
	 */
	private function render_geometry_field( $term = null ) {
		$field_prefix = self::FIELD_PREFIX;

		$current_geojson = '';
		$current_type    = 'polygon';
		$current_lat     = '';
		$current_lng     = '';

		if ( $term instanceof WP_Term ) {
			$stored_geojson = get_term_meta( $term->term_id, '_country_geojson', true );
			if ( $stored_geojson ) {
				$current_geojson = $stored_geojson;
				$type_from_data  = Jet_Geometry_Utils::get_geometry_type( $stored_geojson );
				if ( $type_from_data ) {
					$current_type = $type_from_data;
				}
			}

			$meta_type = get_term_meta( $term->term_id, '_country_geometry_type', true );
			if ( $meta_type ) {
				$current_type = $meta_type;
			}

			$current_lat = get_term_meta( $term->term_id, '_country_lat', true );
			$current_lng = get_term_meta( $term->term_id, '_country_lng', true );
		}

		$settings = array(
			'height'         => 420,
			'format'         => 'geojson',
			'field_prefix'   => $field_prefix,
			'geometry_types' => array( 'polygon' ),
			'default_type'   => 'polygon',
			'line_color'     => '#ff5f6d',
			'polygon_color'  => '#ff5f6d',
			'fill_opacity'   => 0.4,
		);

		?>
		<?php wp_nonce_field( 'jet_country_geometry_save', 'jet_country_geometry_nonce' ); ?>

		<div class="cx-ui-container jet-geometry-term-field">
			<input type="hidden"
				name="<?php echo esc_attr( $field_prefix ); ?>"
				class="jet-geometry-field"
				data-geometry-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>"
				value=""
			/>

			<input type="hidden"
				name="<?php echo esc_attr( $field_prefix . '_geometry_type' ); ?>"
				value="<?php echo esc_attr( $current_type ); ?>"
			/>

			<input type="hidden"
				name="<?php echo esc_attr( $field_prefix . '_geometry_data' ); ?>"
				value="<?php echo esc_attr( $current_geojson ); ?>"
			/>

			<input type="hidden"
				name="<?php echo esc_attr( $field_prefix . '_lat' ); ?>"
				value="<?php echo esc_attr( $current_lat ); ?>"
			/>

			<input type="hidden"
				name="<?php echo esc_attr( $field_prefix . '_lng' ); ?>"
				value="<?php echo esc_attr( $current_lng ); ?>"
			/>
		</div>

		<p class="description">
			<?php esc_html_e( 'Draw or import the official polygon for this country. Only polygon geometry is supported.', 'jet-geometry-addon' ); ?>
		</p>

		<?php
		if ( $current_geojson ) {
			echo '<p class="description">';
			esc_html_e( 'Existing geometry detected. You can adjust it on the map or delete and re-import.', 'jet-geometry-addon' );
			echo '</p>';
		}
	}

	/**
	 * Delete geometry related meta.
	 *
	 * @param int $term_id Term ID.
	 */
	private function delete_geometry_meta( $term_id ) {
		delete_term_meta( $term_id, '_country_geojson' );
		delete_term_meta( $term_id, '_country_geojson_simplified' );
		delete_term_meta( $term_id, '_country_geometry_type' );
		delete_term_meta( $term_id, '_country_lat' );
		delete_term_meta( $term_id, '_country_lng' );

		Jet_Geometry_Country_Geojson_File::regenerate();
	}

	/**
	 * Calculate centroid for storing lat/lng.
	 *
	 * @param array $geojson Geometry array.
	 *
	 * @return array|false [lng, lat] or false.
	 */
	private function calculate_centroid( $geojson ) {
		if ( empty( $geojson['type'] ) || empty( $geojson['coordinates'] ) ) {
			return false;
		}

		switch ( $geojson['type'] ) {
			case 'Point':
				return $geojson['coordinates'];

			case 'LineString':
				return Jet_Geometry_Utils::calculate_line_centroid( $geojson['coordinates'] );

			case 'Polygon':
				return Jet_Geometry_Utils::calculate_polygon_centroid( $geojson['coordinates'] );

			case 'MultiPoint':
				return Jet_Geometry_Utils::calculate_line_centroid( $geojson['coordinates'] );

			case 'MultiLineString':
				$all_points = array();
				foreach ( $geojson['coordinates'] as $line ) {
					$all_points = array_merge( $all_points, $line );
				}
				return Jet_Geometry_Utils::calculate_line_centroid( $all_points );

			case 'MultiPolygon':
				$all_points = array();
				foreach ( $geojson['coordinates'] as $polygon ) {
					foreach ( $polygon as $ring ) {
						$all_points = array_merge( $all_points, $ring );
					}
				}
				return Jet_Geometry_Utils::calculate_line_centroid( $all_points );

			default:
				return false;
		}
	}
}


