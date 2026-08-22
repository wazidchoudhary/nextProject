<?php
/**
 * Deterministic demo imagery.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Demo;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;

/**
 * Renders original product imagery with GD.
 *
 * Why generate instead of downloading: a demo store must not ship scraped
 * competitor photography, and stock libraries come with licence obligations
 * that do not survive being copied into a client repository. Every image here
 * is drawn from code — an original work with no third-party rights attached —
 * and is deterministic: the same SKU always produces the same image, so
 * re-seeding does not churn the media library or the diff.
 *
 * Technique: a low resolution material texture (procedural grain, banding and
 * speckle per material family) is rendered, then resampled up onto a studio
 * backdrop with a soft shadow. Working at low resolution keeps the per-pixel
 * loops to a few hundred thousand iterations instead of millions, which is the
 * difference between seeding in seconds and seeding in minutes.
 */
final class ImageFactory {

	private const CANVAS       = 1200;
	private const TEXTURE      = 320;
	private const JPEG_QUALITY = 82;

	/**
	 * Material palettes: base, grain light, grain dark, accent.
	 *
	 * @var array<string, array{0:string,1:string,2:string,3:string}>
	 */
	private const PALETTES = [
		'camel-bone'         => [ '#EFE7D6', '#FBF6EA', '#D8CCB4', '#C2B393' ],
		'cattle-bone'        => [ '#EAE1CC', '#F7F1E1', '#D0C3A7', '#B7A88A' ],
		'water-buffalo-horn' => [ '#2B2724', '#4A423A', '#141210', '#6B5B45' ],
		'rams-horn'          => [ '#C9AE86', '#E4D0AE', '#8E7452', '#6A5334' ],
		'stabilized-wood'    => [ '#9A6B3F', '#C79157', '#6E4726', '#3F2915' ],
		'hardwood'           => [ '#7C5230', '#A97748', '#4E3218', '#2E1D0D' ],
		'acrylic'            => [ '#2F4F63', '#6FA3BF', '#1B2E3B', '#D8E7EF' ],
		'brass-pin-stock'    => [ '#B08A3E', '#E2C173', '#7C5F22', '#4A3812' ],
	];

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( private LoggerInterface $logger ) {}

	/**
	 * Whether the GD extension can render the imagery.
	 */
	public function is_available(): bool {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' );
	}

	/**
	 * Creates (or reuses) an attachment for a product view.
	 *
	 * @param string $sku      Product SKU, used as the deterministic seed.
	 * @param string $material Material slug.
	 * @param string $shape    Shape family: scale|blank|horn|comb|round|bead.
	 * @param string $title    Human readable image title.
	 * @param string $alt      Alt text.
	 * @param int    $view     View index (0 = hero, 1..n = gallery angles).
	 *
	 * @return int Attachment id, or 0 on failure.
	 */
	public function create( string $sku, string $material, string $shape, string $title, string $alt, int $view = 0 ): int {
		$filename = sanitize_file_name( strtolower( $sku ) . '-' . $view . '.jpg' );

		$existing = $this->find_existing( $filename );

		if ( $existing > 0 ) {
			return $existing;
		}

		if ( ! $this->is_available() ) {
			return 0;
		}

		$binary = $this->render( $sku, $material, $shape, $view );

		if ( '' === $binary ) {
			return 0;
		}

		$upload = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) ) {
			$this->logger->warning(
				'demo.image_failed',
				[
					'sku'   => $sku,
					'error' => (string) $upload['error'],
				]
			);

			return 0;
		}

		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $title,
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_name'      => sanitize_title( $sku . '-' . $view ),
			],
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) || 0 === (int) $attachment_id ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $upload['file'] );

		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( (int) $attachment_id, $metadata );
		}

		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $alt );

		// Marker used by `wp bhc demo reset --orphans`: generated imagery must
		// be identifiable even if the run that produced it never finished.
		update_post_meta( (int) $attachment_id, '_bhc_demo', 'yes' );

		return (int) $attachment_id;
	}

	/**
	 * Finds a previously generated attachment by file name.
	 *
	 * @param string $filename File name.
	 */
	private function find_existing( string $filename ): int {
		global $wpdb;

		$slug = sanitize_title( pathinfo( $filename, PATHINFO_FILENAME ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Seeder-only lookup by unique slug.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = %s LIMIT 1",
				$slug
			)
		);

		return (int) $id;
	}

	/**
	 * Renders the JPEG binary for one view.
	 *
	 * @param string $sku      SKU seed.
	 * @param string $material Material slug.
	 * @param string $shape    Shape family.
	 * @param int    $view     View index.
	 */
	private function render( string $sku, string $material, string $shape, int $view ): string {
		$seed = crc32( $sku . '|' . $view );

		// Deterministic imagery: the same SKU and view must always regenerate the
		// same texture so reseeding the demo store does not churn every file.
		// wp_rand() is a CSPRNG and cannot be seeded, which is the point here.
		mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Reproducible demo textures, not security.

		$canvas = imagecreatetruecolor( self::CANVAS, self::CANVAS );

		if ( false === $canvas ) {
			return '';
		}

		imageantialias( $canvas, true );
		imagealphablending( $canvas, true );
		imagesavealpha( $canvas, false );

		$this->paint_backdrop( $canvas, $view );

		$texture = $this->build_texture( $material, $shape, $seed );

		if ( false !== $texture ) {
			$this->place_object( $canvas, $texture, $shape, $view );

			imagedestroy( $texture );
		}

		$this->paint_vignette( $canvas );

		ob_start();
		imagejpeg( $canvas, null, self::JPEG_QUALITY );
		$binary = (string) ob_get_clean();

		imagedestroy( $canvas );

		mt_srand(); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Restores PHP's own seeding for the rest of the request.

		return $binary;
	}

	/**
	 * Paints the studio backdrop.
	 *
	 * @param \GdImage $canvas Canvas.
	 * @param int      $view   View index.
	 */
	private function paint_backdrop( $canvas, int $view ): void {
		$top    = $this->hex( $canvas, 0 === $view ? '#F6F1E8' : '#F1EBE0' );
		$bottom = $this->hex( $canvas, 0 === $view ? '#E5DCCC' : '#DED4C2' );

		for ( $y = 0; $y < self::CANVAS; $y++ ) {
			$ratio = $y / self::CANVAS;
			$color = $this->blend( $canvas, $top, $bottom, $ratio );

			imageline( $canvas, 0, $y, self::CANVAS, $y, $color );
		}

		// Workbench horizon: a soft band rather than a hard line, so the
		// backdrop reads as a surface receding under the object.
		$horizon = (int) ( self::CANVAS * 0.72 );

		for ( $offset = -18; $offset <= 18; $offset++ ) {
			$fade  = 1 - ( abs( $offset ) / 19 );
			$shade = imagecolorallocatealpha( $canvas, 178, 165, 142, (int) ( 127 - ( 34 * $fade ) ) );

			imageline( $canvas, 0, $horizon + $offset, self::CANVAS, $horizon + $offset, $shade );
		}
	}

	/**
	 * Builds the material texture tile.
	 *
	 * @param string $material Material slug.
	 * @param string $shape    Shape family.
	 * @param int    $seed     Random seed.
	 *
	 * @return \GdImage|false
	 */
	private function build_texture( string $material, string $shape, int $seed ) {
		$palette = self::PALETTES[ $material ] ?? self::PALETTES['cattle-bone'];

		$texture = imagecreatetruecolor( self::TEXTURE, self::TEXTURE );

		if ( false === $texture ) {
			return false;
		}

		$base  = $this->rgb( $palette[0] );
		$light = $this->rgb( $palette[1] );
		$dark  = $this->rgb( $palette[2] );
		$grain = $this->rgb( $palette[3] );

		$stripe_scale = 'stabilized-wood' === $material || 'hardwood' === $material ? 0.16 : 0.05;
		$band_scale   = str_contains( $material, 'horn' ) ? 0.02 : 0.01;

		for ( $y = 0; $y < self::TEXTURE; $y++ ) {
			for ( $x = 0; $x < self::TEXTURE; $x++ ) {
				// Layered sine waves stand in for grain and banding; the noise
				// term breaks up the regularity so it does not read as CGI.
				$wave  = sin( ( $x * $stripe_scale ) + sin( $y * 0.03 ) * 2.2 );
				$band  = sin( ( $y * $band_scale ) + ( $seed % 7 ) );
				$noise = ( mt_rand( 0, 100 ) - 50 ) / 900; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Seeded by render() so the same SKU always yields the same texture; wp_rand() cannot be seeded.

				$mix = 0.5 + ( $wave * 0.28 ) + ( $band * 0.16 ) + $noise;
				$mix = max( 0.0, min( 1.0, $mix ) );

				$target = $mix > 0.5
					? $this->mix_rgb( $base, $light, ( $mix - 0.5 ) * 2 )
					: $this->mix_rgb( $dark, $base, $mix * 2 );

				// Speckle: bone and horn both carry fine mineral flecks.
				if ( mt_rand( 0, 220 ) === 1 ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Seeded; see above.
					$target = $this->mix_rgb( $target, $grain, 0.55 );
				}

				imagesetpixel(
					$texture,
					$x,
					$y,
					imagecolorallocate( $texture, $target[0], $target[1], $target[2] )
				);
			}
		}

		if ( 'jigged' === $shape ) {
			$this->add_jigging( $texture, $dark );
		}

		return $texture;
	}

	/**
	 * Adds a jigged groove pattern to a texture.
	 *
	 * @param \GdImage                 $texture Texture image.
	 * @param array{0:int,1:int,2:int} $dark Groove colour.
	 */
	private function add_jigging( $texture, array $dark ): void {
		$colour = imagecolorallocatealpha( $texture, $dark[0], $dark[1], $dark[2], 60 );

		for ( $i = 0; $i < 26; $i++ ) {
			$x = (int) ( ( $i * self::TEXTURE ) / 26 );

			imagefilledrectangle( $texture, $x, 0, $x + 2, self::TEXTURE, $colour );
		}
	}

	/**
	 * Places the textured object onto the canvas.
	 *
	 * @param \GdImage $canvas  Canvas.
	 * @param \GdImage $texture Texture tile.
	 * @param string   $shape   Shape family.
	 * @param int      $view    View index.
	 */
	private function place_object( $canvas, $texture, string $shape, int $view ): void {
		$centre = (int) ( self::CANVAS / 2 );

		[ $width, $height ] = match ( $shape ) {
			'blank'  => [ 260, 780 ],
			'horn'   => [ 520, 700 ],
			'comb'   => [ 640, 320 ],
			'round'  => [ 600, 600 ],
			'bead'   => [ 420, 420 ],
			default  => [ 420, 760 ],
		};

		if ( 1 === $view ) {
			$width  = (int) ( $width * 0.86 );
			$height = (int) ( $height * 0.86 );
		}

		if ( 2 === $view ) {
			$width  = (int) ( $width * 1.05 );
			$height = (int) ( $height * 0.7 );
		}

		$x = $centre - (int) ( $width / 2 );
		$y = $centre - (int) ( $height / 2 ) + ( 1 === $view ? 30 : 0 );

		// Contact shadow: a stack of translucent ellipses fakes a blur without
		// a convolution pass, which would cost more than the whole render.
		for ( $step = 10; $step >= 1; $step-- ) {
			$shadow = imagecolorallocatealpha( $canvas, 96, 82, 62, 118 - ( 10 - $step ) );

			imagefilledellipse(
				$canvas,
				$centre + 10,
				$y + $height - 4,
				(int) ( $width * ( 0.72 + ( 0.06 * $step ) ) ),
				24 + ( 5 * $step ),
				$shadow
			);
		}

		$object = imagecreatetruecolor( $width, $height );

		if ( false === $object ) {
			return;
		}

		imagecopyresampled( $object, $texture, 0, 0, 0, 0, $width, $height, self::TEXTURE, self::TEXTURE );

		$mask_shape = match ( $shape ) {
			'round', 'bead' => 'round',
			'horn'          => 'horn',
			'comb'          => 'comb',
			default         => 'rounded',
		};

		$this->apply_shape( $canvas, $object, $x, $y, $width, $height, $mask_shape );

		imagedestroy( $object );
	}

	/**
	 * Copies the object onto the canvas within a rounded or circular mask.
	 *
	 * @param \GdImage $canvas Canvas.
	 * @param \GdImage $object Object image.
	 * @param int      $x      Left offset.
	 * @param int      $y      Top offset.
	 * @param int      $width  Object width.
	 * @param int      $height Object height.
	 * @param string   $mask   `rounded` or `round`.
	 */
	private function apply_shape( $canvas, $object, int $x, int $y, int $width, int $height, string $mask ): void {
		$radius = 'round' === $mask ? (int) ( min( $width, $height ) / 2 ) : (int) ( min( $width, $height ) * 0.18 );

		// The specular sheen is applied while the pixels are copied, so it is
		// clipped to the silhouette. Drawing it onto the canvas afterwards
		// leaves a visible rectangle over non-rectangular shapes.
		$band = max( 1, (int) ( $height * 0.34 ) );

		for ( $oy = 0; $oy < $height; $oy++ ) {
			$sheen = $oy < $band ? ( 1 - ( $oy / $band ) ) * 0.24 : 0.0;

			for ( $ox = 0; $ox < $width; $ox++ ) {
				if ( ! $this->inside_mask( $ox, $oy, $width, $height, $radius, $mask ) ) {
					continue;
				}

				$colour = imagecolorat( $object, $ox, $oy );

				if ( $sheen > 0.0 ) {
					// Taper the sheen away from the centre so it reads as a
					// light source rather than a flat overlay.
					$across = 1 - min( 1.0, abs( ( $ox / max( 1, $width ) ) - 0.42 ) * 2.4 );

					if ( $across > 0 ) {
						$strength = $sheen * $across;

						$red   = (int) min( 255, ( ( $colour >> 16 ) & 0xFF ) + ( ( 255 - ( ( $colour >> 16 ) & 0xFF ) ) * $strength ) );
						$green = (int) min( 255, ( ( $colour >> 8 ) & 0xFF ) + ( ( 252 - ( ( $colour >> 8 ) & 0xFF ) ) * $strength ) );
						$blue  = (int) min( 255, ( $colour & 0xFF ) + ( ( 244 - ( $colour & 0xFF ) ) * $strength ) );

						$colour = ( $red << 16 ) | ( $green << 8 ) | $blue;
					}
				}

				imagesetpixel( $canvas, $x + $ox, $y + $oy, $colour );
			}
		}
	}

	/**
	 * Whether a pixel falls inside the mask.
	 *
	 * @param int    $x      Pixel x.
	 * @param int    $y      Pixel y.
	 * @param int    $width  Object width.
	 * @param int    $height Object height.
	 * @param int    $radius Corner radius.
	 * @param string $mask   Mask type.
	 */
	private function inside_mask( int $x, int $y, int $width, int $height, int $radius, string $mask ): bool {
		if ( 'horn' === $mask ) {
			// A horn is a tapered, curved cone: the half-width shrinks toward
			// the tip and the centre line follows a shallow arc.
			$progress   = $y / max( 1, $height );
			$half_width = ( $width / 2 ) * ( 1 - ( 0.92 * $progress ** 1.25 ) );
			$centre     = ( $width / 2 ) + ( sin( $progress * 1.5 ) * $width * 0.24 );

			if ( $half_width <= 0.5 || abs( $x - $centre ) > $half_width ) {
				return false;
			}

			// Round the mouth of the horn: without an elliptical cap the top
			// edge is a hard diagonal and the shape reads as a paper cone.
			$cap = $height * 0.07;

			if ( $y < $cap ) {
				$dx = ( $x - $centre ) / max( 1.0, $half_width );
				$dy = ( $y - $cap ) / max( 1.0, $cap );

				return ( $dx ** 2 ) + ( $dy ** 2 ) <= 1.0;
			}

			return true;
		}

		if ( 'comb' === $mask ) {
			// Solid spine across the top, teeth cut through the lower portion.
			$spine = (int) ( $height * 0.42 );

			if ( $y <= $spine ) {
				return $x >= 0 && $x <= $width;
			}

			$tooth_pitch = max( 6, (int) ( $width / 26 ) );
			$in_tooth    = ( $x % $tooth_pitch ) < (int) ( $tooth_pitch * 0.55 );

			// Teeth shorten toward both ends, the way a hand-cut comb is shaped.
			$edge_fade = 1 - ( abs( ( $x / max( 1, $width ) ) - 0.5 ) * 0.45 );
			$tooth_end = $spine + ( ( $height - $spine ) * $edge_fade );

			return $in_tooth && $y <= $tooth_end;
		}

		if ( 'round' === $mask ) {
			$cx = $width / 2;
			$cy = $height / 2;

			return ( ( ( $x - $cx ) ** 2 ) + ( ( $y - $cy ) ** 2 ) ) <= ( $radius ** 2 );
		}

		$corners = [
			[ $radius, $radius ],
			[ $width - $radius, $radius ],
			[ $radius, $height - $radius ],
			[ $width - $radius, $height - $radius ],
		];

		foreach ( $corners as $index => $corner ) {
			$in_x = ( 0 === $index % 2 ) ? $x < $corner[0] : $x > $corner[0];
			$in_y = ( $index < 2 ) ? $y < $corner[1] : $y > $corner[1];

			if ( $in_x && $in_y ) {
				return ( ( ( $x - $corner[0] ) ** 2 ) + ( ( $y - $corner[1] ) ** 2 ) ) <= ( $radius ** 2 );
			}
		}

		return true;
	}

	/**
	 * Darkens the frame edges slightly.
	 *
	 * @param \GdImage $canvas Canvas.
	 */
	private function paint_vignette( $canvas ): void {
		// Rendered as a small alpha mask and resampled up: a full-resolution
		// radial loop would be 1.4M iterations per image, and drawing
		// concentric shapes leaves visible banding.
		$size    = 72;
		$overlay = imagecreatetruecolor( $size, $size );

		if ( false === $overlay ) {
			return;
		}

		imagealphablending( $overlay, false );
		imagesavealpha( $overlay, true );

		$centre = ( $size - 1 ) / 2;
		$max    = sqrt( 2 * ( $centre ** 2 ) );

		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				$distance = sqrt( ( ( $x - $centre ) ** 2 ) + ( ( $y - $centre ) ** 2 ) ) / $max;

				// No darkening across the middle 55%, then a smooth falloff.
				$strength = max( 0.0, ( $distance - 0.55 ) / 0.45 );
				$alpha    = (int) round( 127 - ( $strength ** 1.6 ) * 46 );

				imagesetpixel(
					$overlay,
					$x,
					$y,
					imagecolorallocatealpha( $overlay, 68, 57, 44, min( 127, max( 0, $alpha ) ) )
				);
			}
		}

		imagealphablending( $canvas, true );
		imagecopyresampled( $canvas, $overlay, 0, 0, 0, 0, self::CANVAS, self::CANVAS, $size, $size );
		imagedestroy( $overlay );
	}

	/**
	 * Allocates a colour from a hex string.
	 *
	 * @param \GdImage $image Image.
	 * @param string   $hex   Hex colour.
	 */
	private function hex( $image, string $hex ): int {
		[ $r, $g, $b ] = $this->rgb( $hex );

		return (int) imagecolorallocate( $image, $r, $g, $b );
	}

	/**
	 * Converts a hex string to an RGB triplet.
	 *
	 * @param string $hex Hex colour.
	 *
	 * @return array{0:int,1:int,2:int}
	 */
	private function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			$hex = 'CCCCCC';
		}

		return [
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		];
	}

	/**
	 * Blends two allocated colours.
	 *
	 * @param \GdImage $image Image.
	 * @param int      $from  Source colour index.
	 * @param int      $to    Target colour index.
	 * @param float    $ratio Blend ratio 0..1.
	 */
	private function blend( $image, int $from, int $to, float $ratio ): int {
		$a = imagecolorsforindex( $image, $from );
		$b = imagecolorsforindex( $image, $to );

		return (int) imagecolorallocate(
			$image,
			(int) ( $a['red'] + ( $b['red'] - $a['red'] ) * $ratio ),
			(int) ( $a['green'] + ( $b['green'] - $a['green'] ) * $ratio ),
			(int) ( $a['blue'] + ( $b['blue'] - $a['blue'] ) * $ratio )
		);
	}

	/**
	 * Mixes two RGB triplets.
	 *
	 * @param array{0:int,1:int,2:int} $a     First colour.
	 * @param array{0:int,1:int,2:int} $b     Second colour.
	 * @param float                    $ratio Blend ratio 0..1.
	 *
	 * @return array{0:int,1:int,2:int}
	 */
	private function mix_rgb( array $a, array $b, float $ratio ): array {
		$ratio = max( 0.0, min( 1.0, $ratio ) );

		return [
			(int) max( 0, min( 255, $a[0] + ( $b[0] - $a[0] ) * $ratio ) ),
			(int) max( 0, min( 255, $a[1] + ( $b[1] - $a[1] ) * $ratio ) ),
			(int) max( 0, min( 255, $a[2] + ( $b[2] - $a[2] ) * $ratio ) ),
		];
	}
}
