<?php
/**
 * Sizing guide content (room / door reference) for the configurator slide-in panel.
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reference sizes shown beside a door for scale.
 */
class Sizing_Guide {

	const UPLOAD_SUBDIR = 'wc-aicc-sizing-guide';

	/**
	 * Canonical reference tiers (inches); cm derived for display.
	 *
	 * @return array<int, array{slug: string, label: string, width_in: float, height_in: float}>
	 */
	public static function reference_entries() {
		$entries = array(
			array(
				'slug'      => 'xs',
				'label'     => 'XS',
				'width_in'  => 11.7,
				'height_in' => 16.5,
			),
			array(
				'slug'      => 's',
				'label'     => 'S',
				'width_in'  => 16.5,
				'height_in' => 23.4,
			),
			array(
				'slug'      => 'm',
				'label'     => 'M',
				'width_in'  => 20.0,
				'height_in' => 28.0,
			),
			array(
				'slug'      => 'l',
				'label'     => 'L',
				'width_in'  => 23.4,
				'height_in' => 33.1,
			),
		);

		/**
		 * @param array<int, array> $entries Reference size tiers.
		 */
		return apply_filters( 'wc_aicc_sizing_guide_entries', $entries );
	}

	/**
	 * Payload for frontend slide-in panel.
	 *
	 * @return array{title: string, intro: string, grid_image: string, entries: array}
	 */
	public static function get_panel_data() {
		$out_entries = array();

		foreach ( self::reference_entries() as $entry ) {
			$w_in  = (float) $entry['width_in'];
			$h_in  = (float) $entry['height_in'];
			$w_cm  = (int) round( $w_in * Size_Display::CM_PER_IN );
			$h_cm  = (int) round( $h_in * Size_Display::CM_PER_IN );
			$slug  = isset( $entry['slug'] ) ? (string) $entry['slug'] : 'ref';

			$out_entries[] = array(
				'slug'   => $slug,
				'label'  => isset( $entry['label'] ) ? (string) $entry['label'] : '',
				'inches' => Size_Display::format_inches( $w_in, $h_in ),
				'cm'     => sprintf( '(%d×%d cm)', $w_cm, $h_cm ),
				'image'  => self::resolve_entry_image_url( $slug ),
			);
		}

		return array(
			'title'      => __( 'Sizing Guide', 'wc-aicc' ),
			'intro'      => __( 'See how each canvas size looks next to a standard door for scale.', 'wc-aicc' ),
			'grid_image' => self::resolve_grid_image_url(),
			'entries'    => $out_entries,
		);
	}

	/**
	 * Full grid reference (all sizes in one scene).
	 *
	 * @return string
	 */
	public static function resolve_grid_image_url() {
		$candidates = array( 'room-reference-grid.webp', 'room-reference-grid.jpg', 'room-reference-grid.png' );
		return self::resolve_asset_url( $candidates, 'grid' );
	}

	/**
	 * Per-tier image (optional override per slug).
	 *
	 * @param string $slug Entry slug.
	 * @return string
	 */
	public static function resolve_entry_image_url( $slug ) {
		$slug = preg_replace( '/[^a-z0-9_-]/i', '', (string) $slug );
		$candidates = array(
			$slug . '.webp',
			$slug . '.jpg',
			$slug . '.jpeg',
			$slug . '.png',
		);
		return self::resolve_asset_url( $candidates, $slug );
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
						return (string) apply_filters( 'wc_aicc_sizing_guide_image_url', $found, $slug, $path );
					}
				}
			}
		}

		$base = defined( 'WC_AICC_PLUGIN_DIR' ) ? WC_AICC_PLUGIN_DIR . 'assets/images/sizing-guide/' : '';
		$base_url = defined( 'WC_AICC_PLUGIN_URL' ) ? WC_AICC_PLUGIN_URL . 'assets/images/sizing-guide/' : '';

		foreach ( $filenames as $file ) {
			$path = $base . $file;
			if ( $base && is_readable( $path ) ) {
				$found = $base_url . $file;
				return (string) apply_filters( 'wc_aicc_sizing_guide_image_url', $found, $slug, $path );
			}
		}

		return (string) apply_filters( 'wc_aicc_sizing_guide_image_url', '', $slug, '' );
	}
}
