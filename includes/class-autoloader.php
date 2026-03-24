<?php
/**
 * Autoloader for WC AI Canvas Configurator
 *
 * @package WC_AICC
 */

namespace WC_AICC;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Autoloader class
 */
class Autoloader {

    /**
     * Namespace prefix
     *
     * @var string
     */
    private static $prefix = 'WC_AICC\\';

    /**
     * Base directory for the namespace prefix
     *
     * @var string
     */
    private static $base_dir;

    /**
     * Register the autoloader
     */
    public static function register() {
        self::$base_dir = WC_AICC_PLUGIN_DIR . 'includes/';
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload classes
     *
     * @param string $class The fully-qualified class name.
     */
    public static function autoload( $class ) {
        // Check if class uses our namespace
        $len = strlen( self::$prefix );
        if ( strncmp( self::$prefix, $class, $len ) !== 0 ) {
            return;
        }

        // Get the relative class name
        $relative_class = substr( $class, $len );

        // Convert namespace to path
        $path = self::class_to_path( $relative_class );

        // Require file if exists
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    /**
     * Convert class name to file path
     *
     * @param string $relative_class Relative class name.
     * @return string File path.
     */
    private static function class_to_path( $relative_class ) {
        // Replace namespace separators with directory separators
        $path = str_replace( '\\', '/', $relative_class );
        
        // Split into parts
        $parts = explode( '/', $path );
        
        // Get the class name (last part)
        $class_name = array_pop( $parts );
        
        // Convert class name to file name (Class_Name -> class-class-name.php)
        $file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
        
        // Build directory path (convert to lowercase)
        $dir_path = '';
        if ( ! empty( $parts ) ) {
            $dir_path = strtolower( implode( '/', $parts ) ) . '/';
        }

        return self::$base_dir . $dir_path . $file_name;
    }
}
