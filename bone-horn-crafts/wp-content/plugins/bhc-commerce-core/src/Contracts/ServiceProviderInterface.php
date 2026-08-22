<?php
/**
 * Service provider contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Support\Context;

/**
 * Providers bind services and (optionally) attach WordPress hooks.
 *
 * `register()` must stay cheap: it may only bind factory closures. Object
 * graphs are built lazily inside `boot()` or when a hook actually fires, so a
 * page that never touches the wishlist never instantiates it.
 */
interface ServiceProviderInterface {

	/**
	 * Binds services into the container.
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function register( ContainerInterface $container ): void;

	/**
	 * Attaches hooks. Runs after every provider has registered.
	 *
	 * @param ContainerInterface $container Container instance.
	 */
	public function boot( ContainerInterface $container ): void;

	/**
	 * Whether this provider should load for the current request context.
	 *
	 * @param Context $context Request context.
	 */
	public function should_load( Context $context ): bool;
}
