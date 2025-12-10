<?php

/**
 * GoLightPay API Client
 *
 * @package WooCommerce_GoLightPay
 */

defined('ABSPATH') || exit;

/**
 * GoLightPay API Client Class
 */
class GoLightPay_API
{
	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor
	 *
	 * @param string $api_url API base URL
	 * @param string $api_key API key
	 */
	public function __construct($api_url, $api_key)
	{
		$this->api_url = trailingslashit($api_url);
		$this->api_key = $api_key;
	}

	/**
	 * Make API request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $args Request arguments
	 * @return array|WP_Error
	 */
	private function request($endpoint, $args = array())
	{
		$url = $this->api_url . ltrim($endpoint, '/');

		$defaults = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		$args = wp_parse_args($args, $defaults);

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		$code = wp_remote_retrieve_response_code($response);
		if ($code >= 400) {
			$error_message = isset($data['error']) ? $data['error'] : 'API request failed';
			return new WP_Error($code, $error_message);
		}

		return $data;
	}

	/**
	 * Get available networks
	 *
	 * @return array|WP_Error
	 */
	public function get_networks()
	{
		return $this->request('/payment/networks');
	}

	/**
	 * Get available tokens for currency (with cache)
	 *
	 * @param string $currency Currency code (USD, EUR)
	 * @return array|WP_Error
	 */
	public function get_available_tokens($currency)
	{
		$cache_key = 'golightpay_tokens_' . strtoupper($currency);
		$tokens    = get_transient($cache_key);

		if (false === $tokens) {
			// TODO: Call API to get tokens
			// For now, return default tokens
			$tokens = $this->get_default_tokens($currency);
			set_transient($cache_key, $tokens, 24 * HOUR_IN_SECONDS);
		}

		return $tokens;
	}

	/**
	 * Get default tokens for currency
	 *
	 * @param string $currency Currency code
	 * @return array
	 */
	private function get_default_tokens($currency)
	{
		$currency = strtoupper($currency);
		switch ($currency) {
			case 'USD':
				return array('USDC', 'USDT');
			case 'EUR':
				return array('EURC');
			default:
				return array('USDC');
		}
	}

	/**
	 * Create invoice
	 *
	 * @param array $data Invoice data
	 * @return array|WP_Error
	 */
	public function create_invoice($data)
	{
		return $this->request(
			'/v2/invoices',
			array(
				'method' => 'POST',
				'body'   => wp_json_encode($data),
			)
		);
	}

	/**
	 * Get invoice
	 *
	 * @param string $invoice_id Invoice ID
	 * @return array|WP_Error
	 */
	public function get_invoice($invoice_id)
	{
		return $this->request('/payment/invoices/' . $invoice_id);
	}

	/**
	 * Create transaction
	 *
	 * @param string $invoice_id Invoice ID
	 * @param array  $data Transaction data
	 * @return array|WP_Error
	 */
	public function create_transaction($invoice_id, $data)
	{
		return $this->request(
			'/v2/invoices/' . $invoice_id . '/transactions',
			array(
				'method' => 'POST',
				'body'   => wp_json_encode($data),
			)
		);
	}

	/**
	 * Get transaction
	 *
	 * @param string $transaction_id Transaction ID
	 * @return array|WP_Error
	 */
	public function get_transaction($transaction_id)
	{
		return $this->request('/payment/transactions/' . $transaction_id);
	}

	/**
	 * Get webhook configuration for API key
	 *
	 * @param string $key_id API Key ID (extracted from full API key)
	 * @return array|WP_Error
	 */
	public function get_webhook_config($key_id)
	{
		// Note: This endpoint requires JWT token, not API key
		// We'll use API key for now, but it may not work
		return $this->request('/auth/api-keys/' . $key_id . '/webhook');
	}

	/**
	 * Update webhook configuration for API key
	 *
	 * @param string $key_id API Key ID
	 * @param array  $data Webhook configuration data
	 * @return array|WP_Error
	 */
	public function update_webhook_config($key_id, $data)
	{
		// Note: This endpoint requires JWT token, not API key
		// We'll use API key for now, but it may not work
		return $this->request(
			'/auth/api-keys/' . $key_id . '/webhook',
			array(
				'method' => 'POST',
				'body'   => wp_json_encode($data),
			)
		);
	}

	/**
	 * Extract key ID from full API key
	 *
	 * @param string $api_key Full API key (format: pk_live_xxx...yyy or pk_test_xxx...yyy)
	 * @return string|false Key ID on success, false on failure
	 */
	public static function extract_key_id($api_key)
	{
		if (empty($api_key)) {
			return false;
		}

		// API key format: pk_{env}_{32 chars}...{secret}
		// We need to extract the key_id part (pk_{env}_{32 chars})
		if (preg_match('/^(pk_(?:live|test)_[a-zA-Z0-9]{32})/', $api_key, $matches)) {
			return $matches[1];
		}

		return false;
	}

	/**
	 * Verify API key by making a test request
	 *
	 * @return bool|WP_Error True if valid, WP_Error if invalid
	 */
	public function verify_api_key()
	{
		// Try to get networks as a test
		$result = $this->get_networks();
		if (is_wp_error($result)) {
			return $result;
		}
		return true;
	}
}
