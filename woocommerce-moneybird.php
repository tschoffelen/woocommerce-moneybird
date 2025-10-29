<?php
/**
 * Plugin Name: Sync to Moneybird
 * Description: A WooCommerce plugin to sync orders to Moneybird.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: Thomas Schoffelen
 * Author URI: https://includable.com
 * License: MIT
 * Text Domain: woocommerce-moneybird
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WC_MONEYBIRD_VERSION', '1.0.0');
define('WC_MONEYBIRD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_MONEYBIRD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Composer autoloader
require_once WC_MONEYBIRD_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize plugin
function wc_moneybird_init()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>' .
                esc_html__('Sync to Moneybird requires WooCommerce to be installed and active.', 'woocommerce-moneybird') .
                '</p></div>';
        });
        return;
    }

    \WC_Moneybird\Plugin::instance();
}
add_action('plugins_loaded', 'wc_moneybird_init');

// Activation hook
register_activation_hook(__FILE__, function () {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('This plugin requires WooCommerce to be installed and active.', 'woocommerce-moneybird'),
            'Plugin dependency check',
            ['back_link' => true]
        );
    }

    \WC_Moneybird\Installer::install();
});
