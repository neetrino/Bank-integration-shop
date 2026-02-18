<?php

$overrideLocaleIneco = !empty(get_option('language_payment_inecobank')) ? get_option('language_payment_inecobank') : 'hy';
add_filter('plugin_locale','changeLanguageInecoBank', 10, 2);

/**
 * change location event
 *
 * @param $locale
 * @param $domain
 * @return string
 */
function changeLanguageInecoBank($locale, $domain)
{
    global $currentPluginDomainIneco;
    global $overrideLocaleIneco;
    if ($domain == $currentPluginDomainIneco) {
        $locale = $overrideLocaleIneco;
    }
    return $locale;
}