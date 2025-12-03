/**
 * Jet Geometry Field - Admin Interface
 */

(function($) {
	'use strict';
	

	window.JetGeometryField = {
		instances: [],
		mapboxgl: null,
		MapboxDraw: null,

		init: function() {
			var self = this;
			

			// Initialize fields immediately when DOM is ready
			$(document).ready(function() {
				self.initFields();
			});
			
			// Also try on cx-control-init (JetEngine event)
			$(document).on('cx-control-init', function(event, data) {
				setTimeout(function() {
					self.initFields();
				}, 100);
			});

			// Re-initialize on JetEngine events
			$(document).on('jet-engine/meta-boxes/repeater/added', function() {
				setTimeout(function() {
					self.initFields();
				}, 100);
			});
		},

		initFields: function() {
			var self = this;
			
			
			// Search for our fields - try multiple selectors
			var $fields = $('.jet-geometry-field');
			
			$fields = $('.jet-geometry-field.cx-ui-container');
			
			$fields = $('input.jet-geometry-field');

			$('input[data-geometry-settings]').each(function() {
				var $input = $(this);
				var $container = $input.closest('.cx-ui-container');
				

				// Skip if already initialized
				if ( $container.data('geometry-initialized') ) {
					return;
				}

				$container.data('geometry-initialized', true);

				var settings = $input.attr('data-geometry-settings');
				if ( settings ) {
					try {
						settings = JSON.parse(
							$('<textarea/>').html(settings).text()
						);
						self.createFieldInstance($input, $container, settings);
					} catch (e) {
					}
				}
			});
		},

		createFieldInstance: function($input, $container, settings) {
			var instance = new GeometryFieldInstance($input, $container, settings);
			this.instances.push(instance);
			return instance;
		}
	};

	/**
	 * Single field instance
	 */
	function GeometryFieldInstance($input, $container, settings) {
		this.$field = $input;
		this.$container = $container;
		this.settings = settings;
		this.map = null;
		this.draw = null;
		this.currentGeometry = null;
		this.currentType = settings.default_type || 'pin';
		this.isPanMode = false;

		this.init();
	}

	GeometryFieldInstance.prototype = {
		init: function() {
			this.createUI();
			this.initMap();
			this.bindEvents();
			this.loadExistingData();
		},

		createUI: function() {
			var template = wp.template('jet-geometry-field');
			var html = template({
				height: this.settings.height || 400,
				geometryTypes: this.settings.geometry_types || ['pin', 'line', 'polygon'],
				currentType: this.currentType
			});

			// Append to existing container
			this.$container.append(html);
		},

		initMap: function() {
			var self = this;
			var $mapEl = this.$container.find('.jet-geometry-field__map')[0];

			if ( ! $mapEl ) {
				return;
			}

			// Get Mapbox token
			var token = JetGeometrySettings.mapboxToken || '';
			if ( ! token ) {
				return;
			}

			mapboxgl.accessToken = token;

			// Create map
			this.map = new mapboxgl.Map({
				container: $mapEl,
				style: 'mapbox://styles/mapbox/streets-v12',
				center: JetGeometrySettings.defaultCenter || [0, 0],
				zoom: JetGeometrySettings.defaultZoom || 2
			});

			// Initialize Mapbox Draw
			this.draw = new MapboxDraw({
				displayControlsDefault: false,
				controls: {
					point: false,
					line_string: false,
					polygon: false,
					trash: true
				},
				styles: this.getDrawStyles()
			});

			this.map.addControl(this.draw);

			// Handle draw events
			this.map.on('draw.create', function(e) {
				self.onGeometryCreated(e.features[0]);
			});

			this.map.on('draw.update', function(e) {
				self.onGeometryUpdated(e.features[0]);
			});

			this.map.on('draw.delete', function() {
				self.onGeometryDeleted();
			});
		},

		getDrawStyles: function() {
			var lineColor = this.settings.line_color || '#ff0000';
			var polygonColor = this.settings.polygon_color || '#ff0000';
			var fillOpacity = this.settings.fill_opacity || 0.3;

			return [
				// Active polygon fill
				{
					'id': 'gl-draw-polygon-fill',
					'type': 'fill',
					'filter': ['all', ['==', '$type', 'Polygon'], ['!=', 'mode', 'static']],
					'paint': {
						'fill-color': polygonColor,
						'fill-outline-color': polygonColor,
						'fill-opacity': fillOpacity
					}
				},
				// Active polygon outline
				{
					'id': 'gl-draw-polygon-stroke-active',
					'type': 'line',
					'filter': ['all', ['==', '$type', 'Polygon'], ['!=', 'mode', 'static']],
					'layout': {
						'line-cap': 'round',
						'line-join': 'round'
					},
					'paint': {
						'line-color': polygonColor,
						'line-width': 2
					}
				},
				// Active line
				{
					'id': 'gl-draw-line',
					'type': 'line',
					'filter': ['all', ['==', '$type', 'LineString'], ['!=', 'mode', 'static']],
					'layout': {
						'line-cap': 'round',
						'line-join': 'round'
					},
					'paint': {
						'line-color': lineColor,
						'line-width': 3
					}
				},
				// Points
				{
					'id': 'gl-draw-point',
					'type': 'circle',
					'filter': ['all', ['==', '$type', 'Point'], ['==', 'meta', 'feature']],
					'paint': {
						'circle-radius': 8,
						'circle-color': lineColor
					}
				}
			];
		},

		bindEvents: function() {
			var self = this;

			// Type selector buttons
			this.$container.on('click', '.jet-geometry-type-btn', function(e) {
				e.preventDefault();
				var type = $(this).data('type');
				self.setGeometryType(type);
			});

			// Search input
			var searchTimeout;
			this.$container.on('input', '.jet-geometry-search-input', function() {
				clearTimeout(searchTimeout);
				var query = $(this).val();
				
				if ( query.length < 3 ) {
					self.$container.find('.jet-geometry-field__search-results').empty();
					return;
				}

				searchTimeout = setTimeout(function() {
					self.searchLocation(query);
				}, 500);
			});

			// Coordinates input
			this.$container.on('click', '.jet-geometry-set-coords-btn', function(e) {
				e.preventDefault();
				var coords = self.$container.find('.jet-geometry-coords-input').val();
				self.setCoordinates(coords);
			});

			// Reset button
			this.$container.on('click', '.jet-geometry-reset-btn', function(e) {
				e.preventDefault();
				self.resetGeometry();
			});

			// Delete button
			this.$container.on('click', '.jet-geometry-delete-btn', function(e) {
				e.preventDefault();
				self.deleteGeometry();
			});

			// Pan toggle button
			this.$container.on('click', '.jet-geometry-pan-btn', function(e) {
				e.preventDefault();
				self.setPanMode( ! self.isPanMode );
			});
		},

		setGeometryType: function(type) {
			if ( this.isPanMode ) {
				this.setPanMode(false);
			}

			this.currentType = type;

			// Update button states
			this.$container.find('.jet-geometry-type-btn').attr('aria-pressed', 'false');
			this.$container.find('.jet-geometry-type-btn[data-type="' + type + '"]').attr('aria-pressed', 'true');

			// Clear current drawing
			if ( this.draw ) {
				this.draw.deleteAll();
			}

			// Enable appropriate draw mode
			this.enableDrawMode();

			// Update info
			this.updateInfo();
		},

		enableDrawMode: function() {
			if ( ! this.draw ) {
				return;
			}

			this.draw.changeMode('draw_' + this.getDrawModeName());
		},

		getDrawModeName: function() {
			switch ( this.currentType ) {
				case 'pin':
					return 'point';
				case 'line':
					return 'line_string';
				case 'polygon':
					return 'polygon';
				default:
					return 'point';
			}
		},

		searchLocation: function(query) {
			var self = this;
			var $results = this.$container.find('.jet-geometry-field__search-results');

			$results.html('<div class="jet-geometry-loading">' + JetGeometrySettings.i18n.loading + '</div>');

			// Use Mapbox Geocoding API
			var token = JetGeometrySettings.mapboxToken;
			var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' + 
				      encodeURIComponent(query) + '.json?access_token=' + token;

			$.getJSON(url, function(data) {
				if ( data.features && data.features.length > 0 ) {
					var html = '<ul class="jet-geometry-search-list">';
					data.features.forEach(function(feature) {
						html += '<li data-lng="' + feature.center[0] + '" data-lat="' + feature.center[1] + '">';
						html += '<strong>' + feature.text + '</strong><br>';
						html += '<small>' + feature.place_name + '</small>';
						html += '</li>';
					});
					html += '</ul>';
					$results.html(html);

					// Handle result clicks
					$results.find('li').on('click', function() {
						var lng = parseFloat($(this).data('lng'));
						var lat = parseFloat($(this).data('lat'));
						self.flyToLocation(lng, lat);
						$results.empty();
						self.$container.find('.jet-geometry-search-input').val('');
					});
				} else {
					$results.html('<div class="jet-geometry-no-results">' + JetGeometrySettings.i18n.notFound + '</div>');
				}
			}).fail(function() {
				$results.html('<div class="jet-geometry-error">Error searching location</div>');
			});
		},

		setCoordinates: function(coordsStr) {
			var parts = coordsStr.split(',');
			if ( parts.length !== 2 ) {
				alert(JetGeometrySettings.i18n.invalidCoordinates);
				return;
			}

			var lat = parseFloat(parts[0].trim());
			var lng = parseFloat(parts[1].trim());

			if ( isNaN(lat) || isNaN(lng) ) {
				alert(JetGeometrySettings.i18n.invalidCoordinates);
				return;
			}

			this.flyToLocation(lng, lat);
			this.$container.find('.jet-geometry-coords-input').val('');
		},

		flyToLocation: function(lng, lat) {
			if ( this.map ) {
				this.map.flyTo({
					center: [lng, lat],
					zoom: 12
				});

				// If pin mode, create point
				if ( this.currentType === 'pin' ) {
					this.createPoint(lng, lat);
				}
			}
		},

		createPoint: function(lng, lat) {
			if ( ! this.draw ) {
				return;
			}

			this.draw.deleteAll();
			var pointId = this.draw.add({
				type: 'Feature',
				geometry: {
					type: 'Point',
					coordinates: [lng, lat]
				}
			});

			this.currentGeometry = {
				type: 'Point',
				coordinates: [lng, lat]
			};

			this.saveGeometry();
		},

		onGeometryCreated: function(feature) {
			this.currentGeometry = feature.geometry;
			this.saveGeometry();
		},

		onGeometryUpdated: function(feature) {
			this.currentGeometry = feature.geometry;
			this.saveGeometry();
		},

		onGeometryDeleted: function() {
			this.currentGeometry = null;
			this.saveGeometry();
		},

		saveGeometry: function() {
			var prefix = this.settings.field_prefix;
			var geometry = this.currentGeometry;

			if ( ! geometry ) {
				// Clear all fields
				this.setFieldValue(prefix + '_geometry_type', '');
				this.setFieldValue(prefix + '_geometry_data', '');
				this.setFieldValue(prefix + '_lat', '');
				this.setFieldValue(prefix + '_lng', '');
				this.$field.val('');
				this.updateInfo();
				return;
			}

			// Calculate centroid
			var centroid = this.calculateCentroid(geometry);

			// Save data
			this.setFieldValue(prefix + '_geometry_type', this.currentType);
			this.setFieldValue(prefix + '_geometry_data', JSON.stringify(geometry));
			this.setFieldValue(prefix + '_lat', centroid[1]);
			this.setFieldValue(prefix + '_lng', centroid[0]);
			
			// Set main field value (for compatibility)
			this.$field.val(JSON.stringify(geometry));

			this.updateInfo();
		},

		setFieldValue: function(name, value) {
			var $input = $('[name="' + name + '"]');
			if ( $input.length ) {
				$input.val(value);
			}
		},

		calculateCentroid: function(geometry) {
			var coords = geometry.coordinates;
			var type = geometry.type;

			switch ( type ) {
				case 'Point':
					return coords;

				case 'LineString':
					var sum = coords.reduce(function(acc, coord) {
						return [acc[0] + coord[0], acc[1] + coord[1]];
					}, [0, 0]);
					return [sum[0] / coords.length, sum[1] / coords.length];

				case 'Polygon':
					var ring = coords[0];
					var sum = ring.reduce(function(acc, coord) {
						return [acc[0] + coord[0], acc[1] + coord[1]];
					}, [0, 0]);
					return [sum[0] / ring.length, sum[1] / ring.length];

				default:
					return [0, 0];
			}
		},

		updateInfo: function() {
			var typeText = JetGeometrySettings.i18n['type' + this.currentType.charAt(0).toUpperCase() + this.currentType.slice(1)];
			this.$container.find('.jet-geometry-current-type').text(typeText || this.currentType);

			if ( this.currentGeometry ) {
				var centroid = this.calculateCentroid(this.currentGeometry);
				var coordsText = centroid[1].toFixed(6) + ', ' + centroid[0].toFixed(6);
				this.$container.find('.jet-geometry-current-coords').text(coordsText);
			} else {
				this.$container.find('.jet-geometry-current-coords').text('');
			}
		},

		loadExistingData: function() {
			var prefix = this.settings.field_prefix;
			var geomData = this.getFieldValue(prefix + '_geometry_data');
			var geomType = this.getFieldValue(prefix + '_geometry_type');

			if ( geomData ) {
				try {
					var geometry = JSON.parse(geomData);
					this.currentGeometry = geometry;

					if ( geomType ) {
						this.currentType = geomType;
						this.setGeometryType(geomType);
					}

					// Add to map
					if ( this.draw && geometry ) {
						this.draw.add({
							type: 'Feature',
							geometry: geometry
						});

						// Fit bounds to geometry
						this.fitBoundsToGeometry(geometry);
					}

					this.updateInfo();
				} catch (e) {
				}
			}
		},

		getFieldValue: function(name) {
			var $input = $('[name="' + name + '"]');
			return $input.length ? $input.val() : '';
		},

		fitBoundsToGeometry: function(geometry) {
			if ( ! this.map || ! geometry ) {
				return;
			}

			var bounds = new mapboxgl.LngLatBounds();
			var coords = geometry.coordinates;

			switch ( geometry.type ) {
				case 'Point':
					this.map.flyTo({ center: coords, zoom: 12 });
					break;

				case 'LineString':
					coords.forEach(function(coord) {
						bounds.extend(coord);
					});
					this.map.fitBounds(bounds, { padding: 50 });
					break;

				case 'Polygon':
					coords[0].forEach(function(coord) {
						bounds.extend(coord);
					});
					this.map.fitBounds(bounds, { padding: 50 });
					break;
			}
		},

		resetGeometry: function() {
			if ( this.map ) {
				this.map.flyTo({
					center: JetGeometrySettings.defaultCenter || [0, 0],
					zoom: JetGeometrySettings.defaultZoom || 2
				});
			}
		},

		deleteGeometry: function() {
			if ( this.draw ) {
				this.draw.deleteAll();
			}
			this.currentGeometry = null;
			this.saveGeometry();
			this.setPanMode(false);
		},

		setPanMode: function(enabled) {
			this.isPanMode = !! enabled;

			var $btn = this.$container.find('.jet-geometry-pan-btn');

			if ( this.isPanMode ) {
				$btn.attr('aria-pressed', 'true').addClass('is-active');
				if ( this.draw ) {
					this.draw.changeMode('simple_select');
				}
			} else {
				$btn.attr('aria-pressed', 'false').removeClass('is-active');
				if ( this.draw ) {
					this.enableDrawMode();
				}
			}
		}
	};

	// Initialize when ready
	JetGeometryField.init();

})(jQuery);

