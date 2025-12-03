# JetEngine Geometry Addon

Extends JetEngine Maps Listing with Line and Polygon geometry support, plus country layers integration with Mapbox API.

## Features

### Core Functionality

- **Extended Geometry Types**: Add Lines and Polygons in addition to standard Pins
- **Interactive Drawing Interface**: Draw geometries directly on the map using Mapbox GL Draw
- **Country Layers**: Import and display country boundaries with automatic incident aggregation
- **Flexible Input Methods**:
  - Draw on map
  - Geocoding search
  - Manual coordinate entry
- **GeoJSON Support**: Full GeoJSON format support for geometry storage
- **Smart Integration**: Works seamlessly with JetEngine CPT, CCT, and Meta Fields

### Country Layers

- Import country boundaries from Natural Earth Data
- Multiple resolution options (1:10m, 1:50m, 1:110m)
- Region filtering (World, Europe)
- Automatic matching with `countries` taxonomy
- Interactive map toggle
- Click country to see incidents popup
- Automatic incident aggregation by country

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- JetEngine 3.0 or higher
- JetEngine Maps Listings module (active)
- Mapbox API key

## Installation

1. Install and activate JetEngine plugin
2. Activate the Maps Listings module in JetEngine → Modules
3. Upload `jet-engine-geometry-addon` folder to `/wp-content/plugins/`
4. Activate the plugin through the 'Plugins' menu
5. Configure Mapbox API key in JetEngine → Maps Listings → Settings
6. Go to JetEngine → Geometry Addon to configure

## Usage

### Creating Geometry Fields

1. Go to JetEngine → Meta Boxes
2. Create or edit a meta box
3. Add a new field
4. Select field type: **Map Geometry**
5. Configure options:
   - Allowed Geometry Types (Pin, Line, Polygon)
   - Default Geometry Type
   - Map Height
   - Line/Polygon Colors
   - Fill Opacity

### Drawing Geometries

When editing a post with a geometry field:

1. **Select Geometry Type**: Choose Pin, Line, or Polygon
2. **Draw on Map**:
   - **Pin**: Click on map to place marker
   - **Line**: Click points to create line, double-click to finish
   - **Polygon**: Click points to create shape, double-click to finish
3. **Or Search**: Use the search box to find location by address
4. **Or Enter Coordinates**: Type coordinates in lat,lng format

### Displaying on Frontend

Add a JetEngine Map Listing widget to display geometries:

1. Create/edit page with Elementor
2. Add "JetEngine Map Listing" widget
3. Configure query to show your CPT (e.g., Incidents)
4. The addon automatically renders Lines and Polygons

### Country Layers Setup

1. Go to **JetEngine → Geometry Addon**
2. Click **Country Layers** tab
3. Configure import settings:
   - **Source**: Natural Earth Data
   - **Resolution**: 1:50m (recommended)
   - **Region**: Europe or World
4. Click **Start Import**
5. Wait for import to complete

The addon will match countries with your existing `countries` taxonomy terms.

### Enabling Country Layers on Frontend

Add this to your map listing settings or use the global setting:

```php
// In settings
'enable_country_layers' => true
```

Or go to **JetEngine → Geometry Addon → General Settings** and enable globally.

## Data Storage

### Geometry Data Structure

For each geometry field, the following meta fields are created:

```
{field_name}_geometry_type  (pin, line, polygon)
{field_name}_geometry_data  (GeoJSON string)
{field_name}_lat            (centroid latitude)
{field_name}_lng            (centroid longitude)
```

### Example GeoJSON Storage

**Pin:**
```json
{
  "type": "Point",
  "coordinates": [-74.0060, 40.7128]
}
```

**Line:**
```json
{
  "type": "LineString",
  "coordinates": [
    [-74.0060, 40.7128],
    [-73.9352, 40.7306]
  ]
}
```

**Polygon:**
```json
{
  "type": "Polygon",
  "coordinates": [[
    [-74.0, 40.7],
    [-73.9, 40.7],
    [-73.9, 40.8],
    [-74.0, 40.8],
    [-74.0, 40.7]
  ]]
}
```

### Country Data Storage

Country GeoJSON is stored in term meta:

```
_country_geojson            (full GeoJSON)
_country_geojson_simplified (simplified for performance)
_country_iso_code           (ISO 3166-1 alpha-2)
_country_geojson_source     (data source)
_country_geojson_imported   (import timestamp)
```

## Hooks & Filters

### Actions

```php
// After plugin initialization
do_action( 'jet-geometry-addon/init', $plugin_instance );
```

### Filters

```php
// Modify geometry field prefix
add_filter( 'jet-geometry-addon/field-prefix', function( $prefix, $field_name ) {
    return $prefix;
}, 10, 2 );

// Add custom geometry data to map
add_filter( 'jet-engine/maps-listing/map-data', function( $data, $settings, $render ) {
    // Modify map data
    return $data;
}, 10, 3 );
```

## REST API Endpoints

### Validate Geometry

```
POST /wp-json/jet-geometry/v1/validate-geometry
Body: {
  "geometry": {...GeoJSON...}
}
```

### Import Countries

```
POST /wp-json/jet-geometry/v1/countries/import
Body: {
  "source": "natural-earth",
  "resolution": "50m",
  "region": "europe"
}
```

### Get Countries GeoJSON

```
GET /wp-json/jet-geometry/v1/countries/geojson?simplified=true
```

### Get Country Incidents

```
GET /wp-json/jet-geometry/v1/country-incidents/{term_id}
```

## Styling

### Customizing Colors

In field settings, you can set:
- Line Color
- Polygon Fill Color
- Fill Opacity

Or use CSS to style map elements:

```css
/* Line styling */
.mapboxgl-layer[id*="jet-geometry-lines"] {
    line-color: #ff0000;
    line-width: 3px;
}

/* Polygon styling */
.mapboxgl-layer[id*="jet-geometry-polygons-fill"] {
    fill-color: #ff0000;
    fill-opacity: 0.3;
}
```

### Country Layer Styling

Configure in **JetEngine → Geometry Addon → General Settings**.

## Development

### File Structure

```
jet-engine-geometry-addon/
├── assets/
│   ├── js/
│   │   ├── admin/
│   │   │   ├── geometry-field.js
│   │   │   └── settings-page.js
│   │   └── public/
│   │       ├── geometry-renderer.js
│   │       └── country-layers.js
│   └── css/
│       ├── admin-geometry-field.css
│       ├── admin-settings.css
│       ├── public-geometry.css
│       └── country-layers.css
├── includes/
│   ├── admin/
│   │   ├── settings-page.php
│   │   └── country-list-table.php
│   ├── rest-api/
│   │   ├── base.php
│   │   ├── validate-geometry.php
│   │   ├── country-import.php
│   │   ├── countries-geojson.php
│   │   └── country-incidents.php
│   ├── autoloader.php
│   ├── geometry-field.php
│   ├── geometry-field-storage.php
│   ├── geometry-renderer.php
│   ├── country-layers.php
│   ├── admin-assets.php
│   ├── filters-integration.php
│   ├── utils.php
│   └── geojson-simplifier.php
└── jet-engine-geometry-addon.php
```

### Extending the Plugin

```php
// Add custom geometry type
add_filter( 'jet-geometry-addon/geometry-types', function( $types ) {
    $types['circle'] = __( 'Circle', 'my-plugin' );
    return $types;
});

// Modify country popup content
add_filter( 'jet-geometry-addon/country-popup-content', function( $content, $term_id, $incidents ) {
    // Custom popup content
    return $content;
}, 10, 3 );
```

## Troubleshooting

### Mapbox Token Issues

Make sure Mapbox access token is set in **JetEngine → Maps Listings → Settings**.

### Geometries Not Showing

1. Check if Mapbox provider is active
2. Verify geometry data is saved in post meta
3. Check browser console for JavaScript errors
4. Ensure map listing is using correct query

### Country Layers Not Appearing

1. Verify countries are imported (**JetEngine → Geometry Addon → Country Layers**)
2. Check if `countries` taxonomy exists
3. Enable country layers in settings or per-map
4. Check browser console for errors

### Import Fails

- Check PHP `max_execution_time` (increase to 120+)
- Verify server can access external URLs
- Try different resolution (110m is faster)
- Check error log for details

## Performance Optimization

1. **Use Simplified Geometries**: Import creates simplified versions automatically
2. **Limit Region**: Import only needed countries (e.g., Europe instead of World)
3. **Lower Resolution**: Use 1:110m for world maps, 1:50m for regional
4. **Caching**: Results are cached in term meta

## Credits

- **Natural Earth Data**: Country boundaries (public domain)
- **Mapbox GL JS**: Map rendering
- **Mapbox GL Draw**: Drawing interface
- **JetEngine**: WordPress framework

## License

GPL v2 or later

## Support

For issues and feature requests, please use the GitHub repository or contact support.













