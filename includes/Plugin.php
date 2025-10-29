<?php
namespace WC_Moneybird;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Plugin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // Initialize components
        new Admin\Settings();
        new Admin\Order_Metabox();
        new Sync\Order_Handler();
    }

    public function admin_notices() {
        if (isset($_GET['moneybird_synced']) && $_GET['moneybird_synced'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 esc_html__('Order synced to Moneybird successfully!', 'woocommerce-moneybird') .
                 '</p></div>';
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Moneybird Sync', 'woocommerce-moneybird'),
            __('Moneybird', 'woocommerce-moneybird'),
            'manage_woocommerce',
            'wc-moneybird',
            [$this, 'render_main_page'],
            'dashicons-cloud-upload',
            56
        );

        add_submenu_page(
            'wc-moneybird',
            __('Settings', 'woocommerce-moneybird'),
            __('Settings', 'woocommerce-moneybird'),
            'manage_woocommerce',
            'wc-moneybird',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'wc-moneybird',
            __('Sync History', 'woocommerce-moneybird'),
            __('Sync History', 'woocommerce-moneybird'),
            'manage_woocommerce',
            'wc-moneybird-history',
            [$this, 'render_history_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wc-moneybird') === false) {
            return;
        }

        wp_enqueue_style(
            'wc-moneybird-admin',
            WC_MONEYBIRD_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WC_MONEYBIRD_VERSION
        );
    }

    public function render_main_page() {
        $settings = new Admin\Settings();
        $settings->render();
    }

    public function render_history_page() {
        $history = new Admin\Sync_History();
        $history->render();
    }
}
