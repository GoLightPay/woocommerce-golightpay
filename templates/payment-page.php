<?php

/**
 * Payment Page Template
 *
 * @package WooCommerce_GoLightPay
 * @var WC_Order $order
 * @var string   $invoice_id
 * @var string   $api_url
 * @var string   $testmode
 */

defined('ABSPATH') || exit;

// Get site title
$site_title = get_bloginfo('name');
$page_title = sprintf(
  /* translators: %s: Order number */
  __('Payment - Order #%s', 'woocommerce-golightpay'),
  $order->get_order_number()
);

// Determine if testnet mode from gateway testmode setting
$is_testnet = ('yes' === $testmode);

// Remove /api suffix if present (widget expects base URL)
$base_url = rtrim($api_url, '/');

// Get widget CDN URL (can be configured via filter)
$widget_cdn_url = apply_filters(
  'golightpay_widget_cdn_url',
  'https://cdn.golightpay.com/sdk/golightpay-widget.es.js'
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo esc_html($page_title . ' - ' . $site_title); ?></title>
  <?php wp_head(); ?>
</head>

<body class="golightpay-payment-body">
  <div class="golightpay-payment-page">
    <golightpay-widget
      invoice-id="<?php echo esc_attr($invoice_id); ?>"
      base-url="<?php echo esc_attr($base_url); ?>"
      <?php echo $is_testnet ? 'use-testnet' : ''; ?>></golightpay-widget>
  </div>

  <script type="module">
    // Import GoLightPay Widget
    import '<?php echo esc_url($widget_cdn_url); ?>';

    // Get widget element
    const widget = document.querySelector('golightpay-widget');

    if (widget) {
      // Handle payment success
      widget.addEventListener('payment-success', (e) => {
        const {
          signature,
          network,
          token
        } = e.detail;
        console.log('Payment successful:', {
          signature,
          network,
          token
        });

        // Redirect to order received page
        // const returnUrl = '<?php echo esc_js($order->get_checkout_order_received_url()); ?>';
        // if (returnUrl) {
        //   window.location.href = returnUrl;
        // }
      });

      // Handle payment error
      widget.addEventListener('payment-error', (e) => {
        console.error('Payment error:', e.detail);
        // Error is already displayed by the widget
      });
    }
  </script>
  <?php wp_footer(); ?>
</body>

</html>