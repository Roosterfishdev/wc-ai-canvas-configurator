<?php
/**
 * Stub Mockup Generator
 *
 * A placeholder mockup generator that returns the final art unchanged.
 * Use this for development/testing before implementing real template compositing.
 *
 * @package WC_AICC\Mockup
 */

namespace WC_AICC\Mockup;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stub Mockup Generator class
 */
class Stub_Mockup_Generator implements Mockup_Generator_Interface {

    /**
     * Get generator identifier
     *
     * @return string
     */
    public function get_id() {
        return 'stub';
    }

    /**
     * Get generator display name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Stub Generator (Development)', 'wc-aicc' );
    }

    /**
     * Check if generator is available
     *
     * @return bool
     */
    public function is_available() {
        return true; // Always available
    }

    /**
     * Generate mockup from final artwork
     *
     * For the stub, this just returns the final art image unchanged.
     *
     * @param string $final_art_url URL of the final artwork.
     * @param string $size_label    Size label.
     * @param string $aspect_ratio  Aspect ratio.
     * @param array  $options       Additional options.
     * @return array
     */
    public function generate( $final_art_url, $size_label, $aspect_ratio, $options = array() ) {
        // Log for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[WC_AICC Stub Mockup] Generate called - size: %s, aspect: %s, source: %s',
                $size_label,
                $aspect_ratio,
                $final_art_url
            ) );
        }

        // TODO: Implement real template compositing using GD or Imagick
        // For now, download the final art and return it as the mockup

        $image_data = Mockup_Image_Fetcher::fetch( $final_art_url );

        if ( $image_data === false || $image_data === '' ) {
            return array(
                'success' => false,
                'error'   => __( 'Failed to fetch final art image (check URL, SSL, or cron loopback to uploads).', 'wc-aicc' ),
            );
        }

        return array(
            'success'    => true,
            'image_data' => $image_data,
            'error'      => '',
        );
    }

    /**
     * Get available templates
     *
     * @return array
     */
    public function get_templates() {
        return array(
            array(
                'id'          => 'living-room-white',
                'name'        => __( 'Modern Living Room (White Wall)', 'wc-aicc' ),
                'description' => __( 'Clean, modern living room with white walls.', 'wc-aicc' ),
                'thumbnail'   => WC_AICC_PLUGIN_URL . 'assets/images/templates/living-room-white.jpg',
            ),
            array(
                'id'          => 'living-room-dark',
                'name'        => __( 'Cozy Living Room (Dark)', 'wc-aicc' ),
                'description' => __( 'Warm, cozy living room with dark accent wall.', 'wc-aicc' ),
                'thumbnail'   => WC_AICC_PLUGIN_URL . 'assets/images/templates/living-room-dark.jpg',
            ),
            array(
                'id'          => 'bedroom',
                'name'        => __( 'Bedroom', 'wc-aicc' ),
                'description' => __( 'Serene bedroom setting above the bed.', 'wc-aicc' ),
                'thumbnail'   => WC_AICC_PLUGIN_URL . 'assets/images/templates/bedroom.jpg',
            ),
            array(
                'id'          => 'office',
                'name'        => __( 'Home Office', 'wc-aicc' ),
                'description' => __( 'Professional home office environment.', 'wc-aicc' ),
                'thumbnail'   => WC_AICC_PLUGIN_URL . 'assets/images/templates/office.jpg',
            ),
        );
    }
}
