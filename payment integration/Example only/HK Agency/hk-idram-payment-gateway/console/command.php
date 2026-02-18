<?php

// Add 30 minute cron job

/**
 * Remove the specified resource from storage.
 *
 * @param $schedules
 * @return array
 */
function cronSchedulesForIdram($schedules)
{
    if (!isset($schedules["30min"])) {
        $schedules["30min"] = array(
            'interval' => 30 * 60,
            'display' => __('Once every 30 minutes'));
    }
    return $schedules;
}
add_filter('cron_schedules', 'cronSchedulesForIdram');

function initHKDIdramPlugin()
{
    if (!wp_next_scheduled('cronCheckOrder')) {
        wp_schedule_event(time(), '30min', 'cronCheckOrder');
    }
}
add_action('init', 'initHKDIdramPlugin');