<?php
/*
Plugin Name: Payment Gateway for FastShift
Plugin URI: #
Description: Pay with FastShift payment system. Please note that the payment will be made in Armenian Dram.
Version: 1.0.1
Author: HK Digital Agency LLC
Author URI: https://hkdigital.am
License: GPLv2 or later
 */


/*
 *
 * Սույն հավելման(plugin) պարունակությունը պաշպանված է հեղինակային և հարակից իրավունքների մասին Հայաստանի Հանրապետության օրենսդրությամբ:
 * Արգելվում է պարունակության  վերարտադրումը, տարածումը, նկարազարդումը, հարմարեցումը և այլ ձևերով վերափոխումը,
 * ինչպես նաև այլ եղանակներով օգտագործումը, եթե մինչև նման օգտագործումը ձեռք չի բերվել ԷՅՋԿԱ ԴԻՋԻՏԱԼ ԷՋԵՆՍԻ ՍՊԸ-ի թույլտվությունը:
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;
$currentPluginDomainFastshift = 'payment-gateway-for-fastshift';
$apiUrlFastshift = 'https://plugins.hkdigital.am/api/';
$pluginDirUrlFastshift = plugin_dir_url(__FILE__);
$pluginBaseNameFastshift = dirname(plugin_basename(__FILE__));

if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
$pluginDataFastshift = get_plugin_data(__FILE__);


/**
 *
 * @param $gateways
 * @return array
 */
if (!function_exists('hkdigital_AddFastshiftGatewayClass')) {
    function hkdigital_AddFastshiftGatewayClass($gateways)
    {
        $gateways[] = 'HKDigital_Fastshift_Gateway';
        return $gateways;
    }
}
add_filter('woocommerce_payment_gateways', 'hkdigital_AddFastshiftGatewayClass');

include dirname(__FILE__) . '/console/command.php';

if (is_admin()) {
    include dirname(__FILE__) . '/includes/request.php';
    include dirname(__FILE__) . '/includes/language.php';
    include dirname(__FILE__) . '/includes/activate.php';
}

include dirname(__FILE__) . '/includes/main.php';

/**
 * @param $links
 * @return array
 */
if (!function_exists('hkdigital_fastshift_gateway_setting_link')) {
    function hkdigital_fastshift_gateway_setting_link($links)
    {
        $links = array_merge(array(
            '<a href="' . esc_url(admin_url('/admin.php')) . '?page=wc-settings&tab=checkout&section=payment-gateway-for-fastshift">' . __('Settings', 'payment-gateway-for-fastshift') . '</a>'
        ), $links);
        return $links;
    }
}
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'hkdigital_fastshift_gateway_setting_link');

add_action('woocommerce_blocks_loaded', 'hkdigital_fastshift_woocommerce_blocks_support');
if (!function_exists('hkdigital_fastshift_woocommerce_blocks_support')) {
    function hkdigital_fastshift_woocommerce_blocks_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once dirname(__FILE__) . '/includes/fastshift-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new HKDigital_Fastshift_Blocks_Support);
                }
            );
        }
    }
}
if (!function_exists('hkdigital_fastshift_declare_hpos_compatibility')) {

    /**
     * Declares support for HPOS.
     *
     * @return void
     */
    function hkdigital_fastshift_declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
}
add_action('before_woocommerce_init', 'hkdigital_fastshift_declare_hpos_compatibility');
