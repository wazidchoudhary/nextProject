<?php
/**
 * Hookable contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Implemented by services that own their WordPress hook registrations.
 */
interface HookableInterface {

	/**
	 * Registers WordPress/WooCommerce hooks for the service.
	 */
	public function register_hooks(): void;
}
