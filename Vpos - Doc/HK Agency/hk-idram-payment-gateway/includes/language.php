<?php

$overrideLocaleIdram = !empty(get_option('language_payment_idram')) ? get_option('language_payment_idram') : 'hy';
add_filter('plugin_locale','changeLanguageIdram', 10, 2);

/**
 * change location event
 *
 * @param $locale
 * @param $domain
 * @return string
 */
if (!function_exists('changeLanguageIdram')) {
    function changeLanguageIdram($locale, $domain)
    {
        global $currentPluginDomainIdram;
        global $overrideLocaleIdram;
        if ($domain == $currentPluginDomainIdram) {
            $locale = $overrideLocaleIdram;
        }
        return $locale;
    }
}