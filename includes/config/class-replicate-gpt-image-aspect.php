<?php
/**
 * Map print/canvas aspect ratios to values accepted by openai/gpt-image-1.5 on Replicate
 *
 * The model only accepts: "1:1", "3:2", "2:3". Other ratios (e.g. 3:4, 4:5, 5:7) cause HTTP 422.
 * We map to the closest allowed ratio; physical canvas size is unchanged for print — adjust
 * crop/fit in production if the preview aspect differs slightly from the SKU.
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Replicate GPT Image aspect normalization
 */
class Replicate_Gpt_Image_Aspect {

    /**
     * Values accepted by gpt-image-1.5 input.aspect_ratio (Replicate API)
     */
    const MODEL_ALLOWED = array( '1:1', '3:2', '2:3' );

    /**
     * Explicit canvas ratios → model input (portrait SKUs → 2:3, the only portrait option)
     *
     * @return array<string, string>
     */
    private static function canvas_to_model_map() {
        $map = array(
            '3:4' => '2:3',
            '4:5' => '2:3',
            '5:7' => '2:3',
        );

        /**
         * Override or extend print ratio → Replicate ratio (values must be in MODEL_ALLOWED).
         *
         * @param array<string, string> $map Print ratio => model ratio.
         */
        return apply_filters( 'wc_aicc_replicate_gpt_image_aspect_map', $map );
    }

    /**
     * Convert stored print/configurator aspect ratio to API-safe value
     *
     * @param string $print_aspect_ratio e.g. "3:4", "4:5", "2:3".
     * @return string One of 1:1, 3:2, 2:3.
     */
    public static function to_model_input( $print_aspect_ratio ) {
        $r = strtolower( preg_replace( '/\s+/', '', (string) $print_aspect_ratio ) );

        if ( in_array( $r, self::MODEL_ALLOWED, true ) ) {
            return $r;
        }

        $map = self::canvas_to_model_map();
        if ( isset( $map[ $r ] ) ) {
            return $map[ $r ];
        }

        return self::snap_to_nearest_allowed( $r );
    }

    /**
     * @param string $ratio Normalized "w:h" or garbage.
     * @return string
     */
    private static function snap_to_nearest_allowed( $ratio ) {
        if ( preg_match( '/^(\d+):(\d+)$/', $ratio, $m ) ) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            if ( $w > 0 && $h > 0 ) {
                $value      = $w / $h;
                $candidates = array(
                    '1:1' => 1.0,
                    '3:2' => 3 / 2,
                    '2:3' => 2 / 3,
                );
                $best  = '2:3';
                $bdist = PHP_FLOAT_MAX;
                foreach ( $candidates as $key => $v ) {
                    $d = abs( $value - $v );
                    if ( $d < $bdist ) {
                        $bdist = $d;
                        $best  = $key;
                    }
                }
                return $best;
            }
        }

        return '2:3';
    }
}
