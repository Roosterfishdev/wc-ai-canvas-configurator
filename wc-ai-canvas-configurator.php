<?php
/**
 * Plugin Name: WC AI Canvas Configurator
 * Plugin URI: https://example.com/wc-ai-canvas-configurator
 * Description: A step-based custom canvas configurator for WooCommerce with AI-powered artwork generation.
 * Version: 1.2.11
 * Author: RoosterFishDev
 * Author URI: https://roosterfish.dev
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-aicc
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'WC_AICC_VERSION', '1.2.11' );
define( 'WC_AICC_PLUGIN_FILE', __FILE__ );
define( 'WC_AICC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_AICC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_AICC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class WC_AI_Canvas_Configurator {

    /**
     * Single instance
     *
     * @var WC_AI_Canvas_Configurator|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return WC_AI_Canvas_Configurator
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
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Autoloader
        require_once WC_AICC_PLUGIN_DIR . 'includes/class-autoloader.php';
        WC_AICC\Autoloader::register();

        // Core classes
        require_once WC_AICC_PLUGIN_DIR . 'includes/class-activator.php';
        require_once WC_AICC_PLUGIN_DIR . 'includes/class-deactivator.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check WooCommerce dependency
        add_action( 'admin_init', array( $this, 'check_woocommerce' ) );
        
        // Initialize plugin after plugins loaded
        add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
        
        // Register activation/deactivation hooks
        register_activation_hook( WC_AICC_PLUGIN_FILE, array( 'WC_AICC\Activator', 'activate' ) );
        register_deactivation_hook( WC_AICC_PLUGIN_FILE, array( 'WC_AICC\Deactivator', 'deactivate' ) );
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            deactivate_plugins( WC_AICC_PLUGIN_BASENAME );
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'WC AI Canvas Configurator requires WooCommerce to be installed and active.', 'wc-aicc' ); ?></p>
        </div>
        <?php
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Bail if WooCommerce is not active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Load text domain
        load_plugin_textdomain( 'wc-aicc', false, dirname( WC_AICC_PLUGIN_BASENAME ) . '/languages' );

        // Check database version and run migrations
        $this->maybe_upgrade_db();

        // Initialize components
        $this->init_components();
    }

    /**
     * Run database upgrades if needed
     */
    private function maybe_upgrade_db() {
        \WC_AICC\Activator::maybe_upgrade();
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Storage adapter
        WC_AICC\Storage\R2_Storage::instance();

        // Build repository
        WC_AICC\Repository\Build_Repository::instance();

        // REST API
        WC_AICC\API\REST_Controller::instance();

        // Action Scheduler jobs
        WC_AICC\Jobs\Job_Handler::instance();

        // WooCommerce integration
        WC_AICC\WooCommerce\Product_Integration::instance();
        WC_AICC\WooCommerce\Cart_Integration::instance();
        WC_AICC\WooCommerce\Order_Integration::instance();

        // Admin
        if ( is_admin() ) {
            WC_AICC\Admin\Admin_Controller::instance();
            WC_AICC\Admin\Product_Meta::instance();
            WC_AICC\Admin\Settings::instance();
        }

        // Frontend
        if ( ! is_admin() || wp_doing_ajax() ) {
            WC_AICC\Frontend\Configurator::instance();
        }

        // Cleanup scheduler
        WC_AICC\Jobs\Cleanup_Job::instance();
    }

    /**
     * Get the plugin URL
     *
     * @param string $path Optional path to append.
     * @return string
     */
    public function plugin_url( $path = '' ) {
        return WC_AICC_PLUGIN_URL . ltrim( $path, '/' );
    }

    /**
     * Get the plugin directory path
     *
     * @param string $path Optional path to append.
     * @return string
     */
    public function plugin_path( $path = '' ) {
        return WC_AICC_PLUGIN_DIR . ltrim( $path, '/' );
    }
}

/**
 * Returns the main instance of WC_AI_Canvas_Configurator
 *
 * @return WC_AI_Canvas_Configurator
 */
function wc_aicc() {
    return WC_AI_Canvas_Configurator::instance();
}

// Initialize the plugin
wc_aicc();
