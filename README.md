# GoLightPay for WooCommerce

WooCommerce payment gateway plugin for accepting cryptocurrency payments via GoLightPay.

## Features

- Accept payments in multiple crypto stablecoins (USDC, USDT, EURC, etc.)
- Support for multiple blockchain networks (Base, Ethereum, Solana, Polygon, etc.)
- Automatic currency mapping (USD → USDC/USDT, EUR → EURC)
- Real-time payment status updates via webhook
- QR code and wallet connection support
- 24-hour token cache for optimal performance

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- GoLightPay API credentials

## Installation

1. Upload the plugin to `/wp-content/plugins/woocommerce-golightpay/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin in WooCommerce → Settings → Payments → GoLightPay

## Configuration

1. Go to WooCommerce → Settings → Payments
2. Click on "GoLightPay" to configure
3. Enter your API credentials:
   - API Key (Production/Test)

## Payment Flow

1. Customer selects GoLightPay as payment method
2. Customer sees payment amount and network selection
3. Customer selects payment network
4. QR code and wallet connection options are displayed
5. Payment status is updated in real-time
6. Order is automatically completed upon payment confirmation

## Support

For support, please visit https://golightpay.com
