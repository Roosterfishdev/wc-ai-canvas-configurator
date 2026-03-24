<?php
/**
 * Product Meta
 *
 * Handles product edit page meta fields.
 *
 * @package WC_AICC\Admin
 */

namespace WC_AICC\Admin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Meta class
 */
class Product_Meta {

    /**
     * Singleton instance
     *
     * @var Product_Meta|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Product_Meta
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
        // Add product data tab
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
        
        // Add product data panel
        add_action( 'woocommerce_product_data_panels', array( $this, 'add_product_data_panel' ) );
        
        // Save product meta
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

        // Add variation fields
        add_action( 'woocommerce_variation_options', array( $this, 'add_variation_options' ), 10, 3 );
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_meta' ), 10, 2 );
    }

    /**
     * Add product data tab
     *
     * @param array $tabs Existing tabs.
     * @return array Modified tabs.
     */
    public function add_product_data_tab( $tabs ) {
        $tabs['wc_aicc'] = array(
            'label'    => __( 'AI Canvas', 'wc-aicc' ),
            'target'   => 'wc_aicc_product_data',
            'class'    => array( 'show_if_variable' ),
            'priority' => 80,
        );

        return $tabs;
    }

    /**
     * Add product data panel
     */
    public function add_product_data_panel() {
        global $post;

        $enabled = get_post_meta( $post->ID, '_wc_aicc_enabled', true );
        ?>
        <div id="wc_aicc_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="_wc_aicc_enabled">
                        <?php esc_html_e( 'Enable AI Canvas Configurator', 'wc-aicc' ); ?>
                    </label>
                    <input type="checkbox" 
                           class="checkbox" 
                           name="_wc_aicc_enabled" 
                           id="_wc_aicc_enabled" 
                           value="yes" 
                           <?php checked( $enabled, 'yes' ); ?> />
                    <span class="description">
                        <?php esc_html_e( 'Replace the standard add-to-cart with the AI canvas configurator.', 'wc-aicc' ); ?>
                    </span>
                </p>

                <p class="form-field">
                    <span class="description">
                        <?php esc_html_e( 'Note: This product must be a Variable Product. Each variation represents a canvas size. The variation attribute should indicate the size (e.g., "16x20", "24x36").', 'wc-aicc' ); ?>
                    </span>
                </p>
            </div>

            <div class="options_group">
                <h4 style="padding-left: 12px;"><?php esc_html_e( 'Configuration', 'wc-aicc' ); ?></h4>
                
                <p class="form-field">
                    <span class="description" style="margin-left: 12px;">
                        <?php esc_html_e( 'Additional settings will be available here in future versions.', 'wc-aicc' ); ?>
                    </span>
                </p>
            </div>

            <div class="options_group">
                <h4 style="padding-left: 12px;"><?php esc_html_e( 'Aspect Ratios', 'wc-aicc' ); ?></h4>
                
                <p class="form-field">
                    <span class="description" style="margin-left: 12px;">
                        <?php esc_html_e( 'Set aspect ratios for each variation in the Variations tab, or let the system calculate them from the size label (e.g., "16x20" → 4:5).', 'wc-aicc' ); ?>
                    </span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Save product meta
     *
     * @param int $post_id Product ID.
     */
    public function save_product_meta( $post_id ) {
        $enabled = isset( $_POST['_wc_aicc_enabled'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_wc_aicc_enabled', $enabled );
    }

    /**
     * Add variation options (checkboxes next to other options)
     *
     * @param int     $loop           Variation loop index.
     * @param array   $variation_data Variation data.
     * @param WP_Post $variation      Variation post object.
     */
    public function add_variation_options( $loop, $variation_data, $variation ) {
        // Could add a checkbox here if needed
    }

    /**
     * Add variation fields
     *
     * @param int     $loop           Variation loop index.
     * @param array   $variation_data Variation data.
     * @param WP_Post $variation      Variation post object.
     */
    public function add_variation_fields( $loop, $variation_data, $variation ) {
        $aspect_ratio = get_post_meta( $variation->ID, '_wc_aicc_aspect_ratio', true );
        ?>
        <div class="wc-aicc-variation-fields">
            <p class="form-row form-row-first">
                <label><?php esc_html_e( 'AI Canvas Aspect Ratio', 'wc-aicc' ); ?></label>
                <input type="text" 
                       class="short" 
                       name="wc_aicc_aspect_ratio[<?php echo esc_attr( $loop ); ?>]" 
                       value="<?php echo esc_attr( $aspect_ratio ); ?>" 
                       placeholder="<?php esc_attr_e( 'e.g., 4:5 (auto-calculated if empty)', 'wc-aicc' ); ?>" />
            </p>
        </div>
        <?php
    }

    /**
     * Save variation meta
     *
     * @param int $variation_id Variation ID.
     * @param int $loop         Variation loop index.
     */
    public function save_variation_meta( $variation_id, $loop ) {
        if ( isset( $_POST['wc_aicc_aspect_ratio'][ $loop ] ) ) {
            $aspect_ratio = sanitize_text_field( wp_unslash( $_POST['wc_aicc_aspect_ratio'][ $loop ] ) );
            update_post_meta( $variation_id, '_wc_aicc_aspect_ratio', $aspect_ratio );
        }
    }
}
