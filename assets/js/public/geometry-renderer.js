/**
 * Jet Geometry Renderer - Frontend
 */

(function($) {
	'use strict';

	window.JetGeometryRenderer = {
		maps: {},

		init: function() {
			var self = this;

			// Wait for JetEngine maps to initialize
			$(document).on('jet-engine/maps-listing/init', function(e, mapInstance) {
				self.initMap(mapInstance);
			});

			// Also try to init on window load for already loaded maps
			$(window).on('load', function() {
				setTimeout(function() {
					self.initExistingMaps();
				}, 500);
			});
		},

		initExistingMaps: function() {
			var self = this;

			if ( typeof window.JetEngineListingMaps !== 'undefined' ) {
				$.each(window.JetEngineListingMaps, function(id, mapInstance) {
					if ( ! self.maps[id] ) {
						self.initMap(mapInstance);
					}
				});
			}
		},

		initMap: function(mapInstance) {
			if ( ! mapInstance || ! mapInstance.map ) {
				return;
			}

			var mapId = mapInstance.$container.attr('id') || 'map-' + Date.now();
			this.maps[mapId] = mapInstance;

			// Wait for map to load
			if ( mapInstance.map.loaded() ) {
				this.renderGeometries(mapId, mapInstance);
			} else {
				var self = this;
				mapInstance.map.on('load', function() {
					self.renderGeometries(mapId, mapInstance);
				});
			}
		},

		renderGeometries: function(mapId, mapInstance) {
			var map = mapInstance.map;
			var markers = mapInstance.markers || [];

			if ( ! markers.length ) {
				return;
			}

			var geometries = [];

			// Collect all geometries from markers
			markers.forEach(function(marker) {
				if ( marker.geometry_data && Array.isArray(marker.geometry_data) ) {
					marker.geometry_data.forEach(function(geom) {
						if ( geom.type && geom.data ) {
							try {
								var geojson = typeof geom.data === 'string' ? 
									JSON.parse(geom.data) : geom.data;

								geometries.push({
									id: marker.id,
									type: geom.type,
									geojson: geojson,
									marker: marker
								});
							} catch (e) {
							}
						}
					});
				}
			});

			if ( ! geometries.length ) {
				return;
			}

			// Render lines
			this.renderLines(map, geometries, mapId);

			// Render polygons
			this.renderPolygons(map, geometries, mapId);

			// Setup click handlers for popups
			this.setupPopupHandlers(map, geometries, mapInstance);
		},

		renderLines: function(map, geometries, mapId) {
			var lines = geometries.filter(function(g) { 
				return g.type === 'line'; 
			});

			if ( ! lines.length ) {
				return;
			}

			var features = lines.map(function(line) {
				return {
					type: 'Feature',
					properties: {
						id: line.id,
						post_id: line.id,
						markerData: line.marker
					},
					geometry: line.geojson
				};
			});

			var sourceId = 'jet-geometry-lines-' + mapId;
			var layerId = 'jet-geometry-lines-layer-' + mapId;

			// Add source
			if ( ! map.getSource(sourceId) ) {
				map.addSource(sourceId, {
					type: 'geojson',
					data: {
						type: 'FeatureCollection',
						features: features
					}
				});
			}

			// Add layer
			if ( ! map.getLayer(layerId) ) {
				map.addLayer({
					id: layerId,
					type: 'line',
					source: sourceId,
					layout: {
						'line-join': 'round',
						'line-cap': 'round'
					},
					paint: {
						'line-color': '#ff0000',
						'line-width': 3,
						'line-opacity': 0.8
					}
				});
			}

			// Make lines clickable
			map.on('mouseenter', layerId, function() {
				map.getCanvas().style.cursor = 'pointer';
			});

			map.on('mouseleave', layerId, function() {
				map.getCanvas().style.cursor = '';
			});
		},

		renderPolygons: function(map, geometries, mapId) {
			var polygons = geometries.filter(function(g) { 
				return g.type === 'polygon'; 
			});

			if ( ! polygons.length ) {
				return;
			}

			var features = polygons.map(function(polygon) {
				return {
					type: 'Feature',
					properties: {
						id: polygon.id,
						post_id: polygon.id,
						markerData: polygon.marker
					},
					geometry: polygon.geojson
				};
			});

			var sourceId = 'jet-geometry-polygons-' + mapId;
			var fillLayerId = 'jet-geometry-polygons-fill-' + mapId;
			var outlineLayerId = 'jet-geometry-polygons-outline-' + mapId;

			// Add source
			if ( ! map.getSource(sourceId) ) {
				map.addSource(sourceId, {
					type: 'geojson',
					data: {
						type: 'FeatureCollection',
						features: features
					}
				});
			}

			// Add fill layer
			if ( ! map.getLayer(fillLayerId) ) {
				map.addLayer({
					id: fillLayerId,
					type: 'fill',
					source: sourceId,
					paint: {
						'fill-color': '#ff0000',
						'fill-opacity': 0.3
					}
				});
			}

			// Add outline layer
			if ( ! map.getLayer(outlineLayerId) ) {
				map.addLayer({
					id: outlineLayerId,
					type: 'line',
					source: sourceId,
					layout: {},
					paint: {
						'line-color': '#ff0000',
						'line-width': 2,
						'line-opacity': 0.8
					}
				});
			}

			// Make polygons clickable
			map.on('mouseenter', fillLayerId, function() {
				map.getCanvas().style.cursor = 'pointer';
			});

			map.on('mouseleave', fillLayerId, function() {
				map.getCanvas().style.cursor = '';
			});
		},

		setupPopupHandlers: function(map, geometries, mapInstance) {
			var self = this;
			
			// Get general settings from container
			var $container = mapInstance.$container || (mapInstance.container && window.jQuery ? window.jQuery(mapInstance.container) : null);
			var generalData = null;
			var general = {};
			
			if ($container && $container.length) {
				generalData = $container.attr('data-general');
			} else if (map._container) {
				var containerEl = map._container;
				if (containerEl.closest) {
					var $closest = window.jQuery(containerEl.closest('.jet-map-listing'));
					if ($closest.length) {
						generalData = $closest.attr('data-general');
						$container = $closest;
					}
				}
			}
			
			if (generalData) {
				try {
					var decoded = generalData;
					try {
						decoded = decodeURIComponent(generalData);
					} catch (e) {
						try {
							var tempDiv = document.createElement('div');
							tempDiv.innerHTML = generalData;
							decoded = tempDiv.textContent || tempDiv.innerText || generalData;
						} catch (e2) {
							decoded = generalData;
						}
					}
					general = JSON.parse(decoded);
				} catch (e) {
					// Silent fail
				}
			}
			
			// Helper function to show popup using JetEngine API
			var showMarkerPopup = function(postId, lngLat) {
				if (!general.api || !general.listingID) {
					return;
				}
				
				var querySeparator = general.querySeparator || '?';
				var api = general.api +
					querySeparator +
					'listing_id=' + general.listingID +
					'&post_id=' + postId +
					'&source=' + (general.source || 'post') +
					'&geo_query_distance=';
				
				var queriedID = $container && $container.length ? $container.data('queried-id') : null;
				if (queriedID) {
					api += '&queried_id=' + queriedID;
				}
				
				var mapId = map._container ? map._container.id : null;
				if (mapId) {
					api += '&element_id=' + mapId;
				}
				
				// Show loading popup
				if (typeof mapboxgl !== 'undefined' && mapboxgl.Popup) {
					var loadingPopup = new mapboxgl.Popup()
						.setLngLat(lngLat)
						.setHTML('<div class="jet-map-preloader is-active"><div class="jet-map-loader"></div></div>')
						.addTo(map);
					
					// Fetch popup content
					window.jQuery.ajax({
						url: api,
						type: 'GET',
						dataType: 'json',
						beforeSend: function(jqXHR) {
							var nonce = window.JetEngineSettings ? window.JetEngineSettings.restNonce : general.restNonce;
							if (nonce) {
								jqXHR.setRequestHeader('X-WP-Nonce', nonce);
							}
						}
					}).done(function(response) {
						loadingPopup.setHTML(response.html || '');
						
						// Initialize handlers for popup content
						if (window.JetEngineMaps && window.JetEngineMaps.initHandlers) {
							var popupElement = loadingPopup.getElement();
							if (popupElement) {
								var $popupBox = window.jQuery(popupElement).find('.jet-map-box');
								if ($popupBox.length) {
									window.JetEngineMaps.initHandlers($popupBox);
								}
							}
						}
					}).fail(function(error) {
						loadingPopup.setHTML('<div class="jet-map-error">Failed to load content</div>');
					});
				}
			};

			geometries.forEach(function(geom) {
				var layerId = null;

				if ( geom.type === 'line' ) {
					layerId = 'jet-geometry-lines-layer-' + map._container.id;
				} else if ( geom.type === 'polygon' ) {
					layerId = 'jet-geometry-polygons-fill-' + map._container.id;
				}

				if ( ! layerId ) {
					return;
				}

				map.on('click', layerId, function(e) {
					var feature = e.features && e.features[0];
					if ( ! feature || !feature.properties ) {
						return;
					}

					var postId = feature.properties.id || feature.properties.post_id;
					
					if ( postId && e.lngLat ) {
						showMarkerPopup(postId, e.lngLat);
					} else {
					}
				});
			});
		}
	};

	// Initialize
	JetGeometryRenderer.init();

})(jQuery);






