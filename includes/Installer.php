<?php
namespace WC_Moneybird;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin installer
 */
class Installer {
    public static function install() {
        self::create_tables();
        self::create_options();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'wc_moneybird_sync_log';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            invoice_id varchar(100),
            synced_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY synced_at (synced_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Create default options
     */
    private static function create_options() {
        add_option('wc_moneybird_sync_on_status', 'completed');
    }
}
