<?php
/**
 * Order Integration
 *
 * Handles order-level WooCommerce integration.
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
 * Order Integration class
 */
class Order_Integration {

    /**
     * Singleton instance
     *
     * @var Order_Integration|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Order_Integration
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
        // Copy build data to order item
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'create_order_line_item' ), 10, 4 );

        // Mark build as ordered after order created
        add_action( 'woocommerce_checkout_order_created', array( $this, 'mark_builds_ordered' ), 10, 1 );

        // Display build data in admin order
        add_action( 'woocommerce_before_order_itemmeta', array( $this, 'display_order_item_meta' ), 10, 3 );

        // Also handle order through REST API / programmatic orders
        add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 10, 2 );
    }

    /**
     * Copy build data to order line item
     *
     * @param \WC_Order_Item_Product $item          Order item.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values        Cart item values.
     * @param \WC_Order              $order         Order object.
     */
    public function create_order_line_item( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['wc_aicc_build_uuid'] ) ) {
            return;
        }

        // Get full build data
        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $values['wc_aicc_build_uuid'] );

        if ( ! $build ) {
            return;
        }

        // Store build data in order item meta
        $item->add_meta_data( '_wc_aicc_build_uuid', $build->build_uuid );
        $item->add_meta_data( '_wc_aicc_customization_options', $build->customization_options );
        $item->add_meta_data( '_wc_aicc_size_label', $build->size_label );
        $item->add_meta_data( '_wc_aicc_aspect_ratio', $build->aspect_ratio );
        $item->add_meta_data( '_wc_aicc_original_key', $build->original_key );
        $item->add_meta_data( '_wc_aicc_cropped_key', $build->cropped_key );
        $item->add_meta_data( '_wc_aicc_final_art_key', $build->final_art_key );
        $item->add_meta_data( '_wc_aicc_mockup_key', $build->mockup_key );

        // Store URLs for easy access
        $storage = R2_Storage::instance();
        
        if ( $build->final_art_key ) {
            $item->add_meta_data( '_wc_aicc_final_art_url', $this->get_asset_url( $build->final_art_key, $storage ) );
        }
        
        if ( $build->mockup_key ) {
            $item->add_meta_data( '_wc_aicc_mockup_url', $this->get_asset_url( $build->mockup_key, $storage ) );
        }
    }

    /**
     * Mark builds as ordered after checkout
     *
     * @param \WC_Order $order Order object.
     */
    public function mark_builds_ordered( $order ) {
        $repository = Build_Repository::instance();

        foreach ( $order->get_items() as $item ) {
            $build_uuid = $item->get_meta( '_wc_aicc_build_uuid' );
            
            if ( empty( $build_uuid ) ) {
                continue;
            }

            // Update build status
            $repository->update_by_uuid(
                $build_uuid,
                array( 'status' => Build::STATUS_ORDERED )
            );
        }
    }

    /**
     * Handle new order (for API/programmatic orders)
     *
     * @param int       $order_id Order ID.
     * @param \WC_Order $order    Order object.
     */
    public function handle_new_order( $order_id, $order ) {
        // This handles orders created outside checkout flow
        $this->mark_builds_ordered( $order );
    }

    /**
     * Display build data in admin order view
     *
     * @param int                    $item_id Order item ID.
     * @param \WC_Order_Item_Product $item    Order item.
     * @param \WC_Product            $product Product object.
     */
    public function display_order_item_meta( $item_id, $item, $product ) {
        if ( ! is_admin() ) {
            return;
        }

        $build_uuid = $item->get_meta( '_wc_aicc_build_uuid' );
        
        if ( empty( $build_uuid ) ) {
            return;
        }

        $customization = $item->get_meta( '_wc_aicc_customization_options' );
        $final_art_url = $item->get_meta( '_wc_aicc_final_art_url' );
        $mockup_url   = $item->get_meta( '_wc_aicc_mockup_url' );

        $options_parts = is_array( $customization )
            ? \WC_AICC\Config\Prompt_Builder::summarize_option_labels( $customization )
            : array();

        ?>
        <div class="wc-aicc-order-item-meta" style="margin-top: 10px; padding: 10px; background: #f8f8f8; border-radius: 4px;">
            <strong><?php esc_html_e( 'AI Canvas Build', 'wc-aicc' ); ?></strong>
            
            <table class="wc-aicc-meta-table" style="margin-top: 5px; font-size: 12px;">
                <tr>
                    <td><strong><?php esc_html_e( 'Build ID:', 'wc-aicc' ); ?></strong></td>
                    <td><code><?php echo esc_html( $build_uuid ); ?></code></td>
                </tr>
                <?php if ( ! empty( $options_parts ) ) : ?>
                <tr>
                    <td><strong><?php esc_html_e( 'Options:', 'wc-aicc' ); ?></strong></td>
                    <td><?php echo esc_html( implode( ', ', $options_parts ) ); ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <div class="wc-aicc-preview-links" style="margin-top: 10px;">
                <?php if ( $final_art_url ) : ?>
                    <a href="<?php echo esc_url( $final_art_url ); ?>" target="_blank" class="button button-small">
                        <?php esc_html_e( 'View Final Art', 'wc-aicc' ); ?>
                    </a>
                <?php endif; ?>
                
                <?php if ( $mockup_url ) : ?>
                    <a href="<?php echo esc_url( $mockup_url ); ?>" target="_blank" class="button button-small">
                        <?php esc_html_e( 'View Mockup', 'wc-aicc' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
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
