<?php
/**
 * Container contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A very small PSR-11 shaped container contract.
 *
 * Deliberately not depending on `psr/container` keeps the plugin free of
 * runtime Composer dependencies while preserving the same semantics.
 */
interface ContainerInterface {

	/**
	 * Resolves an entry.
	 *
	 * @param string $id Service identifier, usually a class-string.
	 *
	 * @return mixed
	 */
	public function get( string $id ): mixed;

	/**
	 * Whether the container can resolve the identifier.
	 *
	 * @param string $id Service identifier.
	 */
	public function has( string $id ): bool;
}
