<?php
namespace WC_Moneybird\Admin;

use WC_Moneybird\Sync\Order_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order edit screen metabox
 */
class Order_Metabox {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('admin_post_wc_moneybird_manual_sync', [$this, 'handle_manual_sync']);
    }

    public function add_metabox() {
        $screen = 'shop_order';

        // Support HPOS (High-Performance Order Storage) if available
        if (function_exists('wc_get_page_screen_id')) {
            $screen = wc_get_page_screen_id('shop-order');
        }

        add_meta_box(
            'wc_moneybird_sync_status',
            __('Moneybird Sync', 'woocommerce-moneybird'),
            [$this, 'render_metabox'],
            $screen,
            'side',
            'default'
        );
    }

    public function render_metabox($post_or_order_object) {
        // Support both post object (classic orders) and order object (HPOS)
        $order = $post_or_order_object instanceof \WC_Order
            ? $post_or_order_object
            : wc_get_order($post_or_order_object->ID);

        if (!$order) {
            return;
        }

        $order_id = $order->get_id();
        $invoice_id = get_post_meta($order_id, '_moneybird_invoice_id', true);
        $synced_at = get_post_meta($order_id, '_moneybird_synced_at', true);

        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_moneybird_sync_log';
        $last_log = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY synced_at DESC LIMIT 1",
                $order_id
            )
        );

        ?>
        <div class="wc-moneybird-metabox">
            <?php if (!empty($invoice_id)): ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'woocommerce-moneybird'); ?></strong>
                    <span class="wc-moneybird-status-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Synced', 'woocommerce-moneybird'); ?>
                    </span>
                </p>

                <p>
                    <strong><?php esc_html_e('Synced at:', 'woocommerce-moneybird'); ?></strong><br>
                    <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($synced_at))); ?>
                </p>

                <p>
                    <?php
                    $administration_id = get_option('wc_moneybird_administration_id');
                    $url = sprintf(
                        'https://moneybird.com/%s/sales_invoices/%s',
                        $administration_id,
                        $invoice_id
                    );
                    ?>
                    <a href="<?php echo esc_url($url); ?>" target="_blank" class="button button-secondary">
                        <?php esc_html_e('View in Moneybird', 'woocommerce-moneybird'); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                </p>

            <?php elseif ($last_log && $last_log->status === 'error'): ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'woocommerce-moneybird'); ?></strong>
                    <span class="wc-moneybird-status-error">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Sync Failed', 'woocommerce-moneybird'); ?>
                    </span>
                </p>

                <p>
                    <strong><?php esc_html_e('Error:', 'woocommerce-moneybird'); ?></strong><br>
                    <?php echo esc_html($last_log->message); ?>
                </p>

                <p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wc_moneybird_manual_sync', 'wc_moneybird_manual_sync_nonce'); ?>
                        <input type="hidden" name="action" value="wc_moneybird_manual_sync">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Retry Sync', 'woocommerce-moneybird'); ?>
                        </button>
                    </form>
                </p>

            <?php else: ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'woocommerce-moneybird'); ?></strong>
                    <?php esc_html_e('Not synced', 'woocommerce-moneybird'); ?>
                </p>

                <p class="description">
                    <?php
                    $sync_status = get_option('wc_moneybird_sync_on_status', 'completed');
                    printf(
                        esc_html__('This order will be automatically synced when it reaches %s status.', 'woocommerce-moneybird'),
                        '<strong>' . esc_html($sync_status) . '</strong>'
                    );
                    ?>
                </p>

                <p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wc_moneybird_manual_sync', 'wc_moneybird_manual_sync_nonce'); ?>
                        <input type="hidden" name="action" value="wc_moneybird_manual_sync">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e('Sync Now', 'woocommerce-moneybird'); ?>
                        </button>
                    </form>
                </p>
            <?php endif; ?>
        </div>

        <style>
            .wc-moneybird-metabox .dashicons {
                vertical-align: middle;
            }
            .wc-moneybird-status-success {
                color: #00a32a;
                font-weight: 500;
            }
            .wc-moneybird-status-error {
                color: #d63638;
                font-weight: 500;
            }
        </style>
        <?php
    }

    public function handle_manual_sync() {
        if (!isset($_POST['wc_moneybird_manual_sync_nonce']) ||
            !wp_verify_nonce($_POST['wc_moneybird_manual_sync_nonce'], 'wc_moneybird_manual_sync')) {
            wp_die(esc_html__('Security check failed', 'woocommerce-moneybird'));
        }

        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have permission to sync orders', 'woocommerce-moneybird'));
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die(esc_html__('Order not found', 'woocommerce-moneybird'));
        }

        // Clear existing invoice ID to allow re-sync
        delete_post_meta($order_id, '_moneybird_invoice_id');

        $handler = new Order_Handler();
        $handler->sync_order($order);

        wp_safe_redirect(
            add_query_arg(
                ['post' => $order_id, 'action' => 'edit', 'moneybird_synced' => '1'],
                admin_url('post.php')
            )
        );
        exit;
    }
}
