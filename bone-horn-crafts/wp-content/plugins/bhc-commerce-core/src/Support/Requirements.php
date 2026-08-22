<?php
/**
 * Environment requirement checks.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies the runtime satisfies the plugin's minimum requirements.
 *
 * The plugin refuses to boot rather than fatal-ing on an unsupported stack;
 * the failure is surfaced to administrators as an admin notice.
 */
final class Requirements {

	/**
	 * Collected failure messages.
	 *
	 * @var string[]
	 */
	private array $failures = [];

	/**
	 * Whether the checks have run.
	 */
	private bool $checked = false;

	/**
	 * Constructor.
	 *
	 * @param array{php:string,wp:string,wc:string} $minimums Minimum versions.
	 */
	public function __construct( private array $minimums ) {}

	/**
	 * Runs (and memoises) the requirement checks.
	 */
	public function satisfied(): bool {
		if ( $this->checked ) {
			return [] === $this->failures;
		}

		$this->checked = true;

		if ( version_compare( PHP_VERSION, $this->minimums['php'], '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'PHP %1$s or newer is required. This server runs PHP %2$s.', 'bhc-commerce-core' ),
				$this->minimums['php'],
				PHP_VERSION
			);
		}

		if ( version_compare( (string) get_bloginfo( 'version' ), $this->minimums['wp'], '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: %s: required WordPress version. */
				__( 'WordPress %s or newer is required.', 'bhc-commerce-core' ),
				$this->minimums['wp']
			);
		}

		if ( ! defined( 'WC_VERSION' ) ) {
			$this->failures[] = __( 'WooCommerce must be installed and activated.', 'bhc-commerce-core' );
		} elseif ( version_compare( (string) constant( 'WC_VERSION' ), $this->minimums['wc'], '<' ) ) {
			$this->failures[] = sprintf(
				/* translators: %s: required WooCommerce version. */
				__( 'WooCommerce %s or newer is required.', 'bhc-commerce-core' ),
				$this->minimums['wc']
			);
		}

		return [] === $this->failures;
	}

	/**
	 * Renders an admin notice describing why the plugin did not boot.
	 */
	public function render_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) || [] === $this->failures ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><ul style="list-style:disc;margin-left:20px">%s</ul></div>',
			esc_html__( 'Bone Horn Crafts Commerce Core could not start.', 'bhc-commerce-core' ),
			implode(
				'',
				array_map(
					static fn ( string $failure ): string => '<li>' . esc_html( $failure ) . '</li>',
					$this->failures
				)
			)
		);
	}

	/**
	 * Exposes the failure list (used by the health screen and WP-CLI).
	 *
	 * @return string[]
	 */
	public function failures(): array {
		$this->satisfied();

		return $this->failures;
	}
}
