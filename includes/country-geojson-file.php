<?php
/**
 * Countries GeoJSON file helper.
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Handles generation and retrieval of consolidated countries GeoJSON file.
 */
class Jet_Geometry_Country_Geojson_File {

	const DIRECTORY = 'jet-geometry';
	const FILENAME  = 'countries.json';

	/**
	 * Regenerate the GeoJSON file with current taxonomy data.
	 *
	 * @return bool True on success.
	 */
	public static function regenerate() {
		$data = self::collect_geojson_data();

		if ( empty( $data['features'] ) ) {
			return false;
		}

		$path = self::get_file_path();
		$dir  = dirname( $path );

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$json = wp_json_encode( $data );

		if ( false === $json ) {
			return false;
		}

		$result = file_put_contents( $path, $json ); // phpcs:ignore

		if ( false === $result ) {
			return false;
		}

		// Make sure the file is readable via web.
		@chmod( $path, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return true;
	}

	/**
	 * Ensure the file exists. Regenerates if missing.
	 *
	 * @return bool True if file exists or was created.
	 */
	public static function ensure_exists() {
		$path = self::get_file_path();

		if ( file_exists( $path ) && filesize( $path ) > 0 ) {
			return true;
		}

		return self::regenerate();
	}

	/**
	 * Get the full filesystem path to the JSON file.
	 *
	 * @return string
	 */
	public static function get_file_path() {
		$uploads = wp_upload_dir();

		$dir = trailingslashit( $uploads['basedir'] ) . self::DIRECTORY;

		return trailingslashit( $dir ) . self::FILENAME;
	}

	/**
	 * Get the public URL to the JSON file.
	 *
	 * @return string
	 */
	public static function get_file_url() {
		$uploads = wp_upload_dir();

		$dir_url = trailingslashit( $uploads['baseurl'] ) . self::DIRECTORY;

		return trailingslashit( $dir_url ) . self::FILENAME;
	}

	/**
	 * Get last modified timestamp.
	 *
	 * @return int|null Unix timestamp or null.
	 */
	public static function get_last_modified() {
		$path = self::get_file_path();

		if ( file_exists( $path ) ) {
			return filemtime( $path ); // phpcs:ignore
		}

		return null;
	}

	/**
	 * Collect GeoJSON data for all countries.
	 *
	 * @return array FeatureCollection
	 */
	public static function collect_geojson_data() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'countries',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array(
				'type'     => 'FeatureCollection',
				'features' => array(),
			);
		}

		$counts = self::get_incident_counts_for_terms( $terms );

		$features = array();

		foreach ( $terms as $term ) {
			$geojson = get_term_meta( $term->term_id, '_country_geojson_simplified', true );

			if ( empty( $geojson ) ) {
				$geojson = get_term_meta( $term->term_id, '_country_geojson', true );
			}

			if ( empty( $geojson ) ) {
				continue;
			}

			$geometry = json_decode( $geojson, true );

			if ( ! $geometry || ! isset( $geometry['coordinates'] ) ) {
				continue;
			}

			$term_key    = (string) $term->term_id;
			$slug_key    = ! empty( $term->slug ) ? strtolower( $term->slug ) : null;
			$incident_count = 0;

			if ( isset( $counts['byId'][ $term_key ] ) ) {
				$incident_count = intval( $counts['byId'][ $term_key ] );
			} elseif ( $slug_key && isset( $counts['bySlug'][ $slug_key ] ) ) {
				$incident_count = intval( $counts['bySlug'][ $slug_key ] );
			}

			$features[] = array(
				'type'       => 'Feature',
				'properties' => array(
					'term_id'   => $term->term_id,
					'name'      => $term->name,
					'slug'      => $term->slug,
					'iso_code'  => get_term_meta( $term->term_id, '_country_iso_code', true ),
					'incident_count' => $incident_count,
				),
				'geometry'   => $geometry,
			);
		}

		return array(
			'type'       => 'FeatureCollection',
			'generated'  => current_time( 'mysql' ),
			'features'   => $features,
		);
	}

	/**
	 * Get incident counts for provided terms.
	 *
	 * @param WP_Term[] $terms Terms array.
	 * @return array
	 */
	public static function get_incident_counts_for_terms( $terms ) {
		$by_id          = array();
		$by_slug        = array();
		$by_taxonomy_id = array();

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array(
				'byId'          => $by_id,
				'bySlug'        => $by_slug,
				'byTaxonomyId'  => $by_taxonomy_id,
			);
		}

		global $wpdb;

		$term_tax_ids = array();

		foreach ( $terms as $term ) {
			$term_tax_ids[ intval( $term->term_taxonomy_id ) ] = (int) $term->term_id;
		}

		if ( empty( $term_tax_ids ) ) {
			return array(
				'byId'          => $by_id,
				'bySlug'        => $by_slug,
				'byTaxonomyId'  => $by_taxonomy_id,
			);
		}

		$tt_ids_sql = implode( ',', array_map( 'intval', array_keys( $term_tax_ids ) ) );

		if ( empty( $tt_ids_sql ) ) {
			return array(
				'byId'          => $by_id,
				'bySlug'        => $by_slug,
				'byTaxonomyId'  => $by_taxonomy_id,
			);
		}

		$post_type = 'incidents';
		$sql       = "
			SELECT tr.term_taxonomy_id, COUNT( DISTINCT tr.object_id ) AS total
			FROM {$wpdb->term_relationships} AS tr
			INNER JOIN {$wpdb->posts} AS p ON p.ID = tr.object_id
			WHERE tr.term_taxonomy_id IN ( {$tt_ids_sql} )
				AND p.post_type = %s
				AND p.post_status = 'publish'
			GROUP BY tr.term_taxonomy_id
		";

		$prepared = $wpdb->prepare( $sql, $post_type );
		$results  = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$tt_id = isset( $row['term_taxonomy_id'] ) ? intval( $row['term_taxonomy_id'] ) : 0;
				$total = isset( $row['total'] ) ? intval( $row['total'] ) : 0;

				if ( $tt_id ) {
					$by_taxonomy_id[ $tt_id ] = $total;
				}
			}
		}

		foreach ( $terms as $term ) {
			$tt_id = intval( $term->term_taxonomy_id );
			$count = isset( $by_taxonomy_id[ $tt_id ] ) ? intval( $by_taxonomy_id[ $tt_id ] ) : 0;
			$by_id[ (string) $term->term_id ] = $count;

			if ( ! empty( $term->slug ) ) {
				$by_slug[ strtolower( $term->slug ) ] = $count;
			}
		}

		return array(
			'byId'          => $by_id,
			'bySlug'        => $by_slug,
			'byTaxonomyId'  => $by_taxonomy_id,
		);
	}
}


