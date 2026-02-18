=== Payment Gateway for IDBANK ===
Contributors: HKDigitalAgency
Donate link: https://hkdigital.am/
Tags: idbank, payment gateway, woocommerce, armenia, online payments, credit card, payment system
Requires at least: 5.0
Tested up to: 6.7.2
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Secure payment gateway integration for IDBANK - Accept online payments through IDBANK's payment system with full WooCommerce compatibility.

== Description ==

**Payment Gateway for IDBANK** is a professional WooCommerce extension that enables seamless integration of IDBANK's payment system into your online store. Designed specifically for businesses operating in Armenia.

= Key Features =

* **Multi-Currency Support** - Accept payments in AMD, RUB, USD, and EUR
* **Secure Payments** - PCI DSS compliant with 3DS2 authentication
* **Card Binding** - Save customer cards for faster future checkouts
* **WooCommerce Subscriptions** - Full support for recurring payments
* **Refund Management** - Process refunds directly from WooCommerce
* **WPML Compatible** - Full support for multilingual websites
* **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
* **Test Mode** - Sandbox environment for testing before going live
* **Automatic Updates** - Keep your gateway up-to-date automatically

= Payment Process =

1. Customer selects IDBANK payment method at checkout
2. Customer is redirected to IDBANK's secure payment page
3. Payment is processed through IDBANK's system
4. Customer returns to your store with payment confirmation
5. Order status is automatically updated

= Requirements =

Before using this plugin, you must:

* Have a WordPress website with WooCommerce installed
* Contact IDBANK to set up a merchant account
* Obtain API credentials from IDBANK

**Contact IDBANK:**
* Phone: 010 59 33 33
* Email: info@idbank.am

= Activation & Setup =

This plugin requires license activation through HK Digital Agency. Visit [hkdigital.am](https://hkdigital.am/) or call 033 779-779 for activation.

*Note: Activation service is paid.*

= Technical Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* SSL certificate (HTTPS)
* Valid IDBANK merchant account

= Security & Privacy =

During plugin installation, your domain name, site URL, and IP address will be accessible to HK Digital Agency for license verification and copyright protection purposes.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "Payment Gateway for IDBANK"
4. Click "Install Now" and then "Activate"
5. Go to WooCommerce → Settings → Payments
6. Enable and configure IDBANK payment gateway

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the downloaded ZIP file and click "Install Now"
5. Activate the plugin
6. Go to WooCommerce → Settings → Payments
7. Enable and configure IDBANK payment gateway

= Configuration =

1. Navigate to WooCommerce → Settings → Payments
2. Click on "IDBANK" to access settings
3. Enter your IDBANK API credentials (provided by IDBANK)
4. Configure payment page language (Armenian, Russian, or English)
5. Enable Test Mode for testing (optional)
6. Save settings

For API credentials and merchant account setup, contact IDBANK:
* Phone: 010 59 33 33
* Email: info@idbank.am

== Frequently Asked Questions ==

= Where can I get the API credentials? =

Contact IDBANK directly to set up a merchant account and receive your API credentials:
* Phone: 010 59 33 33
* Email: info@idbank.am

= How do I activate the plugin? =

Visit [hkdigital.am](https://hkdigital.am/) or call 033 779-779 to obtain an activation license. Enter the license key in the plugin settings.

= Is multi-currency support available? =

Yes, the plugin supports AMD, RUB, USD, and EUR. You must contact IDBANK to enable multi-currency processing on your merchant account.

= Can customers save their cards? =

Yes, customers can securely save their cards for faster future checkouts. All card data is stored securely by IDBANK, not on your website.

= Does it work with WooCommerce Subscriptions? =

Yes, the plugin fully supports WooCommerce Subscriptions for recurring payments.

= Is it compatible with WPML? =

Yes, the plugin is fully compatible with WPML for multilingual websites.

= How do I process refunds? =

Refunds can be processed directly from the WooCommerce order page. The refund will be automatically sent to IDBANK's system.

= What about security and PCI compliance? =

The plugin redirects customers to IDBANK's PCI DSS compliant payment page. No sensitive card data is processed or stored on your website.

= Does it support test mode? =

Yes, test mode is available. Contact IDBANK for test environment credentials.

== Screenshots ==

1. Plugin activation panel - License verification interface
2. Gateway configuration settings - Complete setup options
3. Automatic update interface - Keep your plugin up-to-date
4. Checkout payment options - Customer-facing payment method
5. Saved cards management - Customer account cards page

== Changelog ==

= 1.0.7 - 2025-01-09 =
**Security & Compatibility Update**

* **Security Improvements:**
  * Added CSRF protection for card deletion endpoint with nonce verification
  * Implemented POST-only requirement for sensitive actions
  * Enhanced output escaping throughout the plugin (esc_attr, esc_html, esc_url)
  * Improved authentication checks for all AJAX endpoints

* **WordPress 6.7+ Compatibility:**
  * Fixed translation loading notice by moving textdomain load to init hook
  * Removed all translation function calls before init
  * Updated translation best practices with sprintf/printf placeholders

* **WooCommerce Compatibility:**
  * Declared HPOS (High-Performance Order Storage) compatibility
  * Removed deprecated wc_get_log_file_path() usage (WooCommerce 8.6+)
  * Updated logging to use modern wc_get_logger() API
  * Enhanced WooCommerce Blocks integration

* **PHP 8.2+ Compatibility:**
  * Declared all class properties to avoid dynamic property deprecations
  * Updated code to follow PHP 8.2 best practices
  * Improved type safety throughout the codebase

* **Performance Improvements:**
  * Optimized rewrite rules flushing (now only on activation)
  * Removed unnecessary flush_rewrite_rules() calls on every request
  * Improved cron schedule registration efficiency

* **Code Quality:**
  * Enhanced currency mapping with safe fallbacks
  * Improved error handling and logging
  * Better code organization and documentation
  * Standardized JSON responses using wp_send_json_success/error

* **Testing:** Verified compatibility with WordPress 6.7.2 and WooCommerce 9.x

= 1.0.6 - 2024-11-15 =
* Management panel improvements
* Minor bug fixes and optimizations

= 1.0.5 - 2024-09-20 =
* Management panel updates
* Performance optimizations

= 1.0.4 - 2024-08-10 =
* Tested with WordPress 6.6.1
* Management panel improvements
* Bug fixes

= 1.0.3 - 2024-06-15 =
* Management panel updates
* Code optimizations

= 1.0.2 - 2024-04-10 =
* Payment process messages integration
* Management panel improvements
* IPay 3DS2 transition update

= 1.0.1 - 2024-02-05 =
* Added binding (card saving) functionality
* Added refund capability
* Payment confirmation feature added
* WooCommerce Subscriptions integration
* Management panel improvements
* Image gallery added
* Tested with WordPress 5.4.2

= 1.0.0 - 2023-12-01 =
* Initial release
* Multi-currency support (AMD, RUB, USD, EUR)
* WPML compatibility
* Test mode support
* Automatic updates

== Upgrade Notice ==

= 1.0.7 =
IMPORTANT SECURITY UPDATE: This version includes critical security improvements, CSRF protection, and full compatibility with WordPress 6.7+ and PHP 8.2+. Update recommended for all users.

= 1.0.6 =
Management panel improvements and bug fixes. Update recommended.

= 1.0.5 =
Performance optimizations and panel updates. Update recommended.

= 1.0.4 =
WordPress 6.6.1 compatibility verified. Update recommended.

== Additional Information ==

= Automatic Updates =

The plugin includes automatic update functionality to ensure compatibility with the latest WordPress and WooCommerce versions. Update service terms are available on the HK Digital Agency website.

= Support & Documentation =

For technical support and detailed documentation:
* Website: [hkdigital.am](https://hkdigital.am/)
* Phone: 033 779-779
* Email: info@hkdigital.am

For IDBANK merchant account and payment system support:
* Phone: 010 59 33 33
* Email: info@idbank.am

= Copyright & License =

This plugin and all its contents are protected by copyright law.

**Copyright © HK Digital Agency LLC**

Distribution, modification, adaptation, or any other form of use of trademarks and code contained in this plugin is prohibited without prior written permission from HK Digital Agency LLC.

The plugin is provided with a paid usage license. For pricing information, visit [hkdigital.am](https://hkdigital.am/) or call 033 779-779.

= Disclaimer =

HK Digital Agency LLC is not responsible for:
* Services provided by other plugins, extensions, platforms, or organizations
* Technical issues caused by updates from WordPress, other plugins, or third-party services
* Partial or complete disruption of the plugin due to external factors
* Website uptime or security (merchant responsibility)

= Privacy Policy =

During installation, the following information is collected for license verification:
* Website domain name
* Website URL
* Server IP address

This information is used solely for license verification and copyright protection purposes.

== Verification Checklist ==

After updating to version 1.0.7, verify the following:

1. **Admin Panel:**
   * Navigate to WooCommerce → Settings → Payments → IDBANK
   * Verify no PHP notices or deprecation warnings appear
   * Check debug.log for any errors

2. **Checkout (Test Mode):**
   * Complete a test purchase with a new card
   * Complete a test purchase with a saved card
   * Verify order status updates correctly

3. **Account Page:**
   * Navigate to My Account → Cards
   * Verify saved cards display correctly
   * Test card deletion functionality

4. **Optional - Refunds:**
   * Process a small test refund from order admin
   * Verify refund processes correctly in WooCommerce logs

5. **Permalinks:**
   * Go to Settings → Permalinks → Save Settings
   * This ensures the cards endpoint rewrite rules are properly flushed

**Note:** Clear all caching plugins after update to ensure updated JavaScript files are served.

== Technical Notes ==

= Minimum Requirements =
* WordPress 5.0+
* WooCommerce 5.0+
* PHP 7.4+
* MySQL 5.6+ or MariaDB 10.0+
* HTTPS/SSL Certificate
* Memory Limit: 128MB minimum (256MB recommended)

= Recommended Server Configuration =
* PHP 8.0 or higher
* MySQL 5.7+ or MariaDB 10.2+
* Memory Limit: 256MB or higher
* Max Execution Time: 300 seconds
* HTTPS/SSL Certificate (required for production)

= Browser Compatibility =
* Chrome (latest 2 versions)
* Firefox (latest 2 versions)
* Safari (latest 2 versions)
* Edge (latest 2 versions)
* Mobile browsers (iOS Safari, Chrome Mobile)

= Known Compatibility =
* ✓ WooCommerce 5.0 - 9.x
* ✓ WordPress 5.0 - 6.7.2
* ✓ PHP 7.4 - 8.3
* ✓ WPML (all versions)
* ✓ WooCommerce Subscriptions
* ✓ WooCommerce Blocks
* ✓ High-Performance Order Storage (HPOS)

== Credits ==

Developed by [HK Digital Agency](https://hkdigital.am/)

Special thanks to IDBANK for their payment system integration support.