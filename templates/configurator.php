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
            <div class="wc-aicc-step-header wc-aicc-step-header--size">
                <h3><?php esc_html_e( 'Choose Your Canvas Size', 'wc-aicc' ); ?></h3>
            </div>

            <div class="wc-aicc-size-grid">
                <?php foreach ( $variations as $variation ) : ?>
                    <?php
                    $inches = ! empty( $variation['size_inches'] ) ? $variation['size_inches'] : $variation['size_label'];
                    $cm     = ! empty( $variation['size_cm'] ) ? $variation['size_cm'] : '';
                    ?>
                    <div class="wc-aicc-size-card <?php echo ! $variation['in_stock'] ? 'wc-aicc-size-card--out-of-stock' : ''; ?>">
                        <div class="wc-aicc-size-card__frame"
                             role="button"
                             tabindex="0"
                             data-variation-id="<?php echo esc_attr( $variation['id'] ); ?>"
                             aria-label="<?php echo esc_attr( sprintf( __( 'Size %s', 'wc-aicc' ), $inches ) ); ?>">
                            <span class="wc-aicc-size-card__arrow wc-aicc-size-card__arrow--tl" aria-hidden="true"></span>
                            <span class="wc-aicc-size-card__arrow wc-aicc-size-card__arrow--br" aria-hidden="true"></span>
                            <span class="wc-aicc-size-card__inches"><?php echo esc_html( $inches ); ?></span>
                            <?php if ( $cm !== '' ) : ?>
                                <span class="wc-aicc-size-card__cm"><?php echo esc_html( $cm ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( ! empty( $variation['price_html'] ) ) : ?>
                            <p class="wc-aicc-size-card__price"><?php echo wp_kses_post( $variation['price_html'] ); ?></p>
                        <?php endif; ?>
                        <?php if ( ! $variation['in_stock'] ) : ?>
                            <p class="wc-aicc-size-card__stock"><?php esc_html_e( 'Out of stock', 'wc-aicc' ); ?></p>
                        <?php else : ?>
                            <button type="button"
                                    class="wc-aicc-size-select-btn"
                                    data-variation-id="<?php echo esc_attr( $variation['id'] ); ?>">
                                <?php esc_html_e( 'Select', 'wc-aicc' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="wc-aicc-size-guide-link-wrap">
                <button type="button" class="wc-aicc-sizing-guide-open" aria-haspopup="dialog">
                    <?php esc_html_e( 'Sizing Guide', 'wc-aicc' ); ?>
                </button>
            </p>
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
                    <div class="wc-aicc-customize-panel" data-customize-key="<?php echo esc_attr( $option_key ); ?>" data-customize-substep="<?php echo esc_attr( (string) $sub_index ); ?>" <?php echo 1 === $sub_index ? '' : 'style="display: none;"'; ?>>
                        <div class="wc-aicc-customize-panel__intro<?php echo 'situation' === $option_key ? ' wc-aicc-customize-panel__intro--character' : ''; ?>">
                            <?php if ( 'situation' === $option_key ) : ?>
                                <h4 class="wc-aicc-customize-panel__title"><?php echo esc_html( $option['step_title'] ?? $option['label'] ); ?></h4>
                            <?php elseif ( 'style' === $option_key || 'background_color' === $option_key ) : ?>
                                <h4 class="wc-aicc-customize-panel__title"><?php echo esc_html( $option['step_title'] ?? $option['label'] ); ?></h4>
                                <p class="wc-aicc-customize-panel__meta wc-aicc-customize-panel__meta--dynamic"><?php echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'wc-aicc' ), $sub_index, $total_sub ) ); ?></p>
                            <?php else : ?>
                                <span class="wc-aicc-customize-badge" aria-hidden="true"><?php echo esc_html( sprintf( '3.%d', $sub_index ) ); ?></span>
                                <h4 class="wc-aicc-customize-panel__title"><?php echo esc_html( $option['step_title'] ?? $option['label'] ); ?></h4>
                                <p class="wc-aicc-customize-panel__meta"><?php echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'wc-aicc' ), $sub_index, $total_sub ) ); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php
                        $cards_class = 'wc-aicc-choice-cards';
                        if ( 'style' === $option_key ) {
                            $cards_class .= ' wc-aicc-choice-cards--style';
                        } elseif ( 'background_color' === $option_key ) {
                            $cards_class .= ' wc-aicc-choice-cards--background';
                        } elseif ( 'situation' === $option_key ) {
                            $cards_class .= ' wc-aicc-choice-cards--character';
                        }
                        ?>
                        <div class="<?php echo esc_attr( $cards_class ); ?>" role="group" aria-label="<?php echo esc_attr( $option['label'] ); ?>">
                            <?php foreach ( $option['choices'] as $value => $choice_meta ) : ?>
                                <?php
                                $label = is_array( $choice_meta ) && isset( $choice_meta['label'] ) ? $choice_meta['label'] : (string) $choice_meta;
                                $hint  = is_array( $choice_meta ) && ! empty( $choice_meta['hint'] ) ? $choice_meta['hint'] : '';
                                $icon  = is_array( $choice_meta ) && ! empty( $choice_meta['icon'] ) ? (string) $choice_meta['icon'] : '';
                                $swatch = is_array( $choice_meta ) && ! empty( $choice_meta['swatch'] ) ? (string) $choice_meta['swatch'] : '';
                                $sel   = (string) $value === (string) $default ? ' wc-aicc-choice-card--selected' : '';
                                $thumb = '';
                                if ( 'style' === $option_key ) {
                                    if ( is_array( $choice_meta ) && ! empty( $choice_meta['example_image'] ) ) {
                                        $thumb = (string) $choice_meta['example_image'];
                                    } else {
                                        $thumb = \WC_AICC\Config\Prompt_Builder::resolve_style_example_image_url( (string) $value );
                                    }
                                }
                                $card_mod = '';
                                if ( $thumb ) {
                                    $card_mod .= ' wc-aicc-choice-card--with-thumb';
                                }
                                if ( $icon ) {
                                    $card_mod .= ' wc-aicc-choice-card--with-icon';
                                }
                                if ( $swatch ) {
                                    $card_mod .= ' wc-aicc-choice-card--with-swatch';
                                }
                                ?>
                                <button type="button"
                                        class="wc-aicc-choice-card<?php echo esc_attr( $card_mod . $sel ); ?>"
                                        data-option-key="<?php echo esc_attr( $option_key ); ?>"
                                        data-value="<?php echo esc_attr( $value ); ?>">
                                    <?php if ( $swatch ) : ?>
                                        <span class="wc-aicc-choice-card__swatch<?php echo 'auto' === $swatch ? ' wc-aicc-choice-card__swatch--auto' : ''; ?>"<?php echo 'auto' !== $swatch ? ' style="--wc-aicc-swatch: ' . esc_attr( $swatch ) . ';"' : ''; ?> aria-hidden="true"></span>
                                    <?php endif; ?>
                                    <?php if ( $thumb ) : ?>
                                        <span class="wc-aicc-choice-card__thumb">
                                            <img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" decoding="async" width="200" height="240" />
                                        </span>
                                    <?php endif; ?>
                                    <?php if ( $icon ) : ?>
                                        <span class="wc-aicc-choice-card__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
                                    <?php endif; ?>
                                    <span class="wc-aicc-choice-card__label"><?php echo esc_html( $label ); ?></span>
                                    <?php if ( $hint ) : ?>
                                        <span class="wc-aicc-choice-card__hint"><?php echo esc_html( $hint ); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="wc-aicc-btn-row wc-aicc-customize-actions">
                            <button type="button" class="wc-aicc-customize-back-btn">
                                <?php esc_html_e( '← Back', 'wc-aicc' ); ?>
                            </button>
                            <button type="button" class="wc-aicc-customize-next-btn"<?php echo $is_last ? ' style="display: none;"' : ''; ?>>
                                <?php esc_html_e( 'Continue', 'wc-aicc' ); ?>
                            </button>
                            <button type="button" class="wc-aicc-generate-btn"<?php echo $is_last ? '' : ' style="display: none;"'; ?>>
                                <?php esc_html_e( 'Generate Preview', 'wc-aicc' ); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="wc-aicc-customize-panel" data-customize-key="pet_name" style="display: none;">
                    <div class="wc-aicc-customize-panel__intro">
                        <span class="wc-aicc-customize-badge wc-aicc-customize-badge--dynamic" aria-hidden="true">3.2</span>
                        <h4 class="wc-aicc-customize-panel__title"><?php esc_html_e( 'Pet name', 'wc-aicc' ); ?></h4>
                        <p class="wc-aicc-customize-panel__meta wc-aicc-customize-panel__meta--dynamic"><?php esc_html_e( 'Step 2 of 2', 'wc-aicc' ); ?></p>
                        <p class="wc-aicc-customize-panel__hint"><?php esc_html_e( 'This name appears centered above your pet on the charcoal poster.', 'wc-aicc' ); ?></p>
                    </div>

                    <div class="wc-aicc-pet-name-wrap">
                        <label for="wc-aicc-pet-name" class="wc-aicc-pet-name-label">
                            <?php esc_html_e( 'Pet name', 'wc-aicc' ); ?>
                        </label>
                        <input
                            type="text"
                            id="wc-aicc-pet-name"
                            class="wc-aicc-pet-name-input"
                            name="wc_aicc_pet_name"
                            maxlength="40"
                            autocomplete="off"
                            placeholder="<?php esc_attr_e( 'e.g. LUNA', 'wc-aicc' ); ?>"
                        />
                    </div>

                    <div class="wc-aicc-btn-row wc-aicc-customize-actions">
                        <button type="button" class="wc-aicc-customize-back-btn">
                            <?php esc_html_e( '← Back', 'wc-aicc' ); ?>
                        </button>
                        <button type="button" class="wc-aicc-generate-btn">
                            <?php esc_html_e( 'Generate Preview', 'wc-aicc' ); ?>
                        </button>
                    </div>
                </div>

                <div class="wc-aicc-customize-panel" data-customize-key="memorial_text" style="display: none;">
                    <div class="wc-aicc-customize-panel__intro">
                        <h4 class="wc-aicc-customize-panel__title"><?php esc_html_e( 'Memorial details', 'wc-aicc' ); ?></h4>
                        <p class="wc-aicc-customize-panel__meta wc-aicc-customize-panel__meta--dynamic"><?php esc_html_e( 'Step 2 of 3', 'wc-aicc' ); ?></p>
                        <p class="wc-aicc-customize-panel__hint"><?php esc_html_e( 'All fields are optional. Leave blank to omit text from the artwork.', 'wc-aicc' ); ?></p>
                    </div>

                    <div class="wc-aicc-memorial-fields">
                        <div class="wc-aicc-memorial-field">
                            <label for="wc-aicc-memorial-name" class="wc-aicc-memorial-field__label">
                                <?php esc_html_e( 'Name', 'wc-aicc' ); ?>
                            </label>
                            <input
                                type="text"
                                id="wc-aicc-memorial-name"
                                class="wc-aicc-memorial-field__input"
                                name="wc_aicc_memorial_name"
                                maxlength="40"
                                autocomplete="off"
                                placeholder="<?php esc_attr_e( 'e.g. Ruffus', 'wc-aicc' ); ?>"
                            />
                        </div>
                        <div class="wc-aicc-memorial-field">
                            <label for="wc-aicc-memorial-dates" class="wc-aicc-memorial-field__label">
                                <?php esc_html_e( 'Dates', 'wc-aicc' ); ?>
                            </label>
                            <input
                                type="text"
                                id="wc-aicc-memorial-dates"
                                class="wc-aicc-memorial-field__input"
                                name="wc_aicc_memorial_dates"
                                maxlength="32"
                                autocomplete="off"
                                placeholder="<?php esc_attr_e( 'e.g. 2018-2026', 'wc-aicc' ); ?>"
                            />
                        </div>
                        <div class="wc-aicc-memorial-field">
                            <label for="wc-aicc-memorial-message" class="wc-aicc-memorial-field__label">
                                <?php esc_html_e( 'Message', 'wc-aicc' ); ?>
                            </label>
                            <input
                                type="text"
                                id="wc-aicc-memorial-message"
                                class="wc-aicc-memorial-field__input"
                                name="wc_aicc_memorial_message"
                                maxlength="80"
                                autocomplete="off"
                                placeholder="<?php esc_attr_e( 'e.g. Forever in our Hearts', 'wc-aicc' ); ?>"
                            />
                        </div>
                    </div>

                    <div class="wc-aicc-btn-row wc-aicc-customize-actions">
                        <button type="button" class="wc-aicc-customize-back-btn">
                            <?php esc_html_e( '← Back', 'wc-aicc' ); ?>
                        </button>
                        <button type="button" class="wc-aicc-customize-next-btn">
                            <?php esc_html_e( 'Continue', 'wc-aicc' ); ?>
                        </button>
                        <button type="button" class="wc-aicc-generate-btn" style="display: none;">
                            <?php esc_html_e( 'Generate Preview', 'wc-aicc' ); ?>
                        </button>
                    </div>
                </div>
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
                    <?php echo \WC_AICC\Config\Preview_Watermark::open_wrapper(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <img id="wc-aicc-final-art-preview" class="wc-aicc-art-preview__image" src="" alt="<?php esc_attr_e( 'Final artwork', 'wc-aicc' ); ?>" draggable="false" style="width:100%;max-width:none;height:auto;display:block;" />
                    <?php echo \WC_AICC\Config\Preview_Watermark::render_overlay_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo \WC_AICC\Config\Preview_Watermark::close_wrapper(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="wc-aicc-preview-item">
                    <h4><?php esc_html_e( 'Room Mockup', 'wc-aicc' ); ?></h4>
                    <?php echo \WC_AICC\Config\Preview_Watermark::open_wrapper(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <img id="wc-aicc-mockup-preview" class="wc-aicc-art-preview__image" src="" alt="<?php esc_attr_e( 'Room mockup', 'wc-aicc' ); ?>" draggable="false" style="width:100%;max-width:none;height:auto;display:block;" />
                    <?php echo \WC_AICC\Config\Preview_Watermark::render_overlay_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo \WC_AICC\Config\Preview_Watermark::close_wrapper(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

            <div class="wc-aicc-preview-item wc-aicc-preview-item--cart" style="text-align: center; margin-bottom: 24px;">
                <?php echo \WC_AICC\Config\Preview_Watermark::open_wrapper(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <img id="wc-aicc-cart-preview" class="wc-aicc-art-preview__image" src="" alt="<?php esc_attr_e( 'Your artwork', 'wc-aicc' ); ?>" style="max-width: 300px;" draggable="false" />
                <?php echo \WC_AICC\Config\Preview_Watermark::render_overlay_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo \WC_AICC\Config\Preview_Watermark::close_wrapper(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

    <!-- Sizing guide slide-in (content filled by JS) -->
    <div class="wc-aicc-sizing-guide" id="wc-aicc-sizing-guide" hidden aria-hidden="true">
        <button type="button" class="wc-aicc-sizing-guide__backdrop" aria-label="<?php esc_attr_e( 'Close sizing guide', 'wc-aicc' ); ?>"></button>
        <aside class="wc-aicc-sizing-guide__panel" role="dialog" aria-modal="true" aria-labelledby="wc-aicc-sizing-guide-title">
            <header class="wc-aicc-sizing-guide__header">
                <h4 id="wc-aicc-sizing-guide-title"><?php esc_html_e( 'Sizing Guide', 'wc-aicc' ); ?></h4>
                <button type="button" class="wc-aicc-sizing-guide__close" aria-label="<?php esc_attr_e( 'Close', 'wc-aicc' ); ?>">&times;</button>
            </header>
            <div class="wc-aicc-sizing-guide__body"></div>
        </aside>
    </div>
</div>
