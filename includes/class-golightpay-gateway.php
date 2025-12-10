<?php

/**
 * GoLightPay Payment Gateway
 *
 * @package WooCommerce_GoLightPay
 */

defined('ABSPATH') || exit;

/**
 * GoLightPay Gateway Class
 */
class GoLightPay_Gateway extends WC_Payment_Gateway
{
  /**
   * @var string
   */
  protected $api_key = '';

  /**
   * @var string
   */
  protected $api_key_testnet = '';

  /**
   * @var string
   */
  protected $testmode = 'no';

  /**
   * @var string
   */
  public $title = '';

  /**
   * @var string
   */
  public $description = '';

  /**
   * @var string
   */
  public $enabled = 'no';

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->id                 = 'golightpay';
    $this->icon               = GOLIGHTPAY_WC_PLUGIN_URL . 'assets/img/golightpay-logo.png';
    $this->has_fields         = false;
    $this->method_title       = __('GoLightPay', 'woocommerce-golightpay');
    $this->method_description = __('Accept crypto stablecoin payments via GoLightPay', 'woocommerce-golightpay');

    // Load settings
    $this->init_form_fields();
    $this->init_settings();

    // Get settings
    // Use English defaults - will be translated via get_title() and get_description()
    $this->title            = $this->get_option('title', 'Pay with crypto stablecoins');
    $this->description      = $this->get_option('description', 'Pay with crypto stablecoins via GoLightPay. Supports multiple networks and tokens.');
    $this->enabled          = $this->get_option('enabled', 'no');
    $this->api_key          = $this->get_option('api_key', '');
    $this->api_key_testnet  = $this->get_option('api_key_testnet', '');
    $this->testmode         = $this->get_option('testmode', 'no');

    // Override title and description with translated versions
    $this->title = $this->get_title();
    $this->description = $this->get_description();

    // Save settings
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

    // Auto-configure webhook after API key is saved
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'auto_configure_webhook'), 20);

    // Declare support for features
    $this->supports = array(
      'products',
      'cart_checkout_blocks', // Support WooCommerce Blocks checkout
    );
  }

  /**
   * Initialize form fields
   */
  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled'     => array(
        'title'   => __('Enable/Disable', 'woocommerce-golightpay'),
        'type'    => 'checkbox',
        'label'   => __('Enable GoLightPay', 'woocommerce-golightpay'),
        'default' => 'no',
      ),
      'title'       => array(
        'title'       => __('Title', 'woocommerce-golightpay'),
        'type'        => 'text',
        'description' => __('Payment method title shown to customers', 'woocommerce-golightpay'),
        'default'     => 'Pay with crypto stablecoins', // English default - will be translated via get_title()
      ),
      'description' => array(
        'title'       => __('Description', 'woocommerce-golightpay'),
        'type'        => 'textarea',
        'description' => __('Payment method description shown to customers', 'woocommerce-golightpay'),
        'default'     => 'Pay with crypto stablecoins via GoLightPay. Supports multiple networks and tokens.', // English default - will be translated via get_description()
      ),
      'api_key'         => array(
        'title'       => __('API Key (Production)', 'woocommerce-golightpay'),
        'type'        => 'password',
        'description' => __('Your GoLightPay production API key', 'woocommerce-golightpay'),
        'default'     => '',
      ),
      'api_key_testnet' => array(
        'title'       => __('API Key (Testnet)', 'woocommerce-golightpay'),
        'type'        => 'password',
        'description' => __('Your GoLightPay testnet API key', 'woocommerce-golightpay'),
        'default'     => '',
      ),
      'testmode'        => array(
        'title'   => __('Test Mode', 'woocommerce-golightpay'),
        'type'    => 'checkbox',
        'label'   => __('Enable test mode', 'woocommerce-golightpay'),
        'default' => 'no',
      ),
    );
  }

  /**
   * Get API URL based on test mode
   *
   * @return string
   */
  public function get_api_url()
  {
    if ('yes' === $this->testmode) {
      // Testnet API URL
      return 'https://testapi.golightpay.com';
      // return 'http://host.docker.internal';
    }
    // Production API URL
    return 'https://api.golightpay.com';
  }

  /**
   * Get API key based on test mode
   *
   * @return string
   */
  public function get_api_key()
  {
    if ('yes' === $this->testmode) {
      return $this->api_key_testnet;
    }
    return $this->api_key;
  }

  /**
   * Get payment method title (supports translation)
   *
   * @return string
   */
  public function get_title()
  {
    $title = $this->title;

    // If title is empty or matches any possible default value, use translated default
    if (empty($title)) {
      $title = __('Pay with crypto stablecoins', 'woocommerce-golightpay');
    } elseif ($title === 'Pay with crypto stablecoins') {
      // Translate the English default value
      $title = __('Pay with crypto stablecoins', 'woocommerce-golightpay');
    } elseif ($title === 'GoLightPay') {
      // Translate if user saved the old default
      $title = __('GoLightPay', 'woocommerce-golightpay');
    }
    // If user customized the title, use it as-is (no translation)

    // Apply filter for third-party translation plugins (WPML, Polylang, etc.)
    return apply_filters('woocommerce_gateway_title', $title, $this->id);
  }

  /**
   * Get payment method description (supports translation)
   *
   * @return string
   */
  public function get_description()
  {
    $description = $this->description;

    // If description is empty or matches any possible default value, use translated default
    $default_descriptions = array(
      'Pay with crypto stablecoins via GoLightPay. Supports multiple networks and tokens.',
      '', // Empty
    );

    if (empty($description) || in_array($description, $default_descriptions, true)) {
      $description = __('Pay with crypto stablecoins via GoLightPay. Supports multiple networks and tokens.', 'woocommerce-golightpay');
    }

    // Apply filter for third-party translation plugins (WPML, Polylang, etc.)
    return apply_filters('woocommerce_gateway_description', $description, $this->id);
  }

  /**
   * Process payment
   *
   * @param int $order_id Order ID
   * @return array
   */
  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);

    // wc_get_logger()->info(
    //   'GoLightPay process_payment: ' . $order_id,
    //   ['source' => 'golightpay']
    // );

    if (! $order) {
      return array(
        'result'   => 'fail',
        'redirect' => '',
      );
    }

    // Initialize API client
    $api = new GoLightPay_API($this->get_api_url(), $this->get_api_key());

    // Get available tokens for order currency
    $currency = $order->get_currency();
    $tokens   = $api->get_available_tokens($currency);

    // wc_get_logger()->info(
    //   'GoLightPay get_currency: ' . $order->get_currency(),
    //   ['source' => 'golightpay']
    // );

    // wc_get_logger()->info(
    //   'GoLightPay tokens: ' . print_r($tokens, true),
    //   ['source' => 'golightpay']
    // );

    if (is_wp_error($tokens)) {
      wc_add_notice(__('Failed to get available tokens', 'woocommerce-golightpay'), 'error');
      return array(
        'result'   => 'fail',
        'redirect' => '',
      );
    }

    // Create invoice
    $invoice_data = array(
      'amount'         => $order->get_total(),
      'currency'       => $currency,
      'accepted_tokens'  => $tokens,
      'description'    => sprintf(__('Order #%s', 'woocommerce-golightpay'), $order->get_order_number()),
      'return_url'     => $this->get_return_url($order),
      'out_biz_id'     => (string) $order->get_id(),
    );

    $invoice = $api->create_invoice($invoice_data);
    // wc_get_logger()->info(
    //   'GoLightPay create_invoice: ' . print_r($invoice, true),
    //   ['source' => 'golightpay']
    // );
    if (is_wp_error($invoice)) {
      wc_add_notice($invoice->get_error_message(), 'error');
      return array(
        'result'   => 'fail',
        'redirect' => '',
      );
    }

    // Store invoice ID in order meta
    $order->update_meta_data('_golightpay_invoice_id', $invoice['invoice']['invoice_id']);
    $order->save();

    // Mark order as pending payment
    // $order->update_status('pending', __('Awaiting GoLightPay payment', 'woocommerce-golightpay'));

    // Redirect to payment page
    // Support both permalink and non-permalink modes
    if (get_option('permalink_structure')) {
      // Permalink mode: use endpoint
      $payment_url = trim(wc_get_checkout_url(), '/') . '/order-pay/' . $order->get_id() . '/?golightpay_pay=1&key=' . $order->get_order_key();
    } else {
      // Non-permalink mode: use query parameters
      $payment_url = add_query_arg(
        array(
          'order-pay'       => $order->get_id(),
          'golightpay_pay' => '1',
          'key'            => $order->get_order_key(),
        ),
        wc_get_checkout_url()
      );
    }

    return array(
      'result'   => 'success',
      'redirect' => $payment_url,
    );
  }

  /**
   * Auto-configure webhook after API key is saved
   */
  public function auto_configure_webhook()
  {
    $this->init_settings();
    $this->api_key          = $this->get_option('api_key', '');
    $this->api_key_testnet  = $this->get_option('api_key_testnet', '');
    $this->testmode         = $this->get_option('testmode', 'no');

    $api_key = $this->get_api_key();

    if (empty($api_key)) {
      wc_get_logger()->info(
        'GoLightPay auto_configure_webhook: API key is empty',
        ['source' => 'golightpay']
      );
      return;
    }

    // Extract key ID from API key
    $key_id = GoLightPay_API::extract_key_id($api_key);
    if (!$key_id) {
      return;
    }

    // Initialize API client
    $api = new GoLightPay_API($this->get_api_url(), $api_key);

    // Verify API key first
    $verified = $api->verify_api_key();
    if (is_wp_error($verified)) {
      // API key is invalid, don't proceed
      return;
    }

    // Generate webhook URL
    $webhook_url = $this->get_webhook_url();

    // Try to get current webhook config
    $current_config = $api->get_webhook_config($key_id);

    // If webhook is not configured or URL doesn't match, try to update it
    $needs_update = false;
    if (is_wp_error($current_config)) {
      // Webhook not configured yet, needs update
      $needs_update = true;
    } elseif (isset($current_config['webhook_url']) && $current_config['webhook_url'] !== $webhook_url) {
      // Webhook URL doesn't match, needs update
      $needs_update = true;
    } elseif (!isset($current_config['webhook_enabled']) || !$current_config['webhook_enabled']) {
      // Webhook is disabled, needs update
      $needs_update = true;
    }

    if ($needs_update) {
      // Try to update webhook configuration
      // Note: This may fail if API requires JWT token instead of API key
      $result = $api->update_webhook_config(
        $key_id,
        array(
          'webhook_url'     => $webhook_url,
          'webhook_enabled' => true,
          'webhook_events'  => array('invoice.paid', 'invoice.expired'),
        )
      );

      if (!is_wp_error($result)) {
        // Successfully configured webhook
        add_action('admin_notices', function () {
          echo '<div class="notice notice-success is-dismissible"><p>';
          echo esc_html__('GoLightPay: Webhook has been automatically configured.', 'woocommerce-golightpay');
          echo '</p></div>';
        });
      } else {
        // Failed to configure webhook automatically
        // Show notice with manual setup instructions
        add_action('admin_notices', function () use ($webhook_url) {
          echo '<div class="notice notice-warning is-dismissible"><p>';
          echo '<strong>' . esc_html__('GoLightPay: Webhook Configuration', 'woocommerce-golightpay') . '</strong><br>';
          echo esc_html__('Please configure the webhook URL in your GoLightPay dashboard:', 'woocommerce-golightpay') . '<br>';
          echo '<code>' . esc_html($webhook_url) . '</code>';
          echo '</p></div>';
        });
      }
    }
  }

  /**
   * Get webhook URL for this site
   *
   * @return string
   */
  private function get_webhook_url()
  {
    // Use WooCommerce's built-in method to generate API endpoint URL
    // This automatically handles permalink settings
    if (function_exists('WC') && WC()->api_request_url) {
      return WC()->api_request_url('golightpay_webhook');
    }

    // Fallback: manual URL generation if WC() is not available
    if (get_option('permalink_structure')) {
      // Permalink mode: use path format
      return home_url('/wc-api/golightpay_webhook');
    } else {
      // Non-permalink mode: use query parameter format
      return add_query_arg('wc-api', 'golightpay_webhook', home_url('/'));
    }
  }

  /**
   * Check if gateway needs setup
   * This will show "Action needed" status in payment gateways list
   *
   * WooCommerce logic:
   * - If needs_setup() returns true, shows "Action needed" badge
   * - This is checked regardless of enabled status
   * - is_available() controls if gateway appears in checkout
   *
   * @return bool
   */
  public function needs_setup()
  {
    if ('yes' === $this->testmode) {
      $api_key = trim($this->api_key_testnet);
    } else {
      $api_key = trim($this->api_key);
    }

    // Return true if API key is empty (needs setup)
    // This will make WooCommerce show "setup" button when gateway is not enabled
    return empty($api_key);
  }

  /**
   * Check if gateway is available for checkout
   * This controls if the payment method appears in checkout page
   *
   * @return bool
   */
  public function is_available()
  {
    // Gateway is only available if enabled and configured
    if ('yes' !== $this->enabled) {
      return false;
    }

    $api_key = $this->get_api_key();
    if (empty($api_key)) {
      return false;
    }

    return parent::is_available();
  }
}
