<?php
/**
 * Central logging for WC AI Canvas Configurator
 *
 * Enable with either:
 *   define( 'WC_AICC_LOG', true ); // in wp-config.php — logs without full WP_DEBUG UI noise
 *   define( 'WP_DEBUG', true );   // existing behavior
 *
 * With WP_DEBUG_LOG true, lines go to wp-content/debug.log (via PHP error_log).
 *
 * @package WC_AICC
 */

namespace WC_AICC;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logger utility
 */
class Logger {

    /**
     * Max length for string context values and bodies (avoid huge logs).
     */
    const MAX_STRING_LEN = 600;

    /**
     * Whether WC AICC logging is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        if ( defined( 'WC_AICC_LOG' ) && WC_AICC_LOG ) {
            return true;
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }
        return false;
    }

    /**
     * @param string $channel Short label (Job, Replicate, REST, etc.).
     * @param string $message Message text.
     * @param array  $context Optional key-value context (arrays/objects JSON-encoded).
     */
    public static function info( $channel, $message, array $context = array() ) {
        self::write( 'INFO', $channel, $message, $context );
    }

    /**
     * @param string $channel Short label.
     * @param string $message Message text.
     * @param array  $context Optional context.
     */
    public static function warning( $channel, $message, array $context = array() ) {
        self::write( 'WARNING', $channel, $message, $context );
    }

    /**
     * @param string $channel Short label.
     * @param string $message Message text.
     * @param array  $context Optional context.
     */
    public static function error( $channel, $message, array $context = array() ) {
        self::write( 'ERROR', $channel, $message, $context );
    }

    /**
     * Truncate a string for safe logging
     *
     * @param string $value Raw string.
     * @param int    $max   Max length.
     * @return string
     */
    public static function truncate( $value, $max = null ) {
        $max = null === $max ? self::MAX_STRING_LEN : (int) $max;
        if ( ! is_string( $value ) ) {
            $value = (string) $value;
        }
        if ( strlen( $value ) <= $max ) {
            return $value;
        }
        return substr( $value, 0, $max ) . '…';
    }

    /**
     * Summarize a URL for logs (host + path, no query secrets)
     *
     * @param string $url Full URL.
     * @return string
     */
    public static function summarize_url( $url ) {
        if ( empty( $url ) || ! is_string( $url ) ) {
            return '';
        }
        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) ) {
            return self::truncate( $url, 200 );
        }
        $path = isset( $parts['path'] ) ? $parts['path'] : '';
        return self::truncate( $parts['host'] . $path, 300 );
    }

    /**
     * @param string $level   INFO|WARNING|ERROR.
     * @param string $channel Channel name.
     * @param string $message Message.
     * @param array  $context Context pairs.
     */
    private static function write( $level, $channel, $message, array $context ) {
        if ( ! self::is_enabled() ) {
            return;
        }

        $line = sprintf( '[WC_AICC] [%s] [%s] %s', $channel, $level, $message );

        if ( ! empty( $context ) ) {
            $line .= ' | ' . self::format_context( $context );
        }

        error_log( $line );
    }

    /**
     * @param array $context Key-value pairs.
     * @return string
     */
    private static function format_context( array $context ) {
        $parts = array();
        foreach ( $context as $key => $value ) {
            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value );
            } elseif ( is_bool( $value ) ) {
                $value = $value ? 'true' : 'false';
            } elseif ( null === $value ) {
                $value = 'null';
            } else {
                $value = (string) $value;
            }
            $parts[] = sanitize_key( (string) $key ) . '=' . self::truncate( $value );
        }
        return implode( ' ', $parts );
    }
}
