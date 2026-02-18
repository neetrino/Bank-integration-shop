<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if (isset($_POST['woocommerce_payment-gateway-for-fastshift_language_payment_fastshift'])) {
    $language = sanitize_text_field($_POST['woocommerce_payment-gateway-for-fastshift_language_payment_fastshift']);
    if ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US')
        update_option('hkdigital_language_payment_fastshift', sanitize_text_field($language));
}

