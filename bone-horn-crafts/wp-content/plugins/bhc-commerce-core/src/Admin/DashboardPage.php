<?php
/**
 * Operations dashboard screen.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Jobs\Scheduler;
use BoneHornCrafts\Core\Order\OrderRepository;
use BoneHornCrafts\Core\Product\ProductRepository;
use BoneHornCrafts\Core\Security\Capabilities;
use WC_Order;
use WC_Product;

/**
 * The screen a shop manager opens first each morning.
 *
 * Everything shown is either cached or a bounded query — opening the dashboard
 * must never be the slowest request on the site.
 */
final class DashboardPage {

	/**
	 * Constructor.
	 *
	 * @param ProductRepository $products  Product read model.
	 * @param OrderRepository   $orders    Order read model.
	 * @param HealthReport      $health    Health report.
	 * @param Scheduler         $scheduler Job scheduler.
	 */
	public function __construct(
		private ProductRepository $products,
		private OrderRepository $orders,
		private HealthReport $health,
		private Scheduler $scheduler
	) {}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bhc-commerce-core' ), 403 );
		}

		$report     = $this->health->build();
		$low_stock  = $this->products->hydrate( $this->products->low_stock_ids( 8 ) );
		$recent     = $this->orders->recent( 5 );
		$processing = $this->orders->count_by_status( 'processing' );
		$revenue    = $this->orders->revenue_last_days( 30 );

		echo '<div class="wrap bhc-admin">';
		printf( '<h1>%s</h1>', esc_html__( 'Bone Horn Crafts — operations', 'bhc-commerce-core' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Catalogue, fulfilment and background job status for the storefront.', 'bhc-commerce-core' )
		);

		echo '<div class="bhc-admin__tiles">';

		$this->tile(
			__( 'Published products', 'bhc-commerce-core' ),
			(string) $report['catalogue']['published_products'],
			admin_url( 'edit.php?post_type=product' )
		);

		$this->tile(
			__( 'Orders awaiting fulfilment', 'bhc-commerce-core' ),
			(string) $processing,
			admin_url( 'admin.php?page=wc-orders&status=wc-processing' )
		);

		$this->tile(
			__( 'Revenue (30 days)', 'bhc-commerce-core' ),
			wp_strip_all_tags( (string) wc_price( $revenue ) ),
			admin_url( 'admin.php?page=wc-admin&path=/analytics/revenue' )
		);

		$this->tile(
			__( 'Saved to wishlists', 'bhc-commerce-core' ),
			(string) $report['catalogue']['wishlist_rows'],
			''
		);

		$this->tile(
			__( 'Object cache', 'bhc-commerce-core' ),
			$report['cache']['persistent']
				? ( $report['cache']['redis'] ? __( 'Redis', 'bhc-commerce-core' ) : __( 'Persistent', 'bhc-commerce-core' ) )
				: __( 'Transients', 'bhc-commerce-core' ),
			admin_url( 'admin.php?page=' . AdminMenu::HEALTH_SLUG )
		);

		$this->tile(
			__( 'Plugin version', 'bhc-commerce-core' ),
			(string) $report['plugin']['version'],
			''
		);

		echo '</div>';

		echo '<div class="bhc-admin__columns">';

		echo '<section class="bhc-admin__panel"><h2>' . esc_html__( 'Low stock', 'bhc-commerce-core' ) . '</h2>';

		if ( [] === $low_stock ) {
			echo '<p>' . esc_html__( 'Nothing is running low. ', 'bhc-commerce-core' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Product', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'SKU', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Stock', 'bhc-commerce-core' ) . '</th></tr></thead><tbody>';

			foreach ( $low_stock as $product ) {
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				// A variation has no edit screen of its own; WordPress returns
				// nothing for get_edit_post_link() on one, which would render a
				// dead link. Send the operator to the parent, where the
				// variation's stock field actually lives.
				$edit_id = $product->is_type( 'variation' ) && $product->get_parent_id() > 0
					? (int) $product->get_parent_id()
					: (int) $product->get_id();

				printf(
					'<tr><td><a href="%1$s">%2$s</a></td><td><code>%3$s</code></td><td>%4$s</td></tr>',
					esc_url( (string) get_edit_post_link( $edit_id ) ),
					esc_html( $product->get_name() ),
					esc_html( $product->get_sku() ),
					esc_html( (string) $product->get_stock_quantity() )
				);
			}

			echo '</tbody></table>';
		}

		echo '</section>';

		echo '<section class="bhc-admin__panel"><h2>' . esc_html__( 'Recent orders', 'bhc-commerce-core' ) . '</h2>';

		if ( [] === $recent ) {
			echo '<p>' . esc_html__( 'No orders yet.', 'bhc-commerce-core' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Order', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Destination', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Total', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Status', 'bhc-commerce-core' ) . '</th></tr></thead><tbody>';

			foreach ( $recent as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}

				printf(
					'<tr><td><a href="%1$s">#%2$s</a></td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
					esc_url( (string) $order->get_edit_order_url() ),
					esc_html( (string) $order->get_order_number() ),
					esc_html( (string) ( $order->get_shipping_country() ?: $order->get_billing_country() ) ),
					wp_kses_post( (string) $order->get_formatted_order_total() ),
					esc_html( wc_get_order_status_name( $order->get_status() ) )
				);
			}

			echo '</tbody></table>';
		}

		echo '</section>';

		echo '<section class="bhc-admin__panel"><h2>' . esc_html__( 'Background jobs', 'bhc-commerce-core' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Job', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Next run', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Pending', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Last completed', 'bhc-commerce-core' ) . '</th></tr></thead><tbody>';

		foreach ( $this->scheduler->status() as $job ) {
			printf(
				'<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$d</td><td>%4$s</td></tr>',
				esc_html( (string) $job['hook'] ),
				esc_html( (string) $job['next_run'] ),
				(int) $job['pending'],
				esc_html( (string) $job['last_completed'] )
			);
		}

		echo '</tbody></table>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Run any job immediately with: wp bhc products sync --job=index', 'bhc-commerce-core' )
		);
		echo '</section>';

		echo '</div></div>';
	}

	/**
	 * Renders a single metric tile.
	 *
	 * @param string $label Tile label.
	 * @param string $value Tile value.
	 * @param string $url   Optional link.
	 */
	private function tile( string $label, string $value, string $url ): void {
		$inner = sprintf(
			'<span class="bhc-admin__tile-value">%1$s</span><span class="bhc-admin__tile-label">%2$s</span>',
			esc_html( $value ),
			esc_html( $label )
		);

		if ( '' === $url ) {
			printf( '<div class="bhc-admin__tile">%s</div>', $inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.

			return;
		}

		printf(
			'<a class="bhc-admin__tile" href="%1$s">%2$s</a>',
			esc_url( $url ),
			$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);
	}
}
