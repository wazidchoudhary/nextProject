<?php
/**
 * Payments module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Payments;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Support\Context;

/**
 * Registers gateway credential handling and the demo gateway guard.
 *
 * Loads everywhere rather than on a context, which is unusual in this plugin
 * and deliberate: the credential filter has to be in place for any request that
 * builds a PayPal client — a checkout, a webhook, a REST call, a WP-CLI order
 * command — and gating it would leave some of those authenticating with
 * whatever happened to be in the database.
 */
final class PaymentsServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool {
		unset( $context );

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton(
			PayPalCredentials::class,
			static fn (): PayPalCredentials => new PayPalCredentials()
		);

		$container->singleton(
			PayPalVerifier::class,
			static fn ( Container $c ): PayPalVerifier => new PayPalVerifier( $c->get( PayPalCredentials::class ) )
		);

		$container->singleton(
			GatewayGuard::class,
			static fn ( Container $c ): GatewayGuard => new GatewayGuard( $c->get( PayPalCredentials::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, PayPalCredentials::class, GatewayGuard::class );
	}
}
