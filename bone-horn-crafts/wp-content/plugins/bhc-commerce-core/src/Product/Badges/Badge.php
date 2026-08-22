<?php
/**
 * Badge value object.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Badges;

defined( 'ABSPATH' ) || exit;

/**
 * An immutable merchandising badge.
 */
final class Badge {

	/**
	 * Constructor.
	 *
	 * @param string $slug        Machine name.
	 * @param string $label       Customer facing label.
	 * @param string $tone        Visual tone: neutral|accent|warm|stock|sale.
	 * @param bool   $automatic   Whether the badge is assigned by a rule.
	 * @param int    $priority    Lower sorts first.
	 * @param string $description Admin help text.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $label,
		public readonly string $tone = 'neutral',
		public readonly bool $automatic = false,
		public readonly int $priority = 50,
		public readonly string $description = ''
	) {}

	/**
	 * Returns a copy with a different label (used for dynamic sale badges).
	 *
	 * @param string $label New label.
	 */
	public function with_label( string $label ): self {
		return new self( $this->slug, $label, $this->tone, $this->automatic, $this->priority, $this->description );
	}

	/**
	 * Array representation for REST responses and templates.
	 *
	 * @return array{slug:string, label:string, tone:string}
	 */
	public function to_array(): array {
		return [
			'slug'  => $this->slug,
			'label' => $this->label,
			'tone'  => $this->tone,
		];
	}
}
