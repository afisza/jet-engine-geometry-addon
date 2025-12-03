/**
 * Elementor Widgets Frontend JavaScript
 */
(function($) {
	'use strict';


	var JetGeometryWidgets = {

		// Store map instances
		mapInstances: {},

		init: function() {
			this.initResetMapZoom();
			this.initCountryLayersToggle();
			this.initTimelineFilter();
			this.initIncidentCounter();
			
			// Listen for filter updates to refresh counter
			// This event is triggered when JetSmartFilters updates the map content
			$(document).on('jet-filter-custom-content-render', '.jet-map-listing', function(event, response) {
				var count = 0;
				var source = 'unknown';
				
				if (response && response.markers && Array.isArray(response.markers)) {
					count = response.markers.length;
					source = 'response.markers';
					
				// If markers array is empty, wait and try to get from JetEngineMaps.markersData
				// DO NOT use map.data('markers') - it contains old, unfiltered data!
				if (count === 0) {
					setTimeout(function() {
						var $map = $(this);
						var mapInstance = $map.data('mapInstance');
						
						// Try JetEngineMaps.markersData (count unique post IDs)
						if (window.JetEngineMaps && window.JetEngineMaps.markersData) {
							var uniquePostIds = {};
							var totalMarkers = 0;
							for (var markerId in window.JetEngineMaps.markersData) {
								if (window.JetEngineMaps.markersData[markerId] && Array.isArray(window.JetEngineMaps.markersData[markerId])) {
									if (!uniquePostIds[markerId]) {
										uniquePostIds[markerId] = true;
										totalMarkers++;
									}
								}
							}
							if (totalMarkers > 0) {
								count = totalMarkers;
								source = 'JetEngineMaps.markersData (delayed)';
							}
						}
						// Try map instance source
						if (count === 0 && mapInstance && mapInstance.getSource && mapInstance.getSource('markers')) {
							var mapSource = mapInstance.getSource('markers');
							if (mapSource && mapSource._data && mapSource._data.features) {
								count = mapSource._data.features.length;
								source = 'mapInstance.source (delayed)';
							}
						}
						
						if (count > 0) {
							var activeFilters = getActiveFiltersInfo();
							JetGeometryWidgets.updateIncidentCounter(count, {
								source: source,
								filters: activeFilters
							});
						}
					}.bind(this), 2000);
				}
				} else {
					// Try to get count from map data immediately
					var $map = $(this);
					var markersData = $map.data('markers');
					if (markersData && Array.isArray(markersData)) {
						count = markersData.length;
						source = 'map.data(markers)';
					}
				}
				
				// Get active filters info
				var activeFilters = getActiveFiltersInfo();
				
				// Only update if we have a count (or if count is 0 and we're sure it should be 0)
				if (count >= 0) {
					JetGeometryWidgets.updateIncidentCounter(count, {
						source: source,
						filters: activeFilters
					});
				}
			});
			
			// Listen for JetSmartFilters applied event (when filters are applied)
			$(document).on('jet-smart-filters/after-ajax-content', function(event, data) {
				// Wait a bit for map to update, then get marker count
				setTimeout(function() {
					var $map = $('.jet-map-listing').first();
					if ($map.length) {
						var markersData = $map.data('markers');
						var count = 0;
						
						if (markersData && Array.isArray(markersData)) {
							count = markersData.length;
						} else {
							// Try alternative: get from map instance
							var mapInstance = $map.data('mapInstance');
							if (mapInstance && mapInstance.getSource && mapInstance.getSource('markers')) {
								var source = mapInstance.getSource('markers');
								if (source && source._data && source._data.features) {
									count = source._data.features.length;
								}
							}
						}
						
						if (count > 0) {
							var activeFilters = getActiveFiltersInfo();
							JetGeometryWidgets.updateIncidentCounter(count, {
								source: 'after-ajax-content',
								filters: activeFilters
							});
						}
					}
				}, 500);
			});
			
			// Listen for JetSmartFilters reset/clear event
			$(document).on('jet-smart-filters/clear-filters', function(event) {
				// Wait for map to reset, then update counter
				setTimeout(function() {
					var $map = $('.jet-map-listing').first();
					if ($map.length) {
						var markersData = $map.data('markers');
						if (markersData) {
							JetGeometryWidgets.updateIncidentCounter(markersData.length);
						}
					}
				}, 500);
			});
			
			// Listen for JetSmartFilters filter change events
			$(document).on('change', '.jet-smart-filters-wrapper select, .jet-smart-filters-wrapper input[type="checkbox"], .jet-smart-filters-wrapper input[type="radio"]', function() {
				// This will be handled by the ajax-content event, but we can also listen here
				setTimeout(function() {
					var $map = $('.jet-map-listing').first();
					if ($map.length) {
						var markersData = $map.data('markers');
						if (markersData) {
							JetGeometryWidgets.updateIncidentCounter(markersData.length);
						}
					}
				}, 1000);
			});
		},

		/**
		 * Initialize Reset Map Zoom buttons
		 */
		initResetMapZoom: function() {
			
			$(document).on('click', '.jet-reset-map-zoom', function(e) {
				e.preventDefault();
				
				// Get reset zoom level from button data attribute (from widget settings)
				var $button = $(this);
				var resetZoom = parseFloat($button.data('reset-zoom'));
				
				// Find the nearest map
				var $map = $('.jet-map-listing').first();
				
				if (!$map.length) {
					return;
				}
				
				var mapInstance = $map.data('mapInstance');
				var generalSettings = $map.data('general');
				
				if (!mapInstance) {
					return;
				}
				
				// Reset to default center (Europe) and zoom from widget settings
				var defaultCenter = generalSettings && generalSettings.customCenter 
					? generalSettings.customCenter 
					: { lat: 50.0, lng: 15.0 }; // Central Europe
				
				// Use zoom from widget settings, or fallback to map settings, or default to 4
				var defaultZoom = !isNaN(resetZoom) && resetZoom > 0 
					? resetZoom 
					: (generalSettings && generalSettings.customZoom ? generalSettings.customZoom : 4);
				
				mapInstance.flyTo({
					center: [defaultCenter.lng, defaultCenter.lat],
					zoom: defaultZoom,
					duration: 1500
				});
			});
		},

		/**
		 * Initialize Country Layers Toggle
		 */
		initCountryLayersToggle: function() {

			var storageKey = (window.JetCountryLayers && window.JetCountryLayers.toggleStorageKey) ? window.JetCountryLayers.toggleStorageKey : 'jetCountryLayersToggle';
			var savedValue = null;

			if (window.localStorage) {
				try {
					savedValue = localStorage.getItem(storageKey);
				} catch (error) {
				}
			}

			var initialChecked = $('.jet-country-layers-checkbox').first().is(':checked');

			if (savedValue !== null) {
				var shouldCheck = savedValue === 'on';
				$('.jet-country-layers-checkbox').prop('checked', shouldCheck);
				window.__JetCountryLayersPendingState = shouldCheck;
			} else {
				window.__JetCountryLayersPendingState = initialChecked;
			}
			
			$(document).on('change', '.jet-country-layers-checkbox', function() {
				var isChecked = $(this).is(':checked');
				var $wrapper = $(this).closest('.jet-country-layers-toggle-wrapper');
				window.__JetCountryLayersPendingState = isChecked;

				if (window.JetCountryLayers && typeof window.JetCountryLayers.updateStyleOptionsFromElement === 'function') {
					window.JetCountryLayers.updateStyleOptionsFromElement($wrapper, true);
				}
				
				if (window.JetCountryLayers && typeof window.JetCountryLayers.toggleCountryLayers === 'function') {
					window.JetCountryLayers.toggleCountryLayers(isChecked, { persist: true });
				} else if (!window.__JetCountryLayersWaitScheduled) {
					window.__JetCountryLayersWaitScheduled = true;
					var waitForPlugin = function(attempts) {
						attempts = attempts || 0;
						if (window.JetCountryLayers && typeof window.JetCountryLayers.toggleCountryLayers === 'function') {
							window.JetCountryLayers.toggleCountryLayers(window.__JetCountryLayersPendingState, { persist: true });
							window.__JetCountryLayersWaitScheduled = false;
							return;
						}
						if (attempts < 10) {
							setTimeout(function() {
								waitForPlugin(attempts + 1);
							}, 300);
						} else {
							window.__JetCountryLayersWaitScheduled = false;
							window.__JetCountryLayersUnavailable = true;
						}
					};
					waitForPlugin();
				} else if (window.__JetCountryLayersUnavailable) {
					// Fallback for legacy behaviour
					var $map = $('.jet-map-listing').first();
					
					if (!$map.length) {
						return;
					}
					
					var mapInstance = $map.data('mapInstance');
					
					if (!mapInstance) {
						return;
					}
					
					if (isChecked) {
						JetGeometryWidgets.showCountryLayers(mapInstance);
					} else {
						JetGeometryWidgets.hideCountryLayers(mapInstance);
					}
				}
			});
			
			// Initialize on page load respecting persisted state
			setTimeout(function() {
				// Use saved state from localStorage instead of checkbox state
				// This ensures consistency with what was saved
				var savedState = null;
				if (window.localStorage) {
					try {
						var raw = localStorage.getItem(storageKey);
						if (raw === 'on') {
							savedState = true;
						} else if (raw === 'off') {
							savedState = false;
						}
					} catch (error) {
					}
				}
				
				// If no saved state, use checkbox state
				var isChecked = (savedState !== null) ? savedState : $('.jet-country-layers-checkbox').first().is(':checked');
				
				$('.jet-country-layers-checkbox').each(function() {
					var $wrapper = $(this).closest('.jet-country-layers-toggle-wrapper');

					if (window.JetCountryLayers && typeof window.JetCountryLayers.updateStyleOptionsFromElement === 'function') {
						window.JetCountryLayers.updateStyleOptionsFromElement($wrapper, false);
					}

					if (window.JetCountryLayers && typeof window.JetCountryLayers.toggleCountryLayers === 'function') {
						// Use saved state, not checkbox state
						window.JetCountryLayers.toggleCountryLayers(isChecked, { persist: false });
					} else {
						window.__JetCountryLayersPendingState = isChecked;

						if (!window.__JetCountryLayersWaitScheduled) {
							window.__JetCountryLayersWaitScheduled = true;
							var waitForPlugin = function(attempts) {
								attempts = attempts || 0;
								if (window.JetCountryLayers && typeof window.JetCountryLayers.toggleCountryLayers === 'function') {
									window.JetCountryLayers.toggleCountryLayers(window.__JetCountryLayersPendingState, { persist: false });
									window.__JetCountryLayersWaitScheduled = false;
									return;
								}
								if (attempts < 10) {
									setTimeout(function() {
										waitForPlugin(attempts + 1);
									}, 300);
								} else {
									window.__JetCountryLayersWaitScheduled = false;
									window.__JetCountryLayersUnavailable = true;
								}
							};
							waitForPlugin();
						}

						if (window.__JetCountryLayersUnavailable) {
							var $map = $('.jet-map-listing').first();
							var mapInstance = $map.data('mapInstance');
							
							if (mapInstance) {
								if (isChecked) {
									JetGeometryWidgets.showCountryLayers(mapInstance);
								} else {
									JetGeometryWidgets.hideCountryLayers(mapInstance);
								}
							}
						}
					}
				});
			}, 2000);
		},

		/**
		 * Show country layers on map
		 */
		showCountryLayers: function(mapInstance) {
			
			// Check if country layers source exists
			if (!mapInstance.getSource('country-layers')) {
				// Country layers will be added by the country-layers.php component
				// Trigger custom event to load country layers
				$(document).trigger('jet-geometry/load-country-layers', [mapInstance]);
				return;
			}
			
			// Show existing layers
			var layers = ['country-layers-fill', 'country-layers-outline'];
			layers.forEach(function(layerId) {
				if (mapInstance.getLayer(layerId)) {
					mapInstance.setLayoutProperty(layerId, 'visibility', 'visible');
				}
			});
		},

		/**
		 * Hide country layers on map
		 */
		hideCountryLayers: function(mapInstance) {
			
			var layers = ['country-layers-fill', 'country-layers-outline'];
			layers.forEach(function(layerId) {
				if (mapInstance.getLayer(layerId)) {
					mapInstance.setLayoutProperty(layerId, 'visibility', 'none');
				}
			});
		},

		/**
		 * Initialize Timeline Filter
		 */
		initTimelineFilter: function() {
			var self = this;

			var setup = function(scope) {
				var $scope = scope ? $(scope) : $(document);
				$scope.find('.jet-timeline-filter').each(function() {
					self.bindTimelineFilter($(this));
				});
			};

			setup();

			$(window).on('elementor/frontend/init', function() {
				setup();
			});

			if ('MutationObserver' in window) {
				var observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (!mutation.addedNodes) {
							return;
						}

						mutation.addedNodes.forEach(function(node) {
							if (node.nodeType !== 1) {
								return;
							}

							var $node = $(node);
							if ($node.hasClass('jet-timeline-filter')) {
								self.bindTimelineFilter($node);
							} else if ($node.find('.jet-timeline-filter').length) {
								setup($node);
							}
						});
					});
				});

				observer.observe(document.body, { childList: true, subtree: true });
			}
		},

		/**
		 * Bind timeline filter interactions
		 */
		bindTimelineFilter: function($widget) {
			if ($widget.data('timelineInitialized')) {
				return;
			}

			var $rangeWrapper = $widget.find('.jet-range');
			var $minInput     = $rangeWrapper.find('.jet-range__slider__input--min');
			var $maxInput     = $rangeWrapper.find('.jet-range__slider__input--max');

			if (!$rangeWrapper.length || !$minInput.length || !$maxInput.length) {
				return;
			}

			var minDefault = parseFloat($minInput.attr('min')) || 0;
			var maxDefault = parseFloat($maxInput.attr('max')) || 0;
			var currentMin = parseFloat($minInput.val()) || minDefault;
			var currentMax = parseFloat($maxInput.val()) || maxDefault;
			var step       = parseFloat($widget.data('step')) || 1;

			$widget.data('timelineInitialized', true);
			$widget.data('timelineDefaults', { min: minDefault, max: maxDefault });
			$widget.data('timelinePrevious', { min: currentMin, max: currentMax });

			this.renderTimelineTicks($widget, minDefault, maxDefault, step);
			this.updateTimelineDisplay($widget, currentMin, currentMax);

			var self = this;

			var inputHandler = function() {
				var valueMin = parseFloat($minInput.val());
				var valueMax = parseFloat($maxInput.val());

				if (valueMin > valueMax) {
					var tmp = valueMin;
					valueMin = valueMax;
					valueMax = tmp;
					$minInput.val(valueMin);
					$maxInput.val(valueMax);
				}

				self.updateTimelineDisplay($widget, valueMin, valueMax);
				$widget.data('timelinePrevious', { min: valueMin, max: valueMax });
			};

			$minInput.on('input change', inputHandler);
			$maxInput.on('input change', inputHandler);

			$widget.find('.jet-timeline-filter__reset').on('click', function(e) {
				e.preventDefault();
				var defaults = $widget.data('timelineDefaults');
				$minInput.val(defaults.min).trigger('input').trigger('change');
				$maxInput.val(defaults.max).trigger('input').trigger('change');
			});

			var $toggle = $widget.find('.jet-timeline-filter__toggle');
			var defaultEnabled = $widget.data('defaultEnabled');

			var disableTimeline = function() {
				$widget.removeClass('is-enabled').addClass('is-disabled');
				$toggle.attr('data-state', 'unchecked').attr('aria-checked', 'false');

				var previous = $widget.data('timelinePrevious');
				$widget.data('timelineSaved', previous);

				$minInput.prop('disabled', true);
				$maxInput.prop('disabled', true);

				var defaults = $widget.data('timelineDefaults');
				$minInput.val(defaults.min).trigger('input').trigger('change');
				$maxInput.val(defaults.max).trigger('input').trigger('change');
			};

			var enableTimeline = function() {
				$widget.removeClass('is-disabled').addClass('is-enabled');
				$toggle.attr('data-state', 'checked').attr('aria-checked', 'true');

				$minInput.prop('disabled', false);
				$maxInput.prop('disabled', false);

				var restored = $widget.data('timelineSaved') || $widget.data('timelineDefaults');
				$minInput.val(restored.min).trigger('input').trigger('change');
				$maxInput.val(restored.max).trigger('input').trigger('change');
			};

			$toggle.on('click', function(e) {
				e.preventDefault();
				if ($widget.hasClass('is-enabled')) {
					disableTimeline();
				} else {
					enableTimeline();
				}
			});

			if (defaultEnabled === 'no') {
				disableTimeline();
			} else {
				enableTimeline();
			}
		},

		/**
		 * Render tick marks for the slider
		 */
		renderTimelineTicks: function($widget, min, max, step) {
			var $ticksContainer = $widget.find('[data-range-ticks]');

			if (!$ticksContainer.length) {
				return;
			}

			$ticksContainer.empty();

			var ticks = [];
			var maxTicks = 11;
			var range = max - min;

			if (range <= 0) {
				ticks = [min];
			} else {
				if (step <= 0) {
					step = 1;
				}

				var totalSteps = Math.floor(range / step);

				if (totalSteps + 1 <= maxTicks) {
					for (var value = min; value <= max; value += step) {
						ticks.push(value);
					}
					if (ticks[ticks.length - 1] !== max) {
						ticks.push(max);
					}
				} else {
					var sections = maxTicks - 1;
					var interval = range / sections;

					for (var i = 0; i <= sections; i++) {
						ticks.push(min + interval * i);
					}
				}
			}

			ticks.forEach(function(tick) {
				var $item = $('<div/>', { 'class': 'jet-timeline-filter__tick' });
				$item.append($('<div/>', { 'class': 'jet-timeline-filter__tick-bar' }));
				var displayValue = Math.abs(tick % 1) < 0.0001 ? Math.round(tick) : parseFloat(tick.toFixed(2));
				$item.append($('<span/>').text(displayValue));
				$ticksContainer.append($item);
			});
		},

		/**
		 * Update timeline display text
		 */
		updateTimelineDisplay: function($widget, minValue, maxValue) {
			var formatValue = function(value) {
				if (!isFinite(value)) {
					return value;
				}

				return Math.abs(value % 1) < 0.0001 ? Math.round(value) : parseFloat(value.toFixed(2));
			};

			var displayMin = formatValue(minValue);
			var displayMax = formatValue(maxValue);

			$widget.find('[data-range-summary]').text(displayMin + ' - ' + displayMax);
			var fromTemplate = $widget.data('fromTemplate') || 'From: %s';
			var toTemplate   = $widget.data('toTemplate') || 'To: %s';
			$widget.find('[data-range-from]').text(fromTemplate.replace('%s', displayMin));
			$widget.find('[data-range-to]').text(toTemplate.replace('%s', displayMax));
		},

		/**
		 * Update incidents counter inside timeline widget
		 */
		updateTimelineIncidents: function(count) {
			$('.jet-timeline-filter').each(function() {
				var $widget      = $(this);
				var singular     = $widget.data('incidentSingular') || 'incident';
				var plural       = $widget.data('incidentPlural') || 'incidents';
				var label        = count === 1 ? singular : plural;
				var $countHolder = $widget.find('[data-incidents-count]');
				var $labelHolder = $widget.find('[data-incidents-label]');

				$countHolder.text(count);
				$labelHolder.text(label);
			});
		},

		/**
		 * Initialize Incident Counter
		 */
		initIncidentCounter: function() {
			var self = this;
			
			// Function to get and update counter from map markers
			var updateCounterFromMap = function(attempt) {
				attempt = attempt || 0;
				var $map = $('.jet-map-listing').first();
				
				if (!$map.length) {
					// Retry if map not ready yet (max 5 attempts)
					if (attempt < 5) {
						setTimeout(function() {
							updateCounterFromMap(attempt + 1);
						}, 500);
					}
					return;
				}
				
				var count = 0;
				var markersData = $map.data('markers');
				
				// If markers data exists, use it (this will be filtered if filters are active)
				if (markersData && Array.isArray(markersData)) {
					count = markersData.length;
				} else {
					// Fallback: try to get markers from map instance
					var mapInstance = $map.data('mapInstance');
					if (mapInstance && mapInstance.getSource && mapInstance.getSource('markers')) {
						var source = mapInstance.getSource('markers');
						if (source && source._data && source._data.features) {
							count = source._data.features.length;
						}
					}
				}
				
				if (count > 0) {
					var activeFilters = getActiveFiltersInfo();
					self.updateIncidentCounter(count, {
						source: 'init',
						filters: activeFilters
					});
				} else if (attempt < 5) {
					// If still no data, wait a bit more and retry
					setTimeout(function() {
						updateCounterFromMap(attempt + 1);
					}, 500);
				}
			};
			
			// Wait for map to load and update counter
			// Try multiple times to ensure we get the filtered count if filters are active
			setTimeout(function() { updateCounterFromMap(0); }, 1000);
			setTimeout(function() { updateCounterFromMap(3); }, 2000);
			setTimeout(function() { updateCounterFromMap(4); }, 3000);
		},

		/**
		 * Update incident counter
		 */
		updateIncidentCounter: function(count, debugInfo) {
			$('.jet-incident-counter').each(function() {
				var $counter = $(this);
				var singular = $counter.data('singular') || 'incident';
				var plural = $counter.data('plural') || 'incidents';
				var text = count === 1 ? singular : plural;
				
				$counter.find('.jet-incident-count').text(count);
				$counter.find('.jet-incident-text').text(text);
			});

			this.updateTimelineIncidents(count);

			if (window.JetCountryLayers && window.JetCountryLayers.countryToggleState) {
				window.JetCountryLayers.toggleIncidentLayers(false);
			}
		}
	};

	// Initialize on DOM ready
	$(document).ready(function() {
		JetGeometryWidgets.init();
	});

	// Make it available globally
	window.JetGeometryWidgets = JetGeometryWidgets;
	
	/**
	 * Get active filters information for debugging
	 */
	function getActiveFiltersInfo() {
		var filters = {};
		var filterInfo = [];
		
		// Check for JetSmartFilters select filters (proper structure)
		$('.jet-select').each(function() {
			var $wrapper = $(this);
			var queryVar = $wrapper.data('query-var') || $wrapper.attr('data-query-var') || 'unknown';
			var $select = $wrapper.find('select');
			
			if ($select.length) {
				var value = $select.val();
				var selectedOption = $select.find('option:selected');
				var label = selectedOption.text() || selectedOption.data('label') || value;
				
				if (value && value !== '' && value !== '0' && (!Array.isArray(value) || value.length > 0)) {
					if (Array.isArray(value)) {
						value = value.join(', ');
					}
					filters[queryVar] = {
						value: value,
						label: label
					};
					// Show only label in summary, not the ID value
					filterInfo.push(queryVar + ': ' + label);
				}
			}
		});
		
		// Check for select filters in general (fallback)
		$('select.jet-select__control, .jet-smart-filters-wrapper select').each(function() {
			var $select = $(this);
			var $wrapper = $select.closest('.jet-select');
			var queryVar = $wrapper.length && $wrapper.data('query-var') 
				? $wrapper.data('query-var')
				: ($select.closest('.jet-smart-filters-wrapper').data('query-var') || 
				   $select.data('query-var') || 
				   $select.attr('name') || 
				   'unknown');
			var value = $select.val();
			var selectedOption = $select.find('option:selected');
			var label = selectedOption.text() || value;
			
			// Skip if already found
			if (filters[queryVar]) {
				return;
			}
			
			if (value && value !== '' && value !== '0' && (!Array.isArray(value) || value.length > 0)) {
				if (Array.isArray(value)) {
					value = value.join(', ');
				}
				filters[queryVar] = {
					value: value,
					label: label
				};
				// Show only label in summary, not the ID value
				filterInfo.push(queryVar + ': ' + label);
			}
		});
		
		// Check for checkbox filters
		$('.jet-smart-filters-wrapper input[type="checkbox"]:checked, .jet-checkboxes input[type="checkbox"]:checked').each(function() {
			var $checkbox = $(this);
			var $wrapper = $checkbox.closest('.jet-checkboxes, .jet-smart-filters-wrapper');
			var queryVar = $wrapper.data('query-var') || 
			              $checkbox.data('query-var') || 
			              $checkbox.attr('name') || 
			              'unknown';
			var value = $checkbox.val();
			var label = $checkbox.closest('label').text().trim() || value;
			
			if (!filters[queryVar]) {
				filters[queryVar] = {
					value: [],
					labels: []
				};
			}
			if (Array.isArray(filters[queryVar].value)) {
				filters[queryVar].value.push(value);
				filters[queryVar].labels.push(label);
			}
		});
		
		// Convert checkbox arrays to strings for display
		for (var key in filters) {
			if (filters[key].value && Array.isArray(filters[key].value)) {
				var values = filters[key].value.join(', ');
				var labels = filters[key].labels ? filters[key].labels.join(', ') : values;
				var index = filterInfo.findIndex(function(item) {
					return item.indexOf(key + ':') === 0;
				});
				// Show only labels in summary, not the values
				if (index >= 0) {
					filterInfo[index] = key + ': ' + labels;
				} else {
					filterInfo.push(key + ': ' + labels);
				}
			}
		}
		
		// Check for radio filters
		$('.jet-smart-filters-wrapper input[type="radio"]:checked, .jet-radio-list input[type="radio"]:checked').each(function() {
			var $radio = $(this);
			var $wrapper = $radio.closest('.jet-radio-list, .jet-smart-filters-wrapper');
			var queryVar = $wrapper.data('query-var') || 
			              $radio.data('query-var') || 
			              $radio.attr('name') || 
			              'unknown';
			var value = $radio.val();
			var label = $radio.closest('label').text().trim() || value;
			
			// Skip if already found
			if (filters[queryVar]) {
				return;
			}
			
			if (value && value !== '' && value !== '0') {
				filters[queryVar] = {
					value: value,
					label: label
				};
				// Show only label in summary, not the ID value
				var index = filterInfo.findIndex(function(item) {
					return item.indexOf(queryVar + ':') === 0;
				});
				if (index < 0) {
					filterInfo.push(queryVar + ': ' + label);
				}
			}
		});
		
		return {
			filters: filters,
			summary: filterInfo.length > 0 ? filterInfo.join(' | ') : 'No active filters'
		};
	}
	
	// Make function available globally for debugging
	window.getActiveFiltersInfo = getActiveFiltersInfo;

})(jQuery);




