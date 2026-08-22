<?php
/**
 * JSON-LD graph assembly.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\SEO\Schema;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Support\Options;

/**
 * Emits a single `@graph` document instead of several disconnected blocks.
 *
 * One graph with `@id` references lets Organization, WebSite, Product,
 * BreadcrumbList and Article point at each other, which is what search engines
 * actually want — and it means one `<script type="application/ld+json">` in the
 * head instead of five.
 */
final class SchemaGraph implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param SchemaPieceInterface[] $pieces  Graph piece builders.
	 * @param Options                $options Settings.
	 */
	public function __construct( private array $pieces, private Options $options ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', [ $this, 'render' ], 20 );
	}

	/**
	 * Prints the JSON-LD graph.
	 */
	public function render(): void {
		if ( ! $this->options->bool( 'schema_enabled' ) || is_404() ) {
			return;
		}

		$graph = $this->build();

		if ( [] === $graph ) {
			return;
		}

		$document = [
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		];

		// HEX_TAG/HEX_AMP escape `<`, `>` and `&` as unicode sequences, which
		// makes it impossible for encoded content to close the script element.
		$json = wp_json_encode(
			$document,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);

		if ( false === $json ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded above with JSON_HEX_TAG | JSON_HEX_AMP.
		printf( "<script type=\"application/ld+json\">%s</script>\n", $json );
	}

	/**
	 * Builds the graph pieces that apply to the current request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function build(): array {
		$graph = [];

		foreach ( $this->pieces as $piece ) {
			if ( ! $piece instanceof SchemaPieceInterface || ! $piece->is_needed() ) {
				continue;
			}

			foreach ( $piece->build() as $node ) {
				if ( is_array( $node ) && [] !== $node ) {
					$graph[] = $node;
				}
			}
		}

		/**
		 * Filters the assembled schema graph.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $graph Graph nodes.
		 */
		return (array) apply_filters( 'bhc_schema_graph', $graph );
	}
}
