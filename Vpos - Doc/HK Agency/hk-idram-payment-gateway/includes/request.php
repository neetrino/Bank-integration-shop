<?php

if (isset($_POST['woocommerce_hk-idram-payment-gateway_language_payment_idram'])) {
    $language = $_POST['woocommerce_hk-idram-payment-gateway_language_payment_idram'];
    if ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US')
        update_option('language_payment_idram', $language);
}

