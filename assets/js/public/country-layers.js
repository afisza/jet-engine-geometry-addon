/**
 * Country Layers - Frontend
 */

(function($) {
	'use strict';

	// Ensure data container exists
	window.JetCountryLayersData = window.JetCountryLayersData || {};

	window.JetCountryLayers = {
		maps: {},
		layersAdded: {},
		countryToggleState: false,
		incidentToggleAttempts: {},
		countriesRequest: null,
		countriesAbortController: null,
		countriesData: null,
		pendingToggle: null,
		selectedCountryKey: null,
		selectedCountryFeature: null,
		pendingSelectedCountry: null,
		selectedCountryLayers: {},
		selectedHighlightOptions: {},
		countryIndexBySlug: {},
		countryIndexById: {},
		countryIndexByIso: {},
		currentPopup: null, // Store current active popup
		incidentCounts: {
			byId: {},
			bySlug: {}
		},
		incidentColor: '#ef4444',
		noIncidentColor: '#3b82f6',
		incidentBorderColor: '#ef4444',
		noIncidentBorderColor: '#3b82f6',
		incidentBorderWidth: 2,
		noIncidentBorderWidth: 1,
		pendingToggleStates: {},
		forceCountryLayersVisible: false,
		toggleStorageKey: 'jetCountryLayersToggle',
		// Debounce timers to prevent excessive calls
		toggleMarkerElementsTimer: null,
		toggleIncidentLayersTimers: {},
		lastToggleMarkerElementsCall: 0,
		lastToggleIncidentLayersCall: {},

		init: function() {
			var self = this;

			// Setup highlight options on init
			this.setupHighlightOptions();
			
			// Re-setup highlight options when DOM is ready (to catch widget settings)
			$(document).ready(function() {
				self.setupHighlightOptions();
			});

			if ( window.JetCountryLayersData ) {
				if ( window.JetCountryLayersData.incidentCounts ) {
					this.incidentCounts = {
						byId: window.JetCountryLayersData.incidentCounts.byId || {},
						bySlug: window.JetCountryLayersData.incidentCounts.bySlug || {}
					};
				}

				if ( window.JetCountryLayersData.incidentColor ) {
					this.incidentColor = this.normalizeColor( window.JetCountryLayersData.incidentColor );
				}

				if ( window.JetCountryLayersData.noIncidentColor ) {
					this.noIncidentColor = this.normalizeColor( window.JetCountryLayersData.noIncidentColor );
				}

				if ( window.JetCountryLayersData.incidentBorderColor ) {
					this.incidentBorderColor = this.normalizeColor( window.JetCountryLayersData.incidentBorderColor );
				}

				if ( window.JetCountryLayersData.noIncidentBorderColor ) {
					this.noIncidentBorderColor = this.normalizeColor( window.JetCountryLayersData.noIncidentBorderColor );
				}

				if ( typeof window.JetCountryLayersData.incidentBorderWidth !== 'undefined' ) {
					this.incidentBorderWidth = this.parseNumericValue( window.JetCountryLayersData.incidentBorderWidth, this.incidentBorderWidth );
				}

				if ( typeof window.JetCountryLayersData.noIncidentBorderWidth !== 'undefined' ) {
					this.noIncidentBorderWidth = this.parseNumericValue( window.JetCountryLayersData.noIncidentBorderWidth, this.noIncidentBorderWidth );
				}
			}

			this.deferStyleOptionsSync();

			// Ensure REST endpoint is known
			this.ensureRestUrl();

			// Restore saved toggle state
			var savedState = this.getStoredToggleState();
			if ( null !== savedState ) {
				this.countryToggleState = savedState;
				this.pendingToggle = savedState;
				// Update checkbox immediately to reflect saved state
				try {
					if ( window.jQuery ) {
						// Force checkbox to match saved state
						window.jQuery('.jet-country-layers-checkbox').prop('checked', savedState);
						var i18n = ( JetCountryLayersData && JetCountryLayersData.i18n ) ? JetCountryLayersData.i18n : {
							showCountryLayers: 'Show Country Layers',
							hideCountryLayers: 'Hide Country Layers',
						};
						var label = savedState ? i18n.hideCountryLayers : i18n.showCountryLayers;
						window.jQuery('.jet-country-layers-toggle .toggle-label, .jet-country-layers-label').text(label);
					}
				} catch (error) {
				}
			} else {
				// No saved state - check checkbox but don't save yet
				try {
					if ( window.jQuery ) {
						var checked = window.jQuery('.jet-country-layers-checkbox:checked').length > 0;
						this.countryToggleState = !! checked;
						this.pendingToggle = !! checked;
						// Don't save to localStorage until user explicitly toggles
					}
				} catch (error) {
				}
			}

			// Pre-fetch country data if not already available
			this.loadCountriesData();

			// Wait for maps to be ready
			$(document).on('jet-engine/maps-listing/init', function(e, mapInstance) {
				if (mapInstance && mapInstance.mapID) {
				}
				self.initMapLayers(mapInstance);
			});

			// Listen for when markers are added to ensure they respect toggle state
			$(document).on('jet-engine/maps-listing/markers-added', function(e, data) {
				
				// Apply current toggle state to newly added markers (debounced)
				if ( ! self.countryToggleState ) {
					// If country layers are OFF, ensure incidents are visible
					self.toggleMarkerElementsDebounced(true);
				}
			});

			// Also monitor for marker additions via MutationObserver as fallback
			if ( typeof MutationObserver !== 'undefined' ) {
				var markerObserver = new MutationObserver(function(mutations) {
					var markersAdded = false;
					mutations.forEach(function(mutation) {
						if ( mutation.addedNodes && mutation.addedNodes.length > 0 ) {
							for ( var i = 0; i < mutation.addedNodes.length; i++ ) {
								var node = mutation.addedNodes[i];
								if ( node.classList && node.classList.contains('mapboxgl-marker') ) {
									markersAdded = true;
									break;
								}
							}
						}
					});
					
					if ( markersAdded && ! self.countryToggleState ) {
						// Small delay to ensure marker is fully initialized (debounced)
						self.toggleMarkerElementsDebounced(true);
					}
				});

				// Start observing when DOM is ready
				$(document).ready(function() {
					var $mapContainers = $('.jet-map-listing');
					if ( $mapContainers.length ) {
						$mapContainers.each(function() {
							markerObserver.observe(this, {
								childList: true,
								subtree: true
							});
						});
					}
				});
			}

			// Bind toggle controls
			$(document).on('change', '#show-country-layers, .jet-country-layers-checkbox', function() {
				var isChecked = $(this).is(':checked');
				// Always persist when user explicitly toggles
				self.toggleCountryLayers(isChecked, { persist: true });
			});
			
			// Add touch support for mobile devices
			$(document).on('touchstart', '.jet-country-layers-toggle', function(e) {
				// Prevent double-tap zoom on iOS
				if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches.length > 1) {
					e.preventDefault();
					return;
				}
			});
			
			$(document).on('touchend', '.jet-country-layers-toggle', function(e) {
				e.preventDefault();
				var $checkbox = $(this).find('.jet-country-layers-checkbox, #show-country-layers');
				if ($checkbox.length) {
					// Toggle checkbox state
					$checkbox.prop('checked', !$checkbox.prop('checked'));
					// Trigger change event
					$checkbox.trigger('change');
				}
			});
			
			// Also handle click on label for better compatibility
			$(document).on('click', '.jet-country-layers-toggle', function(e) {
				// Only handle if click is not directly on checkbox
				if ($(e.target).is('.toggle-checkbox, .jet-country-layers-checkbox')) {
					return; // Let default behavior handle it
				}
				var $checkbox = $(this).find('.jet-country-layers-checkbox, #show-country-layers');
				if ($checkbox.length) {
					$checkbox.prop('checked', !$checkbox.prop('checked'));
					$checkbox.trigger('change');
				}
			});

			// Bind reset zoom
			$(document).on('click', '.jet-geometry-reset-zoom', function(e) {
				e.preventDefault();
				self.resetMapZoom();
			});

			// Bind reset zoom when JetSmartFilters "Remove Filters" button is clicked
			$(document).on('click', '.jet-remove-all-filters__button', function(e) {
				// Small delay to ensure filters are reset first, then reset map zoom
				setTimeout(function() {
					self.resetMapZoom();
				}, 100);
			});

			// Monitor JetEngineMaps.markersData for changes
			var lastMarkersDataKeys = '';
			var markersDataCheckInterval = setInterval(function() {
				if (window.JetEngineMaps && window.JetEngineMaps.markersData) {
					var currentKeys = Object.keys(window.JetEngineMaps.markersData).join(',');
					if (currentKeys !== lastMarkersDataKeys) {
						lastMarkersDataKeys = currentKeys;
						
						// Count total markers
						var totalMarkers = 0;
						Object.keys(window.JetEngineMaps.markersData).forEach(function(postId) {
							var markersArray = window.JetEngineMaps.markersData[postId];
							if (Array.isArray(markersArray)) {
								totalMarkers += markersArray.length;
							}
						});
						
						// If country layers are OFF, ensure incidents are visible (debounced)
						if ( ! self.countryToggleState && totalMarkers > 0 ) {
							self.toggleMarkerElementsDebounced(true);
						}
					}
				} else {
				}
			}, 500);
			
			// Stop monitoring after 30 seconds
			setTimeout(function() {
				clearInterval(markersDataCheckInterval);
			}, 30000);

			// Initialize on page load
			$(window).on('load', function() {
				setTimeout(function() {
					self.initExistingMaps();
					
					// Ensure incidents are visible if country layers toggle is OFF
					if ( ! self.countryToggleState ) {
						setTimeout(function() {
							self.toggleMarkerElementsDebounced(true);
							// Apply to all maps (debounced)
							$.each(self.maps, function(mapId, mapInstance) {
								var map = mapInstance.map || mapInstance;
								if ( map && mapId ) {
									self.toggleIncidentLayersDebounced(true, mapId, map);
								}
							});
						}, 1000);
					}
				}, 500);
			});
		},

		ensureRestUrl: function() {
			if ( JetCountryLayersData.restUrl ) {
				return JetCountryLayersData.restUrl;
			}

			var root = '';

			if ( window.wpApiSettings && window.wpApiSettings.root ) {
				root = window.wpApiSettings.root;
			} else {
				var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
				root = origin.replace(/\/$/, '') + '/wp-json/';
			}

			var namespace = 'jet-geometry/v1/';
			if ( root.slice(-1) !== '/' ) {
				root += '/';
			}

			JetCountryLayersData.restUrl = root + namespace;

			return JetCountryLayersData.restUrl;
		},

		setupHighlightOptions: function() {
			var data = window.JetCountryLayersData || {};
			var outline = ( data.highlightOutline && typeof data.highlightOutline === 'object' ) ? data.highlightOutline : {};

			// Priority: Widget settings > Global settings > Defaults
			var $widget = $('.jet-country-highlight-settings').first();
			var hasWidget = $widget.length > 0;

			var fillColor, fillOpacity, outlineEnabled, outlineColor, outlineWidth;

			if ( hasWidget ) {
				// Use widget settings
				fillColor = $widget.data('fill-color') || data.highlightColor || data.defaultColor || '#f25f5c';
				fillOpacity = this.parseNumericValue( $widget.data('fill-opacity'), data.highlightOpacity || 0.45 );
				outlineEnabled = $widget.data('outline-enabled') === 'yes' || $widget.data('outline-enabled') === true;
				outlineColor = $widget.data('outline-color') || outline.color || data.highlightColor || data.defaultColor || '#f25f5c';
				outlineWidth = this.parseNumericValue( $widget.data('outline-width'), outline.width || 2.5 );
			} else {
				// Use global settings
				fillColor = data.highlightColor || data.defaultColor || '#f25f5c';
				fillOpacity = this.parseNumericValue( data.highlightOpacity, 0.45 );
				outlineEnabled = ( outline.enabled !== false && outline.enabled !== 'false' );
				outlineColor = outline.color || data.highlightColor || data.defaultColor || '#f25f5c';
				outlineWidth = this.parseNumericValue( outline.width, 2.5 );
			}

			if ( fillOpacity > 1 ) {
				// Assume percentage (0-100)
				fillOpacity = fillOpacity / 100;
			}

			this.selectedHighlightOptions = {
				fillColor: this.normalizeColor( fillColor ),
				fillOpacity: fillOpacity,
				outlineEnabled: outlineEnabled,
				outlineColor: this.normalizeColor( outlineColor ),
				outlineWidth: outlineWidth
			};
		},

		deferStyleOptionsSync: function() {
			var self = this;

			if ( ! window.jQuery ) {
				return;
			}

			try {
				window.jQuery(function($) {
					self.updateStyleOptionsFromElement($('.jet-country-layers-toggle-wrapper').first(), false);
				});
			} catch (error) {
			}
		},

		updateStyleOptionsFromElement: function(element, reapply) {
			if ( ! window.jQuery ) {
				return;
			}

			var $element;

			if ( element ) {
				$element = window.jQuery(element);
			} else {
				$element = window.jQuery('.jet-country-layers-toggle-wrapper').first();
			}

			if ( ! $element || ! $element.length ) {
				return;
			}

			var data = $element.data() || {};

			if ( data.noIncidentFill ) {
				this.noIncidentColor = this.normalizeColor( data.noIncidentFill );
			}

			if ( data.incidentFill ) {
				this.incidentColor = this.normalizeColor( data.incidentFill );
			}

			if ( data.noIncidentBorder ) {
				this.noIncidentBorderColor = this.normalizeColor( data.noIncidentBorder );
			}

			if ( data.incidentBorder ) {
				this.incidentBorderColor = this.normalizeColor( data.incidentBorder );
			}

			if ( typeof data.noIncidentBorderWidth !== 'undefined' ) {
				this.noIncidentBorderWidth = this.parseNumericValue( data.noIncidentBorderWidth, this.noIncidentBorderWidth );
			}

			if ( typeof data.incidentBorderWidth !== 'undefined' ) {
				this.incidentBorderWidth = this.parseNumericValue( data.incidentBorderWidth, this.incidentBorderWidth );
			}

			if ( reapply ) {
				this.reapplyCurrentState();
			}
		},

		initExistingMaps: function() {
			var self = this;

			if ( typeof window.JetEngineListingMaps !== 'undefined' ) {
				var mapsCount = Object.keys(window.JetEngineListingMaps).length;
				$.each(window.JetEngineListingMaps, function(id, mapInstance) {
					self.initMapLayers(mapInstance);
				});
				return;
			}

			setTimeout(function() {
				var $maps = $('.jet-map-listing');
				var attempts = 0;
				var maxAttempts = 20;

				var check = function() {
					attempts++;

					$maps = $('.jet-map-listing');

					if ( ! $maps.length || attempts > maxAttempts ) {
						if (attempts > maxAttempts) {
						}
						return;
					}

					var found = false;

					$maps.each(function() {
						var $container = $(this);

						if ( $container.data('jet-country-layers-registered') ) {
							return;
						}

						var mapInstance = $container.data('mapInstance') ||
							$container.data('mapinstance') ||
							this.mapInstance ||
							this.mapinstance;

						if ( ! mapInstance ) {
							return;
						}

						$container.data('jet-country-layers-registered', true);

						self.initMapLayers(mapInstance);
						found = true;
					});

					if ( ! found ) {
						setTimeout(check, 500);
					} else {
						
						// Ensure incidents are visible if country layers are OFF
						if ( ! self.countryToggleState ) {
							setTimeout(function() {
								self.toggleMarkerElementsDebounced(true);
								// Apply to all registered maps
								$.each(self.maps, function(mapId, mapInstance) {
									var map = mapInstance.map || mapInstance;
									if ( map && mapId ) {
										self.toggleIncidentLayersDebounced(true, mapId, map);
									}
								});
							}, 1000);
						}
					}
				};

				check();
			}, 500);
		},

		initMapLayers: function(mapInstance) {
			if ( ! mapInstance ) {
				return;
			}

			var self = window.JetCountryLayers || this;
			var mapObj      = mapInstance.map || mapInstance;
			var containerEl = null;
			var $container  = null;

			if ( mapInstance.$container && typeof mapInstance.$container.attr === 'function' ) {
				$container  = mapInstance.$container;
				containerEl = $container[0];
			} else if ( mapInstance.getContainer && typeof mapInstance.getContainer === 'function' ) {
				containerEl = mapInstance.getContainer();
				if ( containerEl && window.jQuery ) {
					$container = window.jQuery(containerEl.closest('.jet-map-listing'));
				}
			}

			if ( ! mapObj || typeof mapObj.on !== 'function' ) {
				var fallbackCanvas = containerEl ? containerEl.querySelector('canvas') : document.querySelector('.jet-map-listing canvas');

				if ( fallbackCanvas && fallbackCanvas._map ) {
					mapObj = fallbackCanvas._map;
					if ( ! containerEl ) {
						containerEl = fallbackCanvas.parentNode;
					}

					if ( ! $container && containerEl && window.jQuery ) {
						$container = window.jQuery(containerEl.closest('.jet-map-listing'));
					}
				}

				if ( ! mapObj || typeof mapObj.on !== 'function' ) {
					return;
				}
			}

			if ( ! containerEl && mapObj.getContainer ) {
				containerEl = mapObj.getContainer();
				if ( containerEl && window.jQuery ) {
					$container = window.jQuery(containerEl.closest('.jet-map-listing'));
				}
			}

			var mapId = null;

			if ( $container && $container.length ) {
				mapId = $container.attr('id');
			} else if ( containerEl ) {
				mapId = containerEl.getAttribute('id');
			}

			if ( ! mapId ) {
				mapId = 'jet-map-' + Date.now() + '-' + Math.floor( Math.random() * 1000 );

				if ( $container && $container.length ) {
					$container.attr('id', mapId);
				} else if ( containerEl ) {
					containerEl.setAttribute('id', mapId);
				}
			}

			this.maps[mapId] = { map: mapObj, container: $container };
			this.layersAdded[mapId] = false;
			
			// Setup click handlers for geometry layers (unclustered-point, lines, polygons)
			this.setupGeometryClickHandlers(mapId, mapObj, $container);
			
			// Listen for map 'idle' event to ensure markers are visible when clustering adds them
			if ( self && ! self.countryToggleState ) {
				var onMapIdle = function() {
					// Small delay to ensure markers are added to DOM by clustering system
					setTimeout(function() {
						if (self && self.toggleMarkerElementsDebounced) {
							self.toggleMarkerElementsDebounced(true);
						}
					}, 200);
				};
				mapObj.on('idle', onMapIdle);
			}
			var ensureReady = function() {
				self.setupCountrySource(mapId, mapObj);
				self.applySelectedCountryForMap(mapId, mapObj);
				self.applyToggleState(mapId, mapObj, self.countryToggleState);
				
				// Ensure incidents are visible if country layers are OFF
				// Wait for map 'idle' event to ensure markers are rendered by clustering system
				if ( ! self.countryToggleState ) {
					// Force show incidents immediately and on idle
					var showIncidents = function() {
						self.toggleMarkerElementsDebounced(true);
						if ( mapId && mapId !== 'undefined' ) {
							self.toggleIncidentLayersDebounced(true, mapId, mapObj);
						} else {
							// Apply to all maps if no mapId
							$.each(self.maps, function(eachMapId, mapInstance) {
								if ( eachMapId && eachMapId !== 'undefined' ) {
									var eachMap = mapInstance.map || mapInstance;
									self.toggleIncidentLayersDebounced(true, eachMapId, eachMap);
								}
							});
						}
					};
					
					// Show immediately
					setTimeout(showIncidents, 100);
					
					// Also show on map idle (when markers are fully rendered)
					if ( mapObj && typeof mapObj.once === 'function' ) {
						mapObj.once('idle', function() {
							setTimeout(showIncidents, 200);
						});
					}
					
					// Fallback: show after longer delay
					setTimeout(showIncidents, 1000);
				}
			};

			if ( typeof mapObj.isStyleLoaded === 'function' ) {
				if ( mapObj.isStyleLoaded() ) {
					ensureReady();
				} else {
					mapObj.once('load', ensureReady);
				}
			} else {
				mapObj.once('load', ensureReady);
			}
		},

		setupCountrySource: function(mapId, map) {
			var sourceId = 'jet-country-source-' + mapId;

			if ( ! this.hasCountriesData() ) {
				this.fetchCountriesData(function(success) {
					if ( success && JetCountryLayers.hasCountriesData() ) {
						JetCountryLayers.setupCountrySource(mapId, map);
						// Apply toggle state after source is set up
						var desiredState = ( typeof JetCountryLayers.pendingToggleStates[mapId] !== 'undefined' ) 
							? JetCountryLayers.pendingToggleStates[mapId] 
							: JetCountryLayers.countryToggleState;
						JetCountryLayers.applyToggleState(mapId, map, desiredState);
					} else {
					}
				});
				return;
			}

			var data = this.countriesData;
			var featureCount = ( data && data.features ) ? data.features.length : 0;

			// Add source if not exists
			if ( ! map.getSource(sourceId) ) {
				map.addSource(sourceId, {
					type: 'geojson',
					data: data
				});
			} else {
				map.getSource(sourceId).setData(data);
			}

			if ( featureCount === 0 ) {
			}

			// Check pending toggle state first, then use current state
			var desiredState = ( typeof this.pendingToggleStates[mapId] !== 'undefined' ) 
				? this.pendingToggleStates[mapId] 
				: this.countryToggleState;
			
			// Clear pending state after reading it
			if ( typeof this.pendingToggleStates[mapId] !== 'undefined' ) {
				delete this.pendingToggleStates[mapId];
			}
			
			// Apply toggle state - use a small delay to ensure source is fully ready
			var self = this;
			setTimeout(function() {
				self.applyToggleState(mapId, map, desiredState);
			}, 50);
		},

		hasCountriesData: function() {
			return !! ( this.countriesData && this.countriesData.features && this.countriesData.features.length );
		},

		fetchCountriesData: function(callback, force) {
			var self = this;

			return this.loadCountriesData(force).then(function(data) {
				if ( typeof callback === 'function' ) {
					callback(!! ( data && data.features && data.features.length ));
				}
			});
		},

		getStoredToggleState: function() {
			if ( ! window.localStorage ) {
				return null;
			}

			try {
				var raw = localStorage.getItem(this.toggleStorageKey);

				if ( 'on' === raw ) {
					return true;
				}

				if ( 'off' === raw ) {
					return false;
				}
			} catch (error) {
			}

			return null;
		},

		storeToggleState: function(state) {
			if ( ! window.localStorage ) {
				return;
			}

			try {
				localStorage.setItem(this.toggleStorageKey, state ? 'on' : 'off');
			} catch (error) {
			}
		},

		loadCountriesData: function(force) {
			var self = this;

			if ( ! force && this.hasCountriesData() ) {
				return Promise.resolve(this.countriesData);
			}

			if ( ! force && this.countriesRequest ) {
				return this.countriesRequest;
			}

			var url = JetCountryLayersData.countriesUrl;

			if ( ! url ) {
				var restUrl = this.ensureRestUrl();

				if ( restUrl ) {
					var base = restUrl.replace(/\/wp-json\/.*$/, '/').replace(/\/+$/, '/');
					url = base + 'wp-content/uploads/jet-geometry/countries.json';
					JetCountryLayersData.countriesUrl = url;
				} else {
					return this.fetchCountriesDataViaRest();
				}
			}

			if ( this.countriesAbortController ) {
				try {
					this.countriesAbortController.abort();
				} catch (error) {}
			}

			this.countriesAbortController = ( 'AbortController' in window ) ? new AbortController() : null;

			var cacheBuster = JetCountryLayersData.countriesUpdated || Date.now();
			var requestUrl = url;

			if ( cacheBuster ) {
				requestUrl += ( requestUrl.indexOf('?') === -1 ? '?' : '&' ) + 'v=' + cacheBuster;
			}

			var options = { cache: 'no-cache' };

			if ( this.countriesAbortController && this.countriesAbortController.signal ) {
				options.signal = this.countriesAbortController.signal;
			}

			var promise = fetch(requestUrl, options)
				.then(function(response) {
					if ( ! response.ok ) {
						throw new Error('Failed to fetch countries JSON: ' + response.status);
					}
					return response.json();
				})
				.catch(function(error) {
					return self.fetchCountriesDataViaRest();
				})
				.then(function(data) {
					self.setCountriesData(data);
					return self.countriesData;
				})
				.finally(function() {
					self.countriesRequest = null;
					self.countriesAbortController = null;
				});

			this.countriesRequest = promise;
			return promise;
		},

		fetchCountriesDataViaRest: function() {
			var self = this;
			var restUrl = this.ensureRestUrl();

			if ( ! restUrl ) {
				return Promise.resolve(null);
			}

			return new Promise(function(resolve) {
				var requestUrl = restUrl + 'countries/geojson';
				
				$.ajax({
					url: requestUrl,
					method: 'GET',
					data: { simplified: true },
					beforeSend: function(xhr) {
						if ( JetCountryLayersData.nonce ) {
							xhr.setRequestHeader('X-WP-Nonce', JetCountryLayersData.nonce);
						}
					}
				}).done(function(response) {
					self.setCountriesData(response);
					resolve(self.countriesData);
				}).fail(function(jqXHR, textStatus, errorThrown) {
					// Silent fail - return null
					resolve(null);
				});
			});
		},

		setCountriesData: function(data) {
			if ( data && data.features ) {
				this.countriesData = data;

				this.buildCountryIndexes(data);
				this.applyIncidentCountsToFeatures(data);
				this.applyCountriesDataToMaps();

				if ( this.pendingSelectedCountry ) {
					var pending = this.pendingSelectedCountry;
					this.pendingSelectedCountry = null;
					this.setSelectedCountry(pending, { fit: true });
				} else if ( this.selectedCountryKey ) {
					var refreshed = this.findCountryFeature(this.selectedCountryKey);
					this.selectedCountryFeature = refreshed;
					if ( refreshed ) {
						this.applySelectedCountryToMaps(false);
					} else {
						this.applySelectedCountryToMaps(true);
					}
				}

				if ( null !== this.pendingToggle ) {
					var desired = this.pendingToggle;
					this.pendingToggle = null;
					this.countryToggleState = !! desired;
					
					// Update checkbox state to match
					if ( window.jQuery ) {
						window.jQuery('.jet-country-layers-checkbox').prop('checked', this.countryToggleState);
						var i18n = ( JetCountryLayersData && JetCountryLayersData.i18n ) ? JetCountryLayersData.i18n : {
							showCountryLayers: 'Show Country Layers',
							hideCountryLayers: 'Hide Country Layers',
						};
						var label = this.countryToggleState ? i18n.hideCountryLayers : i18n.showCountryLayers;
						window.jQuery('.jet-country-layers-toggle .toggle-label, .jet-country-layers-label').text(label);
					}
					
					this.reapplyCurrentState();
				} else {
					this.reapplyCurrentState();
				}
			} else {
				this.countriesData = null;
			}
		},

		applyCountriesDataToMaps: function() {
			var self = this;

			$.each(this.maps, function(mapId, instance) {
				var map = instance.map || instance;

				if ( ! map ) {
					return;
				}

				if ( typeof map.loaded === 'function' && ! map.loaded() ) {
					// setupCountrySource will be invoked once the map fires the load event.
					return;
				}

				self.setupCountrySource(mapId, map);
				
				// Apply toggle state after source is set up
				// Check pending state first, then use current state
				var desiredState = ( typeof self.pendingToggleStates[mapId] !== 'undefined' ) 
					? self.pendingToggleStates[mapId] 
					: self.countryToggleState;
				
				// Use a small delay to ensure source is fully ready
				setTimeout(function() {
					self.applyToggleState(mapId, map, desiredState);
				}, 100);
				
				if ( self.forceCountryLayersVisible ) {
					self.showCountryLayers(mapId, map);
					self.setLayersVisibility(mapId, map, 'visible');
				}
			});
		},

		buildCountryIndexes: function(data) {
			this.countryIndexBySlug = {};
			this.countryIndexById   = {};
			this.countryIndexByIso  = {};

			if ( ! data || ! data.features ) {
				return;
			}

			var self = this;

			data.features.forEach(function(feature) {
				if ( ! feature || ! feature.properties ) {
					return;
				}

				var props = feature.properties;

				if ( typeof props.slug !== 'undefined' && props.slug !== null ) {
					self.countryIndexBySlug[String(props.slug).toLowerCase()] = feature;
				}

				if ( typeof props.term_id !== 'undefined' && props.term_id !== null ) {
					self.countryIndexById[String(props.term_id)] = feature;
				}

				if ( typeof props.iso_code !== 'undefined' && props.iso_code !== null ) {
					self.countryIndexByIso[String(props.iso_code).toUpperCase()] = feature;
				}
			});
		},

		applyIncidentCountsToFeatures: function(data) {
			if ( ! data || ! data.features ) {
				return;
			}

			var countsById   = ( this.incidentCounts && this.incidentCounts.byId ) ? this.incidentCounts.byId : {};
			var countsBySlug = ( this.incidentCounts && this.incidentCounts.bySlug ) ? this.incidentCounts.bySlug : {};

			data.features.forEach(function(feature) {
				if ( ! feature ) {
					return;
				}

				if ( ! feature.properties ) {
					feature.properties = {};
				}

				var props   = feature.properties;
				var termId  = props.term_id ? String(props.term_id) : null;
				var slugKey = props.slug ? String(props.slug).toLowerCase() : null;

				var count = null;

				if ( termId && typeof countsById[termId] !== 'undefined' ) {
					count = countsById[termId];
				} else if ( slugKey && typeof countsBySlug[slugKey] !== 'undefined' ) {
					count = countsBySlug[slugKey];
				} else if ( typeof props.incident_count !== 'undefined' ) {
					count = props.incident_count;
				}

				props.incident_count = parseInt(count, 10);

				if ( isNaN(props.incident_count) ) {
					props.incident_count = 0;
				}
			});
		},

		applySelectedCountryForMap: function(mapId, map) {
			if ( ! map ) {
				return;
			}

			if ( this.selectedCountryFeature ) {
				this.ensureSelectedCountryLayers(mapId, map, this.selectedCountryFeature, false);
			} else {
				this.removeSelectedCountryLayers(mapId, map);
			}
		},

		applySelectedCountryToMaps: function(clearOnly, options) {
			options = options || {};

			var self = this;
			var feature = clearOnly ? null : this.selectedCountryFeature;
			var fitRequested = !! options.fit;
			var fitConsumed = false;

			$.each(this.maps, function(mapId, instance) {
				var map = instance.map || instance;
				if ( ! map ) {
					return;
				}

				if ( feature ) {
					if ( typeof map.isStyleLoaded === 'function' ) {
						if ( map.isStyleLoaded() ) {
							self.ensureSelectedCountryLayers(mapId, map, feature, fitRequested && ! fitConsumed);
							if ( fitRequested && ! fitConsumed ) {
								fitConsumed = true;
							}
						} else {
							map.once('load', function() {
								self.ensureSelectedCountryLayers(mapId, map, feature, fitRequested && ! fitConsumed);
								if ( fitRequested && ! fitConsumed ) {
									fitConsumed = true;
								}
							});
						}
					} else if ( typeof map.loaded === 'function' && ! map.loaded() ) {
						map.once('load', function() {
							self.ensureSelectedCountryLayers(mapId, map, feature, fitRequested && ! fitConsumed);
							if ( fitRequested && ! fitConsumed ) {
								fitConsumed = true;
							}
						});
					} else {
						self.ensureSelectedCountryLayers(mapId, map, feature, fitRequested && ! fitConsumed);
						if ( fitRequested && ! fitConsumed ) {
							fitConsumed = true;
						}
					}
				} else {
					self.removeSelectedCountryLayers(mapId, map);
				}
			});
		},

		reapplyCurrentState: function() {
			this.toggleCountryLayers(this.countryToggleState, { persist: false, reapply: true });
		},

		toggleCountryLayers: function(show, options) {
			var self    = this;
			var persist = ! options || options.persist !== false;

			if ( this.forceCountryLayersVisible ) {
				show = true;
				persist = false;
			}

			// Clear pending toggle states when new explicit state provided
			if ( this.pendingToggleStates ) {
				this.pendingToggleStates = {};
			}

			if ( this.incidentToggleAttempts ) {
				$.each(this.incidentToggleAttempts, function(key, tracker) {
					if ( tracker && tracker.timer ) {
						try {
							clearTimeout(tracker.timer);
						} catch (error) {}
						tracker.timer = null;
					}
				});
			}

			if ( ! this.hasCountriesData() ) {
				this.pendingToggle = show;
				this.fetchCountriesData(function(success) {
					if ( success ) {
						var desired = self.pendingToggle;
						self.pendingToggle = null;
						if ( desired !== null ) {
							self.toggleCountryLayers(desired, { persist: persist });
						}
					} else {
						self.pendingToggle = null;
					}
				}, true);
				return;
			}

			$.each(this.maps, function(mapId, mapInstance) {
				self.applyToggleState(mapId, mapInstance.map, show);
			});

			var i18n = ( JetCountryLayersData && JetCountryLayersData.i18n ) ? JetCountryLayersData.i18n : {
				showCountryLayers: 'Show Country Layers',
				hideCountryLayers: 'Hide Country Layers',
			};

			var label = show ? i18n.hideCountryLayers : i18n.showCountryLayers;

			$('.jet-country-layers-toggle .toggle-label, .jet-country-layers-label').text(label);
			$('.jet-country-layers-checkbox').prop('checked', !! show);

			this.countryToggleState = !! show;

			if ( persist ) {
				this.storeToggleState(this.countryToggleState);
			}
			
			// Emit event dla innych modułów (np. cluster styling)
			$(document).trigger('jet-country-layers/toggle', [show]);
		},

		applyToggleState: function(mapId, map, showCountries) {
			var context = ( this && this === window ) ? window.JetCountryLayers : this;

			if ( ! context ) {
				context = window.JetCountryLayers || this;
			}

			if ( ! context ) {
				return;
			}

			// JetEngine map instances sometimes expose `map` or are the mapbox Map directly
			var targetMap = map;
			if ( targetMap && targetMap.map ) {
				targetMap = targetMap.map;
			}

			if ( ! targetMap || typeof targetMap.getSource !== 'function' ) {
				// Nothing to toggle yet - save state for later
				if ( mapId ) {
					context.pendingToggleStates[mapId] = showCountries;
				}
				return;
			}

			var sourceId = 'jet-country-source-' + mapId;
			if ( ! targetMap.getSource(sourceId) ) {
				// Source not ready yet - save state and try to setup source
				if ( mapId ) {
				context.pendingToggleStates[mapId] = showCountries;
					// Try to setup source if we have data
					if ( context.hasCountriesData() ) {
						context.setupCountrySource(mapId, targetMap);
					}
				}
				return;
			}

			// Source exists - clear pending state and apply
			if ( mapId && typeof context.pendingToggleStates[mapId] !== 'undefined' ) {
			delete context.pendingToggleStates[mapId];
			}

			if ( showCountries ) {
				context.showCountryLayers(mapId, targetMap);
				if ( mapId ) {
					context.toggleIncidentLayersDebounced(false, mapId, targetMap);
				}
				context.toggleMarkerElementsDebounced(false);
			} else {
				context.hideCountryLayers(mapId, targetMap);
				// Always show incidents when country layers are OFF
				// Force show incidents immediately
				context.toggleMarkerElementsDebounced(true);
				
				// Try with specific mapId first
				if ( mapId && mapId !== 'undefined' ) {
					context.toggleIncidentLayersDebounced(true, mapId, targetMap);
				}
				
				// Also apply to all maps to ensure coverage (with delay to let map initialize)
				setTimeout(function() {
					$.each(context.maps, function(eachMapId, mapInstance) {
						if ( eachMapId && eachMapId !== 'undefined' ) {
							var eachMap = mapInstance.map || mapInstance;
							if ( eachMap ) {
								context.toggleIncidentLayersDebounced(true, eachMapId, eachMap);
							}
						}
					});
					// Emit event po zmianie widoczności warstw
					$(document).trigger('jet-country-layers/incidents-toggled', [true]);
				}, 200);
				
				// Additional attempt after longer delay (for markers that load later)
				setTimeout(function() {
					context.toggleMarkerElementsDebounced(true);
					if ( mapId && mapId !== 'undefined' ) {
						context.toggleIncidentLayersDebounced(true, mapId, targetMap);
					}
					// Emit event po zmianie widoczności warstw
					$(document).trigger('jet-country-layers/incidents-toggled', [true]);
				}, 1000);
			}
		},

		showCountryLayers: function(mapId, map) {
			var self = window.JetCountryLayers || this;
			
			if ( ! map || typeof map.getSource !== 'function' ) {
				return;
			}
			
			var sourceId = 'jet-country-source-' + mapId;
			
			// Check if source exists, if not, setup source first
			if ( ! map.getSource(sourceId) ) {
				// Source not ready yet, setup it first
				// But only if we have countries data, otherwise it will be set up later
				if ( self.hasCountriesData() ) {
				self.setupCountrySource(mapId, map);
				}
				return;
			}
			
			if ( self.layersAdded[mapId] ) {
				// Just show existing layers
				self.setLayersVisibility(mapId, map, 'visible');
				return;
			}

			var fillLayerId = 'jet-country-fill-' + mapId;
			var outlineLayerId = 'jet-country-outline-' + mapId;

			var baseFillColor = self.normalizeColor( self.noIncidentColor || JetCountryLayersData.noIncidentColor || '#3b82f6' );
			var incidentFillColor = self.normalizeColor( self.incidentColor || JetCountryLayersData.incidentColor || '#ef4444' );
			var baseBorderColor = self.normalizeColor( self.noIncidentBorderColor || self.noIncidentColor || baseFillColor );
			var incidentBorderColor = self.normalizeColor( self.incidentBorderColor || self.incidentColor || incidentFillColor );
			var noIncidentBorderWidth = parseFloat( self.noIncidentBorderWidth );
			var incidentBorderWidth = parseFloat( self.incidentBorderWidth );

			if ( isNaN( noIncidentBorderWidth ) ) {
				noIncidentBorderWidth = 1;
			}

			if ( isNaN( incidentBorderWidth ) ) {
				incidentBorderWidth = 2;
			}
			
			// Dynamiczna opacity w zależności od liczby incydentów
			var minOpacity = parseFloat( JetCountryLayersData.opacityMin || 0.2 );
			var maxOpacity = parseFloat( JetCountryLayersData.opacityMax || 0.9 );
			var maxIncidents = parseInt( JetCountryLayersData.opacityMaxIncidents || 50, 10 );
			
			if ( isNaN( minOpacity ) ) {
				minOpacity = 0.2;
			}
			if ( isNaN( maxOpacity ) ) {
				maxOpacity = 0.9;
			}
			if ( isNaN( maxIncidents ) || maxIncidents < 1 ) {
				maxIncidents = 50;
			}
			
			// Wyrażenie Mapbox dla dynamicznej opacity
			var fillOpacityExpression = [
				'case',
				// Jeśli incident_count = 0, użyj minimalnej opacity
				['==', ['coalesce', ['get', 'incident_count'], 0], 0],
				minOpacity,
				// W przeciwnym razie użyj interpolacji liniowej
				[
					'interpolate',
					['linear'],
					['coalesce', ['get', 'incident_count'], 0],
					0,   minOpacity,           // 0 incydentów = minOpacity
					1,   minOpacity + (maxOpacity - minOpacity) * 0.25,  // 1 incydent = 25% skali
					5,   minOpacity + (maxOpacity - minOpacity) * 0.5,   // 5 incydentów = 50% skali
					10,  minOpacity + (maxOpacity - minOpacity) * 0.75,  // 10 incydentów = 75% skali
					20,  minOpacity + (maxOpacity - minOpacity) * 0.9,   // 20 incydentów = 90% skali
					maxIncidents, maxOpacity   // maxIncidents+ = maxOpacity
				]
			];

			var fillColorExpression = [
				'case',
				['>', ['coalesce', ['get', 'incident_count'], 0], 0],
				incidentFillColor,
				baseFillColor
			];

			var outlineColorExpression = [
				'case',
				['>', ['coalesce', ['get', 'incident_count'], 0], 0],
				incidentBorderColor,
				baseBorderColor
			];

			var outlineWidthExpression = [
				'case',
				['>', ['coalesce', ['get', 'incident_count'], 0], 0],
				incidentBorderWidth,
				noIncidentBorderWidth
			];

			// Add fill layer
			if ( ! map.getLayer(fillLayerId) ) {
				map.addLayer({
					id: fillLayerId,
					type: 'fill',
					source: sourceId,
					paint: {
						'fill-color': fillColorExpression,
						'fill-opacity': fillOpacityExpression
					},
					layout: {
						'visibility': 'visible'
					}
				});
				try {
					map.moveLayer(fillLayerId);
				} catch (error) {
				}
			}

			if ( map.getLayer(fillLayerId) ) {
				try {
					map.setPaintProperty(fillLayerId, 'fill-color', fillColorExpression);
					map.setPaintProperty(fillLayerId, 'fill-opacity', fillOpacityExpression);
					map.setLayoutProperty(fillLayerId, 'visibility', 'visible');
				} catch (error) {
				}
			}

			// Add outline layer
			if ( ! map.getLayer(outlineLayerId) ) {
				map.addLayer({
					id: outlineLayerId,
					type: 'line',
					source: sourceId,
					paint: {
						'line-color': outlineColorExpression,
						'line-width': outlineWidthExpression,
						'line-opacity': 0.8
					}
				});
				try {
					map.moveLayer(outlineLayerId);
				} catch (error) {
				}
			}

			if ( map.getLayer(outlineLayerId) ) {
				try {
					map.setPaintProperty(outlineLayerId, 'line-color', outlineColorExpression);
					map.setPaintProperty(outlineLayerId, 'line-width', outlineWidthExpression);
					map.setLayoutProperty(outlineLayerId, 'visibility', 'visible');
				} catch (error) {
				}
			}

			self.layersAdded[mapId] = true;

			// Setup click handlers
			self.setupCountryClickHandlers(mapId, map, fillLayerId);

			// Setup hover effect
			self.setupHoverEffect(map, fillLayerId);
		},

		hideCountryLayers: function(mapId, map) {
			var self = window.JetCountryLayers || this;
			self.setLayersVisibility(mapId, map, 'none');
		},

		toggleIncidentLayersDebounced: function(showIncidents, mapId, map) {
			var self = this;
			var key = mapId || 'default';
			var now = Date.now();
			
			// Prevent calls more frequent than 300ms
			if ( self.lastToggleIncidentLayersCall[key] && (now - self.lastToggleIncidentLayersCall[key]) < 300 ) {
				// Clear existing timer and set new one
				if ( self.toggleIncidentLayersTimers[key] ) {
					clearTimeout(self.toggleIncidentLayersTimers[key]);
				}
				self.toggleIncidentLayersTimers[key] = setTimeout(function() {
					self.toggleIncidentLayers(showIncidents, mapId, map);
				}, 300);
				return;
			}
			
			self.lastToggleIncidentLayersCall[key] = now;
			self.toggleIncidentLayers(showIncidents, mapId, map);
		},

		toggleIncidentLayers: function(showIncidents, mapId, map) {
			var self = this;
			var visibility = showIncidents ? 'visible' : 'none';
			
			// If mapId is invalid, we'll apply to all maps at the end

			var applyToMap = function(targetMapId, targetMap) {
				if ( ! targetMap || ! targetMap.getStyle ) {
					return false;
				}

				var style = targetMap.getStyle();

				if ( ! style || ! style.layers ) {
					return false;
				}

				var affected = false;
				var affectedLayers = [];

				
				style.layers.forEach(function(layer) {
					if ( self.isIncidentLayer(layer.id) ) {
						try {
							targetMap.setLayoutProperty(layer.id, 'visibility', visibility);
							
							// For unclustered-point layer, keep it invisible (opacity 0)
							// This layer is only used by clustering system to detect which markers to show
							// The actual DOM markers (pins) are what should be visible, not the circles
							if (layer.id === 'unclustered-point') {
								try {
									if (visibility === 'visible') {
										// Keep circle invisible - only DOM markers should be visible
										targetMap.setPaintProperty(layer.id, 'circle-opacity', 0);
										// Keep radius small to avoid visual artifacts
										targetMap.setPaintProperty(layer.id, 'circle-radius', 1);
										
										// Trigger map idle event to ensure markers are rendered
										// This is needed because clustering system adds markers on 'idle' event
										if (typeof targetMap.trigger === 'function') {
											targetMap.trigger('idle');
										} else {
											// Force a repaint by slightly moving the map
											var currentCenter = targetMap.getCenter();
											targetMap.setCenter(currentCenter);
										}
									} else {
										// Hide layer completely
										targetMap.setPaintProperty(layer.id, 'circle-opacity', 0);
									}
								} catch (paintError) {
								}
							}
							
							affectedLayers.push(layer.id);
							affected = true;
						} catch (error) {
							// Ignore layers that do not support layout visibility changes
						}
					}
				});

				if ( affected ) {
				} else {
				}

				return affected;
			};

			var scheduleAttempts = function(targetMapId, targetMap) {
				var key = targetMapId || 'default';
				if ( ! self.incidentToggleAttempts[key] ) {
					self.incidentToggleAttempts[key] = { count: 0, timer: null };
				}

				var tracker = self.incidentToggleAttempts[key];
				if ( tracker.timer ) {
					try {
						clearTimeout(tracker.timer);
					} catch (error) {}
					tracker.timer = null;
				}
				tracker.count = 0;

				var attemptApply = function() {
					tracker.count++;
					var applied = applyToMap(targetMapId, targetMap);

					if ( ! applied && tracker.count < 15 ) {
						tracker.timer = setTimeout(attemptApply, 250);
					} else {
						tracker.timer = null;
					}
				};

				attemptApply();
			};

			if ( map && mapId ) {
				scheduleAttempts(mapId, map);
			} else {
				$.each(this.maps, function(eachMapId, mapInstance) {
					if ( eachMapId && eachMapId !== 'undefined' ) {
						scheduleAttempts(eachMapId, mapInstance.map || mapInstance);
					}
				});
			}
		},

		isIncidentLayer: function(layerId) {
			if ( ! layerId ) {
				return false;
			}

			var prefixes = ['geometry-polygon-', 'geometry-line-'];
			for ( var i = 0; i < prefixes.length; i++ ) {
				if ( layerId.indexOf(prefixes[i]) === 0 ) {
					return true;
				}
			}

			var knownLayers = ['clusters', 'cluster-count', 'unclustered-point'];
			return knownLayers.indexOf(layerId) !== -1;
		},

		toggleMarkerElementsDebounced: function(showMarkers) {
			var self = this;
			var now = Date.now();
			
			// Prevent calls more frequent than 300ms
			if ( self.lastToggleMarkerElementsCall && (now - self.lastToggleMarkerElementsCall) < 300 ) {
				// Clear existing timer and set new one
				if ( self.toggleMarkerElementsTimer ) {
					clearTimeout(self.toggleMarkerElementsTimer);
				}
				self.toggleMarkerElementsTimer = setTimeout(function() {
					self.toggleMarkerElements(showMarkers);
				}, 300);
				return;
			}
			
			self.lastToggleMarkerElementsCall = now;
			self.toggleMarkerElements(showMarkers);
		},

		toggleMarkerElements: function(showMarkers) {
			var self = this;
			var attempts = 0;
			var maxAttempts = 20;
			
			var applyToggle = function() {
				attempts++;
				var foundMarkers = false;
				var markersCount = 0;
				var domMarkersCount = 0;
				
				if ( ! window.JetEngineMaps ) {
					if ( attempts < maxAttempts ) {
						setTimeout(applyToggle, 250);
					}
					return;
				}

				// Toggle markers from JetEngineMaps.markersData
				if ( window.JetEngineMaps.markersData ) {
					var postIds = Object.keys(window.JetEngineMaps.markersData);
					
					Object.keys(window.JetEngineMaps.markersData).forEach(function(postId) {
						var markersArray = window.JetEngineMaps.markersData[postId];

						if ( ! Array.isArray(markersArray) ) {
							return;
						}


						markersArray.forEach(function(markerObj) {
							var marker = markerObj.marker || markerObj;

							if ( marker && marker._element ) {
								foundMarkers = true;
								markersCount++;
								var currentDisplay = window.getComputedStyle(marker._element).display;
								
								if ( showMarkers ) {
									var originalDisplay = marker._element.dataset.jetGeometryOriginalDisplay || 'block';
									marker._element.style.display = originalDisplay;
								} else {
									if ( ! marker._element.dataset.jetGeometryOriginalDisplay ) {
										var computedDisplay = '';
										try {
											computedDisplay = window.getComputedStyle(marker._element).display || '';
										} catch (error) {}
										marker._element.dataset.jetGeometryOriginalDisplay = marker._element.style.display || computedDisplay || 'block';
									}
									marker._element.style.display = 'none';
								}
							}

							if ( marker && typeof marker.getElement === 'function' ) {
								var element = marker.getElement();
								if ( ! element ) {
									return;
								}

								foundMarkers = true;
								markersCount++;
								var currentDisplay = window.getComputedStyle(element).display;

								if ( showMarkers ) {
									var original = element.dataset.jetGeometryOriginalDisplay || 'block';
									element.style.display = original;
								} else {
									if ( ! element.dataset.jetGeometryOriginalDisplay ) {
										var computed = '';
										try {
											computed = window.getComputedStyle(element).display || '';
										} catch (error) {}
										element.dataset.jetGeometryOriginalDisplay = element.style.display || computed || 'block';
									}
									element.style.display = 'none';
								}
							}
						});
					});
				} else {
				}

				// Also toggle all mapbox markers directly (fallback for markers not yet in markersData)
				// Note: With clustering enabled, markers may not be in DOM until map 'idle' event
				if ( typeof window.jQuery !== 'undefined' ) {
					var $allMarkers = window.jQuery('.mapboxgl-marker');
					domMarkersCount = $allMarkers.length;
					
					if ( domMarkersCount === 0 && showMarkers ) {
						// Markers not in DOM yet - they will be added by clustering on 'idle' event
						// Try to trigger idle event on all maps to force marker rendering
						
						// Find all map instances and trigger idle if possible
						if ( self.maps && Object.keys(self.maps).length > 0 ) {
							Object.keys(self.maps).forEach(function(mapId) {
								var mapInstance = self.maps[mapId];
								var map = mapInstance && mapInstance.map ? mapInstance.map : mapInstance;
								if ( map && typeof map.getCenter === 'function' ) {
									try {
										// Force map repaint to trigger idle event
										var center = map.getCenter();
										map.setCenter(center);
									} catch (e) {
									}
								}
							});
						}
					}
					
					$allMarkers.each(function() {
						var $marker = window.jQuery(this);
						var element = this;
						
						// Skip if this marker is for geometry (polygon/line)
						if ( $marker.closest('.jet-map-listing').find('.jet-geometry-markers-data').length > 0 ) {
							// Check if this marker has geometry data
							var $container = $marker.closest('.jet-map-listing');
							var markersData = $container.data('markers');
							if ( markersData && Array.isArray(markersData) ) {
								var markerId = $marker.data('post-id') || $marker.attr('data-post-id');
								if ( markerId ) {
									var hasGeometry = markersData.some(function(m) {
										return m.id == markerId && m.geometry_data && m.geometry_data.length > 0;
									});
									if ( hasGeometry ) {
										return; // Skip geometry markers
									}
								}
							}
						}
						
						foundMarkers = true;
						var currentDisplay = window.getComputedStyle(element).display;
						
						if ( showMarkers ) {
							var original = element.dataset.jetGeometryOriginalDisplay || 'block';
							element.style.display = original;
						} else {
							if ( ! element.dataset.jetGeometryOriginalDisplay ) {
								var computed = '';
								try {
									computed = window.getComputedStyle(element).display || '';
								} catch (error) {}
								element.dataset.jetGeometryOriginalDisplay = element.style.display || computed || 'block';
							}
							element.style.display = 'none';
						}
					});
				}

				// Toggle geometry layers visibility
				if ( window.JetEngineMaps && window.JetEngineMaps.layerVisibility ) {
					try {
						Object.keys(window.JetEngineMaps.layerVisibility).forEach(function(layerId) {
							if ( layerId.indexOf('geometry-') === 0 ) {
								var mapInstance = window.JetEngineMaps.mapInstance;
								if ( mapInstance && mapInstance.setLayoutProperty ) {
									mapInstance.setLayoutProperty(layerId, 'visibility', showMarkers ? 'visible' : 'none');
								}
							}
						});
					} catch (error) {
					}
				}

				// Retry if no markers found yet and we haven't exceeded max attempts
				if ( ! foundMarkers && attempts < maxAttempts ) {
					setTimeout(applyToggle, 250);
				} else if ( foundMarkers ) {
				} else {
				}
			};

			// Start applying toggle
			applyToggle();
		},

		setSelectedCountry: function(value, options) {
			var key = this.normalizeCountryKey(value);
			var sameKey = ( key === this.selectedCountryKey );

			if ( sameKey && this.selectedCountryFeature ) {
				if ( options && options.fit ) {
					this.applySelectedCountryToMaps(false, { fit: true });
				}
				return;
			}

			this.selectedCountryKey = key;

			if ( null === key ) {
				this.selectedCountryFeature = null;
				this.pendingSelectedCountry = null;
				this.applySelectedCountryToMaps(true);
				return;
			}

			if ( ! this.hasCountriesData() ) {
				this.pendingSelectedCountry = key;
				this.loadCountriesData();
				return;
			}

			var feature = this.findCountryFeature(key);

			if ( ! feature ) {
				this.selectedCountryFeature = null;
				this.applySelectedCountryToMaps(true);
				return;
			}

			this.selectedCountryFeature = feature;
			this.pendingSelectedCountry = null;

			var shouldFit = options && options.fit === true;
			this.applySelectedCountryToMaps(false, { fit: shouldFit });
		},

		clearSelectedCountry: function() {
			this.setSelectedCountry(null);
		},

		normalizeCountryKey: function(value) {
			if ( Array.isArray(value) ) {
				value = value.length ? value[0] : null;
			}

			if ( value && typeof value === 'object' ) {
				if ( typeof value.value !== 'undefined' ) {
					value = value.value;
				} else if ( typeof value.term_id !== 'undefined' ) {
					value = value.term_id;
				} else if ( typeof value.slug !== 'undefined' ) {
					value = value.slug;
				}
			}

			if ( null === value || typeof value === 'undefined' ) {
				return null;
			}

			var key = String(value).trim();

			if ( ! key ) {
				return null;
			}

			return key;
		},

		findCountryFeature: function(key) {
			if ( ! key ) {
				return null;
			}

			var idKey   = String(key);
			var slugKey = idKey.toLowerCase();
			var isoKey  = idKey.toUpperCase();

			if ( this.countryIndexById[idKey] ) {
				return this.countryIndexById[idKey];
			}

			if ( this.countryIndexBySlug[slugKey] ) {
				return this.countryIndexBySlug[slugKey];
			}

			if ( this.countryIndexByIso[isoKey] ) {
				return this.countryIndexByIso[isoKey];
			}

			return null;
		},

		ensureSelectedCountryLayers: function(mapId, map, feature, shouldFit) {
			if ( ! feature || ! map ) {
				this.removeSelectedCountryLayers(mapId, map);
				return;
			}

			var sourceId        = 'jet-selected-country-source-' + mapId;
			var fillLayerId     = 'jet-selected-country-fill-' + mapId;
			var outlineLayerId  = 'jet-selected-country-outline-' + mapId;
			var featureCollection = {
				type: 'FeatureCollection',
				features: [feature]
			};

			if ( ! map.getSource(sourceId) ) {
				map.addSource(sourceId, {
					type: 'geojson',
					data: featureCollection
				});
			} else {
				map.getSource(sourceId).setData(featureCollection);
			}

			var highlight    = this.selectedHighlightOptions || {};
			// Use settings from setupHighlightOptions (widget > global > default)
			var fillColor    = highlight.fillColor ? this.normalizeColor(highlight.fillColor) : '#f25f5c';
			var fillOpacity  = typeof highlight.fillOpacity === 'number' ? highlight.fillOpacity : 0.45;
			var outlineEnabled = highlight.outlineEnabled !== false && highlight.outlineEnabled !== 'false';
			var outlineColor = highlight.outlineColor ? this.normalizeColor(highlight.outlineColor) : '#f25f5c';
			var outlineWidth = typeof highlight.outlineWidth === 'number' ? highlight.outlineWidth : 2.5;

			if ( fillOpacity > 1 ) {
				fillOpacity = fillOpacity / 100;
			}

			// Add fill layer
			if ( ! map.getLayer(fillLayerId) ) {
				map.addLayer({
					id: fillLayerId,
					type: 'fill',
					source: sourceId,
					paint: {
						'fill-color': fillColor,
						'fill-opacity': fillOpacity
					}
				});
			} else {
				try {
					map.setPaintProperty(fillLayerId, 'fill-color', fillColor);
					map.setPaintProperty(fillLayerId, 'fill-opacity', fillOpacity);
					map.setLayoutProperty(fillLayerId, 'visibility', 'visible');
				} catch (error) {}
			}

			// Add outline layer only if enabled
			if ( outlineEnabled ) {
				if ( ! map.getLayer(outlineLayerId) ) {
					map.addLayer({
						id: outlineLayerId,
						type: 'line',
						source: sourceId,
						paint: {
							'line-color': outlineColor,
							'line-width': outlineWidth,
							'line-opacity': 0.95
						}
					});
				} else {
					try {
						map.setPaintProperty(outlineLayerId, 'line-color', outlineColor);
						map.setPaintProperty(outlineLayerId, 'line-width', outlineWidth);
						map.setLayoutProperty(outlineLayerId, 'visibility', 'visible');
					} catch (error) {}
				}
			} else {
				// Remove outline layer if disabled
				if ( map.getLayer(outlineLayerId) ) {
					try {
						map.removeLayer(outlineLayerId);
					} catch (error) {}
				}
			}

			// Move layers to top (only if they exist)
			try {
				if ( map.getLayer(fillLayerId) ) {
					map.moveLayer(fillLayerId);
				}
				if ( outlineEnabled && map.getLayer(outlineLayerId) ) {
					map.moveLayer(outlineLayerId);
				}
			} catch (error) {
				// Silently ignore move errors
			}

			this.selectedCountryLayers[mapId] = true;

			// Setup click handlers for the selected country layer
			// This allows clicking on the highlighted country to show popup with incident information
			this.setupCountryClickHandlers(mapId, map, fillLayerId);

			if ( shouldFit ) {
				this.fitMapToFeature(map, feature);
			}
		},

		removeSelectedCountryLayers: function(mapId, map) {
			if ( ! map ) {
				return;
			}

			var sourceId       = 'jet-selected-country-source-' + mapId;
			var fillLayerId    = 'jet-selected-country-fill-' + mapId;
			var outlineLayerId = 'jet-selected-country-outline-' + mapId;

			if ( map.getLayer(fillLayerId) ) {
				try {
					map.removeLayer(fillLayerId);
				} catch (error) {}
			}

			if ( map.getLayer(outlineLayerId) ) {
				try {
					map.removeLayer(outlineLayerId);
				} catch (error) {}
			}

			if ( map.getSource(sourceId) ) {
				try {
					map.removeSource(sourceId);
				} catch (error) {}
			}

			delete this.selectedCountryLayers[mapId];
		},

		fitMapToFeature: function(map, feature) {
			if ( ! map || ! feature || typeof map.fitBounds !== 'function' ) {
				return;
			}

			var bounds = this.computeFeatureBounds(feature);

			if ( ! bounds ) {
				return;
			}

			try {
				map.fitBounds(bounds, {
					padding: { top: 60, bottom: 60, left: 80, right: 80 },
					duration: 1200
				});
			} catch (error) {}
		},

		computeFeatureBounds: function(feature) {
			if ( ! feature || ! feature.geometry ) {
				return null;
			}

			var minLng = Infinity;
			var minLat = Infinity;
			var maxLng = -Infinity;
			var maxLat = -Infinity;

			var updateBounds = function(coord) {
				if ( ! coord || coord.length < 2 ) {
					return;
				}

				var lng = coord[0];
				var lat = coord[1];

				if ( typeof lng !== 'number' || typeof lat !== 'number' ) {
					return;
				}

				if ( lng < minLng ) { minLng = lng; }
				if ( lng > maxLng ) { maxLng = lng; }
				if ( lat < minLat ) { minLat = lat; }
				if ( lat > maxLat ) { maxLat = lat; }
			};

			var traverse = function(coords) {
				if ( ! Array.isArray(coords) ) {
					return;
				}

				if ( typeof coords[0] === 'number' ) {
					updateBounds(coords);
					return;
				}

				for ( var i = 0; i < coords.length; i++ ) {
					traverse(coords[i]);
				}
			};

			traverse(feature.geometry.coordinates);

			if ( ! isFinite(minLng) || ! isFinite(minLat) || ! isFinite(maxLng) || ! isFinite(maxLat) ) {
				return null;
			}

			return [
				[minLng, minLat],
				[maxLng, maxLat]
			];
		},

		setLayersVisibility: function(mapId, map, visibility) {
			if ( ! map || typeof map.getLayer !== 'function' ) {
				return;
			}
			
			var fillLayerId = 'jet-country-fill-' + mapId;
			var outlineLayerId = 'jet-country-outline-' + mapId;

			if ( map.getLayer(fillLayerId) ) {
				try {
					map.setLayoutProperty(fillLayerId, 'visibility', visibility);
				} catch (error) {}
			}

			if ( map.getLayer(outlineLayerId) ) {
				try {
					map.setLayoutProperty(outlineLayerId, 'visibility', visibility);
				} catch (error) {}
			}
		},

		setupHoverEffect: function(map, layerId) {
			map.on('mouseenter', layerId, function() {
				map.getCanvas().style.cursor = 'pointer';
			});

			map.on('mouseleave', layerId, function() {
				map.getCanvas().style.cursor = '';
			});
		},

		setupCountryClickHandlers: function(mapId, map, layerId) {
			var self = this;
			var clickStartPos = null;
			var clickStartTime = null;

			// Track click start position and time to distinguish clicks from drags
			// Only track when clicking on our layer
			map.on('mousedown', layerId, function(e) {
				clickStartTime = Date.now();
				if ( e.originalEvent ) {
					clickStartPos = {
						x: e.originalEvent.clientX,
						y: e.originalEvent.clientY
					};
				}
			});

			map.on('click', layerId, function(e) {
				// Check if this was a drag (moved more than 5px or took longer than 200ms)
				var wasDrag = false;
				if ( clickStartPos && e.originalEvent ) {
					var dx = Math.abs(e.originalEvent.clientX - clickStartPos.x);
					var dy = Math.abs(e.originalEvent.clientY - clickStartPos.y);
					var clickDuration = clickStartTime ? (Date.now() - clickStartTime) : 0;
					
					// If moved more than 5px or took longer than 200ms, it was likely a drag
					if ( dx > 5 || dy > 5 || clickDuration > 200 ) {
						wasDrag = true;
					}
				}

				if ( wasDrag ) {
					clickStartPos = null;
					clickStartTime = null;
					return;
				}

				if ( ! e.features || ! e.features[0] ) {
					return;
				}

				var feature = e.features[0];
				var termId = feature.properties.term_id;
				var countryName = feature.properties.name;

				// Fetch incidents for this country
				self.showCountryPopup(map, e.lngLat, termId, countryName);
				
				// Reset state
				clickStartPos = null;
				clickStartTime = null;
			});
		},

		/**
		 * Calculate optimal popup position to keep it within map bounds
		 */
		calculatePopupPosition: function(map, lngLat) {
			var offset = [0, 0];
			var anchor = 'bottom';
			
			if (!map || !lngLat) {
				return { offset: offset, anchor: anchor };
			}
			
			// Get map container bounds
			var container = map.getContainer();
			if (!container) {
				return { offset: offset, anchor: anchor };
			}
			
			var mapBounds = container.getBoundingClientRect();
			var mapWidth = mapBounds.width;
			var mapHeight = mapBounds.height;
			
			// Convert lngLat to pixel coordinates
			var point = map.project(lngLat);
			
			// Estimate popup dimensions (will be adjusted after render)
			var popupWidth = 400; // maxWidth
			var popupHeight = 300; // estimated height
			var popupOffsetY = 20; // default offset from marker
			
			// Check if popup would go outside map bounds
			var spaceRight = mapWidth - point.x;
			var spaceLeft = point.x;
			var spaceBottom = mapHeight - point.y;
			var spaceTop = point.y;
			
			// Horizontal positioning
			if (spaceRight < popupWidth / 2) {
				// Not enough space on right, shift left
				offset[0] = -(popupWidth / 2 - spaceRight + 10);
				anchor = 'left';
			} else if (spaceLeft < popupWidth / 2) {
				// Not enough space on left, shift right
				offset[0] = popupWidth / 2 - spaceLeft + 10;
				anchor = 'right';
			} else {
				// Center horizontally
				anchor = 'bottom';
			}
			
			// Vertical positioning - prefer showing below, but check both directions
			var minSpaceRequired = popupHeight + popupOffsetY + 20; // Add padding
			
			if (spaceTop < minSpaceRequired && spaceBottom >= minSpaceRequired) {
				// Not enough space above but enough below - show below
				offset[1] = popupOffsetY;
				// Keep anchor as is (bottom, left, or right)
			} else if (spaceBottom < minSpaceRequired && spaceTop >= minSpaceRequired) {
				// Not enough space below but enough above - show above
				offset[1] = -(popupHeight + popupOffsetY);
				if (anchor === 'bottom') {
					anchor = 'top';
				} else if (anchor === 'left') {
					anchor = 'top-left';
				} else if (anchor === 'right') {
					anchor = 'top-right';
				}
			} else if (spaceTop < minSpaceRequired && spaceBottom < minSpaceRequired) {
				// Not enough space in either direction - prefer below and adjust offset
				offset[1] = popupOffsetY;
				// Will be adjusted by adjustPopupPosition after render
			} else {
				// Enough space in both directions - prefer below
				offset[1] = popupOffsetY;
			}
			
			return { offset: offset, anchor: anchor };
		},

		/**
		 * Adjust popup position after render to ensure it's fully visible
		 */
		adjustPopupPosition: function(map, popup, lngLat) {
			// Popup is always centered on the map, so we just need to ensure it stays centered
			// when the map is moved or resized
			if (!map || !popup) {
				return;
			}
			
			var mapCenter = map.getCenter();
			if (!mapCenter) {
				return;
			}
			
			// Update popup position to map center
			popup.setLngLat(mapCenter);
			
			// Also ensure popup stays centered when map is resized or moved
			var self = this;
			var recenterPopup = function() {
				if (popup && !popup.isOpen()) {
					return;
				}
				var center = map.getCenter();
				if (center) {
					popup.setLngLat(center);
				}
			};
			
			// Listen for map resize and move events to keep popup centered
			if (!popup._recenterBound) {
				map.on('resize', recenterPopup);
				map.on('move', recenterPopup);
				popup._recenterBound = true;
				
				// Clean up on popup close
				popup.on('close', function() {
					map.off('resize', recenterPopup);
					map.off('move', recenterPopup);
					popup._recenterBound = false;
				});
			}
		},

		/**
		 * Force popup height to 220px to override Mapbox styles
		 */
		forcePopupHeight: function(popup) {
			if (!popup) {
				return;
			}
			
			var popupElement = popup.getElement();
			if (!popupElement) {
				return;
			}
			
			var contentElement = popupElement.querySelector('.mapboxgl-popup-content');
			if (contentElement) {
				contentElement.style.setProperty('height', '220px', 'important');
				contentElement.style.setProperty('max-height', '220px', 'important');
				contentElement.style.setProperty('min-height', '220px', 'important');
				contentElement.style.setProperty('box-sizing', 'border-box', 'important');
				contentElement.style.setProperty('display', 'flex', 'important');
				contentElement.style.setProperty('flex-direction', 'column', 'important');
				contentElement.style.setProperty('overflow', 'hidden', 'important');
			}
			
			// Also ensure inner popup and body have correct styles
			var innerPopup = popupElement.querySelector('.jet-country-popup');
			if (innerPopup) {
				innerPopup.style.setProperty('height', '100%', 'important');
				innerPopup.style.setProperty('max-height', '100%', 'important');
				innerPopup.style.setProperty('min-height', '0', 'important');
				innerPopup.style.setProperty('display', 'flex', 'important');
				innerPopup.style.setProperty('flex-direction', 'column', 'important');
				innerPopup.style.setProperty('overflow', 'hidden', 'important');
			}
			
			var bodyElement = popupElement.querySelector('.jet-country-popup__body');
			if (bodyElement) {
				bodyElement.style.setProperty('flex', '1 1 auto', 'important');
				bodyElement.style.setProperty('overflow-y', 'auto', 'important');
				bodyElement.style.setProperty('overflow-x', 'hidden', 'important');
				bodyElement.style.setProperty('min-height', '0', 'important');
				bodyElement.style.removeProperty('height'); // Remove height to allow content to be visible
			}
			
			// Ensure content-wrapper has correct styles
			var contentWrapper = popupElement.querySelector('.jet-country-popup__content-wrapper');
			if (contentWrapper) {
				contentWrapper.style.setProperty('display', 'flex', 'important');
				contentWrapper.style.setProperty('flex-direction', 'row', 'important');
				contentWrapper.style.setProperty('flex', '1 1 auto', 'important');
				contentWrapper.style.setProperty('min-height', '0', 'important');
				contentWrapper.style.setProperty('overflow', 'hidden', 'important');
			}
		},

		/**
		 * Update scroll indicators (arrow-up/down) opacity based on scroll position
		 * Always visible, but opacity changes to indicate scrollability
		 */
		updateScrollIndicators: function(popup) {
			if (!popup) {
				return;
			}
			
			var popupElement = popup.getElement();
			if (!popupElement) {
				return;
			}
			
			var bodyElement = popupElement.querySelector('.jet-country-popup__body');
			if (!bodyElement) {
				return;
			}
			
			var scrollUp = popupElement.querySelector('.jet-country-popup__scroll-up');
			var scrollDown = popupElement.querySelector('.jet-country-popup__scroll-down');
			
			if (!scrollUp || !scrollDown) {
				return;
			}
			
			// Check if content is scrollable
			var isScrollable = bodyElement.scrollHeight > bodyElement.clientHeight;
			
			if (!isScrollable) {
				// Hide if not scrollable
				scrollUp.style.opacity = '0';
				scrollUp.style.pointerEvents = 'none';
				scrollDown.style.opacity = '0';
				scrollDown.style.pointerEvents = 'none';
				return;
			}
			
			// Always show, but adjust opacity based on scroll position
			scrollUp.style.opacity = '1';
			scrollUp.style.pointerEvents = 'auto';
			scrollDown.style.opacity = '1';
			scrollDown.style.pointerEvents = 'auto';
			
			// Check scroll position for visual feedback
			var isAtTop = bodyElement.scrollTop <= 0;
			var isAtBottom = bodyElement.scrollTop + bodyElement.clientHeight >= bodyElement.scrollHeight - 1;
			
			// Dim arrows when at boundaries (but still visible)
			scrollUp.style.opacity = isAtTop ? '0.3' : '1';
			scrollDown.style.opacity = isAtBottom ? '0.3' : '1';
		},

		/**
		 * Initialize scroll indicators and event listeners
		 */
		initScrollIndicators: function(popup) {
			if (!popup) {
				return;
			}
			
			var popupElement = popup.getElement();
			if (!popupElement) {
				return;
			}
			
			var bodyElement = popupElement.querySelector('.jet-country-popup__body');
			if (!bodyElement) {
				return;
			}
			
			var self = this;
			
			// Get scroll buttons
			var scrollUp = popupElement.querySelector('.jet-country-popup__scroll-up');
			var scrollDown = popupElement.querySelector('.jet-country-popup__scroll-down');
			
			// Scroll functionality
			if (scrollUp) {
				scrollUp.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					var scrollAmount = bodyElement.clientHeight * 0.8; // Scroll 80% of visible height
					bodyElement.scrollBy({
						top: -scrollAmount,
						behavior: 'smooth'
					});
				});
			}
			
			if (scrollDown) {
				scrollDown.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					var scrollAmount = bodyElement.clientHeight * 0.8; // Scroll 80% of visible height
					bodyElement.scrollBy({
						top: scrollAmount,
						behavior: 'smooth'
					});
				});
			}
			
			// Update indicators on scroll
			var scrollHandler = function() {
				self.updateScrollIndicators(popup);
			};
			
			bodyElement.addEventListener('scroll', scrollHandler);
			
			// Update indicators on resize (if content changes)
			var resizeObserver = null;
			if (typeof ResizeObserver !== 'undefined') {
				resizeObserver = new ResizeObserver(function() {
					self.updateScrollIndicators(popup);
				});
				resizeObserver.observe(bodyElement);
			}
			
			// Fallback: use MutationObserver if ResizeObserver is not available
			var mutationObserver = null;
			if (typeof MutationObserver !== 'undefined' && !resizeObserver) {
				mutationObserver = new MutationObserver(function() {
					self.updateScrollIndicators(popup);
				});
				mutationObserver.observe(bodyElement, {
					childList: true,
					subtree: true,
					attributes: true
				});
			}
			
			// Clean up on popup close
			popup.on('close', function() {
				bodyElement.removeEventListener('scroll', scrollHandler);
				if (resizeObserver) {
					resizeObserver.disconnect();
				}
				if (mutationObserver) {
					mutationObserver.disconnect();
				}
			});
			
			// Initial update
			setTimeout(function() {
				self.updateScrollIndicators(popup);
			}, 100);
		},

		/**
		 * Initialize drag & drop for popup
		 */
		initPopupDrag: function(map, popup) {
			if (!popup || !map) {
				return;
			}

			var popupElement = popup.getElement();
			if (!popupElement) {
				return;
			}

			var dragHandle = popupElement.querySelector('.jet-country-popup__drag-handle');
			if (!dragHandle) {
				return;
			}

			var isDragging = false;
			var startMouseX = 0;
			var startMouseY = 0;
			var startPopupX = 0;
			var startPopupY = 0;

			// Make drag handle cursor pointer
			dragHandle.style.cursor = 'move';
			dragHandle.style.userSelect = 'none';

			// Mouse down on drag handle
			dragHandle.addEventListener('mousedown', function(e) {
				e.preventDefault();
				e.stopPropagation();
				isDragging = true;
				
				// Store initial mouse position
				startMouseX = e.clientX;
				startMouseY = e.clientY;
				
				// Get current transform
				var currentTransform = popupElement.style.transform || '';
				var translateMatch = currentTransform.match(/translate\(([^)]+)\)/);
				if (translateMatch) {
					var values = translateMatch[1].split(',');
					startPopupX = parseFloat(values[0].trim()) || 0;
					startPopupY = parseFloat(values[1].trim()) || 0;
				} else {
					startPopupX = 0;
					startPopupY = 0;
				}
				
				document.body.style.cursor = 'move';
				document.body.style.userSelect = 'none';
			});

			// Mouse move
			var mouseMoveHandler = function(e) {
				if (!isDragging) {
					return;
				}
				
				e.preventDefault();
				
				var container = map.getContainer();
				if (!container) {
					return;
				}
				
				var mapBounds = container.getBoundingClientRect();
				var popupBounds = popupElement.getBoundingClientRect();
				
				// Calculate delta from start position
				var deltaX = e.clientX - startMouseX;
				var deltaY = e.clientY - startMouseY;
				
				// Calculate new position
				var newX = startPopupX + deltaX;
				var newY = startPopupY + deltaY;
				
				// Constrain to map bounds
				var minX = mapBounds.left - popupBounds.left;
				var maxX = mapBounds.right - popupBounds.right;
				var minY = mapBounds.top - popupBounds.top;
				var maxY = mapBounds.bottom - popupBounds.bottom;
				
				newX = Math.max(minX, Math.min(maxX, newX));
				newY = Math.max(minY, Math.min(maxY, newY));
				
				// Apply transform
				popupElement.style.transform = 'translate(' + newX + 'px, ' + newY + 'px)';
			};

			document.addEventListener('mousemove', mouseMoveHandler);

			// Mouse up
			var mouseUpHandler = function(e) {
				if (!isDragging) {
					return;
				}
				
				isDragging = false;
				document.body.style.cursor = '';
				document.body.style.userSelect = '';
			};

			document.addEventListener('mouseup', mouseUpHandler);
			
			// Clean up event listeners when popup is closed
			popup.on('close', function() {
				document.removeEventListener('mousemove', mouseMoveHandler);
				document.removeEventListener('mouseup', mouseUpHandler);
			});

			// Prevent map panning when dragging popup
			dragHandle.addEventListener('mousedown', function(e) {
				e.stopPropagation();
			});
		},

		showCountryPopup: function(map, lngLat, termId, countryName) {
			// Show loading popup
			if ( typeof mapboxgl === 'undefined' || ! mapboxgl.Popup ) {
				return;
			}

			// Close previous popup if exists
			if ( this.currentPopup ) {
				this.currentPopup.remove();
				this.currentPopup = null;
				// Re-enable zoom when closing popup
				if (map && map.scrollZoom) {
					map.scrollZoom.enable();
				}
			}
			
			// Disable zoom when opening popup
			if (map && map.scrollZoom) {
				map.scrollZoom.disable();
			}

			var i18n = ( window.JetCountryLayersData && window.JetCountryLayersData.i18n ) ? window.JetCountryLayersData.i18n : {
				loading: 'Loading',
				loadError: 'Failed to load incidents',
				noIncidents: 'No incidents in this country',
				viewAll: 'View all',
				incidents: 'incidents',
				incidentTypes: 'Incident types',
				incidentTypeCount: 'incidents'
			};

			var loadingContent = '<div class="jet-country-popup">' +
				'<div class="jet-country-popup__header">' +
					'<div class="jet-country-popup__drag-handle" title="Drag to move">' +
						'<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="4" cy="4" r="1.5"/><circle cx="12" cy="4" r="1.5"/><circle cx="4" cy="8" r="1.5"/><circle cx="12" cy="8" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/></svg>' +
					'</div>' +
					'<h4 class="jet-country-popup__title">' + countryName + '</h4>' +
				'</div>' +
				'<div class="jet-country-popup__content-wrapper">' +
					'<div class="jet-country-popup__body">' +
						'<div class="jet-country-popup__loading">' + ( i18n.loading || 'Loading' ) + '...</div>' +
					'</div>' +
					'<div class="jet-country-popup__scroll-indicators">' +
						'<div class="jet-country-popup__scroll-up"></div>' +
						'<div class="jet-country-popup__scroll-down"></div>' +
					'</div>' +
				'</div>' +
				'</div>';

			// Center popup in the middle of the map (always centered, regardless of click position)
			var mapCenter = map.getCenter();
			var popup = new mapboxgl.Popup({
				className: 'jet-country-popup-container',
				offset: [0, 0],
				anchor: 'center',
				maxWidth: '400px',
				closeOnClick: false,
				closeButton: true
			})
				.setLngLat(mapCenter)
				.setHTML(loadingContent)
				.addTo(map);
			
			// Store current popup
			this.currentPopup = popup;
			
			// Handle popup close
			var self = this;
			popup.on('close', function() {
				if (self.currentPopup === popup) {
					self.currentPopup = null;
				}
				// Re-enable zoom when closing popup
				if (map && map.scrollZoom) {
					map.scrollZoom.enable();
				}
			});
			
			this.applyPopupTheme(popup);
			
			// Initialize drag & drop, scroll indicators and force height after popup is rendered
			var self = this;
			setTimeout(function() {
				self.forcePopupHeight(popup);
				// self.initPopupDrag(map, popup); // Disabled drag&drop functionality
				self.initScrollIndicators(popup);
				self.adjustPopupPosition(map, popup, lngLat);
			}, 100);

			// Fetch incidents
			$.ajax({
				url: JetCountryLayersData.restUrl + 'country-incidents/' + termId,
				method: 'GET',
				beforeSend: function(xhr) {
					if ( JetCountryLayersData && JetCountryLayersData.nonce ) {
					xhr.setRequestHeader('X-WP-Nonce', JetCountryLayersData.nonce);
					}
				},
				success: function(response) {
					var payload = ( response && typeof response === 'object' && response.data ) ? response.data : response;

					if ( payload && payload.incidents ) {
						var content = buildPopupContent(
							countryName,
							payload.incidents,
							payload.total,
							termId,
							payload.types || [],
							payload.types_total || 0
						);
						popup.setHTML(content);
						JetCountryLayers.applyPopupTheme(popup);
						// Force height, re-initialize scroll indicators and adjust position after content is updated
						setTimeout(function() {
							JetCountryLayers.forcePopupHeight(popup);
							// JetCountryLayers.initPopupDrag(map, popup); // Disabled drag&drop functionality
							JetCountryLayers.initScrollIndicators(popup);
							JetCountryLayers.adjustPopupPosition(map, popup, lngLat);
						}, 50);
					} else {
						popup.setHTML(
							'<div class="jet-country-popup">' +
								'<div class="jet-country-popup__header">' +
									'<div class="jet-country-popup__drag-handle" title="Drag to move">' +
										'<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="4" cy="4" r="1.5"/><circle cx="12" cy="4" r="1.5"/><circle cx="4" cy="8" r="1.5"/><circle cx="12" cy="8" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/></svg>' +
									'</div>' +
									'<h4 class="jet-country-popup__title">' + countryName + '</h4>' +
								'</div>' +
								'<div class="jet-country-popup__content-wrapper">' +
									'<div class="jet-country-popup__body">' +
										'<p class="jet-country-popup__error">' + ( i18n.loadError || 'Failed to load incidents' ) + '</p>' +
									'</div>' +
									'<div class="jet-country-popup__scroll-indicators">' +
										'<div class="jet-country-popup__scroll-up"></div>' +
										'<div class="jet-country-popup__scroll-down"></div>' +
									'</div>' +
								'</div>' +
							'</div>'
						);
						JetCountryLayers.applyPopupTheme(popup);
						// Force height, re-initialize scroll indicators and adjust position after content is updated
						setTimeout(function() {
							JetCountryLayers.forcePopupHeight(popup);
							// JetCountryLayers.initPopupDrag(map, popup); // Disabled drag&drop functionality
							JetCountryLayers.initScrollIndicators(popup);
							JetCountryLayers.adjustPopupPosition(map, popup, lngLat);
						}, 50);
					}
				},
				error: function(xhr, textStatus, errorThrown) {
					popup.setHTML(
						'<div class="jet-country-popup">' +
							'<div class="jet-country-popup__header">' +
								'<div class="jet-country-popup__drag-handle" title="Drag to move">' +
									'<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="4" cy="4" r="1.5"/><circle cx="12" cy="4" r="1.5"/><circle cx="4" cy="8" r="1.5"/><circle cx="12" cy="8" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/></svg>' +
								'</div>' +
								'<h4 class="jet-country-popup__title">' + countryName + '</h4>' +
							'</div>' +
							'<div class="jet-country-popup__content-wrapper">' +
								'<div class="jet-country-popup__body">' +
									'<p class="jet-country-popup__error">' + ( i18n.loadError || 'Failed to load incidents' ) + '</p>' +
								'</div>' +
								'<div class="jet-country-popup__scroll-indicators">' +
									'<div class="jet-country-popup__scroll-up"></div>' +
									'<div class="jet-country-popup__scroll-down"></div>' +
								'</div>' +
							'</div>' +
						'</div>'
					);
					JetCountryLayers.applyPopupTheme(popup);
					// Force height, re-initialize scroll indicators and adjust position after content is updated
					setTimeout(function() {
						JetCountryLayers.forcePopupHeight(popup);
						// JetCountryLayers.initPopupDrag(map, popup); // Disabled drag&drop functionality
						JetCountryLayers.initScrollIndicators(popup);
						JetCountryLayers.adjustPopupPosition(map, popup, lngLat);
					}, 50);
				}
			});

			function buildPopupContent(name, incidents, total, termId, types, typesTotal) {
				var html = '<div class="jet-country-popup">';

				html += '<div class="jet-country-popup__header">';
				html += '<div class="jet-country-popup__drag-handle" title="Drag to move">';
				html += '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="4" cy="4" r="1.5"/><circle cx="12" cy="4" r="1.5"/><circle cx="4" cy="8" r="1.5"/><circle cx="12" cy="8" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/></svg>';
				html += '</div>';
				var titleText = name;
				if (total !== undefined && total !== null && total > 0) {
					var incidentWord = total === 1 ? 'incident' : 'incidents';
					titleText = name + ' - ' + total + ' ' + incidentWord;
				}
				html += '<h4 class="jet-country-popup__title">' + titleText + '</h4>';
				html += '</div>';

				html += '<div class="jet-country-popup__content-wrapper">';
				html += '<div class="jet-country-popup__body">';

				if ( types && types.length ) {
					html += '<div class="incident-types">';
					html += '<table class="incident-types__table">';
					html += '<tbody>';

					types.forEach(function(type) {
						html += '<tr class="incident-types__row">';
						html += '<td class="incident-types__cell incident-types__cell--name">' + type.name + '</td>';
						html += '<td class="incident-types__cell incident-types__cell--count">' + type.count + '</td>';
						html += '</tr>';
					});

					html += '</tbody></table>';
					html += '</div>';
				}

				// Incidents table intentionally hidden for now; leave placeholder for future use
				if ( incidents.length === 0 ) {
					html += '<p class="no-incidents">' + i18n.noIncidents + '</p>';
				}

				html += '</div>'; // body

				html += '<div class="jet-country-popup__scroll-indicators">';
				html += '<div class="jet-country-popup__scroll-up">';
				html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>';
				html += '</div>';
				html += '<div class="jet-country-popup__scroll-down">';
				html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
				html += '</div>';
				html += '</div>'; // scroll-indicators

				html += '</div>'; // content-wrapper

				html += '</div>'; // jet-country-popup

				return html;
			}
		},

		applyPopupTheme: function(popup) {
			if ( ! popup || typeof popup.getElement !== 'function' ) {
				return;
			}

			var container = popup.getElement();
			if ( ! container ) {
				return;
			}

			var content = container.querySelector('.mapboxgl-popup-content');
			if ( content ) {
				content.style.background = '#1e484b';
				content.style.borderRadius = '14px';
				content.style.boxShadow = '0 18px 40px rgba(0, 0, 0, 0.45)';
				content.style.color = '#ffffff';
				content.style.padding = '20px 22px';
				content.style.maxWidth = '320px';
			}

			var tip = container.querySelector('.mapboxgl-popup-tip');
			if ( tip ) {
				// Hide popup tip
				tip.style.display = 'none';
				tip.style.visibility = 'hidden';
				tip.style.opacity = '0';
				tip.style.width = '0';
				tip.style.height = '0';
				tip.style.border = 'none';
			}
			
			// Hide drag handle
			var dragHandle = container.querySelector('.jet-country-popup__drag-handle');
			if ( dragHandle ) {
				dragHandle.style.display = 'none';
				dragHandle.style.visibility = 'hidden';
				dragHandle.style.opacity = '0';
				dragHandle.style.width = '0';
				dragHandle.style.height = '0';
				dragHandle.style.padding = '0';
				dragHandle.style.margin = '0';
			}

			var close = container.querySelector('.mapboxgl-popup-close-button');
			if ( close ) {
				close.style.color = '#ffffff';
				close.style.background = 'transparent';
				close.style.borderRadius = '50%';
				close.style.width = '28px';
				close.style.height = '28px';
				close.style.lineHeight = '28px';
				close.style.fontSize = '1.5rem';
				close.style.display = 'flex';
				close.style.alignItems = 'center';
				close.style.justifyContent = 'center';
				
				// Also style the span inside the button
				var closeSpan = close.querySelector('span');
				if ( closeSpan ) {
					closeSpan.style.fontSize = '1.5rem';
					closeSpan.style.lineHeight = '1';
					closeSpan.style.display = 'inline-block';
				}
			}

			var textNodes = container.querySelectorAll('.jet-country-popup, .jet-country-popup *');
			textNodes.forEach(function(node) {
				if ( node && node.style ) {
					node.style.color = '#ffffff';
				}
			});
		},

		resetMapZoom: function() {
			var self = this;
			$.each(this.maps, function(mapId, mapInstance) {
				if ( mapInstance && mapInstance.map ) {
					// Try to get general settings from map container (same logic as Elementor widget)
					var $map = $('.jet-map-listing').first();
					var generalSettings = $map.length ? $map.data('general') : null;
					
					// Try to get reset zoom from any Reset Map Zoom button on the page
					var $resetButton = $('.jet-reset-map-zoom').first();
					var resetZoom = $resetButton.length ? parseFloat($resetButton.data('reset-zoom')) : NaN;
					
					// Use customCenter from settings, or default to Europe
					var defaultCenter = (generalSettings && generalSettings.customCenter) 
						? generalSettings.customCenter 
						: { lat: 50.0, lng: 15.0 }; // Central Europe
					
					// Priority: widget setting > map setting > default
					var defaultZoom = !isNaN(resetZoom) && resetZoom > 0
						? resetZoom
						: ((generalSettings && generalSettings.customZoom) 
							? generalSettings.customZoom 
							: 4); // Zoom level to see most of Europe
					
					mapInstance.map.flyTo({
						center: [defaultCenter.lng, defaultCenter.lat],
						zoom: defaultZoom,
						duration: 1500
					});
				}
			});
		},

		normalizeColor: function(color) {
			if ( ! color || typeof color !== 'string' ) {
				return color;
			}

			var hex8Match = color.match(/^#([A-Fa-f0-9]{8})$/);
			if ( hex8Match ) {
				return this.hex8ToRgba( hex8Match[0] );
			}

			return color;
		},

		hex8ToRgba: function(hex) {
			if ( ! hex || hex.length !== 9 ) {
				return hex;
			}

			var r = parseInt(hex.substr(1, 2), 16);
			var g = parseInt(hex.substr(3, 2), 16);
			var b = parseInt(hex.substr(5, 2), 16);
			var a = parseInt(hex.substr(7, 2), 16) / 255;

			return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + a.toFixed(3) + ')';
		},

		parseNumericValue: function(value, fallback) {
			var parsed = parseFloat(value);

			if ( isNaN(parsed) ) {
				return fallback;
			}

			return parsed;
		},

		setupGeometryClickHandlers: function(mapId, map, $container) {
			var self = this;
			
			// Wait for map to be ready
			var setupHandlers = function() {
				// Get general settings from container
				var generalData = $container.attr('data-general');
				var general = {};
				if (generalData) {
					try {
						// Safe decoding: try decodeURIComponent first, then jQuery.html().text()
						var decoded = generalData;
						try {
							decoded = decodeURIComponent(generalData);
						} catch (e) {
							// If decodeURIComponent fails, try jQuery method
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
				var showMarkerPopup = function(postId, coordinates, lngLat) {
					if (!general.api || !general.listingID) {
						return;
					}
					
					var querySeparator = general.querySeparator || '?';
					var api = general.api +
						querySeparator +
						'listing_id=' + general.listingID +
						'&post_id=' + postId +
						'&source=' + (general.source || 'post') +
						'&geo_query_distance=' + (general.geo_query_distance || '');
					
					var queriedID = $container.data('queried-id');
					if (queriedID) {
						api += '&queried_id=' + queriedID;
					}
					
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
				
				// Handle clicks on unclustered-point layer (invisible circles used by clustering)
				// Make it clickable with pointer cursor
				map.on('mouseenter', 'unclustered-point', function() {
					map.getCanvas().style.cursor = 'pointer';
				});
				map.on('mouseleave', 'unclustered-point', function() {
					map.getCanvas().style.cursor = '';
				});
				
				map.on('click', 'unclustered-point', function(e) {
					var feature = e.features && e.features[0];
					if (!feature || !feature.properties) {
						return;
					}
					
					var markerKey = feature.properties.markerKey;
					if (!markerKey) {
						return;
					}
					
					// Find marker in JetEngineMaps.markersData by coordinates
					var postId = null;
					if (window.JetEngineMaps && window.JetEngineMaps.markersData) {
						Object.keys(window.JetEngineMaps.markersData).forEach(function(id) {
							var markersArray = window.JetEngineMaps.markersData[id];
							if (Array.isArray(markersArray)) {
								markersArray.forEach(function(markerObj) {
									var marker = markerObj.marker || markerObj;
									if (marker && typeof marker.getLngLat === 'function') {
										try {
											var lngLat = marker.getLngLat();
											var key = '' + lngLat.lng + lngLat.lat;
											if (key === markerKey) {
												postId = id;
											}
										} catch (err) {
											// Marker might not be ready yet
										}
									}
								});
							}
						});
					}
					
					if (postId && e.lngLat) {
						showMarkerPopup(postId, [e.lngLat.lng, e.lngLat.lat], e.lngLat);
					} else {
					}
				});
				
				// Function to setup handlers for geometry layers
				var setupGeometryLayerHandlers = function() {
					var style = map.getStyle();
					if (!style || !style.layers) {
						return;
					}
					
					style.layers.forEach(function(layer) {
						if (!layer.id) {
							return;
						}
						
						// Handle geometry-line layers
						if (layer.id.indexOf('geometry-line-') === 0 || layer.id.indexOf('jet-geometry-lines-layer-') === 0) {
							// Remove existing handlers if any
							map.off('click', layer.id);
							map.off('mouseenter', layer.id);
							map.off('mouseleave', layer.id);
							
							map.on('click', layer.id, function(e) {
								var feature = e.features && e.features[0];
								var postId = null;
								
								// Try to get postId from feature properties
								if (feature && feature.properties) {
									postId = feature.properties.id || feature.properties.post_id;
								}
								
								// If not in properties, try to extract from layer ID
								// Format: geometry-line-{postId}-{index}-layer
								if (!postId && layer.id.indexOf('geometry-line-') === 0) {
									var match = layer.id.match(/geometry-line-(\d+)-/);
									if (match && match[1]) {
										postId = match[1];
									}
								}
								
								if (postId && e.lngLat) {
									showMarkerPopup(postId, [e.lngLat.lng, e.lngLat.lat], e.lngLat);
								} else {
								}
							});
							
							// Make cursor pointer on hover
							map.on('mouseenter', layer.id, function() {
								map.getCanvas().style.cursor = 'pointer';
							});
							map.on('mouseleave', layer.id, function() {
								map.getCanvas().style.cursor = '';
							});
							
						}
						
						// Handle geometry-polygon layers
						if (layer.id.indexOf('geometry-polygon-') === 0 || 
							layer.id.indexOf('jet-geometry-polygons-fill-') === 0 ||
							layer.id.indexOf('jet-geometry-polygons-outline-') === 0) {
							// Remove existing handlers if any
							map.off('click', layer.id);
							map.off('mouseenter', layer.id);
							map.off('mouseleave', layer.id);
							
							map.on('click', layer.id, function(e) {
								var feature = e.features && e.features[0];
								var postId = null;
								
								// Try to get postId from feature properties
								if (feature && feature.properties) {
									postId = feature.properties.id || feature.properties.post_id;
								}
								
								// If not in properties, try to extract from layer ID
								// Format: geometry-polygon-{postId}-{index}-layer
								if (!postId && layer.id.indexOf('geometry-polygon-') === 0) {
									var match = layer.id.match(/geometry-polygon-(\d+)-/);
									if (match && match[1]) {
										postId = match[1];
									}
								}
								
								// For jet-geometry-polygons-fill-{mapId}, need to check source data
								if (!postId && layer.id.indexOf('jet-geometry-polygons-fill-') === 0) {
									var sourceId = 'jet-geometry-polygons-' + mapId;
									if (map.getSource(sourceId)) {
										var source = map.getSource(sourceId);
										if (source && source._data && feature) {
											// Find feature in source data
											var sourceFeatures = source._data.features || [];
											for (var i = 0; i < sourceFeatures.length; i++) {
												if (sourceFeatures[i] === feature || 
													(sourceFeatures[i].properties && feature.properties && 
													 sourceFeatures[i].properties.id === feature.properties.id)) {
													postId = sourceFeatures[i].properties.id || sourceFeatures[i].properties.post_id;
													break;
												}
											}
										}
									}
								}
								
								if (postId && e.lngLat) {
									showMarkerPopup(postId, [e.lngLat.lng, e.lngLat.lat], e.lngLat);
								} else {
								}
							});
							
							// Make cursor pointer on hover
							map.on('mouseenter', layer.id, function() {
								map.getCanvas().style.cursor = 'pointer';
							});
							map.on('mouseleave', layer.id, function() {
								map.getCanvas().style.cursor = '';
							});
							
						}
					});
				};
				
				// Setup handlers immediately
				setupGeometryLayerHandlers();
				
				// Also setup handlers when new layers are added (for dynamically added geometry)
				map.on('style.load', function() {
					setTimeout(setupGeometryLayerHandlers, 100);
				});
				
				// Also setup handlers on map idle event (for dynamically added geometry layers)
				map.on('idle', function() {
					setTimeout(setupGeometryLayerHandlers, 100);
				});
				
			};
			
			// Setup handlers when map is ready
			if (map.loaded && map.loaded()) {
				setupHandlers();
			} else {
				map.once('load', setupHandlers);
			}
		}
	};

	// Initialize
	JetCountryLayers.init();
	if ( window.JetCountryLayersData ) {
			var dataInfo = JetCountryLayersData.countriesUrl || 'inline';
	}

})(jQuery);




