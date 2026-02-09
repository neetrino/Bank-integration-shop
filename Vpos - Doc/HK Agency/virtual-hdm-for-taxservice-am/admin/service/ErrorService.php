<?php

if (!class_exists('ErrorService')) {
    class ErrorService
    {

        public static function hkdTaxServiceInsertTaxErrorReport($orderId, $reason, $message, $paymentGateway)
        {
            global $wpdb;
            $args = [
                'order_id' => $orderId,
                'payment_gateway' => $paymentGateway,
                'error_reason' => $reason,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $table_name = $wpdb->prefix . 'tax_service_report';
            if ($wpdb->insert($table_name, $args)) {
                return $wpdb->insert_id;
            }
            return false;
        }
    }
}