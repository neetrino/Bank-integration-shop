<?php
/*
Plugin Name: Payment Gateway for Idram
Plugin URI: #
Description: Pay with Idram payment system. Please note that the payment will be made in Armenian Dram.
Version: 2.1.5
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


$currentPluginDomainIdram = 'hk-idram-payment-gateway';
$apiUrlIdram = 'https://plugins.hkdigital.am/api/';
$pluginDirUrlIdram = plugin_dir_url(__FILE__);
$pluginBaseNameIdram = dirname(plugin_basename(__FILE__));
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
$pluginDataIdram = get_plugin_data(__FILE__);


/**
 *
 * @param $gateways
 * @return array
 */
if (!function_exists('hkdAddIdramGatewayClass')) {
    function hkdAddIdramGatewayClass($gateways)
    {
        $gateways[] = 'WC_HKD_Idram_Gateway';
        return $gateways;
    }
}
add_filter('woocommerce_payment_gateways', 'hkdAddIdramGatewayClass');

include dirname(__FILE__) . '/console/command.php';

if (is_admin()) {
    include dirname(__FILE__) . '/includes/request.php';
    include dirname(__FILE__) . '/includes/language.php';
    include dirname(__FILE__) . '/includes/activate.php';
}

include dirname(__FILE__) . '/includes/main.php';
include dirname(__FILE__) . '/includes/redirectIdram.php';


/**
 * @param $links
 * @return array
 */
if (!function_exists('hkd_gateway_setting_link')) {
    function hkd_gateway_setting_link($links)
    {
        $links = array_merge(array(
            '<a href="' . esc_url(admin_url('/admin.php')) . '?page=wc-settings&tab=checkout&section=hk-idram-payment-gateway">' . __('Settings', 'hk-idram-payment-gateway') . '</a>'
        ), $links);
        return $links;
    }
}
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'hkd_gateway_setting_link');

add_action( 'woocommerce_blocks_loaded', 'woocommerce_idram_woocommerce_blocks_support' );

function woocommerce_idram_woocommerce_blocks_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once dirname( __FILE__ ) . '/includes/idram-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new Idram_Blocks_Support );
            }
        );
    }
}

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_idram_declare_hpos_compatibility() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}
add_action( 'before_woocommerce_init', 'woocommerce_idram_declare_hpos_compatibility' );
