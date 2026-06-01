<?php
/**
 * CSS-overlay preview watermark (no server-side compositing).
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves bundled watermark asset and renders overlay markup.
 */
class Preview_Watermark {

	/**
	 * Bundled watermark relative to plugin root.
	 */
	const ASSET_REL_PATH = 'assets/images/preview-watermark.svg';

	/**
	 * Whether the preview watermark overlay is active.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$url = self::get_url();

		/**
		 * Toggle preview watermark overlay on customer-facing previews.
		 *
		 * @param bool $enabled Default true when asset URL exists.
		 */
		return (bool) apply_filters( 'wc_aicc_preview_watermark_enabled', $url !== '' );
	}

	/**
	 * Public URL for the watermark overlay image.
	 *
	 * @return string Empty when no asset is configured.
	 */
	public static function get_url() {
		if ( ! defined( 'WC_AICC_PLUGIN_DIR' ) || ! defined( 'WC_AICC_PLUGIN_URL' ) ) {
			return '';
		}

		$file = WC_AICC_PLUGIN_DIR . self::ASSET_REL_PATH;
		if ( ! is_readable( $file ) ) {
			/**
			 * Custom watermark URL when bundled asset is missing.
			 *
			 * @param string $url Empty by default.
			 */
			return (string) apply_filters( 'wc_aicc_preview_watermark_url', '' );
		}

		$url   = WC_AICC_PLUGIN_URL . self::ASSET_REL_PATH;
		$mtime = @filemtime( $file );
		if ( $mtime ) {
			$url .= '?v=' . (int) $mtime;
		}

		/**
		 * Filter watermark overlay URL.
		 *
		 * @param string $url Asset URL.
		 * @param string $file Absolute filesystem path.
		 */
		return (string) apply_filters( 'wc_aicc_preview_watermark_url', $url, $file );
	}

	/**
	 * Opening wrapper for a watermarked preview image.
	 *
	 * @param string $extra_class Optional BEM modifier classes.
	 * @return string HTML opening tag.
	 */
	public static function open_wrapper( $extra_class = '' ) {
		$classes = 'wc-aicc-art-preview';
		if ( self::is_enabled() ) {
			$classes .= ' wc-aicc-art-preview--watermarked';
		}
		if ( $extra_class !== '' ) {
			$classes .= ' ' . $extra_class;
		}

		return '<div class="' . esc_attr( trim( $classes ) ) . '">';
	}

	/**
	 * Closing wrapper tag.
	 *
	 * @return string
	 */
	public static function close_wrapper() {
		return '</div>';
	}

	/**
	 * Overlay image markup (empty when disabled).
	 *
	 * @return string
	 */
	public static function render_overlay_markup() {
		if ( ! self::is_enabled() ) {
			return '';
		}

		return sprintf(
			'<img class="wc-aicc-art-preview__watermark" src="%s" alt="" decoding="async" aria-hidden="true" draggable="false" />',
			esc_url( self::get_url() )
		);
	}

	/**
	 * Wrap an image URL in the preview + watermark overlay structure.
	 *
	 * @param string $image_url Artwork or mockup URL.
	 * @param string $alt       Alt text.
	 * @param string $img_class Classes for the base image.
	 * @param string $wrapper_class Extra wrapper classes.
	 * @return string HTML.
	 */
	public static function wrap_image_html( $image_url, $alt, $img_class = '', $wrapper_class = '' ) {
		if ( $image_url === '' ) {
			return '';
		}

		$class_attr = trim( 'wc-aicc-art-preview__image ' . $img_class );

		$html  = self::open_wrapper( $wrapper_class );
		$html .= sprintf(
			'<img src="%s" alt="%s" class="%s" loading="lazy" decoding="async" draggable="false" />',
			esc_url( $image_url ),
			esc_attr( $alt ),
			esc_attr( $class_attr )
		);
		$html .= self::render_overlay_markup();
		$html .= self::close_wrapper();

		return $html;
	}
}
