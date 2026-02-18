<?php

$overrideLocaleIdBank = !empty(get_option('language_payment_idbank')) ? get_option('language_payment_idbank') : 'hy';
add_filter('plugin_locale','changeLanguageIDBank', 10, 2);

/**
 * change location event
 *
 * @param $locale
 * @param $domain
 * @return string
 */
function changeLanguageIDBank($locale, $domain)
{
    global $currentPluginDomainIdBank;
    global $overrideLocaleIdBank;
    if ($domain == $currentPluginDomainIdBank) {
        $locale = $overrideLocaleIdBank;
    }
    return $locale;
}