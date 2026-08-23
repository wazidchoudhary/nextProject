<?php
/**
 * Demo gateway guard.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Payments;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Keeps the demo payment methods out of a production store.
 *
 * The seeder enables WooCommerce's two offline gateways so the purchase flow is
 * demonstrable end to end — a fresh WooCommerce install has every gateway
 * disabled and checkout fails at the last step with "Invalid payment method".
 * Both accept an order without collecting a penny, which is exactly right for a
 * demo and a serious problem the moment the store is live.
 *
 * Relying on somebody remembering to turn them off is not a control. This
 * removes them from checkout whenever the environment is production and they
 * still carry the demo titles the seeder gave them, and warns in the admin so
 * the reason is visible rather than mysterious.
 *
 * Deliberately keyed on the demo title rather than on the gateway id: a store
 * that has genuinely configured bank transfer with its own account details and
 * its own wording should keep it. The marker is the seeded copy, not the
 * method.
 */
final class GatewayGuard implements HookableInterface {

	/**
	 * Gateway ids the seeder enables, and the titles it gives them.
	 */
	private const DEMO_TITLES = [
		'cod'  => 'Pay on invoice (demo)',
		'bacs' => 'Bank transfer (demo)',
	];

	/**
	 * Constructor.
	 *
	 * @param PayPalCredentials $paypal PayPal credential bridge.
	 */
	public function __construct( private PayPalCredentials $paypal ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_demo_gateways' ] );
		add_action( 'admin_notices', [ $this, 'render_notice' ] );
	}

	/**
	 * Drops demo gateways from checkout on production.
	 *
	 * @param array<string, \WC_Payment_Gateway> $gateways Available gateways.
	 *
	 * @return array<string, \WC_Payment_Gateway>
	 */
	public function remove_demo_gateways( array $gateways ): array {
		if ( ! $this->is_production() ) {
			return $gateways;
		}

		foreach ( $this->demo_gateway_ids() as $id ) {
			unset( $gateways[ $id ] );
		}

		return $gateways;
	}

	/**
	 * Warns in the admin when demo gateways are still enabled.
	 */
	public function render_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$demo = $this->demo_gateway_ids();

		if ( [] === $demo ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s</p></div>',
			esc_attr( $this->is_production() ? 'error' : 'warning' ),
			esc_html__( 'Demo payment methods are enabled.', 'bhc-commerce-core' ),
			esc_html(
				$this->is_production()
					? __( 'They accept an order without taking payment, and are hidden from checkout because this site is in production. Disable them under WooCommerce → Settings → Payments.', 'bhc-commerce-core' )
					: __( 'They accept an order without taking payment. Disable them under WooCommerce → Settings → Payments before this store goes live.', 'bhc-commerce-core' )
			)
		);
	}

	/**
	 * Ids of gateways still carrying their seeded demo title.
	 *
	 * @return string[]
	 */
	private function demo_gateway_ids(): array {
		if ( ! function_exists( 'WC' ) || null === WC()->payment_gateways() ) {
			return [];
		}

		$found = [];

		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			if ( ! isset( self::DEMO_TITLES[ $id ] ) || 'yes' !== $gateway->enabled ) {
				continue;
			}

			if ( self::DEMO_TITLES[ $id ] === $gateway->get_title() ) {
				$found[] = (string) $id;
			}
		}

		return $found;
	}

	/**
	 * Whether this site is production.
	 */
	private function is_production(): bool {
		return 'production' === wp_get_environment_type();
	}
}
