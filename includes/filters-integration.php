<?php
/**
 * Jet Smart Filters integration
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Filters_Integration
 */
class Jet_Geometry_Filters_Integration {

	/**
	 * Store all markers for chunk loading (before chunking)
	 *
	 * @var array
	 */
	private static $all_markers_for_chunking = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Check if Jet Smart Filters is active
		if ( ! function_exists( 'jet_smart_filters' ) ) {
			return;
		}

		// Ensure filters are applied from URL/permalinks before rendering map
		// Use parse_query hook which runs after parse_request, ensuring JetSmartFilters has parsed URL
		add_action( 'parse_query', array( $this, 'ensure_filters_applied' ), 5 );
		
		// Normalize meta values for map fields (handle array format)
		// WordPress get_post_meta() returns array when meta is stored as array, but JetEngine expects string
		// Use get_post_metadata filter which is called by WordPress before returning meta value
		add_filter( 'get_post_metadata', array( $this, 'normalize_meta_value' ), 10, 4 );
		
		// Force posts_per_page to -1 for maps (optimized - no debug logging)
		add_filter( 'jet-engine/listing/grid/posts-query-args', array( $this, 'optimize_maps_query' ), 999, 3 );
		
		// Filter map markers directly (JetEngine Maps integration)
		add_filter( 'jet-engine/maps-listing/map-markers', array( $this, 'filter_map_markers' ), 999, 3 );
		
		// Add filter to inject data-all-markers attribute for chunk loading
		add_filter( 'jet-engine/maps-listing/render-data', array( $this, 'add_chunk_loading_data' ), 10, 2 );
		
		// Inject chunk loading script in footer
		add_action( 'wp_footer', array( $this, 'inject_chunk_loading_script' ), 5 );

		// Add frontend script for filter handling
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_filters_script' ) );
		
		// Add AJAX handler for getting term post count
		add_action( 'wp_ajax_jet_geometry_get_term_post_count', array( $this, 'ajax_get_term_post_count' ) );
		add_action( 'wp_ajax_nopriv_jet_geometry_get_term_post_count', array( $this, 'ajax_get_term_post_count' ) );
	}
	
	/**
	 * Ensure filters are applied from URL/permalinks before rendering
	 * This ensures that when page loads with URL parameters like /incidents/countries:albania/,
	 * JetSmartFilters parses them and sets up query args before map markers are rendered
	 */
	public function ensure_filters_applied( $wp_query ) {
		// Check if we have JSF query var in URL
		// Note: URL Aliases (e.g., /incidents/ -> /jsf/jet-engine:incidents/) are processed
		// by JetSmartFilters BEFORE WordPress parses the request, so $wp_query->query_vars['jsf']
		// should contain the original format even when aliases are used
		if ( ! empty( $wp_query->query_vars['jsf'] ) && ! jet_smart_filters()->query->is_ajax_filter() ) {
			// Force JetSmartFilters to parse and apply filters from URL if not already done
			// This ensures get_query_args() returns the correct filtered query
			if ( empty( jet_smart_filters()->query->get_query_args() ) ) {
				// Trigger JetSmartFilters to parse URL parameters
				if ( method_exists( jet_smart_filters()->render, 'apply_filters_from_permalink' ) ) {
					jet_smart_filters()->render->apply_filters_from_permalink( $wp_query );
				}
			}
		}
	}

	/**
	 * Normalize meta value for map fields
	 * 
	 * WordPress get_post_meta() returns array when meta is stored as array (e.g., array(0 => 'value')),
	 * but JetEngine expects string. This filter normalizes array values to strings.
	 * 
	 * This hook intercepts get_post_metadata calls and normalizes array values to strings
	 * for fields commonly used by JetEngine Maps (address, lat, lng, geometry_data, etc.)
	 *
	 * @param mixed  $value     The value to return (or null to use default WordPress behavior)
	 * @param int    $object_id Object ID
	 * @param string $meta_key  Meta key
	 * @param bool   $single    Whether to return a single value
	 * @return mixed Normalized value (string if was array, null to use default behavior otherwise)
	 */
	public function normalize_meta_value( $value, $object_id, $meta_key, $single ) {
		// Only normalize for single meta requests (when $single = true)
		// and only for fields that might be used by maps (address, lat, lng, geometry fields)
		if ( ! $single ) {
			return null; // Use default WordPress behavior for multiple values
		}
		
		// Check if this is a map-related field
		$map_fields = array( 'address', 'lat', 'lng', 'geometry_data', 'geometry_type', 'incident_geometry', 'location_csv', '_incident_location' );
		$is_map_field = false;
		foreach ( $map_fields as $field ) {
			if ( false !== strpos( $meta_key, $field ) ) {
				$is_map_field = true;
				break;
			}
		}
		
		// Also check for geometry fields with hash prefix (e.g., 4e3d8c7b47e499ebe6983d9555fa1bb8_geometry_data)
		if ( ! $is_map_field && preg_match( '/^[a-f0-9]{32}_(geometry_data|geometry_type|lat|lng)$/i', $meta_key ) ) {
			$is_map_field = true;
		}
		
		if ( ! $is_map_field ) {
			return null; // Use default WordPress behavior for non-map fields
		}
		
		// IMPORTANT: Use direct database query to avoid infinite loop
		// get_post_meta() would trigger this filter again, causing recursion
		global $wpdb;
		$meta_value = $wpdb->get_col( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
			$object_id,
			$meta_key
		) );
		
		// If meta doesn't exist or is empty, return null to use default behavior
		if ( empty( $meta_value ) || ! is_array( $meta_value ) ) {
			return null;
		}
		
		// Unserialize values
		$meta_value = array_map( 'maybe_unserialize', $meta_value );
		
		// If meta is stored as array with single element, return that element as string
		if ( count( $meta_value ) === 1 && isset( $meta_value[0] ) ) {
			$single_value = $meta_value[0];
			
			// If the single value is itself an array (e.g., array(0 => 'value')), extract first element
			if ( is_array( $single_value ) && ! empty( $single_value ) && isset( $single_value[0] ) ) {
				return $single_value[0];
			}
			
			// Return the single value
			return $single_value;
		}
		
		// If multiple values exist, return first one (for backward compatibility)
		if ( ! empty( $meta_value ) && isset( $meta_value[0] ) ) {
			$first_value = $meta_value[0];
			
			// If the first value is itself an array, extract first element
			if ( is_array( $first_value ) && ! empty( $first_value ) && isset( $first_value[0] ) ) {
				return $first_value[0];
			}
			
			return $first_value;
		}
		
		// Return null to use default WordPress behavior
		return null;
	}

	/**
	 * Optimize maps query - force posts_per_page to -1 for maps
	 * 
	 * @param array $args Query arguments
	 * @param object $widget Widget instance
	 * @param array $settings Widget settings
	 * @return array Query arguments
	 */
	public function optimize_maps_query( $args, $widget, $settings ) {
		// Only optimize for maps listing widget
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $args;
		}
		
		if ( 'jet-engine-maps-listing' !== $widget->get_name() ) {
			return $args;
		}
		
		// IMPORTANT: For maps, we should always show ALL posts, not just a limited number
		// If posts_per_page is set to a specific number (not -1), force it to -1 for maps
		if ( isset( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 && $args['posts_per_page'] !== -1 ) {
			$args['posts_per_page'] = -1;
		}
		
		return $args;
	}
	
	/**
	 * Filter map markers based on active filters
	 * 
	 * This is called by JetEngine when rendering map markers,
	 * and we ensure filtered posts are properly applied
	 *
	 * @param array $markers Map markers
	 * @return array Filtered markers
	 */
	public function filter_map_markers( $markers ) {
		// Check if chunk loading is enabled FIRST
		$chunk_loading_enabled = get_option( 'jet_geometry_chunk_loading_enabled', true );
		$chunk_size = get_option( 'jet_geometry_chunk_size', 20 );
		
		// Check if JSON cache mode is enabled
		$cache_mode = get_option( 'jet_geometry_cache_mode', 'standard' );
		if ( $cache_mode === 'json' && class_exists( 'Jet_Geometry_Markers_Cache' ) ) {
			$cache_manager = new Jet_Geometry_Markers_Cache();
			
			// Get query args from JetSmartFilters
			if ( function_exists( 'jet_smart_filters' ) ) {
				$query_args = jet_smart_filters()->query->get_query_args();
				
				// Convert to cache filter format
				$filters = array();
				if ( ! empty( $query_args['tax_query'] ) ) {
					$filters['tax_query'] = $this->normalize_tax_query( $query_args['tax_query'] );
				}
				if ( ! empty( $query_args['date_query'] ) ) {
					$filters['date_query'] = $query_args['date_query'];
				}
				
				$cached_markers = $cache_manager->get_filtered_markers( $filters );
				if ( $cached_markers !== null ) {
					// Apply chunk loading to cached markers if enabled
					if ( $chunk_loading_enabled && is_array( $cached_markers ) && count( $cached_markers ) > $chunk_size ) {
						// Store all markers for JavaScript chunk loading
						self::$all_markers_for_chunking = $cached_markers;
						// Return only first chunk
						return array_slice( $cached_markers, 0, $chunk_size );
					}
					// Cache hit - return cached markers
					return $cached_markers;
				}
				// Cache miss - fall through to standard mode
			}
		}
		
		// Standard mode - original logic
		// Check if JetSmartFilters is active
		if ( ! function_exists( 'jet_smart_filters' ) ) {
			return $markers;
		}
		
		// Get current query args from JetSmartFilters (works for both AJAX and URL-based filters)
		$query_args = jet_smart_filters()->query->get_query_args();
		
		$is_ajax = jet_smart_filters()->query->is_ajax_filter();
		
		// JetSmartFilters may return tax_query in a different format for URL-based filters
		// Check if tax_query exists but is in JetSmartFilters format (array with taxonomy as key)
		if ( ! empty( $query_args['tax_query'] ) && is_array( $query_args['tax_query'] ) ) {
			// Check if it's in JetSmartFilters format: ['countries' => ['taxonomy' => 'countries', 'field' => 'term_id', 'terms' => 12]]
			$first_key = key( $query_args['tax_query'] );
			if ( ! is_numeric( $first_key ) && isset( $query_args['tax_query'][ $first_key ]['taxonomy'] ) ) {
				// Convert JetSmartFilters format to WordPress standard format
				$standard_tax_query = array();
				foreach ( $query_args['tax_query'] as $taxonomy => $tax_data ) {
					if ( isset( $tax_data['taxonomy'] ) ) {
						// JetSmartFilters already provides terms as IDs in tax_data['terms']
						// Use them directly instead of parsing from $_REQUEST
						$terms = array();
						if ( isset( $tax_data['terms'] ) ) {
							// Terms can be a single ID or array of IDs
							if ( is_array( $tax_data['terms'] ) ) {
								$terms = $tax_data['terms'];
							} else {
								$terms = array( $tax_data['terms'] );
							}
						} else {
							// Fallback: try to get from $_REQUEST if terms not in tax_data
							if ( ! empty( $_REQUEST['tax'] ) ) {
								$tax_value = $_REQUEST['tax'];
								if ( strpos( $tax_value, ':' ) !== false ) {
									list( $req_taxonomy, $req_terms ) = explode( ':', $tax_value, 2 );
									if ( $req_taxonomy === $taxonomy ) {
										$term_slugs = explode( ',', $req_terms );
										foreach ( $term_slugs as $term_slug ) {
											$term = get_term_by( 'slug', urldecode( $term_slug ), $taxonomy );
											if ( $term && ! is_wp_error( $term ) ) {
												$terms[] = $term->term_id;
											}
										}
									}
								}
							}
						}
						
						if ( ! empty( $terms ) ) {
							$standard_tax_query[] = array(
								'taxonomy' => $taxonomy,
								'field'    => isset( $tax_data['field'] ) ? $tax_data['field'] : 'term_id',
								'terms'    => $terms,
							);
						}
					}
				}
				
				if ( ! empty( $standard_tax_query ) ) {
					$query_args['tax_query'] = $standard_tax_query;
				}
			}
		}
		
		// Also check $_REQUEST for filters (both AJAX and URL-based) if get_query_args() is empty or incomplete
		// For AJAX: check $_REQUEST['query']['_tax_query_countries'] or $_REQUEST['query']['tax_query']
		// For URL: check $_REQUEST['tax'] (format: "countries:albania")
		if ( empty( $query_args['tax_query'] ) ) {
			$tax_query_found = false;
			
			if ( $is_ajax && ! empty( $_REQUEST['query'] ) ) {
				// For AJAX requests, check $_REQUEST['query'] for tax_query
				// Check for _tax_query_{taxonomy} format (e.g., _tax_query_countries)
				foreach ( $_REQUEST['query'] as $key => $value ) {
					if ( strpos( $key, '_tax_query_' ) === 0 ) {
						$taxonomy = str_replace( '_tax_query_', '', $key );
						// Value can be term ID or array of term IDs
						$term_ids = is_array( $value ) ? $value : array( $value );
						
						$query_args['tax_query'] = array(
							array(
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => array_filter( array_map( 'intval', $term_ids ) ),
							)
						);
						$tax_query_found = true;
						break;
					}
				}
			}
			
			// Fallback: check $_REQUEST['tax'] for URL-based filters
			if ( ! $tax_query_found && ! empty( $_REQUEST['tax'] ) ) {
				// Parse tax query from URL format: "countries:albania" or "countries:albania,denmark"
				$tax_value = $_REQUEST['tax'];
				if ( strpos( $tax_value, ':' ) !== false ) {
					list( $taxonomy, $terms ) = explode( ':', $tax_value, 2 );
					$term_slugs = explode( ',', $terms );
					
					// Convert term slugs to IDs
					$term_ids = array();
					foreach ( $term_slugs as $term_slug ) {
						$term = get_term_by( 'slug', urldecode( $term_slug ), $taxonomy );
						if ( $term && ! is_wp_error( $term ) ) {
							$term_ids[] = $term->term_id;
						}
					}
					
					if ( ! empty( $term_ids ) ) {
						$query_args['tax_query'] = array(
							array(
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => $term_ids,
							)
						);
					}
				}
			}
		}
		
		// Check if there are any filters active (tax_query, meta_query, date_query, etc.)
		$has_filters = ! empty( $query_args['tax_query'] ) 
			|| ! empty( $query_args['meta_query'] ) 
			|| ! empty( $query_args['date_query'] )
			|| ! empty( $query_args['s'] ); // search query
		
		// If no filters active, apply chunk loading if enabled
		if ( ! $has_filters ) {
			if ( $chunk_loading_enabled && is_array( $markers ) && count( $markers ) > $chunk_size ) {
				// Store all markers for JavaScript chunk loading
				self::$all_markers_for_chunking = $markers;
				// Return only first chunk
				return array_slice( $markers, 0, $chunk_size );
			}
			return $markers;
		}
		
		// Get the filtered post IDs by running a WP_Query with the filter args
		// This ensures markers are filtered correctly for both AJAX and URL-based requests
		$filtered_query = new \WP_Query( array_merge( 
			array(
				'post_type' => 'incidents',
				'posts_per_page' => -1,
				'fields' => 'ids',
			),
			$query_args
		) );
		
		$filtered_post_ids = $filtered_query->posts;
		
		// Filter markers to only include filtered posts
		// Convert filtered_post_ids to integers for proper comparison
		$filtered_post_ids = array_map( 'intval', $filtered_post_ids );
		
		$filtered_markers = array();
		foreach ( $markers as $marker ) {
			// Ensure marker ID is an integer for comparison
			$marker_id = isset( $marker['id'] ) ? intval( $marker['id'] ) : 0;
			if ( $marker_id > 0 && in_array( $marker_id, $filtered_post_ids, true ) ) {
				$filtered_markers[] = $marker;
			}
		}
		
		// Apply chunk loading to filtered markers if enabled
		if ( $chunk_loading_enabled && is_array( $filtered_markers ) && count( $filtered_markers ) > $chunk_size ) {
			// Store all filtered markers for JavaScript chunk loading
			self::$all_markers_for_chunking = $filtered_markers;
			// Return only first chunk
			return array_slice( $filtered_markers, 0, $chunk_size );
		}
		
		return $filtered_markers;
	}

	/**
	 * Normalize tax_query for cache compatibility
	 *
	 * @param array $tax_query Tax query array.
	 * @return array
	 */
	private function normalize_tax_query( $tax_query ) {
		$normalized = array();
		
		// Handle JetSmartFilters format
		$first_key = key( $tax_query );
		if ( ! is_numeric( $first_key ) && isset( $tax_query[ $first_key ]['taxonomy'] ) ) {
			// Convert JetSmartFilters format to WordPress standard format
			foreach ( $tax_query as $taxonomy => $tax_data ) {
				if ( isset( $tax_data['taxonomy'] ) ) {
					$terms = array();
					if ( isset( $tax_data['terms'] ) ) {
						$terms = is_array( $tax_data['terms'] ) ? $tax_data['terms'] : array( $tax_data['terms'] );
					}
					
					if ( ! empty( $terms ) ) {
						$normalized[] = array(
							'taxonomy' => $taxonomy,
							'field'    => isset( $tax_data['field'] ) ? $tax_data['field'] : 'term_id',
							'terms'    => $terms,
						);
					}
				}
			}
		} else {
			// Already in WordPress standard format
			$normalized = $tax_query;
		}
		
		return $normalized;
	}

	/**
	 * Add chunk loading data to map container attributes
	 * Injects data-all-markers via JavaScript before map initialization
	 *
	 * @param array $data Map render data.
	 * @param array $settings Map settings.
	 * @return array
	 */
	public function add_chunk_loading_data( $data, $settings ) {
		// This filter might not exist, but we'll use wp_footer hook instead
		return $data;
	}
	
	/**
	 * Inject chunk loading data via JavaScript
	 * Called on wp_footer to add data-all-markers attribute
	 */
	public function inject_chunk_loading_script() {
		$chunk_loading_enabled = get_option( 'jet_geometry_chunk_loading_enabled', true );
		if ( ! $chunk_loading_enabled ) {
			return;
		}
		
		if ( empty( self::$all_markers_for_chunking ) ) {
			// Debug: Log that no markers were stored for chunking
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[JetGeometry] inject_chunk_loading_script: No markers stored for chunking' );
			}
			return;
		}
		
		$all_markers = self::$all_markers_for_chunking;
		$markers_count = count( $all_markers );
		
		// Debug: Log markers count
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[JetGeometry] inject_chunk_loading_script: Injecting ' . $markers_count . ' markers' );
		}
		
		// Clear stored markers
		self::$all_markers_for_chunking = array();
		
		?>
		<script type="text/javascript">
		(function() {
			var allMarkers = <?php echo wp_json_encode( $all_markers ); ?>;
			
			console.log('[JetGeometry] inject_chunk_loading_script: All markers prepared:', {
				count: allMarkers.length,
				firstMarker: allMarkers.length > 0 ? allMarkers[0] : null
			});
			
			function injectChunkData() {
				var containers = document.querySelectorAll('.jet-map-listing');
				console.log('[JetGeometry] injectChunkData: Found', containers.length, 'map containers');
				
				containers.forEach(function(container, index) {
					if (!container.hasAttribute('data-all-markers')) {
						container.setAttribute('data-all-markers', JSON.stringify(allMarkers));
						console.log('[JetGeometry] injectChunkData: Added data-all-markers to container', index, {
							containerId: container.id || 'no-id',
							markersCount: allMarkers.length
						});
					} else {
						console.log('[JetGeometry] injectChunkData: Container', index, 'already has data-all-markers');
					}
				});
			}
			
			// Inject immediately if DOM is ready, otherwise wait
			if (document.readyState === 'loading') {
				console.log('[JetGeometry] inject_chunk_loading_script: DOM not ready, waiting for DOMContentLoaded');
				document.addEventListener('DOMContentLoaded', injectChunkData);
			} else {
				console.log('[JetGeometry] inject_chunk_loading_script: DOM ready, injecting immediately');
				injectChunkData();
			}
		})();
		</script>
		<?php
	}

	/**
	 * Check if we are in Elementor editor mode
	 *
	 * @return bool True if in Elementor editor, false otherwise.
	 */
	private function is_elementor_editor() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		
		// Check if in Elementor editor via GET parameter
		if ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) {
			return true;
		}
		
		// Check Elementor editor mode
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return true;
		}
		
		return false;
	}

	/**
	 * Enqueue filters script
	 */
	public function enqueue_filters_script() {
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		// Enqueue chunk loading script if enabled
		$chunk_loading_enabled = get_option( 'jet_geometry_chunk_loading_enabled', true );
		if ( $chunk_loading_enabled ) {
			wp_enqueue_script(
				'jet-geometry-chunk-loading',
				jet_geometry_addon()->plugin_url( 'assets/js/chunk-loading.js' ),
				array( 'jquery' ),
				JET_GEOMETRY_ADDON_VERSION . '-' . time(),
				true
			);
			
			// Localize settings
			wp_localize_script(
				'jet-geometry-chunk-loading',
				'jetGeometryChunkSettings',
				array(
					'enabled'  => $chunk_loading_enabled,
					'chunkSize' => get_option( 'jet_geometry_chunk_size', 20 ),
					'chunkDelay' => get_option( 'jet_geometry_chunk_delay', 50 ),
				)
			);
		}
		
		// Original filters script
		if ( ! function_exists( 'jet_smart_filters' ) ) {
			return;
		}

		wp_enqueue_script(
			'jet-geometry-filters',
			jet_geometry_addon()->plugin_url( 'assets/js/filters-integration.js' ),
			array( 'jquery', 'jet-geometry-renderer', 'jet-geometry-widgets' ),
			JET_GEOMETRY_ADDON_VERSION . '-' . time(), // Cache busting to force refresh
			true
		);
		
		// Localize script with AJAX URL
		wp_localize_script( 'jet-geometry-filters', 'jetGeometryFilters', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
	}

	/**
	 * AJAX handler to get term post count
	 */
	public function ajax_get_term_post_count() {
		// Verify nonce if available
		if ( isset( $_POST['nonce'] ) && ! empty( $_POST['nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_rest' ) ) {
				wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
				return;
			}
		}
		
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( $_POST['taxonomy'] ) : '';
		$term_id = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
		
		if ( ! $taxonomy || ! $term_id ) {
			wp_send_json_error( array( 'message' => 'Missing taxonomy or term_id' ) );
			return;
		}
		
		// Get term object
		$term = get_term( $term_id, $taxonomy );
		
		if ( is_wp_error( $term ) || ! $term ) {
			wp_send_json_error( array( 'message' => 'Term not found' ) );
			return;
		}
		
		// Get post count for this term
		$count = $term->count;
		
		wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * Register custom filter widgets
	 */
	public function register_filter_widgets() {
		// Register geometry type filter widget
		// This is optional - can be implemented later if needed
	}
}

