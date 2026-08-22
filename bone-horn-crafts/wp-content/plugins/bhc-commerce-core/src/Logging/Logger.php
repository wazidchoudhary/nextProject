<?php
/**
 * Structured logger.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Logging;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\LoggerInterface;
use WC_Log_Levels;

/**
 * Writes structured log lines through the WooCommerce logger.
 *
 * Every line is `event {json-context}` so log files stay greppable and can be
 * shipped to a log aggregator without a bespoke parser. Sensitive keys are
 * redacted before they reach disk — the logger is the last line of defence
 * against leaking a token into `wp-content/uploads/wc-logs`.
 */
final class Logger implements LoggerInterface {

	/**
	 * Keys whose values are never written to the log.
	 *
	 * @var string[]
	 */
	private const REDACTED_KEYS = [
		'password',
		'pass',
		'pwd',
		'token',
		'access_token',
		'refresh_token',
		'secret',
		'api_key',
		'apikey',
		'authorization',
		'auth',
		'nonce',
		'card',
		'card_number',
		'cvv',
		'cvc',
		'iban',
		'session',
		'session_token',
		'cookie',
		'ssn',
		'tax_id',
	];

	/**
	 * Severity ranking used to honour the configured minimum level.
	 *
	 * @var array<string, int>
	 */
	private const SEVERITY = [
		'debug'     => 10,
		'info'      => 20,
		'notice'    => 30,
		'warning'   => 40,
		'error'     => 50,
		'critical'  => 60,
		'alert'     => 70,
		'emergency' => 80,
	];

	/**
	 * WooCommerce logger instance, resolved lazily.
	 *
	 * @var \WC_Logger_Interface|null
	 */
	private $wc_logger = null;

	/**
	 * Constructor.
	 *
	 * @param string $channel   Log channel (WooCommerce "source").
	 * @param string $min_level Minimum severity that gets written.
	 */
	public function __construct( private string $channel = 'bhc-core', private string $min_level = 'info' ) {}

	/**
	 * Overrides the minimum severity at runtime (used by WP-CLI `--debug`).
	 *
	 * @param string $level Minimum level.
	 */
	public function set_min_level( string $level ): void {
		if ( isset( self::SEVERITY[ $level ] ) ) {
			$this->min_level = $level;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $level   Severity.
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function log( string $level, string $event, array $context = [] ): void {
		$level = isset( self::SEVERITY[ $level ] ) ? $level : 'info';

		if ( self::SEVERITY[ $level ] < ( self::SEVERITY[ $this->min_level ] ?? 20 ) ) {
			return;
		}

		$payload = [
			'event'   => sanitize_key( str_replace( ' ', '_', $event ) ),
			'ts'      => gmdate( 'c' ),
			'level'   => $level,
			'context' => $this->redact( $context ),
		];

		/**
		 * Filters a log payload before it is written.
		 *
		 * Returning an empty array suppresses the line, which lets a site drop
		 * noisy events without editing the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $payload Structured payload.
		 */
		$payload = (array) apply_filters( 'bhc_log_payload', $payload );

		if ( [] === $payload ) {
			return;
		}

		$message = $payload['event'] . ' ' . (string) wp_json_encode( $payload['context'] );

		$logger = $this->logger();

		if ( null !== $logger ) {
			$logger->log( $level, $message, [ 'source' => $this->channel ] );

			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Fallback when WooCommerce is unavailable.
			error_log( sprintf( '[%s][%s] %s', $this->channel, $level, $message ) );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function debug( string $event, array $context = [] ): void {
		$this->log( 'debug', $event, $context );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function info( string $event, array $context = [] ): void {
		$this->log( 'info', $event, $context );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function warning( string $event, array $context = [] ): void {
		$this->log( 'warning', $event, $context );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $event   Event name.
	 * @param array<string, mixed> $context Context payload.
	 */
	public function error( string $event, array $context = [] ): void {
		$this->log( 'error', $event, $context );
	}

	/**
	 * Recursively removes sensitive values and trims oversized payloads.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @param int                  $depth   Current recursion depth.
	 *
	 * @return array<string, mixed>
	 */
	public function redact( array $context, int $depth = 0 ): array {
		if ( $depth > 4 ) {
			return [ 'truncated' => true ];
		}

		$clean = [];

		foreach ( $context as $key => $value ) {
			$normalised_key = strtolower( (string) $key );

			$is_sensitive = false;

			foreach ( self::REDACTED_KEYS as $needle ) {
				if ( str_contains( $normalised_key, $needle ) ) {
					$is_sensitive = true;

					break;
				}
			}

			if ( $is_sensitive ) {
				$clean[ $key ] = '[redacted]';

				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->redact( $value, $depth + 1 );

				continue;
			}

			if ( is_object( $value ) ) {
				$clean[ $key ] = method_exists( $value, '__toString' ) ? (string) $value : get_class( $value );

				continue;
			}

			if ( is_string( $value ) && strlen( $value ) > 500 ) {
				$clean[ $key ] = substr( $value, 0, 500 ) . '…';

				continue;
			}

			$clean[ $key ] = $value;
		}

		return $clean;
	}

	/**
	 * Resolves the WooCommerce logger when available.
	 *
	 * @return \WC_Logger_Interface|null
	 */
	private function logger() {
		if ( null !== $this->wc_logger ) {
			return $this->wc_logger;
		}

		if ( ! function_exists( 'wc_get_logger' ) || ! class_exists( WC_Log_Levels::class ) ) {
			return null;
		}

		return $this->wc_logger = wc_get_logger();
	}
}
