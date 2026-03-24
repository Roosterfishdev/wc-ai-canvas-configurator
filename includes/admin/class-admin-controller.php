<?php
/**
 * Admin Controller
 *
 * Handles admin pages and functionality.
 *
 * @package WC_AICC\Admin
 */

namespace WC_AICC\Admin;

use WC_AICC\Models\Build;
use WC_AICC\Repository\Build_Repository;
use WC_AICC\Storage\R2_Storage;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Controller class
 */
class Admin_Controller {

    /**
     * Singleton instance
     *
     * @var Admin_Controller|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Admin_Controller
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
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'AI Canvas Builds', 'wc-aicc' ),
            __( 'AI Canvas Builds', 'wc-aicc' ),
            'manage_woocommerce',
            'wc-aicc-builds',
            array( $this, 'render_builds_page' )
        );
    }

    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wc-aicc-builds' ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        $build_uuid = isset( $_GET['build_uuid'] ) ? sanitize_text_field( wp_unslash( $_GET['build_uuid'] ) ) : '';
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( empty( $action ) || empty( $build_uuid ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $nonce, 'wc_aicc_admin_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'wc-aicc' ) );
        }

        $repository = Build_Repository::instance();
        $build = $repository->get_by_uuid( $build_uuid );

        if ( ! $build ) {
            wp_die( esc_html__( 'Build not found.', 'wc-aicc' ) );
        }

        switch ( $action ) {
            case 'retry':
                $this->action_retry( $build, $repository );
                break;

            case 'expire':
                $this->action_expire( $build, $repository );
                break;
        }

        // Redirect back to builds page
        wp_safe_redirect( admin_url( 'admin.php?page=wc-aicc-builds&action_completed=' . $action ) );
        exit;
    }

    /**
     * Retry action - re-enqueue processing job
     *
     * @param Build            $build      Build object.
     * @param Build_Repository $repository Repository.
     */
    private function action_retry( $build, $repository ) {
        // Reset status to processing
        $repository->update_by_uuid(
            $build->build_uuid,
            array(
                'status'        => Build::STATUS_PROCESSING,
                'error_message' => '',
            )
        );

        // Enqueue job
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action(
                'wc_aicc_process_build',
                array( 'build_uuid' => $build->build_uuid ),
                'wc-aicc'
            );
        }
    }

    /**
     * Expire action - delete build
     *
     * @param Build            $build      Build object.
     * @param Build_Repository $repository Repository.
     */
    private function action_expire( $build, $repository ) {
        $storage = R2_Storage::instance();

        // Delete storage assets
        $prefix = "builds/{$build->build_uuid}/";

        if ( $storage->is_configured() ) {
            $storage->delete_by_prefix( $prefix );
        } else {
            // Delete local files
            $upload_dir = wp_upload_dir();
            $local_dir  = $upload_dir['basedir'] . '/wc-aicc-builds/' . $build->build_uuid;

            if ( is_dir( $local_dir ) ) {
                $this->delete_directory( $local_dir );
            }
        }

        // Delete from database
        $repository->delete( $build->id );
    }

    /**
     * Recursively delete directory
     *
     * @param string $dir Directory path.
     * @return bool
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
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'woocommerce_page_wc-aicc-builds' ) {
            return;
        }

        wp_enqueue_style(
            'wc-aicc-admin',
            WC_AICC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_AICC_VERSION
        );
    }

    /**
     * Render builds page
     */
    public function render_builds_page() {
        // Handle status filter
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        
        // Get builds
        $repository = Build_Repository::instance();
        $builds = $repository->get_recent_builds( 50, 0, $status_filter );
        $storage = R2_Storage::instance();

        // Get counts
        $counts = array(
            'all'        => $repository->count_builds(),
            'draft'      => $repository->count_builds( Build::STATUS_DRAFT ),
            'processing' => $repository->count_builds( Build::STATUS_PROCESSING ),
            'ready'      => $repository->count_builds( Build::STATUS_READY ),
            'failed'     => $repository->count_builds( Build::STATUS_FAILED ),
            'ordered'    => $repository->count_builds( Build::STATUS_ORDERED ),
        );

        // Check for action completion message
        $action_completed = isset( $_GET['action_completed'] ) ? sanitize_text_field( wp_unslash( $_GET['action_completed'] ) ) : '';

        include WC_AICC_PLUGIN_DIR . 'templates/admin/builds-list.php';
    }

    /**
     * Get asset URL
     *
     * @param string     $key     R2 object key.
     * @param R2_Storage $storage Storage instance.
     * @return string|null URL or null.
     */
    public function get_asset_url( $key, $storage ) {
        if ( empty( $key ) ) {
            return null;
        }

        if ( ! $storage->is_configured() ) {
            $upload_dir = wp_upload_dir();
            $local_path = str_replace( 'builds/', 'wc-aicc-builds/', $key );
            return $upload_dir['baseurl'] . '/' . $local_path;
        }

        return $storage->get_public_url( $key );
    }

    /**
     * Get status label
     *
     * @param string $status Status code.
     * @return string Status label.
     */
    public function get_status_label( $status ) {
        $labels = array(
            Build::STATUS_DRAFT      => __( 'Draft', 'wc-aicc' ),
            Build::STATUS_PROCESSING => __( 'Processing', 'wc-aicc' ),
            Build::STATUS_READY      => __( 'Ready', 'wc-aicc' ),
            Build::STATUS_FAILED     => __( 'Failed', 'wc-aicc' ),
            Build::STATUS_ORDERED    => __( 'Ordered', 'wc-aicc' ),
            Build::STATUS_EXPIRED    => __( 'Expired', 'wc-aicc' ),
        );

        return $labels[ $status ] ?? $status;
    }

    /**
     * Get status class
     *
     * @param string $status Status code.
     * @return string CSS class.
     */
    public function get_status_class( $status ) {
        $classes = array(
            Build::STATUS_DRAFT      => 'status-draft',
            Build::STATUS_PROCESSING => 'status-processing',
            Build::STATUS_READY      => 'status-ready',
            Build::STATUS_FAILED     => 'status-failed',
            Build::STATUS_ORDERED    => 'status-ordered',
            Build::STATUS_EXPIRED    => 'status-expired',
        );

        return $classes[ $status ] ?? '';
    }
}
