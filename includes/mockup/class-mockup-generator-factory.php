<?php
/**
 * Mockup Generator Factory
 *
 * Factory for creating mockup generator instances.
 *
 * @package WC_AICC\Mockup
 */

namespace WC_AICC\Mockup;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mockup Generator Factory class
 */
class Mockup_Generator_Factory {

    /**
     * Registered generators
     *
     * @var array
     */
    private static $generators = array();

    /**
     * Get the active mockup generator
     *
     * @return Mockup_Generator_Interface
     */
    public static function get_generator() {
        // Try template generator first (default)
        $template_generator = new Template_Mockup_Generator();
        if ( $template_generator->is_available() ) {
            return $template_generator;
        }

        // Get configured generator ID from options as fallback
        $generator_id = get_option( 'wc_aicc_mockup_generator', 'stub' );

        // Get generator instance
        $generator = self::get_generator_by_id( $generator_id );

        // Fallback to stub if generator not available
        if ( ! $generator || ! $generator->is_available() ) {
            $generator = new Stub_Mockup_Generator();
        }

        return $generator;
    }

    /**
     * Get generator by ID
     *
     * @param string $generator_id Generator ID.
     * @return Mockup_Generator_Interface|null
     */
    public static function get_generator_by_id( $generator_id ) {
        self::register_default_generators();

        if ( isset( self::$generators[ $generator_id ] ) ) {
            $class = self::$generators[ $generator_id ];
            return new $class();
        }

        return null;
    }

    /**
     * Register a generator
     *
     * @param string $generator_id Generator ID.
     * @param string $class_name   Fully qualified class name.
     */
    public static function register_generator( $generator_id, $class_name ) {
        self::$generators[ $generator_id ] = $class_name;
    }

    /**
     * Get all registered generators
     *
     * @return array
     */
    public static function get_all_generators() {
        self::register_default_generators();

        $generators = array();
        foreach ( self::$generators as $id => $class ) {
            $generator         = new $class();
            $generators[ $id ] = array(
                'id'        => $generator->get_id(),
                'name'      => $generator->get_name(),
                'available' => $generator->is_available(),
            );
        }

        return $generators;
    }

    /**
     * Register default generators
     */
    private static function register_default_generators() {
        if ( empty( self::$generators ) ) {
            self::$generators['template'] = Template_Mockup_Generator::class;
            self::$generators['stub']     = Stub_Mockup_Generator::class;

            /**
             * Filter to register additional mockup generators
             *
             * @param array $generators Array of generator_id => class_name.
             */
            self::$generators = apply_filters( 'wc_aicc_mockup_generators', self::$generators );
        }
    }
}
