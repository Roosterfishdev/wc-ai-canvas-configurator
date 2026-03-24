<?php
/**
 * Storage Interface
 *
 * @package WC_AICC\Storage
 */

namespace WC_AICC\Storage;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Storage Interface
 */
interface Storage_Interface {

    /**
     * Upload an object to storage
     *
     * @param string $key         Object key/path.
     * @param string $binary      Binary content.
     * @param string $content_type Content type (mime type).
     * @return bool True on success.
     */
    public function put_object( $key, $binary, $content_type );

    /**
     * Get public URL for an object
     *
     * @param string $key Object key/path.
     * @return string Public URL.
     */
    public function get_public_url( $key );

    /**
     * Get signed URL for an object (time-limited access)
     *
     * @param string $key     Object key/path.
     * @param int    $expires Expiration time in seconds.
     * @return string Signed URL.
     */
    public function get_signed_url( $key, $expires = 3600 );

    /**
     * Delete an object from storage
     *
     * @param string $key Object key/path.
     * @return bool True on success.
     */
    public function delete_object( $key );

    /**
     * Delete multiple objects by prefix
     *
     * @param string $prefix Key prefix to delete.
     * @return bool True on success.
     */
    public function delete_by_prefix( $prefix );

    /**
     * Check if an object exists
     *
     * @param string $key Object key/path.
     * @return bool True if exists.
     */
    public function object_exists( $key );

    /**
     * Get object content
     *
     * @param string $key Object key/path.
     * @return string|false Object content or false on failure.
     */
    public function get_object( $key );
}
