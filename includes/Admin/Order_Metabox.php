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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_wc_moneybird_manual_sync', [$this, 'handle_manual_sync_ajax']);
    }

    public function enqueue_scripts($hook) {
        // Only load on order edit screens
        if (!in_array($hook, ['post.php', 'woocommerce_page_wc-orders'])) {
            return;
        }

        global $post;
        if ($post && $post->post_type !== 'shop_order') {
            return;
        }

        wp_enqueue_script(
            'wc-moneybird-admin-order',
            WC_MONEYBIRD_PLUGIN_URL . 'assets/js/admin-order.js',
            ['jquery'],
            WC_MONEYBIRD_VERSION,
            true
        );
    }

    public function add_metabox() {
        $screen = 'shop_order';

        // Support HPOS (High-Performance Order Storage) if available
        if (function_exists('wc_get_page_screen_id')) {
            $screen = wc_get_page_screen_id('shop-order');
        }

        add_meta_box(
            'wc_moneybird_sync_status',
            __('Moneybird Sync', 'moneybird-for-woocommerce'),
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
		if(!$order->get_status() || $order->get_status() === 'draft'){
			return;
		}

        $order_id = $order->get_id();
        $invoice_id = get_post_meta($order_id, '_moneybird_invoice_id', true);
        $synced_at = get_post_meta($order_id, '_moneybird_synced_at', true);

        $last_log = \WC_Moneybird\Sync_Log::get_last_log($order_id);

        ?>
        <div class="wc-moneybird-metabox">
            <?php if (!empty($invoice_id)): ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'moneybird-for-woocommerce'); ?></strong>
                    <span class="wc-moneybird-status-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Synced', 'moneybird-for-woocommerce'); ?>
                    </span>
                </p>

                <p>
                    <strong><?php esc_html_e('Synced at:', 'moneybird-for-woocommerce'); ?></strong><br>
                    <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($synced_at))); ?>
                </p>

                <p>
                    <?php
                    $administration_id = get_option('wc_moneybird_administration_id');
                    $url = sprintf(
                        'https://moneybird.com/%s/external_sales_invoices/%s',
                        $administration_id,
                        $invoice_id
                    );
                    ?>
                    <a href="<?php echo esc_url($url); ?>" target="_blank" class="button button-secondary">
                        <?php esc_html_e('View in Moneybird', 'moneybird-for-woocommerce'); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                </p>

            <?php elseif ($last_log && $last_log->status === 'error'): ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'moneybird-for-woocommerce'); ?></strong>
                    <span class="wc-moneybird-status-error">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Sync Failed', 'moneybird-for-woocommerce'); ?>
                    </span>
                </p>

                <p>
                    <strong><?php esc_html_e('Error:', 'moneybird-for-woocommerce'); ?></strong><br>
                    <?php echo esc_html($last_log->message); ?>
                </p>

                <p>
                    <button type="button"
                            class="button button-primary wc-moneybird-sync-button"
                            data-order-id="<?php echo esc_attr($order_id); ?>"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('wc_moneybird_manual_sync_' . $order_id)); ?>">
                        <?php esc_html_e('Retry Sync', 'moneybird-for-woocommerce'); ?>
                    </button>
                    <span class="wc-moneybird-sync-status"></span>
                </p>

            <?php else: ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'moneybird-for-woocommerce'); ?></strong>
                    <?php esc_html_e('Not synced', 'moneybird-for-woocommerce'); ?>
                </p>

                <p class="description">
                    <?php
                    $sync_status = get_option('wc_moneybird_sync_on_status', 'completed');
                    /* translators: %s: The order status that triggers automatic sync (e.g., "completed" or "processing") */
                    printf(
                        esc_html__('This order will be automatically synced when it reaches %s status.', 'moneybird-for-woocommerce'),
                        '<strong>' . esc_html($sync_status) . '</strong>'
                    );
                    ?>
                </p>

                <p>
                    <button type="button"
                            class="button button-secondary wc-moneybird-sync-button"
                            data-order-id="<?php echo esc_attr($order_id); ?>"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('wc_moneybird_manual_sync_' . $order_id)); ?>">
                        <?php esc_html_e('Sync Now', 'moneybird-for-woocommerce'); ?>
                    </button>
                    <span class="wc-moneybird-sync-status"></span>
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

    public function handle_manual_sync_ajax() {
        // Verify nonce
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'wc_moneybird_manual_sync_' . $order_id)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'moneybird-for-woocommerce')
            ]);
        }

        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error([
                'message' => __('You do not have permission to sync orders', 'moneybird-for-woocommerce')
            ]);
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error([
                'message' => __('Order not found', 'moneybird-for-woocommerce')
            ]);
        }

        // Clear existing invoice ID to allow re-sync
        delete_post_meta($order_id, '_moneybird_invoice_id');

        // Perform sync
        try {
            $handler = new Order_Handler();
            $handler->sync_order($order);

            // Check if sync was successful
            $invoice_id = get_post_meta($order_id, '_moneybird_invoice_id', true);

            if (!empty($invoice_id)) {
                wp_send_json_success([
                    'message' => __('Order synced successfully!', 'moneybird-for-woocommerce')
                ]);
            } else {
                // Check sync log for error
                $last_log = \WC_Moneybird\Sync_Log::get_last_log($order_id);

                $error_message = $last_log && $last_log->status === 'error'
                    ? $last_log->message
                    : __('Sync failed. Check the sync history for details.', 'moneybird-for-woocommerce');

                wp_send_json_error([
                    'message' => $error_message
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}
