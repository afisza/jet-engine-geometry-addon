/**
 * JetSmartFilters Integration
 * Handles geometry rendering after filter updates
 */
(function($) {
	'use strict';

	var countryQueryVar = (window.JetCountryLayersData && window.JetCountryLayersData.taxonomy) ? window.JetCountryLayersData.taxonomy : 'countries';
	var countryFilterSelector = '.jet-select[data-query-var="' + countryQueryVar + '"] select';

	function readCountryFilterValue() {
		var value = null;

		$(countryFilterSelector).each(function() {
			var current = $(this).val();

			if ( Array.isArray(current) ) {
				current = current.length ? current[0] : null;
			}

			if ( null !== current && '' !== current && typeof current !== 'undefined' ) {
				value = current;
				return false;
			}
		});

		if ( null === value ) {
			var $fallback = $('.jet-select[data-query-var="' + countryQueryVar + '"] input[type="hidden"]').first();

			if ( $fallback.length ) {
				value = $fallback.val() || null;
			}
		}

		return value;
	}

	function applySelectedCountry(value, options) {
		var attempts = 0;
		var maxAttempts = 12;

		var normalized = value;

		if ( Array.isArray(normalized) ) {
			normalized = normalized.length ? normalized[0] : null;
		}

		var execute = function() {
			if ( window.JetCountryLayers && typeof window.JetCountryLayers.setSelectedCountry === 'function' ) {
				if ( normalized ) {
					window.JetCountryLayers.setSelectedCountry(normalized, options || {});
				} else if ( window.JetCountryLayers.clearSelectedCountry ) {
					window.JetCountryLayers.clearSelectedCountry();
				} else {
					window.JetCountryLayers.setSelectedCountry(null);
				}
				return true;
			}
			return false;
		};

		if ( execute() ) {
			return;
		}

		var retry = function() {
			attempts++;
			if ( execute() || attempts >= maxAttempts ) {
				return;
			}
			setTimeout(retry, 250);
		};

		retry();
	}

	function syncSelectedCountry(options) {
		applySelectedCountry(readCountryFilterValue(), options);
	}

	$(document).on('change', countryFilterSelector, function() {
		syncSelectedCountry({ fit: true });
	});

	$(function() {
		syncSelectedCountry();
	});

	// Function to update counter from current map state
	function updateCounterFromMapState() {
		if (!window.JetGeometryWidgets) {
			console.warn('[Filters Integration] JetGeometryWidgets not available');
			return;
		}
		
		var $map = $('.jet-map-listing').first();
		if (!$map.length) {
			console.warn('[Filters Integration] Map not found');
			return;
		}
		
		var count = 0;
		var markersData = $map.data('markers');
		
		if (markersData && Array.isArray(markersData)) {
			count = markersData.length;
		} else {
			// Try map instance
			var mapInstance = $map.data('mapInstance');
			if (mapInstance && mapInstance.getSource && mapInstance.getSource('markers')) {
				var source = mapInstance.getSource('markers');
				if (source && source._data && source._data.features) {
					count = source._data.features.length;
				}
			}
		}
		
		if (count > 0 || count === 0) { // Allow 0 to be set
			var activeFilters = window.getActiveFiltersInfo ? window.getActiveFiltersInfo() : { summary: 'Unknown' };
			window.JetGeometryWidgets.updateIncidentCounter(count, {
				source: 'updateCounterFromMapState',
				filters: activeFilters
			});
		}
	}
	
	// Listen for JetSmartFilters content update (triggered on each map widget)
	// This event is triggered by JetEngine on the map $scope element with response data
	$(document).on('jet-filter-custom-content-render', '.jet-map-listing', function(event, response) {

		var $mapContainer = $(this);

		// Get general settings (including styling) first to check preloader setting
		var generalSettings = $mapContainer.data('general');
		var styling = (generalSettings && generalSettings.geometryStyling) || {};
		var preloaderEnabled = true;
		
		// Check if preloader is enabled
		if (typeof styling.showPreloader !== 'undefined') {
			preloaderEnabled = !! styling.showPreloader;
		} else {
			// Fallback to data attribute set in frontend-geometry.js
			var dataPreloader = $mapContainer.data('jetPreloaderEnabled');
			if (typeof dataPreloader !== 'undefined') {
				preloaderEnabled = !! dataPreloader;
			}
		}

		// Add loading class only if preloader is enabled
		if (preloaderEnabled) {
			$mapContainer.addClass('loading');
		}

		// Get map instance
		var mapInstance = $mapContainer.data('mapInstance');
		
		if (!mapInstance) {
			if (preloaderEnabled) {
				$mapContainer.removeClass('loading');
			}
			return;
		}

		
		// Clear existing geometry layers
		clearGeometryLayers(mapInstance);

		// Re-add geometries for filtered markers
		if (response && response.markers && response.markers.length > 0) {
			processFilteredGeometries(mapInstance, response.markers, styling, $mapContainer);
		}

		syncSelectedCountry();
		
		// Update incident counter after filter update
		// IMPORTANT: response.markers might be empty, so we need to wait for JetEngine to update the map
		// and then count the actual markers that were added to the map
		var updateCounterAttempts = [500, 1000, 1500, 2500, 3500];
		var attemptIndex = 0;
		
		// Store mapInstance in outer scope so it's available in tryUpdateCounter
		var storedMapInstance = mapInstance;
		var storedResponse = response;
		
		var tryUpdateCounter = function() {
			var count = 0;
			var source = 'unknown';
			var $map = $mapContainer;
			var mapInstance = storedMapInstance;
			var response = storedResponse;
			
			// First try: response.markers (if available and not empty)
			if (response && response.markers && Array.isArray(response.markers) && response.markers.length > 0) {
				count = response.markers.length;
				source = 'response.markers';
			}
			// NOTE: Skip response.pagination.found_posts - it contains total posts count, not filtered count
			// We'll use JetEngineMaps.markersData instead which has the actual filtered markers
			// Second try: JetEngineMaps.markersData (count unique post IDs - this is the actual filtered count)
			else if (window.JetEngineMaps && window.JetEngineMaps.markersData) {
				var uniquePostIds = {};
				var totalMarkers = 0;
				for (var markerId in window.JetEngineMaps.markersData) {
					if (window.JetEngineMaps.markersData[markerId] && Array.isArray(window.JetEngineMaps.markersData[markerId])) {
						// Count unique post IDs (markerId is the post ID)
						if (!uniquePostIds[markerId]) {
							uniquePostIds[markerId] = true;
							totalMarkers++;
						}
					}
				}
				if (totalMarkers > 0) {
					count = totalMarkers;
					source = 'JetEngineMaps.markersData (unique posts)';
				}
			}
			// Third try: map instance source (for Mapbox) - count features in the source
			if (count === 0 && mapInstance) {
				if (mapInstance.getSource && mapInstance.getSource('markers')) {
					var mapSource = mapInstance.getSource('markers');
					if (mapSource && mapSource._data && mapSource._data.features) {
						count = mapSource._data.features.length;
						source = 'mapInstance.source.features';
					}
				}
			}
			// Fourth try: count from clusterer (for clustered maps)
			if (count === 0 && window.JetEngineMaps && $map.length) {
				var mapID = $map.attr('id') || $map.data('map-id');
				if (mapID && window.JetEngineMaps.clusterersData && window.JetEngineMaps.clusterersData[mapID]) {
					var clusterer = window.JetEngineMaps.clusterersData[mapID];
					if (clusterer && clusterer.markers && Array.isArray(clusterer.markers)) {
						count = clusterer.markers.length;
						source = 'clusterer.markers';
					}
				}
			}
			
			// DO NOT use map.data('markers') - it contains old, unfiltered data!
			// Only update if we have a valid count from one of the above sources
			if (count > 0 || attemptIndex >= updateCounterAttempts.length - 1) {
				if (window.JetGeometryWidgets) {
					// Get active filters info
					var activeFilters = window.getActiveFiltersInfo ? window.getActiveFiltersInfo() : { summary: 'Unknown' };
					
					// Only update if count > 0, or if it's the last attempt (to show 0 if really no markers)
					if (count > 0 || attemptIndex >= updateCounterAttempts.length - 1) {
						window.JetGeometryWidgets.updateIncidentCounter(count, {
							source: source,
							filters: activeFilters
						});
					}
				}
			} else {
				// Retry with next delay
				attemptIndex++;
				if (attemptIndex < updateCounterAttempts.length) {
					var delay = attemptIndex === 1 
						? updateCounterAttempts[attemptIndex] - updateCounterAttempts[0]
						: updateCounterAttempts[attemptIndex] - updateCounterAttempts[attemptIndex - 1];
					setTimeout(tryUpdateCounter, delay);
				}
			}
		};
		
		setTimeout(tryUpdateCounter, updateCounterAttempts[0]);
		
		// Remove loading class only if preloader was enabled
		if (preloaderEnabled) {
			setTimeout(function() {
				$mapContainer.removeClass('loading');
			}, 1500);
		}
	});
	
	/**
	 * Get count of posts related to a term
	 * Uses WordPress REST API endpoint for JetEngine taxonomies
	 */
	function getTermPostCount(taxonomy, termId, termName, callback) {
		if (!termId || !taxonomy) {
			if (callback) {
				callback(termName || 'Unknown', termId || 'N/A', 0);
			}
			return;
		}
		
		// Determine REST API base URL
		var restUrl = '/wp-json/wp/v2/';
		if (window.wpApiSettings && window.wpApiSettings.root) {
			restUrl = window.wpApiSettings.root;
			// Ensure it ends with /wp/v2/
			if (!restUrl.endsWith('/wp/v2/')) {
				if (restUrl.endsWith('/')) {
					restUrl = restUrl + 'wp/v2/';
				} else {
					restUrl = restUrl + '/wp/v2/';
				}
			}
		} else if (window.jetGeometrySettings && window.jetGeometrySettings.restUrl) {
			// Extract base URL from restUrl (remove endpoint path)
			var restUrlMatch = window.jetGeometrySettings.restUrl.match(/^(https?:\/\/[^\/]+)(\/.*)?$/);
			if (restUrlMatch) {
				restUrl = restUrlMatch[1] + '/wp-json/wp/v2/';
			}
		}
		
		// Use WordPress REST API endpoint for taxonomy terms
		// For JetEngine taxonomies, the endpoint is /wp-json/wp/v2/{taxonomy}/{term_id}
		// Ensure restUrl always ends with /wp/v2/ before appending taxonomy
		if (!restUrl.endsWith('/wp/v2/')) {
			if (restUrl.endsWith('/')) {
				restUrl = restUrl + 'wp/v2/';
			} else {
				restUrl = restUrl + '/wp/v2/';
			}
		}
		
		var termEndpoint = restUrl + taxonomy + '/' + termId;
		
		$.ajax({
			url: termEndpoint,
			method: 'GET',
			beforeSend: function(xhr) {
				// Add nonce if available
				if (window.wpApiSettings && window.wpApiSettings.nonce) {
					xhr.setRequestHeader('X-WP-Nonce', window.wpApiSettings.nonce);
				} else if (window.jetGeometrySettings && window.jetGeometrySettings.restNonce) {
					xhr.setRequestHeader('X-WP-Nonce', window.jetGeometrySettings.restNonce);
				}
			},
			success: function(term) {
				// The REST API returns term object with 'count' property
				var postCount = (term && typeof term.count !== 'undefined') 
					? parseInt(term.count) 
					: 0;
				if (callback) {
					callback(termName, termId, postCount);
				}
			},
			error: function(xhr, status, error) {
				// Fallback: try to get from term object if available in DOM
				var termCount = 0;
				var $termOption = $('.jet-select[data-query-var="' + taxonomy + '"] option[value="' + termId + '"]');
				if ($termOption.length) {
					// Try to extract count from option text (format: "Denmark (14)")
					var optionText = $termOption.text();
					var match = optionText.match(/\((\d+)\)/);
					if (match && match[1]) {
						termCount = parseInt(match[1]);
					}
				}
				
				if (termCount > 0) {
					if (callback) {
						callback(termName, termId, termCount);
					}
				} else {
					if (callback) {
						callback(termName, termId, 0);
					}
				}
			}
		});
	}
	
	// Listen for JetSmartFilters filter applied event
	$(document).on('jet-smart-filters/after-ajax-content', function(event, data) {
		// Update counter when filters are applied
		setTimeout(updateCounterFromMapState, 500);
	});
	
	// Listen for JetSmartFilters filter reset/clear event
	$(document).on('jet-smart-filters/clear-filters', function(event) {
		// Update counter when filters are cleared
		setTimeout(updateCounterFromMapState, 500);
	});
	
	// Listen for individual filter changes (as fallback)
	$(document).on('change', '.jet-smart-filters-wrapper select, .jet-smart-filters-wrapper input[type="checkbox"], .jet-smart-filters-wrapper input[type="radio"]', function() {
		// Update counter after filter change (with delay to allow AJAX to complete)
		setTimeout(updateCounterFromMapState, 1000);
	});

	function clearGeometryLayers(mapInstance) {
		
		// Remove all our geometry layers
		var layers = mapInstance.getStyle().layers;
		layers.forEach(function(layer) {
			if (layer.id.indexOf('geometry-polygon-') === 0 || 
			    layer.id.indexOf('geometry-line-') === 0) {
				try {
					mapInstance.removeLayer(layer.id);
				} catch (e) {
					// Layer might already be removed
				}
			}
		});
		
		// Remove sources
		var sources = Object.keys(mapInstance.getStyle().sources);
		sources.forEach(function(sourceId) {
			if (sourceId.indexOf('geometry-polygon-') === 0 || 
			    sourceId.indexOf('geometry-line-') === 0) {
				try {
					mapInstance.removeSource(sourceId);
				} catch (e) {
					// Source might already be removed
				}
			}
		});
	}

	function processFilteredGeometries(mapInstance, markersData, widgetStyling, $mapContainer) {
		
		markersData.forEach(function(marker, index) {
			if (!marker.geometry_data || !marker.geometry_data[0]) {
				return;
			}

			var geometry = marker.geometry_data[0];

			if (geometry.type === 'polygon') {
				addPolygonFiltered(mapInstance, geometry, marker, index, widgetStyling);
				setTimeout(function() {
					hideMarkerForPostFiltered(marker.id);
				}, 1000);
			} else if (geometry.type === 'line') {
				addLineFiltered(mapInstance, geometry, marker, index, widgetStyling);
				setTimeout(function() {
					hideMarkerForPostFiltered(marker.id);
				}, 1000);
			}
		});

		// Re-apply cluster styling - użyj 'idle' event aby zastosować style natychmiast po utworzeniu warstw
		if (widgetStyling && widgetStyling.clusterColor) {
			var applyClusterStyle = function() {
				applyClusterStylingFiltered(mapInstance, widgetStyling.clusterColor, widgetStyling.clusterTextColor);
			};
			
			// Sprawdź czy już dodaliśmy listenery dla tej mapy
			if (!mapInstance._jetGeometryClusterStylingFilteredApplied) {
				mapInstance._jetGeometryClusterStylingFilteredApplied = true;
				
				// Dodaj listener na 'idle' aby ponownie zastosować style po każdej zmianie (np. po toggle country layers)
				mapInstance.on('idle', function() {
					// Sprawdź czy warstwa clusters istnieje i jest widoczna
					if (mapInstance.getLayer('clusters')) {
						try {
							var style = mapInstance.getStyle();
							if (style && style.layers) {
								var layer = style.layers.find(function(l) { return l.id === 'clusters'; });
								if (layer && layer.layout && layer.layout.visibility !== 'none') {
									applyClusterStyle();
								}
							}
						} catch (e) {
							// Fallback: po prostu zastosuj style
							applyClusterStyle();
						}
					}
				});
			}
			
			// Jeśli mapa jest już załadowana, zastosuj style natychmiast
			if (mapInstance.loaded && mapInstance.loaded()) {
				if (mapInstance.getLayer('clusters')) {
					applyClusterStyle();
				} else {
					// Poczekaj na 'idle' - warstwy będą gotowe
					mapInstance.once('idle', applyClusterStyle);
				}
			} else {
				// Przechwyć 'load' i zastosuj style natychmiast po utworzeniu warstw
				mapInstance.once('load', function() {
					// Przechwyć 'idle' który jest wywoływany po pełnym załadowaniu wszystkich warstw
					mapInstance.once('idle', applyClusterStyle);
					
					// Dodatkowo, spróbuj zastosować style natychmiast po 'load' (warstwy mogą być już utworzone)
					requestAnimationFrame(function() {
						applyClusterStyle();
					});
				});
			}
			
			// Przechwyć zmiany widoczności warstw przez country-layers toggle
			$(document).on('jet-country-layers/toggle jet-country-layers/incidents-toggled', function() {
				// Opóźnienie aby upewnić się, że warstwy są już zaktualizowane
				setTimeout(function() {
					applyClusterStyle();
				}, 150);
			});
		}
		
		// Re-apply map theme colors
		applyMapThemeColorsFiltered(mapInstance, widgetStyling);
		
		// Update incident counter with filtered count
		if (window.JetGeometryWidgets) {
			var filteredCount = markersData ? markersData.length : 0;
			window.JetGeometryWidgets.updateIncidentCounter(filteredCount);
		}
	}

	function addPolygonFiltered(mapInstance, geometry, marker, index, widgetStyling) {
		// Use same logic as main frontend-geometry.js
		try {
			var geoJson = JSON.parse(geometry.data);
			var sourceId = 'geometry-polygon-' + marker.id + '-' + index;
			var layerId = sourceId + '-layer';
			var outlineId = sourceId + '-outline';
			
			var fillColor = (widgetStyling && widgetStyling.polygonFillColor) || '#ff0000';
			var fillOpacity = (widgetStyling && widgetStyling.fillOpacity) || 0.3;
			var lineColor = (widgetStyling && widgetStyling.lineColor) || '#ff0000';
			var lineWidth = (widgetStyling && widgetStyling.lineWidth) || 2;

			// Mapbox requires fill-color to be 6-digit hex without alpha channel
			// If color has 8 digits (includes alpha), extract alpha and use it for opacity
			// Format: #RRGGBBAA where AA is alpha channel (00-FF)
			if (fillColor.length === 9 && fillColor.startsWith('#')) {
				// Extract alpha channel (last 2 characters)
				var alphaHex = fillColor.substring(7, 9);
				var alphaDecimal = parseInt(alphaHex, 16) / 255;
				// Use alpha as opacity if it's not already set explicitly (default is 0.3)
				if (fillOpacity === 0.3 || !widgetStyling || !widgetStyling.fillOpacity) {
					fillOpacity = alphaDecimal;
				}
				// Use only RGB part (first 7 characters: #RRGGBB)
				fillColor = fillColor.substring(0, 7);
			}

			if (!mapInstance.getSource(sourceId)) {
				mapInstance.addSource(sourceId, {
					type: 'geojson',
					data: geoJson
				});

				mapInstance.addLayer({
					id: layerId,
					type: 'fill',
					source: sourceId,
					paint: {
						'fill-color': fillColor,
						'fill-opacity': parseFloat(fillOpacity)
					}
				});

				mapInstance.addLayer({
					id: outlineId,
					type: 'line',
					source: sourceId,
					paint: {
						'line-color': lineColor,
						'line-width': parseInt(lineWidth)
					}
				});

			}
		} catch (error) {
		}
	}

	function addLineFiltered(mapInstance, geometry, marker, index, widgetStyling) {
		try {
			var geoJson = JSON.parse(geometry.data);
			var sourceId = 'geometry-line-' + marker.id + '-' + index;
			var layerId = sourceId + '-layer';
			
			var lineColor = (widgetStyling && widgetStyling.lineColor) || '#ff0000';
			var lineWidth = (widgetStyling && widgetStyling.lineWidth) || 3;

			if (!mapInstance.getSource(sourceId)) {
				mapInstance.addSource(sourceId, {
					type: 'geojson',
					data: geoJson
				});

				mapInstance.addLayer({
					id: layerId,
					type: 'line',
					source: sourceId,
					paint: {
						'line-color': lineColor,
						'line-width': parseInt(lineWidth)
					}
				});

			}
		} catch (error) {
		}
	}

	function hideMarkerForPostFiltered(postId) {
		if (window.JetEngineMaps && window.JetEngineMaps.markersData && window.JetEngineMaps.markersData[postId]) {
			var markersArray = window.JetEngineMaps.markersData[postId];
			markersArray.forEach(function(markerObj) {
				var marker = markerObj.marker || markerObj;
				if (marker && marker._element) {
					marker._element.style.display = 'none';
				}
			});
		}
	}

	function applyClusterStylingFiltered(mapInstance, clusterColor, textColor) {
		clusterColor = normalizeMapColor(clusterColor);
		textColor = normalizeMapColor(textColor || '#ffffff');
		
		if (!mapInstance || !mapInstance.getLayer) {
			return;
		}
		
		try {
			if (mapInstance.getLayer('clusters')) {
				mapInstance.setPaintProperty('clusters', 'circle-color', [
					'step',
					['get', 'point_count'],
					clusterColor,
					100,
					clusterColor,
					750,
					clusterColor
				]);
				
				if (mapInstance.getLayer('cluster-count')) {
					mapInstance.setPaintProperty('cluster-count', 'text-color', textColor);
				}
			}
		} catch (e) {
			// Silent fail
		}
	}

	function applyMapThemeColorsFiltered(mapInstance, widgetStyling) {
		if (!widgetStyling || (!widgetStyling.waterColor && !widgetStyling.landColor && !widgetStyling.boundaryColor)) {
			return;
		}


		try {
			// Apply water color
			if (widgetStyling.waterColor) {
				var waterLayers = ['water', 'waterway', 'ocean', 'marine_label'];
				var waterColor = normalizeMapColor(widgetStyling.waterColor);
				waterLayers.forEach(function(layerId) {
					safeSetPaintProperty(mapInstance, layerId, 'fill-color', waterColor);
					safeSetPaintProperty(mapInstance, layerId, 'line-color', waterColor);
					safeSetPaintProperty(mapInstance, layerId, 'text-color', waterColor);
				});
			}

			// Apply land color
			if (widgetStyling.landColor) {
				var landLayers = ['land', 'landcover', 'background'];
				var landColor = normalizeMapColor(widgetStyling.landColor);
				landLayers.forEach(function(layerId) {
					safeSetPaintProperty(mapInstance, layerId, 'fill-color', landColor);
					safeSetPaintProperty(mapInstance, layerId, 'background-color', landColor);
				});
			}

			// Apply boundary color
			if (widgetStyling.boundaryColor) {
				var boundaryLayers = ['admin-0-boundary', 'admin-1-boundary', 'boundary', 'admin'];
				var boundaryColor = normalizeMapColor(widgetStyling.boundaryColor);
				boundaryLayers.forEach(function(layerId) {
					safeSetPaintProperty(mapInstance, layerId, 'line-color', boundaryColor);
				});
			}

		} catch (error) {
		}
	}

	function normalizeMapColor(color) {
		if (!color || typeof color !== 'string') {
			return color;
		}

		var hex8Match = color.match(/^#([A-Fa-f0-9]{8})$/);
		if (hex8Match) {
			return hex8ToRgba(hex8Match[0]);
		}

		return color;
	}

	function hex8ToRgba(hex) {
		var r = parseInt(hex.substr(1, 2), 16);
		var g = parseInt(hex.substr(3, 2), 16);
		var b = parseInt(hex.substr(5, 2), 16);
		var a = parseInt(hex.substr(7, 2), 16) / 255;
		return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + a.toFixed(3) + ')';
	}

	function safeSetPaintProperty(mapInstance, layerId, property, value) {
		if (!layerId || !property || typeof value === 'undefined' || value === null) {
			return;
		}

		if (!mapInstance.getLayer(layerId)) {
			return;
		}

		try {
			mapInstance.setPaintProperty(layerId, property, value);
		} catch (error) {
			// Some layers do not support certain properties; log at debug level.
		}
	}

})(jQuery);

