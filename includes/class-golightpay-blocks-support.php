<?php

/**
 * GoLightPay Blocks Support
 *
 * @package WooCommerce_GoLightPay
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * GoLightPay Blocks Support Class
 */
final class GoLightPay_Blocks_Support extends AbstractPaymentMethodType
{
  /**
   * Payment gateway instance
   *
   * @var GoLightPay_Gateway
   */
  private $gateway;

  /**
   * Payment method name
   *
   * @return string
   */
  public function get_name()
  {
    return 'golightpay';
  }

  /**
   * Initialize payment method
   */
  public function initialize()
  {
    $this->settings = get_option('woocommerce_golightpay_settings', array());
  }

  /**
   * Check if payment method is active
   *
   * @return bool
   */
  public function is_active()
  {
    $payment_gateways_class = WC()->payment_gateways();
    $payment_gateways = $payment_gateways_class->payment_gateways();
    $gateway = isset($payment_gateways['golightpay']) ? $payment_gateways['golightpay'] : null;

    return $gateway && 'yes' === $gateway->enabled;
  }

  /**
   * Get payment method script handles
   *
   * @return array
   */
  public function get_payment_method_script_handles()
  {
    $script_handle = 'golightpay-blocks';

    // Register script if not already registered
    if (! wp_script_is($script_handle, 'registered')) {
      wp_register_script(
        $script_handle,
        GOLIGHTPAY_WC_PLUGIN_URL . 'assets/js/golightpay-blocks.js',
        array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'),
        GOLIGHTPAY_WC_VERSION,
        true
      );

      // Localize script with payment method data
      $this->localize_script();
    }

    return array($script_handle);
  }

  /**
   * Localize script with payment method data
   */
  private function localize_script()
  {
    $payment_gateways_class = WC()->payment_gateways();
    $payment_gateways = $payment_gateways_class->payment_gateways();
    $gateway = isset($payment_gateways['golightpay']) ? $payment_gateways['golightpay'] : null;

    if (! $gateway) {
      return;
    }

    $is_active = $this->is_active();

    wp_localize_script(
      'golightpay-blocks',
      'wc_golightpay_blocks',
      array(
        'title'       => $gateway->title,
        'description' => $gateway->description,
        'icon'        => $gateway->icon,
        'supports'    => $gateway->supports,
        'isActive'    => $is_active ? true : false, // Explicitly pass boolean
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('wc_store_api'),
      )
    );

    // Debug log
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[GoLightPay Blocks] Localized script with data: ' . print_r(array(
        'title'       => $gateway->title,
        'description' => $gateway->description,
        'isActive'    => $is_active,
      ), true));
    }
  }

  /**
   * Get payment method data
   *
   * @return array
   */
  public function get_payment_method_data()
  {
    $payment_gateways_class = WC()->payment_gateways();
    $payment_gateways = $payment_gateways_class->payment_gateways();
    $gateway = isset($payment_gateways['golightpay']) ? $payment_gateways['golightpay'] : null;

    if (! $gateway) {
      return array();
    }

    return array(
      'title'       => $gateway->title,
      'description' => $gateway->description,
      'icon'        => $gateway->icon,
      'supports'    => $gateway->supports,
    );
  }
}
