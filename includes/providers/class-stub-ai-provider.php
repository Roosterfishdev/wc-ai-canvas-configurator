<?php
/**
 * Stub AI Provider
 *
 * A placeholder AI provider that returns the source image unchanged.
 * Use this for development/testing before implementing real AI integration.
 *
 * @package WC_AICC\Providers
 */

namespace WC_AICC\Providers;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stub AI Provider class
 */
class Stub_AI_Provider implements AI_Provider_Interface {

    /**
     * Get provider identifier
     *
     * @return string
     */
    public function get_id() {
        return 'stub';
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Stub Provider (Development)', 'wc-aicc' );
    }

    /**
     * Check if provider is available
     *
     * @return bool
     */
    public function is_available() {
        return true; // Always available
    }

    /**
     * Generate stylized artwork
     *
     * For the stub, this just returns the source image URL unchanged.
     *
     * @param string $source_url      URL of the source image.
     * @param string $prompt          Full prompt (ignored by stub).
     * @param string $aspect_ratio    Aspect ratio.
     * @param string $negative_prompt Optional negative prompt (ignored by stub).
     * @return array
     */
    public function generate( $source_url, $prompt, $aspect_ratio = '1:1', $negative_prompt = '' ) {
        // Simulate processing delay
        sleep( 2 );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WC_AICC Stub AI] Generate called - aspect: ' . $aspect_ratio . ', source: ' . $source_url );
        }

        return array(
            'success'   => true,
            'image_url' => $source_url,
            'error'     => '',
        );
    }
}
