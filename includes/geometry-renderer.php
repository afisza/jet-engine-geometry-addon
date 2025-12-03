<?php
/**
 * Geometry renderer for frontend maps
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_Renderer
 */
class Jet_Geometry_Renderer {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Intercept get_post_meta calls for geometry fields
		add_filter( 'get_post_metadata', array( $this, 'intercept_geometry_meta' ), 10, 4 );
		
		// Modify address field to return lat/lng directly
		add_filter( 'jet-engine/maps-listing/get-address-from-field', array( $this, 'get_geometry_coordinates' ), 10, 3 );
		
		// Add geometry data to map markers (CRITICAL - must be here!)
		add_filter( 'jet-engine/maps-listing/map-markers', array( $this, 'add_geometry_to_markers' ), 10 );
		
		// Add geometry data to map markers
		add_filter( 'jet-engine/listings/data/prop', array( $this, 'add_geometry_prop' ), 10, 3 );

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Enqueue on maps-listings hook (works better than frontend-scripts)
		add_action( 'jet-engine/maps-listings/assets', array( $this, 'enqueue_geometry_assets' ), 10 );
		
		// Override JetEngine's mapbox-markerclusterer.js with our custom version (red clusters by default)
		add_filter( 'script_loader_src', array( $this, 'override_mapbox_markerclusterer_script' ), 10, 2 );
	}
	
	/**
	 * Intercept get_post_metadata to normalize geometry fields and return coordinates for address field
	 * 
	 * This ensures that geometry fields with hash prefix are properly normalized
	 * from array format to string format for JetEngine Maps.
	 * 
	 * CRITICAL: When JetEngine requests 'address' field and it's empty, we return coordinates
	 * from hash-prefixed fields so JetEngine can use them directly.
	 *
	 * @param mixed  $value     The value to return (or null to use default).
	 * @param int    $object_id Object ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether to return a single value.
	 * @return mixed Normalized value or null to use default behavior
	 */
	public function intercept_geometry_meta( $value, $object_id, $meta_key, $single ) {
		// Only process for single meta requests
		if ( ! $single ) {
			return null;
		}
		
		// Check if this is a geometry field with hash prefix (e.g., 4e3d8c7b47e499ebe6983d9555fa1bb8_lat)
		if ( ! preg_match( '/^([a-f0-9]{32})_(lat|lng|geometry_data|geometry_type)$/i', $meta_key, $matches ) ) {
			return null;
		}
		
		$prefix = $matches[1];
		$field_type = $matches[2];
		
		// Get meta value directly from database to avoid recursion
		global $wpdb;
		$meta_value = $wpdb->get_col( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
			$object_id,
			$meta_key
		) );
		
		if ( empty( $meta_value ) || ! is_array( $meta_value ) ) {
			return null;
		}
		
		// Unserialize and normalize
		$meta_value = array_map( 'maybe_unserialize', $meta_value );
		
		// Get first value
		$first_value = isset( $meta_value[0] ) ? $meta_value[0] : null;
		
		// If value is nested array, extract first element
		if ( is_array( $first_value ) && ! empty( $first_value[0] ) ) {
			$first_value = $first_value[0];
		}
		
		return $first_value;
	}
	
	/**
	 * Get geometry coordinates when address field is requested
	 * 
	 * This hook intercepts address field requests and if the post has geometry data,
	 * returns coordinates directly instead of requiring geocoding.
	 *
	 * @param mixed  $value Original value (address string or false).
	 * @param object $post Post object.
	 * @param string $field_name Field name being requested (e.g., 'address').
	 * @return mixed Coordinates array ['lat' => ..., 'lng' => ...] or original value
	 */
	public function get_geometry_coordinates( $value, $post, $field_name ) {
		// Get post ID
		$post_id = isset( $post->ID ) ? $post->ID : 0;
		if ( ! $post_id ) {
			return $value;
		}
		
		// Check if this is a hash-prefixed lat/lng field (used by lat_lng_address_field)
		// When JetEngine uses lat_lng_address_field like "4e3d8c7b47e499ebe6983d9555fa1bb8_lat+4e3d8c7b47e499ebe6983d9555fa1bb8_lng",
		// it calls get_address_from_field for each field separately
		if ( preg_match( '/^([a-f0-9]{32})_(lat|lng)$/', $field_name, $matches ) ) {
			$prefix = $matches[1];
			$coord_type = $matches[2]; // 'lat' or 'lng'
			
			// Get the value directly from database
			global $wpdb;
			$meta_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				$field_name
			) );
			
			if ( $meta_value !== null ) {
				$meta_value = maybe_unserialize( $meta_value );
				
				// Normalize if it's an array (e.g., array(0 => '42.663857'))
				if ( is_array( $meta_value ) && ! empty( $meta_value[0] ) ) {
					$meta_value = $meta_value[0];
				}
				
				// Return the normalized value (lat or lng as string/number)
				// This allows JetEngine to use these coordinates directly
				return $meta_value;
			}
			
			// If not found, return original value (might be false)
			return $value;
		}
		
		// IMPORTANT: For 'address' field, we should validate if address is "valid" (specific address)
		// or just a generic location (e.g., country name only like 'Kosovo')
		// If address is too generic and we have hash-prefixed coordinates, prefer coordinates
		// JetEngine expects a string address from 'address' field, not coordinates
		// Coordinates should come from 'lat_lng_address_field' when 'add_lat_lng' is enabled
		if ( $field_name === 'address' || strpos( $field_name, 'address' ) !== false ) {
			// Check if address is actually empty
			$is_address_empty = ( empty( $value ) || $value === false || $value === '—' || trim( $value ) === '' );
			
			// If address is not empty, check if it's too generic (e.g., just country name)
			if ( ! $is_address_empty && is_string( $value ) ) {
				$address_trimmed = trim( $value );
				
				// First, check if we have hash-prefixed coordinates (quick check)
				global $wpdb;
				$lat_meta = $wpdb->get_var( $wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s LIMIT 1",
					$post_id,
					'%_lat'
				) );
				
				if ( $lat_meta ) {
					// We have hash-prefixed coordinates - check if address is too generic
					
					// Check if address is too short (less than 5 characters) - likely too generic
					if ( strlen( $address_trimmed ) < 5 ) {
						// Return false/empty to force JetEngine to use lat_lng_address_field
						return false;
					}
					
					// Check if address is just a country name (too generic)
					// Use cached country names to avoid performance issues
					static $country_names_cache = null;
					if ( null === $country_names_cache ) {
						$countries = get_terms( array(
							'taxonomy'   => 'countries',
							'hide_empty' => false,
							'fields'     => 'names',
						) );
						$country_names_cache = ! is_wp_error( $countries ) && ! empty( $countries ) ? $countries : array();
					}
					
					// Check if address matches exactly a country name (case-insensitive)
					foreach ( $country_names_cache as $country_name ) {
						if ( strcasecmp( $address_trimmed, $country_name ) === 0 ) {
							// Address is just a country name - too generic!
							// Return false/empty to force JetEngine to use lat_lng_address_field
							return false;
						}
					}
				}
			}
			
			// Return original value (valid address string or false/empty)
			// This allows JetEngine to use address if valid, or fall back to lat_lng_address_field if empty/generic
			return $value;
		}
		
		// If no geometry found, return original value (address string or false)
		return $value;
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
	 * Register frontend assets
	 */
	public function register_assets() {
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		// Frontend geometry renderer CSS
		wp_register_style(
			'jet-geometry-renderer',
			jet_geometry_addon()->plugin_url( 'assets/css/public-geometry.css' ),
			array(),
			JET_GEOMETRY_ADDON_VERSION
		);

		// Frontend geometry renderer JS - load EARLY, only depend on jQuery
		wp_register_script(
			'jet-geometry-renderer',
			jet_geometry_addon()->plugin_url( 'assets/js/frontend-geometry.js' ),
			array( 'jquery' ), // Don't depend on jet-maps-listings to avoid the error
			JET_GEOMETRY_ADDON_VERSION . '-' . time(), // Cache busting
			false // Load in header, not footer!
		);
		
		// Register popup positioning script
		wp_register_script(
			'jet-geometry-popup-positioning',
			jet_geometry_addon()->plugin_url( 'assets/js/popup-positioning.js' ),
			array( 'jquery' ),
			JET_GEOMETRY_ADDON_VERSION,
			true
		);
		
		// Default settings - these can be overridden by field-specific settings
		$default_settings = array(
			'polygonFillColor' => '#ff0000',
			'fillOpacity'      => '0.3',
			'lineColor'        => '#ff0000',
			'lineWidth'        => '2',
			'showPreloader'    => true,
			'restUrl'          => esc_url_raw( rest_url( 'jet-geometry/v1/' ) ),
			'restNonce'        => wp_create_nonce( 'wp_rest' ),
			'debugLogging'     => (bool) apply_filters( 'jet-geometry-addon/frontend-debug-logging', false ),
			'taxonomyKey'      => apply_filters( 'jet-geometry-addon/country-taxonomy', 'countries' ),
		);
		
		wp_localize_script( 'jet-geometry-renderer', 'jetGeometrySettings', $default_settings );
	}

	/**
	 * Enqueue geometry assets for maps
	 * This hook is called directly by jet-engine/maps-listings/assets
	 */
	public function enqueue_geometry_assets() {
		// Skip if in Elementor editor (to prevent conflicts)
		if ( $this->is_elementor_editor() ) {
			return;
		}
		
		// Enqueue with very high priority
		wp_enqueue_style( 'jet-geometry-renderer' );
		wp_enqueue_script( 'jet-geometry-renderer' );
		// Disabled popup positioning - using JetEngine default behavior
		// wp_enqueue_script( 'jet-geometry-popup-positioning' );
		
		// Add inline CSS for preloader
		$preloader_css = "
		.jet-map-listing {
			position: relative;
		}
		.jet-map-listing.loading::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: linear-gradient(135deg, rgba(14, 42, 46, 0.55), rgba(21, 83, 88, 0.35));
			backdrop-filter: blur(14px);
			-webkit-backdrop-filter: blur(14px);
			border-radius: inherit;
			z-index: 1000;
		}
		.jet-map-listing.loading::after {
			content: '';
			position: absolute;
			top: 50%;
			left: 50%;
			width: 60px;
			height: 60px;
			margin: -30px 0 0 -30px;
			border-radius: 50%;
			border: 4px solid rgba(255, 255, 255, 0.25);
			border-top-color: var(--jet-geometry-spinner-color, #51bbd6);
			backdrop-filter: blur(18px);
			-webkit-backdrop-filter: blur(18px);
			box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
			animation: jet-geometry-spin 0.8s linear infinite;
			z-index: 1001;
		}
		@keyframes jet-geometry-spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		";
		
		wp_add_inline_style( 'jet-geometry-renderer', $preloader_css );
		
		// Also try to load it directly
		wp_print_scripts( 'jet-geometry-renderer' );
	}

	/**
	 * Add geometry property to listing data
	 *
	 * @param mixed  $value Current property value.
	 * @param object $object Current object.
	 * @param string $prop Property name.
	 * @return mixed
	 */
	public function add_geometry_prop( $value, $object, $prop ) {
		// Only for map geometry fields
		if ( false === strpos( $prop, '_geometry_type' ) && false === strpos( $prop, '_geometry_data' ) ) {
			return $value;
		}

		// Get object ID
		$object_id = 0;
		$object_type = 'post';

		if ( is_a( $object, 'WP_Post' ) ) {
			$object_id = $object->ID;
			$object_type = 'post';
		} elseif ( is_a( $object, 'WP_Term' ) ) {
			$object_id = $object->term_id;
			$object_type = 'term';
		} elseif ( is_a( $object, 'WP_User' ) ) {
			$object_id = $object->ID;
			$object_type = 'user';
		}

		if ( ! $object_id ) {
			return $value;
		}

		// Get meta
		$meta_key = $prop;

		switch ( $object_type ) {
			case 'term':
				$value = get_term_meta( $object_id, $meta_key, true );
				break;

			case 'user':
				$value = get_user_meta( $object_id, $meta_key, true );
				break;

			case 'post':
			default:
				$value = get_post_meta( $object_id, $meta_key, true );
				break;
		}

		return $value;
	}

	/**
	 * Prepare geometry data for map
	 *
	 * @param object $render Render instance.
	 * @return object
	 */
	public function prepare_geometry_data( $render ) {
		if ( ! $render || 'maps-listing' !== $render->get_render_type() ) {
			return $render;
		}

		// Add filter to modify markers data
		add_filter( 'jet-engine/maps-listing/map-markers', array( $this, 'add_geometry_to_markers' ), 10 );

		return $render;
	}
	
	/**
	 * Add geometry data to markers
	 *
	 * @param array $markers Markers array.
	 * @return array
	 */
	public function add_geometry_to_markers( $markers ) {
		foreach ( $markers as $index => $marker ) {
			if ( empty( $marker['id'] ) ) {
				continue;
			}
			
			$post_id = $marker['id'];
			
			// Get taxonomy terms for this post (especially countries)
			$taxonomies = $this->get_post_taxonomies( $post_id );
			if ( ! empty( $taxonomies ) ) {
				$markers[ $index ]['taxonomies'] = $taxonomies;
				// Also add directly accessible countries for easier frontend access
				if ( ! empty( $taxonomies['countries'] ) ) {
					$markers[ $index ]['countries'] = $taxonomies['countries'];
				}
			}
			
			// Get geometry data
			$geometry_data = $this->get_geometry_data_for_post( $post_id );
			
			if ( ! empty( $geometry_data ) ) {
				// Add styling settings from field configuration
				foreach ( $geometry_data as $idx => $geom ) {
					if ( ! empty( $geom['field_name'] ) ) {
						// Get meta box for this post type to retrieve field settings
						$field_settings = $this->get_field_styling_settings( $geom['field_name'], get_post_type( $post_id ) );
						if ( $field_settings ) {
							$geometry_data[ $idx ]['styling'] = $field_settings;
						}
					}
				}
				
				$markers[ $index ]['geometry_data'] = $geometry_data;
			}
		}
		
		return $markers;
	}

	/**
	 * Add geometry data to frontend settings
	 *
	 * @param array  $settings Settings array.
	 * @param object $render   Render instance.
	 * @return array
	 */
	public function add_geometry_to_settings( $settings, $render ) {
		if ( ! isset( $settings['markers'] ) || ! is_array( $settings['markers'] ) ) {
			return $settings;
		}

		// Get geometry data for each marker
		$markers = $settings['markers'];

		foreach ( $markers as $index => $marker ) {
			if ( ! isset( $marker['id'] ) ) {
				continue;
			}

			$post_id = $marker['id'];

			// Get all geometry fields for this post
			$geometry_data = $this->get_geometry_data_for_post( $post_id );

			if ( ! empty( $geometry_data ) ) {
				$markers[ $index ]['geometry_data'] = $geometry_data;
			}
		}

		$settings['markers'] = $markers;

		return $settings;
	}

	/**
	 * Get field styling settings
	 *
	 * @param string $field_name Field name (hash).
	 * @param string $post_type Post type.
	 * @return array|null
	 */
	private function get_field_styling_settings( $field_name, $post_type ) {
		if ( ! function_exists( 'jet_engine' ) ) {
			return null;
		}

		$meta_boxes = jet_engine()->meta_boxes->data->get_items();
		
		foreach ( $meta_boxes as $meta_box ) {
			if ( empty( $meta_box['meta_fields'] ) ) {
				continue;
			}

			// Check if this meta box is for our post type
			if ( ! empty( $meta_box['args']['allowed_post_type'] ) ) {
				if ( ! in_array( $post_type, (array) $meta_box['args']['allowed_post_type'] ) ) {
					continue;
				}
			}

			// Find our geometry field
			foreach ( $meta_box['meta_fields'] as $field ) {
				if ( $field['type'] !== 'map_geometry' ) {
					continue;
				}

				$field_prefix = Jet_Geometry_Utils::get_field_prefix( $field['name'] );
				
				if ( $field_prefix === $field_name ) {
					// Found our field! Return styling settings
					return array(
						'lineColor'        => ! empty( $field['geometry_line_color'] ) ? $field['geometry_line_color'] : '#ff0000',
						'polygonFillColor' => ! empty( $field['geometry_polygon_color'] ) ? $field['geometry_polygon_color'] : '#ff0000',
						'fillOpacity'      => ! empty( $field['geometry_fill_opacity'] ) ? floatval( $field['geometry_fill_opacity'] ) : 0.3,
						'lineWidth'        => 2, // Default
					);
				}
			}
		}

		return null;
	}

	/**
	 * Get taxonomies for post
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_post_taxonomies( $post_id ) {
		$taxonomies = array();
		
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return $taxonomies;
		}
		
		// Get all taxonomies for this post type
		$post_taxonomies = get_object_taxonomies( $post_type, 'objects' );
		
		foreach ( $post_taxonomies as $taxonomy_name => $taxonomy_obj ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy_name, array( 'fields' => 'all' ) );
			
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$taxonomies[ $taxonomy_name ] = array();
				
				foreach ( $terms as $term ) {
					$taxonomies[ $taxonomy_name ][] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					);
				}
			}
		}
		
		return $taxonomies;
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

		// Find geometry fields
		foreach ( $all_meta as $meta_key => $meta_value ) {
			if ( false === strpos( $meta_key, '_geometry_type' ) ) {
				continue;
			}

			// Extract field prefix
			$prefix = str_replace( '_geometry_type', '', $meta_key );

			$geometry_type = isset( $meta_value[0] ) ? $meta_value[0] : '';
			$geometry_data = get_post_meta( $post_id, $prefix . '_geometry_data', true );
			$lat = get_post_meta( $post_id, $prefix . '_lat', true );
			$lng = get_post_meta( $post_id, $prefix . '_lng', true );

			if ( ! empty( $geometry_type ) && ! empty( $geometry_data ) ) {
				$geometry_fields[] = array(
					'type'       => $geometry_type,
					'data'       => $geometry_data,
					'lat'        => $lat,
					'lng'        => $lng,
					'field_name' => $prefix,
				);
			}
		}

		return $geometry_fields;
	}

	/**
	 * Get geometry fields from meta boxes
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	private function get_geometry_fields_for_post_type( $post_type ) {
		if ( ! function_exists( 'jet_engine' ) || ! jet_engine()->meta_boxes ) {
			return array();
		}

		$meta_boxes = jet_engine()->meta_boxes->get_registered_boxes();
		$geometry_fields = array();

		if ( empty( $meta_boxes ) ) {
			return $geometry_fields;
		}

		foreach ( $meta_boxes as $meta_box ) {
			if ( empty( $meta_box['args']['object_type'] ) || 'post' !== $meta_box['args']['object_type'] ) {
				continue;
			}

			if ( empty( $meta_box['args']['allowed_post_type'] ) ) {
				continue;
			}

			$allowed_types = $meta_box['args']['allowed_post_type'];

			if ( ! in_array( $post_type, $allowed_types, true ) ) {
				continue;
			}

			// Get geometry fields from meta box
			if ( ! empty( $meta_box['args']['meta_fields'] ) ) {
				foreach ( $meta_box['args']['meta_fields'] as $field ) {
					if ( 'map_geometry' === $field['type'] ) {
						$geometry_fields[] = $field;
					}
				}
			}
		}

		return $geometry_fields;
	}

	/**
	 * Override JetEngine's mapbox-markerclusterer.js with our custom version
	 * This ensures clusters are red by default instead of blue
	 *
	 * @param string $src    Script source URL.
	 * @param string $handle Script handle.
	 * @return string Modified script source URL.
	 */
	public function override_mapbox_markerclusterer_script( $src, $handle ) {
		// Only override the jet-mapbox-markerclusterer script
		if ( 'jet-mapbox-markerclusterer' === $handle ) {
			// Check if function exists and file exists
			if ( function_exists( 'jet_geometry_addon' ) ) {
				$custom_src = jet_geometry_addon()->plugin_path( 'assets/js/public/mapbox-markerclusterer.js' );
				// Only override if our custom file exists
				if ( file_exists( $custom_src ) ) {
					$src = jet_geometry_addon()->plugin_url( 'assets/js/public/mapbox-markerclusterer.js' );
				}
			}
		}
		
		return $src;
	}
}

