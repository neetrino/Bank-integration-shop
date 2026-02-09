<?php
if (isset($_POST['woocommerce_hkd_inecobank_language_payment_inecobank'])) {
    $language = $_POST['woocommerce_hkd_inecobank_language_payment_inecobank'];
    if ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US')
        update_option('language_payment_inecobank', $language);
}

