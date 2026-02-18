<?php


if (isset($_POST['woocommerce_hkd_telcell_language_payment_telcell'])) {
    $language = sanitize_text_field($_POST['woocommerce_hkd_telcell_language_payment_telcell']);
    if ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US')
        update_option('language_payment_telcell', $language);
}

