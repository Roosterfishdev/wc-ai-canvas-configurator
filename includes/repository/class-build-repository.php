<?php
/**
 * Build Repository
 *
 * Handles database operations for builds.
 *
 * @package WC_AICC\Repository
 */

namespace WC_AICC\Repository;

use WC_AICC\Models\Build;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build Repository class
 */
class Build_Repository {

    /**
     * Singleton instance
     *
     * @var Build_Repository|null
     */
    private static $instance = null;

    /**
     * Table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Get singleton instance
     *
     * @return Build_Repository
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_builds';
    }

    /**
     * Create a new build
     *
     * @param array $data Build data.
     * @return Build|false Build object or false on failure.
     */
    public function create( $data ) {
        global $wpdb;

        // Generate UUID if not provided
        if ( empty( $data['build_uuid'] ) ) {
            $data['build_uuid'] = wp_generate_uuid4();
        }

        // Set timestamps
        $now                 = current_time( 'mysql', true );
        $data['created_at']  = $now;
        $data['updated_at']  = $now;

        // Set defaults
        $defaults = array(
            'status'      => Build::STATUS_DRAFT,
            'regen_count' => 0,
        );
        $data = wp_parse_args( $data, $defaults );

        // Sanitize
        $data = $this->sanitize_data( $data );

        // Insert
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            $this->get_column_formats( $data )
        );

        if ( $result === false ) {
            $this->log_error( 'Create failed: ' . $wpdb->last_error );
            return false;
        }

        $data['id'] = $wpdb->insert_id;
        return new Build( $data );
    }

    /**
     * Get build by ID
     *
     * @param int $id Build ID.
     * @return Build|null
     */
    public function get_by_id( $id ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );

        return $row ? new Build( $row ) : null;
    }

    /**
     * Get build by UUID
     *
     * @param string $uuid Build UUID.
     * @return Build|null
     */
    public function get_by_uuid( $uuid ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE build_uuid = %s",
                $uuid
            )
        );

        return $row ? new Build( $row ) : null;
    }

    /**
     * Get builds by session key
     *
     * @param string $session_key Session key.
     * @param int    $limit       Maximum number of results.
     * @return Build[]
     */
    public function get_by_session_key( $session_key, $limit = 50 ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE session_key = %s 
                ORDER BY created_at DESC 
                LIMIT %d",
                $session_key,
                $limit
            )
        );

        return array_map( function( $row ) {
            return new Build( $row );
        }, $rows );
    }

    /**
     * Get builds by user ID
     *
     * @param int $user_id User ID.
     * @param int $limit   Maximum number of results.
     * @return Build[]
     */
    public function get_by_user_id( $user_id, $limit = 50 ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE user_id = %d 
                ORDER BY created_at DESC 
                LIMIT %d",
                $user_id,
                $limit
            )
        );

        return array_map( function( $row ) {
            return new Build( $row );
        }, $rows );
    }

    /**
     * Update a build
     *
     * @param int   $id   Build ID.
     * @param array $data Data to update.
     * @return bool True on success.
     */
    public function update( $id, $data ) {
        global $wpdb;

        // Update timestamp
        $data['updated_at'] = current_time( 'mysql', true );

        // Sanitize
        $data = $this->sanitize_data( $data );

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array( 'id' => $id ),
            $this->get_column_formats( $data ),
            array( '%d' )
        );

        if ( $result === false ) {
            $this->log_error( 'Update failed: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }

    /**
     * Update build by UUID
     *
     * @param string $uuid Build UUID.
     * @param array  $data Data to update.
     * @return bool True on success.
     */
    public function update_by_uuid( $uuid, $data ) {
        global $wpdb;

        // Update timestamp
        $data['updated_at'] = current_time( 'mysql', true );

        // Sanitize
        $data = $this->sanitize_data( $data );

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array( 'build_uuid' => $uuid ),
            $this->get_column_formats( $data ),
            array( '%s' )
        );

        if ( $result === false ) {
            $this->log_error( 'Update by UUID failed: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }

    /**
     * Delete a build
     *
     * @param int $id Build ID.
     * @return bool True on success.
     */
    public function delete( $id ) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get expired builds (for cleanup)
     *
     * @param int $hours Hours since creation.
     * @return Build[]
     */
    public function get_expired_builds( $hours = 72 ) {
        global $wpdb;

        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE status != %s 
                AND created_at < %s",
                Build::STATUS_ORDERED,
                $cutoff
            )
        );

        return array_map( function( $row ) {
            return new Build( $row );
        }, $rows );
    }

    /**
     * Get recent builds for admin
     *
     * @param int    $limit  Maximum results.
     * @param int    $offset Offset for pagination.
     * @param string $status Filter by status (optional).
     * @return Build[]
     */
    public function get_recent_builds( $limit = 50, $offset = 0, $status = '' ) {
        global $wpdb;

        $where = '1=1';
        $params = array();

        if ( ! empty( $status ) ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE {$where}
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d",
                $params
            )
        );

        return array_map( function( $row ) {
            return new Build( $row );
        }, $rows );
    }

    /**
     * Count builds
     *
     * @param string $status Filter by status (optional).
     * @return int
     */
    public function count_builds( $status = '' ) {
        global $wpdb;

        if ( ! empty( $status ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                    $status
                )
            );
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );
    }

    /**
     * Check if user/session can access build
     *
     * @param Build       $build       Build to check.
     * @param string      $session_key Current session key.
     * @param int|null    $user_id     Current user ID.
     * @return bool
     */
    public function can_access( $build, $session_key, $user_id = null ) {
        // Check session key match
        if ( ! empty( $session_key ) && $build->session_key === $session_key ) {
            return true;
        }

        // Check user ID match
        if ( ! empty( $user_id ) && $build->user_id === $user_id ) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize data for database
     *
     * @param array $data Data to sanitize.
     * @return array Sanitized data.
     */
    private function sanitize_data( $data ) {
        $sanitized = array();

        foreach ( $data as $key => $value ) {
            switch ( $key ) {
                case 'id':
                case 'user_id':
                case 'product_id':
                case 'variation_id':
                case 'regen_count':
                    $sanitized[ $key ] = $value === null ? null : absint( $value );
                    break;

                case 'build_uuid':
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    break;

                case 'session_key':
                case 'size_label':
                case 'aspect_ratio':
                case 'status':
                case 'original_key':
                case 'cropped_key':
                case 'final_art_key':
                case 'mockup_key':
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    break;

                case 'customization_options':
                    $sanitized[ $key ] = is_array( $value ) ? wp_json_encode( $value ) : ( is_string( $value ) ? $value : '' );
                    break;

                case 'error_message':
                    $sanitized[ $key ] = sanitize_textarea_field( $value );
                    break;

                case 'created_at':
                case 'updated_at':
                    $sanitized[ $key ] = sanitize_text_field( $value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Get column formats for wpdb
     *
     * @param array $data Data array.
     * @return array Format strings.
     */
    private function get_column_formats( $data ) {
        $formats = array();
        $int_columns = array( 'id', 'user_id', 'product_id', 'variation_id', 'regen_count' );

        foreach ( array_keys( $data ) as $key ) {
            if ( in_array( $key, $int_columns, true ) ) {
                $formats[] = $data[ $key ] === null ? null : '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * Log error
     *
     * @param string $message Error message.
     */
    private function log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WC_AICC Repository] ' . $message );
        }
    }
}
