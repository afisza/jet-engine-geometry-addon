<?php
/**
 * Markers Cache Manager
 * 
 * Handles JSON cache generation and retrieval for optimized marker loading
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Markers_Cache
 */
class Jet_Geometry_Markers_Cache {

	/**
	 * Cache directory path
	 *
	 * @var string
	 */
	private $cache_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cache_dir = JET_GEOMETRY_ADDON_PATH . 'cache/';
		
		// Ensure cache directory exists
		if ( ! file_exists( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );
		}
		
		// Auto-invalidate on post changes
		add_action( 'save_post_incidents', array( $this, 'invalidate_cache' ) );
		add_action( 'set_object_terms', array( $this, 'invalidate_cache_on_term_change' ), 10, 4 );
		add_action( 'delete_post', array( $this, 'invalidate_cache' ) );
		add_action( 'edited_term', array( $this, 'invalidate_cache' ), 10, 3 );
		
		// REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Check if cache mode is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return get_option( 'jet_geometry_cache_mode', 'standard' ) === 'json';
	}

	/**
	 * Generate cache files
	 *
	 * @return bool
	 */
	public function generate_cache() {
		$markers = $this->get_all_markers();
		$indexes = $this->build_indexes( $markers );
		
		// Save markers
		$markers_result = $this->save_json_file( 'markers-all.json', array(
			'version'     => JET_GEOMETRY_ADDON_VERSION,
			'last_update' => current_time( 'mysql' ),
			'total_posts' => count( $markers ),
			'markers'     => $markers,
		) );
		
		// Save indexes
		$indexes_result = $this->save_json_file( 'markers-indexes.json', array(
			'version'       => JET_GEOMETRY_ADDON_VERSION,
			'last_update'   => current_time( 'mysql' ),
			'indexes'       => $indexes['indexes'],
			'incident_counts' => $indexes['counts'],
		) );
		
		return $markers_result && $indexes_result;
	}

	/**
	 * Get all markers with all necessary data
	 *
	 * @return array
	 */
	private function get_all_markers() {
		$query = new WP_Query( array(
			'post_type'      => 'incidents',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );
		
		$markers = array();
		
		// Use JetEngine's method to get coordinates (same as Maps Listing)
		$lat_lng = null;
		if ( function_exists( 'jet_engine' ) && jet_engine()->modules->is_module_active( 'maps-listings' ) ) {
			$module = \Jet_Engine\Modules\Maps_Listings\Module::instance();
			if ( $module && isset( $module->lat_lng ) ) {
				$lat_lng = $module->lat_lng;
			}
		}
		
		// Set source for JetEngine
		if ( $lat_lng && function_exists( 'jet_engine' ) ) {
			$lat_lng->set_current_source( 'posts' );
		}
		
		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );
			
			if ( ! $post ) {
				continue;
			}
			
			// Use JetEngine's method to get coordinates (same as Maps Listing widget)
			// This uses filters that normalize hash-prefixed fields
			$latlang = false;
			
			if ( $lat_lng ) {
				// Find hash-prefixed lat/lng fields dynamically
				global $wpdb;
				$lat_keys = $wpdb->get_results( $wpdb->prepare(
					"SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
					$post_id,
					'%_lat'
				) );
				
				$address = false;
				$field_name_used = '';
				
				// Try each hash-prefixed lat field
				foreach ( $lat_keys as $lat_row ) {
					$lat_key = $lat_row->meta_key;
					
					// Extract hash prefix
					if ( preg_match( '/^([a-f0-9]{32})_lat$/', $lat_key, $matches ) ) {
						$hash_prefix = $matches[1];
						$lng_key = $hash_prefix . '_lng';
						
						// Use filter directly to get normalized values (same as JetEngine Maps Listing)
						$lat_val = apply_filters( 'jet-engine/maps-listing/get-address-from-field', false, $post, $lat_key );
						$lng_val = apply_filters( 'jet-engine/maps-listing/get-address-from-field', false, $post, $lng_key );
						
						// If filter didn't return value, try direct get_post_meta (with normalization)
						if ( ! $lat_val ) {
							$lat_meta = get_post_meta( $post_id, $lat_key, true );
							if ( is_array( $lat_meta ) && ! empty( $lat_meta[0] ) ) {
								$lat_meta = $lat_meta[0];
							}
							$lat_val = $lat_meta;
						}
						
						if ( ! $lng_val ) {
							$lng_meta = get_post_meta( $post_id, $lng_key, true );
							if ( is_array( $lng_meta ) && ! empty( $lng_meta[0] ) ) {
								$lng_meta = $lng_meta[0];
							}
							$lng_val = $lng_meta;
						}
						
						if ( $lat_val && $lng_val ) {
							// Check if values are numeric coordinates
							if ( is_numeric( $lat_val ) && is_numeric( $lng_val ) ) {
								$address = array(
									'lat' => trim( $lat_val ),
									'lng' => trim( $lng_val ),
								);
								$field_name_used = $lat_key . '+' . $lng_key;
								break;
							}
						}
					}
				}
				
				// If no coordinates from hash-prefixed fields, try address field
				if ( ! $address ) {
					$address_field = 'address';
					$address = apply_filters( 'jet-engine/maps-listing/get-address-from-field', false, $post, $address_field );
					if ( ! $address ) {
						$address = $lat_lng->get_address_from_field( $post, $address_field );
					}
					$field_name_used = $address_field;
				}
				
				// Get coordinates using JetEngine's method (uses filters, supports hash-prefixed fields)
				if ( $address ) {
					$coordinates = $lat_lng->get( $post, $address, $field_name_used );
					
					if ( $coordinates && isset( $coordinates['lat'] ) && isset( $coordinates['lng'] ) ) {
						// JetEngine expects latLang as object with lat/lng properties, not array
						$latlang = array(
							'lat' => floatval( $coordinates['lat'] ),
							'lng' => floatval( $coordinates['lng'] )
						);
					}
				}
			}
			
			// Skip if no coordinates found
			// latLang should be an object with lat/lng properties (not array)
			if ( ! $latlang || ! is_array( $latlang ) || ! isset( $latlang['lat'] ) || ! isset( $latlang['lng'] ) ) {
				continue;
			}
			
			// Get taxonomies
			$countries = wp_get_post_terms( $post_id, 'countries', array( 'fields' => 'ids' ) );
			$incident_types = wp_get_post_terms( $post_id, 'incident-type', array( 'fields' => 'ids' ) );
			$incident_subtypes = wp_get_post_terms( $post_id, 'incident-subtype', array( 'fields' => 'ids' ) );
			
			// Get geometry data
			$geometry_data = $this->get_geometry_data_for_post( $post_id );
			
			// Get custom marker (if any)
			$custom_marker = false;
			
			$markers[] = array(
				'id'                    => $post_id,
				'latLang'               => $latlang,
				'label'                 => get_the_title( $post_id ),
				'post_date'             => $post->post_date,
				'post_date_timestamp'   => strtotime( $post->post_date ),
				'countries'             => $countries && ! is_wp_error( $countries ) ? array_map( 'intval', $countries ) : array(),
				'country_names'         => wp_get_post_terms( $post_id, 'countries', array( 'fields' => 'names' ) ),
				'incident_types'        => $incident_types && ! is_wp_error( $incident_types ) ? array_map( 'intval', $incident_types ) : array(),
				'incident_type_names'   => wp_get_post_terms( $post_id, 'incident-type', array( 'fields' => 'names' ) ),
				'incident_subtypes'     => $incident_subtypes && ! is_wp_error( $incident_subtypes ) ? array_map( 'intval', $incident_subtypes ) : array(),
				'geometry_data'        => $geometry_data,
				'taxonomies'           => array(
					'countries'      => $this->format_taxonomy_terms( $post_id, 'countries' ),
					'incident-type'  => $this->format_taxonomy_terms( $post_id, 'incident-type' ),
					'incident-subtype' => $this->format_taxonomy_terms( $post_id, 'incident-subtype' ),
				),
				'custom_marker'        => $custom_marker,
				'geo_query_distance'   => -1,
			);
		}
		
		return $markers;
	}

	/**
	 * Build indexes for fast filtering
	 *
	 * @param array $markers Markers array.
	 * @return array
	 */
	private function build_indexes( $markers ) {
		$indexes = array(
			'countries'      => array(),
			'incident_types' => array(),
			'date_ranges'    => array(),
		);
		
		$counts = array(
			'by_country'          => array(),
			'by_incident_type'    => array(),
			'by_country_and_type' => array(),
		);
		
		foreach ( $markers as $marker ) {
			$post_id = $marker['id'];
			
			// Index by country
			foreach ( $marker['countries'] as $country_id ) {
				if ( ! isset( $indexes['countries'][ $country_id ] ) ) {
					$indexes['countries'][ $country_id ] = array();
				}
				$indexes['countries'][ $country_id ][] = $post_id;
				
				// Count by country
				if ( ! isset( $counts['by_country'][ $country_id ] ) ) {
					$counts['by_country'][ $country_id ] = 0;
				}
				$counts['by_country'][ $country_id ]++;
			}
			
			// Index by incident type
			foreach ( $marker['incident_types'] as $type_id ) {
				if ( ! isset( $indexes['incident_types'][ $type_id ] ) ) {
					$indexes['incident_types'][ $type_id ] = array();
				}
				$indexes['incident_types'][ $type_id ][] = $post_id;
				
				// Count by type
				if ( ! isset( $counts['by_incident_type'][ $type_id ] ) ) {
					$counts['by_incident_type'][ $type_id ] = 0;
				}
				$counts['by_incident_type'][ $type_id ]++;
				
				// Count by country + type
				foreach ( $marker['countries'] as $country_id ) {
					$key = $country_id . '_' . $type_id;
					if ( ! isset( $counts['by_country_and_type'][ $key ] ) ) {
						$counts['by_country_and_type'][ $key ] = 0;
					}
					$counts['by_country_and_type'][ $key ]++;
				}
			}
			
			// Index by date (monthly)
			$date = date( 'Y-m', $marker['post_date_timestamp'] );
			if ( ! isset( $indexes['date_ranges'][ $date ] ) ) {
				$indexes['date_ranges'][ $date ] = array();
			}
			$indexes['date_ranges'][ $date ][] = $post_id;
		}
		
		return array(
			'indexes' => $indexes,
			'counts'  => $counts,
		);
	}

	/**
	 * Filter markers using cache
	 *
	 * @param array $filters Filter arguments.
	 * @return array|null Filtered markers or null if cache not available
	 */
	public function get_filtered_markers( $filters = array() ) {
		if ( ! $this->is_enabled() ) {
			return null; // Fallback to standard mode
		}
		
		$cache_file = $this->cache_dir . 'markers-all.json';
		if ( ! file_exists( $cache_file ) ) {
			// Try to generate cache
			$this->generate_cache();
			if ( ! file_exists( $cache_file ) ) {
				return null; // Fallback to standard mode
			}
		}
		
		$data = json_decode( file_get_contents( $cache_file ), true );
		if ( ! $data || ! isset( $data['markers'] ) ) {
			return null; // Fallback to standard mode
		}
		
		$markers = $data['markers'];
		
		// Apply filters
		if ( ! empty( $filters['tax_query'] ) ) {
			$markers = $this->filter_by_taxonomy( $markers, $filters['tax_query'] );
		}
		
		if ( ! empty( $filters['date_query'] ) ) {
			$markers = $this->filter_by_date( $markers, $filters['date_query'] );
		}
		
		return $markers;
	}

	/**
	 * Filter markers by taxonomy
	 *
	 * @param array $markers   Markers array.
	 * @param array $tax_query Taxonomy query.
	 * @return array
	 */
	private function filter_by_taxonomy( $markers, $tax_query ) {
		$filtered = array();
		
		foreach ( $markers as $marker ) {
			$include = true;
			
			foreach ( $tax_query as $tax_filter ) {
				$taxonomy = isset( $tax_filter['taxonomy'] ) ? $tax_filter['taxonomy'] : '';
				$terms = isset( $tax_filter['terms'] ) ? $tax_filter['terms'] : array();
				if ( ! is_array( $terms ) ) {
					$terms = array( $terms );
				}
				$terms = array_map( 'intval', $terms );
				
				$marker_terms = array();
				switch ( $taxonomy ) {
					case 'countries':
						$marker_terms = $marker['countries'];
						break;
					case 'incident-type':
						$marker_terms = $marker['incident_types'];
						break;
					case 'incident-subtype':
						$marker_terms = $marker['incident_subtypes'];
						break;
				}
				
				$intersection = array_intersect( $marker_terms, $terms );
				if ( empty( $intersection ) ) {
					$include = false;
					break;
				}
			}
			
			if ( $include ) {
				$filtered[] = $marker;
			}
		}
		
		return $filtered;
	}

	/**
	 * Filter markers by date range
	 *
	 * @param array $markers    Markers array.
	 * @param array $date_query Date query.
	 * @return array
	 */
	private function filter_by_date( $markers, $date_query ) {
		$filtered = array();
		
		foreach ( $markers as $marker ) {
			$include = true;
			$post_timestamp = $marker['post_date_timestamp'];
			
			foreach ( $date_query as $date_filter ) {
				if ( isset( $date_filter['after'] ) ) {
					$after = is_numeric( $date_filter['after'] )
						? $date_filter['after']
						: strtotime( $date_filter['after'] );
					if ( $post_timestamp < $after ) {
						$include = false;
						break;
					}
				}
				
				if ( isset( $date_filter['before'] ) ) {
					$before = is_numeric( $date_filter['before'] )
						? $date_filter['before']
						: strtotime( $date_filter['before'] );
					if ( $post_timestamp > $before ) {
						$include = false;
						break;
					}
				}
				
				// Handle year, month, day
				if ( isset( $date_filter['year'] ) ) {
					$marker_year = (int) date( 'Y', $post_timestamp );
					if ( $marker_year != (int) $date_filter['year'] ) {
						$include = false;
						break;
					}
				}
				
				if ( isset( $date_filter['month'] ) ) {
					$marker_month = (int) date( 'm', $post_timestamp );
					if ( $marker_month != (int) $date_filter['month'] ) {
						$include = false;
						break;
					}
				}
				
				if ( isset( $date_filter['day'] ) ) {
					$marker_day = (int) date( 'd', $post_timestamp );
					if ( $marker_day != (int) $date_filter['day'] ) {
						$include = false;
						break;
					}
				}
			}
			
			if ( $include ) {
				$filtered[] = $marker;
			}
		}
		
		return $filtered;
	}

	/**
	 * Get incident counts for Show Country Layers
	 *
	 * @return array
	 */
	public function get_incident_counts() {
		$index_file = $this->cache_dir . 'markers-indexes.json';
		if ( ! file_exists( $index_file ) ) {
			$this->generate_cache();
			if ( ! file_exists( $index_file ) ) {
				return array(
					'byId'   => array(),
					'bySlug' => array(),
				);
			}
		}
		
		$data = json_decode( file_get_contents( $index_file ), true );
		if ( ! $data || ! isset( $data['incident_counts'] ) ) {
			return array(
				'byId'   => array(),
				'bySlug' => array(),
			);
		}
		
		$counts = $data['incident_counts']['by_country'];
		
		// Convert to format expected by Show Country Layers
		$result = array(
			'byId'   => array(),
			'bySlug' => array(),
		);
		
		foreach ( $counts as $country_id => $count ) {
			$term = get_term( $country_id, 'countries' );
			if ( $term && ! is_wp_error( $term ) ) {
				$result['byId'][ $country_id ] = $count;
				$result['bySlug'][ $term->slug ] = $count;
			}
		}
		
		return $result;
	}

	/**
	 * Invalidate cache
	 */
	public function invalidate_cache() {
		$files = array( 'markers-all.json', 'markers-indexes.json' );
		foreach ( $files as $file ) {
			$path = $this->cache_dir . $file;
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
	}

	/**
	 * Invalidate cache on term change
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Terms array.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy name.
	 */
	public function invalidate_cache_on_term_change( $object_id, $terms, $tt_ids, $taxonomy ) {
		$post = get_post( $object_id );
		if ( $post && $post->post_type === 'incidents' ) {
			$this->invalidate_cache();
		}
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route(
			'jet-geometry/v1',
			'/markers-cache/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_generate_cache' ),
				'permission_callback' => array( $this, 'admin_permission_callback' ),
			)
		);
		
		register_rest_route(
			'jet-geometry/v1',
			'/markers-cache/get',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_markers' ),
				'permission_callback' => '__return_true',
			)
		);
		
		register_rest_route(
			'jet-geometry/v1',
			'/markers-cache/counts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_counts' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST: Generate cache
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_generate_cache( $request ) {
		$result = $this->generate_cache();
		return new WP_REST_Response(
			array(
				'success' => $result,
				'message' => $result ? __( 'Cache generated successfully', 'jet-geometry-addon' ) : __( 'Failed to generate cache', 'jet-geometry-addon' ),
			),
			200
		);
	}

	/**
	 * REST: Get filtered markers
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_markers( $request ) {
		$filters = array();
		
		// Parse filters from request
		if ( $request->get_param( 'countries' ) ) {
			$filters['tax_query'][] = array(
				'taxonomy' => 'countries',
				'field'    => 'term_id',
				'terms'    => explode( ',', $request->get_param( 'countries' ) ),
			);
		}
		
		if ( $request->get_param( 'incident_types' ) ) {
			$filters['tax_query'][] = array(
				'taxonomy' => 'incident-type',
				'field'    => 'term_id',
				'terms'    => explode( ',', $request->get_param( 'incident_types' ) ),
			);
		}
		
		if ( $request->get_param( 'date_from' ) || $request->get_param( 'date_to' ) ) {
			$filters['date_query'] = array();
			if ( $request->get_param( 'date_from' ) ) {
				$filters['date_query']['after'] = $request->get_param( 'date_from' );
			}
			if ( $request->get_param( 'date_to' ) ) {
				$filters['date_query']['before'] = $request->get_param( 'date_to' );
			}
		}
		
		$markers = $this->get_filtered_markers( $filters );
		
		if ( $markers === null ) {
			return new WP_REST_Response(
				array(
					'error' => __( 'Cache not available', 'jet-geometry-addon' ),
				),
				404
			);
		}
		
		return new WP_REST_Response(
			array(
				'markers' => $markers,
				'count'   => count( $markers ),
			),
			200
		);
	}

	/**
	 * REST: Get incident counts
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_counts( $request ) {
		$counts = $this->get_incident_counts();
		return new WP_REST_Response( $counts, 200 );
	}

	/**
	 * Admin permission callback
	 *
	 * @return bool
	 */
	public function admin_permission_callback() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Format taxonomy terms for marker
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private function format_taxonomy_terms( $post_id, $taxonomy ) {
		$terms = wp_get_post_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		
		$formatted = array();
		foreach ( $terms as $term ) {
			$formatted[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}
		return $formatted;
	}

	/**
	 * Get geometry data for post
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_geometry_data_for_post( $post_id ) {
		$geometry_fields = array();

		// Get all meta fields
		$all_meta = get_post_meta( $post_id );

		if ( empty( $all_meta ) ) {
			return $geometry_fields;
		}

		// Find geometry fields (same logic as geometry-renderer.php)
		foreach ( $all_meta as $meta_key => $meta_value ) {
			if ( false === strpos( $meta_key, '_geometry_type' ) ) {
				continue;
			}

			// Extract field prefix
			$prefix = str_replace( '_geometry_type', '', $meta_key );

			$geometry_type = isset( $meta_value[0] ) ? $meta_value[0] : '';
			if ( is_array( $geometry_type ) && ! empty( $geometry_type[0] ) ) {
				$geometry_type = $geometry_type[0];
			}
			
			$geometry_data_raw = get_post_meta( $post_id, $prefix . '_geometry_data', true );
			if ( is_array( $geometry_data_raw ) && ! empty( $geometry_data_raw[0] ) ) {
				$geometry_data_raw = $geometry_data_raw[0];
			}
			
			$lat = get_post_meta( $post_id, $prefix . '_lat', true );
			if ( is_array( $lat ) && ! empty( $lat[0] ) ) {
				$lat = $lat[0];
			}
			
			$lng = get_post_meta( $post_id, $prefix . '_lng', true );
			if ( is_array( $lng ) && ! empty( $lng[0] ) ) {
				$lng = $lng[0];
			}

			if ( ! empty( $geometry_type ) && ! empty( $geometry_data_raw ) ) {
				$geometry_data = json_decode( $geometry_data_raw, true );
				if ( $geometry_data ) {
					$geometry_fields[] = array(
						'type'        => $geometry_type,
						'data'        => $geometry_data,
						'lat'         => $lat,
						'lng'         => $lng,
						'field_name'  => $prefix,
					);
				}
			}
		}

		return $geometry_fields;
	}

	/**
	 * Save JSON file
	 *
	 * @param string $filename Filename.
	 * @param array  $data      Data to save.
	 * @return bool
	 */
	private function save_json_file( $filename, $data ) {
		if ( ! file_exists( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );
		}
		
		$filepath = $this->cache_dir . $filename;
		$result = file_put_contents( $filepath, wp_json_encode( $data, JSON_PRETTY_PRINT ) );
		
		return $result !== false;
	}
}

