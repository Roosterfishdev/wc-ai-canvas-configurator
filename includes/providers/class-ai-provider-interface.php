<?php
/**
 * AI Provider Interface
 *
 * @package WC_AICC\Providers
 */

namespace WC_AICC\Providers;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Provider Interface
 * 
 * Implement this interface to add new AI image generation providers.
 */
interface AI_Provider_Interface {

    /**
     * Get provider identifier
     *
     * @return string Provider ID (e.g., 'replicate', 'stability', 'stub')
     */
    public function get_id();

    /**
     * Get provider display name
     *
     * @return string Display name
     */
    public function get_name();

    /**
     * Check if provider is configured and available
     *
     * @return bool True if available
     */
    public function is_available();

    /**
     * Generate stylized artwork from source image
     *
     * @param string $source_url     URL of the source image (cropped/prepared).
     * @param string $prompt         Full prompt (built by Prompt_Builder).
     * @param string $aspect_ratio   Aspect ratio (e.g., '1:1', '3:4', '4:3').
     * @param string $negative_prompt Optional negative prompt / constraints.
     * @return array {
     *     @type bool   $success    Whether generation succeeded.
     *     @type string $image_url  URL to download the generated image (if success).
     *     @type string $image_data Base64 encoded image data (alternative to URL).
     *     @type string $error      Error message (if failed).
     * }
     */
    public function generate( $source_url, $prompt, $aspect_ratio = '1:1', $negative_prompt = '' );
}
