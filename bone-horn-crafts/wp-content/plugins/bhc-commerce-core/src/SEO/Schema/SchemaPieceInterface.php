<?php
/**
 * Schema piece contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * A contributor to the JSON-LD graph.
 */
interface SchemaPieceInterface {

	/**
	 * Whether the piece applies to the current request.
	 */
	public function is_needed(): bool;

	/**
	 * Returns one or more graph nodes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function build(): array;
}
