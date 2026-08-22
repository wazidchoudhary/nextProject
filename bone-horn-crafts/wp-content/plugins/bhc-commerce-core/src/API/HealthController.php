<?php
/**
 * Health REST endpoint.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Admin\HealthReport;
use BoneHornCrafts\Core\Security\RestGuard;
use WP_REST_Response;

/**
 * `bhc/v1/health` — machine readable system status.
 *
 * Restricted to store managers and never cached. The report deliberately
 * contains versions and feature flags only: no credentials, no connection
 * strings, no file paths.
 */
final class HealthController extends AbstractController {

	/**
	 * Constructor.
	 *
	 * @param HealthReport $report Health report builder.
	 * @param RestGuard    $guard  Permission callbacks.
	 */
	public function __construct( private HealthReport $report, RestGuard $guard ) {
		parent::__construct( $guard );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/health',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_report' ],
				'permission_callback' => [ $this->guard, 'manage' ],
			]
		);
	}

	/**
	 * Returns the health report.
	 */
	public function get_report(): WP_REST_Response {
		return $this->respond( $this->report->build() );
	}
}
