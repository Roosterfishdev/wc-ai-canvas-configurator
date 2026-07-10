<?php
/**
 * Product Integration
 *
 * Handles product-level WooCommerce integration.
 * Supports configurable hook locations for compatibility with page builders like Bricks.
 *
 * @package WC_AICC\WooCommerce
 */

namespace WC_AICC\WooCommerce;

use WC_AICC\Admin\Settings;
use WC_AICC\Config\Size_Aspect_Map;
use WC_AICC\Config\Size_Display;
use WC_AICC\Config\Sizing_Guide;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Integration class
 */
class Product_Integration {

    /**
     * Singleton instance
     *
     * @var Product_Integration|null
     */
    private static $instance = null;

    /**
     * Flag to prevent double rendering
     *
     * @var bool
     */
    private $rendered = false;

    /**
     * Get singleton instance
     *
     * @return Product_Integration
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
        // Replace add to cart for enabled products (always needed)
        add_action( 'woocommerce_before_single_product', array( $this, 'maybe_replace_add_to_cart' ) );
        
        // Only add automatic hook if not in shortcode-only mode
        if ( $this->should_auto_inject() ) {
            $this->register_configurator_hook();
        }

        // Reset rendered flag on new product
        add_action( 'woocommerce_before_single_product', array( $this, 'reset_rendered_flag' ), 1 );
    }

    /**
     * Check if we should auto-inject the configurator
     *
     * @return bool
     */
    private function should_auto_inject() {
        // Check if Settings class is loaded (it may not be in admin context during init)
        if ( class_exists( Settings::class ) ) {
            return Settings::is_auto_inject_enabled();
        }
        
        // Fallback to direct option check
        return get_option( 'wc_aicc_render_mode', 'hook' ) === 'hook';
    }

    /**
     * Register the configurator hook based on settings
     *
     * Note: before_add_to_cart_form and after_add_to_cart_form are fired from
     * inside the add-to-cart template. Since we remove that template for
     * configurator products, those hooks never run. We map them to
     * woocommerce_single_product_summary at priority 30 instead (replacing
     * the add-to-cart slot).
     */
    private function register_configurator_hook() {
        $location = get_option( 'wc_aicc_hook_location', 'before_add_to_cart_form' );
        $priority = (int) get_option( 'wc_aicc_hook_priority', 5 );

        list( $hook_name, $hook_priority ) = $this->get_hook_and_priority( $location, $priority );

        add_action( $hook_name, array( $this, 'render_configurator' ), $hook_priority );
    }

    /**
     * Get WooCommerce hook name and priority for configurator injection
     *
     * before/after_add_to_cart_form are inside the add-to-cart template,
     * which we remove for configurator products. So those hooks never fire.
     * We use woocommerce_single_product_summary at 30 (add-to-cart slot).
     *
     * @param string $location User's location setting.
     * @param int    $priority User's priority setting.
     * @return array [ hook_name, priority ]
     */
    private function get_hook_and_priority( $location, $priority ) {
        $mapping = array(
            'before_add_to_cart_form'       => array( 'woocommerce_single_product_summary', 30 ),
            'after_add_to_cart_form'        => array( 'woocommerce_single_product_summary', 31 ),
            'before_single_product_summary' => array( 'woocommerce_before_single_product_summary', $priority ),
            'after_single_product_summary'  => array( 'woocommerce_after_single_product_summary', $priority ),
            'product_meta_end'              => array( 'woocommerce_product_meta_end', $priority ),
            'share'                         => array( 'woocommerce_share', $priority ),
        );

        $pair = $mapping[ $location ] ?? array( 'woocommerce_single_product_summary', 30 );
        return array( $pair[0], $pair[1] );
    }

    /**
     * Reset rendered flag for new product
     */
    public function reset_rendered_flag() {
        $this->rendered = false;
    }

    /**
     * Check if product has configurator enabled
     *
     * @param int $product_id Product ID.
     * @return bool
     */
    public static function is_configurator_enabled( $product_id ) {
        return get_post_meta( $product_id, '_wc_aicc_enabled', true ) === 'yes';
    }

    /**
     * Maybe replace add to cart
     */
    public function maybe_replace_add_to_cart() {
        global $product;

        if ( ! $product ) {
            return;
        }

        if ( ! self::is_configurator_enabled( $product->get_id() ) ) {
            return;
        }

        // Remove default add to cart for variable products
        if ( $product->is_type( 'variable' ) ) {
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        }
    }

    /**
     * Render configurator
     * 
     * This is called both from WooCommerce hooks and can be called directly.
     *
     * @param int|null $product_id Optional product ID. If null, uses global $product.
     * @return string|void Returns HTML if $return is true, otherwise echoes.
     */
    public function render_configurator( $product_id = null ) {
        // Prevent double rendering
        if ( $this->rendered ) {
            return;
        }

        // Get product
        if ( $product_id ) {
            $product = wc_get_product( $product_id );
        } else {
            global $product;
        }

        if ( ! $product ) {
            return;
        }

        if ( ! self::is_configurator_enabled( $product->get_id() ) ) {
            return;
        }

        if ( ! $product->is_type( 'variable' ) ) {
            // Only works with variable products (sizes are variations)
            echo '<p class="wc-aicc-notice">' . esc_html__( 'Canvas configurator requires a variable product with size variations.', 'wc-aicc' ) . '</p>';
            return;
        }

        // Mark as rendered
        $this->rendered = true;

        // Get variations for size selection
        $variations = $this->get_product_variations( $product );

        // Get customization options config
        $options = \WC_AICC\Config\Prompt_Builder::get_options_config();

        // Enqueue assets
        $this->enqueue_assets( $product, $variations, $options );

        // Render template
        include WC_AICC_PLUGIN_DIR . 'templates/configurator.php';
    }

    /**
     * Render configurator and return HTML
     *
     * @param int $product_id Product ID.
     * @return string HTML output.
     */
    public function render_configurator_html( $product_id ) {
        ob_start();
        $this->render_configurator( $product_id );
        return ob_get_clean();
    }

    /**
     * Get product variations with size info
     *
     * @param \WC_Product_Variable $product Product object.
     * @return array Variations data.
     */
    private function get_product_variations( $product ) {
        $variations      = array();
        $variation_ids   = $product->get_children();

        foreach ( $variation_ids as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            
            if ( ! $variation || ! $variation->is_purchasable() ) {
                continue;
            }

            // Get size attribute
            $attributes = $variation->get_variation_attributes();
            $size_label = '';
            
            foreach ( $attributes as $attr_name => $attr_value ) {
                // Look for size-related attributes
                if ( stripos( $attr_name, 'size' ) !== false || stripos( $attr_name, 'pa_size' ) !== false ) {
                    $size_label = $attr_value;
                    break;
                }
            }

            // If no size attribute, use first attribute
            if ( empty( $size_label ) && ! empty( $attributes ) ) {
                $size_label = reset( $attributes );
            }

            // Aspect ratio: variation meta → canonical size map → gcd from label
            $aspect_ratio = get_post_meta( $variation_id, '_wc_aicc_aspect_ratio', true );

            if ( empty( $aspect_ratio ) ) {
                $aspect_ratio = Size_Aspect_Map::resolve( $size_label );
            }

            if ( empty( $aspect_ratio ) ) {
                $aspect_ratio = Size_Aspect_Map::resolve_for_label( $size_label );
            }

            $variations[] = Size_Display::enrich_variation(
                array(
                    'id'           => $variation_id,
                    'size_label'   => $size_label,
                    'aspect_ratio' => $aspect_ratio,
                    'price'        => $variation->get_price(),
                    'price_html'   => $variation->get_price_html(),
                    'in_stock'     => $variation->is_in_stock(),
                )
            );
        }

        return $variations;
    }

    /**
     * Calculate aspect ratio from size label
     *
     * @param string $size_label Size label (e.g., "16x20", "24x36").
     * @return string Aspect ratio (e.g., "4:5", "2:3").
     */
    private function calculate_aspect_ratio( $size_label ) {
        // Try to parse dimensions from label
        if ( preg_match( '/(\d+)\s*[x×]\s*(\d+)/i', $size_label, $matches ) ) {
            $width  = (int) $matches[1];
            $height = (int) $matches[2];

            if ( $width > 0 && $height > 0 ) {
                $gcd = $this->gcd( $width, $height );
                return ( $width / $gcd ) . ':' . ( $height / $gcd );
            }
        }

        // Default to 1:1
        return '1:1';
    }

    /**
     * Calculate greatest common divisor
     *
     * @param int $a First number.
     * @param int $b Second number.
     * @return int GCD.
     */
    private function gcd( $a, $b ) {
        while ( $b != 0 ) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return $a;
    }

    /**
     * Enqueue configurator assets
     *
     * @param \WC_Product $product    Product object.
     * @param array       $variations Variations data.
     * @param array       $options    Customization options config.
     */
    private function enqueue_assets( $product, $variations, $options ) {
        // Enqueue CSS
        wp_enqueue_style(
            'wc-aicc-configurator',
            WC_AICC_PLUGIN_URL . 'assets/css/configurator.css',
            array(),
            WC_AICC_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'wc-aicc-configurator',
            WC_AICC_PLUGIN_URL . 'assets/js/configurator.js',
            array(),
            WC_AICC_VERSION,
            true
        );

        // Pass data to JS
        wp_localize_script(
            'wc-aicc-configurator',
            'wcAiccConfig',
            array(
                'productId'   => $product->get_id(),
                'variations'  => $variations,
                'options'     => $options,
                'optionDefaults' => \WC_AICC\Config\Prompt_Builder::DEFAULTS,
                'customizeFlow' => \WC_AICC\Config\Prompt_Builder::get_customize_flow_meta(),
                'styleCustomizeFlows' => \WC_AICC\Config\Prompt_Builder::get_style_customize_flows(),
                'sizingGuide'         => Sizing_Guide::get_panel_data(),
                'previewWatermark'    => \WC_AICC\Config\Preview_Watermark::is_enabled(),
                'restUrl'     => rest_url( 'wc-aicc/v1' ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'addToCartUrl' => wc_get_cart_url(),
                'i18n'        => array(
                    'selectSize'       => __( 'Select Size', 'wc-aicc' ),
                    'uploadImage'      => __( 'Upload Image', 'wc-aicc' ),
                    'selectStyle'      => __( 'Select Style', 'wc-aicc' ),
                    'generatePreview'  => __( 'Generate Preview', 'wc-aicc' ),
                    'generating'       => __( 'Generating...', 'wc-aicc' ),
                    'addToCart'        => __( 'Add to Cart', 'wc-aicc' ),
                    'processing'       => __( 'Processing your artwork...', 'wc-aicc' ),
                    'generatingPatience' => __( 'This might take a minute or two — please don\'t close this window.', 'wc-aicc' ),
                    'summaryCustomDirection' => __( 'Custom direction', 'wc-aicc' ),
                    'uploading'        => __( 'Uploading...', 'wc-aicc' ),
                    'uploadError'      => __( 'Upload failed. Please try again.', 'wc-aicc' ),
                    'generateError'    => __( 'Generation failed. Please try again.', 'wc-aicc' ),
                    'fileTooBig'       => __( 'File is too large. Maximum size is 10MB.', 'wc-aicc' ),
                    'invalidFileType'  => __( 'Invalid file type. Please upload JPG, PNG, or WebP.', 'wc-aicc' ),
                    'step'             => __( 'Step', 'wc-aicc' ),
                    'back'             => __( 'Back', 'wc-aicc' ),
                    'next'             => __( 'Next', 'wc-aicc' ),
                    'retry'            => __( 'Retry', 'wc-aicc' ),
                    'notes_placeholder' => __( 'Optional: Add notes for the AI (e.g., "make it more vibrant")', 'wc-aicc' ),
                    'select'           => __( 'Select', 'wc-aicc' ),
                    'sizingGuide'      => __( 'Sizing Guide', 'wc-aicc' ),
                    'close'            => __( 'Close', 'wc-aicc' ),
                    'outOfStock'       => __( 'Out of stock', 'wc-aicc' ),
                    'petNameRequired'  => __( 'Please enter your pet\'s name to continue.', 'wc-aicc' ),
                    'petNameLabel'     => __( 'Pet name', 'wc-aicc' ),
                ),
            )
        );
    }
}
