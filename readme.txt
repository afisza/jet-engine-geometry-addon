=== JetEngine Geometry Addon ===
Contributors: alexshram
Tags: jetengine, maps, geometry, mapbox, geojson
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.3.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends JetEngine Maps Listing with Line and Polygon geometry support, plus country layers integration with Mapbox API.

== Description ==

JetEngine Geometry Addon extends the powerful JetEngine Maps Listing module with advanced geometry capabilities:

**Features:**

* **Extended Geometry Types**: Add Lines and Polygons in addition to standard Pins
* **Interactive Drawing**: Draw geometries directly on the map using Mapbox GL Draw
* **Country Layers**: Import and display country boundaries with automatic incident aggregation
* **Flexible Input**: Set locations by drawing, geocoding, or entering coordinates
* **Smart Integration**: Works seamlessly with JetEngine CPT, CCT, and Meta Fields
* **Country Popups**: Click on country layers to see incidents within that country
* **GeoJSON Support**: Full GeoJSON format support for geometry storage

**Requirements:**

* WordPress 6.0 or higher
* JetEngine 3.0 or higher
* JetEngine Maps Listings module must be active
* Mapbox API key

**Use Cases:**

* Incident tracking systems with area definitions
* Route planning and visualization
* Geographic coverage areas
* Country-specific data visualization
* Multi-geometry incident reports

== Installation ==

1. Install and activate JetEngine plugin
2. Activate the Maps Listings module in JetEngine → Modules
3. Upload `jet-engine-geometry-addon` folder to the `/wp-content/plugins/` directory
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Configure Mapbox API key in JetEngine → Maps Listings → Settings
6. Go to JetEngine → Geometry Addon to configure additional settings

== Frequently Asked Questions ==

= Does this plugin require JetEngine? =

Yes, this plugin extends JetEngine's Maps Listings functionality and cannot work without it.

= Which map provider is supported? =

Currently, the plugin is optimized for Mapbox GL JS. Google Maps support may be added in future versions.

= Can I import country boundaries? =

Yes! Go to JetEngine → Geometry Addon → Country Layers to import GeoJSON data from Natural Earth or other sources.

= Is the plugin compatible with Elementor/Gutenberg/Bricks? =

Yes, the plugin integrates with JetEngine's existing page builder integrations.

== Screenshots ==

1. Geometry field in post editor with drawing tools
2. Map listing showing mixed geometry types (pins, lines, polygons)
3. Country layers with incident popup
4. Admin settings page for country import
5. Drawing interface in action

== Changelog ==

= 1.0.3.7 =
* Added "Check for updates" link in plugin action links for manual update checking
* Improved update check functionality with cache clearing
* Added success notice after manual update check

= 1.0.3.6 =
* Fixed "Show Country Layers" toggle not working on mobile devices (iPhone/iOS)
* Improved touch event handling for mobile compatibility
* Increased touch target size to 44px (iOS recommended size)
* Added proper touch event handlers (touchstart/touchend)
* Fixed checkbox visibility issues on iOS Safari
* Improved CSS for better mobile responsiveness

= 1.0.3.5 =
* Added visual distinction for subcategories in incident-type filter (prefix "- " before subcategory names)
* Fixed date range filter compatibility with Map Listing markers
* Improved AJAX handling for term parent checking
* Fixed taxonomy name handling (incident-type vs incident_types)

= 1.0.3.4 =
* Fixed GitHub Updater version comparison issue - now uses get_plugin_data() instead of constant
* Added wp_clean_plugins_cache() to refresh plugin cache after update
* Resolved update loop issue where plugin version was not updating correctly after installation

= 1.0.3.3 =
* Fixed MultiPolygon import issue for countries with overseas territories (e.g., France, Austria)
* Added extract_main_polygon function to select the largest polygon (mainland) from MultiPolygon geometries
* Countries with multiple territories now correctly import with mainland geometry instead of small overseas territories

= 1.0.3.2 =
* Added GitHub Updater integration for automatic updates from GitHub Releases
* Plugin can now be updated directly from WordPress Admin
* Automatic version checking every 12 hours

= 1.0.3.1 =
* Fixed ISO code import issue for countries with -99 placeholder (e.g., France)
* Added support for ISO_A2_EH field from Natural Earth Data
* Improved ISO code validation to filter invalid values
* Countries now correctly import with proper ISO codes (e.g., FR for France)

= 1.0.0 =
* Initial release
* Map Geometry field type
* Line and Polygon support
* Country layers functionality
* Mapbox GL Draw integration
* GeoJSON import/export
* Country incident aggregation

== Upgrade Notice ==

= 1.0.0 =
Initial release of JetEngine Geometry Addon.

