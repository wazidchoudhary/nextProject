<?php
/**
 * Product editor panel for craft, merchandising and export fields.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Product\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Product\Badges\BadgeRegistry;
use BoneHornCrafts\Core\Product\ProductMeta;
use BoneHornCrafts\Core\Security\Sanitizer;
use WC_Product;

/**
 * Adds a "Bone Horn Crafts" tab to the WooCommerce product data box.
 *
 * All writes go through `woocommerce_admin_process_product_object`, so values
 * land on the CRUD object and are persisted by WooCommerce in the same save
 * transaction as core fields — no second `update_post_meta()` pass, no stale
 * object cache.
 */
final class ProductDataPanel implements HookableInterface {

	/**
	 * Constructor.
	 *
	 * @param BadgeRegistry $badges Badge registry.
	 */
	public function __construct( private BadgeRegistry $badges ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_panel' ] );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save' ], 10, 1 );
	}

	/**
	 * Registers the product data tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Existing tabs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['bhc_craft'] = [
			'label'    => __( 'Bone Horn Crafts', 'bhc-commerce-core' ),
			'target'   => 'bhc_craft_product_data',
			'class'    => [ 'hide_if_grouped' ],
			'priority' => 65,
		];

		return $tabs;
	}

	/**
	 * Renders the panel markup.
	 */
	public function render_panel(): void {
		global $post;

		$product = $post instanceof \WP_Post ? wc_get_product( $post->ID ) : null;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$selected_badges = ProductMeta::badges( $product );
		$tiers           = ProductMeta::price_tiers( $product );

		echo '<div id="bhc_craft_product_data" class="panel woocommerce_options_panel hidden">';

		wp_nonce_field( 'bhc_save_product_panel', 'bhc_product_panel_nonce' );

		echo '<div class="options_group">';
		echo '<p class="form-field"><label>' . esc_html__( 'Merchandising badges', 'bhc-commerce-core' ) . '</label><span class="description">' . esc_html__( 'Automatic badges (sale, new arrival, bestseller, bulk, low stock) are applied by the merchandising rules and are not listed here.', 'bhc-commerce-core' ) . '</span></p>';

		foreach ( $this->badges->manual() as $badge ) {
			woocommerce_wp_checkbox(
				[
					'id'          => 'bhc_badge_' . $badge->slug,
					'name'        => 'bhc_badges[]',
					'value'       => in_array( $badge->slug, $selected_badges, true ) ? $badge->slug : '',
					'cbvalue'     => $badge->slug,
					'label'       => $badge->label,
					'description' => $badge->description,
					'desc_tip'    => true,
				]
			);
		}

		echo '</div>';

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			[
				'id'          => ProductMeta::PAIR_MATCHED,
				'value'       => ProductMeta::is_pair_matched( $product ) ? 'yes' : 'no',
				'label'       => __( 'Supplied as a matched pair', 'bhc-commerce-core' ),
				'description' => __( 'Scales are cut from one block and matched for colour and grain.', 'bhc-commerce-core' ),
				'desc_tip'    => true,
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => ProductMeta::UNIT_OF_SALE,
				'value'       => ProductMeta::unit_of_sale( $product ),
				'label'       => __( 'Unit of sale', 'bhc-commerce-core' ),
				'placeholder' => __( 'per matched pair', 'bhc-commerce-core' ),
				'desc_tip'    => true,
				'description' => __( 'Shown next to the price, e.g. "per matched pair" or "set of 6".', 'bhc-commerce-core' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'                => ProductMeta::LEAD_TIME_DAYS,
				'value'             => (string) ProductMeta::lead_time_days( $product ),
				'label'             => __( 'Workshop lead time (days)', 'bhc-commerce-core' ),
				'type'              => 'number',
				'custom_attributes' => [
					'min'  => '0',
					'max'  => '60',
					'step' => '1',
				],
				'desc_tip'          => true,
				'description'       => __( 'Days needed to cut and finish before dispatch. 0 ships from stock.', 'bhc-commerce-core' ),
			]
		);

		woocommerce_wp_textarea_input(
			[
				'id'          => ProductMeta::CARE_INSTRUCTIONS,
				'value'       => ProductMeta::care_instructions( $product ),
				'label'       => __( 'Care &amp; finishing notes', 'bhc-commerce-core' ),
				'rows'        => 4,
				'desc_tip'    => true,
				'description' => __( 'Shown in the "Care &amp; finishing" tab on the product page.', 'bhc-commerce-core' ),
			]
		);

		echo '</div>';

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( 'Wholesale price breaks', 'bhc-commerce-core' ) . '</strong></p>';

		woocommerce_wp_checkbox(
			[
				'id'          => ProductMeta::WHOLESALE_ENABLED,
				'value'       => ProductMeta::wholesale_enabled( $product ) ? 'yes' : 'no',
				'label'       => __( 'Enable quantity pricing', 'bhc-commerce-core' ),
				'description' => __( 'Applies the tiers below once the cart quantity reaches each threshold.', 'bhc-commerce-core' ),
				'desc_tip'    => true,
			]
		);

		echo '<div class="bhc-tiers" data-bhc-tiers>';

		$rows = [] === $tiers ? [ [ 'min_qty' => '', 'price' => '' ] ] : $tiers;

		foreach ( $rows as $index => $tier ) {
			printf(
				'<p class="form-field bhc-tier-row"><label for="bhc_tier_qty_%1$d">%2$s</label>
				<input type="number" min="2" step="1" id="bhc_tier_qty_%1$d" name="bhc_price_tiers[%1$d][min_qty]" value="%3$s" class="short" placeholder="10" />
				<input type="text" inputmode="decimal" name="bhc_price_tiers[%1$d][price]" value="%4$s" class="short wc_input_price" placeholder="%5$s" aria-label="%6$s" /></p>',
				(int) $index,
				esc_html__( 'Minimum quantity / unit price', 'bhc-commerce-core' ),
				esc_attr( (string) ( $tier['min_qty'] ?? '' ) ),
				esc_attr( (string) ( $tier['price'] ?? '' ) ),
				esc_attr( wc_format_localized_price( '0' ) ),
				esc_attr__( 'Tier unit price', 'bhc-commerce-core' )
			);
		}

		echo '</div>';
		echo '<p class="form-field"><button type="button" class="button" data-bhc-add-tier>' . esc_html__( 'Add price break', 'bhc-commerce-core' ) . '</button></p>';
		echo '</div>';

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( 'Export &amp; tax reference data', 'bhc-commerce-core' ) . '</strong><span class="description">' . esc_html__( 'Reference fields for packing lists and invoices. They do not calculate tax on their own.', 'bhc-commerce-core' ) . '</span></p>';

		woocommerce_wp_text_input(
			[
				'id'          => ProductMeta::HSN_CODE,
				'value'       => ProductMeta::hsn_code( $product ),
				'label'       => __( 'HSN code', 'bhc-commerce-core' ),
				'placeholder' => '96019090',
				'desc_tip'    => true,
				'description' => __( 'Harmonised System code used on export documents and GST invoices.', 'bhc-commerce-core' ),
			]
		);

		woocommerce_wp_select(
			[
				'id'      => ProductMeta::GST_RATE,
				'value'   => (string) ProductMeta::gst_rate( $product ),
				'label'   => __( 'Domestic GST rate', 'bhc-commerce-core' ),
				'options' => [
					'0'  => __( '0% (zero rated / export)', 'bhc-commerce-core' ),
					'5'  => '5%',
					'12' => '12%',
					'18' => '18%',
				],
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => ProductMeta::BATCH_REFERENCE,
				'value'       => ProductMeta::batch_reference( $product ),
				'label'       => __( 'Batch / lot reference', 'bhc-commerce-core' ),
				'placeholder' => 'LOT-2026-018',
				'desc_tip'    => true,
				'description' => __( 'Internal reference for the material lot this listing was cut from.', 'bhc-commerce-core' ),
			]
		);

		woocommerce_wp_text_input(
			[
				'id'          => ProductMeta::ORIGIN_COUNTRY,
				'value'       => ProductMeta::origin_country( $product ),
				'label'       => __( 'Country of manufacture', 'bhc-commerce-core' ),
				'placeholder' => 'IN',
				'desc_tip'    => true,
				'description' => __( 'ISO 3166-1 alpha-2 code printed on the customs declaration.', 'bhc-commerce-core' ),
			]
		);

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Persists the panel fields onto the product CRUD object.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save( WC_Product $product ): void {
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$nonce = isset( $_POST['bhc_product_panel_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['bhc_product_panel_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'bhc_save_product_panel' ) ) {
			return;
		}

		$allowed_badges = array_keys( $this->badges->manual() );
		$posted_badges  = isset( $_POST['bhc_badges'] ) ? (array) wp_unslash( $_POST['bhc_badges'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised below.
		$badges         = array_values( array_intersect( array_map( 'sanitize_key', $posted_badges ), $allowed_badges ) );

		ProductMeta::set( $product, ProductMeta::BADGES, $badges );
		ProductMeta::set( $product, ProductMeta::LIMITED_BATCH, in_array( BadgeRegistry::LIMITED_BATCH, $badges, true ) ? 'yes' : 'no' );

		ProductMeta::set( $product, ProductMeta::PAIR_MATCHED, isset( $_POST[ ProductMeta::PAIR_MATCHED ] ) ? 'yes' : 'no' );
		ProductMeta::set( $product, ProductMeta::WHOLESALE_ENABLED, isset( $_POST[ ProductMeta::WHOLESALE_ENABLED ] ) ? 'yes' : 'no' );

		ProductMeta::set(
			$product,
			ProductMeta::UNIT_OF_SALE,
			Sanitizer::text( wp_unslash( $_POST[ ProductMeta::UNIT_OF_SALE ] ?? '' ), 60 )
		);

		ProductMeta::set(
			$product,
			ProductMeta::LEAD_TIME_DAYS,
			min( 60, Sanitizer::id( wp_unslash( $_POST[ ProductMeta::LEAD_TIME_DAYS ] ?? 0 ) ) )
		);

		ProductMeta::set(
			$product,
			ProductMeta::CARE_INSTRUCTIONS,
			Sanitizer::rich_text( wp_unslash( $_POST[ ProductMeta::CARE_INSTRUCTIONS ] ?? '' ) )
		);

		ProductMeta::set(
			$product,
			ProductMeta::HSN_CODE,
			preg_replace( '/[^0-9]/', '', Sanitizer::text( wp_unslash( $_POST[ ProductMeta::HSN_CODE ] ?? '' ), 12 ) ) ?? ''
		);

		ProductMeta::set(
			$product,
			ProductMeta::GST_RATE,
			(float) Sanitizer::amount( wp_unslash( $_POST[ ProductMeta::GST_RATE ] ?? 0 ) )
		);

		ProductMeta::set(
			$product,
			ProductMeta::BATCH_REFERENCE,
			Sanitizer::text( wp_unslash( $_POST[ ProductMeta::BATCH_REFERENCE ] ?? '' ), 40 )
		);

		ProductMeta::set(
			$product,
			ProductMeta::ORIGIN_COUNTRY,
			Sanitizer::country( wp_unslash( $_POST[ ProductMeta::ORIGIN_COUNTRY ] ?? 'IN' ) ) ?: 'IN'
		);

		ProductMeta::set( $product, ProductMeta::PRICE_TIERS, $this->sanitize_tiers( $_POST['bhc_price_tiers'] ?? [] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised in helper.
	}

	/**
	 * Sanitises the posted tier rows.
	 *
	 * @param mixed $raw Raw POST value.
	 *
	 * @return array<int, array{min_qty:int, price:float}>
	 */
	private function sanitize_tiers( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$tiers = [];

		foreach ( array_slice( $raw, 0, 8 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$min_qty = Sanitizer::id( $row['min_qty'] ?? 0 );
			$price   = Sanitizer::amount( wc_format_decimal( wp_unslash( (string) ( $row['price'] ?? '' ) ) ) );

			if ( $min_qty < 2 || $price <= 0 ) {
				continue;
			}

			$tiers[ $min_qty ] = [
				'min_qty' => $min_qty,
				'price'   => $price,
			];
		}

		ksort( $tiers );

		return array_values( $tiers );
	}
}
