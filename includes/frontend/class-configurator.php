<?php
/**
 * Frontend Configurator
 *
 * Handles frontend configurator rendering and shortcode.
 * Provides [wc_aicc_configurator] shortcode for manual placement.
 *
 * @package WC_AICC\Frontend
 */

namespace WC_AICC\Frontend;

use WC_AICC\Config\Size_Aspect_Map;
use WC_AICC\Config\Size_Display;
use WC_AICC\Config\Sizing_Guide;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Configurator class
 */
class Configurator {

    /**
     * Singleton instance
     *
     * @var Configurator|null
     */
    private static $instance = null;

    /**
     * Track if configurator has been rendered on this page
     *
     * @var array Product IDs that have been rendered
     */
    private $rendered_products = array();

    /**
     * Get singleton instance
     *
     * @return Configurator
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
        // Register shortcode
        add_shortcode( 'wc_aicc_configurator', array( $this, 'shortcode' ) );
    }

    /**
     * Configurator shortcode
     *
     * Usage:
     * [wc_aicc_configurator] - Uses current product (on product page)
     * [wc_aicc_configurator product_id="123"] - Specific product
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'product_id' => 0,
            ),
            $atts,
            'wc_aicc_configurator'
        );

        $product_id = absint( $atts['product_id'] );

        // Try to get product ID from global if not specified
        if ( ! $product_id ) {
            global $product;
            if ( $product instanceof \WC_Product ) {
                $product_id = $product->get_id();
            } elseif ( is_singular( 'product' ) ) {
                $product_id = get_the_ID();
            }
        }

        if ( ! $product_id ) {
            return '<p class="wc-aicc-error">' . esc_html__( 'No product specified. Use [wc_aicc_configurator product_id="123"] or place on a product page.', 'wc-aicc' ) . '</p>';
        }

        // Prevent double rendering
        if ( in_array( $product_id, $this->rendered_products, true ) ) {
            return '<!-- WC AICC: Configurator already rendered for product ' . $product_id . ' -->';
        }

        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return '<p class="wc-aicc-error">' . esc_html__( 'Product not found.', 'wc-aicc' ) . '</p>';
        }

        if ( ! $product->is_type( 'variable' ) ) {
            return '<p class="wc-aicc-error">' . esc_html__( 'This product is not a variable product. The configurator requires size variations.', 'wc-aicc' ) . '</p>';
        }

        if ( get_post_meta( $product_id, '_wc_aicc_enabled', true ) !== 'yes' ) {
            return '<p class="wc-aicc-error">' . esc_html__( 'Configurator is not enabled for this product. Enable it in the product settings.', 'wc-aicc' ) . '</p>';
        }

        // Mark as rendered
        $this->rendered_products[] = $product_id;

        // Get variations
        $variations = $this->get_product_variations( $product );

        // Get customization options config
        $options = \WC_AICC\Config\Prompt_Builder::get_options_config();

        // Enqueue assets
        $this->enqueue_assets( $product, $variations, $options );

        // Capture template output
        ob_start();
        include WC_AICC_PLUGIN_DIR . 'templates/configurator.php';
        return ob_get_clean();
    }

    /**
     * Render configurator directly (for programmatic use)
     *
     * @param int $product_id Product ID.
     * @return string HTML output.
     */
    public function render( $product_id ) {
        return $this->shortcode( array( 'product_id' => $product_id ) );
    }

    /**
     * Get product variations
     *
     * @param \WC_Product_Variable $product Product object.
     * @return array Variations data.
     */
    private function get_product_variations( $product ) {
        $variations    = array();
        $variation_ids = $product->get_children();

        foreach ( $variation_ids as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            
            if ( ! $variation || ! $variation->is_purchasable() ) {
                continue;
            }

            $attributes = $variation->get_variation_attributes();
            $size_label = '';
            
            foreach ( $attributes as $attr_name => $attr_value ) {
                if ( stripos( $attr_name, 'size' ) !== false || stripos( $attr_name, 'pa_size' ) !== false ) {
                    $size_label = $attr_value;
                    break;
                }
            }

            if ( empty( $size_label ) && ! empty( $attributes ) ) {
                $size_label = reset( $attributes );
            }

            $aspect_ratio = get_post_meta( $variation_id, '_wc_aicc_aspect_ratio', true );

            if ( empty( $aspect_ratio ) ) {
                $aspect_ratio = Size_Aspect_Map::resolve( $size_label );
            }

            if ( empty( $aspect_ratio ) ) {
                $aspect_ratio = $this->calculate_aspect_ratio( $size_label );
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
     * @param string $size_label Size label.
     * @return string Aspect ratio.
     */
    private function calculate_aspect_ratio( $size_label ) {
        if ( preg_match( '/(\d+)\s*[x×]\s*(\d+)/i', $size_label, $matches ) ) {
            $width  = (int) $matches[1];
            $height = (int) $matches[2];

            if ( $width > 0 && $height > 0 ) {
                $gcd = $this->gcd( $width, $height );
                return ( $width / $gcd ) . ':' . ( $height / $gcd );
            }
        }

        return '1:1';
    }

    /**
     * Calculate GCD
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
     * Enqueue assets
     *
     * @param \WC_Product $product    Product object.
     * @param array       $variations Variations data.
     * @param array       $options    Customization options config.
     */
    private function enqueue_assets( $product, $variations, $options ) {
        // Only enqueue once per page load
        static $enqueued = false;

        wp_enqueue_style(
            'wc-aicc-configurator',
            WC_AICC_PLUGIN_URL . 'assets/css/configurator.css',
            array(),
            WC_AICC_VERSION
        );

        wp_enqueue_script(
            'wc-aicc-configurator',
            WC_AICC_PLUGIN_URL . 'assets/js/configurator.js',
            array(),
            WC_AICC_VERSION,
            true
        );

        // Only localize once (or update with new product data)
        wp_localize_script(
            'wc-aicc-configurator',
            'wcAiccConfig',
            array(
                'productId'    => $product->get_id(),
                'variations'   => $variations,
                'options'      => $options,
                'optionDefaults' => \WC_AICC\Config\Prompt_Builder::DEFAULTS,
                'customizeFlow' => \WC_AICC\Config\Prompt_Builder::get_customize_flow_meta(),
                'sizingGuide'   => Sizing_Guide::get_panel_data(),
                'restUrl'      => rest_url( 'wc-aicc/v1' ),
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'addToCartUrl' => wc_get_cart_url(),
                'i18n'         => array(
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
                ),
            )
        );
    }

    /**
     * Reset rendered products (for testing)
     */
    public function reset_rendered() {
        $this->rendered_products = array();
    }
}
