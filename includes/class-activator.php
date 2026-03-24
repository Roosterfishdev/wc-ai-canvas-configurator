<?php
/**
 * Plugin Activator
 *
 * @package WC_AICC
 */

namespace WC_AICC;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Activator class
 */
class Activator {

    /**
     * Database table version
     *
     * @var string
     */
    const DB_VERSION = '1.1.0';

    /**
     * Plugin activation
     */
    public static function activate() {
        // Check WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( WC_AICC_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'WC AI Canvas Configurator requires WooCommerce to be installed and active.', 'wc-aicc' ),
                'Plugin dependency check',
                array( 'back_link' => true )
            );
        }

        // Create database tables
        self::create_tables();

        // Schedule cleanup event
        self::schedule_cleanup();

        // Store activation time
        add_option( 'wc_aicc_activated', time() );

        // Store DB version
        update_option( 'wc_aicc_db_version', self::DB_VERSION );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Run database schema upgrades (add new columns, etc.)
     * Call from init when DB version is older than current.
     */
    public static function maybe_upgrade() {
        $current = get_option( 'wc_aicc_db_version', '0' );
        if ( version_compare( $current, self::DB_VERSION, '>=' ) ) {
            return;
        }
        self::create_tables();
        update_option( 'wc_aicc_db_version', self::DB_VERSION );
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'ai_builds';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            build_uuid CHAR(36) NOT NULL,
            session_key VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED NOT NULL,
            size_label VARCHAR(100) NOT NULL DEFAULT '',
            aspect_ratio VARCHAR(20) NOT NULL DEFAULT '',
            style_id VARCHAR(100) NOT NULL DEFAULT '',
            notes TEXT NULL,
            customization_options LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            regen_count INT UNSIGNED NOT NULL DEFAULT 0,
            original_key VARCHAR(500) NULL,
            cropped_key VARCHAR(500) NULL,
            final_art_key VARCHAR(500) NULL,
            mockup_key VARCHAR(500) NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY build_uuid (build_uuid),
            KEY session_key (session_key),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY product_id (product_id),
            KEY variation_id (variation_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Log any errors
        if ( ! empty( $wpdb->last_error ) ) {
            error_log( 'WC AICC table creation error: ' . $wpdb->last_error );
        }
    }

    /**
     * Schedule daily cleanup event
     */
    private static function schedule_cleanup() {
        if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
            // Action Scheduler not yet available, will be scheduled on first init
            update_option( 'wc_aicc_needs_cleanup_schedule', true );
            return;
        }

        // Clear existing schedule
        as_unschedule_all_actions( 'wc_aicc_daily_cleanup' );

        // Schedule daily cleanup at 3 AM
        $timestamp = strtotime( 'tomorrow 3:00' );
        as_schedule_recurring_action( $timestamp, DAY_IN_SECONDS, 'wc_aicc_daily_cleanup', array(), 'wc-aicc' );
    }
}
