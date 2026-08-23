<?php
/**
 * The home page hero banner.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Store;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Support\Options;
use WP_Error;

/**
 * Owns which media-library attachment is the home page banner.
 *
 * This logic used to live inside the `wp bhc setup hero` closure in the CLI
 * registrar — ninety lines of validation, temp-file staging and option writing
 * that only a WP-CLI session could reach. A use case does not belong inside the
 * adapter that invokes it: the settings screen writes the same option, a future
 * REST route may want the same import, and none of it was testable in
 * isolation. The CLI command is now a thin translation layer over this class.
 */
final class HeroBanner {

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * The attachment currently set as the banner.
	 *
	 * @return int Attachment id, or 0 when none is set or it was deleted.
	 */
	public function current(): int {
		$id = (int) $this->options->get( 'hero_image_id', 0 );

		return $id > 0 && wp_attachment_is_image( $id ) ? $id : 0;
	}

	/**
	 * Points the banner at an attachment already in the media library.
	 *
	 * @param int $attachment_id Attachment id. 0 clears the banner.
	 *
	 * @return true|WP_Error
	 */
	public function set( int $attachment_id ) {
		if ( $attachment_id > 0 && ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'bhc_not_an_image',
				sprintf(
					/* translators: %d: attachment id. */
					__( '%d is not an image attachment.', 'bhc-commerce-core' ),
					$attachment_id
				)
			);
		}

		$settings                  = $this->options->all();
		$settings['hero_image_id'] = $attachment_id;

		$this->options->save( $settings );

		return true;
	}

	/**
	 * Imports a local image file into the media library and sets it.
	 *
	 * @param string $path Readable path to the image.
	 *
	 * @return int|WP_Error The new attachment id.
	 */
	public function import( string $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error(
				'bhc_unreadable',
				sprintf(
					/* translators: %s: file path. */
					__( 'Cannot read %s', 'bhc-commerce-core' ),
					$path
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Copied into a temp file first: media_handle_sideload() *moves* what
		// it is given, and importing straight from the caller's path would
		// delete their original.
		$temp = wp_tempnam( basename( $path ) );

		if ( ! $temp || ! copy( $path, $temp ) ) {
			return new WP_Error( 'bhc_stage_failed', __( 'Could not stage the file for import.', 'bhc-commerce-core' ) );
		}

		$attachment = media_handle_sideload(
			[
				'name'     => basename( $path ),
				'tmp_name' => $temp,
			],
			0,
			sprintf(
				/* translators: %s: store name. */
				__( '%s home page banner', 'bhc-commerce-core' ),
				get_bloginfo( 'name' )
			)
		);

		if ( is_wp_error( $attachment ) ) {
			if ( file_exists( $temp ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing our own temp copy after a failed import.
				unlink( $temp );
			}

			return $attachment;
		}

		$result = $this->set( (int) $attachment );

		return true === $result ? (int) $attachment : $result;
	}
}
