<?php
/**
 * Typed settings accessor.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's single settings option.
 *
 * Storing one array under one option key keeps `alloptions` small (a single
 * autoloaded row instead of a dozen) and makes the settings screen a single
 * atomic write.
 */
final class Options {

	public const OPTION_KEY = 'bhc_settings';

	/**
	 * Memoised settings array.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $settings = null;

	/**
	 * Default values. Also acts as the schema for sanitisation.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'badges_enabled'             => true,
			'new_arrival_days'           => 45,
			'bestseller_limit'           => 12,
			'recommendations_limit'      => 8,
			'recommendations_ttl'        => 6 * HOUR_IN_SECONDS,
			'wishlist_enabled'           => true,
			'wishlist_guest_enabled'     => true,
			'wishlist_max_items'         => 60,
			'recently_viewed_limit'      => 8,
			'tiered_pricing_enabled'     => true,
			'delivery_estimator_enabled' => true,
			'schema_enabled'             => true,
			'social_image_id'            => 0,
			'twitter_handle'             => '@bonehorncrafts',
			'organization_email'         => 'hello@bonehorncrafts.com',
			'organization_phone'         => '+1 302 555 0148',
			'legal_entity'               => 'AS International',
			'canonical_host'             => 'https://www.bonehorncrafts.com',
			'index_batch_size'           => 40,
			'log_level'                  => 'info',
			// Off by default: uninstalling a plugin should not be able to destroy
			// wishlists and merchandising history by accident. See uninstall.php.
			'delete_data_on_uninstall'   => false,
		];
	}

	/**
	 * Returns a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$settings = $this->all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default ?? ( $this->defaults()[ $key ] ?? null );
	}

	/**
	 * Returns a boolean setting.
	 *
	 * @param string $key Setting key.
	 */
	public function bool( string $key ): bool {
		return (bool) $this->get( $key );
	}

	/**
	 * Returns an integer setting.
	 *
	 * @param string $key Setting key.
	 */
	public function int( string $key ): int {
		return (int) $this->get( $key );
	}

	/**
	 * Returns a string setting.
	 *
	 * @param string $key Setting key.
	 */
	public function string( string $key ): string {
		return (string) $this->get( $key );
	}

	/**
	 * Returns every setting merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->settings ) {
			$stored = get_option( self::OPTION_KEY, [] );

			$this->settings = array_merge( $this->defaults(), is_array( $stored ) ? $stored : [] );
		}

		/**
		 * Filters the resolved plugin settings.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $settings Settings.
		 */
		return (array) apply_filters( 'bhc_settings', $this->settings );
	}

	/**
	 * Persists a settings array after sanitising it against the schema.
	 *
	 * @param array<string, mixed> $input Raw input, typically from $_POST.
	 */
	public function save( array $input ): bool {
		$clean = $this->sanitize( $input );

		$this->settings = null;

		return update_option( self::OPTION_KEY, $clean, true );
	}

	/**
	 * Sanitises raw input against the defaults schema.
	 *
	 * Unknown keys are dropped, every value is cast to the type of its default.
	 *
	 * @param array<string, mixed> $input Raw input.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$clean = [];

		foreach ( $this->defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				$clean[ $key ] = $this->all()[ $key ] ?? $default;

				continue;
			}

			$value = $input[ $key ];

			// Type comes from the default, except where the *meaning* of the
			// field needs a stricter sanitiser than its PHP type implies.
			// A canonical host sanitised as free text will happily store
			// "not a url", and the first place anyone finds out is a broken
			// canonical tag on every page.
			$current = $this->all()[ $key ] ?? $default;

			// Type comes from the default, except where the *meaning* of the
			// field needs a stricter rule than its PHP type implies. A
			// canonical host sanitised as free text will happily store
			// "not a url", and the first anyone hears of it is a broken
			// canonical tag on every page. Invalid input keeps the previous
			// value rather than blanking the field: a typo in the contact
			// address should not silently remove it from the site.
			$clean[ $key ] = match ( true ) {
				'canonical_host' === $key     => self::valid_host( (string) $value, (string) $current ),
				'organization_email' === $key => sanitize_email( (string) $value ) ?: (string) $current,
				is_bool( $default )           => (bool) $value,
				is_int( $default )            => max( 0, (int) $value ),
				is_float( $default )          => (float) $value,
				default                       => sanitize_text_field( (string) $value ),
			};
		}

		return $clean;
	}

	/**
	 * Accepts a URL only if it is one, with a scheme and a host.
	 *
	 * @param string $value    Submitted value.
	 * @param string $fallback Value to keep when the input is not a URL.
	 */
	private static function valid_host( string $value, string $fallback ): string {
		$value = untrailingslashit( trim( $value ) );

		if ( '' === $value ) {
			return '';
		}

		$url   = esc_url_raw( $value, [ 'http', 'https' ] );
		$parts = '' === $url ? [] : (array) wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! str_contains( (string) $parts['host'], '.' ) ) {
			return $fallback;
		}

		return $url;
	}
}
