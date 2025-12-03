<?php
/**
 * Country geometry migrator.
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Ensures imported country data populates new geometry meta fields.
 */
class Jet_Geometry_Country_Geometry_Migrator {

	const MIGRATION_OPTION = 'jet_geometry_country_geometry_migrated_v1';

	/**
	 * Run migration if needed.
	 */
	public static function maybe_run() {
		if ( ! taxonomy_exists( 'countries' ) ) {
			return;
		}

		// Allow developers to force rerun.
		$force = apply_filters( 'jet-geometry-addon/force-country-geometry-migration', false );

		if ( ! $force && get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		self::run();
		update_option( self::MIGRATION_OPTION, current_time( 'mysql' ), false );
	}

	/**
	 * Perform migration.
	 */
	private static function run() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'countries',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			self::migrate_term( $term );
		}
	}

	/**
	 * Migrate single term meta.
	 *
	 * @param WP_Term $term Country term.
	 */
	private static function migrate_term( $term ) {
		$geojson_raw = get_term_meta( $term->term_id, '_country_geojson', true );

		if ( empty( $geojson_raw ) ) {
			return;
		}

		$geojson = json_decode( $geojson_raw, true );

		if ( ! $geojson || empty( $geojson['type'] ) || empty( $geojson['coordinates'] ) ) {
			return;
		}

		// Geometry type.
		$geometry_type = Jet_Geometry_Utils::get_geometry_type( $geojson );
		if ( $geometry_type ) {
			update_term_meta( $term->term_id, '_country_geometry_type', $geometry_type );
		}

		// Centroid lat/lng.
		$centroid = self::calculate_centroid( $geojson );
		if ( $centroid ) {
			update_term_meta( $term->term_id, '_country_lat', $centroid[1] );
			update_term_meta( $term->term_id, '_country_lng', $centroid[0] );
		}

		// Ensure simplified geometry exists.
		$simplified_meta = get_term_meta( $term->term_id, '_country_geojson_simplified', true );
		if ( empty( $simplified_meta ) ) {
			$simplified = Jet_Geometry_GeoJSON_Simplifier::simplify( $geojson, 0.05 );
			if ( $simplified ) {
				update_term_meta( $term->term_id, '_country_geojson_simplified', wp_json_encode( $simplified ) );
			}
		}
	}

	/**
	 * Calculate centroid using utility helpers.
	 *
	 * @param array $geojson Geometry array.
	 *
	 * @return array|false [lng, lat] or false.
	 */
	private static function calculate_centroid( $geojson ) {
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
		}

		return false;
	}
}











