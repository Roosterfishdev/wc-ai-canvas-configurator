<?php
/**
 * Mockup Generator Interface
 *
 * @package WC_AICC\Mockup
 */

namespace WC_AICC\Mockup;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mockup Generator Interface
 * 
 * Implement this interface to add different mockup generation methods.
 */
interface Mockup_Generator_Interface {

    /**
     * Get generator identifier
     *
     * @return string Generator ID
     */
    public function get_id();

    /**
     * Get generator display name
     *
     * @return string Display name
     */
    public function get_name();

    /**
     * Check if generator is available
     *
     * @return bool True if available
     */
    public function is_available();

    /**
     * Generate mockup from final artwork
     *
     * @param string $final_art_url  URL of the final artwork image.
     * @param string $size_label     Size label (e.g., '16x20', '24x36').
     * @param string $aspect_ratio   Aspect ratio (e.g., '1:1', '3:4').
     * @param array  $options        Additional options (template, room scene, etc.).
     * @return array {
     *     @type bool   $success    Whether generation succeeded.
     *     @type string $image_data Binary image data of the mockup.
     *     @type string $image_url  URL to download the mockup (alternative).
     *     @type string $error      Error message (if failed).
     * }
     */
    public function generate( $final_art_url, $size_label, $aspect_ratio, $options = array() );

    /**
     * Get available templates/scenes
     *
     * @return array Array of template definitions.
     */
    public function get_templates();
}
