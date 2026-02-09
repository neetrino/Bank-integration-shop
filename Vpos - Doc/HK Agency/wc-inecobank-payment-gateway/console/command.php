<?php

// Add 30 minute cron job

if (!function_exists('hkdigital_cronSchedulesForInecoBank')) {

    /**
     * Remove the specified resource from storage.
     *
     * @param $schedules
     * @return array
     */
    function hkdigital_cronSchedulesForInecoBank($schedules)
    {
        if (!isset($schedules["30min"])) {
            $schedules["30min"] = array(
                'interval' => 30 * 60,
                'display' => __('Once every 30 minutes'));
        }
        return $schedules;
    }
}
add_filter('cron_schedules', 'hkdigital_cronSchedulesForInecoBank');
if (!function_exists('hkdigital_initHKDInecoPlugin')) {
    function hkdigital_initHKDInecoPlugin()
    {
        if (!wp_next_scheduled('cronCheckOrderIneco')) {
            wp_schedule_event(time(), '30min', 'cronCheckOrderIneco');
        }
        add_rewrite_endpoint('cards', EP_PERMALINK | EP_PAGES, 'cards');
        flush_rewrite_rules();
    }
}
add_action('init', 'hkdigital_initHKDInecoPlugin');