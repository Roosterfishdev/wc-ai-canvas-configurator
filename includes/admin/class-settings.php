<?php
/**
 * Plugin Settings
 *
 * Handles plugin settings integration with WooCommerce Settings.
 *
 * @package WC_AICC\Admin
 */

namespace WC_AICC\Admin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings class
 */
class Settings {

    /**
     * Singleton instance
     *
     * @var Settings|null
     */
    private static $instance = null;

    /**
     * Settings section ID
     *
     * @var string
     */
    const SECTION_ID = 'wc_aicc';

    /**
     * Get singleton instance
     *
     * @return Settings
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
        // Add settings tab to WooCommerce
        add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section' ) );
        add_filter( 'woocommerce_get_settings_products', array( $this, 'get_settings' ), 10, 2 );
    }

    /**
     * Add settings section to WooCommerce Products settings
     *
     * @param array $sections Existing sections.
     * @return array Modified sections.
     */
    public function add_section( $sections ) {
        $sections[ self::SECTION_ID ] = __( 'AI Canvas Configurator', 'wc-aicc' );
        return $sections;
    }

    /**
     * Get settings for our section
     *
     * @param array  $settings        Existing settings.
     * @param string $current_section Current section ID.
     * @return array Settings array.
     */
    public function get_settings( $settings, $current_section ) {
        if ( $current_section !== self::SECTION_ID ) {
            return $settings;
        }

        $settings = array(
            array(
                'title' => __( 'AI Canvas Configurator Settings', 'wc-aicc' ),
                'type'  => 'title',
                'desc'  => __( 'Configure how the canvas configurator is displayed on product pages.', 'wc-aicc' ),
                'id'    => 'wc_aicc_settings_title',
            ),

            array(
                'title'    => __( 'Render Mode', 'wc-aicc' ),
                'desc'     => __( 'Choose how the configurator is rendered on product pages.', 'wc-aicc' ),
                'id'       => 'wc_aicc_render_mode',
                'type'     => 'select',
                'default'  => 'hook',
                'options'  => array(
                    'hook'      => __( 'Hook (automatic injection)', 'wc-aicc' ),
                    'shortcode' => __( 'Shortcode only (manual placement)', 'wc-aicc' ),
                ),
                'desc_tip' => __( 'Hook mode automatically injects the configurator. Shortcode mode requires you to manually place [wc_aicc_configurator] in your template.', 'wc-aicc' ),
            ),

            array(
                'title'    => __( 'Hook Location', 'wc-aicc' ),
                'desc'     => __( 'Where to inject the configurator when using Hook mode.', 'wc-aicc' ),
                'id'       => 'wc_aicc_hook_location',
                'type'     => 'select',
                'default'  => 'before_add_to_cart_form',
                'options'  => self::get_hook_locations(),
                'desc_tip' => __( 'Choose a WooCommerce hook location. "Before Add to Cart Form" works best with page builders like Bricks.', 'wc-aicc' ),
            ),

            array(
                'title'    => __( 'Hook Priority', 'wc-aicc' ),
                'desc'     => __( 'Priority for the hook (lower = earlier).', 'wc-aicc' ),
                'id'       => 'wc_aicc_hook_priority',
                'type'     => 'number',
                'default'  => 5,
                'custom_attributes' => array(
                    'min'  => 1,
                    'max'  => 100,
                    'step' => 1,
                ),
                'desc_tip' => __( 'Lower numbers execute earlier. Default is 5.', 'wc-aicc' ),
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'wc_aicc_settings_end',
            ),

            array(
                'title' => __( 'Shortcode Usage', 'wc-aicc' ),
                'type'  => 'title',
                'desc'  => $this->get_shortcode_help(),
                'id'    => 'wc_aicc_shortcode_help',
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'wc_aicc_shortcode_help_end',
            ),
        );

        return $settings;
    }

    /**
     * Get available hook locations
     *
     * @return array Hook location options.
     */
    public static function get_hook_locations() {
        return array(
            'before_add_to_cart_form'     => __( 'Before Add to Cart Form (recommended for Bricks)', 'wc-aicc' ),
            'after_add_to_cart_form'      => __( 'After Add to Cart Form', 'wc-aicc' ),
            'before_single_product_summary' => __( 'Before Product Summary', 'wc-aicc' ),
            'after_single_product_summary'  => __( 'After Product Summary', 'wc-aicc' ),
            'product_meta_end'            => __( 'After Product Meta', 'wc-aicc' ),
            'share'                       => __( 'After Share Buttons', 'wc-aicc' ),
        );
    }

    /**
     * Get hook name from location key
     *
     * @param string $location Location key.
     * @return string WooCommerce hook name.
     */
    public static function get_hook_name( $location ) {
        $hooks = array(
            'before_add_to_cart_form'       => 'woocommerce_before_add_to_cart_form',
            'after_add_to_cart_form'        => 'woocommerce_after_add_to_cart_form',
            'before_single_product_summary' => 'woocommerce_before_single_product_summary',
            'after_single_product_summary'  => 'woocommerce_after_single_product_summary',
            'product_meta_end'              => 'woocommerce_product_meta_end',
            'share'                         => 'woocommerce_share',
        );

        return $hooks[ $location ] ?? 'woocommerce_before_add_to_cart_form';
    }

    /**
     * Get shortcode help text
     *
     * @return string Help HTML.
     */
    private function get_shortcode_help() {
        return sprintf(
            '<p>%s</p><code>[wc_aicc_configurator]</code><p>%s</p><code>[wc_aicc_configurator product_id="123"]</code>',
            __( 'Use this shortcode on a product page to render the configurator:', 'wc-aicc' ),
            __( 'Or specify a product ID to use on any page:', 'wc-aicc' )
        );
    }

    /**
     * Get render mode setting
     *
     * @return string 'hook' or 'shortcode'.
     */
    public static function get_render_mode() {
        return get_option( 'wc_aicc_render_mode', 'hook' );
    }

    /**
     * Get hook location setting
     *
     * @return string Hook location key.
     */
    public static function get_hook_location() {
        return get_option( 'wc_aicc_hook_location', 'before_add_to_cart_form' );
    }

    /**
     * Get hook priority setting
     *
     * @return int Hook priority.
     */
    public static function get_hook_priority() {
        return (int) get_option( 'wc_aicc_hook_priority', 5 );
    }

    /**
     * Check if auto-injection is enabled
     *
     * @return bool True if hook mode is active.
     */
    public static function is_auto_inject_enabled() {
        return self::get_render_mode() === 'hook';
    }
}
