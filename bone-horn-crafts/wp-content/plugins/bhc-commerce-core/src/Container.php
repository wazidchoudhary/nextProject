<?php
/**
 * Dependency injection container.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * A compact service container with lazy factories and optional autowiring.
 *
 * Design notes
 * ------------
 * * Every binding is a closure, so registering a provider costs one array
 *   write — no object graph is built until something is actually resolved.
 *   That matters on a WooCommerce front end where `plugins_loaded` runs on
 *   every request, including cart fragments and REST calls.
 * * `make()` autowiring uses reflection and is intended for tests, WP-CLI and
 *   one-off admin screens. Hot paths use explicit factories.
 */
final class Container implements ContainerInterface {

	/**
	 * Factory closures keyed by service id.
	 *
	 * @var array<string, callable(self):mixed>
	 */
	private array $factories = [];

	/**
	 * Ids that must resolve to a single shared instance.
	 *
	 * @var array<string, bool>
	 */
	private array $shared = [];

	/**
	 * Resolved shared instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = [];

	/**
	 * Ids currently being resolved, used to detect circular dependencies.
	 *
	 * @var array<string, bool>
	 */
	private array $resolving = [];

	/**
	 * Registers a factory that produces a new instance on every resolve.
	 *
	 * @param string                 $id      Service id.
	 * @param callable(self):mixed   $factory Factory closure.
	 */
	public function bind( string $id, callable $factory ): void {
		unset( $this->instances[ $id ], $this->shared[ $id ] );

		$this->factories[ $id ] = $factory;
	}

	/**
	 * Registers a factory whose result is memoised.
	 *
	 * @param string               $id      Service id.
	 * @param callable(self):mixed $factory Factory closure.
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		$this->shared[ $id ]    = true;

		unset( $this->instances[ $id ] );
	}

	/**
	 * Stores an already constructed instance.
	 *
	 * @param string $id       Service id.
	 * @param mixed  $instance Instance.
	 */
	public function instance( string $id, mixed $instance ): void {
		$this->instances[ $id ] = $instance;
		$this->shared[ $id ]    = true;
	}

	/**
	 * Aliases one id to another.
	 *
	 * @param string $alias  Alias id (typically an interface).
	 * @param string $target Target id (typically a concrete class).
	 */
	public function alias( string $alias, string $target ): void {
		$this->singleton( $alias, static fn ( self $container ): mixed => $container->get( $target ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $id Service id.
	 *
	 * @throws RuntimeException When the id cannot be resolved or is circular.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			if ( class_exists( $id ) ) {
				return $this->make( $id );
			}

			throw new RuntimeException( sprintf( 'Service "%s" is not registered.', $id ) );
		}

		if ( isset( $this->resolving[ $id ] ) ) {
			throw new RuntimeException( sprintf( 'Circular dependency detected while resolving "%s".', $id ) );
		}

		$this->resolving[ $id ] = true;

		try {
			$resolved = ( $this->factories[ $id ] )( $this );
		} finally {
			unset( $this->resolving[ $id ] );
		}

		if ( isset( $this->shared[ $id ] ) ) {
			$this->instances[ $id ] = $resolved;
		}

		return $resolved;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $id Service id.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}

	/**
	 * Autowires a concrete class using constructor type hints.
	 *
	 * @param class-string         $class_name Class to build.
	 * @param array<string, mixed> $overrides  Constructor overrides keyed by parameter name.
	 *
	 * @throws RuntimeException When a dependency cannot be resolved.
	 *
	 * @return object
	 */
	public function make( string $class_name, array $overrides = [] ): object {
		if ( ! class_exists( $class_name ) ) {
			throw new RuntimeException( sprintf( 'Class "%s" does not exist.', $class_name ) );
		}

		$reflection = new ReflectionClass( $class_name );

		if ( ! $reflection->isInstantiable() ) {
			throw new RuntimeException( sprintf( 'Class "%s" is not instantiable.', $class_name ) );
		}

		$constructor = $reflection->getConstructor();

		if ( null === $constructor ) {
			return new $class_name();
		}

		$arguments = [];

		foreach ( $constructor->getParameters() as $parameter ) {
			$name = $parameter->getName();

			if ( array_key_exists( $name, $overrides ) ) {
				$arguments[] = $overrides[ $name ];

				continue;
			}

			$type = $parameter->getType();

			if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
				$arguments[] = $this->get( $type->getName() );

				continue;
			}

			if ( $parameter->isDefaultValueAvailable() ) {
				$arguments[] = $parameter->getDefaultValue();

				continue;
			}

			throw new RuntimeException(
				sprintf( 'Cannot resolve parameter "$%s" of %s.', $name, $class_name )
			);
		}

		return $reflection->newInstanceArgs( $arguments );
	}

	/**
	 * Returns the ids currently registered. Used by the health screen.
	 *
	 * @return string[]
	 */
	public function ids(): array {
		return array_values( array_unique( array_merge( array_keys( $this->factories ), array_keys( $this->instances ) ) ) );
	}
}
