<?php
/**
 * Sync History Admin Page
 *
 * @package WC_Moneybird
 */

namespace WC_Moneybird\Admin;

/**
 * Class Sync_History
 */
class Sync_History {
	/**
	 * Initialize the sync history page
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Moneybird Sync History', 'moneybird-for-woocommerce' ),
			__( 'Moneybird Sync', 'moneybird-for-woocommerce' ),
			'manage_woocommerce',
			'wc-moneybird-sync',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the sync history page
	 */
	public function render() {
		$per_page   = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading page number for pagination
		$paged      = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset     = ( $paged - 1 ) * $per_page;

		// Get total count and logs using centralized Sync_Log class
		$total_items = \WC_Moneybird\Sync_Log::get_total_count();
		$total_pages = ceil( $total_items / $per_page );
		$logs = \WC_Moneybird\Sync_Log::get_all_logs($per_page, $offset);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order ID', 'moneybird-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Moneybird Invoice ID', 'moneybird-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'moneybird-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Message', 'moneybird-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Synced At', 'moneybird-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No sync history found.', 'moneybird-for-woocommerce' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log->order_id . '&action=edit' ) ); ?>">
										#<?php echo esc_html( $log->order_id ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $log->moneybird_invoice_id ?: '-' ); ?></td>
								<td>
									<span class="status-<?php echo esc_attr( $log->status ); ?>">
										<?php echo esc_html( ucfirst( $log->status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log->message ?: '-' ); ?></td>
								<td><?php echo esc_html( $log->synced_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links is safe
						echo paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.status-success { color: #46b450; font-weight: 600; }
			.status-error { color: #dc3232; font-weight: 600; }
		</style>
		<?php
	}
}
