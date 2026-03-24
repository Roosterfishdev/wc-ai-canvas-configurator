<?php
/**
 * Cloudflare R2 Storage Adapter
 *
 * S3-compatible storage adapter for Cloudflare R2.
 * Uses minimal S3v4 signature implementation without requiring AWS SDK.
 *
 * Configuration via wp-config.php constants:
 * - WC_AICC_R2_ENDPOINT      (e.g., https://account-id.r2.cloudflarestorage.com)
 * - WC_AICC_R2_ACCESS_KEY    (access key id)
 * - WC_AICC_R2_SECRET_KEY    (secret access key)
 * - WC_AICC_R2_BUCKET        (bucket name)
 * - WC_AICC_R2_PUBLIC_BASE_URL (public CDN URL base, e.g., https://cdn.example.com)
 *
 * @package WC_AICC\Storage
 */

namespace WC_AICC\Storage;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * R2 Storage class
 */
class R2_Storage implements Storage_Interface {

    /**
     * Singleton instance
     *
     * @var R2_Storage|null
     */
    private static $instance = null;

    /**
     * R2 endpoint URL
     *
     * @var string
     */
    private $endpoint;

    /**
     * Access key ID
     *
     * @var string
     */
    private $access_key;

    /**
     * Secret access key
     *
     * @var string
     */
    private $secret_key;

    /**
     * Bucket name
     *
     * @var string
     */
    private $bucket;

    /**
     * Public base URL (CDN)
     *
     * @var string
     */
    private $public_base_url;

    /**
     * AWS region (always auto for R2)
     *
     * @var string
     */
    private $region = 'auto';

    /**
     * Service name
     *
     * @var string
     */
    private $service = 's3';

    /**
     * Get singleton instance
     *
     * @return R2_Storage
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->endpoint        = defined( 'WC_AICC_R2_ENDPOINT' ) ? WC_AICC_R2_ENDPOINT : '';
        $this->access_key      = defined( 'WC_AICC_R2_ACCESS_KEY' ) ? WC_AICC_R2_ACCESS_KEY : '';
        $this->secret_key      = defined( 'WC_AICC_R2_SECRET_KEY' ) ? WC_AICC_R2_SECRET_KEY : '';
        $this->bucket          = defined( 'WC_AICC_R2_BUCKET' ) ? WC_AICC_R2_BUCKET : '';
        $this->public_base_url = defined( 'WC_AICC_R2_PUBLIC_BASE_URL' ) ? rtrim( WC_AICC_R2_PUBLIC_BASE_URL, '/' ) : '';
    }

    /**
     * Check if storage is configured
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->endpoint ) 
            && ! empty( $this->access_key ) 
            && ! empty( $this->secret_key ) 
            && ! empty( $this->bucket );
    }

    /**
     * Upload an object to R2
     *
     * @param string $key          Object key/path.
     * @param string $binary       Binary content.
     * @param string $content_type Content type (mime type).
     * @return bool True on success.
     */
    public function put_object( $key, $binary, $content_type ) {
        if ( ! $this->is_configured() ) {
            $this->log_error( 'R2 storage not configured' );
            return false;
        }

        $url     = $this->get_bucket_url() . '/' . $key;
        $headers = $this->get_signed_headers( 'PUT', $key, $binary, $content_type );

        $response = wp_remote_request(
            $url,
            array(
                'method'  => 'PUT',
                'headers' => $headers,
                'body'    => $binary,
                'timeout' => 120,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'R2 PUT failed: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 && $code !== 201 ) {
            $body = wp_remote_retrieve_body( $response );
            $this->log_error( "R2 PUT failed with code {$code}: {$body}" );
            return false;
        }

        return true;
    }

    /**
     * Get public URL for an object
     *
     * @param string $key Object key/path.
     * @return string Public URL.
     */
    public function get_public_url( $key ) {
        if ( empty( $key ) ) {
            return '';
        }

        if ( ! empty( $this->public_base_url ) ) {
            return $this->public_base_url . '/' . $key;
        }

        // Fallback to bucket URL (will require auth)
        return $this->get_bucket_url() . '/' . $key;
    }

    /**
     * Get signed URL for an object (time-limited access)
     *
     * @param string $key     Object key/path.
     * @param int    $expires Expiration time in seconds.
     * @return string Signed URL.
     */
    public function get_signed_url( $key, $expires = 3600 ) {
        if ( ! $this->is_configured() || empty( $key ) ) {
            return '';
        }

        // TODO: Implement proper S3v4 pre-signed URL generation
        // For now, return public URL if CDN is configured
        return $this->get_public_url( $key );
    }

    /**
     * Delete an object from R2
     *
     * @param string $key Object key/path.
     * @return bool True on success.
     */
    public function delete_object( $key ) {
        if ( ! $this->is_configured() ) {
            return false;
        }

        $url     = $this->get_bucket_url() . '/' . $key;
        $headers = $this->get_signed_headers( 'DELETE', $key );

        $response = wp_remote_request(
            $url,
            array(
                'method'  => 'DELETE',
                'headers' => $headers,
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'R2 DELETE failed: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        return $code === 204 || $code === 200;
    }

    /**
     * Delete multiple objects by prefix
     *
     * @param string $prefix Key prefix to delete.
     * @return bool True on success.
     */
    public function delete_by_prefix( $prefix ) {
        if ( ! $this->is_configured() ) {
            return false;
        }

        // List objects with prefix
        $objects = $this->list_objects( $prefix );

        if ( empty( $objects ) ) {
            return true;
        }

        $success = true;
        foreach ( $objects as $key ) {
            if ( ! $this->delete_object( $key ) ) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * List objects with a prefix
     *
     * @param string $prefix Key prefix.
     * @return array List of object keys.
     */
    public function list_objects( $prefix ) {
        if ( ! $this->is_configured() ) {
            return array();
        }

        $url = $this->get_bucket_url() . '?list-type=2&prefix=' . urlencode( $prefix );
        $headers = $this->get_signed_headers( 'GET', '', '', '', array(
            'list-type' => '2',
            'prefix'    => $prefix,
        ) );

        $response = wp_remote_get(
            $url,
            array(
                'headers' => $headers,
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'R2 LIST failed: ' . $response->get_error_message() );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $keys = array();

        // Parse XML response
        if ( preg_match_all( '/<Key>([^<]+)<\/Key>/', $body, $matches ) ) {
            $keys = $matches[1];
        }

        return $keys;
    }

    /**
     * Check if an object exists
     *
     * @param string $key Object key/path.
     * @return bool True if exists.
     */
    public function object_exists( $key ) {
        if ( ! $this->is_configured() ) {
            return false;
        }

        $url     = $this->get_bucket_url() . '/' . $key;
        $headers = $this->get_signed_headers( 'HEAD', $key );

        $response = wp_remote_request(
            $url,
            array(
                'method'  => 'HEAD',
                'headers' => $headers,
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        return wp_remote_retrieve_response_code( $response ) === 200;
    }

    /**
     * Get object content
     *
     * @param string $key Object key/path.
     * @return string|false Object content or false on failure.
     */
    public function get_object( $key ) {
        if ( ! $this->is_configured() ) {
            return false;
        }

        $url     = $this->get_bucket_url() . '/' . $key;
        $headers = $this->get_signed_headers( 'GET', $key );

        $response = wp_remote_get(
            $url,
            array(
                'headers' => $headers,
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'R2 GET failed: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return false;
        }

        return wp_remote_retrieve_body( $response );
    }

    /**
     * Get bucket URL
     *
     * @return string
     */
    private function get_bucket_url() {
        return rtrim( $this->endpoint, '/' ) . '/' . $this->bucket;
    }

    /**
     * Get signed headers for S3v4 authentication
     *
     * @param string $method       HTTP method.
     * @param string $key          Object key.
     * @param string $payload      Request body.
     * @param string $content_type Content type.
     * @param array  $query_params Query parameters.
     * @return array Headers with authorization.
     */
    private function get_signed_headers( $method, $key = '', $payload = '', $content_type = '', $query_params = array() ) {
        $now       = new \DateTime( 'UTC' );
        $date      = $now->format( 'Ymd' );
        $datetime  = $now->format( 'Ymd\THis\Z' );
        $host      = wp_parse_url( $this->endpoint, PHP_URL_HOST );

        // Payload hash
        $payload_hash = hash( 'sha256', $payload );

        // Build signed headers
        $headers = array(
            'host'                 => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'           => $datetime,
        );

        if ( ! empty( $content_type ) ) {
            $headers['content-type'] = $content_type;
        }

        // Sort headers
        ksort( $headers );

        // Build canonical headers string
        $canonical_headers = '';
        $signed_headers    = array();
        foreach ( $headers as $name => $value ) {
            $canonical_headers .= strtolower( $name ) . ':' . trim( $value ) . "\n";
            $signed_headers[]   = strtolower( $name );
        }
        $signed_headers_str = implode( ';', $signed_headers );

        // Build canonical query string
        $canonical_query = '';
        if ( ! empty( $query_params ) ) {
            ksort( $query_params );
            $canonical_query = http_build_query( $query_params, '', '&', PHP_QUERY_RFC3986 );
        }

        // Build canonical URI
        $canonical_uri = '/' . $this->bucket;
        if ( ! empty( $key ) ) {
            $canonical_uri .= '/' . $key;
        }

        // Build canonical request
        $canonical_request = implode( "\n", array(
            $method,
            $canonical_uri,
            $canonical_query,
            $canonical_headers,
            $signed_headers_str,
            $payload_hash,
        ) );

        // Build string to sign
        $credential_scope = $date . '/' . $this->region . '/' . $this->service . '/aws4_request';
        $string_to_sign   = implode( "\n", array(
            'AWS4-HMAC-SHA256',
            $datetime,
            $credential_scope,
            hash( 'sha256', $canonical_request ),
        ) );

        // Calculate signature
        $date_key               = hash_hmac( 'sha256', $date, 'AWS4' . $this->secret_key, true );
        $date_region_key        = hash_hmac( 'sha256', $this->region, $date_key, true );
        $date_region_service_key = hash_hmac( 'sha256', $this->service, $date_region_key, true );
        $signing_key            = hash_hmac( 'sha256', 'aws4_request', $date_region_service_key, true );
        $signature              = hash_hmac( 'sha256', $string_to_sign, $signing_key );

        // Build authorization header
        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->access_key,
            $credential_scope,
            $signed_headers_str,
            $signature
        );

        // Return headers for wp_remote_*
        $result = array(
            'Authorization'        => $authorization,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'           => $datetime,
        );

        if ( ! empty( $content_type ) ) {
            $result['Content-Type'] = $content_type;
        }

        return $result;
    }

    /**
     * Log error message
     *
     * @param string $message Error message.
     */
    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WC_AICC R2] ' . $message );
        }
    }
}
