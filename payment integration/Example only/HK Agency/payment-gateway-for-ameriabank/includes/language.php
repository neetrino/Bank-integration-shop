<?php

$overrideLocaleAmeria = !empty(get_option('language_payment')) ? get_option('language_payment') : 'hy';
add_filter('plugin_locale','changeLanguageAmeriaBank', 10, 2);

/**
 * change location event
 *
 * @param $locale
 * @param $domain
 * @return string
 */
function changeLanguageAmeriaBank($locale, $domain)
{
    global $currentPluginDomainAmeria;
    global $overrideLocaleAmeria;
    if ($domain == $currentPluginDomainAmeria) {
        $locale = $overrideLocaleAmeria;
    }
    return $locale;
}