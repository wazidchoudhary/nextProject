<?php
/**
 * Delivery estimate REST endpoint.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\API;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Checkout\CountryProfile;
use BoneHornCrafts\Core\Checkout\DeliveryEstimator;
use BoneHornCrafts\Core\Checkout\PostcodeValidator;
use BoneHornCrafts\Core\Security\RestGuard;
use BoneHornCrafts\Core\Security\Sanitizer;
use WC_Product;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `bhc/v1/delivery-estimate` — transit window for a destination.
 */
final class ShippingController extends AbstractController {

	/**
	 * Constructor.
	 *
	 * @param DeliveryEstimator $estimator Estimator.
	 * @param PostcodeValidator $postcodes Postcode helper.
	 * @param RestGuard         $guard     Permission callbacks.
	 */
	public function __construct(
		private DeliveryEstimator $estimator,
		private PostcodeValidator $postcodes,
		RestGuard $guard
	) {
		parent::__construct( $guard );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/delivery-estimate',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_estimate' ],
				'permission_callback' => [ $this->guard, 'public_read' ],
				'args'                => [
					'country'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => static fn ( $value ): string => Sanitizer::country( $value ),
						'validate_callback' => static fn ( $value ): bool => '' !== Sanitizer::country( $value ),
					],
					'postcode'   => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn ( $value ): string => Sanitizer::postcode( $value ),
					],
					'product_id' => [
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Returns the delivery estimate.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_estimate( WP_REST_Request $request ): WP_REST_Response {
		$country  = (string) $request->get_param( 'country' );
		$postcode = (string) $request->get_param( 'postcode' );
		$product  = wc_get_product( (int) $request->get_param( 'product_id' ) );

		$estimate = $this->estimator->estimate( $product instanceof WC_Product ? $product : null, $country );

		$payload = [
			'estimate' => $estimate,
			'postcode' => [
				'label'   => $this->postcodes->label( $country ),
				'example' => $this->postcodes->example( $country ),
				'valid'   => '' === $postcode ? null : $this->postcodes->is_valid( $postcode, $country ),
			],
			'zone'     => (string) CountryProfile::get( $country )['zone'],
		];

		return $this->respond( $payload, 200, HOUR_IN_SECONDS );
	}
}
