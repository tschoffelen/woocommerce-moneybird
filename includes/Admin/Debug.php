<?php
namespace WC_Moneybird\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug information page
 */
class Debug {
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Moneybird Debug Info', 'woocommerce-moneybird'); ?></h1>

            <div class="wc-moneybird-card">
                <h2><?php esc_html_e('Configuration Status', 'woocommerce-moneybird'); ?></h2>

                <?php
                $api_token = get_option('wc_moneybird_api_token');
                $administration_id = get_option('wc_moneybird_administration_id');
                $ledger_account_id = get_option('wc_moneybird_ledger_account_id');
                $tax_rate_id = get_option('wc_moneybird_tax_rate_id');
                $sync_on_status = get_option('wc_moneybird_sync_on_status', 'completed');
                ?>

                <table class="widefat">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('API Token', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <?php if (!empty($api_token)): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php echo esc_html(substr($api_token, 0, 10) . '...' . substr($api_token, -4)); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php esc_html_e('Not set', 'woocommerce-moneybird'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Administration ID', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <?php if (!empty($administration_id)): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php echo esc_html($administration_id); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php esc_html_e('Not set', 'woocommerce-moneybird'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Ledger Account ID', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <?php if (!empty($ledger_account_id)): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php echo esc_html($ledger_account_id); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php esc_html_e('Not set', 'woocommerce-moneybird'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Tax Rate ID', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <?php if (!empty($tax_rate_id)): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php echo esc_html($tax_rate_id); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-info" style="color: #999;"></span>
                                    <?php esc_html_e('Not set (optional)', 'woocommerce-moneybird'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Sync on Status', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php echo esc_html($sync_on_status); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p style="margin-top: 20px;">
                    <strong>
                        <?php if (!empty($api_token) && !empty($administration_id) && !empty($ledger_account_id)): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php esc_html_e('Plugin is fully configured and ready to sync orders!', 'woocommerce-moneybird'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                            <?php esc_html_e('Plugin is not fully configured. Please complete setup in Settings.', 'woocommerce-moneybird'); ?>
                        <?php endif; ?>
                    </strong>
                </p>
            </div>

            <div class="wc-moneybird-card">
                <h2><?php esc_html_e('Recent Sync Attempts', 'woocommerce-moneybird'); ?></h2>

                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'wc_moneybird_sync_log';

                // Check if table exists
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

                if (!$table_exists): ?>
                    <p><?php esc_html_e('Sync log table does not exist. Please deactivate and reactivate the plugin.', 'woocommerce-moneybird'); ?></p>
                <?php else:
                    $recent_logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY synced_at DESC LIMIT 10");

                    if (empty($recent_logs)): ?>
                        <p><?php esc_html_e('No sync attempts yet. Change an order to the configured status to trigger a sync.', 'woocommerce-moneybird'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Order ID', 'woocommerce-moneybird'); ?></th>
                                    <th><?php esc_html_e('Status', 'woocommerce-moneybird'); ?></th>
                                    <th><?php esc_html_e('Message', 'woocommerce-moneybird'); ?></th>
                                    <th><?php esc_html_e('Time', 'woocommerce-moneybird'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $log->order_id . '&action=edit')); ?>">
                                                #<?php echo esc_html($log->order_id); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($log->status === 'success'): ?>
                                                <span style="color: #46b450;">✓ <?php esc_html_e('Success', 'woocommerce-moneybird'); ?></span>
                                            <?php else: ?>
                                                <span style="color: #d63638;">✗ <?php esc_html_e('Error', 'woocommerce-moneybird'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($log->message); ?></td>
                                        <td><?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($log->synced_at))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif;
                endif; ?>
            </div>

            <div class="wc-moneybird-card">
                <h2><?php esc_html_e('Hooks Status', 'woocommerce-moneybird'); ?></h2>

                <?php
                global $wp_filter;
                $hook_name = 'woocommerce_order_status_changed';
                $has_hook = isset($wp_filter[$hook_name]) && !empty($wp_filter[$hook_name]->callbacks);
                ?>

                <table class="widefat">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Order Status Hook', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <?php if ($has_hook): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php esc_html_e('Registered', 'woocommerce-moneybird'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php esc_html_e('Not registered', 'woocommerce-moneybird'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Plugin Class', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <?php if (class_exists('\WC_Moneybird\Plugin')): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php esc_html_e('Loaded', 'woocommerce-moneybird'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php esc_html_e('Not loaded', 'woocommerce-moneybird'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Order Handler Class', 'woocommerce-moneybird'); ?></th>
                            <td>
                                <?php if (class_exists('\WC_Moneybird\Sync\Order_Handler')): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php esc_html_e('Loaded', 'woocommerce-moneybird'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php esc_html_e('Not loaded', 'woocommerce-moneybird'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
