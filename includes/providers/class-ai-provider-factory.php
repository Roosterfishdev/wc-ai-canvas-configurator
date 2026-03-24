<?php
/**
 * AI Provider Factory
 *
 * Factory for creating AI provider instances.
 *
 * @package WC_AICC\Providers
 */

namespace WC_AICC\Providers;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Provider Factory class
 */
class AI_Provider_Factory {

    /**
     * Registered providers
     *
     * @var array
     */
    private static $providers = array();

    /**
     * Get the active AI provider
     *
     * @return AI_Provider_Interface
     */
    public static function get_provider() {
        // Auto-detect: if Replicate token is set, use Replicate provider
        if ( self::is_replicate_configured() ) {
            $provider = new Replicate_AI_Provider();
            if ( $provider->is_available() ) {
                return $provider;
            }
        }

        // Get configured provider ID from options
        $provider_id = get_option( 'wc_aicc_ai_provider', 'stub' );

        // Get provider instance
        $provider = self::get_provider_by_id( $provider_id );

        // Fallback to stub if provider not available
        if ( ! $provider || ! $provider->is_available() ) {
            $provider = new Stub_AI_Provider();
        }

        return $provider;
    }

    /**
     * Check if Replicate API token is configured
     *
     * @return bool True if token is set.
     */
    private static function is_replicate_configured() {
        // Check wp-config constant
        if ( defined( 'REPLICATE_API_TOKEN' ) && ! empty( REPLICATE_API_TOKEN ) ) {
            return true;
        }

        // Check environment variable
        $env_token = getenv( 'REPLICATE_API_TOKEN' );
        if ( ! empty( $env_token ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get provider by ID
     *
     * @param string $provider_id Provider ID.
     * @return AI_Provider_Interface|null
     */
    public static function get_provider_by_id( $provider_id ) {
        self::register_default_providers();

        if ( isset( self::$providers[ $provider_id ] ) ) {
            $class = self::$providers[ $provider_id ];
            return new $class();
        }

        return null;
    }

    /**
     * Register a provider
     *
     * @param string $provider_id Provider ID.
     * @param string $class_name  Fully qualified class name.
     */
    public static function register_provider( $provider_id, $class_name ) {
        self::$providers[ $provider_id ] = $class_name;
    }

    /**
     * Get all registered providers
     *
     * @return array
     */
    public static function get_all_providers() {
        self::register_default_providers();

        $providers = array();
        foreach ( self::$providers as $id => $class ) {
            $provider = new $class();
            $providers[ $id ] = array(
                'id'        => $provider->get_id(),
                'name'      => $provider->get_name(),
                'available' => $provider->is_available(),
            );
        }

        return $providers;
    }

    /**
     * Register default providers
     */
    private static function register_default_providers() {
        if ( empty( self::$providers ) ) {
            self::$providers['stub']      = Stub_AI_Provider::class;
            self::$providers['replicate'] = Replicate_AI_Provider::class;

            /**
             * Filter to register additional AI providers
             *
             * @param array $providers Array of provider_id => class_name.
             */
            self::$providers = apply_filters( 'wc_aicc_ai_providers', self::$providers );
        }
    }
}
