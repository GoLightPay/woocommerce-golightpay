# Dockerfile for GoLightPay WooCommerce Plugin Development
# This is optional - docker-compose.yml uses WordPress official image
# Use this if you need custom WordPress configuration

FROM wordpress:latest

# Install additional PHP extensions if needed
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy plugin files (if needed for custom setup)
# COPY . /var/www/html/wp-content/plugins/woocommerce-golightpay/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

