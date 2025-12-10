<?php

/**
 * GoLightPay Webhook Handler
 *
 * @package WooCommerce_GoLightPay
 */

defined('ABSPATH') || exit;

/**
 * GoLightPay Webhook Class
 */
class GoLightPay_Webhook
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('woocommerce_api_golightpay_webhook', array($this, 'handle_webhook'));
	}

	/**
	 * Handle webhook
	 */
	public function handle_webhook()
	{
		// Get raw request body (needed for signature verification)
		$raw_body = file_get_contents('php://input');
		$data = json_decode($raw_body, true);

		// Log webhook received
		// wc_get_logger()->info(
		// 	'GoLightPay webhook received',
		// 	array('source' => 'golightpay-webhook')
		// );

		// Get headers (support multiple methods)
		$signature = $this->get_header('X-LightPay-Signature');
		$event_type = $this->get_header('X-LightPay-Event');
		$event_id = $this->get_header('X-LightPay-Event-ID');
		$timestamp = $this->get_header('X-LightPay-Timestamp');

		// Log request data
		// wc_get_logger()->info(
		// 	'GoLightPay webhook headers: ' . print_r(
		// 		array(
		// 			'signature'  => $signature,
		// 			'event_type' => $event_type,
		// 			'event_id'   => $event_id,
		// 			'timestamp'  => $timestamp,
		// 		),
		// 		true
		// 	),
		// 	array('source' => 'golightpay-webhook')
		// );

		// wc_get_logger()->info(
		// 	'GoLightPay webhook payload: ' . print_r($data, true),
		// 	array('source' => 'golightpay-webhook')
		// );

		// 1. Verify webhook signature
		if (!$this->verify_signature($raw_body, $signature, $timestamp)) {
			wc_get_logger()->error(
				'GoLightPay webhook signature verification failed',
				array('source' => 'golightpay-webhook')
			);
			wp_send_json(array('error' => 'Invalid signature'), 403);
			return;
		}

		// 2. Validate event type
		if (empty($event_type)) {
			wc_get_logger()->error(
				'GoLightPay webhook missing event type',
				array('source' => 'golightpay-webhook')
			);
			wp_send_json(array('error' => 'Missing event type'), 400);
			return;
		}

		// 3. Validate payload
		if (empty($data) || !isset($data['invoice_id'])) {
			wc_get_logger()->error(
				'GoLightPay webhook missing invoice_id',
				array('source' => 'golightpay-webhook')
			);
			wp_send_json(array('error' => 'Missing invoice_id'), 400);
			return;
		}

		// 4. Process event
		$result = $this->process_event($event_type, $data);

		if (is_wp_error($result)) {
			wc_get_logger()->error(
				'GoLightPay webhook processing failed: ' . $result->get_error_message(),
				array('source' => 'golightpay-webhook')
			);
			wp_send_json(
				array(
					'error'   => 'Processing failed',
					'message' => $result->get_error_message(),
				),
				500
			);
			return;
		}

		// Success
		wp_send_json(array('received' => true), 200);
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $raw_body Raw request body
	 * @param string $signature Received signature header
	 * @param string $timestamp Timestamp header
	 * @return bool
	 */
	private function verify_signature($raw_body, $signature, $timestamp)
	{
		// Skip signature verification if signature is empty (for testing)
		if (empty($signature)) {
			// In production, you should always verify signature
			// For now, allow skipping for development
			if (defined('WP_DEBUG') && WP_DEBUG) {
				wc_get_logger()->warning(
					'GoLightPay webhook signature verification skipped (WP_DEBUG enabled)',
					array('source' => 'golightpay-webhook')
				);
				return true;
			}
			return false;
		}

		// Verify timestamp (prevent replay attacks)
		// if (!empty($timestamp)) {
		// 	$current_time = time();
		// 	$time_diff = abs($current_time - intval($timestamp));
		// 	if ($time_diff > 300) { // 5 minutes
		// 		wc_get_logger()->error(
		// 			'GoLightPay webhook timestamp too old: ' . $time_diff . ' seconds',
		// 			array('source' => 'golightpay-webhook')
		// 		);
		// 		return false;
		// 	}
		// }

		// Get API key from gateway
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$gateway = isset($gateways['golightpay']) ? $gateways['golightpay'] : null;

		if (!$gateway) {
			wc_get_logger()->error(
				'GoLightPay gateway not found for signature verification',
				array('source' => 'golightpay-webhook')
			);
			return false;
		}

		// Get API key using gateway's get_api_key() method
		$api_key = $gateway->get_api_key();

		if (empty($api_key)) {
			wc_get_logger()->error(
				'GoLightPay API key not configured',
				array('source' => 'golightpay-webhook')
			);
			return false;
		}

		// Extract secret from API key (from position 40, 64 characters)
		// API key format: pk_live_abc123...def456... (104 characters total)
		// Key ID: first 40 characters (32 + 8)
		// Secret: from position 40 characters to the end
		if (strlen($api_key) < 40) {
			wc_get_logger()->error(
				'GoLightPay API key format invalid (expected 104 characters)',
				array('source' => 'golightpay-webhook')
			);
			return false;
		}

		$secret = substr($api_key, 40); // From position 40 characters to the end

		// Calculate key hash: SHA256(secret)
		$key_hash = hash('sha256', $secret, false); // Binary output

		// Calculate expected signature: HMAC-SHA256(key_hash, raw_body)
		$expected_signature = 'sha256=' . hash_hmac('sha256', $raw_body, $key_hash);

		// Compare signatures (use hash_equals to prevent timing attacks)
		$is_valid = hash_equals($signature, $expected_signature);

		if (!$is_valid) {
			wc_get_logger()->error(
				'GoLightPay webhook signature mismatch',
				array(
					'source'            => 'golightpay-webhook',
					'received'          => substr($signature, 0, 20) . '...',
					'expected'          => substr($expected_signature, 0, 20) . '...',
					'key_hash'          => $key_hash,
				)
			);
		}

		return $is_valid;
	}

	/**
	 * Process webhook event
	 *
	 * @param string $event_type Event type
	 * @param array  $data Event data
	 * @return bool|WP_Error
	 */
	private function process_event($event_type, $data)
	{
		$invoice_id = isset($data['invoice_id']) ? $data['invoice_id'] : '';

		if (empty($invoice_id)) {
			return new WP_Error('missing_invoice_id', __('Invoice ID is required', 'woocommerce-golightpay'));
		}

		// Find order by invoice ID
		$order = $this->find_order_by_invoice_id($invoice_id);

		if (!$order) {
			wc_get_logger()->warning(
				'GoLightPay webhook: Order not found for invoice_id: ' . $invoice_id,
				array('source' => 'golightpay-webhook')
			);
			// Don't return error - webhook might be for a different system
			return true;
		}

		// Process based on event type
		switch ($event_type) {
			case 'invoice.paid':
				return $this->handle_invoice_paid($order, $data);

			case 'invoice.expired':
				return $this->handle_invoice_expired($order, $data);

			default:
				wc_get_logger()->info(
					'GoLightPay webhook: Unhandled event type: ' . $event_type,
					array('source' => 'golightpay-webhook')
				);
				return true; // Don't fail on unknown events
		}
	}

	/**
	 * Find order by invoice ID
	 *
	 * @param string $invoice_id Invoice ID
	 * @return WC_Order|false
	 */
	private function find_order_by_invoice_id($invoice_id)
	{
		// Query orders by meta key
		$orders = wc_get_orders(
			array(
				'meta_key'   => '_golightpay_invoice_id',
				'meta_value' => $invoice_id,
				'limit'      => 1,
			)
		);

		return !empty($orders) ? $orders[0] : false;
	}

	/**
	 * Handle invoice.paid event
	 *
	 * @param WC_Order $order Order object
	 * @param array    $data Event data
	 * @return bool|WP_Error
	 */
	private function handle_invoice_paid($order, $data)
	{
		// wc_get_logger()->info(
		// 	'GoLightPay webhook: Invoice paid for order #' . $order->get_id(),
		// 	array('source' => 'golightpay-webhook')
		// );

		// Update order status to processing
		$order->update_status(
			'processing',
			sprintf(
				/* translators: %s: Invoice ID */
				__('GoLightPay payment received for invoice', 'woocommerce-golightpay') . ' %s',
				$data['invoice_id']
			)
		);

		// Add order note
		// $order->add_order_note(
		// 	sprintf(
		// 		/* translators: %s: Invoice ID */
		// 		__('GoLightPay payment received for invoice %s', 'woocommerce-golightpay'),
		// 		$data['invoice_id']
		// 	)
		// );

		return true;
	}

	/**
	 * Handle invoice.expired event
	 *
	 * @param WC_Order $order Order object
	 * @param array    $data Event data
	 * @return bool|WP_Error
	 */
	private function handle_invoice_expired($order, $data)
	{
		// wc_get_logger()->info(
		// 	'GoLightPay webhook: Invoice expired for order #' . $order->get_id(),
		// 	array('source' => 'golightpay-webhook')
		// );

		// Only cancel if order is still pending
		if ($order->has_status(array('pending', 'on-hold'))) {
			$order->update_status(
				'cancelled',
				sprintf(
					/* translators: %s: Invoice ID */
					__('GoLightPay payment expired for invoice', 'woocommerce-golightpay') . ' %s',
					$data['invoice_id']
				)
			);

			// Add order note
			// $order->add_order_note(
			// 	sprintf(
			// 		/* translators: %s: Invoice ID */
			// 		__('GoLightPay payment expired for invoice %s', 'woocommerce-golightpay'),
			// 		$data['invoice_id']
			// 	)
			// );
		}

		return true;
	}

	/**
	 * Get HTTP header value
	 *
	 * @param string $header_name Header name (e.g., 'X-LightPay-Signature')
	 * @return string
	 */
	private function get_header($header_name)
	{
		// Method 1: Try getallheaders() (if available, usually in Apache/mod_php)
		if (function_exists('getallheaders')) {
			$headers = getallheaders();
			if ($headers && isset($headers[$header_name])) {
				return $headers[$header_name];
			}
			// Also try lowercase version
			$header_lower = strtolower($header_name);
			foreach ($headers as $key => $value) {
				if (strtolower($key) === $header_lower) {
					return $value;
				}
			}
		}

		// Method 2: Try $_SERVER with HTTP_ prefix (standard format)
		$server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header_name));
		if (isset($_SERVER[$server_key])) {
			return $_SERVER[$server_key];
		}

		// Method 3: Try $_SERVER with REDIRECT_ prefix (some server configs)
		$redirect_key = 'REDIRECT_' . $server_key;
		if (isset($_SERVER[$redirect_key])) {
			return $_SERVER[$redirect_key];
		}

		return '';
	}
}
