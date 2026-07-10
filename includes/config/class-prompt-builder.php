<?php
/**
 * Prompt Builder
 *
 * Builds normalized prompts from style preset + background color selections.
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
     * Style preset definitions (portrait theme cards).
     *
     * @return array
     */
    public static function get_style_definitions() {
        $file = __DIR__ . '/prompt-style-presets.php';
        $defs = is_readable( $file ) ? include $file : array();
        if ( ! is_array( $defs ) ) {
            $defs = array();
        }

        /**
         * @param array $defs Style slug => definition.
         */
        return apply_filters( 'wc_aicc_prompt_style_definitions', $defs );
    }

    /**
     * Background color → prompt fragment (merged with style preset)
     *
     * @var array
     */
    const BACKGROUND_PHRASES = array(
        'auto'       => '',
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
        'style'            => 'original',
        'background_color' => 'auto',
    );

    /**
     * Ordered keys for customize sub-steps (UI + summaries)
     *
     * @var array
     */
    const CUSTOMIZE_OPTION_ORDER = array( 'style', 'background_color' );

    /**
     * Subdirectory under wp-content/uploads for style card thumbnails.
     *
     * Use files named {slug}.webp (or jpg/png), e.g. warhol_grid.webp.
     */
    const STYLE_EXAMPLES_UPLOAD_SUBDIR = 'wc-aicc-style-examples';

    /**
     * Alternate filenames for style preview images (legacy or human-readable names).
     *
     * @var array<string, string[]>
     */
    private const STYLE_EXAMPLE_ALIASES = array(
        'magazine_cover' => array( 'magazine-cover', 'magazine_dogue', 'dogue', 'Dogue Cover' ),
        'royal'          => array( 'royal_legacy', 'Royal Legacy' ),
        'gentleman'      => array( 'whiskey_office', 'Whiskey Office' ),
        'original'       => array( 'warhol_grid', 'black_studio', 'black_white', 'Black Studio' ),
    );

    /**
     * Map legacy stored style keys to current preset slugs.
     *
     * @var array<string, string>
     */
    private const LEGACY_STYLE_MAP = array(
        'warhol_grid'                  => 'original',
        'watercolor'                   => 'original',
        'pop_art'                      => 'original',
        'pixar'                        => 'original',
        'impasto'                      => 'original',
        'american_traditional_tattoo'  => 'original',
        'black_studio'                 => 'original',
        'black_white'                  => 'original',
        'magazine_dogue'               => 'magazine_cover',
        'royal_legacy'                 => 'royal',
        'whiskey_office'               => 'gentleman',
    );

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
                'title' => $cfg[ $key ]['section_label'] ?? ( $cfg[ $key ]['label'] ?? '' ),
            );
        }
        return $out;
    }

    /**
     * Per-style customize sub-step flows (override default style → situation → background).
     *
     * @return array<string, array<int, array{key: string, title: string}>>
     */
    public static function get_style_customize_flows() {
        /**
         * @param array<string, array> $flows Style slug => ordered step definitions.
         */
        return apply_filters( 'wc_aicc_style_customize_flows', array() );
    }

    /**
     * Customize flow for a given style selection.
     *
     * @param string $style_key Style slug.
     * @return array<int, array{key: string, title: string}>
     */
    public static function get_customize_flow_for_style( $style_key ) {
        $style_key = sanitize_key( (string) $style_key );
        $flows     = self::get_style_customize_flows();
        if ( isset( $flows[ $style_key ] ) && is_array( $flows[ $style_key ] ) ) {
            return $flows[ $style_key ];
        }
        return self::get_customize_flow_meta();
    }

    /**
     * Options for UI (extensible via filter)
     *
     * @return array
     */
    public static function get_options_config() {
        $config = array(
            'style' => array(
                'label'          => __( 'Style', 'wc-aicc' ),
                'section_label'  => __( 'Choose a style', 'wc-aicc' ),
                'type'           => 'cards',
                'choices'        => array(
                    'original' => array(
                        'label' => __( 'Original', 'wc-aicc' ),
                        'hint'  => __( 'Stay true to your pet and reference photo.', 'wc-aicc' ),
                    ),
                    'royal' => array(
                        'label' => __( 'Royal', 'wc-aicc' ),
                        'hint'  => __( 'Regal king or queen portrait with elegant attire.', 'wc-aicc' ),
                    ),
                    'magazine_cover' => array(
                        'label' => __( 'Magazine Cover', 'wc-aicc' ),
                        'hint'  => __( 'Editorial-style cover with premium typography.', 'wc-aicc' ),
                    ),
                    'cowboy' => array(
                        'label' => __( 'Cowboy', 'wc-aicc' ),
                        'hint'  => __( 'Western-inspired outfit and setting.', 'wc-aicc' ),
                    ),
                    'firefighter' => array(
                        'label' => __( 'Firefighter', 'wc-aicc' ),
                        'hint'  => __( 'Heroic firefighter uniform and dramatic setting.', 'wc-aicc' ),
                    ),
                    'astronaut' => array(
                        'label' => __( 'Astronaut', 'wc-aicc' ),
                        'hint'  => __( 'Space suit with a cosmic environment.', 'wc-aicc' ),
                    ),
                    'pirate' => array(
                        'label' => __( 'Pirate', 'wc-aicc' ),
                        'hint'  => __( 'Classic pirate outfit with an ocean or ship setting.', 'wc-aicc' ),
                    ),
                    'gentleman' => array(
                        'label' => __( 'Gentleman', 'wc-aicc' ),
                        'hint'  => __( 'Sophisticated formal portrait with elegant attire.', 'wc-aicc' ),
                    ),
                ),
            ),
            'background_color' => array(
                'label'          => __( 'Background', 'wc-aicc' ),
                'section_label'  => __( 'Choose a background', 'wc-aicc' ),
                'type'           => 'cards',
                'choices'        => array(
                    'auto' => array(
                        'label'  => __( 'Auto', 'wc-aicc' ),
                        'hint'   => __( 'AI chooses the best background.', 'wc-aicc' ),
                        'swatch' => 'auto',
                    ),
                    'white' => array(
                        'label'  => __( 'White', 'wc-aicc' ),
                        'hint'   => __( 'Clean studio background.', 'wc-aicc' ),
                        'swatch' => '#f8f8f8',
                    ),
                    'black' => array(
                        'label'  => __( 'Black', 'wc-aicc' ),
                        'hint'   => __( 'Deep dramatic background.', 'wc-aicc' ),
                        'swatch' => '#1a1a1a',
                    ),
                    'navy' => array(
                        'label'  => __( 'Navy', 'wc-aicc' ),
                        'hint'   => __( 'Rich midnight-blue background.', 'wc-aicc' ),
                        'swatch' => '#1e3a5f',
                    ),
                    'cream' => array(
                        'label'  => __( 'Cream', 'wc-aicc' ),
                        'hint'   => __( 'Warm ivory background.', 'wc-aicc' ),
                        'swatch' => '#f5f0e6',
                    ),
                    'sage' => array(
                        'label'  => __( 'Sage', 'wc-aicc' ),
                        'hint'   => __( 'Soft muted-green background.', 'wc-aicc' ),
                        'swatch' => '#9caf88',
                    ),
                    'gray' => array(
                        'label'  => __( 'Gray', 'wc-aicc' ),
                        'hint'   => __( 'Clean neutral-gray background.', 'wc-aicc' ),
                        'swatch' => '#b0b0b0',
                    ),
                    'burgundy' => array(
                        'label'  => __( 'Burgundy', 'wc-aicc' ),
                        'hint'   => __( 'Rich wine-red background.', 'wc-aicc' ),
                        'swatch' => '#6b2d3e',
                    ),
                    'gold' => array(
                        'label'  => __( 'Gold', 'wc-aicc' ),
                        'hint'   => __( 'Warm golden background.', 'wc-aicc' ),
                        'swatch' => '#c9a227',
                    ),
                    'teal' => array(
                        'label'  => __( 'Teal', 'wc-aicc' ),
                        'hint'   => __( 'Deep turquoise background.', 'wc-aicc' ),
                        'swatch' => '#0d6e6e',
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
     * 3. Known aliases and normalized filename scan in those directories
     *
     * @param string $style_slug Style choice key (e.g. warhol_grid, watercolor).
     * @return string URL or empty if no file exists.
     */
    public static function resolve_style_example_image_url( $style_slug ) {
        $slug = preg_replace( '/[^a-z0-9_-]/i', '', (string) $style_slug );
        if ( $slug === '' || ! defined( 'WC_AICC_PLUGIN_DIR' ) || ! defined( 'WC_AICC_PLUGIN_URL' ) ) {
            return '';
        }

        $extensions  = array( 'webp', 'jpg', 'jpeg', 'png' );
        $candidates  = self::style_example_file_candidates( $slug );

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
                $found = self::find_style_example_asset( $upload_style_dir, $candidates, $extensions );
                if ( $found ) {
                    $upload_style_url = trailingslashit( $upload['baseurl'] ) . self::STYLE_EXAMPLES_UPLOAD_SUBDIR;
                    $url              = self::style_example_url_from_file( $upload_style_url, $found );
                    return (string) apply_filters( 'wc_aicc_style_example_image_url', $url, $slug, $found );
                }
            }
        }

        $subdir = 'assets/images/style-examples/';
        $dir    = WC_AICC_PLUGIN_DIR . $subdir;
        $base   = WC_AICC_PLUGIN_URL . $subdir;

        $found = self::find_style_example_asset( $dir, $candidates, $extensions );
        if ( $found ) {
            $url = self::style_example_url_from_file( $base, $found );
            return (string) apply_filters( 'wc_aicc_style_example_image_url', $url, $slug, $found );
        }

        return (string) apply_filters( 'wc_aicc_style_example_image_url', '', $slug, '' );
    }

    /**
     * Style slugs that have no bundled/upload preview image.
     *
     * @return string[]
     */
    public static function get_style_slugs_missing_example_images() {
        $missing = array();
        foreach ( self::get_choice_keys( 'style' ) as $slug ) {
            if ( self::resolve_style_example_image_url( $slug ) === '' ) {
                $missing[] = $slug;
            }
        }
        return $missing;
    }

    /**
     * @param string $slug Style slug.
     * @return string[]
     */
    private static function style_example_file_candidates( $slug ) {
        $candidates = array( $slug );
        if ( isset( self::STYLE_EXAMPLE_ALIASES[ $slug ] ) ) {
            $candidates = array_merge( $candidates, self::STYLE_EXAMPLE_ALIASES[ $slug ] );
        }
        return array_values( array_unique( $candidates ) );
    }

    /**
     * @param string $name Basename or label.
     * @return string
     */
    private static function normalize_style_example_key( $name ) {
        $name = strtolower( (string) $name );
        $name = preg_replace( '/[\s_-]+/', '_', $name );
        return (string) preg_replace( '/[^a-z0-9_]/', '', $name );
    }

    /**
     * @param string   $dir         Directory to search.
     * @param string[] $candidates  Basename candidates (without extension).
     * @param string[] $extensions  Allowed extensions.
     * @return string Absolute file path or empty string.
     */
    private static function find_style_example_asset( $dir, $candidates, $extensions ) {
        if ( ! is_dir( $dir ) ) {
            return '';
        }

        foreach ( $candidates as $base ) {
            foreach ( $extensions as $ext ) {
                $file = trailingslashit( $dir ) . $base . '.' . $ext;
                if ( file_exists( $file ) && is_readable( $file ) ) {
                    return $file;
                }
            }
        }

        $normalized_want = array();
        foreach ( $candidates as $base ) {
            $norm = self::normalize_style_example_key( $base );
            if ( $norm !== '' ) {
                $normalized_want[ $norm ] = true;
            }
        }
        if ( empty( $normalized_want ) ) {
            return '';
        }

        $entries = scandir( $dir );
        if ( ! is_array( $entries ) ) {
            return '';
        }

        foreach ( $entries as $entry ) {
            if ( $entry === '.' || $entry === '..' || $entry === 'index.php' ) {
                continue;
            }
            $path = trailingslashit( $dir ) . $entry;
            if ( ! is_file( $path ) || ! is_readable( $path ) ) {
                continue;
            }
            $info = pathinfo( $entry );
            $ext  = strtolower( (string) ( $info['extension'] ?? '' ) );
            if ( ! in_array( $ext, $extensions, true ) ) {
                continue;
            }
            $norm = self::normalize_style_example_key( (string) ( $info['filename'] ?? '' ) );
            if ( $norm !== '' && isset( $normalized_want[ $norm ] ) ) {
                return $path;
            }
        }

        return '';
    }

    /**
     * @param string $url_base Public URL base (with trailing slash).
     * @param string $file     Absolute filesystem path.
     * @return string
     */
    private static function style_example_url_from_file( $url_base, $file ) {
        $filename = basename( $file );
        $url      = trailingslashit( $url_base ) . $filename;
        $mtime    = @filemtime( $file );
        if ( $mtime ) {
            $url .= '?v=' . (int) $mtime;
        }
        return $url;
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
            if ( 'background_color' === $key && 'auto' === $value ) {
                continue;
            }
            $label = self::get_choice_label( $key, $value );
            if ( $label !== '' ) {
                $parts[] = $label;
            }
        }

        return $parts;
    }

    /**
     * Build prompt arrays from user customization options
     *
     * @param array $options User selections.
     * @param array $context Optional context (e.g. aspect_ratio from build).
     * @return array { prompt: string, negative_prompt: string }
     */
    public static function build( $options = array(), $context = array() ) {
        $options = self::sanitize_options( is_array( $options ) ? $options : array() );
        $context = is_array( $context ) ? $context : array();

        $style_key = $options['style'];
        $bg_key    = $options['background_color'];

        $styles = self::get_style_definitions();
        $bg_map = apply_filters( 'wc_aicc_prompt_background_phrases', self::BACKGROUND_PHRASES );

        $fallback  = $styles['original'] ?? ( ! empty( $styles ) ? reset( $styles ) : array() );
        $style_def = $styles[ $style_key ] ?? $fallback;
        $skip_bg   = ! empty( $style_def['skip_background_option'] );

        $prompt_parts = array();

        $aspect_ratio = isset( $context['aspect_ratio'] ) ? self::sanitize_aspect_ratio( $context['aspect_ratio'] ) : '';
        if ( $aspect_ratio !== '' ) {
            $prompt_parts[] = 'output aspect ratio ' . $aspect_ratio . ', portrait vertical canvas, compose the full artwork within frame bounds including all typography';
        }

        // Reference + identity (some styles ship their own likeness lines).
        if ( empty( $style_def['skip_identity_lines'] ) ) {
            $prompt_parts = array_merge( $prompt_parts, self::IDENTITY_LINES );
        }

        // Style core
        if ( ! empty( $style_def['core'] ) && is_array( $style_def['core'] ) ) {
            $prompt_parts = array_merge( $prompt_parts, $style_def['core'] );
        }

        if ( ! empty( $style_def['composition'] ) ) {
            $prompt_parts[] = $style_def['composition'];
        }

        // Background color (skip for fixed-background styles).
        if ( ! $skip_bg ) {
            $bg_phrase = $bg_map[ $bg_key ] ?? '';
            if ( $bg_phrase !== '' ) {
                $prompt_parts[] = $bg_phrase;
            }
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
        if ( ! empty( $style_def['allows_cover_text'] ) ) {
            $negative = array_values(
                array_filter(
                    $negative,
                    static function ( $line ) {
                        return stripos( (string) $line, 'no text' ) === false;
                    }
                )
            );
        }
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
        $raw = is_array( $raw ) ? $raw : array();

        if ( isset( $raw['style'] ) && is_string( $raw['style'] ) && isset( self::LEGACY_STYLE_MAP[ $raw['style'] ] ) ) {
            $raw['style'] = self::LEGACY_STYLE_MAP[ $raw['style'] ];
        }
        if ( isset( $raw['style'] ) && 'black_white' === $raw['style'] ) {
            $raw['style'] = 'original';
        }
        if ( isset( $raw['background_color'] ) && 'natural' === $raw['background_color'] ) {
            $raw['background_color'] = 'auto';
        }

        $config = self::get_options_config();
        $result = array();

        foreach ( array_keys( $config ) as $key ) {
            $value   = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
            $choices = self::get_choice_keys( $key );
            if ( in_array( $value, $choices, true ) ) {
                $result[ $key ] = $value;
            } else {
                $result[ $key ] = self::DEFAULTS[ $key ] ?? '';
            }
        }

        return wp_parse_args( $result, self::DEFAULTS );
    }

    /**
     * Sanitize canvas aspect ratio for prompt injection.
     *
     * @param mixed $raw Raw value.
     * @return string e.g. "4:5" or empty.
     */
    public static function sanitize_aspect_ratio( $raw ) {
        $t = strtolower( preg_replace( '/\s+/', '', (string) $raw ) );
        if ( preg_match( '/^\d+:\d+$/', $t ) ) {
            return $t;
        }
        return '';
    }
}
