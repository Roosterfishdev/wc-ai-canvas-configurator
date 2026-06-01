<?php
/**
 * Canonical canvas size → aspect ratio for AI / Replicate
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maps parsed width×height from variation labels (cm or in) to API aspect ratios.
 */
class Size_Aspect_Map {

    /**
     * Dimension pair "w|h" (integers from label) → ratio string
     *
     * 30×40 cm (12×16 in) → 3:4
     * 40×50 cm (16×20 in) → 4:5
     * 50×70 cm (20×28 in) → 5:7
     *
     * Both orientations included for the same physical canvas.
     *
     * @var array<string, string>
     */
    private static function base_map() {
        return array(
            // 30 × 40 cm / 12 × 16 in → 3:4
            '30|40' => '3:4',
            '40|30' => '3:4',
            '12|16' => '3:4',
            '16|12' => '3:4',
            // 40 × 50 cm / 16 × 20 in → 4:5
            '40|50' => '4:5',
            '50|40' => '4:5',
            '16|20' => '4:5',
            '20|16' => '4:5',
            // 50 × 70 cm / 20 × 28 in → 5:7
            '50|70' => '5:7',
            '70|50' => '5:7',
            '20|28' => '5:7',
            '28|20' => '5:7',
        );
    }

    /**
     * Full map (filterable for extra sizes)
     *
     * @return array<string, string>
     */
    public static function get_map() {
        /**
         * Add or override "width|height" => "w:h" entries (integers only, both orientations if needed).
         *
         * @param array<string, string> $map Pair key to ratio.
         */
        return apply_filters( 'wc_aicc_size_dimension_ratios', self::base_map() );
    }

    /**
     * Resolve aspect ratio from a human size label (e.g. "30 x 40 cm", "12×16 in")
     *
     * @param string $size_label Variation size string.
     * @return string|null Ratio like "3:4" or null if no known pair.
     */
    public static function resolve( $size_label ) {
        if ( empty( $size_label ) || ! is_string( $size_label ) ) {
            return null;
        }

        if ( ! preg_match( '/(\d+)\s*[x×]\s*(\d+)/i', $size_label, $matches ) ) {
            return null;
        }

        $w = (int) $matches[1];
        $h = (int) $matches[2];
        if ( $w < 1 || $h < 1 ) {
            return null;
        }

        $key = $w . '|' . $h;
        $map = self::get_map();

        return isset( $map[ $key ] ) ? $map[ $key ] : null;
    }

    /**
     * Resolve aspect ratio for AI generation, with portrait canvas fallbacks.
     *
     * @param string $size_label Human-readable or attribute size label.
     * @param string $default    Default when label cannot be parsed (portrait canvases).
     * @return string Ratio like "4:5".
     */
    public static function resolve_for_label( $size_label, $default = '2:3' ) {
        $resolved = self::resolve( $size_label );
        if ( ! empty( $resolved ) ) {
            return $resolved;
        }

        if ( empty( $size_label ) || ! is_string( $size_label ) ) {
            return $default;
        }

        if ( ! preg_match( '/(\d+)\s*[x×]\s*(\d+)/i', $size_label, $matches ) ) {
            return $default;
        }

        $w = (int) $matches[1];
        $h = (int) $matches[2];
        if ( $w < 1 || $h < 1 ) {
            return $default;
        }

        $map = self::get_map();
        foreach ( array( $w . '|' . $h, $h . '|' . $w ) as $key ) {
            if ( isset( $map[ $key ] ) ) {
                return $map[ $key ];
            }
        }

        $gcd = self::gcd( $w, $h );
        if ( $gcd < 1 ) {
            return $default;
        }

        return ( $w / $gcd ) . ':' . ( $h / $gcd );
    }

    /**
     * @param int $a First number.
     * @param int $b Second number.
     * @return int
     */
    private static function gcd( $a, $b ) {
        while ( $b !== 0 ) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return (int) $a;
    }
}
