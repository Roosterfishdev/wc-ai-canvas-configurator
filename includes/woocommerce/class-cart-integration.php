<?php
/**
 * Cart Integration
 *
 * Handles cart-level WooCommerce integration.
 *
 * @package WC_AICC\WooCommerce
 */

namespace WC_AICC\WooCommerce;

use WC_AICC\Models\Build;
use WC_AICC\Repository\Build_Repository;
use WC_AICC\Storage\R2_Storage;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cart Integration class
 */
class Cart_Integration {

    /**
     * Singleton instance
     *
     * @var Cart_Integration|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Cart_Integration
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
        // Add build data to cart item
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );

        // Display build info in cart
        add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

        // Validate cart item
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );

        // Handle cart item thumbnail
        add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 10, 3 );

        // Make cart item unique based on build
        add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 2 );

        // Restore build data from session
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 3 );
    }

    /**
     * Add build data to cart item
     *
     * @param array $cart_item_data Cart item data.
     * @param int   $product_id     Product ID.
     * @param int   $variation_id   Variation ID.
     * @return array Modified cart item data.
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        // Check for build_uuid in request
        $build_uuid = isset( $_POST['wc_aicc_build_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_aicc_build_uuid'] ) ) : '';

        if ( empty( $build_uuid ) ) {
            return $cart_item_data;
        }

        // Validate build
        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $build_uuid );

        if ( ! $build || $build->status !== Build::STATUS_READY ) {
            return $cart_item_data;
        }

        // Add build data to cart item
        $cart_item_data['wc_aicc_build_uuid']           = $build->build_uuid;
        $cart_item_data['wc_aicc_customization_options'] = $build->customization_options;
        $cart_item_data['wc_aicc_size_label']            = $build->size_label;
        $cart_item_data['wc_aicc_aspect_ratio']  = $build->aspect_ratio;
        $cart_item_data['wc_aicc_final_art_key'] = $build->final_art_key;
        $cart_item_data['wc_aicc_mockup_key']    = $build->mockup_key;

        // Make cart item unique
        $cart_item_data['unique_key'] = md5( $build_uuid );

        return $cart_item_data;
    }

    /**
     * Display build info in cart
     *
     * @param array $item_data Item data to display.
     * @param array $cart_item Cart item data.
     * @return array Modified item data.
     */
    public function get_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['wc_aicc_build_uuid'] ) ) {
            return $item_data;
        }

        // Add customization options summary
        $options = $cart_item['wc_aicc_customization_options'] ?? array();
        $parts   = is_array( $options ) ? \WC_AICC\Config\Prompt_Builder::summarize_option_labels( $options ) : array();

        if ( ! empty( $parts ) ) {
            $item_data[] = array(
                'key'   => __( 'Options', 'wc-aicc' ),
                'value' => esc_html( implode( ', ', $parts ) ),
            );
        }

        // Add preview thumbnail link
        $storage = R2_Storage::instance();
        $mockup_url = $this->get_asset_url( $cart_item['wc_aicc_mockup_key'], $storage );
        
        if ( $mockup_url ) {
            $item_data[] = array(
                'key'   => __( 'Preview', 'wc-aicc' ),
                'value' => '<a href="' . esc_url( $mockup_url ) . '" target="_blank">' . __( 'View artwork', 'wc-aicc' ) . '</a>',
            );
        }

        return $item_data;
    }

    /**
     * Validate add to cart
     *
     * @param bool $passed     Whether validation passed.
     * @param int  $product_id Product ID.
     * @param int  $quantity   Quantity.
     * @param int  $variation_id Variation ID.
     * @param array $variations Variation attributes.
     * @return bool
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
        // Check if this product requires configurator
        if ( ! Product_Integration::is_configurator_enabled( $product_id ) ) {
            return $passed;
        }

        // Check for build_uuid
        $build_uuid = isset( $_POST['wc_aicc_build_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_aicc_build_uuid'] ) ) : '';

        if ( empty( $build_uuid ) ) {
            wc_add_notice( __( 'Please complete the canvas configuration before adding to cart.', 'wc-aicc' ), 'error' );
            return false;
        }

        // Validate build
        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $build_uuid );

        if ( ! $build ) {
            wc_add_notice( __( 'Invalid build. Please start over.', 'wc-aicc' ), 'error' );
            return false;
        }

        if ( $build->status !== Build::STATUS_READY ) {
            wc_add_notice( __( 'Your artwork is not ready yet. Please wait for processing to complete.', 'wc-aicc' ), 'error' );
            return false;
        }

        // Validate build matches product/variation
        if ( $build->product_id !== $product_id || $build->variation_id !== $variation_id ) {
            wc_add_notice( __( 'Build does not match selected product. Please start over.', 'wc-aicc' ), 'error' );
            return false;
        }

        return $passed;
    }

    /**
     * Custom cart item thumbnail
     *
     * @param string $thumbnail  Default thumbnail HTML.
     * @param array  $cart_item  Cart item data.
     * @param string $cart_item_key Cart item key.
     * @return string Modified thumbnail HTML.
     */
    public function cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['wc_aicc_mockup_key'] ) ) {
            return $thumbnail;
        }

        $storage = R2_Storage::instance();
        $mockup_url = $this->get_asset_url( $cart_item['wc_aicc_mockup_key'], $storage );

        if ( ! $mockup_url ) {
            return $thumbnail;
        }

        return \WC_AICC\Config\Preview_Watermark::wrap_image_html(
            $mockup_url,
            __( 'Custom artwork preview', 'wc-aicc' ),
            'wc-aicc-cart-thumbnail'
        );
    }

    /**
     * Add cart item (makes item unique based on build)
     *
     * @param array  $cart_item     Cart item data.
     * @param string $cart_item_key Cart item key.
     * @return array Modified cart item.
     */
    public function add_cart_item( $cart_item, $cart_item_key ) {
        if ( ! empty( $cart_item['wc_aicc_build_uuid'] ) ) {
            // Store the build data
            $cart_item['data']->add_meta_data( '_wc_aicc_build_uuid', $cart_item['wc_aicc_build_uuid'] );
        }

        return $cart_item;
    }

    /**
     * Restore cart item from session
     *
     * @param array $cart_item      Cart item data.
     * @param array $values         Session values.
     * @param string $cart_item_key Cart item key.
     * @return array Modified cart item.
     */
    public function get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
        if ( ! empty( $values['wc_aicc_build_uuid'] ) ) {
            $cart_item['wc_aicc_build_uuid']    = $values['wc_aicc_build_uuid'];
            $cart_item['wc_aicc_customization_options'] = $values['wc_aicc_customization_options'] ?? array();
            $cart_item['wc_aicc_size_label']    = $values['wc_aicc_size_label'] ?? '';
            $cart_item['wc_aicc_aspect_ratio']  = $values['wc_aicc_aspect_ratio'] ?? '';
            $cart_item['wc_aicc_final_art_key'] = $values['wc_aicc_final_art_key'] ?? '';
            $cart_item['wc_aicc_mockup_key']    = $values['wc_aicc_mockup_key'] ?? '';
        }

        return $cart_item;
    }

    /**
     * Get asset URL
     *
     * @param string     $key     R2 object key.
     * @param R2_Storage $storage Storage instance.
     * @return string|null URL or null.
     */
    private function get_asset_url( $key, $storage ) {
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
}
