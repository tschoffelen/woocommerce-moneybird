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
			__( 'Moneybird Sync History', 'woocommerce-moneybird' ),
			__( 'Moneybird Sync', 'woocommerce-moneybird' ),
			'manage_woocommerce',
			'wc-moneybird-sync',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the sync history page
	 */
	public function render() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_moneybird_sync_log';
		$per_page   = 20;
		$paged      = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset     = ( $paged - 1 ) * $per_page;

		// Get total count
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$total_pages = ceil( $total_items / $per_page );

		// Get logs
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY synced_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order ID', 'woocommerce-moneybird' ); ?></th>
						<th><?php esc_html_e( 'Moneybird Invoice ID', 'woocommerce-moneybird' ); ?></th>
						<th><?php esc_html_e( 'Status', 'woocommerce-moneybird' ); ?></th>
						<th><?php esc_html_e( 'Message', 'woocommerce-moneybird' ); ?></th>
						<th><?php esc_html_e( 'Synced At', 'woocommerce-moneybird' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No sync history found.', 'woocommerce-moneybird' ); ?></td>
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
