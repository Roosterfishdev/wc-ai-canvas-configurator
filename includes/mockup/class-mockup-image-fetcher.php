<?php
/**
 * Fetch artwork bytes for mockup compositing (HTTP or direct disk read).
 *
 * Action Scheduler often cannot reliably HTTP-loopback to the site URL; when the
 * asset lives under wp-content/uploads we read the file from disk instead.
 *
 * @package WC_AICC\Mockup
 */

namespace WC_AICC\Mockup;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mockup image fetcher
 */
class Mockup_Image_Fetcher {

    /**
     * Load binary image data from a URL.
     *
     * @param string $url Full URL to the image.
     * @return string|false Raw bytes or false on failure.
     */
    public static function fetch( $url ) {
        if ( $url === '' ) {
            return false;
        }

        $local = self::resolve_uploads_path( $url );
        if ( $local !== '' && is_readable( $local ) ) {
            $data = file_get_contents( $local );
            return ( $data !== false && $data !== '' ) ? $data : false;
        }

        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 60,
                'sslverify'   => false,
                'redirection' => 5,
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        return ( $body !== '' ) ? $body : false;
    }

    /**
     * If URL points at this site's uploads directory, return the filesystem path.
     *
     * @param string $url Image URL.
     * @return string Absolute path or empty string.
     */
    private static function resolve_uploads_path( $url ) {
        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) || empty( $upload_dir['baseurl'] ) || empty( $upload_dir['basedir'] ) ) {
            return '';
        }

        $parts = wp_parse_url( strtok( $url, '?' ) );
        if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
            return '';
        }

        $base_parts = wp_parse_url( $upload_dir['baseurl'] );
        if ( empty( $base_parts['host'] ) || empty( $base_parts['path'] ) ) {
            return '';
        }

        if ( strtolower( $parts['host'] ) !== strtolower( $base_parts['host'] ) ) {
            return '';
        }

        $url_path  = $parts['path'];
        $base_path = untrailingslashit( $base_parts['path'] );
        if ( $base_path !== '' && strpos( $url_path, $base_path ) !== 0 ) {
            return '';
        }

        $relative = $base_path !== '' ? substr( $url_path, strlen( $base_path ) ) : $url_path;
        $relative = ltrim( (string) $relative, '/' );
        if ( $relative === '' ) {
            return '';
        }

        return wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . $relative );
    }
}
