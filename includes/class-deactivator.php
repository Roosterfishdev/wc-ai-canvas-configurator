<?php
/**
 * Plugin Deactivator
 *
 * @package WC_AICC
 */

namespace WC_AICC;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Deactivator class
 */
class Deactivator {

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Unschedule cleanup event
        self::unschedule_cleanup();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Unschedule cleanup event
     */
    private static function unschedule_cleanup() {
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            as_unschedule_all_actions( 'wc_aicc_daily_cleanup' );
        }
    }
}
