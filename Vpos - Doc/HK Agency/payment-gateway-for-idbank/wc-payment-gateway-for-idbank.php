<?php
/*
Plugin Name:Payment gateway for IDBank
Plugin URI: #
Description: Pay with  IDBank payment system. Please note that the payment will be made in Armenian Dram.
Version: 1.0.8
Author: HK Digital Agency LLC
Author URI: https://hkdigital.am
License: GPLv2 or later
*/



$currentPluginDomainIdBank = 'payment-gateway-for-idbank';
$apiUrlIdBank = 'https://plugins.hkdigital.am/api/';
$pluginDirUrlIdBank = plugin_dir_url(__FILE__);
$pluginBaseNameIdBank = dirname(plugin_basename(__FILE__));
if( !function_exists('get_plugin_data') ){
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$pluginDataIdBank = get_plugin_data(__FILE__);

// Load translations at the proper time (init or later) to avoid early loading notices
add_action('init', function () {
    load_plugin_textdomain('payment-gateway-for-idbank', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

if (!function_exists('hkdigital_addIDBankGatewayClass')) {

    /**
     *
     * @param $gateways
     * @return array
     */
    function hkdigital_addIDBankGatewayClass($gateways)
    {
        $gateways[] = 'HKDigital_Idbank_Arca_Gateway';
        return $gateways;
    }
}
add_filter('woocommerce_payment_gateways', 'hkdigital_addIDBankGatewayClass');


include dirname(__FILE__) . '/console/command.php';
include dirname(__FILE__) . '/includes/thankyou.php';

if (is_admin()) {
    include dirname(__FILE__) . '/includes/activate.php';
}

include dirname(__FILE__) . '/includes/errorCodes.php';
include dirname(__FILE__) . '/includes/main.php';



add_action('plugin_action_links_' . plugin_basename(__FILE__), 'hkdigital_idbank_gateway_setting_link');
if (!function_exists('hkdigital_idbank_gateway_setting_link')) {
    function hkdigital_idbank_gateway_setting_link($links)
    {
        $links = array_merge(array(
            '<a href="' . esc_url(admin_url('/admin.php')) . '?page=wc-settings&tab=checkout&section=hkd_idbank">Settings</a>'
        ), $links);
        return $links;
    }
}

add_action( 'woocommerce_blocks_loaded', 'hkdigital_woocommerce_idbank_woocommerce_blocks_support' );

if (!function_exists('hkdigital_woocommerce_idbank_woocommerce_blocks_support')) {
    function hkdigital_woocommerce_idbank_woocommerce_blocks_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once dirname(__FILE__) . '/includes/idbank-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new IDBank_Blocks_Support);
                }
            );
        }
    }
}

if (!function_exists('hkdigital_woocommerce_idbank_declare_hpos_compatibility')) {
    /**
     * Declares support for HPOS.
     *
     * @return void
     */
    function hkdigital_woocommerce_idbank_declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
}
add_action( 'before_woocommerce_init', 'hkdigital_woocommerce_idbank_declare_hpos_compatibility' );


// Ensure rewrite rules are flushed only on activation to avoid performance impact
register_activation_hook(__FILE__, function () {
    // Register the endpoint used by the plugin and flush rules once
    add_rewrite_endpoint('cards', EP_PERMALINK | EP_PAGES);
    flush_rewrite_rules();
});



/**
 * Move WooCommerce rating notice to the bottom only on our gateway settings page.
 * We do NOT modify WooCommerce – just reposition visually on this screen.
 * Can be disabled via filter: hkd_idbank_move_wc_rating_notice (default true).
 */
add_action('admin_footer', function () {
    if (!apply_filters('hkd_idbank_move_wc_rating_notice', true)) {
        return;
    }
    if (!function_exists('get_current_screen')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'woocommerce_page_wc-settings') {
        return;
    }
    $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
    $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
    if ($tab !== 'checkout' || $section !== 'hkd_idbank') {
        return;
    }
    // User requested to move ALL notices on this specific page. You can disable by filtering to false.
    $move_all = apply_filters('hkd_idbank_move_all_notices_on_settings', true);
    ?>
    <script>
    // Expose toggle to JS
    window.hkdIdbankMoveAllNotices = <?php echo $move_all ? 'true' : 'false'; ?>;
    (function(){
      function moveWCRatingNotice(){
        try {
          var container = document.querySelector('#mainform')
            || document.querySelector('#wpbody-content .wrap form')
            || document.querySelector('#wpbody-content .wrap')
            || document.querySelector('#wpcontent')
            || document.body;
          if (!container) return;

          var moveAll = !!window.hkdIdbankMoveAllNotices;

          var candidates = Array.prototype.slice.call(document.querySelectorAll(
            '#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .update-nag, #wpbody-content .woocommerce-message, #wpbody-content .wc-admin-notice, ' +
            '#wpcontent .notice, #wpcontent .woocommerce-message, #wpcontent .wc-admin-notice'
          ));

          candidates.forEach(function(el){
            if (el.dataset && el.dataset.hkdMoved) return;
            var text = (el.textContent || '').toLowerCase();
            var hasReviewLink = !!el.querySelector('a[href*="wordpress.org"][href*="woocommerce"][href*="review"], a[href*="wordpress.org/plugins/woocommerce"][href*="reviews"]');
            var hasStars = text.indexOf('★★★★★') !== -1 || text.indexOf('5-star') !== -1 || text.indexOf('five-star') !== -1;
            var mentionsWoo = text.indexOf('woocommerce') !== -1;

            if (moveAll || hasReviewLink || (mentionsWoo && hasStars)) {
              if (container.insertAdjacentElement) {
                container.insertAdjacentElement('afterend', el);
              } else {
                (container.parentNode || container).appendChild(el);
              }
              el.style.marginTop = '20px';
              el.dataset.hkdMoved = '1';
            }
          });
        } catch(e) { /* no-op */ }
      }

      function ready(fn){
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
          setTimeout(fn, 0);
        } else {
          document.addEventListener('DOMContentLoaded', fn);
        }
      }

      ready(moveWCRatingNotice);
      window.addEventListener('load', moveWCRatingNotice);
      // Retries for late-inserted notices
      setTimeout(moveWCRatingNotice, 400);
      setTimeout(moveWCRatingNotice, 1200);
      setTimeout(moveWCRatingNotice, 3000);

      var wrap = document.querySelector('#wpbody-content') || document.querySelector('#wpcontent') || document.body;
      if (window.MutationObserver && wrap){
        var mo = new MutationObserver(function(){ moveWCRatingNotice(); });
        mo.observe(wrap, {childList:true, subtree:true});
        // Keep observer a bit longer to catch late injections
        setTimeout(function(){ try{ mo.disconnect(); }catch(e){} }, 10000);
      }
    })();
    </script>
    <?php
});
