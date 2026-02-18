<?php
/*
Plugin Name: TAX SERVICE Electronic HDM
Plugin URI: #
Description: Էլեկտրոնային ՀԴՄ Վեբ Ծառայություն
Version: 1.2.3
Author: HK Digital Agency LLC
Author URI: https://hkdigital.am
License: GPLv2 or later
Text Domain: virtual-hdm-for-taxservice-am
Domain Path: /languages
*/

if (!defined('ABSPATH') ) {
    exit;
}

$tax_service_plugin_url = plugin_dir_url( __FILE__ );
$tax_service_dirname = dirname( __FILE__ );

$apiUrlTaxService = 'https://plugins.hkdigital.am/api/';

add_action('admin_menu', 'hkdigital_admin_menu_tax_service');
if (!function_exists('hkdigital_admin_menu_tax_service')) {
    function hkdigital_admin_menu_tax_service()
    {
        /** Top Menu **/
        add_menu_page(__('Էլեկտրոնային ՀԴՄ', 'hkdadmin'), __('Էլեկտրոնային ՀԴՄ', 'hkdadmin'), 'manage_options', 'tax-service-settings', 'plugin_page', 'dashicons-groups', null);
        add_submenu_page('tax-service-settings', __('Կարգավորումներ', 'hkdadmin'), __('Կարգավորումներ', 'hkdadmin'), 'manage_options', 'tax-service-settings', 'plugin_page');
        add_submenu_page('tax-service-settings', __('Հաշվետվություններ', 'hkdadmin'), __('Հաշվետվություններ', 'hkdadmin'), 'manage_options', 'tax-service-report', 'plugin_page');
        add_submenu_page('tax-service-settings', __('Հարցումներ', 'hkdadmin'), __('Հարցումներ', 'hkdadmin'), 'manage_options', 'tax-service-request-log', 'plugin_page');
        add_submenu_page('tax-service-settings', __('Սխալներ', 'hkdadmin'), __('Սխալներ', 'hkdadmin'), 'manage_options', 'tax-service-errors', 'plugin_page');
    }
}

add_action('init', function () {
    include dirname(__FILE__) . '/admin/service/ErrorService.php';
    include dirname(__FILE__) . '/admin/service/RequestLogService.php';
    include dirname(__FILE__) . '/payment/WCHKDTaxServicePaymentController.php';
    include dirname(__FILE__) . '/checkout/checkout.php';
    if (class_exists('WCHKDTaxServicePaymentController')) {
        new WCHKDTaxServicePaymentController();
    }
});

if (is_admin()) {
    // Include activation logic; plugin version will be resolved lazily inside the hook to avoid early i18n loads
    include dirname(__FILE__) . '/includes/activate.php';
}

// Load all plugin text domains correctly on init to avoid early loading notices (WP 6.7+)
add_action('init', function () {
    $rel_path = dirname(plugin_basename(__FILE__)) . '/languages';
    // Primary domain (match plugin header "Text Domain")
    load_plugin_textdomain('virtual-hdm-for-taxservice-am', false, $rel_path);
    // Secondary domains used in codebase
    load_plugin_textdomain('tax-service', false, $rel_path);
    load_plugin_textdomain('hkdadmin', false, $rel_path);
});

add_action('admin_init', function () {
    include dirname(__FILE__) . '/admin/service/ExportTaxService.php';
    include dirname(__FILE__) . '/admin/service/ATGCodeService.php';
    include dirname(__FILE__) . '/admin/controllers/MainController.php';
    include dirname(__FILE__) . '/admin/controllers/VerificationController.php';
    include dirname(__FILE__) . '/admin/controllers/ExportController.php';
    include dirname(__FILE__) . '/admin/controllers/ImportController.php';
    include dirname(__FILE__) . '/admin/controllers/ProductSettingsController.php';
    include dirname(__FILE__) . '/admin/controllers/ReportTable.php';
    include dirname(__FILE__) . '/admin/controllers/ErrorReportTable.php';
    include dirname(__FILE__) . '/admin/controllers/RequestLogTable.php';

    // Securely register admin-only AJAX actions
    add_action('wp_ajax_exportTaxService', 'exportSettingsAndData');
    add_action('wp_ajax_importTaxService', 'importTaxServiceData');
});


add_action('wp_ajax_checkTaxServiceVerification', 'checkTaxServiceVerification');

register_activation_hook(__FILE__, 'hkdigital_createTaxServiceTableInProcessActivation');
if (!function_exists('hkdigital_createTaxServiceTableInProcessActivation')) {
    function hkdigital_createTaxServiceTableInProcessActivation()
    {
        global $wpdb;
        $createTaxServiceTable = "CREATE TABLE  IF NOT EXISTS `{$wpdb->prefix}tax_service`(
                      `id` INT NOT NULL AUTO_INCREMENT,
                      `order_id` INT,
                      `crn` VARCHAR(255),
                      `sn` VARCHAR(255),
                      `tin` VARCHAR(255),
                      `taxpayer` VARCHAR(255),
                      `address` VARCHAR(255),
                      `status` VARCHAR(255),
                      `time` INT,
                      `fiscal` VARCHAR(255),
                      `total` DOUBLE,
                      `qr` VARCHAR(255),
                      `created_at` DATETIME,
                      PRIMARY KEY (`id`)
                    );
                    ";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($createTaxServiceTable);

        $createTaxServiceReportTable = "CREATE TABLE  IF NOT EXISTS `{$wpdb->prefix}tax_service_report`(
                      `id` INT NOT NULL AUTO_INCREMENT,
                      `order_id` INT,
                      `payment_gateway` VARCHAR(255),
                      `error_reason` VARCHAR(255),
                      `message` longtext,
                      `created_at` DATETIME,
                      PRIMARY KEY (`id`)
                    );";
        dbDelta($createTaxServiceReportTable);

        $createTaxServiceRequestsTable = "CREATE TABLE  IF NOT EXISTS `{$wpdb->prefix}tax_service_requests`(
                      `id` INT NOT NULL AUTO_INCREMENT,
                      `order_id` INT,
                      `payment_gateway` VARCHAR(255),
                      `url` VARCHAR(255),
                      `request_data` longtext,
                      `response_data` longtext,
                      `created_at` DATETIME,
                      PRIMARY KEY (`id`)
                    );";
        dbDelta($createTaxServiceRequestsTable);
    }
}

add_action('plugin_action_links_' . plugin_basename(__FILE__), 'hkdigital_tax_service_setting_link');
if (!function_exists('hkdigital_tax_service_setting_link')) {
    function hkdigital_tax_service_setting_link($links)
    {
        return array_merge(array(
            '<a href="' . esc_url(admin_url('/admin.php')) . '?page=tax-service-settings">' . __('Settings', 'tax-service') . '</a>'
        ), $links);
    }
}


