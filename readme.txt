=== Heating Oil Calculator for WooCommerce ===
Contributors: ftech
Tags: woocommerce, heating oil, calculator, pricing, delivery
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later

Dynamic heating oil price calculator with delivery points and multi-address checkout for WooCommerce.

== Description ==

This plugin adds a dynamic calculator to your WooCommerce product pages for heating oil products.

Features:
* Dynamic price calculation based on liters, delivery points, and postal code
* Real-time price updates via AJAX
* Delivery points management (up to 5 points)
* Postal code validation
* Custom checkout fields for delivery point addresses
* Order meta storage for delivery information
* Admin order view with delivery details

== Installation ==

1. Upload the `heating-oil-calculator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated
4. Add the calculator to your product page (it will automatically appear for products in the 'heating-oil' category)

== Usage ==

1. Create a product and assign it to the 'heating-oil' category
2. The calculator will automatically appear on the product page
3. Customers enter postal code, liters, and delivery points
4. Price is calculated dynamically
5. Customer adds to cart with the calculated price
6. During checkout, they provide details for each delivery point

== Frequently Asked Questions ==

= Minimum and maximum liters? =
Minimum is 1500 liters, maximum is 6000 liters.

= How many delivery points can I have? =
Between 1 and 5 delivery points.

= Can I modify the pricing? =
Yes, you can modify the get_base_price() method in the main plugin file.

== Changelog ==

= 1.0.0 =
* Initial release