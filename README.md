# WooCommerce Email Before Add to Cart

A WordPress plugin that requires customers to enter their email address before adding products to cart, enabling better abandoned cart tracking and recovery.

## Features

- Adds an email input field before the Add to Cart button
- Validates email input before allowing products to be added to cart
- Stores customer emails in a dedicated database table
- Tracks which products were considered by which email addresses
- Admin panel to view all captured emails and associated products
- Daily cleanup of old session data
- Compatible with WooCommerce 3.0+

## Installation

1. Download the plugin ZIP file
2. Go to WordPress admin > Plugins > Add New
3. Click "Upload Plugin" and choose the downloaded ZIP file
4. Click "Install Now" and then "Activate"

## Usage

The plugin works automatically after activation:

1. An email field appears before the Add to Cart button on all product pages
2. Customers must enter their email before adding products to cart
3. View captured emails in WooCommerce > Abandoned Emails

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## Database

The plugin creates a custom table `{prefix}_wc_email_cart_tracking` with the following structure:
- id (AUTO_INCREMENT)
- email (varchar)
- product_id (bigint)
- product_name (varchar)
- created_at (datetime)

## Support

For support, please visit [Crafely.com](https://crafely.com)

## License

This plugin is licensed under GPL2.

## Author

Created by Mohamed Alamin
Website: [Crafely.com](https://crafely.com)
