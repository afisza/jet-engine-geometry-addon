/**
 * Chunk Loading for Map Markers
 * Loads markers progressively in chunks for better performance
 */
(function($) {
	'use strict';

	var ChunkLoader = {
		enabled: false,
		chunkSize: 20,
		chunkDelay: 50,
		loadedChunks: {},
		
		init: function() {
			// Get settings from localized script (will be added in PHP)
			if (typeof window.jetGeometryChunkSettings !== 'undefined') {
				this.enabled = window.jetGeometryChunkSettings.enabled || false;
				this.chunkSize = parseInt(window.jetGeometryChunkSettings.chunkSize, 10) || 20;
				this.chunkDelay = parseInt(window.jetGeometryChunkSettings.chunkDelay, 10) || 50;
			}
			
			// Always log initialization for debugging
			console.log('[JetGeometry ChunkLoader] Initialized:', {
				enabled: this.enabled,
				chunkSize: this.chunkSize,
				chunkDelay: this.chunkDelay,
				hasSettings: typeof window.jetGeometryChunkSettings !== 'undefined'
			});
			
			if (!this.enabled) {
				console.log('[JetGeometry ChunkLoader] Chunk loading is disabled');
				return; // Chunk loading disabled
			}
			
			// Intercept map initialization
			$(document).on('jet-engine/maps-listing/init', function(e, mapInstance) {
				console.log('[JetGeometry ChunkLoader] Map init event received:', {
					hasMapInstance: !!mapInstance,
					hasContainer: !!(mapInstance && mapInstance.$container),
					mapInstanceKeys: mapInstance ? Object.keys(mapInstance) : []
				});
				// Try to get $container from mapInstance or find it
				var $container = null;
				if (mapInstance && mapInstance.$container) {
					$container = mapInstance.$container;
				} else if (mapInstance && mapInstance.map) {
					// Try to find container from map element
					var mapElement = mapInstance.map.getContainer ? mapInstance.map.getContainer() : null;
					if (mapElement) {
						$container = $(mapElement).closest('.jet-map-listing');
					}
				}
				ChunkLoader.handleMapInit(mapInstance, $container);
			});
			
			// Also handle existing maps - wait longer to ensure data-all-markers is injected
			$(window).on('load', function() {
				setTimeout(function() {
					console.log('[JetGeometry ChunkLoader] Handling existing maps (after window load)');
					ChunkLoader.handleExistingMaps();
				}, 1000); // Increased delay to ensure data-all-markers is injected
			});
			
			// Also try on DOM ready (in case window load already fired)
			$(document).ready(function() {
				setTimeout(function() {
					console.log('[JetGeometry ChunkLoader] Handling existing maps (after DOM ready)');
					ChunkLoader.handleExistingMaps();
				}, 1500); // Even longer delay to ensure everything is ready
			});
		},
		
		handleMapInit: function(mapInstance, $containerParam) {
			// $containerParam is passed from handleExistingMaps, or use mapInstance.$container
			var $container = $containerParam || (mapInstance && mapInstance.$container ? mapInstance.$container : null);
			
			console.log('[JetGeometry ChunkLoader] handleMapInit called:', {
				hasMapInstance: !!mapInstance,
				hasContainerParam: !!$containerParam,
				hasContainerFromInstance: !!(mapInstance && mapInstance.$container),
				hasContainer: !!$container
			});
			
			if (!mapInstance || !$container || $container.length === 0) {
				console.warn('[JetGeometry ChunkLoader] handleMapInit: mapInstance or $container missing', {
					hasMapInstance: !!mapInstance,
					hasContainer: !!$container,
					containerLength: $container ? $container.length : 0
				});
				return;
			}
			
			var self = this;
			var mapId = $container.attr('id') || 'map-' + Date.now();
			
			console.log('[JetGeometry ChunkLoader] Processing map:', {
				mapId: mapId,
				hasDataAllMarkers: !!$container.attr('data-all-markers')
			});
			
			// Check if already initialized for this map
			if (self.loadedChunks[mapId]) {
				console.log('[JetGeometry ChunkLoader] Map already initialized:', mapId);
				return; // Already initialized
			}
			
			// Wait a bit for data-all-markers to be injected
			var attempts = 0;
			var maxAttempts = 20; // Increased attempts
			
			function checkAndInit() {
				// Get ALL markers from data-all-markers attribute (set by PHP)
				var allMarkersAttr = $container.attr('data-all-markers');
				
				console.log('[JetGeometry ChunkLoader] checkAndInit attempt:', {
					mapId: mapId,
					attempt: attempts + 1,
					maxAttempts: maxAttempts,
					hasDataAllMarkers: !!allMarkersAttr,
					dataAllMarkersLength: allMarkersAttr ? allMarkersAttr.length : 0
				});
				
				if (!allMarkersAttr && attempts < maxAttempts) {
					attempts++;
					setTimeout(checkAndInit, 150);
					return;
				}
				
				if (!allMarkersAttr) {
					console.warn('[JetGeometry ChunkLoader] No data-all-markers attribute found for map after', attempts, 'attempts:', mapId);
					return; // Chunk loading not applicable - no data-all-markers attribute
				}
				
				console.log('[JetGeometry ChunkLoader] Parsing data-all-markers:', {
					mapId: mapId,
					attrType: typeof allMarkersAttr,
					attrLength: allMarkersAttr ? allMarkersAttr.length : 0,
					firstChars: allMarkersAttr ? allMarkersAttr.substring(0, 100) : null
				});
				
				var allMarkers;
				try {
					// Parse JSON - might be already parsed or string
					if (typeof allMarkersAttr === 'string') {
						// Try direct parse first
						allMarkers = JSON.parse(allMarkersAttr);
						console.log('[JetGeometry ChunkLoader] Successfully parsed JSON string');
					} else {
						// Already an object
						allMarkers = allMarkersAttr;
						console.log('[JetGeometry ChunkLoader] Using existing object');
					}
				} catch (e) {
					console.warn('[JetGeometry ChunkLoader] First parse attempt failed, trying decodeURIComponent:', e);
					// Try decodeURIComponent if it's URL encoded
					try {
						allMarkers = JSON.parse(decodeURIComponent(allMarkersAttr));
						console.log('[JetGeometry ChunkLoader] Successfully parsed after decodeURIComponent');
					} catch (e2) {
						console.error('[JetGeometry ChunkLoader] Failed to parse data-all-markers:', e2);
						return;
					}
				}
				
				console.log('[JetGeometry ChunkLoader] Parsed markers:', {
					mapId: mapId,
					isArray: Array.isArray(allMarkers),
					type: typeof allMarkers,
					length: allMarkers ? allMarkers.length : 0,
					chunkSize: self.chunkSize
				});
				
				if (!Array.isArray(allMarkers)) {
					console.warn('[JetGeometry ChunkLoader] data-all-markers is not an array:', typeof allMarkers, allMarkers);
					return;
				}
				
				if (allMarkers.length <= self.chunkSize) {
					console.log('[JetGeometry ChunkLoader] Not enough markers for chunk loading:', allMarkers.length, '<=', self.chunkSize);
					return; // No need for chunk loading
				}
				
				// Store chunk info
				self.loadedChunks[mapId] = {
					allMarkers: allMarkers,
					loadedCount: self.chunkSize, // First chunk already loaded by PHP
					totalCount: allMarkers.length,
					mapInstance: mapInstance,
					$container: $container, // Store container reference
					retryCount: 0 // Track retry attempts
				};
				
				console.log('[JetGeometry ChunkLoader] Initialized chunk loading:', {
					mapId: mapId,
					totalMarkers: allMarkers.length,
					chunkSize: self.chunkSize,
					loadedCount: self.chunkSize,
					remaining: allMarkers.length - self.chunkSize
				});
				
				// Load remaining chunks progressively (wait a bit for map to be ready)
				console.log('[JetGeometry ChunkLoader] Scheduling loadNextChunks in 500ms...');
				setTimeout(function() {
					console.log('[JetGeometry ChunkLoader] Calling loadNextChunks now');
					self.loadNextChunks(mapId);
				}, 500);
			}
			
			checkAndInit();
		},
		
		handleExistingMaps: function() {
			var self = this;
			var mapsFound = 0;
			var mapsProcessed = 0;
			$('.jet-map-listing').each(function() {
				mapsFound++;
				var $container = $(this);
				var mapInstance = $container.data('mapInstance');
				var mapId = $container.attr('id') || 'no-id';
				var hasDataAllMarkers = $container.attr('data-all-markers') ? true : false;
				
				console.log('[JetGeometry ChunkLoader] Found map container:', {
					mapId: mapId,
					hasMapInstance: !!mapInstance,
					hasDataAllMarkers: hasDataAllMarkers,
					dataAllMarkersLength: hasDataAllMarkers ? $container.attr('data-all-markers').length : 0,
					mapInstanceKeys: mapInstance ? Object.keys(mapInstance) : []
				});
				
				if (mapInstance && hasDataAllMarkers) {
					// Create a wrapper object with $container if it doesn't exist
					if (!mapInstance.$container) {
						mapInstance.$container = $container;
					}
					mapsProcessed++;
					self.handleMapInit(mapInstance, $container);
				} else {
					if (!mapInstance) {
						console.warn('[JetGeometry ChunkLoader] Map container has no mapInstance:', mapId);
					}
					if (!hasDataAllMarkers) {
						console.warn('[JetGeometry ChunkLoader] Map container has no data-all-markers:', mapId);
					}
				}
			});
			
			console.log('[JetGeometry ChunkLoader] handleExistingMaps summary:', {
				totalMaps: mapsFound,
				mapsProcessed: mapsProcessed
			});
		},
		
		loadNextChunks: function(mapId) {
			var chunkInfo = this.loadedChunks[mapId];
			
			// Debug log at start (always log)
			console.log('[JetGeometry ChunkLoader] loadNextChunks called:', {
				mapId: mapId,
				hasChunkInfo: !!chunkInfo,
				loadedCount: chunkInfo ? chunkInfo.loadedCount : 0,
				totalCount: chunkInfo ? chunkInfo.totalCount : 0
			});
			
			if (!chunkInfo) {
				console.warn('[JetGeometry ChunkLoader] No chunk info for mapId:', mapId);
				return;
			}
			
			if (chunkInfo.loadedCount >= chunkInfo.totalCount) {
				console.log('[JetGeometry ChunkLoader] All chunks already loaded:', {
					mapId: mapId,
					loadedCount: chunkInfo.loadedCount,
					totalCount: chunkInfo.totalCount
				});
				return;
			}
			
			var self = this;
			var mapInstance = chunkInfo.mapInstance;
			
			// Check if we've tried too many times
			if (!chunkInfo.retryCount) {
				chunkInfo.retryCount = 0;
			}
			chunkInfo.retryCount++;
			
			console.log('[JetGeometry ChunkLoader] loadNextChunks: mapInstance check:', {
				mapId: mapId,
				hasMapInstance: !!mapInstance,
				hasMap: !!(mapInstance && mapInstance.map),
				retryCount: chunkInfo.retryCount,
				mapInstanceKeys: mapInstance ? Object.keys(mapInstance).slice(0, 10) : []
			});
			
			// Limit retries to prevent infinite loop
			if (chunkInfo.retryCount > 50) {
				console.error('[JetGeometry ChunkLoader] Too many retries, giving up:', {
					mapId: mapId,
					retryCount: chunkInfo.retryCount
				});
				return;
			}
			
			// Try to get map from different possible locations
			var map = null;
			var $container = chunkInfo.$container;
			
			// First, try to get from stored container
			if ($container && $container.length > 0) {
				// Try to get mapInstance from container data
				var containerMapInstance = $container.data('mapInstance');
				if (containerMapInstance) {
					mapInstance = containerMapInstance;
				}
			}
			
			// Try window.JetEngineListingMaps (used by JetEngine)
			if (!map && window.JetEngineListingMaps && window.JetEngineListingMaps[mapId]) {
				var listingMapInstance = window.JetEngineListingMaps[mapId];
				if (listingMapInstance) {
					mapInstance = listingMapInstance;
					if (!chunkInfo.$container && listingMapInstance.$container) {
						chunkInfo.$container = listingMapInstance.$container;
						$container = listingMapInstance.$container;
					}
				}
			}
			
			if (mapInstance) {
				// Try different possible properties
				// Sometimes mapInstance IS the map itself
				if (mapInstance.getContainer && typeof mapInstance.getContainer === 'function') {
					map = mapInstance; // mapInstance is the map itself
				} else {
					map = mapInstance.map || mapInstance.mapbox || mapInstance._map;
				}
			}
			
			// If still no map, try to find it from container
			if (!map && $container && $container.length > 0) {
				// Try to find mapbox map element inside container
				var mapElement = $container.find('.mapboxgl-map')[0];
				if (mapElement && mapElement._mapboxgl) {
					map = mapElement._mapboxgl;
				}
			}
			
			// Wait a bit for map to be fully initialized
			setTimeout(function() {
				if (!mapInstance || !map) {
					// Map not ready yet, try again
					console.log('[JetGeometry ChunkLoader] Map not ready, retrying...', {
						mapId: mapId,
						hasMapInstance: !!mapInstance,
						hasMap: !!map,
						retryCount: chunkInfo.retryCount
					});
					setTimeout(function() {
						self.loadNextChunks(mapId);
					}, 200);
					return;
				}
				
				// Update mapInstance with found map
				if (!mapInstance.map) {
					mapInstance.map = map;
				}
				
				var $container = mapInstance.$container || (map && map.getContainer ? $(map.getContainer()).closest('.jet-map-listing') : null);
				
				if (!$container || $container.length === 0) {
					console.warn('[JetGeometry ChunkLoader] Could not find container, retrying...', {
						mapId: mapId,
						hasContainer: !!mapInstance.$container,
						hasMap: !!map
					});
					setTimeout(function() {
						self.loadNextChunks(mapId);
					}, 200);
					return;
				}
				
				// Calculate next chunk
				var startIndex = chunkInfo.loadedCount;
				var endIndex = Math.min(startIndex + self.chunkSize, chunkInfo.totalCount);
				var nextChunk = chunkInfo.allMarkers.slice(startIndex, endIndex);
				
				if (nextChunk.length === 0) {
					console.warn('[JetGeometry ChunkLoader] Next chunk is empty:', {
						mapId: mapId,
						startIndex: startIndex,
						endIndex: endIndex,
						loadedCount: chunkInfo.loadedCount,
						totalCount: chunkInfo.totalCount
					});
					return;
				}
				
				// Debug log before loading chunk (always log)
				console.log('[JetGeometry ChunkLoader] Preparing to load chunk:', {
					mapId: mapId,
					chunkSize: nextChunk.length,
					startIndex: startIndex,
					endIndex: endIndex,
					loadedCount: chunkInfo.loadedCount,
					totalCount: chunkInfo.totalCount,
					remaining: chunkInfo.totalCount - chunkInfo.loadedCount
				});
				
				// Wait for delay before loading chunk
				setTimeout(function() {
					// Add markers to map using JetEngine's method
					var markersAdded = self.addMarkersToMapbox(mapInstance, nextChunk);
					
					// Update loaded count AFTER markers are added
					chunkInfo.loadedCount = endIndex;
					
					// Update container data
					var currentMarkers = $container.data('markers') || [];
					var updatedMarkers = currentMarkers.concat(nextChunk);
					$container.data('markers', updatedMarkers);
					
					// Debug log after loading chunk (always log)
					console.log('[JetGeometry ChunkLoader] Loaded chunk:', {
						mapId: mapId,
						chunkSize: nextChunk.length,
						markersAdded: markersAdded,
						loadedCount: chunkInfo.loadedCount,
						totalCount: chunkInfo.totalCount,
						remaining: chunkInfo.totalCount - chunkInfo.loadedCount,
						willContinue: chunkInfo.loadedCount < chunkInfo.totalCount
					});
					
					// Continue loading next chunks (recursive call)
					if (chunkInfo.loadedCount < chunkInfo.totalCount) {
						// Recursively load next chunk - IMPORTANT: Use setTimeout to prevent stack overflow
						console.log('[JetGeometry ChunkLoader] Scheduling next chunk load...');
						setTimeout(function() {
							console.log('[JetGeometry ChunkLoader] Calling loadNextChunks recursively');
							self.loadNextChunks(mapId);
						}, 50); // Small delay between recursive calls
					} else {
						// All chunks loaded
						console.log('[JetGeometry ChunkLoader] All chunks loaded:', {
							mapId: mapId,
							totalMarkers: chunkInfo.totalCount
						});
						$container.trigger('jet-geometry/chunks-loaded', {
							totalMarkers: chunkInfo.totalCount
						});
						
						// Final update of incident counter
						setTimeout(function() {
							if (window.JetGeometryWidgets && typeof window.JetGeometryWidgets.updateIncidentCounter === 'function') {
								console.log('[JetGeometry ChunkLoader] Final update of incident counter:', chunkInfo.totalCount);
								window.JetGeometryWidgets.updateIncidentCounter(chunkInfo.totalCount, {
									source: 'chunk-loading-complete',
									totalMarkers: chunkInfo.totalCount
								});
							} else {
								console.warn('[JetGeometry ChunkLoader] JetGeometryWidgets.updateIncidentCounter not available for final update');
							}
						}, 100);
					}
				}, self.chunkDelay);
			}, 100); // Small delay to ensure map is ready
		},
		
		addMarkersToMapbox: function(mapInstance, markers) {
			if (!mapInstance || !mapInstance.map) {
				if (window.jetGeometrySettings && window.jetGeometrySettings.debugLogging) {
					console.warn('[JetGeometry ChunkLoader] addMarkersToMapbox: mapInstance or map is missing');
				}
				return false;
			}
			
			var map = mapInstance.map;
			var $container = mapInstance.$container;
			var mapId = $container ? $container.attr('id') : 'default';
			
			// Get map provider and general settings
			if (!window.JetEngineMapsProvider || !window.JetEngineMaps) {
				if (window.jetGeometrySettings && window.jetGeometrySettings.debugLogging) {
					console.warn('[JetGeometry ChunkLoader] addMarkersToMapbox: JetEngineMapsProvider or JetEngineMaps not available');
				}
				return false;
			}
			
			var mapProvider = new window.JetEngineMapsProvider();
			var general = $container.data('general');
			if (!general) {
				if (window.jetGeometrySettings && window.jetGeometrySettings.debugLogging) {
					console.warn('[JetGeometry ChunkLoader] addMarkersToMapbox: general settings not found for mapId:', mapId);
				}
				return false;
			}
			
			// Parse general settings if it's a string
			if (typeof general === 'string') {
				try {
					general = JSON.parse(decodeURIComponent(general));
				} catch (e) {
					if (window.jetGeometrySettings && window.jetGeometrySettings.debugLogging) {
						console.error('[JetGeometry ChunkLoader] Failed to parse general settings:', e);
					}
					return false;
				}
			}
			
			// Get ALL existing markers from JetEngineMaps.markersData
			// Structure: markersData[mapId][postId] = [{ marker: markerObj, clustererIndex: mapId }, ...]
			var allExistingMarkers = [];
			if (window.JetEngineMaps.markersData && window.JetEngineMaps.markersData[mapId]) {
				Object.keys(window.JetEngineMaps.markersData[mapId]).forEach(function(postId) {
					var markerArray = window.JetEngineMaps.markersData[mapId][postId];
					if (Array.isArray(markerArray)) {
						markerArray.forEach(function(m) {
							if (m && m.marker) {
								allExistingMarkers.push(m.marker);
							}
						});
					}
				});
			}
			
			// Add each new marker using JetEngine's method
			var newMarkers = [];
			var skippedMarkers = 0;
			markers.forEach(function(markerData) {
				if (!markerData.latLang) {
					skippedMarkers++;
					return;
				}
				
				var lat, lng;
				if (typeof markerData.latLang === 'object' && markerData.latLang.lat !== undefined) {
					lat = parseFloat(markerData.latLang.lat);
					lng = parseFloat(markerData.latLang.lng);
				} else if (Array.isArray(markerData.latLang)) {
					lat = parseFloat(markerData.latLang[0]);
					lng = parseFloat(markerData.latLang[1]);
				} else {
					skippedMarkers++;
					return;
				}
				
				if (isNaN(lat) || isNaN(lng)) {
					skippedMarkers++;
					return;
				}
				
				// Prepare marker data similar to JetEngine's initMarker
				var pinData = {
					position: { lat: lat, lng: lng },
					map: map,
					shadow: false,
				};
				
				if (markerData.custom_marker) {
					pinData.content = markerData.custom_marker;
				} else if (general.marker && general.marker.type === 'image') {
					pinData.content = '<img src="' + general.marker.url + '" class="jet-map-marker-image" alt="" style="cursor: pointer;">';
				} else if (general.marker && general.marker.type === 'text') {
					pinData.content = general.marker.html.replace('_marker_label_', markerData.label || '');
				} else if (general.marker && general.marker.type === 'icon') {
					pinData.content = general.marker.html;
				}
				
				pinData.markerClustering = general.markerClustering || false;
				
				// Add marker using JetEngine's method
				// Note: If clustering is enabled, marker won't be added directly to map
				var marker = mapProvider.addMarker(pinData);
				if (marker) {
					newMarkers.push(marker);
					
					// Add to JetEngineMaps.markersData (structure: markersData[mapId][postId] = array)
					if (!window.JetEngineMaps.markersData[mapId]) {
						window.JetEngineMaps.markersData[mapId] = {};
					}
					if (!window.JetEngineMaps.markersData[mapId][markerData.id]) {
						window.JetEngineMaps.markersData[mapId][markerData.id] = [];
					}
					window.JetEngineMaps.markersData[mapId][markerData.id].push({
						marker: marker,
						clustererIndex: mapId
					});
				}
			});
			
			// Debug log
			if (window.jetGeometrySettings && window.jetGeometrySettings.debugLogging) {
				console.log('[JetGeometry ChunkLoader] addMarkersToMapbox:', {
					mapId: mapId,
					markersToAdd: markers.length,
					successfullyAdded: newMarkers.length,
					skipped: skippedMarkers,
					existingMarkers: allExistingMarkers.length
				});
			}
			
			// Combine existing and new markers
			var allMarkers = allExistingMarkers.concat(newMarkers);
			
			// Update clusterer if clustering is enabled
			// For Mapbox, we need to call setMarkers() with ALL markers (existing + new)
			if (general.markerClustering && window.JetEngineMaps && window.JetEngineMaps.clusterersData) {
				// Try to find clusterer - mapId might be different (JetEngine uses random IDs)
				var markerCluster = null;
				var clustererKey = null;
				
				// First try exact mapId match
				if (window.JetEngineMaps.clusterersData[mapId]) {
					markerCluster = window.JetEngineMaps.clusterersData[mapId];
					clustererKey = mapId;
				} else {
					// Try to find clusterer by checking if it uses the same map instance
					var clustererKeys = Object.keys(window.JetEngineMaps.clusterersData);
					for (var i = 0; i < clustererKeys.length; i++) {
						var key = clustererKeys[i];
						var cluster = window.JetEngineMaps.clusterersData[key];
						if (cluster && cluster.map === map) {
							markerCluster = cluster;
							clustererKey = key;
							break;
						}
					}
				}
				
				if (markerCluster && typeof markerCluster.setMarkers === 'function') {
					// Use setTimeout to ensure markers are fully added to DOM first
					setTimeout(function() {
						try {
							// Update markers in clusterer with ALL markers (existing + new)
							// Use JetEngine's method if available, otherwise use direct call
							if (typeof mapProvider.addMarkers === 'function') {
								mapProvider.addMarkers(markerCluster, allMarkers);
							} else {
								markerCluster.setMarkers(allMarkers);
								// Call setMapData to refresh the cluster visualization
								if (typeof markerCluster.setMapData === 'function') {
									markerCluster.setMapData();
								}
							}
							
							console.log('[JetGeometry ChunkLoader] Updated clusterer with markers:', {
								mapId: mapId,
								clustererKey: clustererKey,
								existingMarkers: allExistingMarkers.length,
								newMarkers: newMarkers.length,
								totalMarkers: allMarkers.length,
								usedAddMarkers: typeof mapProvider.addMarkers === 'function'
							});
						} catch (e) {
							console.error('[JetGeometry ChunkLoader] Error updating clusterer:', e);
						}
					}, 150);
				} else {
					console.log('[JetGeometry ChunkLoader] Clusterer not found or setMarkers not available:', {
						mapId: mapId,
						hasClusterer: !!markerCluster,
						hasSetMarkers: markerCluster && typeof markerCluster.setMarkers === 'function',
						clustererKeys: window.JetEngineMaps.clusterersData ? Object.keys(window.JetEngineMaps.clusterersData) : []
					});
				}
			} else {
				console.log('[JetGeometry ChunkLoader] Clustering disabled or clusterer not ready:', {
					mapId: mapId,
					clusteringEnabled: general.markerClustering,
					hasClusterersData: !!(window.JetEngineMaps && window.JetEngineMaps.clusterersData),
					clustererKeys: window.JetEngineMaps && window.JetEngineMaps.clusterersData ? Object.keys(window.JetEngineMaps.clusterersData) : []
				});
			}
			
			// Trigger event for geometry renderer
			if ($container) {
				$container.trigger('jet-geometry/chunk-markers-added', {
					markers: markers,
					count: markers.length,
					totalMarkers: allMarkers.length
				});
			}
			
			// Update incident counter widget after markers are added
			// Use setTimeout to ensure DOM is ready
			setTimeout(function() {
				if (window.JetGeometryWidgets && typeof window.JetGeometryWidgets.updateIncidentCounter === 'function') {
					console.log('[JetGeometry ChunkLoader] Updating incident counter:', allMarkers.length);
					window.JetGeometryWidgets.updateIncidentCounter(allMarkers.length, {
						source: 'chunk-loading',
						chunkSize: markers.length,
						totalMarkers: allMarkers.length
					});
				} else {
					console.warn('[JetGeometry ChunkLoader] JetGeometryWidgets.updateIncidentCounter not available');
				}
			}, 100);
			
			// Return true if markers were added successfully
			return newMarkers.length > 0;
		}
	};
	
	// Initialize on DOM ready
	$(document).ready(function() {
		ChunkLoader.init();
	});
	
	// Expose globally
	window.JetGeometryChunkLoader = ChunkLoader;
	
})(jQuery);

