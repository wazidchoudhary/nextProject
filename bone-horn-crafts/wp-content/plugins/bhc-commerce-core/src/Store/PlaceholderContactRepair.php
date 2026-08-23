<?php
/**
 * Corrects placeholder contact details left in stored settings.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Store;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Support\Options;

/**
 * Replaces contact details that are still the shipped placeholders.
 *
 * `Options::all()` merges the stored settings row over the defaults, so once a
 * value has been written to the database, correcting the default has no effect
 * on an existing site. The store's settings row was populated while the
 * defaults were still sample data — a Delaware phone number and a `hello@`
 * address — and those values then outlived the correction.
 *
 * That mattered more after the postal address, phone and email became the input
 * to the Organization JSON-LD and the policy pages: a placeholder that used to
 * sit unnoticed in a settings screen was suddenly being published as the
 * business's telephone number in structured data and printed on the contact
 * page.
 *
 * The repair is deliberately narrow. Only a value that still matches a known
 * placeholder exactly is replaced; anything the merchant has actually typed is
 * left alone, including a deliberate return to one of these strings. Running it
 * twice changes nothing the second time.
 */
final class PlaceholderContactRepair {

	/**
	 * Setting => the placeholder values that may be overwritten.
	 *
	 * @var array<string, string[]>
	 */
	private const PLACEHOLDERS = [
		'organization_phone' => [ '+1 302 555 0148', '+1 302 555 0147', '+1 555 0100' ],
		'organization_email' => [ 'hello@bonehorncrafts.com', 'hello@example.com' ],
	];

	/**
	 * Constructor.
	 *
	 * @param Options $options Plugin settings.
	 */
	public function __construct( private Options $options ) {}

	/**
	 * Applies the repair.
	 *
	 * @return array<string, string> Setting => new value, for the settings that changed.
	 */
	public function apply(): array {
		// drift() is the single decision-maker for what counts as a
		// placeholder; this method only writes what it reports. The two used
		// to carry the same twenty-line loop each, which is exactly how a
		// future placeholder gets added to one and not the other.
		$drift = $this->drift();

		if ( [] === $drift ) {
			return [];
		}

		$settings = $this->options->all();
		$changed  = [];

		foreach ( $drift as $key => $values ) {
			$settings[ $key ] = $values['replacement'];
			$changed[ $key ]  = $values['replacement'];
		}

		$this->options->save( $settings );

		return $changed;
	}

	/**
	 * Reports which settings would be replaced, without writing.
	 *
	 * @return array<string, array{current:string, replacement:string}>
	 */
	public function drift(): array {
		$defaults = $this->options->defaults();
		$settings = $this->options->all();
		$drift    = [];

		foreach ( self::PLACEHOLDERS as $key => $placeholders ) {
			$current = (string) ( $settings[ $key ] ?? '' );

			if ( ! in_array( $current, $placeholders, true ) ) {
				continue;
			}

			$replacement = (string) ( $defaults[ $key ] ?? '' );

			if ( '' === $replacement || $replacement === $current ) {
				continue;
			}

			$drift[ $key ] = [
				'current'     => $current,
				'replacement' => $replacement,
			];
		}

		return $drift;
	}
}
