<?php
/**
 * Plugin orchestrator.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\ServiceProviderInterface;
use BoneHornCrafts\Core\Support\Context;

/**
 * Wires providers into the container and boots them.
 *
 * The class is intentionally thin: it knows *which* modules exist and in what
 * order they register, and nothing about what any of them do.
 */
final class Plugin {

	/**
	 * Singleton instance. WordPress gives us no bootstrap of its own, so the
	 * plugin file needs one well-known entry point; everything downstream is
	 * constructor injected.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Instantiated providers.
	 *
	 * @var ServiceProviderInterface[]
	 */
	private array $providers = [];

	/**
	 * Guard against double booting.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path to the plugin bootstrap file.
	 */
	private function __construct( private string $file ) {
		$this->container = new Container();
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @param string|null $file Plugin file, required on first call.
	 */
	public static function instance( ?string $file = null ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( (string) ( $file ?? BHC_CORE_FILE ) );
		}

		return self::$instance;
	}

	/**
	 * Exposes the container (used by WP-CLI commands and tests).
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Absolute path to the plugin directory, with a trailing slash.
	 */
	public function dir(): string {
		return trailingslashit( dirname( $this->file ) );
	}

	/**
	 * Public URL of the plugin directory, with a trailing slash.
	 */
	public function url(): string {
		return trailingslashit( plugins_url( '', $this->file ) );
	}

	/**
	 * Plugin version, used for cache busting and schema migrations.
	 */
	public function version(): string {
		return BHC_CORE_VERSION;
	}

	/**
	 * The list of provider classes, in registration order.
	 *
	 * @return class-string<ServiceProviderInterface>[]
	 */
	private function provider_classes(): array {
		$providers = [
			CoreServiceProvider::class,
			Database\DatabaseServiceProvider::class,
			Cache\CacheServiceProvider::class,
			Security\SecurityServiceProvider::class,
			Product\ProductServiceProvider::class,
			Pricing\PricingServiceProvider::class,
			Wishlist\WishlistServiceProvider::class,
			Recommendations\RecommendationsServiceProvider::class,
			Search\SearchServiceProvider::class,
			Checkout\CheckoutServiceProvider::class,
			Payments\PaymentsServiceProvider::class,
			Order\OrderServiceProvider::class,
			Customer\CustomerServiceProvider::class,
			Analytics\AnalyticsServiceProvider::class,
			Jobs\JobsServiceProvider::class,
			SEO\SeoServiceProvider::class,
			API\ApiServiceProvider::class,
			Frontend\FrontendServiceProvider::class,
			Admin\AdminServiceProvider::class,
			CLI\CliServiceProvider::class,
			Demo\DemoServiceProvider::class,
		];

		/**
		 * Filters the registered service providers.
		 *
		 * Site-specific customisations (for example a marketplace integration)
		 * can append a provider instead of patching the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @param class-string<ServiceProviderInterface>[] $providers Provider class names.
		 */
		return (array) apply_filters( 'bhc_service_providers', $providers );
	}

	/**
	 * Registers and boots every provider that applies to this request.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$context = new Context();

		$this->container->instance( self::class, $this );
		$this->container->instance( Container::class, $this->container );
		$this->container->instance( Context::class, $context );

		foreach ( $this->provider_classes() as $provider_class ) {
			if ( ! class_exists( $provider_class ) ) {
				continue;
			}

			/** @var ServiceProviderInterface $provider */
			$provider = new $provider_class();

			if ( ! $provider->should_load( $context ) ) {
				continue;
			}

			$provider->register( $this->container );

			$this->providers[] = $provider;
		}

		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}

		// WordPress 6.7+ expects translations to load on `init`, not earlier:
		// loading at `plugins_loaded` triggers a just-in-time notice and can
		// prime the wrong locale for a user-specific admin request.
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain( 'bhc-commerce-core', false, dirname( BHC_CORE_BASENAME ) . '/languages' );
			},
			1
		);

		/**
		 * Fires once every Bone Horn Crafts service is available.
		 *
		 * @since 1.0.0
		 *
		 * @param Container $container Service container.
		 */
		do_action( 'bhc_booted', $this->container );
	}

	/**
	 * Returns the booted providers. Used by the admin health screen.
	 *
	 * @return ServiceProviderInterface[]
	 */
	public function providers(): array {
		return $this->providers;
	}

	/**
	 * Convenience accessor used by hook callbacks that cannot be injected.
	 *
	 * @param string $id Service id.
	 *
	 * @return mixed
	 */
	public static function resolve( string $id ): mixed {
		return self::instance()->container()->get( $id );
	}
}
