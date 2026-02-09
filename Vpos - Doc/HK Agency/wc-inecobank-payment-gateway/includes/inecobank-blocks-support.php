<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Acbabank payment method integration
 *
 * @since 1.5.0
 */
final class HKDigital_InecoBank_Blocks_Support extends AbstractPaymentMethodType
{
    /**
     * Name of the payment method.
     *
     * @var string
     */
    protected $name = 'hkd_inecobank';

    private $pluginDirUrl;


    public function __construct()
    {

        global $pluginDirUrlIneco;

        $this->pluginDirUrl = $pluginDirUrlIneco;
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_hkd_inecobank_settings', []);
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        $payment_gateways_class = WC()->payment_gateways();
        $payment_gateways = $payment_gateways_class->payment_gateways();
        return $payment_gateways['hkd_inecobank']->is_available();
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
            'wc-hkd_inecobank-blocks-integration',
            $this->pluginDirUrl . 'assets/js/support.js',
            $dependencies,
            $version,
            true
        );
        wp_set_script_translations(
            'wc-hkd_inecobank-blocks-integration',
            'hkd_inecobank'
        );
        return [ 'wc-hkd_inecobank-blocks-integration' ];
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
            'logo_url' => $this->pluginDirUrl . 'assets/images/logo_inecobank.png',
        ];
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features()
    {
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        return $payment_gateways['hkd_inecobank']->supports;
    }
}
