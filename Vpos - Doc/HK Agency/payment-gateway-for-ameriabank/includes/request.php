<?php
if (isset($_POST['woocommerce_hkd_ameriabank_language_payment'])) {
    $language = $_POST['woocommerce_hkd_ameriabank_language_payment'];
    if ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US')
        update_option('language_payment', $language);
}
