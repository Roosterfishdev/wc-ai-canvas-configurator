<?php
/**
 * Photo upload guidelines modal content for the configurator.
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reference images and copy for the upload step guidelines dialog.
 */
class Photo_Guidelines {

	const UPLOAD_SUBDIR = 'wc-aicc-photo-guidelines';

	/**
	 * Payload for frontend modal.
	 *
	 * @return array{
	 *     title: string,
	 *     intro: string,
	 *     good_image: string,
	 *     good_label: string,
	 *     avoid_image: string,
	 *     avoid_label: string,
	 *     tips: string[],
	 *     footnote: string
	 * }
	 */
	public static function get_panel_data() {
		return array(
			'title'       => __( 'Photo Guidelines', 'wc-aicc' ),
			'intro'       => __( 'Get the best portrait by following these tips:', 'wc-aicc' ),
			'good_image'  => self::resolve_good_image_url(),
			'good_label'  => __( 'Good photo', 'wc-aicc' ),
			'avoid_image' => self::resolve_avoid_image_url(),
			'avoid_label' => __( 'Avoid', 'wc-aicc' ),
			'tips'        => array(
				'📷 ' . __( 'Upload a high-resolution photo.', 'wc-aicc' ),
				__( 'Make sure your pet is facing the camera. A front view is best.', 'wc-aicc' ),
				__( 'Keep the entire face and ears visible. Do not crop them out.', 'wc-aicc' ),
				__( 'Use good natural lighting and avoid dark or blurry photos.', 'wc-aicc' ),
				__( 'A simple, uncluttered background works best, but is not required.', 'wc-aicc' ),
				__( 'Include only one pet per photo for the most accurate portrait.', 'wc-aicc' ),
			),
			'footnote'    => __( 'Supported formats: JPG, PNG, WebP • Max file size: 10 MB', 'wc-aicc' ),
		);
	}

	/**
	 * @return string
	 */
	public static function resolve_good_image_url() {
		return self::resolve_asset_url(
			array( 'Yes-Reference.webp', 'yes-reference.webp', 'yes-reference.jpg', 'yes-reference.png' ),
			'good'
		);
	}

	/**
	 * @return string
	 */
	public static function resolve_avoid_image_url() {
		return self::resolve_asset_url(
			array( 'No-Reference.webp', 'no-reference.webp', 'no-reference.jpg', 'no-reference.png' ),
			'avoid'
		);
	}

	/**
	 * @param array  $filenames File names to try.
	 * @param string $slug      For filters.
	 * @return string
	 */
	private static function resolve_asset_url( array $filenames, $slug ) {
		if ( function_exists( 'wp_upload_dir' ) ) {
			$upload = wp_upload_dir();
			if ( empty( $upload['error'] ) ) {
				$dir = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;
				$url = trailingslashit( $upload['baseurl'] ) . self::UPLOAD_SUBDIR;
				foreach ( $filenames as $file ) {
					$path = trailingslashit( $dir ) . $file;
					if ( is_readable( $path ) ) {
						$found = trailingslashit( $url ) . $file;
						return (string) apply_filters( 'wc_aicc_photo_guidelines_image_url', $found, $slug, $path );
					}
				}
			}
		}

		$base     = defined( 'WC_AICC_PLUGIN_DIR' ) ? WC_AICC_PLUGIN_DIR . 'assets/images/' : '';
		$base_url = defined( 'WC_AICC_PLUGIN_URL' ) ? WC_AICC_PLUGIN_URL . 'assets/images/' : '';

		foreach ( $filenames as $file ) {
			$path = $base . $file;
			if ( $base && is_readable( $path ) ) {
				$found = $base_url . $file;
				return (string) apply_filters( 'wc_aicc_photo_guidelines_image_url', $found, $slug, $path );
			}
		}

		return (string) apply_filters( 'wc_aicc_photo_guidelines_image_url', '', $slug, '' );
	}
}
