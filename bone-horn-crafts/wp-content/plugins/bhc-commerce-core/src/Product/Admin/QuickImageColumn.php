<?php
/**
 * Set a product's image from the products list table.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Cache\CacheManager;
use BoneHornCrafts\Core\Cache\Invalidator;
use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use WC_Product;

/**
 * Lets an administrator set or clear a product's featured image without
 * opening the product editor.
 *
 * A catalogue imported from a spreadsheet arrives with photography missing or
 * wrong on a long tail of products, and fixing that one product-edit screen at
 * a time is the slowest possible way to do it: load the editor, scroll to the
 * image box, open the media modal, choose, update, wait for a full save of
 * every field on the product, go back. This replaces that with two clicks in
 * the list table.
 *
 * The write goes through `WC_Product::set_image_id()` and `save()` rather than
 * `update_post_meta()`, so WooCommerce owns the invalidation of its own lookup
 * tables and object cache exactly as it would after an editor save.
 */
final class QuickImageColumn implements HookableInterface {

	/**
	 * AJAX action name.
	 */
	public const ACTION = 'bhc_set_product_image';

	/**
	 * Nonce action.
	 */
	private const NONCE = 'bhc_set_product_image';

	/**
	 * Constructor.
	 *
	 * @param CacheManager    $cache  Cache manager.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( private CacheManager $cache, private LoggerInterface $logger ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// Priority 20 so this appends inside the cell WooCommerce has already
		// filled with the thumbnail, rather than replacing its column.
		add_action( 'manage_product_posts_custom_column', [ $this, 'render_control' ], 20, 2 );

		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Renders the trigger inside the thumbnail column.
	 *
	 * @param string $column     Column key.
	 * @param int    $product_id Product id.
	 */
	public function render_control( string $column, $product_id ): void {
		$product_id = (int) $product_id;

		if ( 'thumb' !== $column || ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		printf(
			'<button type="button" class="button-link bhc-quick-image" data-bhc-quick-image="%1$s" aria-label="%2$s">%3$s</button>',
			esc_attr( (string) $product_id ),
			esc_attr(
				sprintf(
					/* translators: %s: product name. */
					__( 'Change the image for %s', 'bhc-commerce-core' ),
					get_the_title( $product_id )
				)
			),
			esc_html__( 'Change', 'bhc-commerce-core' )
		);
	}

	/**
	 * Data handed to the browser.
	 *
	 * @return array<string, mixed>
	 */
	public function script_data(): array {
		return [
			'action'  => self::ACTION,
			'nonce'   => wp_create_nonce( self::NONCE ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => [
				'frameTitle'  => __( 'Product image', 'bhc-commerce-core' ),
				'frameButton' => __( 'Use this image', 'bhc-commerce-core' ),
				'saving'      => __( 'Saving…', 'bhc-commerce-core' ),
				'failed'      => __( 'Could not save that image.', 'bhc-commerce-core' ),
				'noHandler'   => __( 'The site did not recognise the save request. Reload the page; if it persists the plugin needs reactivating.', 'bhc-commerce-core' ),
				'expired'     => __( 'Your session expired. Reload the page and try again.', 'bhc-commerce-core' ),
			],
		];
	}

	/**
	 * Handles the AJAX write.
	 *
	 * Every failure path returns a distinct message: an administrator who is
	 * told only "failed" has no way to tell a permissions problem from a
	 * deleted attachment.
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		$product_id    = isset( $_POST['product'] ) ? absint( wp_unslash( $_POST['product'] ) ) : 0;
		$attachment_id = isset( $_POST['attachment'] ) ? absint( wp_unslash( $_POST['attachment'] ) ) : 0;

		if ( $product_id <= 0 || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You cannot edit that product.', 'bhc-commerce-core' ) ], 403 );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			wp_send_json_error( [ 'message' => __( 'That product no longer exists.', 'bhc-commerce-core' ) ], 404 );
		}

		// 0 is a deliberate "clear the image", so only a non-zero id is
		// validated as an attachment.
		if ( $attachment_id > 0 && ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => __( 'That file is not an image.', 'bhc-commerce-core' ) ], 400 );
		}

		$previous = (int) $product->get_image_id();

		$product->set_image_id( $attachment_id > 0 ? $attachment_id : '' );
		$product->save();

		foreach ( Invalidator::ALL_GROUPS as $group ) {
			$this->cache->flush_group( $group );
		}

		$this->logger->info(
			'product.image_set',
			[
				'product'  => $product_id,
				'image'    => $attachment_id,
				'previous' => $previous,
			]
		);

		wp_send_json_success(
			[
				'productId' => $product_id,
				'imageId'   => $attachment_id,
				'thumbnail' => $this->thumbnail_html( $product_id ),
			]
		);
	}

	/**
	 * Renders the same thumbnail markup WooCommerce puts in the column, so the
	 * cell can be swapped without a page reload and without drifting from what
	 * a refresh would show.
	 *
	 * @param int $product_id Product id.
	 */
	private function thumbnail_html( int $product_id ): string {
		$thumbnail = get_the_post_thumbnail( $product_id, 'thumbnail' );

		if ( is_string( $thumbnail ) && '' !== $thumbnail ) {
			return $thumbnail;
		}

		return sprintf(
			'<img src="%s" alt="%s" width="48" height="48" />',
			esc_url( (string) wc_placeholder_img_src() ),
			esc_attr__( 'No image', 'bhc-commerce-core' )
		);
	}
}
