<?php
/**
 * Subscribers admin screen.
 *
 * @package BoneHornCrafts\Newsletter
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Newsletter\Admin;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Newsletter\Domain\Subscriber;
use BoneHornCrafts\Newsletter\Domain\SubscriberStatus;
use BoneHornCrafts\Newsletter\Repository\SubscriberRepository;
use BoneHornCrafts\Newsletter\Service\CsvExporter;

/**
 * Lists subscribers and exports them.
 *
 * Deliberately not a `WP_List_Table` subclass: that class is marked private in
 * core, changes without notice between releases, and would be roughly the same
 * amount of code for a screen with one filter, one search box and no bulk
 * actions. This renders the same markup directly against core's table classes.
 */
final class SubscribersPage {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'bhc-newsletter';

	/**
	 * Capability required to see and export the list.
	 */
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Rows per page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Constructor.
	 *
	 * @param SubscriberRepository $repository Storage.
	 * @param CsvExporter          $exporter   CSV export.
	 */
	public function __construct(
		private SubscriberRepository $repository,
		private CsvExporter $exporter
	) {}

	/**
	 * Registers hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_post_bhc_newsletter_export', [ $this, 'export' ] );
	}

	/**
	 * Adds the menu entry.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Newsletter subscribers', 'bhc-newsletter' ),
			__( 'Subscribers', 'bhc-newsletter' ),
			self::CAPABILITY,
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'bhc-newsletter' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters on an admin screen; no state changes here.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$results = $this->repository->query(
			[
				'status'   => $status,
				'search'   => $search,
				'page'     => $page,
				'per_page' => self::PER_PAGE,
			]
		);

		$counts = $this->repository->counts();
		$total  = array_sum( $counts );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Newsletter subscribers', 'bhc-newsletter' ); ?></h1>

			<a class="page-title-action" href="<?php echo esc_url( $this->export_url( $status ) ); ?>">
				<?php esc_html_e( 'Export CSV', 'bhc-newsletter' ); ?>
			</a>

			<hr class="wp-header-end">

			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( $this->filter_url( '' ) ); ?>" class="<?php echo '' === $status ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'bhc-newsletter' ); ?>
						<span class="count">(<?php echo (int) $total; ?>)</span>
					</a>
				</li>
				<?php foreach ( SubscriberStatus::cases() as $case ) : ?>
					<li>
						| <a href="<?php echo esc_url( $this->filter_url( $case->value ) ); ?>" class="<?php echo $status === $case->value ? 'current' : ''; ?>">
							<?php echo esc_html( $case->label() ); ?>
							<span class="count">(<?php echo (int) ( $counts[ $case->value ] ?? 0 ); ?>)</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
				<?php if ( '' !== $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="bhc-nl-search"><?php esc_html_e( 'Search addresses', 'bhc-newsletter' ); ?></label>
					<input type="search" id="bhc-nl-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
					<?php submit_button( __( 'Search', 'bhc-newsletter' ), '', '', false ); ?>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Email', 'bhc-newsletter' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'bhc-newsletter' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source', 'bhc-newsletter' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Subscribed', 'bhc-newsletter' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Confirmed', 'bhc-newsletter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( [] === $results['items'] ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No subscribers match this view.', 'bhc-newsletter' ); ?></td>
						</tr>
					<?php endif; ?>

					<?php foreach ( $results['items'] as $subscriber ) : ?>
						<?php /** @var Subscriber $subscriber */ ?>
						<tr>
							<td><strong><?php echo esc_html( $subscriber->email ); ?></strong></td>
							<td><?php echo esc_html( $subscriber->status->label() ); ?></td>
							<td><?php echo esc_html( $subscriber->source ); ?></td>
							<td><?php echo esc_html( $this->local( $subscriber->created_at ) ); ?></td>
							<td><?php echo esc_html( $subscriber->confirmed_at ? $this->local( $subscriber->confirmed_at ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php $this->render_pagination( $results['total'], $page, $status, $search ); ?>
		</div>
		<?php
	}

	/**
	 * Streams the CSV download.
	 */
	public function export(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to export subscribers.', 'bhc-newsletter' ), 403 );
		}

		check_admin_referer( 'bhc_newsletter_export' );

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '';

		nocache_headers();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $this->exporter->filename( $status ) );

		// WP_Filesystem reads and writes whole files; an unbounded export has to
		// stream, so the row writer needs a real handle.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( 'php://output', 'w' );

		if ( false === $handle ) {
			wp_die( esc_html__( 'Could not open the output stream.', 'bhc-newsletter' ) );
		}

		$this->exporter->write( $handle, [ 'status' => $status ] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		exit;
	}

	/**
	 * URL for the export action.
	 *
	 * @param string $status Current status filter.
	 */
	private function export_url( string $status ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action' => 'bhc_newsletter_export',
					'status' => $status,
				],
				admin_url( 'admin-post.php' )
			),
			'bhc_newsletter_export'
		);
	}

	/**
	 * URL for a status filter.
	 *
	 * @param string $status Status value, or an empty string for all.
	 */
	private function filter_url( string $status ): string {
		$args = [ 'page' => self::SLUG ];

		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Formats a GMT datetime in the site's timezone.
	 *
	 * @param string $gmt MySQL datetime in GMT.
	 */
	private function local( string $gmt ): string {
		if ( '' === $gmt ) {
			return '—';
		}

		return (string) get_date_from_gmt( $gmt, (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) );
	}

	/**
	 * Renders pagination links.
	 *
	 * @param int    $total  Total rows.
	 * @param int    $page   Current page.
	 * @param string $status Status filter.
	 * @param string $search Search term.
	 */
	private function render_pagination( int $total, int $page, string $status, string $search ): void {
		$pages = (int) ceil( $total / self::PER_PAGE );

		if ( $pages < 2 ) {
			return;
		}

		$base = [ 'page' => self::SLUG ];

		if ( '' !== $status ) {
			$base['status'] = $status;
		}

		if ( '' !== $search ) {
			$base['s'] = $search;
		}

		echo '<div class="tablenav bottom"><div class="tablenav-pages">';

		echo wp_kses_post(
			paginate_links(
				[
					'base'      => add_query_arg( array_merge( $base, [ 'paged' => '%#%' ] ), admin_url( 'admin.php' ) ),
					'format'    => '',
					'current'   => $page,
					'total'     => $pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				]
			) ?? ''
		);

		echo '</div></div>';
	}
}
