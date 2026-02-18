<?php
/*
Plugin Name:Payment gateway for Ameriabank
Plugin URI: #
Description: Pay with  Ameriabank payment system. Please note that the payment will be made in Armenian Dram.
Version: 1.2.3
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


$currentPluginDomainAmeria = 'payment-gateway-for-ameriabank';
$apiUrlAmeria = 'https://plugins.hkdigital.am/api/';
$pluginDirUrlAmeria = plugin_dir_url(__FILE__);
$pluginBaseNameAmeria = dirname(plugin_basename(__FILE__));
if (!defined('ABSPATH')) exit;
if( !function_exists('get_plugin_data') ){
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$pluginDataAmeria = get_plugin_data(__FILE__);

if (!function_exists('hkdAddAmeriaBankGatewayClass')) {

    /**
     *
     * @param $gateways
     * @return array
     */
    function hkdAddAmeriaBankGatewayClass($gateways)
    {
        $gateways[] = 'WC_HKD_Ameriabank_Arca_Gateway';
        return $gateways;
    }
}
add_filter('woocommerce_payment_gateways', 'hkdAddAmeriaBankGatewayClass');


include dirname(__FILE__) . '/console/command.php';
include dirname(__FILE__) . '/includes/thankyou.php';

if (is_admin()) {
    include dirname(__FILE__) . '/includes/activate.php';
}

include dirname(__FILE__) . '/includes/errorCodes.php';
include dirname(__FILE__) . '/includes/main.php';


if (!function_exists('hkd_ameria_gateway_setting_link')) {

    /**
     *
     * @param $links
     * @return array
     */
    function hkd_ameria_gateway_setting_link($links)
    {
        $links = array_merge(array(
            '<a href="' . esc_url(admin_url('/admin.php')) . '?page=wc-settings&tab=checkout&section=hkd_ameriabank">' . __('Settings', 'payment-gateway-for-ameriabank') . '</a>'
        ), $links);
        return $links;
    }
}
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'hkd_ameria_gateway_setting_link');



add_action( 'woocommerce_blocks_loaded', 'woocommerce_ameriabank_woocommerce_blocks_support' );
if (!function_exists('woocommerce_ameriabank_woocommerce_blocks_support')) {
    function woocommerce_ameriabank_woocommerce_blocks_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once dirname(__FILE__) . '/includes/ameriabank-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new Ameriabank_Blocks_Support);
                }
            );
        }
    }
}
if (!function_exists('woocommerce_ameriabank_declare_hpos_compatibility')) {
    /**
     * Declares support for HPOS.
     *
     * @return void
     */
    function woocommerce_ameriabank_declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
}
add_action( 'before_woocommerce_init', 'woocommerce_ameriabank_declare_hpos_compatibility' );
