<?php

if (!class_exists('ExportTaxService')) {
    class ExportTaxService
    {

        public static function exportSettingsAndData()
        {
            global $wpdb;

            // Predefined table names (whitelist)
            $tables = [
                $wpdb->prefix . 'tax_service_report',
                $wpdb->prefix . 'tax_service'
            ];

            $export = [
                'version' => '1.0',
                'generated_at' => time(),
                'tables' => [],
                'options' => []
            ];

            foreach ($tables as $table) {
                // Ensure table names are valid
                if (!self::isValidTableName($table)) {
                    continue; // Skip if the table name is invalid
                }

                // Get table rows safely
                $results = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
                $export['tables'][$table] = $results ?: [];
            }

            // Whitelisted options
            $settingArray = [
                'hkd_tax_settings_completed',
                'hkd_tax_service_register_number',
                'hkd_tax_service_tin',
                'hkd_tax_service_tax_type',
                'hkd_tax_service_check_both_type',
                'hkd_tax_service_vat_percent',
                'hkd_tax_service_treasurer',
                'hkd_tax_service_atg_code',
                'hkd_tax_service_units_value',
                'hkd_tax_service_api_activated',
                'hkd_tax_service_api_url',
                'hkd_tax_service_enabled',
                'hkd_tax_service_enabled_type',
                'hkd_tax_service_verification_code_same_sku',
                'hkd_tax_service_shipping_atg_code',
                'hkd_tax_service_shipping_description',
                'hkd_tax_service_shipping_good_code',
                'hkd_tax_service_shipping_unit_value',
                'hkd_tax_service_automatically_print_status',
                'hkd_tax_service_shipping_activated',
                'hkd_tax_service_send_refund_to_user',
                'hkd_tax_service_send_refund_to_admin',
                'hkd_tax_service_send_to_admin',
                'taxServiceSeqNumber'
            ];

            foreach ($settingArray as $item) {
                $export['options'][$item] = get_option($item);
            }

            // Generate file path
            $upload_dir = wp_upload_dir();
            $filename = trailingslashit($upload_dir['basedir']) . 'tax-service-backup-' . time() . '-' . (md5(implode(',', $tables))) . '.json';

            // Write JSON to file
            $encoded = wp_json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($filename, $encoded);

            return str_replace($_SERVER['DOCUMENT_ROOT'], '', $filename);
        }

        // Helper function to validate table names (ensure they only contain alphanumeric characters and underscores)
        private static function isValidTableName($table)
        {
            return preg_match('/^[a-zA-Z0-9_]+$/', $table);
        }
    }
}
?>