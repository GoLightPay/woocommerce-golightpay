<?php

/**
 * Plugin Name: GoLightPay for WooCommerce
 * Plugin URI: https://golightpay.com
 * Description: Accept crypto stablecoin payments via GoLightPay in your WooCommerce store. Supports multiple networks and tokens.
 * Version: 1.0.0
 * Author: GoLightPay
 * Author URI: https://golightpay.com
 * Text Domain: woocommerce-golightpay
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package WooCommerce_GoLightPay
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('GOLIGHTPAY_WC_VERSION', '1.0.0');
define('GOLIGHTPAY_WC_PLUGIN_FILE', __FILE__);
define('GOLIGHTPAY_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GOLIGHTPAY_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GOLIGHTPAY_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declare compatibility with WooCommerce HPOS and Blocks checkout.
add_action('before_woocommerce_init', function () {
  if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
    // Declare compatibility with HPOS (High-Performance Order Storage)
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
      'custom_order_tables',
      GOLIGHTPAY_WC_PLUGIN_FILE,
      true
    );
    // Declare compatibility with Cart & Checkout Blocks
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
      'cart_checkout_blocks',
      GOLIGHTPAY_WC_PLUGIN_FILE,
      true
    );
  }
});

/**
 * Main plugin class
 */
final class WooCommerce_GoLightPay
{
  /**
   * Plugin version
   *
   * @var string
   */
  public $version = GOLIGHTPAY_WC_VERSION;

  /**
   * Single instance of the class
   *
   * @var WooCommerce_GoLightPay
   */
  protected static $_instance = null;

  /**
   * Main instance
   *
   * @return WooCommerce_GoLightPay
   */
  public static function instance()
  {
    if (is_null(self::$_instance)) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->init_hooks();
  }

  /**
   * Initialize hooks
   */
  private function init_hooks()
  {
    add_action('plugins_loaded', array($this, 'init'), 0);
    add_action('plugins_loaded', array($this, 'load_textdomain'), 0);
    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('init', array($this, 'add_payment_endpoint'));
    add_action('template_redirect', array($this, 'handle_payment_page'));
    add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));
  }

  /**
   * Load plugin textdomain for translations
   */
  public function load_textdomain()
  {
    load_plugin_textdomain(
      'woocommerce-golightpay',
      false,
      dirname(GOLIGHTPAY_WC_PLUGIN_BASENAME) . '/languages'
    );
  }

  /**
   * Initialize plugin
   */
  public function init()
  {
    // Check if WooCommerce is active
    if (! class_exists('WooCommerce')) {
      add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
      return;
    }

    // Load plugin files
    $this->includes();
    $this->init_gateway();
  }

  /**
   * Include required files
   */
  private function includes()
  {
    require_once GOLIGHTPAY_WC_PLUGIN_DIR . 'includes/class-golightpay-api.php';
    require_once GOLIGHTPAY_WC_PLUGIN_DIR . 'includes/class-golightpay-gateway.php';
    require_once GOLIGHTPAY_WC_PLUGIN_DIR . 'includes/class-golightpay-webhook.php';
    require_once GOLIGHTPAY_WC_PLUGIN_DIR . 'includes/class-golightpay-admin.php';

    // Load Blocks support if WooCommerce Blocks is active
    if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
      require_once GOLIGHTPAY_WC_PLUGIN_DIR . 'includes/class-golightpay-blocks-support.php';
    }
  }

  /**
   * Initialize payment gateway
   */
  private function init_gateway()
  {
    add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
    add_filter('woocommerce_available_payment_gateways', array($this, 'log_available_gateways'), 99, 1);

    // Register Blocks support
    add_action('woocommerce_blocks_payment_method_type_registration', array($this, 'register_blocks_support'));

    // Initialize webhook handler
    new GoLightPay_Webhook();
  }

  /**
   * Register Blocks payment method support
   *
   * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry
   */
  public function register_blocks_support($payment_method_registry)
  {
    if (class_exists('GoLightPay_Blocks_Support')) {
      $payment_method_registry->register(new GoLightPay_Blocks_Support());
    }
  }

  /**
   * Enqueue frontend assets for checkout
   */
  public function enqueue_assets()
  {
    if (function_exists('is_checkout') && is_checkout()) {
      wp_enqueue_style(
        'woocommerce-golightpay',
        GOLIGHTPAY_WC_PLUGIN_URL . 'assets/css/golightpay.css',
        array(),
        GOLIGHTPAY_WC_VERSION
      );
    }
  }

  /**
   * Add gateway to WooCommerce
   *
   * @param array $gateways Existing gateways
   * @return array
   */
  public function add_gateway($gateways)
  {
    $gateways[] = 'GoLightPay_Gateway';
    return $gateways;
  }

  /**
   * Log available gateways for debugging
   *
   * @param array $gateways
   * @return array
   */
  public function log_available_gateways($gateways)
  {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      $keys = is_array($gateways) ? array_keys($gateways) : array();
      error_log('[GoLightPay] Available gateways: ' . implode(', ', $keys));
    }
    return $gateways;
  }

  /**
   * Add payment endpoint to order-pay
   */
  public function add_payment_endpoint()
  {
    // Add endpoint that works with order-pay
    add_rewrite_endpoint('golightpay_pay', EP_ROOT | EP_PAGES);
  }

  /**
   * Handle payment page
   */
  public function handle_payment_page()
  {
    // Check if golightpay_pay parameter is set
    if (! isset($_GET['golightpay_pay']) || $_GET['golightpay_pay'] !== '1') {
      return;
    }

    // Get order ID - support both permalink and non-permalink modes
    $order_id = 0;

    // Try to get from WooCommerce endpoint (permalink mode)
    if (is_wc_endpoint_url('order-pay')) {
      global $wp;
      $order_id = isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
    }

    // Fallback: get from query parameter (non-permalink mode)
    if (! $order_id && isset($_GET['order_id'])) {
      $order_id = absint($_GET['order_id']);
    }

    // Also try order-pay query parameter (WooCommerce fallback)
    if (! $order_id && isset($_GET['order-pay'])) {
      $order_id = absint($_GET['order-pay']);
    }

    if (! $order_id) {
      wp_die(__('Invalid order.', 'woocommerce-golightpay'));
      return;
    }

    $order = wc_get_order($order_id);

    if (! $order) {
      wp_die(__('Order not found.', 'woocommerce-golightpay'));
      return;
    }

    // Verify order belongs to current user (if logged in)
    if (is_user_logged_in() && $order->get_user_id() !== get_current_user_id()) {
      wp_die(__('You do not have permission to view this order.', 'woocommerce-golightpay'));
      return;
    }

    // Check if order uses GoLightPay payment method
    if ($order->get_payment_method() !== 'golightpay') {
      wp_die(__('This order does not use GoLightPay payment method.', 'woocommerce-golightpay'));
      return;
    }

    // Get invoice ID
    $invoice_id = $order->get_meta('_golightpay_invoice_id');

    if (! $invoice_id) {
      wp_die(__('Payment invoice not found. Please contact support.', 'woocommerce-golightpay'));
      return;
    }

    // Get gateway instance to access API URL
    $gateways = WC()->payment_gateways()->get_available_payment_gateways();
    $gateway = isset($gateways['golightpay']) ? $gateways['golightpay'] : null;

    if (! $gateway) {
      wp_die(__('Payment gateway not available.', 'woocommerce-golightpay'));
      return;
    }

    // Load payment template
    $this->load_payment_template($order, $invoice_id, $gateway);
    exit;
  }

  /**
   * Load payment template
   *
   * @param WC_Order $order Order object
   * @param string   $invoice_id Invoice ID
   * @param object   $gateway Gateway instance
   */
  private function load_payment_template($order, $invoice_id, $gateway)
  {
    // Enqueue assets
    wp_enqueue_style(
      'woocommerce-golightpay',
      GOLIGHTPAY_WC_PLUGIN_URL . 'assets/css/golightpay.css',
      array(),
      GOLIGHTPAY_WC_VERSION
    );

    // Get API URL from gateway
    $api_url = '';
    if (method_exists($gateway, 'get_api_url')) {
      $api_url = $gateway->get_api_url();
    } elseif (method_exists($gateway, 'get_option')) {
      // Fallback: construct from gateway settings
      $testmode = $gateway->get_option('testmode', 'no');
      $api_url = ('yes' === $testmode) ? 'http://localhost:8080/api' : 'https://api.golightpay.com';
    }

    // Get testmode from gateway
    $testmode = 'no';
    if (method_exists($gateway, 'get_option')) {
      $testmode = $gateway->get_option('testmode', 'no');
    }

    // Load template
    include GOLIGHTPAY_WC_PLUGIN_DIR . 'templates/payment-page.php';
  }

  /**
   * Plugin activation
   */
  public function activate()
  {
    // Flush rewrite rules to register endpoint
    flush_rewrite_rules();
  }

  /**
   * Plugin deactivation
   */
  public function deactivate()
  {
    // Flush rewrite rules to remove endpoint
    flush_rewrite_rules();
  }

  /**
   * WooCommerce missing notice
   */
  public function woocommerce_missing_notice()
  {
?>
    <div class="error">
      <p>
        <strong><?php esc_html_e('GoLightPay for WooCommerce', 'woocommerce-golightpay'); ?></strong>
        <?php esc_html_e('requires WooCommerce to be installed and active.', 'woocommerce-golightpay'); ?>
      </p>
    </div>
<?php
  }

  /**
   * Add plugin row meta links
   *
   * @param array  $links Existing links
   * @param string $file  Plugin file
   * @return array
   */
  public function add_plugin_row_meta($links, $file)
  {
    // Only add links for this plugin
    if (GOLIGHTPAY_WC_PLUGIN_BASENAME !== $file) {
      return $links;
    }

    // Add custom links
    $custom_links = array(
      sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url('https://golightpay.com/#pricing'),
        esc_html__('View Pricing & Fees', 'woocommerce-golightpay')
      ),
      sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url('https://docs.golightpay.com'),
        esc_html__('View Documentation', 'woocommerce-golightpay')
      ),
      // sprintf(
      //   '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
      //   esc_url('https://golightpay.com/support'),
      //   esc_html__('Get Support', 'woocommerce-golightpay')
      // ),
    );

    return array_merge($links, $custom_links);
  }
}

/**
 * Main instance
 *
 * @return WooCommerce_GoLightPay
 */
function golightpay_wc()
{
  return WooCommerce_GoLightPay::instance();
}

// Initialize plugin
golightpay_wc();
