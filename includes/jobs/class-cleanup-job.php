<?php
/**
 * Cleanup Job
 *
 * Handles daily cleanup of expired builds.
 *
 * @package WC_AICC\Jobs
 */

namespace WC_AICC\Jobs;

use WC_AICC\Models\Build;
use WC_AICC\Repository\Build_Repository;
use WC_AICC\Storage\R2_Storage;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cleanup Job class
 */
class Cleanup_Job {

    /**
     * Singleton instance
     *
     * @var Cleanup_Job|null
     */
    private static $instance = null;

    /**
     * Hours until builds expire
     *
     * @var int
     */
    const EXPIRATION_HOURS = 72;

    /**
     * Get singleton instance
     *
     * @return Cleanup_Job
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
        // Register cleanup handler
        add_action( 'wc_aicc_daily_cleanup', array( $this, 'run_cleanup' ) );

        // Check if we need to schedule cleanup (deferred from activation)
        add_action( 'init', array( $this, 'maybe_schedule_cleanup' ) );
    }

    /**
     * Maybe schedule cleanup if needed
     */
    public function maybe_schedule_cleanup() {
        if ( ! get_option( 'wc_aicc_needs_cleanup_schedule' ) ) {
            return;
        }

        if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
            return;
        }

        // Clear the flag
        delete_option( 'wc_aicc_needs_cleanup_schedule' );

        // Check if already scheduled
        if ( as_next_scheduled_action( 'wc_aicc_daily_cleanup' ) ) {
            return;
        }

        // Schedule daily cleanup at 3 AM
        $timestamp = strtotime( 'tomorrow 3:00' );
        as_schedule_recurring_action( $timestamp, DAY_IN_SECONDS, 'wc_aicc_daily_cleanup', array(), 'wc-aicc' );

        $this->log( 'Cleanup job scheduled' );
    }

    /**
     * Run cleanup
     */
    public function run_cleanup() {
        $this->log( 'Starting cleanup job' );

        $repository = Build_Repository::instance();
        $storage    = R2_Storage::instance();

        // Get expired builds
        $expired_builds = $repository->get_expired_builds( self::EXPIRATION_HOURS );

        if ( empty( $expired_builds ) ) {
            $this->log( 'No expired builds found' );
            return;
        }

        $this->log( 'Found ' . count( $expired_builds ) . ' expired builds' );

        $deleted_count = 0;
        $error_count   = 0;

        foreach ( $expired_builds as $build ) {
            try {
                // Delete R2 objects
                $this->delete_build_assets( $build, $storage );

                // Delete database row
                $repository->delete( $build->id );

                $deleted_count++;
                $this->log( "Deleted build: {$build->build_uuid}" );

            } catch ( \Exception $e ) {
                $error_count++;
                $this->log( "Failed to delete build {$build->build_uuid}: " . $e->getMessage(), 'error' );
            }
        }

        $this->log( "Cleanup completed. Deleted: {$deleted_count}, Errors: {$error_count}" );
    }

    /**
     * Delete build assets from storage
     *
     * @param Build      $build   Build object.
     * @param R2_Storage $storage Storage instance.
     */
    private function delete_build_assets( $build, $storage ) {
        $prefix = "builds/{$build->build_uuid}/";

        if ( $storage->is_configured() ) {
            // Delete from R2
            $storage->delete_by_prefix( $prefix );
        } else {
            // Delete local files
            $upload_dir = wp_upload_dir();
            $local_dir  = $upload_dir['basedir'] . '/wc-aicc-builds/' . $build->build_uuid;

            if ( is_dir( $local_dir ) ) {
                $this->delete_directory( $local_dir );
            }
        }
    }

    /**
     * Recursively delete a directory
     *
     * @param string $dir Directory path.
     * @return bool True on success.
     */
    private function delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return false;
        }

        $files = array_diff( scandir( $dir ), array( '.', '..' ) );
        
        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            
            if ( is_dir( $path ) ) {
                $this->delete_directory( $path );
            } else {
                unlink( $path );
            }
        }

        return rmdir( $dir );
    }

    /**
     * Log message
     *
     * @param string $message Message to log.
     * @param string $level   Log level.
     */
    private function log( $message, $level = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $prefix = '[WC_AICC Cleanup]';
            
            if ( $level === 'error' ) {
                $prefix .= ' ERROR:';
            }

            error_log( $prefix . ' ' . $message );
        }
    }
}
