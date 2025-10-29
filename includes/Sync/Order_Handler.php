<?php
namespace WC_Moneybird\Sync;

use WC_Moneybird\Api_Client;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles WooCommerce order syncing to Moneybird
 */
class Order_Handler
{
	public function __construct()
	{
		add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 4);
	}

	/**
	 * Check if all required settings are configured
	 */
	private function is_configured()
	{
		$api_token = get_option('wc_moneybird_api_token');
		$administration_id = get_option('wc_moneybird_administration_id');
		$ledger_account_id = get_option('wc_moneybird_ledger_account_id');

		return !empty($api_token) && !empty($administration_id) && !empty($ledger_account_id);
	}

	/**
	 * Handle order status changes
	 */
	public function handle_order_status_change($order_id, $old_status, $new_status, $order)
	{
		// Check if plugin is configured
		if (!$this->is_configured()) {
			return;
		}

		// Check if this is the status we're configured to sync on
		$sync_on_status = get_option('wc_moneybird_sync_on_status', 'completed');
		if ($new_status !== $sync_on_status) {
			return;
		}

		// Check if already synced
		$synced_invoice_id = get_post_meta($order_id, '_moneybird_invoice_id', true);
		if (!empty($synced_invoice_id)) {
			return;
		}

		$this->sync_order($order);
	}

	/**
	 * Sync order to Moneybird
	 */
	public function sync_order($order)
	{
		$order_id = $order->get_id();

		// Double-check configuration
		if (!$this->is_configured()) {
			$this->log_sync_error($order_id, __('Moneybird is not fully configured. Please configure API token, administration, and ledger account.', 'woocommerce-moneybird'));
			return;
		}

		try {
			$invoice_data = $this->prepare_invoice_data($order);
			$api = new Api_Client();
			$result = $api->create_external_sales_invoice($invoice_data);

			if (is_wp_error($result)) {
				throw new \Exception(json_encode($result->get_error_message()));
			}

			// Save the invoice ID
			update_post_meta($order_id, '_moneybird_invoice_id', $result['id']);
			update_post_meta($order_id, '_moneybird_synced_at', current_time('mysql'));

			// Log success
			$this->log_sync_success($order_id, $result);

			// Add order note
			$this->add_order_note($order, $result);

		} catch (\Exception $e) {
			$this->log_sync_error($order_id, $e->getMessage());
		}
	}

	/**
	 * Prepare invoice data for Moneybird
	 */
	private function prepare_invoice_data($order)
	{
		$ledger_account_id = get_option('wc_moneybird_ledger_account_id');
		$tax_rate_id = get_option('wc_moneybird_tax_rate_id');

		$order_id = $order->get_id();
		$order_date = $order->get_date_created();

		$contact_data = [
			'company_name' => $this->get_company_name($order),
			'firstname' => $order->get_billing_first_name(),
			'lastname' => $order->get_billing_last_name(),
			'address1' => $order->get_billing_address_1(),
			'address2' => $order->get_billing_address_2(),
			'zipcode' => $order->get_billing_postcode(),
			'city' => $order->get_billing_city(),
			'country' => $order->get_billing_country(),
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
		];

		$details_attributes = [];

		// Add line items
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			$line_total = $item->get_total();
			$line_tax = $item->get_total_tax();
			$quantity = $item->get_quantity();

			$price = $quantity > 0 ? ($line_total / $quantity) : 0;

			// Calculate expected tax for the configured tax rate
			$tax_rate_to_use = $this->determine_tax_rate($line_total, $line_tax, $tax_rate_id);

			$details_attributes[] = [
				'description' => $item->get_name(),
				'price' => number_format($price, 2, '.', ''),
				'amount' => $quantity,
				'tax_rate_id' => $tax_rate_to_use,
				'ledger_account_id' => $ledger_account_id,
			];
		}

		// Add shipping
		if ($order->get_shipping_total() > 0) {
			$shipping_total = $order->get_shipping_total();
			$shipping_tax = $order->get_shipping_tax();

			$tax_rate_to_use = $this->determine_tax_rate($shipping_total, $shipping_tax, $tax_rate_id);

			$details_attributes[] = [
				'description' => __('Shipping', 'woocommerce-moneybird') . ': ' . $order->get_shipping_method(),
				'price' => number_format($shipping_total, 2, '.', ''),
				'amount' => 1,
				'tax_rate_id' => $tax_rate_to_use,
				'ledger_account_id' => $ledger_account_id,
			];
		}

		// Add fees
		foreach ($order->get_fees() as $fee) {
			$fee_total = $fee->get_total();
			$fee_tax = $fee->get_total_tax();

			$tax_rate_to_use = $this->determine_tax_rate($fee_total, $fee_tax, $tax_rate_id);

			$details_attributes[] = [
				'description' => $fee->get_name(),
				'price' => number_format($fee_total, 2, '.', ''),
				'amount' => 1,
				'tax_rate_id' => $tax_rate_to_use,
				'ledger_account_id' => $ledger_account_id,
			];
		}

		return [
			'reference' => 'WC-' . $order_id,
			'date' => $order_date->format('Y-m-d'),
			'due_date' => $order_date->modify('+30 days')->format('Y-m-d'),
			'contact' => $contact_data,
			'details_attributes' => $details_attributes,
			'source' => 'WooCommerce',
			'source_url' => $order->get_edit_order_url(),
		];
	}

	/**
	 * Determine the appropriate tax rate ID
	 * Only use the configured tax rate if the tax amount matches what we'd expect
	 */
	private function determine_tax_rate($subtotal, $tax_amount, $configured_tax_rate_id)
	{
		if (empty($configured_tax_rate_id) || $tax_amount == 0) {
			return null;
		}

		// Get the configured tax rate percentage
		$api = new Api_Client();
		$tax_rates = $api->get_tax_rates();

		if (is_wp_error($tax_rates)) {
			return null;
		}

		$configured_rate = null;
		foreach ($tax_rates as $rate) {
			if ($rate['id'] == $configured_tax_rate_id) {
				$configured_rate = floatval($rate['percentage']);
				break;
			}
		}

		if ($configured_rate === null) {
			return null;
		}

		// Calculate expected tax
		$expected_tax = round(($subtotal * $configured_rate) / 100, 2);
		$actual_tax = round(floatval($tax_amount), 2);

		// If they match (within a small margin), use the configured rate
		if (abs($expected_tax - $actual_tax) < 0.02) {
			return $configured_tax_rate_id;
		}

		return null;
	}

	/**
	 * Get company name or fallback to customer name
	 */
	private function get_company_name($order)
	{
		$company = $order->get_billing_company();

		if (!empty($company)) {
			return $company;
		}

		return trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
	}

	/**
	 * Add order note with Moneybird link
	 */
	private function add_order_note($order, $result)
	{
		$administration_id = get_option('wc_moneybird_administration_id');
		$invoice_id = $result['id'];

		$url = sprintf(
			'https://moneybird.com/%s/sales_invoices/%s',
			$administration_id,
			$invoice_id
		);

		$note = sprintf(
			__('Order synced to Moneybird. View invoice: %s', 'woocommerce-moneybird'),
			'<a href="' . esc_url($url) . '" target="_blank">' . esc_html($result['reference']) . '</a>'
		);

		$order->add_order_note($note, false, true);
	}

	/**
	 * Log sync success
	 */
	private function log_sync_success($order_id, $result)
	{
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wc_moneybird_sync_log',
			[
				'order_id' => $order_id,
				'status' => 'success',
				'message' => __('Successfully synced to Moneybird', 'woocommerce-moneybird'),
				'invoice_id' => $result['id'],
				'synced_at' => current_time('mysql'),
			],
			['%d', '%s', '%s', '%s', '%s']
		);
	}

	/**
	 * Log sync error
	 */
	private function log_sync_error($order_id, $message)
	{
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wc_moneybird_sync_log',
			[
				'order_id' => $order_id,
				'status' => 'error',
				'message' => $message,
				'synced_at' => current_time('mysql'),
			],
			['%d', '%s', '%s', '%s']
		);

		// Also add an order note
		$order = wc_get_order($order_id);
		if ($order) {
			$order->add_order_note(
				sprintf(__('Failed to sync to Moneybird: %s', 'woocommerce-moneybird'), $message),
				false,
				true
			);
		}
	}
}
