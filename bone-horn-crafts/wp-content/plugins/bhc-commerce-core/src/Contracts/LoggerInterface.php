<?php
/**
 * Logger contract.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Structured logging contract (PSR-3 shaped, WooCommerce backed).
 */
interface LoggerInterface {

	/**
	 * Logs a message.
	 *
	 * @param string               $level   One of debug|info|notice|warning|error|critical|alert|emergency.
	 * @param string               $event   Machine readable event name, e.g. `wishlist.item_added`.
	 * @param array<string, mixed> $context Structured context. Sensitive keys are redacted.
	 */
	public function log( string $level, string $event, array $context = [] ): void;

	/**
	 * Logs a debug event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function debug( string $event, array $context = [] ): void;

	/**
	 * Logs an informational event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function info( string $event, array $context = [] ): void;

	/**
	 * Logs a warning event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function warning( string $event, array $context = [] ): void;

	/**
	 * Logs an error event.
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function error( string $event, array $context = [] ): void;
}
