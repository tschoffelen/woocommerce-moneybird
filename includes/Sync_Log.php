<?php
namespace WC_Moneybird;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized sync log database operations
 */
class Sync_Log {
    /**
     * Get the table name
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wc_moneybird_sync_log';
    }

    /**
     * Log a successful sync
     *
     * @param int $order_id The order ID
     * @param array $result The API result
     * @return int|false The number of rows inserted, or false on error
     */
    public static function log_success($order_id, $result) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->insert(
            self::get_table_name(),
            [
                'order_id' => $order_id,
                'status' => 'success',
                'message' => __('Successfully synced to Moneybird', 'moneybird-for-woocommerce'),
                'invoice_id' => $result['id'] ?? null,
                'synced_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Log a failed sync
     *
     * @param int $order_id The order ID
     * @param string $message The error message
     * @return int|false The number of rows inserted, or false on error
     */
    public static function log_error($order_id, $message) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->insert(
            self::get_table_name(),
            [
                'order_id' => $order_id,
                'status' => 'error',
                'message' => $message,
                'synced_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    /**
     * Get the last sync log entry for an order
     *
     * @param int $order_id The order ID
     * @return object|null The log entry or null if not found
     */
    public static function get_last_log($order_id) {
        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY synced_at DESC LIMIT 1",
                $order_id
            )
        );
    }

    /**
     * Get all sync logs with pagination
     *
     * @param int $limit Number of entries to return
     * @param int $offset Offset for pagination
     * @return array Array of log entries
     */
    public static function get_all_logs($limit = 20, $offset = 0) {
        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY synced_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get total count of sync log entries
     *
     * @return int Total number of log entries
     */
    public static function get_total_count() {
        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }
}
