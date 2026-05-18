<?php
/**
 * Preview watermark for stored final art (WebP).
 *
 * @package WC_AICC\Image
 */

namespace WC_AICC\Image;

use WC_AICC\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Final_Art_Watermarker {

	/**
	 * @param string $blob Raw image bytes from the AI provider.
	 * @return string|false WebP bytes, or false on failure.
	 */
	public static function apply( $blob ) {
		if ( ! is_string( $blob ) || $blob === '' ) {
			return false;
		}

		if ( ! apply_filters( 'wc_aicc_apply_watermark_to_final_art', true ) ) {
			return self::to_webp( $blob );
		}

		if ( self::has_imagick() ) {
			$out = self::watermark_imagick( $blob );
			if ( false !== $out && $out !== '' ) {
				return $out;
			}
			Logger::warning( 'Watermark', 'Imagick watermark failed; trying GD.', array() );
		}

		if ( self::has_gd() ) {
			$out = self::watermark_gd( $blob );
			if ( false !== $out && $out !== '' ) {
				return $out;
			}
		}

		Logger::error( 'Watermark', 'Could not watermark final art (Imagick and GD both failed).', array() );
		return false;
	}

	private static function has_imagick() {
		return extension_loaded( 'imagick' ) && class_exists( '\Imagick' );
	}

	private static function has_gd() {
		return extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' );
	}

	private static function webp_quality( $w, $h ) {
		$q = (int) apply_filters( 'wc_aicc_watermark_webp_quality', 82, $w, $h );
		return max( 2, min( 98, $q ) );
	}

	private static function strength() {
		$v = (float) apply_filters( 'wc_aicc_watermark_pattern_opacity', 0.45, 'final_art' );
		return max( 0.05, min( 1.0, $v ) );
	}

	private static function label_text() {
		$blog = function_exists( 'get_bloginfo' )
			? wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
			: '';

		return (string) apply_filters(
			'wc_aicc_watermark_text',
			trim( $blog ) !== ''
				? sprintf(
					/* translators: %s: site title */
					__( 'PREVIEW • %s', 'wc-aicc' ),
					$blog
				)
				: __( 'PREVIEW', 'wc-aicc' ),
			'final_art'
		);
	}

	private static function png_path() {
		$p = apply_filters( 'wc_aicc_watermark_image_path', '', 'final_art' );
		if ( is_string( $p ) && $p !== '' && is_readable( $p ) ) {
			return $p;
		}
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$u = WP_CONTENT_DIR . '/uploads/wc-aicc/watermark.png';
			if ( is_readable( $u ) ) {
				return $u;
			}
		}
		if ( defined( 'WC_AICC_PLUGIN_DIR' ) ) {
			$b = WC_AICC_PLUGIN_DIR . 'assets/images/watermark.png';
			if ( is_readable( $b ) ) {
				return $b;
			}
		}
		return false;
	}

	/**
	 * Normalize to WebP without pattern/logo.
	 *
	 * @param string $blob Input bytes.
	 * @return string|false
	 */
	private static function to_webp( $blob ) {
		if ( self::has_imagick() ) {
			try {
				$im = new \Imagick();
				$im->readImageBlob( $blob );
				if ( $im->getNumberImages() > 1 ) {
					$flat = $im->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );
					$im->clear();
					$im = $flat;
				}
				$w = max( 1, (int) $im->getImageWidth() );
				$h = max( 1, (int) $im->getImageHeight() );
				$im->setImageFormat( 'webp' );
				$im->setImageCompressionQuality( self::webp_quality( $w, $h ) );
				$out = $im->getImageBlob();
				$im->clear();
				return $out;
			} catch ( \Throwable $e ) {
				Logger::warning( 'Watermark', 'Imagick WebP encode failed: ' . $e->getMessage(), array() );
			}
		}

		if ( ! self::has_gd() || ! function_exists( 'imagewebp' ) ) {
			return false;
		}

		$src = self::gd_load( $blob );
		if ( ! $src ) {
			return false;
		}
		$w = imagesx( $src );
		$h = imagesy( $src );
		$tmp = wp_tempnam( 'wc-aicc-webp' );
		if ( ! $tmp ) {
			imagedestroy( $src );
			return false;
		}
		$ok = imagewebp( $src, $tmp, self::webp_quality( $w, $h ) );
		imagedestroy( $src );
		if ( ! $ok || ! is_readable( $tmp ) ) {
			@unlink( $tmp );
			return false;
		}
		$out = file_get_contents( $tmp );
		@unlink( $tmp );
		return is_string( $out ) ? $out : false;
	}

	private static function watermark_imagick( $blob ) {
		try {
			$im = new \Imagick();
			$im->readImageBlob( $blob );
			if ( $im->getNumberImages() > 1 ) {
				$flat = $im->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );
				$im->clear();
				$im = $flat;
			}
			$im->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			if ( method_exists( $im, 'stripImage' ) ) {
				$im->stripImage();
			}

			$w = max( 1, (int) $im->getImageWidth() );
			$h = max( 1, (int) $im->getImageHeight() );

			self::imagick_tile( $im, $w, $h );
			self::imagick_logo( $im, $w, $h );

			$im->setImageFormat( 'webp' );
			$im->setImageCompressionQuality( self::webp_quality( $w, $h ) );
			$out = $im->getImageBlob();
			$im->clear();
			return $out;
		} catch ( \Throwable $e ) {
			Logger::error( 'Watermark', $e->getMessage(), array() );
			return false;
		}
	}

	private static function imagick_tile( \Imagick $canvas, $w, $h ) {
		$text = sanitize_text_field( wp_strip_all_tags( self::label_text() ) );
		if ( $text === '' ) {
			return;
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 66 ) {
			$text = mb_substr( $text, 0, 63 ) . '…';
		} elseif ( strlen( $text ) > 78 ) {
			$text = substr( $text, 0, 75 ) . '...';
		}

		$s   = self::strength();
		$a   = max( 0.08, min( 0.92, 0.18 + 0.55 * $s ) );
		$fs  = max( 14, min( 88, (int) round( min( $w, $h ) / 12 ) ) );
		$ang = (float) apply_filters( 'wc_aicc_watermark_text_angle', -28.0, 'final_art' );

		$draw = new \ImagickDraw();
		$draw->setGravity( \Imagick::GRAVITY_CENTER );
		$draw->setFontSize( $fs );
		$font = apply_filters( 'wc_aicc_watermark_font_path', '', 'final_art' );
		if ( is_string( $font ) && $font !== '' && is_readable( $font ) ) {
			$draw->setFont( $font );
		}
		$draw->setFillColor( new \ImagickPixel( 'rgba(248,250,255,' . $a . ')' ) );

		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		$tW  = (int) max( 240, min( (int) ( $w * 0.95 ), max( (int) round( $len * $fs * 0.62 ), (int) ( $fs * 10 ) ) ) );
		$tH  = (int) max( (int) round( $fs * 3.4 ), 100 );

		$tile = new \Imagick();
		$tile->newImage( $tW, $tH, new \ImagickPixel( 'transparent' ) );
		$tile->setImageFormat( 'png' );
		$tile->annotateImage( $draw, 0, 0, $ang, $text );

		$stepX = (int) max( 80, round( $tW * 0.52 ) );
		$stepY = (int) max( 60, round( $tH * 0.72 ) );

		for ( $y = -$tH; $y < $h + $tH; $y += $stepY ) {
			for ( $x = -$tW; $x < $w + $tW; $x += $stepX ) {
				$layer = clone $tile;
				$canvas->compositeImage( $layer, \Imagick::COMPOSITE_OVER, $x, $y );
				$layer->clear();
			}
		}
		$tile->clear();
	}

	private static function imagick_logo( \Imagick $canvas, $w, $h ) {
		$file = self::png_path();
		if ( ! $file ) {
			return;
		}
		try {
			$ov = new \Imagick();
			$ov->readImage( $file );
		} catch ( \Throwable $e ) {
			Logger::warning( 'Watermark', 'PNG watermark unreadable.', array() );
			return;
		}
		$ov->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
		$mw = max( 1, (int) $ov->getImageWidth() );
		$mh = max( 1, (int) $ov->getImageHeight() );
		$cap = max( (int) round( min( $w, $h ) * 0.31 ), (int) round( max( $w, $h ) * 0.14 ) );
		if ( max( $mw, $mh ) > $cap ) {
			$ov->thumbnailImage( $cap, $cap, true );
		}
		try {
			$mul = 0.55 + self::strength() * 0.32;
			$ov->evaluateImage( \Imagick::EVALUATE_MULTIPLY, $mul );
		} catch ( \Throwable $e ) {
			Logger::warning( 'Watermark', 'Imagick evaluate on logo skipped: ' . $e->getMessage(), array() );
		}
		$ow = max( 1, (int) $ov->getImageWidth() );
		$oh = max( 1, (int) $ov->getImageHeight() );
		$canvas->compositeImage(
			$ov,
			\Imagick::COMPOSITE_OVER,
			(int) floor( ( $w - $ow ) / 2 ),
			(int) floor( ( $h - $oh ) / 2 )
		);
		$ov->clear();
	}

	private static function is_webp_binary( $data ) {
		return strlen( $data ) > 12
			&& substr( $data, 0, 4 ) === 'RIFF'
			&& substr( $data, 8, 4 ) === 'WEBP';
	}

	private static function gd_load( $blob ) {
		$img = @imagecreatefromstring( $blob );
		if ( $img ) {
			return $img;
		}
		if ( function_exists( 'imagecreatefromwebp' ) && self::is_webp_binary( $blob ) ) {
			$tmp = wp_tempnam( 'wc-aicc-art' );
			if ( $tmp && @file_put_contents( $tmp, $blob ) !== false ) {
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

	private static function watermark_gd( $blob ) {
		if ( ! function_exists( 'imagewebp' ) ) {
			return false;
		}

		$src = self::gd_load( $blob );
		if ( ! $src ) {
			return false;
		}

		$w = imagesx( $src );
		$h = imagesy( $src );
		imagealphablending( $src, true );
		imagesavealpha( $src, true );

		self::gd_tile_text( $src, $w, $h );
		self::gd_logo( $src, $w, $h );

		$tmp = wp_tempnam( 'wc-aicc-wm' );
		if ( ! $tmp ) {
			imagedestroy( $src );
			return false;
		}
		$q   = self::webp_quality( $w, $h );
		$ok  = imagewebp( $src, $tmp, $q );
		imagedestroy( $src );
		if ( ! $ok || ! is_readable( $tmp ) ) {
			@unlink( $tmp );
			return false;
		}
		$out = file_get_contents( $tmp );
		@unlink( $tmp );
		return is_string( $out ) ? $out : false;
	}

	private static function gd_tile_text( $canvas, $w, $h ) {
		$text = sanitize_text_field( wp_strip_all_tags( self::label_text() ) );
		if ( $text === '' ) {
			return;
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 66 ) {
			$text = mb_substr( $text, 0, 63 ) . '…';
		} elseif ( strlen( $text ) > 78 ) {
			$text = substr( $text, 0, 75 ) . '...';
		}

		$s   = self::strength();
		$alp = (int) max( 1, min( 127, (int) round( 115 * $s ) ) );
		$ang = (float) apply_filters( 'wc_aicc_watermark_text_angle', -28.0, 'final_art' );

		$tW = (int) max( 200, min( $w, (int) round( strlen( $text ) * ( $w > 900 ? 16 : 12 ) ) ) );
		$tH = (int) max( 64, round( min( $w, $h ) * 0.12 ) );

		$t = imagecreatetruecolor( $tW, $tH );
		imagealphablending( $t, false );
		imagesavealpha( $t, true );
		$transparent = imagecolorallocatealpha( $t, 0, 0, 0, 127 );
		imagefill( $t, 0, 0, $transparent );

		$font = apply_filters( 'wc_aicc_watermark_font_path', '', 'final_art' );
		$placed = false;
		if ( is_string( $font ) && $font !== '' && is_readable( $font )
			&& function_exists( 'imagettfbbox' ) && function_exists( 'imagettftext' )
		) {
			$size = max( 12.0, min( 64.0, min( $w, $h ) / 14.0 ) );
			$col  = imagecolorallocatealpha( $t, 248, 250, 255, 127 - $alp );
			$bbox = imagettfbbox( $size, 0, $font, $text );
			if ( is_array( $bbox ) ) {
				$x = ( $tW - ( $bbox[2] - $bbox[0] ) ) / 2;
				$y = ( $tH + abs( $bbox[7] - $bbox[1] ) ) / 2;
				imagettftext( $t, $size, 0, (int) $x, (int) $y, $col, $font, $text );
				$placed = true;
			}
		}
		if ( ! $placed ) {
			$col = imagecolorallocatealpha( $t, 248, 250, 255, 127 - $alp );
			$fx  = 5;
			$x0  = (int) max( 4, ( $tW - strlen( $text ) * imagefontwidth( $fx ) ) / 2 );
			$y0  = (int) max( 4, ( $tH - imagefontheight( $fx ) ) / 2 );
			imagestring( $t, $fx, $x0, $y0, $text, $col );
		}

		$bg = imagecolorallocatealpha( $t, 0, 0, 0, 127 );
		$rot = imagerotate( $t, $ang, $bg );
		imagedestroy( $t );
		if ( ! $rot ) {
			return;
		}
		imagealphablending( $rot, true );
		imagesavealpha( $rot, true );

		$rw = imagesx( $rot );
		$rh = imagesy( $rot );
		$stepX = (int) max( 60, round( $rw * 0.45 ) );
		$stepY = (int) max( 50, round( $rh * 0.65 ) );
		$pct   = (int) max( 30, min( 100, (int) round( 40 + 60 * $s ) ) );

		for ( $y = -$rh; $y < $h + $rh; $y += $stepY ) {
			for ( $x = -$rw; $x < $w + $rw; $x += $stepX ) {
				imagecopymerge( $canvas, $rot, $x, $y, 0, 0, $rw, $rh, $pct );
			}
		}
		imagedestroy( $rot );
	}

	private static function gd_logo( $canvas, $w, $h ) {
		$file = self::png_path();
		if ( ! $file ) {
			return;
		}
		$ov = @imagecreatefrompng( $file );
		if ( ! $ov ) {
			return;
		}
		imagealphablending( $ov, false );
		imagesavealpha( $ov, true );
		$ow = imagesx( $ov );
		$oh = imagesy( $ov );
		$cap = max( (int) round( min( $w, $h ) * 0.31 ), (int) round( max( $w, $h ) * 0.14 ) );
		$scale = 1.0;
		if ( max( $ow, $oh ) > $cap ) {
			$scale = $cap / max( $ow, $oh );
		}
		$nw = max( 1, (int) round( $ow * $scale ) );
		$nh = max( 1, (int) round( $oh * $scale ) );
		$res = imagecreatetruecolor( $nw, $nh );
		imagealphablending( $res, false );
		imagesavealpha( $res, true );
		$tr = imagecolorallocatealpha( $res, 0, 0, 0, 127 );
		imagefill( $res, 0, 0, $tr );
		imagecopyresampled( $res, $ov, 0, 0, 0, 0, $nw, $nh, $ow, $oh );
		imagedestroy( $ov );

		$s   = self::strength();
		$pct = (int) max( 35, min( 100, (int) round( 45 + 50 * $s ) ) );
		$dx  = (int) floor( ( $w - $nw ) / 2 );
		$dy  = (int) floor( ( $h - $nh ) / 2 );
		imagecopymerge( $canvas, $res, $dx, $dy, 0, 0, $nw, $nh, $pct );
		imagedestroy( $res );
	}
}
