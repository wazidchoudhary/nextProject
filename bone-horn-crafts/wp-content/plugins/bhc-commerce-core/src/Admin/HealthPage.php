<?php
/**
 * Health check screen.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Security\Capabilities;

/**
 * Renders the environment report as pass/warn/fail rows plus raw detail.
 */
final class HealthPage {

	/**
	 * Constructor.
	 *
	 * @param HealthReport $report Health report.
	 */
	public function __construct( private HealthReport $report ) {}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bhc-commerce-core' ), 403 );
		}

		$checks = $this->report->checks();
		$report = $this->report->build();

		echo '<div class="wrap bhc-admin">';
		printf( '<h1>%s</h1>', esc_html__( 'System health', 'bhc-commerce-core' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Environment and background processing status. No credentials are shown, so this page is safe to share with support.', 'bhc-commerce-core' )
		);

		echo '<table class="widefat striped bhc-admin__health"><tbody>';

		foreach ( $checks as $check ) {
			$status = (string) $check['status'];

			printf(
				'<tr><td style="width:22em"><strong>%1$s</strong></td><td><span class="bhc-status bhc-status--%2$s">%3$s</span></td><td>%4$s</td></tr>',
				esc_html( (string) $check['label'] ),
				esc_attr( $status ),
				esc_html( $this->status_label( $status ) ),
				esc_html( (string) $check['detail'] )
			);
		}

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Details', 'bhc-commerce-core' ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';

		foreach ( $report as $section => $values ) {
			printf( '<tr><th colspan="2">%s</th></tr>', esc_html( ucfirst( (string) $section ) ) );

			foreach ( (array) $values as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				} elseif ( is_bool( $value ) ) {
					$value = $value ? __( 'yes', 'bhc-commerce-core' ) : __( 'no', 'bhc-commerce-core' );
				}

				printf(
					'<tr><td style="width:22em"><code>%1$s</code></td><td>%2$s</td></tr>',
					esc_html( (string) $key ),
					esc_html( (string) $value )
				);
			}
		}

		echo '</tbody></table>';

		printf(
			'<p class="description">%s <code>wp bhc health-check</code></p>',
			esc_html__( 'The same report is available from the command line:', 'bhc-commerce-core' )
		);

		echo '</div>';
	}

	/**
	 * Human label for a status key.
	 *
	 * @param string $status Status key.
	 */
	private function status_label( string $status ): string {
		return match ( $status ) {
			'pass' => __( 'OK', 'bhc-commerce-core' ),
			'warn' => __( 'Attention', 'bhc-commerce-core' ),
			default => __( 'Failing', 'bhc-commerce-core' ),
		};
	}
}
