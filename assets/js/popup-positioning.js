/**
 * Popup Positioning Fix
 * Positions popup at map center (upper part) when opened
 */
(function($) {
	'use strict';

	var initializedMaps = {};

	function setupPopupPositioning(map, $container) {
		if (!map || !map.on) {
			return;
		}

		var mapId = map._container ? map._container.id : 'map_' + Date.now();
		
		// Skip if already initialized
		if (initializedMaps[mapId]) {
			return;
		}
		initializedMaps[mapId] = true;


		// Listen for popup open events
		map.on('popupopen', function(e) {
			var popup = e.popup;
			if (!popup) {
				return;
			}

			// Close all other popups on this map when opening new one
			// This prevents multiple popups from opening when markers are close together
			// Do this immediately to ensure only one popup is open
			var allPopups = map._popups || [];
			for (var i = 0; i < allPopups.length; i++) {
				if (allPopups[i] !== popup && allPopups[i].isOpen && allPopups[i].isOpen()) {
					allPopups[i].remove();
				}
			}

			// Also close any popups found in DOM (fallback for edge cases)
			var popupElement = popup.getElement ? $(popup.getElement()).closest('.mapboxgl-popup') : null;
			var $otherPopups = $('.mapboxgl-popup', map._container).not(popupElement || null);
			$otherPopups.each(function() {
				var $otherPopup = $(this);
				// Find the popup instance associated with this element
				if ($otherPopup.data('mapboxgl-popup')) {
					var otherPopupInstance = $otherPopup.data('mapboxgl-popup');
					if (otherPopupInstance !== popup && otherPopupInstance.isOpen && otherPopupInstance.isOpen()) {
						otherPopupInstance.remove();
					}
				} else {
					$otherPopup.remove();
				}
			});

			// Wait for popup to be fully rendered
			setTimeout(function() {
				if (popup && popup.isOpen && popup.isOpen()) {
					// Get map center
					var center = map.getCenter();
					var bounds = map.getBounds();
					
					if (!center || !bounds) {
						return;
					}

					// Calculate position at center horizontally, but offset upward (25% from top)
					var centerLat = bounds.getNorth() - (bounds.getNorth() - bounds.getSouth()) * 0.25;
					
					// Set popup position to center of map (upper part)
					popup.setLngLat([center.lng, centerLat]);
				}
			}, 100);
		});
	}

	function initPopupPositioning() {
		// Process all existing map listings
		$('.jet-map-listing').each(function() {
			var $container = $(this);
			var mapInstance = $container.data('mapInstance');
			
			if (!mapInstance) {
				return;
			}

			var map = mapInstance.map || mapInstance;
			if (!map || !map.on) {
				return;
			}

			setupPopupPositioning(map, $container);
		});
	}

	// Initialize when JetEngine Maps is loaded
	$(window).on('jet-engine/frontend-maps/loaded', function() {
		setTimeout(initPopupPositioning, 500);
	});

	// Fallback if already loaded
	if (typeof window.JetEngineMaps !== 'undefined') {
		$(document).ready(function() {
			setTimeout(initPopupPositioning, 500);
		});
	}

	// Also intercept map initialization events
	$(document).on('jet-engine/maps-listing/init', function(e, data) {
		if (!data || !data.mapInstance) {
			return;
		}

		var mapInstance = data.mapInstance;
		var map = mapInstance.map || mapInstance;
		var $container = data.$container || $(map._container).closest('.jet-map-listing');

		if (!map || !$container.length) {
			return;
		}

		setTimeout(function() {
			setupPopupPositioning(map, $container);
		}, 500);
	});

})(jQuery);
