<?php
/**
 * Order screen operations panel.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Order\Admin;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;
use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Order\OrderMeta;
use BoneHornCrafts\Core\Security\Capabilities;
use BoneHornCrafts\Core\Security\Sanitizer;
use WC_Order;

/**
 * Adds the workshop/shipping panel to the order screen.
 *
 * Registered against whichever screen id is active, so the panel appears on
 * both the legacy post editor and the HPOS order table screen.
 */
final class OrderOperationsMetaBox implements HookableInterface {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 30, 2 );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save' ], 20, 2 );
		add_action( 'save_post_shop_order', [ $this, 'save' ], 20, 1 );

		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_list_column' ], 20 );
		add_filter( 'woocommerce_shop_order_list_table_columns', [ $this, 'add_list_column' ], 20 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_list_column' ], 10, 2 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ $this, 'render_list_column' ], 10, 2 );
	}

	/**
	 * Registers the meta box on the correct screen.
	 */
	public function register_meta_box(): void {
		$screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'bhc-order-operations',
			__( 'Bone Horn Crafts — workshop &amp; export', 'bhc-commerce-core' ),
			[ $this, 'render' ],
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Renders the panel.
	 *
	 * @param mixed $post_or_order Post or order object.
	 */
	public function render( $post_or_order = null ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( is_object( $post_or_order ) ? ( $post_or_order->ID ?? 0 ) : 0 );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		wp_nonce_field( 'bhc_save_order_operations', 'bhc_order_operations_nonce' );

		$export_type = OrderMeta::export_type( $order );
		$batches     = OrderMeta::batch_references( $order );
		$summary     = OrderMeta::hsn_summary( $order );

		echo '<p><label for="bhc_export_type"><strong>' . esc_html__( 'Invoice treatment', 'bhc-commerce-core' ) . '</strong></label><br />';
		echo '<select id="bhc_export_type" name="bhc_export_type" class="widefat">';

		foreach ( [ OrderMeta::EXPORT_ZERO_RATED, OrderMeta::DOMESTIC_GST ] as $type ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $type ),
				selected( $export_type, $type, false ),
				esc_html( OrderMeta::export_type_label( $type ) )
			);
		}

		echo '</select></p>';

		printf(
			'<p><label for="bhc_is_wholesale"><input type="checkbox" id="bhc_is_wholesale" name="bhc_is_wholesale" value="yes" %s /> %s</label></p>',
			checked( OrderMeta::is_wholesale( $order ), true, false ),
			esc_html__( 'Wholesale order', 'bhc-commerce-core' )
		);

		printf(
			'<p><label for="bhc_packing_notes"><strong>%1$s</strong></label><br /><textarea id="bhc_packing_notes" name="bhc_packing_notes" rows="4" class="widefat" placeholder="%2$s">%3$s</textarea></p>',
			esc_html__( 'Packing notes', 'bhc-commerce-core' ),
			esc_attr__( 'Pair matching, padding, customs paperwork.', 'bhc-commerce-core' ),
			esc_textarea( OrderMeta::packing_notes( $order ) )
		);

		if ( [] !== $batches ) {
			echo '<p><strong>' . esc_html__( 'Material lots', 'bhc-commerce-core' ) . '</strong><br />' . esc_html( implode( ', ', $batches ) ) . '</p>';
		}

		if ( [] !== $summary ) {
			echo '<p><strong>' . esc_html__( 'HSN summary', 'bhc-commerce-core' ) . '</strong></p><ul style="margin-left:16px;list-style:disc">';

			foreach ( $summary as $hsn => $row ) {
				printf(
					'<li>%1$s — %2$d %3$s (%4$s)</li>',
					esc_html( (string) $hsn ),
					(int) ( $row['qty'] ?? 0 ),
					esc_html__( 'units', 'bhc-commerce-core' ),
					wp_kses_post( wc_price( (float) ( $row['value'] ?? 0 ) ) )
				);
			}

			echo '</ul>';
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Reference data captured when the order was placed. It does not calculate tax.', 'bhc-commerce-core' )
		);
	}

	/**
	 * Saves the panel fields.
	 *
	 * @param int      $order_id Order id.
	 * @param WC_Order $order    Order object (HPOS passes it, posts do not).
	 */
	public function save( $order_id, $order = null ): void {
		$order_id = absint( $order_id );

		if ( $order_id <= 0 || ! current_user_can( 'edit_shop_order', $order_id ) && ! Capabilities::can_manage() ) {
			return;
		}

		$nonce = isset( $_POST['bhc_order_operations_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['bhc_order_operations_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'bhc_save_order_operations' ) ) {
			return;
		}

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$export_type = Sanitizer::key( wp_unslash( $_POST['bhc_export_type'] ?? '' ) );

		if ( in_array( $export_type, [ OrderMeta::EXPORT_ZERO_RATED, OrderMeta::DOMESTIC_GST ], true ) ) {
			$order->update_meta_data( OrderMeta::EXPORT_TYPE, $export_type );
		}

		$order->update_meta_data( OrderMeta::IS_WHOLESALE, isset( $_POST['bhc_is_wholesale'] ) ? 'yes' : 'no' );
		$order->update_meta_data(
			OrderMeta::PACKING_NOTES,
			Sanitizer::rich_text( wp_unslash( $_POST['bhc_packing_notes'] ?? '' ) )
		);

		$order->save();
	}

	/**
	 * Adds the export column to the orders list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public function add_list_column( array $columns ): array {
		$new = [];

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'order_status' === $key ) {
				$new['bhc_export'] = __( 'Shipment', 'bhc-commerce-core' );
			}
		}

		if ( ! isset( $new['bhc_export'] ) ) {
			$new['bhc_export'] = __( 'Shipment', 'bhc-commerce-core' );
		}

		return $new;
	}

	/**
	 * Renders the export column.
	 *
	 * @param string          $column        Column key.
	 * @param int|WC_Order    $post_or_order Post id or order object.
	 */
	public function render_list_column( $column, $post_or_order = null ): void {
		if ( 'bhc_export' !== $column ) {
			return;
		}

		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( absint( $post_or_order ) );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$labels = [];

		$labels[] = OrderMeta::DOMESTIC_GST === OrderMeta::export_type( $order )
			? __( 'Domestic', 'bhc-commerce-core' )
			: __( 'Export', 'bhc-commerce-core' );

		if ( OrderMeta::is_wholesale( $order ) ) {
			$labels[] = __( 'Wholesale', 'bhc-commerce-core' );
		}

		echo esc_html( implode( ' · ', $labels ) );
	}
}
