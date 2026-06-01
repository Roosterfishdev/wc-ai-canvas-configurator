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
        'warhol_grid' => array(
            'core' => array(
                '(masterpiece:1.2), Andy Warhol iconic pop art screen-print portrait',
                'high-end pop art poster design',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate fur or coat into bold graphic shapes and flat color fills',
                'four-panel grid composition in the style of Warhol Marilyn / Queen Elizabeth repeats',
                'four square panels in a two-by-two grid, same portrait repeated',
                'each quadrant with distinct saturated colorways and silkscreen separation',
                'flat ink blocks with slight imperfect registration cues',
                'high contrast palettes, acetone or litho pop texture optional',
                'bold contours, celebrity portrait billboard energy',
                'close-up head and upper chest readable in each panel',
                'playful iconic mass-media attitude',
                'minimal noise inside panels',
            ),
            'composition' => 'four equal quadrants, aligned subject placement in each panel',
            'negative_extra' => array(
                'no text, no watermark, no logo, no magazine masthead',
                'no extra animals',
                'no human figure replacement for the pet subject unless present in reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no photoreal candid photo',
                'no watercolor or oil painterly brush chaos',
                'no single lonely panel unless grid implied',
                'no 3D CGI look',
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
                'bold commercial illustration poster energy',
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
        'magazine_dogue' => array(
            'core' => array(
                '(masterpiece:1.2), glossy high-fashion Magazine Dogue pet editorial portrait',
                'the same subject from the input image',
                'preserve facial structure, breed traits, fur markings and proportions',
                'glossy fashion-magazine cover lighting and polish without literal logo text',
                'high-end editorial retouch vibe, sculpted rim light',
                'subject wears oversized cream vintage sunglasses',
                'pink floral scarf under chin with delicate white daisy pattern',
                'clean bright cyan or powder blue seamless backdrop option',
                'mid-century playful chic personality',
                'flat-painted illustration polish mixed with luminous studio sheen',
                'simplified shapes, matte color blocks with soft gradients',
                'symmetrical balanced framing, portrait orientation',
                'close-up head and upper chest emphasis',
                'contemporary pop illustration couture energy',
            ),
            'composition' => 'editorial cover hero composition, generous negative space for imaginary masthead',
            'negative_extra' => array(
                'no text, no watermark, no logo, no readable masthead',
                'no extra animals',
                'no humans',
                'no hyper-real candid snapshot',
                'no cluttered props',
                'no distorted anatomy',
                'no deformed eyes',
                'no gritty newsprint ONLY treatment',
                'no low quality',
                'no glitch artifacts',
                'no cheesy HDR',
            ),
        ),
        'newspaper' => array(
            'core' => array(
                '(masterpiece:1.2), vintage newspaper halftone printed portrait illustration',
                'the same subject from the input image',
                'preserve likeness, markings, silhouette weight from reference',
                'large halftone dot screens, tactile newsprint fibers',
                'muted ink blacks, warm paper stock cream',
                'graphic editorial caricature-lite clarity without text blocks',
                'front-page centerpiece illustration pacing',
                'close-up readable head bust',
                'subtle imperfect ink trapping',
            ),
            'composition' => 'column illustration layout cues with breathing room resembling broadsheet centerpiece',
            'negative_extra' => array(
                'no headlines, captions, watermark, barcode',
                'no glossy RGB magazine finish',
                'no rainbow pop palette',
                'no low resolution mush',
            ),
        ),
        'royal_legacy' => array(
            'core' => array(
                '(masterpiece:1.2), majestic royal ancestral portrait mural',
                'the same subject from the input image',
                'preserve heraldic likeness, coat markings, stature from reference',
                'dramatic Baroque or Tudor court illumination',
                'sumptuous ermine trims, embroidered silk robes optional',
                'ornate gilt frame vignette without literal heraldic typography',
                'old master glazing, subtle craquelure',
                'warm candlelit key with cool shadow fill balance',
                'close-up ceremonial bust presentation',
                'noble restrained expression grandeur',
                'museum oil or tempera grandeur',
                'rich gemstone color accents subdued',
                'velvet drapery cascade background suggestion',
                'storybook heirloom gravitas',
            ),
            'composition' => 'formal symmetrical crest-like portrait presence',
            'negative_extra' => array(
                'no text, watermark, coat-of-arms lettering',
                'no modern office props',
                'no flat pop vector look',
                'no snapshot flash lighting',
                'no exaggerated grotesque features',
                'no grayscale unless palette chosen later',
                'no cluttered contemporary UI',
                'no muddy texture soup',
                'no distorted anatomy',
            ),
        ),
        'black_white' => array(
            'core' => array(
                '(masterpiece:1.25), luminous black and white fine art monochrome portrait',
                'the same subject from the input image',
                'preserve texture direction in fur or coat as tonal rhythm',
                'extended gray-scale latitude, deep rich blacks, controlled specular highlights',
                'timeless silver-gelatin or platinum print atmosphere',
                'close-up head and chest sculptural clarity',
                'subtle atmospheric vignette optional',
                'museum-grade tonal separation',
            ),
            'composition' => 'monochrome portrait composition with deliberate negative space',
            'negative_extra' => array(
                'no color chroma, no sepia unless requested elsewhere',
                'no low contrast flat gray soup',
                'no heavy digital noise',
                'no text or watermark',
            ),
        ),
        'whiskey_office' => array(
            'core' => array(
                '(masterpiece:1.25), hyper-realistic cinematic portrait',
                'use the uploaded dog as the exact character reference',
                'preserve the dog facial features, fur color, proportions, expression, and identity',
                'the dog portrayed as a powerful mob boss sitting behind a large executive desk in a luxurious private office',
                'dark wood-paneled office',
                'leather executive chair',
                'crystal whiskey glass on the desk',
                'vintage desk lamp',
                'expensive watch',
                'documents and cigar box on the desk',
                'floor-to-ceiling windows with city lights outside',
                'tailored three-piece Italian suit',
                'white dress shirt, silk tie, gold watch, pocket square',
                'warm cinematic lighting, moody shadows, Godfather-inspired atmosphere',
                'soft rim light, high-end editorial photography',
                '85mm lens, shallow depth of field, eye-level perspective',
                'ultra-detailed fur rendering, professional studio quality',
                'The Godfather mood, Martin Scorsese crime film atmosphere',
                'Vanity Fair celebrity portrait polish, luxury lifestyle photography',
                'confident, powerful, respected, intimidating but charismatic',
                'ultra-realistic, photorealistic, 8K detail, natural fur texture',
                'realistic paws, realistic canine anatomy, cinematic color grading',
            ),
            'composition' => 'eye-level executive desk portrait, mob boss seated behind desk, desk props and city window backdrop framing the subject',
            'negative_extra' => array(
                'no text, watermark, stock photo UI',
                'no cartoon or flat illustration look',
                'no human figure replacing the dog subject',
                'no neon cyberpunk palette',
                'no cluttered legible paperwork or readable documents',
                'no extreme fish-eye distortion',
                'no multiple duplicate subjects',
                'no grotesque caricature or drunken comedy',
                'no modern open-plan bright white office',
                'no low quality render',
                'no distorted canine anatomy',
            ),
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
        'style'              => 'warhol_grid',
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
     * Subdirectory under wp-content/uploads for style card thumbnails.
     *
     * Use files named {slug}.webp (or jpg/png), e.g. warhol_grid.webp.
     */
    const STYLE_EXAMPLES_UPLOAD_SUBDIR = 'wc-aicc-style-examples';

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
                    'warhol_grid' => array(
                        'label' => __( 'Warhol Grid', 'wc-aicc' ),
                        'hint'  => __( 'Four-panel Andy Warhol style', 'wc-aicc' ),
                    ),
                    'watercolor' => array(
                        'label' => __( 'Water Color', 'wc-aicc' ),
                        'hint'  => __( 'Soft washes on paper', 'wc-aicc' ),
                    ),
                    'pop_art' => array(
                        'label' => __( 'Pop Art', 'wc-aicc' ),
                        'hint'  => __( 'Bold graphic color blocks', 'wc-aicc' ),
                    ),
                    'pixar' => array(
                        'label' => __( 'Pixar', 'wc-aicc' ),
                        'hint'  => __( '3D animated film look', 'wc-aicc' ),
                    ),
                    'impasto' => array(
                        'label' => __( 'Impasto', 'wc-aicc' ),
                        'hint'  => __( 'Thick textured oil paint', 'wc-aicc' ),
                    ),
                    'american_traditional_tattoo' => array(
                        'label' => __( 'American Tradi', 'wc-aicc' ),
                        'hint'  => __( 'Traditional tattoo flash', 'wc-aicc' ),
                    ),
                    'magazine_dogue' => array(
                        'label' => __( 'Magazine (Dogue)', 'wc-aicc' ),
                        'hint'  => __( 'Fashion editorial chic', 'wc-aicc' ),
                    ),
                    'newspaper' => array(
                        'label' => __( 'Newspaper', 'wc-aicc' ),
                        'hint'  => __( 'Newsprint halftone', 'wc-aicc' ),
                    ),
                    'royal_legacy' => array(
                        'label' => __( 'Royal Legacy', 'wc-aicc' ),
                        'hint'  => __( 'Regal old-master portrait', 'wc-aicc' ),
                    ),
                    'black_white' => array(
                        'label' => __( 'Black and White', 'wc-aicc' ),
                        'hint'  => __( 'Monochrome artwork', 'wc-aicc' ),
                    ),
                    'whiskey_office' => array(
                        'label' => __( 'Whiskey Office', 'wc-aicc' ),
                        'hint'  => __( 'Mob boss executive portrait', 'wc-aicc' ),
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
     * Public URL for a style preview image (step 3.1 cards).
     *
     * Resolution order:
     * 1. wp-content/uploads/wc-aicc-style-examples/{slug}.(webp|jpg|jpeg|png)
     * 2. Plugin bundle: assets/images/style-examples/{slug}.(webp|jpg|jpeg|png)
     *
     * @param string $style_slug Style choice key (e.g. warhol_grid, watercolor).
     * @return string URL or empty if no file exists.
     */
    public static function resolve_style_example_image_url( $style_slug ) {
        $slug = preg_replace( '/[^a-z0-9_-]/i', '', (string) $style_slug );
        if ( $slug === '' || ! defined( 'WC_AICC_PLUGIN_DIR' ) || ! defined( 'WC_AICC_PLUGIN_URL' ) ) {
            return '';
        }

        $extensions = array( 'webp', 'jpg', 'jpeg', 'png' );

        if ( function_exists( 'wp_upload_dir' ) ) {
            $upload = wp_upload_dir();
            if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) && ! empty( $upload['baseurl'] ) ) {
                $upload_style_dir = trailingslashit( $upload['basedir'] ) . self::STYLE_EXAMPLES_UPLOAD_SUBDIR;
                if ( function_exists( 'wp_mkdir_p' ) && ! is_dir( $upload_style_dir ) ) {
                    wp_mkdir_p( $upload_style_dir );
                }
                if ( is_dir( $upload_style_dir ) && ! file_exists( $upload_style_dir . '/index.php' ) ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                    @file_put_contents( $upload_style_dir . '/index.php', "<?php\n// Silence is golden.\n" );
                }
                $upload_style_url = trailingslashit( $upload['baseurl'] ) . self::STYLE_EXAMPLES_UPLOAD_SUBDIR;
                foreach ( $extensions as $ext ) {
                    $file = trailingslashit( $upload_style_dir ) . $slug . '.' . $ext;
                    if ( file_exists( $file ) && is_readable( $file ) ) {
                        $url = trailingslashit( $upload_style_url ) . $slug . '.' . $ext;
                        return (string) apply_filters( 'wc_aicc_style_example_image_url', $url, $slug, $file );
                    }
                }
            }
        }

        $subdir = 'assets/images/style-examples/';
        $dir    = WC_AICC_PLUGIN_DIR . $subdir;
        $base   = WC_AICC_PLUGIN_URL . $subdir;

        foreach ( $extensions as $ext ) {
            $file = $dir . $slug . '.' . $ext;
            if ( file_exists( $file ) && is_readable( $file ) ) {
                $url = $base . $slug . '.' . $ext;
                return (string) apply_filters( 'wc_aicc_style_example_image_url', $url, $slug, $file );
            }
        }

        return (string) apply_filters( 'wc_aicc_style_example_image_url', '', $slug, '' );
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

        $style_def = $styles[ $style_key ] ?? $styles['warhol_grid'];
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
