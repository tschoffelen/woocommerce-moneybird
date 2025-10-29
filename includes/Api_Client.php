<?php
namespace WC_Moneybird;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Moneybird API client
 */
class Api_Client {
    private $api_token;
    private $administration_id;
    private $base_url = 'https://moneybird.com/api/v2';

    public function __construct($api_token = null, $administration_id = null) {
        $this->api_token = $api_token ?: get_option('wc_moneybird_api_token');
        $this->administration_id = $administration_id ?: get_option('wc_moneybird_administration_id');
    }

    /**
     * Make a GET request to the Moneybird API
     */
    public function get($endpoint, $params = []) {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Make a POST request to the Moneybird API
     */
    public function post($endpoint, $data = []) {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Make a request to the Moneybird API
     */
    private function request($method, $endpoint, $data = []) {
        if (empty($this->api_token)) {
            return new \WP_Error('no_api_token', __('No API token configured', 'woocommerce-moneybird'));
        }

        $url = $this->base_url . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code >= 400) {
            $message = isset($decoded['error']) ? $decoded['error'] : __('API request failed', 'woocommerce-moneybird');
            return new \WP_Error('api_error', $message, ['status_code' => $code, 'response' => $decoded]);
        }

        return $decoded;
    }

    /**
     * Get all administrations
     */
    public function get_administrations() {
        return $this->get('/administrations.json');
    }

    /**
     * Verify API token by fetching sales invoices
     */
    public function verify_permissions() {
        if (empty($this->administration_id)) {
            return new \WP_Error('no_administration_id', __('No administration ID configured', 'woocommerce-moneybird'));
        }

        $result = $this->get('/' . $this->administration_id . '/sales_invoices.json', ['per_page' => 1]);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Get all ledger accounts
     */
    public function get_ledger_accounts() {
        if (empty($this->administration_id)) {
            return new \WP_Error('no_administration_id', __('No administration ID configured', 'woocommerce-moneybird'));
        }

        return $this->get('/' . $this->administration_id . '/ledger_accounts.json');
    }

    /**
     * Get all tax rates
     */
    public function get_tax_rates() {
        if (empty($this->administration_id)) {
            return new \WP_Error('no_administration_id', __('No administration ID configured', 'woocommerce-moneybird'));
        }

        return $this->get('/' . $this->administration_id . '/tax_rates.json');
    }

    /**
     * Create an external sales invoice
     */
    public function create_external_sales_invoice($data) {
        if (empty($this->administration_id)) {
            return new \WP_Error('no_administration_id', __('No administration ID configured', 'woocommerce-moneybird'));
        }

        return $this->post('/' . $this->administration_id . '/external_sales_invoices.json', [
            'external_sales_invoice' => $data
        ]);
    }
}
