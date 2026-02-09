<?php
/*
Plugin Name: Payment Gateway for Telcell Wallet
Plugin URI: #
Description: Pay with Telcell payment system. Please note that the payment will be made in Armenian Dram.
Version: 2.0.4
Author: HK Digital Agency LLC
Author URI: https://hkdigital.am
License: GPLv2 or later
 */

/*
 * Սույն հավելման(plugin) պարունակությունը պաշպանված է հեղինակային և հարակից իրավունքների մասին Հայաստանի Հանրապետության օրենսդրությամբ:
 * Արգելվում է պարունակության  վերարտադրումը, տարածումը, նկարազարդումը, հարմարեցումը և այլ ձևերով վերափոխումը,
 * ինչպես նաև այլ եղանակներով օգտագործումը, եթե մինչև նման օգտագործումը ձեռք չի բերվել ԷՅՋԿԱ ԴԻՋԻՏԱԼ ԷՋԵՆՍԻ ՍՊԸ-ի թույլտվությունը:
 */

$currentPluginDomainTelcell = 'hkd_telcell';
$apiUrlTelcell = 'https://plugins.hkdigital.am/api/';
$pluginDirUrlTelcell = plugin_dir_url(__FILE__);
$pluginBaseNameTelcell = dirname(plugin_basename(__FILE__));
if( !function_exists('get_plugin_data') ){
    if ( ! defined( 'ABSPATH' ) ) exit;
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$pluginDataTelcell = get_plugin_data(__FILE__);



if (!function_exists('hkdigital_AddTelcellGatewayClass')) {
    /**
     *
     * @param $gateways
     * @return array
     */
    function hkdigital_AddTelcellGatewayClass($gateways)
    {
        $gateways[] = 'HKDigital_Telcell_Gateway';
        return $gateways;
    }
}
add_filter('woocommerce_payment_gateways', 'hkdigital_AddTelcellGatewayClass');

if (is_admin()) {
    include dirname(__FILE__) . '/includes/request.php';
    include dirname(__FILE__) . '/includes/language.php';
    include dirname(__FILE__) . '/includes/activate.php';
}

include dirname(__FILE__) . '/includes/main.php';


add_action('plugin_action_links_' . plugin_basename(__FILE__), 'hkdigital_gateway_setting_link_telcell');

if (!function_exists('hkdigital_gateway_setting_link_telcell')) {

    function hkdigital_gateway_setting_link_telcell($links)
    {
        $links = array_merge(array(
            '<a href="' . esc_url(admin_url('/admin.php')) . '?page=wc-settings&tab=checkout&section=hkd_telcell">' . __('Settings', 'hkd_telcell') . '</a>'
        ), $links);
        return $links;
    }
}


add_action( 'woocommerce_blocks_loaded', 'hkdigital_telcell_woocommerce_blocks_support' );

if (!function_exists('hkdigital_telcell_woocommerce_blocks_support')) {

    function hkdigital_telcell_woocommerce_blocks_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once dirname(__FILE__) . '/includes/telcell-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new Telcell_Blocks_Support);
                }
            );
        }
    }
}

if (!function_exists('hkdigital_telcell_declare_hpos_compatibility')) {

    /**
     * Declares support for HPOS.
     *
     * @return void
     */
    function hkdigital_telcell_declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
}
add_action( 'before_woocommerce_init', 'hkdigital_telcell_declare_hpos_compatibility' );
