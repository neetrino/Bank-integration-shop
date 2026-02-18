<?php

$overrideLocaleTelcell = !empty(get_option('language_payment_telcell')) ? get_option('language_payment_telcell') : 'hy';
add_filter('plugin_locale','hkdigital_changeLanguageTelcell', 10, 2);

if (!function_exists('hkdigital_changeLanguageTelcell')) {

    /**
     * change location event
     *
     * @param $locale
     * @param $domain
     * @return string
     */
    function hkdigital_changeLanguageTelcell($locale, $domain)
    {
        global $currentPluginDomainTelcell;
        global $overrideLocaleTelcell;
        if ($domain == $currentPluginDomainTelcell) {
            $locale = $overrideLocaleTelcell;
        }
        return $locale;
    }
}
