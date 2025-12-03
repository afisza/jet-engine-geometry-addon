/**
 * Elementor Editor Preview for Geometry Addon
 * Handles live updates of geometry styling in Elementor editor
 */
(function($) {
	'use strict';

	$(window).on('elementor/frontend/init', function() {

		// Listen for widget rendering
		elementorFrontend.hooks.addAction('frontend/element_ready/jet-engine-maps-listing.default', function($scope) {
			
			// Small delay to ensure our frontend script has processed
			setTimeout(function() {
				// Trigger re-rendering of geometries when widget updates
				$scope.find('.jet-map-listing').removeClass('loading');
			}, 4000);
		});
	});

	// Listen for Elementor editor panel changes
	if (window.elementor && elementor.channels) {
		elementor.channels.editor.on('change', function(controlView, elementView) {
			// Check if it's one of our geometry controls
			var controlName = controlView.model.get('name');
			
			if (controlName && controlName.indexOf('geometry_') === 0) {
				
				// Refresh the widget preview
				setTimeout(function() {
					if (elementView && elementView.renderHTML) {
						elementView.renderHTML();
					}
				}, 100);
			}
		});
	}

})(jQuery);













