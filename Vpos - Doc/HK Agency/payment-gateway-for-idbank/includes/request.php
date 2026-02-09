<?php

if (isset($_POST['woocommerce_hkd_idbank_language_payment_idbank'])) {
    $language = $_POST['woocommerce_hkd_idbank_language_payment_idbank'];
    if ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US')
        update_option('language_payment_idbank', $language);
}
