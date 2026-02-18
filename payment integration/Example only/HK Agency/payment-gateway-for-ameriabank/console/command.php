<?php

// Add 30 minute cron job

if (!function_exists('hkdigital_cronSchedulesForAmeriaBank')) {

    /**
     * Remove the specified resource from storage.
     *
     * @param $schedules
     * @return array
     */
    function hkdigital_cronSchedulesForAmeriaBank($schedules)
    {
        if (!isset($schedules["30min"])) {
            $schedules["30min"] = array(
                'interval' => 30 * 60,
                'display' => __('Once every 30 minutes'));
        }
        return $schedules;
    }
}
add_filter('cron_schedules', 'hkdigital_cronSchedulesForAmeriaBank');

if (!function_exists('hkdigital_initHKDAmeriaPlugin')) {

    function hkdigital_initHKDAmeriaPlugin()
    {
        if (!wp_next_scheduled('cronCheckOrderAmeria')) {
            wp_schedule_event(time(), '30min', 'cronCheckOrderAmeria');
        }
        add_rewrite_endpoint('cards', EP_PERMALINK | EP_PAGES, 'cards');
        flush_rewrite_rules();
    }
}
add_action('init', 'hkdigital_initHKDAmeriaPlugin');