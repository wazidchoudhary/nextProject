<?php
/**
 * Service container tests.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Tests\Unit;

use BoneHornCrafts\Core\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \BoneHornCrafts\Core\Container
 */
final class ContainerTest extends TestCase {

	public function test_bind_creates_a_new_instance_each_time(): void {
		$container = new Container();

		$container->bind( 'counter', static fn (): object => new \stdClass() );

		$this->assertNotSame( $container->get( 'counter' ), $container->get( 'counter' ) );
	}

	public function test_singleton_memoises_the_instance(): void {
		$container = new Container();

		$container->singleton( 'shared', static fn (): object => new \stdClass() );

		$this->assertSame( $container->get( 'shared' ), $container->get( 'shared' ) );
	}

	public function test_factories_are_lazy(): void {
		$container = new Container();
		$built     = false;

		$container->singleton(
			'expensive',
			static function () use ( &$built ): object {
				$built = true;

				return new \stdClass();
			}
		);

		$this->assertFalse( $built, 'Registering a provider must not build anything.' );

		$container->get( 'expensive' );

		$this->assertTrue( $built );
	}

	public function test_alias_resolves_to_the_target(): void {
		$container = new Container();

		$container->singleton( ContainerTestConcrete::class, static fn (): ContainerTestConcrete => new ContainerTestConcrete( 'wired' ) );
		$container->alias( ContainerTestContract::class, ContainerTestConcrete::class );

		$this->assertSame( 'wired', $container->get( ContainerTestContract::class )->value() );
	}

	public function test_autowires_constructor_dependencies(): void {
		$container = new Container();

		$container->singleton( ContainerTestConcrete::class, static fn (): ContainerTestConcrete => new ContainerTestConcrete( 'injected' ) );

		$consumer = $container->make( ContainerTestConsumer::class );

		$this->assertSame( 'injected', $consumer->dependency->value() );
	}

	public function test_unknown_service_throws(): void {
		$this->expectException( RuntimeException::class );

		( new Container() )->get( 'nope' );
	}

	public function test_circular_dependencies_are_detected(): void {
		$container = new Container();

		$container->singleton( 'a', static fn ( Container $c ): mixed => $c->get( 'b' ) );
		$container->singleton( 'b', static fn ( Container $c ): mixed => $c->get( 'a' ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Circular dependency/' );

		$container->get( 'a' );
	}

	public function test_has_reports_registration_state(): void {
		$container = new Container();

		$this->assertFalse( $container->has( 'thing' ) );

		$container->instance( 'thing', new \stdClass() );

		$this->assertTrue( $container->has( 'thing' ) );
		$this->assertContains( 'thing', $container->ids() );
	}
}

/**
 * Test contract.
 */
interface ContainerTestContract {

	/**
	 * Returns the wired value.
	 */
	public function value(): string;
}

/**
 * Test implementation.
 */
final class ContainerTestConcrete implements ContainerTestContract {

	/**
	 * Constructor.
	 *
	 * @param string $value Value.
	 */
	public function __construct( private string $value ) {}

	/**
	 * {@inheritDoc}
	 */
	public function value(): string {
		return $this->value;
	}
}

/**
 * Consumer used to exercise autowiring.
 */
final class ContainerTestConsumer {

	/**
	 * Constructor.
	 *
	 * @param ContainerTestConcrete $dependency Injected dependency.
	 */
	public function __construct( public ContainerTestConcrete $dependency ) {}
}
