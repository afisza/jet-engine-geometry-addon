<?php
/**
 * Incident geometry migrator.
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Backfills JetEngine Map Geometry hidden meta for imported incidents.
 */
class Jet_Geometry_Incident_Geometry_Migrator {

	const MIGRATION_OPTION = 'jet_geometry_incident_geometry_migrated_v1';
	const FIELD_NAME       = 'incident_geometry';

	/**
	 * Run migration once.
	 */
	public static function maybe_run() {
		// Allow developers to override.
		$force = apply_filters( 'jet-geometry-addon/force-incident-geometry-migration', false );

		if ( ! $force && get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		self::run();

		update_option( self::MIGRATION_OPTION, current_time( 'mysql' ), false );
	}

	/**
	 * Perform migration until all posts are synced.
	 */
	private static function run() {
		if ( ! class_exists( 'Jet_Geometry_Utils' ) ) {
			return;
		}

		$processed = 0;

		do {
			$post_ids = self::get_posts_missing_prefix_meta();

			if ( empty( $post_ids ) ) {
				break;
			}

			foreach ( $post_ids as $post_id ) {
				self::migrate_post( $post_id );
				$processed++;
			}

			// Prevent potential infinite loops on very large datasets.
			if ( $processed > 5000 ) {
				break;
			}
		} while ( true );
	}

	/**
	 * Fetch IDs that still miss JetEngine prefix meta.
	 *
	 * @return int[]
	 */
	private static function get_posts_missing_prefix_meta() {
		$prefix = Jet_Geometry_Utils::get_field_prefix( self::FIELD_NAME );

		$query = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => self::FIELD_NAME,
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => $prefix . '_geometry_data',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => $prefix . '_geometry_data',
							'value'   => '',
							'compare' => '=',
						),
					),
				),
			)
		);

		return $query->posts;
	}

	/**
	 * Migrate a single post.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function migrate_post( $post_id ) {
		$geometry_raw = get_post_meta( $post_id, self::FIELD_NAME, true );

		if ( empty( $geometry_raw ) ) {
			return;
		}

		$geometry = is_string( $geometry_raw ) ? json_decode( $geometry_raw, true ) : $geometry_raw;

		if ( ! is_array( $geometry ) ) {
			return;
		}

		$geometry_data = isset( $geometry['geometry'] ) ? $geometry['geometry'] : $geometry;

		if ( empty( $geometry_data['type'] ) ) {
			return;
		}

		$lat = get_post_meta( $post_id, 'incident_lat', true );
		$lng = get_post_meta( $post_id, 'incident_lng', true );

		$lat = ( '' === $lat ) ? null : floatval( $lat );
		$lng = ( '' === $lng ) ? null : floatval( $lng );

		Jet_Geometry_Utils::sync_map_field_meta(
			$post_id,
			self::FIELD_NAME,
			$geometry_data,
			$lat,
			$lng
		);
	}
}



