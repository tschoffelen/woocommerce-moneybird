<?php
/**
 * Uninstall script
 * Fired when the plugin is uninstalled
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('wc_moneybird_api_token');
delete_option('wc_moneybird_administration_id');
delete_option('wc_moneybird_ledger_account_id');
delete_option('wc_moneybird_tax_rate_id');
delete_option('wc_moneybird_sync_on_status');

// Delete sync log table
global $wpdb;
$table_name = $wpdb->prefix . 'wc_moneybird_sync_log';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_moneybird_invoice_id', '_moneybird_synced_at')");
