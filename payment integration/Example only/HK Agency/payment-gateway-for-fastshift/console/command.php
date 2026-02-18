<?php
if (!defined( 'ABSPATH' ) ) exit;
// Add 30 minute cron job
if (!function_exists('hkdigital_cronSchedulesForFastshift')) {
    /**
     * Remove the specified resource from storage.
     *
     * @param $schedules
     * @return array
     */
    function hkdigital_cronSchedulesForFastshift($schedules)
    {
        if (!isset($schedules["30min"])) {
            $schedules["30min"] = array(
                'interval' => 30 * 60,
                'display' => __('Once every 30 minutes'));
        }
        return $schedules;
    }
}
add_filter('cron_schedules', 'hkdigital_cronSchedulesForFastshift');

if (!function_exists('hkdigital_initHKDFastshiftPlugin')) {
    function hkdigital_initHKDFastshiftPlugin()
    {
        if (!wp_next_scheduled('cronCheckOrder')) {
            wp_schedule_event(time(), '30min', 'cronCheckOrder');
        }
    }
}
add_action('init', 'hkdigital_initHKDFastshiftPlugin');