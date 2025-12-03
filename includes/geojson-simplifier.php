<?php
/**
 * GeoJSON simplifier utility
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Jet_Geometry_GeoJSON_Simplifier
 * 
 * Basic GeoJSON geometry simplification
 */
class Jet_Geometry_GeoJSON_Simplifier {

	/**
	 * Simplify GeoJSON geometry
	 *
	 * @param array $geometry GeoJSON geometry.
	 * @param float $tolerance Simplification tolerance.
	 * @return array Simplified geometry.
	 */
	public static function simplify( $geometry, $tolerance = 0.01 ) {
		if ( ! isset( $geometry['type'], $geometry['coordinates'] ) ) {
			return $geometry;
		}

		$type = $geometry['type'];

		switch ( $type ) {
			case 'Polygon':
				return array(
					'type'        => 'Polygon',
					'coordinates' => self::simplify_polygon( $geometry['coordinates'], $tolerance ),
				);

			case 'MultiPolygon':
				$simplified = array();
				foreach ( $geometry['coordinates'] as $polygon ) {
					$simplified[] = self::simplify_polygon( $polygon, $tolerance );
				}
				return array(
					'type'        => 'MultiPolygon',
					'coordinates' => $simplified,
				);

			case 'LineString':
				return array(
					'type'        => 'LineString',
					'coordinates' => self::simplify_line( $geometry['coordinates'], $tolerance ),
				);

			case 'MultiLineString':
				$simplified = array();
				foreach ( $geometry['coordinates'] as $line ) {
					$simplified[] = self::simplify_line( $line, $tolerance );
				}
				return array(
					'type'        => 'MultiLineString',
					'coordinates' => $simplified,
				);

			default:
				return $geometry;
		}
	}

	/**
	 * Simplify polygon coordinates
	 *
	 * @param array $rings Polygon rings.
	 * @param float $tolerance Tolerance.
	 * @return array
	 */
	private static function simplify_polygon( $rings, $tolerance ) {
		$simplified = array();

		foreach ( $rings as $ring ) {
			$simplified[] = self::simplify_line( $ring, $tolerance );
		}

		return $simplified;
	}

	/**
	 * Simplify line coordinates using Douglas-Peucker algorithm
	 *
	 * @param array $points Line points.
	 * @param float $tolerance Tolerance.
	 * @return array
	 */
	private static function simplify_line( $points, $tolerance ) {
		if ( count( $points ) <= 2 ) {
			return $points;
		}

		// Simple implementation: reduce points
		$simplified = array( $points[0] );
		$total = count( $points );

		for ( $i = 1; $i < $total - 1; $i++ ) {
			$distance = self::perpendicular_distance(
				$points[ $i ],
				$points[0],
				$points[ $total - 1 ]
			);

			if ( $distance > $tolerance ) {
				$simplified[] = $points[ $i ];
			}
		}

		$simplified[] = $points[ $total - 1 ];

		return $simplified;
	}

	/**
	 * Calculate perpendicular distance
	 *
	 * @param array $point Point.
	 * @param array $line_start Line start.
	 * @param array $line_end Line end.
	 * @return float
	 */
	private static function perpendicular_distance( $point, $line_start, $line_end ) {
		$dx = $line_end[0] - $line_start[0];
		$dy = $line_end[1] - $line_start[1];

		if ( 0 === $dx && 0 === $dy ) {
			$dx = $point[0] - $line_start[0];
			$dy = $point[1] - $line_start[1];
			return sqrt( $dx * $dx + $dy * $dy );
		}

		$denominator = ( $dx * $dx + $dy * $dy );

		if ( 0.0 === (float) $denominator ) {
			$dx = $point[0] - $line_start[0];
			$dy = $point[1] - $line_start[1];
			return sqrt( $dx * $dx + $dy * $dy );
		}

		$t = ( ( $point[0] - $line_start[0] ) * $dx + ( $point[1] - $line_start[1] ) * $dy ) / $denominator;

		if ( $t < 0 ) {
			$dx = $point[0] - $line_start[0];
			$dy = $point[1] - $line_start[1];
		} elseif ( $t > 1 ) {
			$dx = $point[0] - $line_end[0];
			$dy = $point[1] - $line_end[1];
		} else {
			$nearest_x = $line_start[0] + $t * $dx;
			$nearest_y = $line_start[1] + $t * $dy;
			$dx = $point[0] - $nearest_x;
			$dy = $point[1] - $nearest_y;
		}

		return sqrt( $dx * $dx + $dy * $dy );
	}
}




