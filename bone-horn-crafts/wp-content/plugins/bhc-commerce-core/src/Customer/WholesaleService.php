<?php
/**
 * Wholesale customer handling.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Customer;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;

/**
 * Decides whether the current customer is a wholesale account.
 *
 * The answer is memoised per request and exposed through the
 * `bhc_is_wholesale_customer` filter, which is the single place the pricing
 * rules and the order metadata capture both consult.
 */
final class WholesaleService implements HookableInterface {

	public const APPROVED_META = 'bhc_wholesale_approved';

	/**
	 * Memoised answers keyed by user id.
	 *
	 * @var array<int, bool>
	 */
	private array $memo = [];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'bhc_is_wholesale_customer', [ $this, 'filter_is_wholesale' ], 10, 2 );
		add_action( 'bhc_schema_installed', [ Roles::class, 'install' ] );
	}

	/**
	 * Filter callback.
	 *
	 * @param bool $is_wholesale Current value.
	 * @param int  $user_id      User id.
	 */
	public function filter_is_wholesale( bool $is_wholesale, int $user_id = 0 ): bool {
		return $is_wholesale || $this->is_wholesale( $user_id );
	}

	/**
	 * Whether a user is an approved wholesale customer.
	 *
	 * @param int $user_id User id, 0 for the current user.
	 */
	public function is_wholesale( int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		if ( isset( $this->memo[ $user_id ] ) ) {
			return $this->memo[ $user_id ];
		}

		$user = get_userdata( $user_id );

		if ( false === $user ) {
			$this->memo[ $user_id ] = false;

			return $this->memo[ $user_id ];
		}

		$approved = 'yes' === get_user_meta( $user_id, self::APPROVED_META, true );
		$has_role = in_array( Roles::WHOLESALE_ROLE, (array) $user->roles, true );

		$this->memo[ $user_id ] = ( $approved || $has_role || user_can( $user, Roles::WHOLESALE_CAP ) );

		return $this->memo[ $user_id ];
	}

	/**
	 * Approves a customer for wholesale pricing.
	 *
	 * @param int $user_id User id.
	 */
	public function approve( int $user_id ): bool {
		$user = get_userdata( absint( $user_id ) );

		if ( false === $user ) {
			return false;
		}

		update_user_meta( $user->ID, self::APPROVED_META, 'yes' );
		$user->add_role( Roles::WHOLESALE_ROLE );

		unset( $this->memo[ $user->ID ] );

		return true;
	}
}
