<?php
/**
 * Checkout module wiring.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Checkout;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\AbstractServiceProvider;
use BoneHornCrafts\Core\Container;
use BoneHornCrafts\Core\Contracts\ContainerInterface;
use BoneHornCrafts\Core\Contracts\LoggerInterface;
use BoneHornCrafts\Core\Support\Context;
use BoneHornCrafts\Core\Support\Options;
use BoneHornCrafts\Core\Support\Template;

/**
 * Registers checkout validation and shipping information services.
 */
final class CheckoutServiceProvider extends AbstractServiceProvider {

	/**
	 * Request context.
	 *
	 * @var Context|null
	 */
	private ?Context $context = null;

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool {
		$this->context = $context;

		return ! $context->is_admin() || $context->is_ajax();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void {
		/** @var Container $container */
		$container->singleton( PostcodeValidator::class, static fn (): PostcodeValidator => new PostcodeValidator() );
		$container->singleton( PhoneValidator::class, static fn (): PhoneValidator => new PhoneValidator() );
		$container->singleton( DeliveryEstimator::class, static fn (): DeliveryEstimator => new DeliveryEstimator() );

		$container->singleton(
			CheckoutFieldCustomizer::class,
			static fn ( Container $c ): CheckoutFieldCustomizer => new CheckoutFieldCustomizer(
				$c->get( PostcodeValidator::class ),
				$c->get( PhoneValidator::class )
			)
		);

		$container->singleton(
			AddressValidator::class,
			static fn ( Container $c ): AddressValidator => new AddressValidator(
				$c->get( PostcodeValidator::class ),
				$c->get( PhoneValidator::class ),
				$c->get( LoggerInterface::class )
			)
		);

		$container->singleton(
			ShippingInfoRenderer::class,
			static fn ( Container $c ): ShippingInfoRenderer => new ShippingInfoRenderer(
				$c->get( DeliveryEstimator::class ),
				$c->get( Template::class ),
				$c->get( Options::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void {
		$this->hook( $container, CheckoutFieldCustomizer::class, AddressValidator::class );

		$context = $this->context ?? new Context();

		if ( $context->is_frontend() || $context->is_ajax() ) {
			$this->hook( $container, ShippingInfoRenderer::class );
		}
	}
}
