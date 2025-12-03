/**
 * Frontend Geometry Renderer
 * Renders Lines and Polygons on Mapbox maps
 */
(function($) {
	'use strict';


	// Global storage for map instances
	window.jetGeometryMaps = window.jetGeometryMaps || {};
	
	// Funkcja pomocnicza do normalizacji kolorów (używana wcześnie w kodzie)
	function normalizeColorEarly(color) {
		if (!color) return '#8c2f2b';
		if (typeof color === 'string') {
			// Jeśli kolor jest już w formacie hex, zwróć go
			if (color.match(/^#[0-9A-Fa-f]{6}$/)) {
				return color;
			}
			// Jeśli kolor jest w formacie rgb/rgba, przekonwertuj na hex
			if (color.match(/^rgba?\(/)) {
				var matches = color.match(/\d+/g);
				if (matches && matches.length >= 3) {
					var r = parseInt(matches[0]);
					var g = parseInt(matches[1]);
					var b = parseInt(matches[2]);
					return '#' + [r, g, b].map(function(x) {
						var hex = x.toString(16);
						return hex.length === 1 ? '0' + hex : hex;
					}).join('');
				}
			}
			return color;
		}
		return '#8c2f2b';
	}
	
	// Nie możemy nadpisać const JetMapboxMarkerClusterer, więc używamy przechwycenia addLayer
	// które jest już zaimplementowane poniżej w funkcji interceptClusterLayerCreation()
	
	// Przechwyć dodawanie warstw klastrów PRZED renderowaniem aby zastosować czerwone kolory od razu
	// Nadpisz map.addLayer dla wszystkich map Mapbox (backup method)
	function interceptClusterLayerCreation() {
		// Przechwyć wszystkie istniejące mapy
		$('.jet-map-listing').each(function() {
			var $container = $(this);
			var mapInstance = $container.data('mapInstance');
			
			if (mapInstance && typeof mapInstance.addLayer === 'function') {
				// Sprawdź czy już nadpisaliśmy addLayer dla tej mapy
				if (mapInstance._jetGeometryLayerIntercepted) {
					return;
				}
				
				mapInstance._jetGeometryLayerIntercepted = true;
				var originalAddLayer = mapInstance.addLayer.bind(mapInstance);
				
				// Nadpisz addLayer aby przechwycić dodawanie warstwy 'clusters'
				mapInstance.addLayer = function(layer, beforeId) {
					// Jeśli dodawana jest warstwa 'clusters', zmień jej kolory przed dodaniem
					if (layer && layer.id === 'clusters' && layer.paint && layer.paint['circle-color']) {
						var generalData = $container.attr('data-general');
						if (generalData) {
							try {
								var decoded = decodeURIComponent(generalData);
								var general = JSON.parse(decoded);
								if (general.geometryStyling && general.geometryStyling.clusterColor) {
									var clusterColor = normalizeColorEarly(general.geometryStyling.clusterColor);
									var textColor = normalizeColorEarly(general.geometryStyling.clusterTextColor || '#ffffff');
									
									// Zmień kolory PRZED dodaniem warstwy
									layer.paint['circle-color'] = [
										'step',
										['get', 'point_count'],
										clusterColor,
										100,
										clusterColor,
										750,
										clusterColor
									];
								}
							} catch (e) {
								// Silent fail
							}
						}
					}
					
					// Wywołaj oryginalną funkcję addLayer
					return originalAddLayer(layer, beforeId);
				};
			}
		});
		
		// Przechwyć nowe mapy przez event JetEngine
		$(document).on('jet-engine/maps-listing/init', function(e, mapInstance) {
			if (mapInstance && mapInstance.map && typeof mapInstance.map.addLayer === 'function') {
				var map = mapInstance.map;
				var $container = $(map.getContainer()).closest('.jet-map-listing');
				
				// Sprawdź czy już nadpisaliśmy addLayer dla tej mapy
				if (map._jetGeometryLayerIntercepted) {
					return;
				}
				
				map._jetGeometryLayerIntercepted = true;
				var originalAddLayer = map.addLayer.bind(map);
				
				// Nadpisz addLayer aby przechwycić dodawanie warstwy 'clusters'
				map.addLayer = function(layer, beforeId) {
					// Jeśli dodawana jest warstwa 'clusters', zmień jej kolory przed dodaniem
					if (layer && layer.id === 'clusters' && layer.paint && layer.paint['circle-color']) {
						var generalData = $container.attr('data-general');
						if (generalData) {
							try {
								var decoded = decodeURIComponent(generalData);
								var general = JSON.parse(decoded);
								if (general.geometryStyling && general.geometryStyling.clusterColor) {
									var clusterColor = normalizeColorEarly(general.geometryStyling.clusterColor);
									var textColor = normalizeColorEarly(general.geometryStyling.clusterTextColor || '#ffffff');
									
									// Zmień kolory PRZED dodaniem warstwy
									layer.paint['circle-color'] = [
										'step',
										['get', 'point_count'],
										clusterColor,
										100,
										clusterColor,
										750,
										clusterColor
									];
								}
							} catch (e) {
								// Silent fail
							}
						}
					}
					
					// Wywołaj oryginalną funkcję addLayer
					return originalAddLayer(layer, beforeId);
				};
			}
		});
	}
	
	// Wywołaj przechwycenie natychmiast i po załadowaniu DOM
	interceptClusterLayerCreation();
	$(document).ready(function() {
		interceptClusterLayerCreation();
	});

	// Configure preloaders per map container
	$(document).ready(function() {
		$('.jet-map-listing').each(function() {
			var $container = $(this);
			var generalData = $container.attr('data-general');
			var showPreloader = false; // Default to false - only show if explicitly enabled
			if (generalData) {
				try {
					// Safe decoding: try decodeURIComponent first, then DOM method
					var decoded = generalData;
					try {
						decoded = decodeURIComponent(generalData);
					} catch (e) {
						// If decodeURIComponent fails, use DOM method
						try {
							var tempDiv = document.createElement('div');
							tempDiv.innerHTML = generalData;
							decoded = tempDiv.textContent || tempDiv.innerText || generalData;
						} catch (e2) {
							decoded = generalData;
						}
					}
					var general = JSON.parse(decoded);
					if (general.geometryStyling) {
						if ( typeof general.geometryStyling.showPreloader !== 'undefined' ) {
							showPreloader = !! general.geometryStyling.showPreloader;
						}
						if ( general.geometryStyling.preloaderColor ) {
							$container.css('--jet-geometry-spinner-color', general.geometryStyling.preloaderColor);
						}
					}
				} catch (error) {
				}
			}

			if ( showPreloader ) {
				$container.addClass('loading');
			} else {
				$container.removeClass('loading');
				$container.addClass('jet-preloader-disabled');
			}

			$container.data('jetPreloaderEnabled', showPreloader);
		});
	});

	// Wait for markers to be rendered, then process geometries
	$(document).ready(function() {
		
		// Optimized: Use event-driven approach instead of MutationObserver
		var geometryRendererInitialized = false;
		
		// Listen for JetEngine Maps initialization
		$(document).on('jet-engine/maps-listing/init', function(e, mapInstance) {
			var $container = mapInstance.$container || $(mapInstance.map ? mapInstance.map.getContainer() : null).closest('.jet-map-listing');
			if ($container && $container.length) {
				var map = mapInstance.map || mapInstance;
				if (map && typeof map.on === 'function') {
					if (map.loaded()) {
						removePreloaderAfterMarkers($container, map);
					} else {
						map.once('load', function() {
							removePreloaderAfterMarkers($container, map);
						});
					}
				}
			}
		});
		
		// Listen for markers added event - optimized
		$(document).on('jet-engine/maps-listing/markers-added', function() {
			$('.jet-map-listing.loading').each(function() {
				var $container = $(this);
				if ($container.find('.mapboxgl-marker').length > 0 || 
				    (window.JetEngineMaps && window.JetEngineMaps.markersData && 
				     Object.keys(window.JetEngineMaps.markersData).length > 0)) {
					$container.removeClass('loading');
				}
			});
		});
		
		// Optimized: Reduced interval checks and faster fallback
		function removePreloaderAfterMarkers($container, map) {
			var checkCount = 0;
			var maxChecks = 10;
			var checkInterval = setInterval(function() {
				checkCount++;
				var hasMarkers = $container.find('.mapboxgl-marker').length > 0 ||
					(window.JetEngineMaps && window.JetEngineMaps.markersData && Object.keys(window.JetEngineMaps.markersData).length > 0);
				
				if ((map && typeof map.loaded === 'function' && map.loaded() && hasMarkers) || checkCount >= maxChecks) {
					clearInterval(checkInterval);
					$container.removeClass('loading');
				}
			}, 300);
			
			// Faster fallback: 2 seconds instead of 5
			setTimeout(function() {
				clearInterval(checkInterval);
				$container.removeClass('loading');
			}, 2000);
		}
		
		// Optimized: Single timeout instead of multiple
		setTimeout(function() {
			if (!$('.mapboxgl-marker').length) {
				return;
			}
			if (!geometryRendererInitialized) {
				geometryRendererInitialized = true;
				initGeometryRenderer();
			}
		}, 1000);
	});

	// Wait for JetEngine Maps to initialize
	$(window).on('jet-engine/frontend-maps/loaded', function() {
		initGeometryRenderer();
	});

	// Fallback if already loaded
	if (typeof window.JetEngineMaps !== 'undefined') {
		$(document).ready(function() {
			initGeometryRenderer();
		});
	}

	function initGeometryRenderer() {

		// Process all existing map listings
		$('.jet-map-listing').each(function() {
			var $container = $(this);
			var mapId = $container.attr('id');
			
			
			// Find canvas inside container
			var canvas = $container.find('canvas.mapboxgl-canvas')[0];
			
			if (!canvas) {
				return;
			}
			
			// Try to get map from canvas parent
			var canvasParent = canvas.parentElement;
			
			// Optimized: Try data approach first (fastest)
			var data = $container.data();
			if (data.mapInstance) {
				processGeometryData(data.mapInstance, $container);
				return;
			}
			
			// Fallback: Try to find map instance with reduced attempts
			var checkAttempts = 0;
			var maxAttempts = 5;
			var checkInterval = setInterval(function() {
				checkAttempts++;
				
				var map = canvasParent.mapbox || canvas.mapbox || $container[0].mapbox || (canvas._map ? canvas._map : null);
				
				if (map) {
					clearInterval(checkInterval);
					processGeometryData(map, $container);
				} else if (checkAttempts >= maxAttempts) {
					clearInterval(checkInterval);
					tryDataApproach($container);
				}
			}, 200);
		});
	}
	
	function tryDataApproach($container) {
		
		// We know the data is there from earlier tests
		var data = $container.data();
		
		// Check if there's a mapInstance in data
		if (data.mapInstance) {
			processGeometryData(data.mapInstance, $container);
		}
	}

function processGeometryData(mapboxMap, $container) {
	
	// Get markers data from container
	var markersData = $container.data('markers');
	if ((!markersData || !markersData.length) && $container.length) {
		var rawMarkersAttr = $container.attr('data-markers');
		if (rawMarkersAttr) {
			try {
				if (rawMarkersAttr === '[]') {
					// look for hidden data storage, e.g. data-jet-geometry-markers attribute
					var encodedMarkers = $container.attr('data-jet-geometry-markers') || '';
					if (!encodedMarkers) {
						var hidden = $container.find('.jet-geometry-markers-data');
						if (hidden.length) {
							encodedMarkers = hidden.text() || hidden.attr('data-value') || '';
						}
					}

					if (encodedMarkers) {
						var decodedHidden = encodedMarkers;
						try {
							decodedHidden = decodeURIComponent(encodedMarkers);
						} catch (e) {
							try {
								var tempDiv = document.createElement('div');
								tempDiv.innerHTML = encodedMarkers;
								decodedHidden = tempDiv.textContent || tempDiv.innerText || encodedMarkers;
							} catch (e2) {
								decodedHidden = encodedMarkers;
							}
						}
						var parsedHidden = JSON.parse(decodedHidden);
						if (parsedHidden && parsedHidden.length) {
							markersData = parsedHidden;
						}
					}
				}

				if (!markersData || !markersData.length) {
					var decodedAttr = rawMarkersAttr;
					try {
						decodedAttr = decodeURIComponent(rawMarkersAttr);
					} catch (e) {
						try {
							var tempDiv = document.createElement('div');
							tempDiv.innerHTML = rawMarkersAttr;
							decodedAttr = tempDiv.textContent || tempDiv.innerText || rawMarkersAttr;
						} catch (e2) {
							decodedAttr = rawMarkersAttr;
						}
					}
					var parsedMarkers = JSON.parse(decodedAttr);
					if (parsedMarkers && parsedMarkers.length) {
						markersData = parsedMarkers;
					}
				}
			} catch (error) {
			}
		}
	}

	if ((!markersData || !markersData.length) && window.JetEngineMaps && window.JetEngineMaps.markersData) {
		var combined = [];
		Object.keys(window.JetEngineMaps.markersData).forEach(function(key) {
			var items = window.JetEngineMaps.markersData[key];
			if (Array.isArray(items)) {
				items.forEach(function(item) {
					if (item && item.markerData) {
						combined.push(item.markerData);
					}
				});
			}
		});
		if (combined.length) {
			markersData = combined;
		}
	}
	
	if (!markersData || !Array.isArray(markersData)) {
		return;
	}

	// Get general settings (including geometryStyling)
	var generalSettings = $container.data('general');
	var styling = (generalSettings && generalSettings.geometryStyling) || {};
	var preloaderEnabled = true;
	if ( typeof styling.showPreloader !== 'undefined' ) {
		preloaderEnabled = !! styling.showPreloader;
	}
	var dataPreloader = $container.data('jetPreloaderEnabled');
	if ( typeof dataPreloader !== 'undefined' ) {
		preloaderEnabled = !! dataPreloader;
	}
	

	// Wait for map to be fully loaded
	if (mapboxMap.loaded()) {
		addGeometriesToMap(mapboxMap, markersData, styling, preloaderEnabled, $container);
	} else {
		mapboxMap.on('load', function() {
			addGeometriesToMap(mapboxMap, markersData, styling, preloaderEnabled, $container);
		});
	}
}

function addGeometriesToMap(mapboxMap, markersData, widgetStyling, preloaderEnabled, $container) {
		
		// Apply map theme colors
		applyMapThemeColors(mapboxMap, widgetStyling);
		
		// Get container to remove loading class later
		var containerElement = ( mapboxMap && typeof mapboxMap.getContainer === 'function' ) ? mapboxMap.getContainer() : null;
		var canvasElement = ( mapboxMap && typeof mapboxMap.getCanvas === 'function' ) ? mapboxMap.getCanvas() : null;
		var $mapContainer = $();

		if ( containerElement ) {
			$mapContainer = $(containerElement).closest('.jet-map-listing');
		}

		if ( ( ! $mapContainer || !$mapContainer.length ) && canvasElement ) {
			$mapContainer = $(canvasElement).closest('.jet-map-listing');
		}

		if ( ! $mapContainer || ! $mapContainer.length ) {
			$mapContainer = $container || $('.jet-map-listing');
		}
		
	// Ensure preloaderEnabled has a default value
		if ( typeof preloaderEnabled === 'undefined' ) {
			preloaderEnabled = true;
		}

	logMarkersDebugInfo(markersData, $mapContainer);

		markersData.forEach(function(marker, index) {
			if (!marker.geometry_data || !marker.geometry_data[0]) {
				return;
			}

			var geometry = marker.geometry_data[0];

			if (geometry.type === 'polygon') {
				addPolygon(mapboxMap, geometry, marker, index, widgetStyling, $mapContainer);
				// Optimized: Reduced delay for hiding marker
				setTimeout(function() {
					hideMarkerForPost(marker.id);
				}, 300);
			} else if (geometry.type === 'line') {
				addLine(mapboxMap, geometry, marker, index, widgetStyling, $mapContainer);
				// Optimized: Reduced delay for hiding marker
				setTimeout(function() {
					hideMarkerForPost(marker.id);
				}, 300);
			} else if (geometry.type === 'pin' || geometry.type === 'point') {
				// Pins/points are handled by default JetEngine - they should be visible as markers
				// Don't hide the marker - let it be visible
			} else {
			}
		});
		
		// Apply cluster styling if provided
		if (widgetStyling && widgetStyling.clusterColor) {
			applyClusterStyling(widgetStyling.clusterColor, widgetStyling.clusterTextColor);
		}
		
		// Setup geometry click handlers after all layers are added
		// Use map 'idle' event to ensure layers are fully rendered
		if (window.JetCountryLayers && window.JetCountryLayers.setupGeometryClickHandlers) {
			var setupHandlers = function() {
				var mapId = $mapContainer && $mapContainer.length ? $mapContainer.attr('id') : null;
				if (mapId && mapboxMap) {
					window.JetCountryLayers.setupGeometryClickHandlers(mapId, mapboxMap, $mapContainer);
				}
			};
			
			if (mapboxMap && mapboxMap.loaded && mapboxMap.loaded()) {
				// Map is already loaded, setup handlers after a short delay
				setTimeout(setupHandlers, 200);
			} else if (mapboxMap) {
				// Wait for map to load
				mapboxMap.once('idle', function() {
					setTimeout(setupHandlers, 200);
				});
			}
		}
		
		var clearLoadingState = function() {
			if ( $mapContainer && $mapContainer.length ) {
				$mapContainer.removeClass('loading');
			} else {
				$('.jet-map-listing').removeClass('loading');
			}
		};

		if ( preloaderEnabled ) {
			// Remove preloader after geometries are added and map is ready
			// Check if map is loaded and has markers/geometries
			var checkAndRemovePreloader = function() {
				var mapReady = mapboxMap && typeof mapboxMap.loaded === 'function' && mapboxMap.loaded();
				var hasMarkers = $mapContainer.find('.mapboxgl-marker').length > 0;
				var hasGeometries = markersData && markersData.length > 0;
				
				if (mapReady && (hasMarkers || hasGeometries)) {
					clearLoadingState();
					return true;
				}
				return false;
			};
			
			// Try immediately
			if (checkAndRemovePreloader()) {
				return;
			}
			
			// Try after a short delay
			setTimeout(function() {
				if (checkAndRemovePreloader()) {
					return;
				}
				
				// Fallback: remove after max 3 seconds
				setTimeout(function() {
					clearLoadingState();
				}, 2000);
			}, 500);
		} else {
			clearLoadingState();
		}
	}
	
	
	function applyClusterStyling(clusterColor, textColor) {
		
		textColor = textColor || '#ffffff';
		clusterColor = normalizeColor(clusterColor);
		
		// Funkcja do zastosowania stylów klastrów
		var applyStyleToMap = function(mapInstance) {
			if (!mapInstance || !mapInstance.getLayer) {
				return false;
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
					return true;
				}
			} catch (e) {
				// Silent fail
			}
			return false;
		};
		
		// Funkcja do zastosowania stylów dla wszystkich map z danym kolorem
		var applyToAllMaps = function() {
			$('.jet-map-listing').each(function() {
				var $container = $(this);
				var mapInstance = $container.data('mapInstance');
				if (mapInstance) {
					applyStyleToMap(mapInstance);
				}
			});
		};
		
		// Przechwyć wszystkie istniejące mapy
		$('.jet-map-listing').each(function() {
			var $container = $(this);
			var mapInstance = $container.data('mapInstance');
			
			if (mapInstance) {
				// Sprawdź czy już dodaliśmy listenery dla tej mapy
				if (mapInstance._jetGeometryClusterStylingApplied) {
					return;
				}
				mapInstance._jetGeometryClusterStylingApplied = true;
				
				// Jeśli mapa jest już załadowana, zastosuj style natychmiast
				if (mapInstance.loaded && mapInstance.loaded()) {
					// Warstwy mogą być już utworzone
					if (!applyStyleToMap(mapInstance)) {
						// Jeśli warstwy nie są jeszcze gotowe, poczekaj na 'idle'
						mapInstance.once('idle', function() {
							applyStyleToMap(mapInstance);
						});
					}
				} else {
					// Przechwyć 'load' i zastosuj style natychmiast po utworzeniu warstw
					mapInstance.once('load', function() {
						// Przechwyć 'idle' który jest wywoływany po pełnym załadowaniu wszystkich warstw
						mapInstance.once('idle', function() {
							applyStyleToMap(mapInstance);
						});
						
						// Dodatkowo, spróbuj zastosować style natychmiast po 'load' (warstwy mogą być już utworzone)
						requestAnimationFrame(function() {
							applyStyleToMap(mapInstance);
						});
					});
				}
				
				// Dodaj listener na 'idle' aby ponownie zastosować style po każdej zmianie (np. po toggle country layers)
				mapInstance.on('idle', function() {
					// Sprawdź czy warstwa clusters istnieje i jest widoczna
					if (mapInstance.getLayer('clusters')) {
						var layer = mapInstance.getStyle().layers.find(function(l) { return l.id === 'clusters'; });
						if (layer && layer.layout && layer.layout.visibility !== 'none') {
							applyStyleToMap(mapInstance);
						}
					}
				});
			}
		});
		
		// Przechwyć nowe mapy przez event JetEngine
		$(document).on('jet-engine/maps-listing/init', function(e, mapInstance) {
			if (mapInstance && mapInstance.map) {
				var map = mapInstance.map;
				
				// Sprawdź czy już dodaliśmy listenery dla tej mapy
				if (map._jetGeometryClusterStylingApplied) {
					return;
				}
				map._jetGeometryClusterStylingApplied = true;
				
				// Przechwyć 'load' i zastosuj style natychmiast po utworzeniu warstw
				map.once('load', function() {
					// Przechwyć 'idle' który jest wywoływany po pełnym załadowaniu wszystkich warstw
					map.once('idle', function() {
						applyStyleToMap(map);
					});
					
					// Dodatkowo, spróbuj zastosować style natychmiast po 'load' (warstwy mogą być już utworzone)
					requestAnimationFrame(function() {
						applyStyleToMap(map);
					});
				});
				
				// Dodaj listener na 'idle' aby ponownie zastosować style po każdej zmianie (np. po toggle country layers)
				map.on('idle', function() {
					// Sprawdź czy warstwa clusters istnieje i jest widoczna
					if (map.getLayer('clusters')) {
						try {
							var style = map.getStyle();
							if (style && style.layers) {
								var layer = style.layers.find(function(l) { return l.id === 'clusters'; });
								if (layer && layer.layout && layer.layout.visibility !== 'none') {
									applyStyleToMap(map);
								}
							}
						} catch (e) {
							// Fallback: po prostu zastosuj style
							applyStyleToMap(map);
						}
					}
				});
			}
		});
		
		// Przechwyć zmiany widoczności warstw przez country-layers toggle
		$(document).on('jet-country-layers/toggle', function() {
			// Opóźnienie aby upewnić się, że warstwy są już zaktualizowane
			setTimeout(function() {
				applyToAllMaps();
			}, 100);
		});
		
		// Fallback: retry po krótkim czasie dla map które mogą być już załadowane
		setTimeout(function() {
			applyToAllMaps();
		}, 100);
	}
	
	function hideMarkerForPost(postId) {
		
		// Try JetEngine's markersData - this is the correct way
		if (window.JetEngineMaps && window.JetEngineMaps.markersData && window.JetEngineMaps.markersData[postId]) {
			var markersArray = window.JetEngineMaps.markersData[postId];
			
			markersArray.forEach(function(markerObj) {
				// Structure is {marker: MarkerObject, clustererIndex: "xxx"}
				var marker = markerObj.marker || markerObj;
			var element = null;

			if (marker) {
				if (marker._element) {
					element = marker._element;
				} else if (typeof marker.getElement === 'function') {
					element = marker.getElement();
				}
			}

			if (element) {
				if (!element.dataset.jetGeometryOriginalDisplay) {
					var computed = '';
					try {
						computed = window.getComputedStyle(element).display || '';
					} catch (error) {}
					element.dataset.jetGeometryOriginalDisplay = element.style.display || computed || '';
				}

				element.style.display = 'none';
			} else if (marker) {
			}
			});
		} else {
		}
	}

	function addPolygon(mapboxMap, geometry, marker, index, widgetStyling, $container) {

		try {
			var geoJson = JSON.parse(geometry.data);
			var sourceId = 'geometry-polygon-' + marker.id + '-' + index;
			var layerId = sourceId + '-layer';
			var outlineId = sourceId + '-outline';
			
			// Priority: widgetStyling > geometry.styling > global defaults
			var fillColorRaw = (widgetStyling && widgetStyling.polygonFillColor) || 
			                   (geometry.styling && geometry.styling.polygonFillColor) || 
			                   (window.jetGeometrySettings && window.jetGeometrySettings.polygonFillColor) || '#ff0000';
			var fillOpacity = (widgetStyling && widgetStyling.fillOpacity) || 
			                  (geometry.styling && geometry.styling.fillOpacity) || 
			                  (window.jetGeometrySettings && window.jetGeometrySettings.fillOpacity) || 0.3;
			var lineColorRaw = (widgetStyling && widgetStyling.lineColor) || 
			                   (geometry.styling && geometry.styling.lineColor) || 
			                   (window.jetGeometrySettings && window.jetGeometrySettings.lineColor) || '#ff0000';
			
			// Convert hex colors with alpha to rgba format for Mapbox
			var fillColor = convertHexToRgba(fillColorRaw, fillOpacity);
			var lineColor = convertHexToRgba(lineColorRaw, 1.0);
			var lineWidth = (widgetStyling && widgetStyling.lineWidth) || 
			                (geometry.styling && geometry.styling.lineWidth) || 
			                (window.jetGeometrySettings && window.jetGeometrySettings.lineWidth) || 2;
			

			// Add source
			if (!mapboxMap.getSource(sourceId)) {
				// Ensure GeoJSON has properties with post ID
				if (geoJson.type === 'Feature' && !geoJson.properties) {
					geoJson.properties = {};
				}
				if (geoJson.type === 'Feature' && !geoJson.properties.id) {
					geoJson.properties.id = marker.id;
					geoJson.properties.post_id = marker.id;
				}
				if (geoJson.type === 'FeatureCollection' && geoJson.features) {
					geoJson.features.forEach(function(feature) {
						if (!feature.properties) {
							feature.properties = {};
						}
						if (!feature.properties.id) {
							feature.properties.id = marker.id;
							feature.properties.post_id = marker.id;
						}
					});
				}
				
				mapboxMap.addSource(sourceId, {
					type: 'geojson',
					data: geoJson
				});

				// Add fill layer
				mapboxMap.addLayer({
					id: layerId,
					type: 'fill',
					source: sourceId,
					paint: {
						'fill-color': fillColor,
						'fill-opacity': parseFloat(fillOpacity)
					}
				});

				// Add outline layer
				mapboxMap.addLayer({
					id: outlineId,
					type: 'line',
					source: sourceId,
					paint: {
						'line-color': lineColor,
						'line-width': parseInt(lineWidth)
					}
				});

				if (window.JetCountryLayers && window.JetCountryLayers.countryToggleState) {
					try {
						mapboxMap.setLayoutProperty(layerId, 'visibility', 'none');
						mapboxMap.setLayoutProperty(outlineId, 'visibility', 'none');
					} catch (error) {
						// ignore
					}
				}

			}
		} catch (error) {
		}
	}

	function addLine(mapboxMap, geometry, marker, index, widgetStyling, $container) {

		try {
			var geoJson = JSON.parse(geometry.data);
			var sourceId = 'geometry-line-' + marker.id + '-' + index;
			var layerId = sourceId + '-layer';
			
			// Get styling
			var lineColorRaw = (widgetStyling && widgetStyling.lineColor) || 
			                   (geometry.styling && geometry.styling.lineColor) || 
			                   (window.jetGeometrySettings && window.jetGeometrySettings.lineColor) || '#ff0000';
			var lineColor = convertHexToRgba(lineColorRaw, 1.0);
			var lineWidth = (widgetStyling && widgetStyling.lineWidth) || 
			                (geometry.styling && geometry.styling.lineWidth) || 
			                (window.jetGeometrySettings && window.jetGeometrySettings.lineWidth) || 3;

			// Add source
			if (!mapboxMap.getSource(sourceId)) {
				// Ensure GeoJSON has properties with post ID
				if (geoJson.type === 'Feature' && !geoJson.properties) {
					geoJson.properties = {};
				}
				if (geoJson.type === 'Feature' && !geoJson.properties.id) {
					geoJson.properties.id = marker.id;
					geoJson.properties.post_id = marker.id;
				}
				if (geoJson.type === 'FeatureCollection' && geoJson.features) {
					geoJson.features.forEach(function(feature) {
						if (!feature.properties) {
							feature.properties = {};
						}
						if (!feature.properties.id) {
							feature.properties.id = marker.id;
							feature.properties.post_id = marker.id;
						}
					});
				}
				
				mapboxMap.addSource(sourceId, {
					type: 'geojson',
					data: geoJson
				});

				// Add line layer
				mapboxMap.addLayer({
					id: layerId,
					type: 'line',
					source: sourceId,
					paint: {
						'line-color': lineColor,
						'line-width': parseInt(lineWidth)
					}
				});

				if (window.JetCountryLayers && window.JetCountryLayers.countryToggleState) {
					try {
						mapboxMap.setLayoutProperty(layerId, 'visibility', 'none');
					} catch (error) {
						// ignore
					}
				}

			}
		} catch (error) {
		}
	}

	function logMarkersDebugInfo(markersData, $mapContainer) {
		if ( ! window.jetGeometrySettings || !window.jetGeometrySettings.debugLogging ) {
			return;
		}

		var stats = summarizeMarkersByCountry(markersData);
		
		// Check incident_geometry field statistics
		var incidentGeometryStats = checkIncidentGeometry(markersData);

		// Debug logging disabled for performance - enable via window.jetGeometrySettings.debugLogging = true
		if ( window.jetGeometrySettings && window.jetGeometrySettings.debugLogging ) {
			try {
				console.group('[JetGeometry] Incidents on map: ' + stats.total);
				console.log('Countries breakdown:', stats.perCountry);
				console.log('Missing country taxonomy:', stats.missing);
				console.log('Map container:', $mapContainer && $mapContainer.attr ? $mapContainer.attr('id') : 'n/a');
				
				// Also log breakdown in a more readable format
				if ( Object.keys(stats.perCountry).length > 0 ) {
					console.log('Breakdown by country:');
					Object.keys(stats.perCountry).forEach(function(country) {
						console.log('  - ' + country + ': ' + stats.perCountry[country]);
					});
				}
				
				// Log incident_geometry statistics
				console.log('');
				console.log('=== Incident Geometry Statistics (Map Markers) ===');
				console.log('Total markers on map: ' + stats.total);
				console.log('Markers with valid incident_geometry: ' + incidentGeometryStats.withGeometry);
				console.log('Markers without incident_geometry: ' + incidentGeometryStats.withoutGeometry);
				console.log('Percentage with geometry: ' + (stats.total > 0 ? ((incidentGeometryStats.withGeometry / stats.total) * 100).toFixed(2) : 0) + '%');
				
				if (incidentGeometryStats.invalidGeometry > 0) {
					console.log('Markers with invalid incident_geometry: ' + incidentGeometryStats.invalidGeometry);
				}
				
				// Fetch and display statistics for all posts in database
				if (window.jetGeometrySettings && window.jetGeometrySettings.restUrl) {
					fetch(window.jetGeometrySettings.restUrl + 'incident-geometry-stats', {
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.jetGeometrySettings.restNonce || ''
						},
						credentials: 'same-origin'
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						console.log('');
						console.log('=== Incident Geometry Statistics (All Posts in Database) ===');
						console.log('Total posts: ' + data.total);
						console.log('Posts with valid incident_geometry: ' + data.with_geometry + ' (' + data.with_geometry_percent + '%)');
						console.log('Posts without incident_geometry: ' + data.without_geometry + ' (' + data.without_geometry_percent + '%)');
						if (data.invalid_geometry > 0) {
							console.log('Posts with invalid incident_geometry: ' + data.invalid_geometry);
						}
						console.log('Posts with hash-prefixed fields (e.g., 4e3d8c7b47e499ebe6983d9555fa1bb8_lat): ' + data.with_hash_fields + ' (' + data.with_hash_fields_percent + '%)');
						console.log('Posts without hash-prefixed fields: ' + data.without_hash_fields);
						console.log('');
						console.log('Comparison:');
						console.log('  - Markers on map: ' + stats.total);
						console.log('  - Posts with geometry in DB: ' + data.with_geometry);
						console.log('  - Difference: ' + (data.with_geometry - stats.total) + ' posts with geometry are not showing on map');
					})
					.catch(function(error) {
						console.error('[JetGeometry] Error fetching incident geometry stats:', error);
					});
				}
				
				console.groupEnd();
			} catch (error) {
				console.error('[JetGeometry] Error logging stats:', error);
			}
		}

		if ( window.jetGeometrySettings.restUrl ) {
			var payload = {
				total: stats.total,
				breakdown: stats.perCountry,
				missing: stats.missing,
				page: window.location.href,
				mapId: ($mapContainer && $mapContainer.length) ? $mapContainer.attr('id') : ''
			};

			var headers = {
				'Content-Type': 'application/json'
			};

			if ( window.jetGeometrySettings.restNonce ) {
				headers['X-WP-Nonce'] = window.jetGeometrySettings.restNonce;
			}

			fetch(window.jetGeometrySettings.restUrl + 'markers-debug', {
				method: 'POST',
				headers: headers,
				credentials: 'same-origin',
				body: JSON.stringify(payload)
			}).catch(function() {
				// ignore
			});
		}
	}

	function summarizeMarkersByCountry(markersData) {
		var taxonomyKey = (window.jetGeometrySettings && window.jetGeometrySettings.taxonomyKey) || 'countries';
		var stats = {
			total: 0,
			missing: 0,
			perCountry: {}
		};

		markersData.forEach(function(marker) {
			stats.total++;

			var countries = extractCountries(marker, taxonomyKey);

			if ( ! countries.length ) {
				stats.missing++;
				countries = ['(no country)'];
			}

			countries.forEach(function(country) {
				if ( ! country ) {
					return;
				}
				if ( ! stats.perCountry[ country ] ) {
					stats.perCountry[ country ] = 0;
				}
				stats.perCountry[ country ]++;
			});
		});

		return stats;
	}

	/**
	 * Check incident_geometry field statistics for markers
	 * 
	 * @param {Array} markersData Array of marker objects
	 * @return {Object} Statistics object with counts
	 */
	function checkIncidentGeometry(markersData) {
		var stats = {
			withGeometry: 0,
			withoutGeometry: 0,
			invalidGeometry: 0
		};

		markersData.forEach(function(marker) {
			// Check if marker has incident_geometry field
			// It might be in marker.incident_geometry or we need to check via AJAX
			// For now, we'll check what's available in the marker object
			
			var hasGeometry = false;
			var geometryValid = false;
			
			// Check direct property
			if (marker.incident_geometry) {
				hasGeometry = true;
				try {
					var geom = typeof marker.incident_geometry === 'string' 
						? JSON.parse(marker.incident_geometry) 
						: marker.incident_geometry;
					
					if (geom && geom.type && geom.coordinates && Array.isArray(geom.coordinates)) {
						geometryValid = true;
					}
				} catch (e) {
					// Invalid JSON
				}
			}
			
			// Check geometry_data (from our PHP filter)
			if (!hasGeometry && marker.geometry_data && Array.isArray(marker.geometry_data) && marker.geometry_data.length > 0) {
				hasGeometry = true;
				geometryValid = true; // Assume valid if it's in geometry_data
			}
			
			if (hasGeometry) {
				if (geometryValid) {
					stats.withGeometry++;
				} else {
					stats.invalidGeometry++;
				}
			} else {
				stats.withoutGeometry++;
			}
		});

		return stats;
	}

	function extractCountries(marker, taxonomyKey) {
		var names = [];

		// Debug: log marker structure to understand how JetEngine stores taxonomy data
		if ( window.jetGeometrySettings && window.jetGeometrySettings.debugLogging ) {
			console.log('[JetGeometry Debug] Marker structure:', {
				id: marker.id,
				hasTaxonomies: !!marker.taxonomies,
				hasCountries: !!marker.countries,
				taxonomies: marker.taxonomies,
				countries: marker.countries,
				hasTaxonomy: !!marker.taxonomy,
				hasTerms: !!marker.terms,
				hasListing: !!marker.listing,
				allKeys: Object.keys(marker)
			});
		}

		var potentialSources = [];

		// Priority 1: Direct countries array (added by our PHP filter)
		if ( marker.countries && Array.isArray(marker.countries) ) {
			if ( window.jetGeometrySettings && window.jetGeometrySettings.debugLogging ) {
				console.log('[JetGeometry Debug] Processing marker.countries:', marker.countries);
			}
			marker.countries.forEach(function(country) {
				if ( country && country.name ) {
					names.push(country.name);
					if ( window.jetGeometrySettings && window.jetGeometrySettings.debugLogging ) {
						console.log('[JetGeometry Debug] Added country from marker.countries:', country.name);
					}
				}
			});
		} else if ( marker.taxonomies && marker.taxonomies[taxonomyKey] ) {
			// Priority 2: Taxonomies object with countries key (only if countries array not found)
			var countries = marker.taxonomies[taxonomyKey];
			if ( window.jetGeometrySettings && window.jetGeometrySettings.debugLogging ) {
				console.log('[JetGeometry Debug] Processing marker.taxonomies[' + taxonomyKey + ']:', countries);
			}
			if ( Array.isArray(countries) ) {
				countries.forEach(function(country) {
					if ( country && country.name ) {
						names.push(country.name);
						if ( window.jetGeometrySettings && window.jetGeometrySettings.debugLogging ) {
							console.log('[JetGeometry Debug] Added country from marker.taxonomies:', country.name);
						}
					}
				});
			}
		}

		if ( window.jetGeometrySettings && window.jetGeometrySettings.debugLogging ) {
			console.log('[JetGeometry Debug] Extracted countries for marker ' + marker.id + ':', names);
		}

		// Fallback: Check other possible locations
		if ( marker.taxonomies ) {
			potentialSources.push(marker.taxonomies);
		}
		if ( marker.taxonomy ) {
			potentialSources.push(marker.taxonomy);
		}
		if ( marker.terms ) {
			potentialSources.push(marker.terms);
		}
		if ( marker.term_list ) {
			potentialSources.push(marker.term_list);
		}
		if ( marker.context ) {
			potentialSources.push(marker.context);
		}
		if ( marker.meta ) {
			potentialSources.push(marker.meta);
		}

		if ( marker.listing && marker.listing.taxonomies ) {
			potentialSources.push(marker.listing.taxonomies);
		}

		// JetEngine may store taxonomies directly on marker with taxonomy name as key
		if ( marker[taxonomyKey] ) {
			potentialSources.push(marker[taxonomyKey]);
		}

		// Check if marker has a listing object with taxonomy data
		if ( marker.listing ) {
			if ( marker.listing[taxonomyKey] ) {
				potentialSources.push(marker.listing[taxonomyKey]);
			}
			// JetEngine might store as listing.taxonomies[taxonomyKey]
			if ( marker.listing.taxonomies && marker.listing.taxonomies[taxonomyKey] ) {
				potentialSources.push(marker.listing.taxonomies[taxonomyKey]);
			}
		}

		// Only process fallback sources if we didn't find countries in priority locations
		if ( names.length === 0 ) {
			potentialSources.forEach(function(source) {
				collectCountryNames(source, taxonomyKey, names);
			});
		}

		// Deduplicate
		var unique = [];
		var seen = {};
		names.forEach(function(name) {
			if ( ! name ) {
				return;
			}
			var key = name.toLowerCase();
			if ( ! seen[key] ) {
				unique.push(name);
				seen[key] = true;
			}
		});

		return unique;
	}

	function collectCountryNames(source, taxonomyKey, target) {
		if ( ! source ) {
			return;
		}

		if ( Array.isArray(source) ) {
			source.forEach(function(item) {
				collectCountryNames(item, taxonomyKey, target);
			});
			return;
		}

		if ( typeof source === 'string' ) {
			target.push(source);
			return;
		}

		if ( typeof source !== 'object' ) {
			return;
		}

		// Direct taxonomy key
		if ( source[taxonomyKey] ) {
			collectCountryNames(source[taxonomyKey], taxonomyKey, target);
			return;
		}

		// JetEngine term object - check multiple possible properties
		if ( source.name && typeof source.name === 'string' ) {
			target.push(source.name);
			return;
		}
		if ( source.title && typeof source.title === 'string' ) {
			target.push(source.title);
			return;
		}
		if ( source.label && typeof source.label === 'string' ) {
			target.push(source.label);
			return;
		}

		// Check if object has term-like structure (name + slug or just name)
		if ( source.slug && source.name ) {
			target.push(source.name);
			return;
		}

		// Recursively search for country-related keys
		Object.keys(source).forEach(function(key) {
			if ( key === taxonomyKey || key.toLowerCase().indexOf('country') !== -1 ) {
				collectCountryNames(source[key], taxonomyKey, target);
			}
		});
	}

	/**
	 * Apply custom theme colors to Mapbox map layers
	 */
	function applyMapThemeColors(mapboxMap, widgetStyling) {
		if (!widgetStyling || (!widgetStyling.waterColor && !widgetStyling.landColor && !widgetStyling.boundaryColor)) {
			return;
		}


		// Wait for map style to load
		var applyColors = function() {
			try {
				// Apply water color
				if (widgetStyling.waterColor) {
					// Try different water layer names
					var waterLayers = ['water', 'waterway', 'ocean', 'marine_label'];
					waterLayers.forEach(function(layerId) {
						if (mapboxMap.getLayer(layerId)) {
							setLayerColor(mapboxMap, layerId, widgetStyling.waterColor);
						}
					});
				}

				// Apply land color
				if (widgetStyling.landColor) {
					// Try different land layer names
					var landLayers = ['land', 'landcover', 'background'];
					landLayers.forEach(function(layerId) {
						if (mapboxMap.getLayer(layerId)) {
							setLayerColor(mapboxMap, layerId, widgetStyling.landColor);
						}
					});
				}

				// Apply boundary color
				if (widgetStyling.boundaryColor) {
					// Try different boundary layer names
					var boundaryLayers = ['admin-0-boundary', 'admin-1-boundary', 'boundary', 'admin'];
					boundaryLayers.forEach(function(layerId) {
						if (mapboxMap.getLayer(layerId)) {
							setLayerColor(mapboxMap, layerId, widgetStyling.boundaryColor);
						}
					});
				}

			} catch (error) {
			}
		};

		// Apply immediately if loaded
		if (mapboxMap.loaded() && mapboxMap.isStyleLoaded()) {
			applyColors();
		} else {
			// Wait for style to load
			mapboxMap.on('style.load', applyColors);
			// Also try on idle as backup
			mapboxMap.on('idle', function onIdle() {
				applyColors();
				mapboxMap.off('idle', onIdle);
			});
		}
	}

	function normalizeColor(color) {
		if (!color) {
			return color;
		}

		var hexMatch = /^#([0-9a-f]{8})$/i.exec(color);

		if (hexMatch) {
			var hex = hexMatch[1];
			var r = parseInt(hex.slice(0, 2), 16);
			var g = parseInt(hex.slice(2, 4), 16);
			var b = parseInt(hex.slice(4, 6), 16);
			var a = parseInt(hex.slice(6, 8), 16) / 255;
			return 'rgba(' + r + ',' + g + ',' + b + ',' + a.toFixed(3) + ')';
		}

		return color;
	}
	
	/**
	 * Convert hex color to rgba format for Mapbox
	 * Handles both 6-digit (#RRGGBB) and 8-digit (#RRGGBBAA) hex colors
	 */
	function convertHexToRgba(hexColor, opacity) {
		if (!hexColor) {
			return hexColor;
		}
		
		// If already rgba or rgb, return as is
		if (hexColor.indexOf('rgba') === 0 || hexColor.indexOf('rgb') === 0) {
			return hexColor;
		}
		
		// Remove # if present
		hexColor = hexColor.replace('#', '');
		
		// Handle 8-digit hex (with alpha)
		if (hexColor.length === 8) {
			var r = parseInt(hexColor.slice(0, 2), 16);
			var g = parseInt(hexColor.slice(2, 4), 16);
			var b = parseInt(hexColor.slice(4, 6), 16);
			var a = parseInt(hexColor.slice(6, 8), 16) / 255;
			// Use provided opacity if given, otherwise use alpha from hex
			if (typeof opacity !== 'undefined') {
				a = opacity;
			}
			return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
		}
		
		// Handle 6-digit hex (without alpha)
		if (hexColor.length === 6) {
			var r = parseInt(hexColor.slice(0, 2), 16);
			var g = parseInt(hexColor.slice(2, 4), 16);
			var b = parseInt(hexColor.slice(4, 6), 16);
			var a = (typeof opacity !== 'undefined') ? opacity : 1.0;
			return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
		}
		
		// Handle 3-digit hex (short form)
		if (hexColor.length === 3) {
			var r = parseInt(hexColor[0] + hexColor[0], 16);
			var g = parseInt(hexColor[1] + hexColor[1], 16);
			var b = parseInt(hexColor[2] + hexColor[2], 16);
			var a = (typeof opacity !== 'undefined') ? opacity : 1.0;
			return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
		}
		
		// If we can't parse it, return original color
		return hexColor;
	}

	function setLayerColor(map, layerId, color) {
		var layer = map.getLayer(layerId);

		if (!layer) {
			return;
		}

		var layerType = layer.type;
		var normalized = normalizeColor(color);

		try {
			switch (layerType) {
				case 'fill':
					map.setPaintProperty(layerId, 'fill-color', normalized);
					break;
				case 'line':
					map.setPaintProperty(layerId, 'line-color', normalized);
					break;
				case 'background':
					map.setPaintProperty(layerId, 'background-color', normalized);
					break;
				case 'fill-extrusion':
					map.setPaintProperty(layerId, 'fill-extrusion-color', normalized);
					break;
				case 'symbol':
					map.setPaintProperty(layerId, 'text-color', normalized);
					break;
				default:
					// no-op
					break;
			}
		} catch (error) {
		}
	}

})(jQuery);

