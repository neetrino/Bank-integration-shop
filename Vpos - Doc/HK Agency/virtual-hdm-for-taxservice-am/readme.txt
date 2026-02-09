=== TAX SERVICE Electronic HDM ===
Contributors: HKDigitalAgency
Donate link: https://hkdigital.am/
Tags: hdm, electronic-hdm, հդմ, էլեկտրոնային-հդմ
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Armenian Electronic Fiscal Data Module (HDM) integration for WooCommerce. Tax compliance for Armenian businesses.

== Description ==

**TAX SERVICE Electronic HDM** is a WooCommerce plugin that seamlessly integrates the Electronic Fiscal Data Module (Էլեկտրոնային ՀԴՄ) system required by Armenian tax authorities.

= Key Features =

* **Automated Fiscal Receipts** - Generate electronic fiscal receipts automatically for WooCommerce orders
* **JKS Certificate Support** - Upload and manage .jks certification files
* **Tax Type Management** - Configure tax types, departments, and cashier information
* **Product Configuration** - Set HS codes, product codes, and measurement units per product
* **VAT Flexibility** - Support for VAT-taxable and non-VAT items with receipt-like printouts
* **Delivery Service Integration** - Built-in shipping/delivery functionality
* **Automatic Updates** - Keep the plugin up-to-date with automatic update notifications

= Who Is This For? =

This plugin is designed specifically for Armenian businesses operating in Armenia who:
* Use WooCommerce for e-commerce
* Are required to comply with Armenian tax regulations
* Need to issue electronic fiscal receipts (Էլեկտրոնային ՀԴՄ)

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Valid Electronic HDM registration with Armenian tax authorities
* SSL certificate recommended

= Getting Started =

Before using this plugin, you must complete the identification process:

1. Visit our website: [hkdigital.am](https://hkdigital.am/)
2. Call us: **033 779-779**
3. Email us: **support@hkdigital.am**

**Note:** The identification service is paid. Contact us for pricing details.

= Documentation & Support =

* [Documentation](https://plugins.hkdigital.am/terms.html)
* [Support Portal](https://plugins.hkdigital.am/terms.html)
* Email: support@hkdigital.am
* Phone: 033 779-779

= Languages =

* Armenian (hy) - Native
* English (en)
* Russian (ru)

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to **Plugins → Add New**
3. Search for "TAX SERVICE Electronic HDM"
4. Click **Install Now**, then **Activate**
5. Go to **WooCommerce → Settings → TAX SERVICE HDM** to configure

= Manual Installation via WordPress Admin =

1. Download the `tax-service-electronic-hdm.zip` file
2. Log in to your WordPress admin panel
3. Go to **Plugins → Add New → Upload Plugin**
4. Choose the downloaded zip file and click **Install Now**
5. Click **Activate Plugin**
6. Go to **WooCommerce → Settings → TAX SERVICE HDM** to configure

= Manual Installation via FTP =

1. Download the `tax-service-electronic-hdm.zip` file
2. Extract the zip file contents
3. Upload the `tax-service-electronic-hdm` folder to `/wp-content/plugins/` directory via FTP
4. Log in to WordPress admin panel
5. Go to **Plugins** and activate the plugin
6. Go to **WooCommerce → Settings → TAX SERVICE HDM** to configure

= Configuration Steps =

After installation and activation:

1. **Complete Identification** - Contact us to verify your business credentials
2. **Upload Certificate** - Upload your .jks certification file
3. **Configure Tax Settings** - Set up tax types, departments, and cashier info
4. **Configure Products** - Add HS codes and measurement units to your products
5. **Test Receipt Generation** - Place a test order to verify receipt generation
6. **Go Live** - Start processing real orders with automatic fiscal receipts

== Frequently Asked Questions ==

= How do I complete the identification process? =

Contact HK Digital Agency via:
* Website: [hkdigital.am](https://hkdigital.am/)
* Phone: 033 779-779
* Email: support@hkdigital.am

Our team will guide you through the identification and setup process.

= Is this plugin free? =

The plugin download is free, but the identification service and ongoing support are paid services. Contact us for pricing details.

= Do I need Electronic HDM registration? =

Yes, you must be registered with the Armenian tax authorities for Electronic HDM before using this plugin.

= What version of WooCommerce is required? =

WooCommerce 5.0 or higher is required. We recommend using the latest stable version.

= Does this work with WordPress multisite? =

Currently, multisite support is limited. Contact us for custom multisite solutions.

= Can I customize the receipt template? =

Yes, receipt templates can be customized. Premium support customers can request custom templates.

= What happens if my certificate expires? =

The plugin will notify you before certificate expiration. Contact your tax authority to renew your certificate.

= Is there a test/sandbox mode? =

Yes, the plugin includes a test mode for development and testing purposes before going live.

= How do I get support? =

Contact us via:
* Email: support@hkdigital.am
* Phone: 033 779-779
* Support portal: [plugins.hkdigital.am/support](https://plugins.hkdigital.am/terms.html)

= What if there's a conflict with another plugin? =

Contact our support team. We'll help identify and resolve plugin conflicts.

= How often is the plugin updated? =

We regularly update the plugin to maintain compatibility with WordPress, WooCommerce, and Armenian tax authority requirements.

== Screenshots ==

1. **Identification Process** - Plugin identification and authentication interface
2. **Certificate Management** - Upload and manage your .jks certification files
3. **Tax Settings** - Configure tax types, cashiers, departments, and sequential numbers
4. **Product Settings** - Set HS codes, measurement units, and delivery options for products
5. **Print Settings** - Control receipt printing status and format options
6. **Email Receipt** - Sample of electronic HDM receipt structure sent via email

== Changelog ==

= 1.2.3 - 2025-01-15 =
* Fixed all SQL injection vulnerabilities.
* Updated database queries to use $wpdb->prepare() with proper placeholders.
* Improved input sanitization and data validation.
* Passed Plugin Check with no issues.
* Confirmed compatibility with the latest WordPress version.

= 1.2.2 - 2025-01-15 =
* **Compatibility:** Tested with WordPress 6.7 and WooCommerce 9.x
* **Improvement:** Updated readme.txt to WordPress Plugin Directory standards
* **Improvement:** Enhanced screenshot documentation
* **Fix:** Minor UI/UX improvements
* **Fix:** Short description length fixed (under 150 characters)
* **Fix:** Proper SVN tag structure

= 1.2.1 - 2024-12-10 =
* **Compatibility:** WordPress 6.6 compatibility
* **Fix:** Certificate upload validation improvements
* **Improvement:** Better error messaging

= 1.2.0 - 2024-11-15 =
* **Feature:** Added automatic certificate expiration warnings
* **Feature:** Enhanced delivery service integration
* **Improvement:** Improved receipt generation performance
* **Fix:** Various bug fixes and stability improvements

= 1.1.0 - 2024-10-01 =
* **Feature:** Added support for multiple cashiers
* **Feature:** Sequential numbering system improvements
* **Improvement:** UI/UX enhancements
* **Fix:** Tax calculation accuracy improvements

= 1.0.9 - 1.0.1 =
* System updates and design improvements

= 1.0.0 - 2024-01-15 =
* **Initial Release** - First public release

== Upgrade Notice ==

= 1.2.2 =
WordPress 6.7 compatibility update with fixed SVN structure. Recommended for all users.

= 1.2.1 =
Minor improvements and bug fixes. Safe to update.

= 1.2.0 =
Important security and feature updates. Update recommended.

= 1.1.0 =
New features and improvements for multi-cashier support.

== Privacy Policy ==

This plugin:
* Does not collect personal data from visitors
* Processes order data as required for fiscal receipt generation
* Communicates with Armenian tax authority servers for receipt validation
* Stores certificate and configuration data locally in WordPress database
* Does not share data with third parties except as required by Armenian tax law

== Additional Information ==

= System Requirements =

**Minimum:**
* WordPress 5.0+
* WooCommerce 5.0+
* PHP 7.4+
* MySQL 5.6+ or MariaDB 10.0+
* SSL Certificate (recommended)

**Recommended:**
* WordPress 6.7+
* WooCommerce 9.0+
* PHP 8.1+
* MySQL 8.0+ or MariaDB 10.6+
* SSL Certificate (required for production)

= Automatic Updates =

The plugin includes automatic update notifications. We regularly release updates to:
* Maintain compatibility with WordPress and WooCommerce
* Add new features
* Fix bugs and improve performance
* Ensure compliance with Armenian tax authority requirements

Update service terms and conditions are available on our website.

= Copyright & Licensing =

**Copyright © 2024 HK Digital Agency LLC (ԷՅՋԿԱ ԴԻՋԻՏԱԼ ԷՋԵՆՍԻ ՍՊԸ)**

This plugin and its contents are protected by copyright law.

**Restrictions:**
* Distribution, modification, or adaptation of the plugin code is prohibited without written permission
* Use of trademarks and branding materials is restricted
* The plugin is licensed for use, not for resale or redistribution

**License Grant:**
* Users receive a paid license to use the plugin on their WordPress/WooCommerce site
* License is non-transferable and site-specific
* Pricing and terms available at [hkdigital.am](https://hkdigital.am/) or by calling 033 779-779

**Disclaimer:**

HK Digital Agency LLC is not responsible for:
* Services provided by third-party plugins, applications, platforms, or organizations
* Technical issues arising from updates to WordPress, WooCommerce, or other plugins
* Partial or complete plugin malfunction due to third-party updates
* Website uptime, security, or data integrity (user responsibility)
* Compliance with tax laws (user must ensure proper configuration)

**Support:**
* Email: support@hkdigital.am
* Phone: 033 779-779
* Website: [hkdigital.am](https://hkdigital.am/)

= Credits =

Developed by **HK Digital Agency LLC**
* Website: [hkdigital.am](https://hkdigital.am/)
* Email: support@hkdigital.am
* Phone: 033 779-779

Special thanks to all businesses using our plugin and providing valuable feedback.

== External Services ==

This plugin communicates with the following external services:

**Armenian Tax Service API**
* Purpose: Generate and validate fiscal receipts
* Data Sent: Order details, tax information, certificate credentials
* Privacy Policy: Contact Armenian State Revenue Committee
* Terms of Service: Armenian tax law requirements
* Website: [petekamutner.am](https://petekamutner.am/)

**Note:** Use of this plugin requires compliance with Armenian tax laws and regulations. Users are responsible for ensuring proper configuration and legal compliance.