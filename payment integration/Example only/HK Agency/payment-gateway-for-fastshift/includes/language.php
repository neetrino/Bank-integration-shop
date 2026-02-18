<?php

if ( ! defined( 'ABSPATH' ) ) exit;
$overrideLocaleFastshift = !empty(get_option('hkdigital_language_payment_fastshift')) ? get_option('language_payment_fastshift') : 'hy';
add_filter('plugin_locale', 'hkdigital_changeLanguageFastshift', 10, 2);

/**
 * change location event
 *
 * @param $locale
 * @param $domain
 * @return string
 */
if (!function_exists('hkdigital_changeLanguageFastshift')) {
    function hkdigital_changeLanguageFastshift($locale, $domain)
    {
        global $currentPluginDomainFastshift;
        global $overrideLocaleFastshift;
        if ($domain == $currentPluginDomainFastshift) {
            $locale = $overrideLocaleFastshift;
        }
        return $locale;
    }
}