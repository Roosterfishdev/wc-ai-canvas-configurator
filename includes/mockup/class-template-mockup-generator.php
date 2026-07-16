<?php
/**
 * Template Mockup Generator
 *
 * Generates room mockups by compositing artwork onto predefined template images.
 * Uses Imagick if available, falls back to GD.
 *
 * @package WC_AICC\Mockup
 */

namespace WC_AICC\Mockup;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Template Mockup Generator class
 */
class Template_Mockup_Generator implements Mockup_Generator_Interface {

    /**
     * Template configuration
     * 
     * Template: room-1.webp (2385 x 1590)
     * Drop zone: x=910, y=240, width=586, height=769
     */
    const TEMPLATE_CONFIG = array(
        'room-1' => array(
            'file'      => 'room-1.webp',
            'width'     => 2385,
            'height'    => 1590,
            'drop_zone' => array(
                'x'      => 910,
                'y'      => 240,
                'width'  => 586,
                'height' => 769,
            ),
        ),
    );

    /**
     * Default template ID
     *
     * @var string
     */
    const DEFAULT_TEMPLATE = 'room-1';

    /**
     * Output quality for JPEG (0-100)
     *
     * @var int
     */
    const OUTPUT_QUALITY = 90;

    /**
     * Get generator identifier
     *
     * @return string
     */
    public function get_id() {
        return 'template';
    }

    /**
     * Get generator display name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Template Mockup Generator', 'wc-aicc' );
    }

    /**
     * Check if generator is available
     *
     * @return bool True if template file exists and image library is available.
     */
    public function is_available() {
        // Check if at least one image library is available
        if ( ! $this->has_imagick() && ! $this->has_gd() ) {
            return false;
        }

        // Check if default template file exists
        $template_path = $this->get_template_path( self::DEFAULT_TEMPLATE );
        return file_exists( $template_path );
    }

    /**
     * Generate mockup from final artwork
     *
     * @param string $final_art_url URL of the final artwork image.
     * @param string $size_label    Size label (e.g., '16x20', '24x36').
     * @param string $aspect_ratio  Aspect ratio (e.g., '1:1', '3:4').
     * @param array  $options       Additional options (template_id, etc.).
     * @return array {
     *     @type bool   $success    Whether generation succeeded.
     *     @type string $image_data Binary image data of the mockup.
     *     @type string $error      Error message (if failed).
     * }
     */
    public function generate( $final_art_url, $size_label, $aspect_ratio, $options = array() ) {
        $template_id = $options['template_id'] ?? self::DEFAULT_TEMPLATE;

        $this->log( "Generating mockup - template: {$template_id}, artwork: {$final_art_url}" );

        try {
            // Validate template exists
            $template_config = self::TEMPLATE_CONFIG[ $template_id ] ?? null;
            if ( ! $template_config ) {
                throw new \Exception( sprintf( __( 'Template "%s" not found.', 'wc-aicc' ), $template_id ) );
            }

            $template_path = $this->get_template_path( $template_id );
            if ( ! file_exists( $template_path ) ) {
                throw new \Exception( sprintf( __( 'Template file "%s" not found.', 'wc-aicc' ), $template_config['file'] ) );
            }

            // Download the final artwork
            $artwork_data = $this->download_image( $final_art_url );
            if ( ! $artwork_data ) {
                throw new \Exception( __( 'Failed to download artwork image.', 'wc-aicc' ) );
            }

            // Generate composite using available library
            if ( $this->has_imagick() ) {
                $result = $this->composite_with_imagick( $template_path, $artwork_data, $template_config['drop_zone'] );
            } elseif ( $this->has_gd() ) {
                $result = $this->composite_with_gd( $template_path, $artwork_data, $template_config['drop_zone'] );
            } else {
                throw new \Exception( __( 'No image processing library available. Please install Imagick or GD.', 'wc-aicc' ) );
            }

            $this->log( "Mockup generated successfully" );

            return array(
                'success'    => true,
                'image_data' => $result,
                'error'      => '',
            );

        } catch ( \Exception $e ) {
            $this->log( 'Mockup generation failed: ' . $e->getMessage(), 'error' );

            return array(
                'success' => false,
                'error'   => $e->getMessage(),
            );
        }
    }

    /**
     * Composite artwork onto template using Imagick
     *
     * @param string $template_path Path to template image.
     * @param string $artwork_data  Binary artwork image data.
     * @param array  $drop_zone     Drop zone configuration (x, y, width, height).
     * @return string Binary image data of composite.
     * @throws \Exception On failure.
     */
    private function composite_with_imagick( $template_path, $artwork_data, $drop_zone ) {
        $this->log( "Using Imagick for compositing" );

        // Load template
        $template = new \Imagick();
        $template->readImage( $template_path );

        // Load artwork
        $artwork = new \Imagick();
        $artwork->readImageBlob( $artwork_data );

        // Get artwork dimensions
        $art_width  = $artwork->getImageWidth();
        $art_height = $artwork->getImageHeight();

        // Calculate "cover" fit dimensions
        $cover = $this->calculate_cover_fit( $art_width, $art_height, $drop_zone['width'], $drop_zone['height'] );

        // Resize artwork to cover dimensions
        $artwork->resizeImage( $cover['resize_width'], $cover['resize_height'], \Imagick::FILTER_LANCZOS, 1 );

        // Crop to exact drop zone size (centered)
        $artwork->cropImage(
            $drop_zone['width'],
            $drop_zone['height'],
            $cover['crop_x'],
            $cover['crop_y']
        );

        // Reset image page after crop
        $artwork->setImagePage( 0, 0, 0, 0 );

        // Composite artwork onto template
        $template->compositeImage(
            $artwork,
            \Imagick::COMPOSITE_OVER,
            $drop_zone['x'],
            $drop_zone['y']
        );

        // Output as JPEG
        $template->setImageFormat( 'jpeg' );
        $template->setImageCompressionQuality( self::OUTPUT_QUALITY );

        $result = $template->getImageBlob();

        // Cleanup
        $artwork->destroy();
        $template->destroy();

        return $result;
    }

    /**
     * Composite artwork onto template using GD
     *
     * @param string $template_path Path to template image.
     * @param string $artwork_data  Binary artwork image data.
     * @param array  $drop_zone     Drop zone configuration (x, y, width, height).
     * @return string Binary image data of composite.
     * @throws \Exception On failure.
     */
    private function composite_with_gd( $template_path, $artwork_data, $drop_zone ) {
        $this->log( "Using GD for compositing" );

        // Load template (detect format from extension)
        $template_ext = strtolower( pathinfo( $template_path, PATHINFO_EXTENSION ) );
        
        switch ( $template_ext ) {
            case 'jpg':
            case 'jpeg':
                $template = imagecreatefromjpeg( $template_path );
                break;
            case 'png':
                $template = imagecreatefrompng( $template_path );
                break;
            case 'webp':
                $template = imagecreatefromwebp( $template_path );
                break;
            default:
                throw new \Exception( sprintf( __( 'Unsupported template format: %s', 'wc-aicc' ), $template_ext ) );
        }

        if ( ! $template ) {
            throw new \Exception( __( 'Failed to load template image.', 'wc-aicc' ) );
        }

        // Load artwork (WebP final art often needs imagecreatefromwebp; imagecreatefromstring may fail on GD without WebP).
        $artwork = $this->load_artwork_for_gd( $artwork_data );
        if ( ! $artwork ) {
            imagedestroy( $template );
            throw new \Exception( __( 'Failed to load artwork image (install Imagick or PHP GD with WebP support).', 'wc-aicc' ) );
        }

        // Get artwork dimensions
        $art_width  = imagesx( $artwork );
        $art_height = imagesy( $artwork );

        // Calculate "cover" fit dimensions
        $cover = $this->calculate_cover_fit( $art_width, $art_height, $drop_zone['width'], $drop_zone['height'] );

        // Create resized artwork image
        $resized = imagecreatetruecolor( $cover['resize_width'], $cover['resize_height'] );
        imagecopyresampled(
            $resized,
            $artwork,
            0, 0,
            0, 0,
            $cover['resize_width'],
            $cover['resize_height'],
            $art_width,
            $art_height
        );

        // Create cropped artwork (drop zone size)
        $cropped = imagecreatetruecolor( $drop_zone['width'], $drop_zone['height'] );
        imagecopy(
            $cropped,
            $resized,
            0, 0,
            $cover['crop_x'],
            $cover['crop_y'],
            $drop_zone['width'],
            $drop_zone['height']
        );

        // Paste cropped artwork onto template
        imagecopy(
            $template,
            $cropped,
            $drop_zone['x'],
            $drop_zone['y'],
            0, 0,
            $drop_zone['width'],
            $drop_zone['height']
        );

        // Output as JPEG to buffer
        ob_start();
        imagejpeg( $template, null, self::OUTPUT_QUALITY );
        $result = ob_get_clean();

        // Cleanup
        imagedestroy( $artwork );
        imagedestroy( $resized );
        imagedestroy( $cropped );
        imagedestroy( $template );

        return $result;
    }

    /**
     * Calculate cover fit dimensions
     *
     * Calculates resize dimensions so image completely covers the target area,
     * preserving aspect ratio. Also calculates centered crop offsets.
     *
     * @param int $src_width   Source image width.
     * @param int $src_height  Source image height.
     * @param int $target_width  Target area width.
     * @param int $target_height Target area height.
     * @return array Resize dimensions and crop offsets.
     */
    private function calculate_cover_fit( $src_width, $src_height, $target_width, $target_height ) {
        $src_ratio    = $src_width / $src_height;
        $target_ratio = $target_width / $target_height;

        if ( $src_ratio > $target_ratio ) {
            // Source is wider - fit to height, crop width
            $resize_height = $target_height;
            $resize_width  = (int) round( $target_height * $src_ratio );
            $crop_x        = (int) round( ( $resize_width - $target_width ) / 2 );
            $crop_y        = 0;
        } else {
            // Source is taller - fit to width, crop height
            $resize_width  = $target_width;
            $resize_height = (int) round( $target_width / $src_ratio );
            $crop_x        = 0;
            $crop_y        = (int) round( ( $resize_height - $target_height ) / 2 );
        }

        return array(
            'resize_width'  => $resize_width,
            'resize_height' => $resize_height,
            'crop_x'        => $crop_x,
            'crop_y'        => $crop_y,
        );
    }

    /**
     * Download image from URL (uses disk read for local uploads when possible).
     *
     * @param string $url Image URL.
     * @return string|false Binary image data or false on failure.
     */
    private function download_image( $url ) {
        $data = Mockup_Image_Fetcher::fetch( $url );
        if ( $data === false ) {
            $this->log( 'Download failed for artwork URL', 'error' );
            return false;
        }

        return $data;
    }

    /**
     * Decode artwork bytes for GD (handles WebP when imagecreatefromstring fails).
     *
     * @param string $artwork_data Raw image bytes.
     * @return resource|\GdImage|false
     */
    private function load_artwork_for_gd( $artwork_data ) {
        $img = @imagecreatefromstring( $artwork_data );
        if ( $img ) {
            return $img;
        }

        if ( function_exists( 'imagecreatefromwebp' ) && $this->is_webp_binary( $artwork_data ) ) {
            $tmp = wp_tempnam( 'wc-aicc-mockup' );
            if ( $tmp && @file_put_contents( $tmp, $artwork_data ) !== false ) {
                $webp = @imagecreatefromwebp( $tmp );
                @unlink( $tmp );
                if ( $webp ) {
                    return $webp;
                }
            }
            if ( $tmp && file_exists( $tmp ) ) {
                @unlink( $tmp );
            }
        }

        return false;
    }

    /**
     * @param string $data Raw bytes.
     * @return bool
     */
    private function is_webp_binary( $data ) {
        return strlen( $data ) > 12
            && substr( $data, 0, 4 ) === 'RIFF'
            && substr( $data, 8, 4 ) === 'WEBP';
    }

    /**
     * Get template file path
     *
     * @param string $template_id Template ID.
     * @return string Full file path.
     */
    private function get_template_path( $template_id ) {
        $config = self::TEMPLATE_CONFIG[ $template_id ] ?? null;
        if ( ! $config ) {
            return '';
        }

        return WC_AICC_PLUGIN_DIR . 'assets/mockups/' . $config['file'];
    }

    /**
     * Check if Imagick is available
     *
     * @return bool
     */
    private function has_imagick() {
        return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
    }

    /**
     * Check if GD is available
     *
     * @return bool
     */
    private function has_gd() {
        return extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' );
    }

    /**
     * Get available templates
     *
     * @return array Array of template definitions.
     */
    public function get_templates() {
        $templates = array();

        foreach ( self::TEMPLATE_CONFIG as $id => $config ) {
            $templates[] = array(
                'id'          => $id,
                'name'        => sprintf( __( 'Room %s', 'wc-aicc' ), ucfirst( str_replace( 'room-', '', $id ) ) ),
                'description' => __( 'Modern living room with white wall.', 'wc-aicc' ),
                'thumbnail'   => WC_AICC_PLUGIN_URL . 'assets/mockups/' . $config['file'],
                'dimensions'  => array(
                    'width'  => $config['width'],
                    'height' => $config['height'],
                ),
            );
        }

        return $templates;
    }

    /**
     * Log message
     *
     * @param string $message Message to log.
     * @param string $level   Log level (info, error).
     */
    private function log( $message, $level = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $prefix = '[WC_AICC Mockup]';
            
            if ( $level === 'error' ) {
                $prefix .= ' ERROR:';
            }

            error_log( $prefix . ' ' . $message );
        }
    }
}
