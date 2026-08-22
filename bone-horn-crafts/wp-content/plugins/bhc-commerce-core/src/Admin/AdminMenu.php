<?php
/**
 * Admin menu and screens.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Contracts\HookableInterface;
use BoneHornCrafts\Core\Security\Capabilities;

/**
 * Registers the "Bone Horn Crafts" admin section.
 *
 * Three screens, each with a single job: an operations dashboard, a health
 * check, and settings. Screen rendering lives in dedicated classes so this file
 * stays a routing table.
 */
final class AdminMenu implements HookableInterface {

	public const SLUG          = 'bhc-commerce';
	public const HEALTH_SLUG   = 'bhc-commerce-health';
	public const SETTINGS_SLUG = 'bhc-commerce-settings';

	/**
	 * Constructor.
	 *
	 * @param DashboardPage $dashboard Dashboard screen.
	 * @param HealthPage    $health    Health screen.
	 * @param SettingsPage  $settings  Settings screen.
	 */
	public function __construct(
		private DashboardPage $dashboard,
		private HealthPage $health,
		private SettingsPage $settings
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this->settings, 'handle_submission' ] );
	}

	/**
	 * Registers the menu and submenus.
	 */
	public function register_menu(): void {
		$capability = Capabilities::can_manage() ? Capabilities::MANAGE_COMMERCE : 'manage_woocommerce';

		add_menu_page(
			__( 'Bone Horn Crafts Commerce', 'bhc-commerce-core' ),
			__( 'Bone Horn Crafts', 'bhc-commerce-core' ),
			$capability,
			self::SLUG,
			[ $this->dashboard, 'render' ],
			'dashicons-hammer',
			56
		);

		add_submenu_page(
			self::SLUG,
			__( 'Operations dashboard', 'bhc-commerce-core' ),
			__( 'Dashboard', 'bhc-commerce-core' ),
			$capability,
			self::SLUG,
			[ $this->dashboard, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			__( 'System health', 'bhc-commerce-core' ),
			__( 'Health check', 'bhc-commerce-core' ),
			$capability,
			self::HEALTH_SLUG,
			[ $this->health, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			__( 'Commerce settings', 'bhc-commerce-core' ),
			__( 'Settings', 'bhc-commerce-core' ),
			$capability,
			self::SETTINGS_SLUG,
			[ $this->settings, 'render' ]
		);
	}
}
