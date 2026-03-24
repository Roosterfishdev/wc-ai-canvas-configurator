<?php
/**
 * Configurator Template
 *
 * This template renders the AI Canvas Configurator on the product page.
 *
 * @package WC_AICC\Templates
 *
 * Variables available:
 * @var WC_Product $product    The current product.
 * @var array      $variations Array of variation data.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wc-aicc-configurator" class="wc-aicc-configurator">
    
    <!-- Step Indicators -->
    <div class="wc-aicc-step-indicators">
        <div class="wc-aicc-step-indicator wc-aicc-step-indicator--active" data-step="1">
            <span class="step-number">1</span>
            <span class="step-text"><?php esc_html_e( 'Size', 'wc-aicc' ); ?></span>
        </div>
        <div class="wc-aicc-step-indicator" data-step="2">
            <span class="step-number">2</span>
            <span class="step-text"><?php esc_html_e( 'Upload', 'wc-aicc' ); ?></span>
        </div>
        <div class="wc-aicc-step-indicator" data-step="3">
            <span class="step-number">3</span>
            <span class="step-text"><?php esc_html_e( 'Customize', 'wc-aicc' ); ?></span>
        </div>
        <div class="wc-aicc-step-indicator" data-step="4">
            <span class="step-number">4</span>
            <span class="step-text"><?php esc_html_e( 'Preview', 'wc-aicc' ); ?></span>
        </div>
        <div class="wc-aicc-step-indicator" data-step="5">
            <span class="step-number">5</span>
            <span class="step-text"><?php esc_html_e( 'Cart', 'wc-aicc' ); ?></span>
        </div>
    </div>

    <!-- Error Message Container -->
    <div class="wc-aicc-error-message"></div>

    <!-- Steps Container -->
    <div class="wc-aicc-steps">
        
        <!-- Step 1: Size Selection -->
        <div class="wc-aicc-step wc-aicc-step-1" data-step="1" style="display: block;">
            <div class="wc-aicc-step-header">
                <h3><?php esc_html_e( 'Choose Your Canvas Size', 'wc-aicc' ); ?></h3>
                <p><?php esc_html_e( 'Select the size that best fits your space.', 'wc-aicc' ); ?></p>
            </div>

            <div class="wc-aicc-variations">
                <?php foreach ( $variations as $variation ) : ?>
                    <button type="button" 
                            class="wc-aicc-variation-btn <?php echo ! $variation['in_stock'] ? 'wc-aicc-variation-btn--out-of-stock' : ''; ?>"
                            data-variation-id="<?php echo esc_attr( $variation['id'] ); ?>"
                            data-size-label="<?php echo esc_attr( $variation['size_label'] ); ?>"
                            data-aspect-ratio="<?php echo esc_attr( $variation['aspect_ratio'] ); ?>"
                            <?php echo ! $variation['in_stock'] ? 'disabled' : ''; ?>>
                        <span class="size-label"><?php echo esc_html( $variation['size_label'] ); ?></span>
                        <span class="price"><?php echo wp_kses_post( $variation['price_html'] ); ?></span>
                        <span class="aspect-ratio"><?php echo esc_html( $variation['aspect_ratio'] ); ?></span>
                        <?php if ( ! $variation['in_stock'] ) : ?>
                            <span class="out-of-stock"><?php esc_html_e( 'Out of stock', 'wc-aicc' ); ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: Image Upload -->
        <div class="wc-aicc-step wc-aicc-step-2" data-step="2" style="display: none;">
            <div class="wc-aicc-step-header">
                <h3><?php esc_html_e( 'Upload Your Image', 'wc-aicc' ); ?></h3>
                <p><?php esc_html_e( 'Upload a high-quality photo to transform into art.', 'wc-aicc' ); ?></p>
            </div>

            <div class="wc-aicc-upload-preview"></div>
            <p class="wc-aicc-upload-preview-caption" hidden>
                <?php esc_html_e( 'Review your photo above, then click Next when you are ready to customize.', 'wc-aicc' ); ?>
            </p>

            <div class="wc-aicc-drop-zone">
                <div class="wc-aicc-drop-zone-icon">📷</div>
                <h4><?php esc_html_e( 'Drag and drop your image here', 'wc-aicc' ); ?></h4>
                <p><?php esc_html_e( 'or click to browse. JPG, PNG, WebP up to 10MB.', 'wc-aicc' ); ?></p>
                <label class="wc-aicc-browse-btn">
                    <?php esc_html_e( 'Browse Files', 'wc-aicc' ); ?>
                    <input type="file" 
                           id="wc-aicc-file-input" 
                           class="wc-aicc-file-input" 
                           accept="image/jpeg,image/png,image/webp" />
                </label>
            </div>

            <div class="wc-aicc-btn-row">
                <button type="button" class="wc-aicc-back-btn">
                    <?php esc_html_e( '← Back', 'wc-aicc' ); ?>
                </button>
                <button type="button" class="wc-aicc-next-btn" disabled>
                    <?php esc_html_e( 'Next →', 'wc-aicc' ); ?>
                </button>
            </div>
        </div>

        <!-- Step 3: Customize (3 sub-steps: style → situation → background) -->
        <div class="wc-aicc-step wc-aicc-step-3" data-step="3" style="display: none;">
            <div class="wc-aicc-step-header wc-aicc-step-header--customize">
                <h3><?php esc_html_e( 'Customize your artwork', 'wc-aicc' ); ?></h3>
                <p><?php esc_html_e( 'Take it one step at a time: style, then context, then background.', 'wc-aicc' ); ?></p>
            </div>

            <?php
            $options_config = \WC_AICC\Config\Prompt_Builder::get_options_config();
            $defaults       = \WC_AICC\Config\Prompt_Builder::DEFAULTS;
            $option_order = \WC_AICC\Config\Prompt_Builder::get_customize_option_order();
            $panel_keys   = array_values(
                array_filter(
                    $option_order,
                    static function ( $key ) use ( $options_config ) {
                        return isset( $options_config[ $key ] );
                    }
                )
            );
            $total_sub    = count( $panel_keys );
            ?>

            <div class="wc-aicc-customize-substeps">
                <?php
                foreach ( $panel_keys as $idx => $option_key ) :
                    $option    = $options_config[ $option_key ];
                    $sub_index = $idx + 1;
                    $default   = $defaults[ $option_key ] ?? '';
                    $is_last   = ( $idx === $total_sub - 1 );
                    ?>
                    <div class="wc-aicc-customize-panel" data-customize-substep="<?php echo esc_attr( (string) $sub_index ); ?>" <?php echo 1 === $sub_index ? '' : 'style="display: none;"'; ?>>
                        <div class="wc-aicc-customize-panel__intro">
                            <span class="wc-aicc-customize-badge" aria-hidden="true"><?php echo esc_html( sprintf( '3.%d', $sub_index ) ); ?></span>
                            <h4 class="wc-aicc-customize-panel__title"><?php echo esc_html( $option['step_title'] ?? $option['label'] ); ?></h4>
                            <p class="wc-aicc-customize-panel__meta"><?php echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'wc-aicc' ), $sub_index, $total_sub ) ); ?></p>
                        </div>

                        <div class="wc-aicc-choice-cards" role="group" aria-label="<?php echo esc_attr( $option['label'] ); ?>">
                            <?php foreach ( $option['choices'] as $value => $choice_meta ) : ?>
                                <?php
                                $label = is_array( $choice_meta ) && isset( $choice_meta['label'] ) ? $choice_meta['label'] : (string) $choice_meta;
                                $hint  = is_array( $choice_meta ) && ! empty( $choice_meta['hint'] ) ? $choice_meta['hint'] : '';
                                $sel   = (string) $value === (string) $default ? ' wc-aicc-choice-card--selected' : '';
                                ?>
                                <button type="button"
                                        class="wc-aicc-choice-card<?php echo esc_attr( $sel ); ?>"
                                        data-option-key="<?php echo esc_attr( $option_key ); ?>"
                                        data-value="<?php echo esc_attr( $value ); ?>">
                                    <span class="wc-aicc-choice-card__label"><?php echo esc_html( $label ); ?></span>
                                    <?php if ( $hint ) : ?>
                                        <span class="wc-aicc-choice-card__hint"><?php echo esc_html( $hint ); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php if ( 'situation' === $option_key ) : ?>
                            <div class="wc-aicc-situation-custom-wrap">
                                <label for="wc-aicc-situation-custom" class="wc-aicc-situation-custom-label">
                                    <?php esc_html_e( 'Describe what you want (optional)', 'wc-aicc' ); ?>
                                </label>
                                <textarea
                                    id="wc-aicc-situation-custom"
                                    class="wc-aicc-situation-custom-input"
                                    name="wc_aicc_situation_custom"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="<?php esc_attr_e( 'E.g. sitting on a throne, wearing a crown, playful expression…', 'wc-aicc' ); ?>"
                                ></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="wc-aicc-btn-row wc-aicc-customize-actions">
                            <button type="button" class="wc-aicc-customize-back-btn">
                                <?php esc_html_e( '← Back', 'wc-aicc' ); ?>
                            </button>
                            <?php if ( $is_last ) : ?>
                                <button type="button" class="wc-aicc-generate-btn">
                                    <?php esc_html_e( 'Generate Preview', 'wc-aicc' ); ?>
                                </button>
                            <?php else : ?>
                                <button type="button" class="wc-aicc-customize-next-btn">
                                    <?php esc_html_e( 'Continue', 'wc-aicc' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wc-aicc-generate-status"></div>
        </div>

        <!-- Step 4: Preview -->
        <div class="wc-aicc-step wc-aicc-step-4" data-step="4" style="display: none;">
            <div class="wc-aicc-step-header">
                <h3><?php esc_html_e( 'Your Custom Artwork', 'wc-aicc' ); ?></h3>
                <p><?php esc_html_e( 'Here is your AI-generated masterpiece.', 'wc-aicc' ); ?></p>
            </div>

            <div class="wc-aicc-preview-grid">
                <div class="wc-aicc-preview-item">
                    <h4><?php esc_html_e( 'Final Artwork', 'wc-aicc' ); ?></h4>
                    <img id="wc-aicc-final-art-preview" src="" alt="<?php esc_attr_e( 'Final artwork', 'wc-aicc' ); ?>" />
                </div>
                <div class="wc-aicc-preview-item">
                    <h4><?php esc_html_e( 'Room Mockup', 'wc-aicc' ); ?></h4>
                    <img id="wc-aicc-mockup-preview" src="" alt="<?php esc_attr_e( 'Room mockup', 'wc-aicc' ); ?>" />
                </div>
            </div>

            <div class="wc-aicc-btn-row">
                <button type="button" class="wc-aicc-back-btn">
                    <?php esc_html_e( '← Change Style', 'wc-aicc' ); ?>
                </button>
                <button type="button" class="wc-aicc-next-btn">
                    <?php esc_html_e( 'Continue →', 'wc-aicc' ); ?>
                </button>
            </div>
        </div>

        <!-- Step 5: Add to Cart -->
        <div class="wc-aicc-step wc-aicc-step-5" data-step="5" style="display: none;">
            <div class="wc-aicc-step-header">
                <h3><?php esc_html_e( 'Ready to Order', 'wc-aicc' ); ?></h3>
                <p><?php esc_html_e( 'Review your custom canvas and add to cart.', 'wc-aicc' ); ?></p>
            </div>

            <div class="wc-aicc-summary">
                <div class="wc-aicc-summary-row">
                    <span class="label"><?php esc_html_e( 'Product', 'wc-aicc' ); ?></span>
                    <span class="value" id="wc-aicc-summary-product"><?php echo esc_html( $product->get_name() ); ?></span>
                </div>
                <div class="wc-aicc-summary-row">
                    <span class="label"><?php esc_html_e( 'Size', 'wc-aicc' ); ?></span>
                    <span class="value" id="wc-aicc-summary-size">-</span>
                </div>
                <div class="wc-aicc-summary-row">
                    <span class="label"><?php esc_html_e( 'Options', 'wc-aicc' ); ?></span>
                    <span class="value" id="wc-aicc-summary-options">-</span>
                </div>
                <div class="wc-aicc-summary-row">
                    <span class="label"><?php esc_html_e( 'Price', 'wc-aicc' ); ?></span>
                    <span class="value" id="wc-aicc-summary-price">-</span>
                </div>
            </div>

            <div class="wc-aicc-preview-item" style="text-align: center; margin-bottom: 24px;">
                <img id="wc-aicc-cart-preview" src="" alt="<?php esc_attr_e( 'Your artwork', 'wc-aicc' ); ?>" style="max-width: 300px;" />
            </div>

            <div class="wc-aicc-btn-row">
                <button type="button" class="wc-aicc-back-btn">
                    <?php esc_html_e( '← Back', 'wc-aicc' ); ?>
                </button>
                <button type="button" class="wc-aicc-add-to-cart-btn">
                    <?php esc_html_e( 'Add to Cart', 'wc-aicc' ); ?>
                </button>
            </div>
        </div>

    </div>
</div>
