<?php
/**
 * Format canvas size labels for the configurator (inches primary, cm secondary).
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse and display size strings from WooCommerce variation labels.
 */
class Size_Display {

	const CM_PER_IN = 2.54;

	/**
	 * @param string $size_label Raw variation label.
	 * @return array{width_in: float, height_in: float, width_cm: int, height_cm: int, slug: string}
	 */
	public static function parse( $size_label ) {
		$label = trim( (string) $size_label );
		$unit  = ( stripos( $label, 'cm' ) !== false ) ? 'cm' : 'in';

		if ( ! preg_match( '/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)/i', $label, $m ) ) {
			return array(
				'width_in'  => 0.0,
				'height_in' => 0.0,
				'width_cm'  => 0,
				'height_cm' => 0,
				'slug'      => sanitize_title( $label ),
			);
		}

		$a = (float) $m[1];
		$b = (float) $m[2];

		if ( 'cm' === $unit ) {
			$width_cm  = (int) round( $a );
			$height_cm = (int) round( $b );
			$width_in  = $a / self::CM_PER_IN;
			$height_in = $b / self::CM_PER_IN;
		} else {
			$width_in  = $a;
			$height_in = $b;
			$width_cm  = (int) round( $a * self::CM_PER_IN );
			$height_cm = (int) round( $b * self::CM_PER_IN );
		}

		$slug_w = (int) round( $width_in );
		$slug_h = (int) round( $height_in );

		return array(
			'width_in'  => $width_in,
			'height_in' => $height_in,
			'width_cm'  => $width_cm,
			'height_cm' => $height_cm,
			'slug'      => $slug_w . 'x' . $slug_h,
		);
	}

	/**
	 * UI strings for step 1 cards.
	 *
	 * @param string $size_label Variation label.
	 * @return array{inches: string, cm: string, slug: string}
	 */
	public static function format_for_ui( $size_label ) {
		$p = self::parse( $size_label );

		if ( $p['width_in'] <= 0 || $p['height_in'] <= 0 ) {
			return array(
				'inches' => $size_label,
				'cm'     => '',
				'slug'   => $p['slug'],
			);
		}

		$inches = self::format_inches( $p['width_in'], $p['height_in'] );
		$cm     = sprintf(
			'(%d×%d cm)',
			$p['width_cm'],
			$p['height_cm']
		);

		return array(
			'inches' => $inches,
			'cm'     => $cm,
			'slug'   => $p['slug'],
		);
	}

	/**
	 * @param float $w Width in inches.
	 * @param float $h Height in inches.
	 * @return string e.g. 12" x 16"
	 */
	public static function format_inches( $w, $h ) {
		return self::format_dimension_pair( $w, $h, '"' );
	}

	/**
	 * @param float  $w    First dimension.
	 * @param float  $h    Second dimension.
	 * @param string $unit Suffix without space (e.g. " or empty).
	 * @return string
	 */
	private static function format_dimension_pair( $w, $h, $unit ) {
		$fw = self::format_number( $w );
		$fh = self::format_number( $h );

		if ( $unit === '"' ) {
			return $fw . '" x ' . $fh . '"';
		}

		return $fw . ' x ' . $fh . $unit;
	}

	/**
	 * @param float $n Numeric value.
	 * @return string
	 */
	private static function format_number( $n ) {
		if ( abs( $n - round( $n ) ) < 0.05 ) {
			return (string) (int) round( $n );
		}
		return rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * Price HTML for configurator UI — always show two decimal places (e.g. $79.00).
	 *
	 * @param \WC_Product $product Variation or simple product.
	 * @return string
	 */
	public static function format_variation_price_html( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		$force_decimals = static function ( $args ) {
			$args['decimals'] = 2;
			return $args;
		};
		$keep_trailing_zeros = static function () {
			return false;
		};

		add_filter( 'wc_price_args', $force_decimals, 999 );
		add_filter( 'woocommerce_price_trim_zeros', $keep_trailing_zeros, 999 );

		$html = $product->get_price_html();

		remove_filter( 'wc_price_args', $force_decimals, 999 );
		remove_filter( 'woocommerce_price_trim_zeros', $keep_trailing_zeros, 999 );

		return $html;
	}

	/**
	 * Add display fields to a variation row.
	 *
	 * @param array $variation Variation data.
	 * @return array
	 */
	public static function enrich_variation( array $variation ) {
		$label   = isset( $variation['size_label'] ) ? $variation['size_label'] : '';
		$display = self::format_for_ui( $label );

		$variation['size_inches'] = $display['inches'];
		$variation['size_cm']     = $display['cm'];
		$variation['size_slug']   = $display['slug'];

		return $variation;
	}
}
