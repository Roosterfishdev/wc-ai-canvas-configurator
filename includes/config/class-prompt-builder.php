<?php
/**
 * Prompt Builder
 *
 * Builds normalized prompts from style + character/situation + background color.
 * Merges layers intelligently (neutral minimizes change; some situations override composition).
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prompt Builder class
 */
class Prompt_Builder {

    /**
     * Base identity lines (reference image — not raw user text)
     *
     * @var array
     */
    const IDENTITY_LINES = array(
        'based on the uploaded reference image',
        'preserve the subject identity, recognizable features, and proportions from the reference',
    );

    /**
     * Default negative constraints
     *
     * @var array
     */
    const CONSTRAINTS = array(
        'no text, no watermark, no logo, no captions',
        'no extra duplicate subjects',
        'no gross anatomy distortion',
    );

    /**
     * Per-style definition: core prompt fragments + optional composition hint + extra negatives
     * (Aligned with client style briefs; subject wording generalized from pet-specific lines.)
     *
     * @var array
     */
    const STYLE_DEFINITIONS = array(
        'impasto' => array(
            'core' => array(
                '(masterpiece:1.3), (impasto oil painting:1.4)',
                'thick textured oil paint, palette knife strokes',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate coat texture into painterly brush texture',
                'close-up head and chest portrait',
                'expressive eyes',
                'heavy layered oil paint, raised ridges, visible brush direction',
                'fine art museum quality',
            ),
            'composition' => 'balanced portrait-friendly composition',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
            ),
        ),
        'pixar' => array(
            'core' => array(
                '(masterpiece:1.2), (pixar style 3D animation:1.4)',
                'high-end animated film character render',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into stylized soft animated fur or surface',
                'cute stylized character design',
                'large expressive eyes, soft rounded facial features',
                'clean cinematic lighting',
                'smooth detailed fur or coat shading',
                '3D animated film quality rendering',
                'close-up head and chest portrait',
                'warm friendly personality expression',
                'ultra polished animation studio quality',
                'Pixar style character design, modern animated film aesthetic',
                'simple clean studio background',
            ),
            'composition' => 'clear readable pose, portrait-friendly framing',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
            ),
        ),
        'watercolor' => array(
            'core' => array(
                '(masterpiece:1.2), (watercolor illustration:1.4)',
                'high-end watercolor painting',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into soft watercolor brush textures',
                'cute stylized watercolor character design',
                'expressive eyes, soft rounded facial features',
                'delicate watercolor shading',
                'fine brush strokes and pigment diffusion',
                'traditional watercolor painting technique',
                'subtle color bleeding and layered washes',
                'close-up head and chest portrait',
                'warm friendly personality expression',
                'ultra refined watercolor illustration quality',
                'storybook watercolor illustration aesthetic',
                'simple clean watercolor paper background',
            ),
            'composition' => 'portrait-oriented composition with breathing room',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
                'no oil painting style',
                'no digital 3D render',
            ),
        ),
        'pop_art' => array(
            'core' => array(
                '(masterpiece:1.2), (pop art illustration:1.4)',
                'high-end pop art portrait',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into bold graphic shapes and flat color areas',
                'iconic pop art character design',
                'large expressive eyes, simplified facial features',
                'bold outlines and strong graphic contrast',
                'vibrant saturated colors',
                'clean vector-like illustration style',
                'Andy Warhol inspired pop art aesthetic',
                'close-up head and chest portrait',
                'playful and energetic personality expression',
                'high contrast color blocking',
                'modern pop art poster style',
                'simple bright graphic background with geometric shapes',
            ),
            'composition' => 'strong graphic composition, centered subject',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
                'no photorealism',
                'no watercolor style',
                'no 3D render',
            ),
        ),
        'van_gogh' => array(
            'core' => array(
                '(masterpiece:1.2), (Van Gogh style oil painting:1.4)',
                'high-end post-impressionist painting',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into expressive painterly brush strokes',
                'Vincent van Gogh inspired artistic interpretation',
                'expressive eyes, soft rounded facial features',
                'thick impasto brush strokes',
                'dynamic swirling paint texture',
                'rich oil paint texture on canvas',
                'vivid expressive color palette',
                'close-up head and chest portrait',
                'warm friendly personality expression',
                'museum-quality oil painting aesthetic',
                'post-impressionist style character portrait',
                'simple painterly background with swirling brush textures',
            ),
            'composition' => 'dynamic portrait composition',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
                'no pop art style',
                'no watercolor style',
                'no 3D render',
            ),
        ),
        'warhol_grid' => array(
            'core' => array(
                '(masterpiece:1.2), (Andy Warhol pop art grid:1.4)',
                'high-end pop art poster design',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into bold graphic shapes and simplified color areas',
                'Andy Warhol inspired pop art portrait',
                '4-panel grid composition',
                'four square panels arranged in a 2x2 grid',
                'each panel showing the same subject portrait',
                'each panel using different vibrant contrasting color palettes',
                'bold black outlines and flat color blocks',
                'high contrast neon pop colors',
                'clean vector-like graphic style',
                'close-up head and chest portrait',
                'playful expressive personality',
                'retro pop art poster aesthetic',
                'simple graphic background inside each panel',
            ),
            'composition' => 'four equal quadrants, consistent subject placement in each',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
                'no photorealism',
                'no watercolor style',
                'no oil painting style',
                'no 3D render',
                'no single-panel layout only',
            ),
        ),
        'american_traditional_tattoo' => array(
            'core' => array(
                '(masterpiece:1.2), american traditional tattoo flash illustration',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'bold traditional tattoo portrait of the subject head',
                'side view or three-quarter head portrait',
                'strong expressive look, optional dramatic attitude',
                'bold american traditional tattoo style',
                'thick black outlines',
                'heavy black linework',
                'flat color fills',
                'high contrast shapes',
                'limited traditional tattoo color palette',
                'black, red, cream, muted green',
                'classic tattoo flash sheet aesthetic',
                'clean vector-like illustration',
                'minimal shading',
                'decorative tattoo elements around the head',
                'spider webs',
                'spark stars',
                'lightning bolts',
                'clean background',
                'tattoo flash design',
            ),
            'composition' => 'strong central subject, tattoo flash clarity',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no low quality render',
                'no blurry details',
            ),
        ),
        'royal_legacy' => array(
            'core' => array(
                'majestic royal portrait legacy style',
                'rich fabrics, ornate details, dignified lighting',
                'old master oil or regal court painting influence',
            ),
            'composition' => 'formal portrait composition, stately presence',
        ),
        'magazine' => array(
            'core' => array(
                'glossy editorial magazine photography look',
                'polished lighting, high-end retouching feel, fashion editorial clarity',
            ),
            'composition' => 'editorial cover-ready framing',
        ),
        'newspaper' => array(
            'core' => array(
                'vintage newspaper print illustration',
                'halftone dot texture, muted ink blacks, newsprint grain',
                'graphic editorial print feel',
            ),
            'composition' => 'front-page illustration style layout',
        ),
        'black_white' => array(
            'core' => array(
                'striking black and white artwork',
                'strong tonal range, careful contrast, no color',
                'timeless monochrome fine art or photo finish',
            ),
            'composition' => 'monochrome portrait composition',
            'negative_extra' => array( 'no color' ),
        ),
    );

    /**
     * Situation / character context
     *
     * @var array
     */
    const SITUATION_DEFINITIONS = array(
        'neutral' => array(
            'minimal_transform' => true,
            'lines'             => array(
                'minimal transformation: keep the original pose, framing, and composition as close as the style allows',
                'treat the style as a surface treatment over the existing layout, not a full scene rewrite',
            ),
        ),
        'royal' => array(
            'lines' => array(
                'regal context: crown or tiara optional, royal robes or ermine-trimmed cloak',
                'palace or velvet drapery hints, dignified ceremonial portrait',
            ),
        ),
        'magazine_cover' => array(
            'lines'                 => array(
                'magazine cover treatment: bold editorial hero portrait',
                'clear negative space or layout rhythm suitable for a masthead',
            ),
            'composition_priority' => 'editorial magazine cover layout with intentional headline space',
        ),
        'whiskey_office' => array(
            'lines' => array(
                'sophisticated office portrait context',
                'leather chair, wood desk, warm lamp light, whiskey glass subtle prop optional',
                'executive character study, cinematic warmth',
            ),
        ),
        'cowboy' => array(
            'lines' => array(
                'western cowboy context: hat, bandana or denim, rustic setting hints',
                'rugged frontier portrait, golden hour or desert tones where appropriate',
            ),
        ),
    );

    /**
     * Background color → prompt fragment (merged with style/situation)
     *
     * @var array
     */
    const BACKGROUND_PHRASES = array(
        'natural'    => '',
        'white'      => 'background: clean white or very light neutral studio backdrop',
        'black'      => 'background: deep black or charcoal seamless backdrop',
        'navy'       => 'background: rich navy and midnight blue tones',
        'cream'      => 'background: warm cream, ivory, and soft beige',
        'sage'       => 'background: muted sage green and soft eucalyptus',
        'gray'       => 'background: soft gray and cool neutral gradient',
        'burgundy'   => 'background: deep burgundy and wine red atmosphere',
        'gold'       => 'background: warm gold and amber glow',
        'teal'       => 'background: teal and deep turquoise wash',
    );

    /**
     * Default selections
     *
     * @var array
     */
    const DEFAULTS = array(
        'style'              => 'impasto',
        'situation'          => 'neutral',
        'background_color'   => 'natural',
        'situation_custom'   => '',
    );

    /**
     * Max length for free-text situation / character notes (after sanitization).
     */
    const SITUATION_CUSTOM_MAX_LEN = 500;

    /**
     * Ordered keys for customize sub-steps (UI + summaries)
     *
     * @var array
     */
    const CUSTOMIZE_OPTION_ORDER = array( 'style', 'situation', 'background_color' );

    /**
     * Customize step order (filterable for extra steps)
     *
     * @return array
     */
    public static function get_customize_option_order() {
        return apply_filters( 'wc_aicc_customize_option_order', self::CUSTOMIZE_OPTION_ORDER );
    }

    /**
     * Get customize flow metadata for JS
     *
     * @return array
     */
    public static function get_customize_flow_meta() {
        $cfg = self::get_options_config();
        $out = array();
        foreach ( self::get_customize_option_order() as $key ) {
            if ( ! isset( $cfg[ $key ] ) ) {
                continue;
            }
            $out[] = array(
                'key'   => $key,
                'title' => $cfg[ $key ]['step_title'] ?? '',
            );
        }
        return $out;
    }

    /**
     * Options for UI (extensible via filter)
     *
     * @return array
     */
    public static function get_options_config() {
        $config = array(
            'style' => array(
                'label'      => __( 'Style', 'wc-aicc' ),
                'step'       => 1,
                'step_title' => __( 'Select style', 'wc-aicc' ),
                'type'       => 'cards',
                'choices'    => array(
                    'impasto' => array(
                        'label' => __( 'Impasto', 'wc-aicc' ),
                        'hint'  => __( 'Thick textured oil paint', 'wc-aicc' ),
                    ),
                    'pixar' => array(
                        'label' => __( 'Pixar', 'wc-aicc' ),
                        'hint'  => __( '3D animated film look', 'wc-aicc' ),
                    ),
                    'watercolor' => array(
                        'label' => __( 'Watercolor', 'wc-aicc' ),
                        'hint'  => __( 'Soft washes on paper', 'wc-aicc' ),
                    ),
                    'pop_art' => array(
                        'label' => __( 'Pop Art', 'wc-aicc' ),
                        'hint'  => __( 'Bold graphic color blocks', 'wc-aicc' ),
                    ),
                    'van_gogh' => array(
                        'label' => __( 'Van Gogh', 'wc-aicc' ),
                        'hint'  => __( 'Swirling expressive strokes', 'wc-aicc' ),
                    ),
                    'warhol_grid' => array(
                        'label' => __( 'Warhol Grid Pop Art', 'wc-aicc' ),
                        'hint'  => __( 'Four-panel Andy Warhol style', 'wc-aicc' ),
                    ),
                    'american_traditional_tattoo' => array(
                        'label' => __( 'American Traditional Tattoo', 'wc-aicc' ),
                        'hint'  => __( 'Bold outlines, classic flash', 'wc-aicc' ),
                    ),
                    'royal_legacy' => array(
                        'label' => __( 'Royal Legacy', 'wc-aicc' ),
                        'hint'  => __( 'Regal old-master portrait', 'wc-aicc' ),
                    ),
                    'magazine' => array(
                        'label' => __( 'Magazine', 'wc-aicc' ),
                        'hint'  => __( 'Glossy editorial photo', 'wc-aicc' ),
                    ),
                    'newspaper' => array(
                        'label' => __( 'Newspaper', 'wc-aicc' ),
                        'hint'  => __( 'Newsprint halftone', 'wc-aicc' ),
                    ),
                    'black_white' => array(
                        'label' => __( 'Black & White', 'wc-aicc' ),
                        'hint'  => __( 'Monochrome artwork', 'wc-aicc' ),
                    ),
                ),
            ),
            'situation' => array(
                'label'      => __( 'Character / situation', 'wc-aicc' ),
                'step'       => 2,
                'step_title' => __( 'Select character / situation', 'wc-aicc' ),
                'type'       => 'cards',
                'choices'    => array(
                    'neutral' => array(
                        'label' => __( 'Neutral', 'wc-aicc' ),
                        'hint'  => __( 'Keeps your original composition', 'wc-aicc' ),
                    ),
                    'royal' => array(
                        'label' => __( 'Royal', 'wc-aicc' ),
                        'hint'  => __( 'Regal portrait treatment', 'wc-aicc' ),
                    ),
                    'magazine_cover' => array(
                        'label' => __( 'Magazine Cover', 'wc-aicc' ),
                        'hint'  => __( 'Cover-style layout & space', 'wc-aicc' ),
                    ),
                    'whiskey_office' => array(
                        'label' => __( 'Whiskey Office', 'wc-aicc' ),
                        'hint'  => __( 'Executive office mood', 'wc-aicc' ),
                    ),
                    'cowboy' => array(
                        'label' => __( 'Cowboy', 'wc-aicc' ),
                        'hint'  => __( 'Western character context', 'wc-aicc' ),
                    ),
                ),
            ),
            'background_color' => array(
                'label'      => __( 'Background color', 'wc-aicc' ),
                'step'       => 3,
                'step_title' => __( 'Select background color', 'wc-aicc' ),
                'type'       => 'cards',
                'choices'    => array(
                    'natural' => array(
                        'label' => __( 'Natural to style', 'wc-aicc' ),
                        'hint'  => __( 'Let the AI choose', 'wc-aicc' ),
                    ),
                    'white' => array(
                        'label' => __( 'White', 'wc-aicc' ),
                        'hint'  => __( 'Clean studio light', 'wc-aicc' ),
                    ),
                    'black' => array(
                        'label' => __( 'Black', 'wc-aicc' ),
                        'hint'  => __( 'Deep dramatic backdrop', 'wc-aicc' ),
                    ),
                    'navy' => array(
                        'label' => __( 'Navy', 'wc-aicc' ),
                        'hint'  => __( 'Midnight blues', 'wc-aicc' ),
                    ),
                    'cream' => array(
                        'label' => __( 'Cream', 'wc-aicc' ),
                        'hint'  => __( 'Warm ivory tones', 'wc-aicc' ),
                    ),
                    'sage' => array(
                        'label' => __( 'Sage', 'wc-aicc' ),
                        'hint'  => __( 'Muted green', 'wc-aicc' ),
                    ),
                    'gray' => array(
                        'label' => __( 'Gray', 'wc-aicc' ),
                        'hint'  => __( 'Cool neutral', 'wc-aicc' ),
                    ),
                    'burgundy' => array(
                        'label' => __( 'Burgundy', 'wc-aicc' ),
                        'hint'  => __( 'Rich wine red', 'wc-aicc' ),
                    ),
                    'gold' => array(
                        'label' => __( 'Gold', 'wc-aicc' ),
                        'hint'  => __( 'Warm amber glow', 'wc-aicc' ),
                    ),
                    'teal' => array(
                        'label' => __( 'Teal', 'wc-aicc' ),
                        'hint'  => __( 'Turquoise depth', 'wc-aicc' ),
                    ),
                ),
            ),
        );

        /**
         * Filter full customization config (add choices, steps, or labels).
         *
         * @param array $config Options config.
         */
        return apply_filters( 'wc_aicc_prompt_builder_options_config', $config );
    }

    /**
     * Valid choice keys for an option (for sanitization)
     *
     * @param string $option_key Option key.
     * @return array
     */
    public static function get_choice_keys( $option_key ) {
        $cfg = self::get_options_config();
        if ( ! isset( $cfg[ $option_key ]['choices'] ) ) {
            return array();
        }
        return array_keys( $cfg[ $option_key ]['choices'] );
    }

    /**
     * Human-readable label for a choice (cart, orders, admin)
     *
     * @param string $option_key Option key.
     * @param string $value      Stored value.
     * @return string
     */
    public static function get_choice_label( $option_key, $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }
        $cfg    = self::get_options_config();
        $choice = $cfg[ $option_key ]['choices'][ $value ] ?? null;
        if ( is_array( $choice ) && isset( $choice['label'] ) ) {
            return $choice['label'];
        }
        if ( is_string( $choice ) ) {
            return $choice;
        }
        return (string) $value;
    }

    /**
     * Ordered summary labels for stored options
     *
     * @param array $options Sanitized options.
     * @return string[]
     */
    public static function summarize_option_labels( array $options ) {
        $parts = array();
        foreach ( self::get_customize_option_order() as $key ) {
            $value = $options[ $key ] ?? '';
            if ( $value === '' || $value === null ) {
                continue;
            }
            if ( 'background_color' === $key && 'natural' === $value ) {
                continue;
            }
            $label = self::get_choice_label( $key, $value );
            if ( $label !== '' ) {
                $parts[] = $label;
            }
        }

        $custom = isset( $options['situation_custom'] ) ? trim( (string) $options['situation_custom'] ) : '';
        if ( $custom !== '' ) {
            $max   = 80;
            $short = function_exists( 'mb_substr' ) ? mb_substr( $custom, 0, $max ) : substr( $custom, 0, $max );
            $len   = function_exists( 'mb_strlen' ) ? mb_strlen( $custom ) : strlen( $custom );
            if ( $len > $max ) {
                $short .= '…';
            }
            $parts[] = __( 'Custom direction', 'wc-aicc' ) . ': ' . $short;
        }

        return $parts;
    }

    /**
     * Build prompt arrays from user customization options
     *
     * @param array $options User selections.
     * @return array { prompt: string, negative_prompt: string }
     */
    public static function build( $options = array() ) {
        $options = self::sanitize_options( is_array( $options ) ? $options : array() );

        $style_key     = $options['style'];
        $situation_key = $options['situation'];
        $bg_key        = $options['background_color'];

        $styles     = apply_filters( 'wc_aicc_prompt_style_definitions', self::STYLE_DEFINITIONS );
        $situations = apply_filters( 'wc_aicc_prompt_situation_definitions', self::SITUATION_DEFINITIONS );
        $bg_map     = apply_filters( 'wc_aicc_prompt_background_phrases', self::BACKGROUND_PHRASES );

        $style_def = $styles[ $style_key ] ?? $styles['impasto'];
        $sit_def   = $situations[ $situation_key ] ?? $situations['neutral'];

        $prompt_parts = array();

        // Reference + identity
        $prompt_parts = array_merge( $prompt_parts, self::IDENTITY_LINES );

        // Style core
        if ( ! empty( $style_def['core'] ) && is_array( $style_def['core'] ) ) {
            $prompt_parts = array_merge( $prompt_parts, $style_def['core'] );
        }

        // Situation context (neutral adds “minimal transformation” lines from definitions)
        if ( ! empty( $sit_def['lines'] ) && is_array( $sit_def['lines'] ) ) {
            $prompt_parts = array_merge( $prompt_parts, $sit_def['lines'] );
        }

        $situation_custom = isset( $options['situation_custom'] ) ? trim( (string) $options['situation_custom'] ) : '';
        if ( $situation_custom !== '' ) {
            $prompt_parts[] = 'additional character / situation direction from customer: ' . $situation_custom;
        }

        // Composition: situation override wins; neutral skips default style framing to avoid fighting the source crop
        if ( ! empty( $sit_def['composition_priority'] ) ) {
            $prompt_parts[] = 'composition priority: ' . $sit_def['composition_priority'];
        } elseif ( empty( $sit_def['minimal_transform'] ) && ! empty( $style_def['composition'] ) ) {
            $prompt_parts[] = $style_def['composition'];
        }

        // Background color (skip natural)
        $bg_phrase = $bg_map[ $bg_key ] ?? '';
        if ( $bg_phrase !== '' ) {
            $prompt_parts[] = $bg_phrase;
        }

        /**
         * Final prompt fragments before join (expert tuning).
         *
         * @param array  $prompt_parts Prompt fragments.
         * @param array  $options      Sanitized options.
         */
        $prompt_parts = apply_filters( 'wc_aicc_prompt_fragments', $prompt_parts, $options );

        $prompt = implode( ', ', array_filter( array_map( 'trim', $prompt_parts ) ) );

        $negative = self::CONSTRAINTS;
        if ( ! empty( $style_def['negative_extra'] ) && is_array( $style_def['negative_extra'] ) ) {
            $negative = array_merge( $negative, $style_def['negative_extra'] );
        }

        /**
         * Negative prompt fragments.
         *
         * @param array $negative Negative lines.
         * @param array $options  Sanitized options.
         */
        $negative = apply_filters( 'wc_aicc_negative_prompt_fragments', $negative, $options );

        $negative_prompt = implode( ', ', array_filter( array_map( 'trim', $negative ) ) );

        return array(
            'prompt'            => $prompt,
            'negative_prompt'   => $negative_prompt,
        );
    }

    /**
     * Validate and sanitize options from request
     *
     * @param array $raw Raw options from user input.
     * @return array Sanitized options.
     */
    public static function sanitize_options( $raw ) {
        $raw     = is_array( $raw ) ? $raw : array();
        $config  = self::get_options_config();
        $result  = array();

        foreach ( array_keys( $config ) as $key ) {
            $value   = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
            $choices = self::get_choice_keys( $key );
            if ( in_array( $value, $choices, true ) ) {
                $result[ $key ] = $value;
            } else {
                $result[ $key ] = self::DEFAULTS[ $key ] ?? '';
            }
        }

        $result['situation_custom'] = self::sanitize_situation_custom( $raw['situation_custom'] ?? '' );

        return wp_parse_args( $result, self::DEFAULTS );
    }

    /**
     * Sanitize free-text character / situation notes from the customer.
     *
     * @param mixed $raw Raw value.
     * @return string
     */
    public static function sanitize_situation_custom( $raw ) {
        $t = sanitize_textarea_field( is_string( $raw ) ? $raw : '' );
        $t = wp_strip_all_tags( $t );
        $t = preg_replace( '/\s+/u', ' ', $t );
        $t = trim( $t );
        $max = (int) self::SITUATION_CUSTOM_MAX_LEN;
        if ( $max < 1 ) {
            return '';
        }
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > $max ) {
            $t = mb_substr( $t, 0, $max );
        } elseif ( strlen( $t ) > $max ) {
            $t = substr( $t, 0, $max );
        }
        return $t;
    }
}
