<?php
/**
 * Template renderer with theme overrides.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Renders plugin templates, allowing themes to override them.
 *
 * Lookup order mirrors WooCommerce's convention:
 *   1. `child-theme/bone-horn-crafts/{template}`
 *   2. `parent-theme/bone-horn-crafts/{template}`
 *   3. `plugin/templates/{template}`
 */
final class Template {

	/**
	 * Resolved template paths, keyed by template name.
	 *
	 * @var array<string, string>
	 */
	private array $resolved = [];

	/**
	 * Constructor.
	 *
	 * @param string $base_path      Plugin template directory (trailing slash).
	 * @param string $theme_dir_name Directory themes use to override templates.
	 */
	public function __construct( private string $base_path, private string $theme_dir_name ) {}

	/**
	 * Resolves a template path.
	 *
	 * @param string $template Template file name, e.g. `wishlist/table.php`.
	 */
	public function locate( string $template ): string {
		$template = ltrim( str_replace( [ '..', "\0" ], '', $template ), '/' );

		if ( isset( $this->resolved[ $template ] ) ) {
			return $this->resolved[ $template ];
		}

		$theme_file = locate_template(
			[
				trailingslashit( $this->theme_dir_name ) . $template,
			]
		);

		$path = $theme_file ?: $this->base_path . $template;

		/**
		 * Filters the resolved template path.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path     Absolute template path.
		 * @param string $template Requested template name.
		 */
		$path = (string) apply_filters( 'bhc_locate_template', $path, $template );

		return $this->resolved[ $template ] = $path;
	}

	/**
	 * Renders a template and returns its markup.
	 *
	 * @param string               $template Template name.
	 * @param array<string, mixed> $data     Variables exposed to the template.
	 */
	public function render( string $template, array $data = [] ): string {
		$path = $this->locate( $template );

		if ( ! is_readable( $path ) ) {
			return '';
		}

		ob_start();

		( static function ( string $bhc_template_path, array $bhc_data ): void {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Scoped to an isolated closure.
			extract( $bhc_data, EXTR_SKIP );

			require $bhc_template_path;
		} )( $path, $data );

		return (string) ob_get_clean();
	}

	/**
	 * Echoes a template.
	 *
	 * @param string               $template Template name.
	 * @param array<string, mixed> $data     Variables exposed to the template.
	 */
	public function output( string $template, array $data = [] ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates escape their own output.
		echo $this->render( $template, $data );
	}
}
