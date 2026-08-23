<?php
/**
 * Plugin container and bootstrap.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Admin\SubscribersPage;
use BoneHornCrafts\Newsletter\CLI\SubscribersCommand;
use BoneHornCrafts\Newsletter\Database\SchemaInstaller;
use BoneHornCrafts\Newsletter\Http\LinkHandler;
use BoneHornCrafts\Newsletter\Http\RestController;
use BoneHornCrafts\Newsletter\Repository\SubscriberRepository;
use BoneHornCrafts\Newsletter\Service\ConfirmationMailer;
use BoneHornCrafts\Newsletter\Service\CsvExporter;
use BoneHornCrafts\Newsletter\Service\SubscriptionService;
use WP_CLI;

/**
 * Wires the plugin together.
 *
 * A small hand-rolled container rather than a dependency on the core plugin's:
 * this plugin has eight services and one graph, and borrowing another plugin's
 * container would mean it could not be activated without that plugin. It reuses
 * core services where they exist — the rate limiter — and degrades cleanly
 * where they do not.
 *
 * Services are built lazily. The admin screen is never constructed on a
 * front-end request, and the CLI command is never constructed in a browser.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Resolved services, keyed by class name.
	 *
	 * @var array<string, object>
	 */
	private array $services = [];

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private string $file;

	/**
	 * Constructor.
	 *
	 * @param string $file Main plugin file.
	 */
	private function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Returns the instance.
	 *
	 * @param string $file Main plugin file.
	 */
	public static function instance( string $file = '' ): self {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self( $file );
		}

		return self::$instance;
	}

	/**
	 * Boots the plugin.
	 */
	public function boot(): void {
		$this->schema()->maybe_install();

		$this->rest()->register_hooks();
		$this->links()->register_hooks();

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

		if ( is_admin() ) {
			$this->admin()->register_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli();
		}
	}

	/**
	 * Runs on activation.
	 */
	public static function activate(): void {
		( new SchemaInstaller() )->install();
	}

	/**
	 * Enqueues the front-end script.
	 */
	public function enqueue(): void {
		$handle = 'bhc-newsletter';
		$path   = plugin_dir_path( $this->file ) . 'assets/js/newsletter.js';

		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/js/newsletter.js', $this->file ),
			[],
			(string) ( file_exists( $path ) ? filemtime( $path ) : '1.0.0' ),
			true
		);

		wp_script_add_data( $handle, 'strategy', 'defer' );

		wp_localize_script(
			$handle,
			'bhcNewsletter',
			[
				'endpoint' => rest_url( 'bhc-newsletter/v1/subscribe' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'strings'  => [
					'working' => __( 'Sending…', 'bhc-newsletter' ),
					'error'   => __( 'Something went wrong. Please try again.', 'bhc-newsletter' ),
					'invalid' => __( 'Please enter a valid email address.', 'bhc-newsletter' ),
				],
			]
		);
	}

	/**
	 * Schema installer.
	 */
	public function schema(): SchemaInstaller {
		return $this->service( SchemaInstaller::class, static fn (): SchemaInstaller => new SchemaInstaller() );
	}

	/**
	 * Subscriber storage.
	 */
	public function repository(): SubscriberRepository {
		return $this->service( SubscriberRepository::class, static fn (): SubscriberRepository => new SubscriberRepository() );
	}

	/**
	 * Outgoing mail.
	 */
	public function mailer(): ConfirmationMailer {
		return $this->service( ConfirmationMailer::class, static fn (): ConfirmationMailer => new ConfirmationMailer() );
	}

	/**
	 * Subscription lifecycle.
	 */
	public function subscriptions(): SubscriptionService {
		return $this->service(
			SubscriptionService::class,
			fn (): SubscriptionService => new SubscriptionService( $this->repository(), $this->mailer() )
		);
	}

	/**
	 * CSV export.
	 */
	public function exporter(): CsvExporter {
		return $this->service( CsvExporter::class, fn (): CsvExporter => new CsvExporter( $this->repository() ) );
	}

	/**
	 * REST controller.
	 */
	public function rest(): RestController {
		return $this->service( RestController::class, fn (): RestController => new RestController( $this->subscriptions() ) );
	}

	/**
	 * Confirmation and unsubscribe links.
	 */
	public function links(): LinkHandler {
		return $this->service( LinkHandler::class, fn (): LinkHandler => new LinkHandler( $this->subscriptions() ) );
	}

	/**
	 * Admin screen.
	 */
	public function admin(): SubscribersPage {
		return $this->service(
			SubscribersPage::class,
			fn (): SubscribersPage => new SubscribersPage( $this->repository(), $this->exporter() )
		);
	}

	/**
	 * Registers WP-CLI commands.
	 */
	private function register_cli(): void {
		$command = new SubscribersCommand( $this->repository(), $this->exporter() );

		WP_CLI::add_command(
			'bhc newsletter export',
			static function ( array $args, array $assoc_args ) use ( $command ): void {
				$command->export( $args, $assoc_args );
			},
			[ 'shortdesc' => 'Export newsletter subscribers as CSV.' ]
		);

		WP_CLI::add_command(
			'bhc newsletter status',
			static function ( array $args, array $assoc_args ) use ( $command ): void {
				$command->status( $args, $assoc_args );
			},
			[ 'shortdesc' => 'Show subscriber counts per state.' ]
		);
	}

	/**
	 * Resolves a service once and caches it.
	 *
	 * @template T of object
	 *
	 * @param string   $id      Service id (a class-string<T>).
	 * @param callable $factory Factory returning T.
	 *
	 * @return T
	 */
	private function service( string $id, callable $factory ): object {
		if ( ! isset( $this->services[ $id ] ) ) {
			$this->services[ $id ] = $factory();
		}

		/** @var T */
		return $this->services[ $id ];
	}
}
