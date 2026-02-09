=== Planet Studio Payment Gateway for ArCa ===
Contributors: planetstudio
Tags: Online payment, arca, armenian banks, Idram payment system, payment gateway
Requires at least: 5.4
Requires PHP: 7.4
Tested up to: 6.8.2
Stable tag: 1.5.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept payments from local & international customers to Armenian banks & Idram via ArCa paycenter for WooCommerce & GiveWP donation plugin.

== Description ==

Accept payments from local & international customers to Armenian banks & Idram via ArCa paycenter for WooCommerce & GiveWP donation plugin.


== Payment gateway works for ==
- ACBA-Credit Agricole Bank
- Araratbank
- Armeconombank
- Ameriabank
- Ardshinbank
- Armbusinessbank
- Byblos Bank Armenia
- Evocabank
- IDBank
- Inecobank
- Armswiss Bank
- Converse Bank
- Idram payment system


== Payment gateway Features ==

- Test mode so you can test without activating live payments.
- Easy to use - just need to add a username, a password that the bank will provide you, and your website ready to receive payment.
- WooCommerce integration
- GiveWP donation Plugin integration
- TATIOSA hotel booking management platform integration
- Accepts Credit cards/Debit cards.
- Route payments in different currencies
- Supports 3D Secure
- Checkout form shortcode
- Payment button shortcode
- Available order logs
- Available error logs


== PREMIUM VERSION FEATURES ==

- Production mode - Free version support only test mode
- Timely compatibility updates and bug fixes.
- Premium support!


== Installation ==

1. Upload the entire `arca-payment-gateway` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen (**Plugins > Installed Plugins**).


== Frequently Asked Questions ==

= Does this plugin work with credit cards, or PayPal? =

This plugin supports payments with credit cards and doesn't support PayPal.

= Does this plugin work with Idram payment system =

This plugin supports Idram payment system.

= What currencies are supported? =

This plugin supports AMD, USD, EUR, RUB currencies.

= Can non-Armenian merchants use this plugin? =

To use this plugin, the merchants need to visit Armenian banks to obtain a username and password.

= Does this plugin works with WooCommerce? =

Yes! This plugin includes payment method in WooCommerce->Payments, you just need activate ArCa Payment Gateway and your store is ready to pay through Armenian banks.

= Can I add donation button using the plugin? =

Yes! the plugin integrated with the very popular GiveWP donation Plugin.

= Does this plugin support testing and production modes? =

Yes! This plugin includes a production and test mode so you can test without activating live payments.

= Will this plugin work with my site's theme? =

ArCa payment gateway should work nicely with any theme, but if you using WooCommerce please visit the [WooCommerce Codex](https://docs.woocommerce.com/documentation/plugins/woocommerce/woocommerce-codex/).


== External services ==

This plugin connects to various banking APIs, payment systems, and booking management platforms to process payments. The integration with these external services ensures secure and efficient transactions.
Supported Payment Systems and Banks

ArCa System Banks
Production: ipay.arca.am
Testing: ipaytest.arca.am

Ameriabank
Production: services.ameriabank.am
Testing: servicestest.ameriabank.am

Inecobank
Production: pg.inecoecom.am

Idram
Production: banking.idram.am

Booking Management Platform

TATIOSA
Custom Payment Gateway API: tatiosa.net

Each of these services plays a crucial role in handling payments securely and efficiently. Ensure that you have the necessary credentials and permissions to use these APIs before integrating them into your system.


== Changelog ==

= 1.5.2 =
*Fixed bugs

= 1.5.1 =
- Add ArCa transaction order ID as a custom column in wc-orders screen
- Add dropdown filter by payment method in WooCommerce admin orders list

= 1.5.0 =
*Fixed bugs

= 1.4.9 =
*Fixed idram endpoint bug

= 1.4.8 =
*Fixed wc blocks bugs

= 1.4.6 =
*Fixed idram wc blocks bugs

= 1.4.6 =
*Add WC blocks support
*Fixed minor bugs

= 1.4.5 =
*Fixed minor bugs

= 1.4.3 =
*Change plugin name

= 1.4.2 =
*Change tags

= 1.4.1 =
*Ameriabank form language fix
*Change tags

= 1.4.0 =
*Add cancellation functionality for ArCa

= 1.3.9 =
*Fixed minor bugs

= 1.3.8 =
*Add refund functionality for ArCa banks
*Add refund functionality for Inecobank
*Add refund and cancellation functionality for Ameriabank

= 1.3.7 =
*Fixed insert bug

= 1.3.6 =
*Fixed minor bugs
*Fixed CSS

= 1.3.5 =
*Fixed minor bugs
*Fixed a CSRF vulnerability in the plugin settings.
*Added CSRF protection for all requests using security tokens (nonce).
*Fixed CSS
*Fixed Translations

= 1.3.1 =
*Fixed a CSRF vulnerability in the plugin settings.
*Added CSRF protection for all requests using security tokens (nonce).
*Improved data validation and sanitization to prevent XSS attacks.
*Tested With WordPress 6.7.1

= 1.3.1 =
*Fixed minor bugs
*Added integration for TATIOSA hotel booking management platform

= 1.3.1 =
*Fixed minor bugs
*Added integration for TATIOSA hotel booking management platform

= 1.3.1 =
*Fixed minor bugs

= 1.3.0 =
*Fixed minor bugs

= 1.2.9 =
*Added custom amount payment shortcode

= 1.2.8 =
*Fixed Idram Callback URLs bug

= 1.2.7 =
*Fixed minor bugs
*Added features
*Update How To Use page

= 1.2.6 =
*Fixed minor bugs
*Added Idram payment system
*Added How To Use page
*Added Privacy Policy Page
*Fixed Inecobank paymets

= 1.2.5 =
*Fixed minor bugs
*Tested With WordPress 6.1.1

= 1.2.5	 =
*Tested With WordPress 6.0.2
*Tested With WordPress 6.0.1
*Tested With WordPress 6

= 1.2.4	 =
*Fix security issue
*Added VPOS multiaccount (AMD, USD, RUB, EUR)
*Added payment system response logs
*ArCa 3D security changes
*Added Inecobank new payment system
*Added port option for ArCa test envoirment
*Added pagination for order and error logs pages
 
= 1.2.3 =
*Important update for Inecobank new processing system
*Tested With Give - Donation Plugin 2.19.2
*Tested With WooCommerce 6.2.1

= 1.2.2 =
*Fixed minor bugs

= 1.2.1 =
*Fixed minor bugs

= 1.2.0 =
*Fixed minor bugs

= 1.1.9 =
*Tested With WordPress 5.9
*Fixed minor bugs

= 1.1.8 =
*Important update in connection with changes from ArCa 
*Fixed minor bugs 

= 1.1.7 =
*Tested With WordPress 5.8.3
*Fixed minor bugs 

= 1.1.6 =
*Tested With WordPress 5.8.2
*Fixed minor bugs 

= 1.1.5 =
*Fixed minor bugs 

= 1.1.4 =
*Fixed minor bugs 

= 1.1.3 =
*Fix bugs for Ameria Bank

= 1.1.2 =
*Tested With WordPress 5.8.1
*Tested With WooCommerce 5.8.0

= 1.1.1 =
*Tested With Give - Donation Plugin 2.11.1

= 1.1.0 =
*Fix bugs

= 1.0.9 =
*Fix bugs
*Tested With WordPress 5.7.1
*Tested With WooCommerce 5.2.2

= 1.0.8 =
*Added dashboard widget
*Added support page
*Added survey form
*Fix bugs

= 1.0.7 =
*Fix bugs

= 1.0.6 =
*Give WP donation plugin integration
*Fix bugs

= 1.0.5 =
*Changed the logic of the bank switch
*Fix bank admin page URL

= 1.0.4 =
*Fix css bug

= 1.0.3 =
*Fix css bug
*Converse Bank

= 1.0.2 =
*WooCommerce integration
*Ameriabank integration

= 1.0.1 =
*Fix API urls update

= 1.0.0 =
*Initial version