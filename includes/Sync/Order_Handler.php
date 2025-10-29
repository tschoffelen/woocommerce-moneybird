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
	private $tax_rates_cache = null;

	public function __construct()
	{
		add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 40, 3);
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
	public function handle_order_status_change($order_id, $old_status, $new_status)
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

		$this->sync_order($order_id);
	}

	/**
	 * Sync order to Moneybird
	 */
	public function sync_order($order_id)
	{
		$order = wc_get_order($order_id);

		// Double-check configuration
		if (!$this->is_configured()) {
			$this->log_sync_error($order_id, __('Moneybird is not fully configured. Please configure API token, administration, and ledger account.', 'woocommerce-moneybird'));
			return;
		}

		try {
			$api = new Api_Client();

			// Step 1: Get or create contact
			$contact_id = $this->get_or_create_contact($order, $api);
			if (is_wp_error($contact_id)) {
				throw new \Exception($contact_id->get_error_message());
			}

			// Step 2: Prepare and create invoice with contact_id
			$invoice_data = $this->prepare_invoice_data($order, $contact_id);
			$result = $api->create_external_sales_invoice($invoice_data);

			if (is_wp_error($result)) {
				throw new \Exception($result->get_error_message());
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
	 * Get or create a Moneybird contact for the order customer
	 */
	private function get_or_create_contact($order, $api)
	{
		$customer_id = $order->get_customer_id();
		$email = $order->get_billing_email();

		// Check if we have a stored contact ID for this customer
		if ($customer_id > 0) {
			$stored_contact_id = get_user_meta($customer_id, '_moneybird_contact_id', true);
			if (!empty($stored_contact_id)) {
				return $stored_contact_id;
			}
		}

		// Try to find existing contact by email
		$existing_contact = $api->find_contact_by_email($email);
		if (is_wp_error($existing_contact)) {
			return $existing_contact;
		}

		if ($existing_contact) {
			$contact_id = $existing_contact['id'];

			// Store for future use
			if ($customer_id > 0) {
				update_user_meta($customer_id, '_moneybird_contact_id', $contact_id);
			}

			return $contact_id;
		}

		// Create new contact
		$contact_data = [
			'company_name' => $this->get_company_name($order),
			'firstname' => $order->get_billing_first_name(),
			'lastname' => $order->get_billing_last_name(),
			'address1' => $order->get_billing_address_1(),
			'address2' => $order->get_billing_address_2(),
			'zipcode' => $order->get_billing_postcode(),
			'city' => $order->get_billing_city(),
			'country' => $order->get_billing_country(),
			'email' => $email,
			'phone' => $order->get_billing_phone(),
			'customer_id' => 'wc_' . ($customer_id > 0 ? $customer_id : $order->get_id()), // External reference
		];

		$result = $api->create_contact($contact_data);

		if (is_wp_error($result)) {
			return $result;
		}

		$contact_id = $result['id'];

		// Store for future use
		if ($customer_id > 0) {
			update_user_meta($customer_id, '_moneybird_contact_id', $contact_id);
		}

		return $contact_id;
	}

	/**
	 * Prepare invoice data for Moneybird
	 */
	private function prepare_invoice_data($order, $contact_id)
	{
		$ledger_account_id = get_option('wc_moneybird_ledger_account_id');

		$order_id = $order->get_id();
		$order_date = $order->get_date_created();

		$details_attributes = [];

		// Add line items
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			$line_total = $item->get_total();
			$line_tax = $item->get_total_tax();
			$quantity = $item->get_quantity();

			$price = $quantity > 0 ? ($line_total / $quantity) : 0;

			// Find matching tax rate from Moneybird
			$tax_rate_to_use = $this->determine_tax_rate($line_total, $line_tax);

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

			$tax_rate_to_use = $this->determine_tax_rate($shipping_total, $shipping_tax);

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

			$tax_rate_to_use = $this->determine_tax_rate($fee_total, $fee_tax);

			$details_attributes[] = [
				'description' => $fee->get_name(),
				'price' => number_format($fee_total, 2, '.', ''),
				'amount' => 1,
				'tax_rate_id' => $tax_rate_to_use,
				'ledger_account_id' => $ledger_account_id,
			];
		}

		return [
			'contact_id' => $contact_id,
			'reference' => 'WC-' . $order_id,
			'date' => $order_date->format('Y-m-d'),
			'due_date' => $order_date->modify('+30 days')->format('Y-m-d'),
			'currency' => $order->get_currency(),
			'details_attributes' => $details_attributes,
			'source' => 'WooCommerce',
			'source_url' => $order->get_edit_order_url(),
		];
	}

	/**
	 * Determine the appropriate tax rate ID by finding a matching rate in Moneybird
	 *
	 * @param float $subtotal Line item subtotal (before tax)
	 * @param float $tax_amount Tax amount for this line item
	 * @throws \Exception If no matching tax rate is found in Moneybird
	 * @return string Tax rate ID from Moneybird
	 */
	private function determine_tax_rate($subtotal, $tax_amount)
	{
		// Calculate the actual tax percentage from the order
		if ($subtotal == 0) {
			$actual_percentage = 0;
		} else {
			$actual_percentage = round(($tax_amount / $subtotal) * 100, 2);
		}

		// Get all available tax rates from Moneybird (cached)
		if ($this->tax_rates_cache === null) {
			$api = new Api_Client();
			$this->tax_rates_cache = $api->get_tax_rates();

			if (is_wp_error($this->tax_rates_cache)) {
				throw new \Exception('Failed to fetch tax rates from Moneybird: ' . $this->tax_rates_cache->get_error_message());
			}
		}

		// Find matching tax rate (with tolerance for rounding differences)
		$tolerance = 0.2; // Allow 0.2% difference for rounding
		foreach ($this->tax_rates_cache as $rate) {
			$rate_percentage = floatval($rate['percentage']);

			if (abs($rate_percentage - $actual_percentage) <= $tolerance) {
				return $rate['id'];
			}
		}

		// No matching tax rate found - this is an error condition
		throw new \Exception(
			sprintf(
				__('No matching tax rate found in Moneybird for %.2f%%. Please add this tax rate in Moneybird or adjust your WooCommerce tax settings.', 'woocommerce-moneybird'),
				$actual_percentage
			)
		);
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
			'https://moneybird.com/%s/external_sales_invoices/%s',
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
