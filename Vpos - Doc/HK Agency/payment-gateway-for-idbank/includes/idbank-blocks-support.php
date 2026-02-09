<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * IdBank payment method integration
 *
 * @since 1.5.0
 */
final class IDBank_Blocks_Support extends AbstractPaymentMethodType
{
    /**
     * Name of the payment method.
     *
     * @var string
     */
    protected $name = 'hkd_idbank';

    private $pluginDirUrl;


    public function __construct()
    {

        global $pluginDirUrlIdBank;

        $this->pluginDirUrl = $pluginDirUrlIdBank;
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_hkd_idbank_settings', []);
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        if ( ! function_exists('WC') || ! WC() ) {
            return false;
        }
        $payment_gateways_class = WC()->payment_gateways();
        if ( ! $payment_gateways_class || ! method_exists( $payment_gateways_class, 'payment_gateways' ) ) {
            return false;
        }
        $payment_gateways = $payment_gateways_class->payment_gateways();
        if ( isset( $payment_gateways['hkd_idbank'] ) && method_exists( $payment_gateways['hkd_idbank'], 'is_available' ) ) {
            return (bool) $payment_gateways['hkd_idbank']->is_available();
        }
        return false;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $version      = '1.0.1';
        $dependencies = [
            'wc-blocks-registry',
            'wc-settings',
            'wp-element',
            'wp-html-entities',
            'wp-i18n'
        ];

        wp_register_script(
            'wc-hkd_idbank-blocks-integration',
            $this->pluginDirUrl . 'assets/js/support.js',
            $dependencies,
            $version,
            true
        );
        wp_set_script_translations(
            'wc-hkd_idbank-blocks-integration',
            'payment-gateway-for-idbank'
        );
        return [ 'wc-hkd_idbank-blocks-integration' ];
    }


    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => $this->get_supported_features(),
            'logo_url' => $this->pluginDirUrl . 'assets/images/logo_idbank.png',
        ];
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features()
    {
        if ( ! function_exists('WC') || ! WC() || ! isset( WC()->payment_gateways ) ) {
            return [];
        }
        $gateways_obj = WC()->payment_gateways;
        if ( method_exists( $gateways_obj, 'payment_gateways' ) ) {
            $payment_gateways = $gateways_obj->payment_gateways();
        } else {
            return [];
        }
        if ( isset( $payment_gateways['hkd_idbank'] ) && isset( $payment_gateways['hkd_idbank']->supports ) ) {
            return $payment_gateways['hkd_idbank']->supports;
        }
        return [];
    }

    /**
     * Returns an array of style handles for this payment method (for Blocks checkout UI).
     *
     * @return array
     */
    public function get_payment_method_style_handles() {
        $version = '1.0.7';
        wp_register_style(
            'wc-hkd_idbank-blocks-style',
            $this->pluginDirUrl . 'assets/css/cards.css',
            array(),
            $version
        );
        return [ 'wc-hkd_idbank-blocks-style' ];
    }
}
