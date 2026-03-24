<?php
/**
 * Session Manager
 *
 * Manages session keys for anonymous users.
 *
 * @package WC_AICC
 */

namespace WC_AICC;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Session Manager class
 */
class Session_Manager {

    /**
     * Cookie name
     */
    const COOKIE_NAME = 'wc_aicc_session';

    /**
     * Cookie expiration (30 days)
     */
    const COOKIE_EXPIRATION = 2592000;

    /**
     * Get or create session key
     *
     * @return string Session key.
     */
    public static function get_session_key() {
        // Check if already set in cookie
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $session_key = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
            if ( self::is_valid_session_key( $session_key ) ) {
                return $session_key;
            }
        }

        // Generate new session key
        $session_key = self::generate_session_key();

        // Set cookie (if headers not sent)
        if ( ! headers_sent() ) {
            self::set_session_cookie( $session_key );
        }

        return $session_key;
    }

    /**
     * Get session key from request header
     *
     * @return string|null Session key or null.
     */
    public static function get_session_key_from_header() {
        // Check X-WC-AICC-Session header
        $headers = getallheaders();
        if ( isset( $headers['X-WC-AICC-Session'] ) ) {
            $session_key = sanitize_text_field( $headers['X-WC-AICC-Session'] );
            if ( self::is_valid_session_key( $session_key ) ) {
                return $session_key;
            }
        }

        // Fallback to cookie
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $session_key = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
            if ( self::is_valid_session_key( $session_key ) ) {
                return $session_key;
            }
        }

        return null;
    }

    /**
     * Generate new session key
     *
     * @return string Session key.
     */
    public static function generate_session_key() {
        return wp_generate_uuid4();
    }

    /**
     * Validate session key format
     *
     * @param string $session_key Session key to validate.
     * @return bool True if valid.
     */
    public static function is_valid_session_key( $session_key ) {
        // UUID format validation
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $session_key
        );
    }

    /**
     * Set session cookie
     *
     * @param string $session_key Session key.
     */
    public static function set_session_cookie( $session_key ) {
        if ( headers_sent() ) {
            return;
        }

        $secure   = is_ssl();
        $samesite = 'Lax';

        // PHP 7.3+ supports SameSite in options
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie(
                self::COOKIE_NAME,
                $session_key,
                array(
                    'expires'  => time() + self::COOKIE_EXPIRATION,
                    'path'     => COOKIEPATH ?: '/',
                    'domain'   => COOKIE_DOMAIN,
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => $samesite,
                )
            );
        } else {
            setcookie(
                self::COOKIE_NAME,
                $session_key,
                time() + self::COOKIE_EXPIRATION,
                ( COOKIEPATH ?: '/' ) . '; SameSite=' . $samesite,
                COOKIE_DOMAIN,
                $secure,
                true
            );
        }

        // Also set in $_COOKIE for immediate use
        $_COOKIE[ self::COOKIE_NAME ] = $session_key;
    }

    /**
     * Clear session cookie
     */
    public static function clear_session_cookie() {
        if ( headers_sent() ) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            '',
            time() - 3600,
            COOKIEPATH ?: '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        unset( $_COOKIE[ self::COOKIE_NAME ] );
    }
}
