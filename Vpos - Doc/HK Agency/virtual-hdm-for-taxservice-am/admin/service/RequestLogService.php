<?php

if (!class_exists('RequestLogService')) {
    class RequestLogService
    {
        public static function hkdTaxServiceRequestReport($orderId, $url, $requestData, $response, $paymentGateway)
        {
            global $wpdb;
            $args = [
                'order_id' => $orderId,
                'payment_gateway' => $paymentGateway,
                'url' => $url,
                'request_data' =>  json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_data' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at'     => current_time( 'mysql' ) 
            ];
            $table_name = $wpdb->prefix . 'tax_service_requests';
            if ($wpdb->insert($table_name, $args)) {
                return $wpdb->insert_id;
            }
            return false;
        }
    }
}