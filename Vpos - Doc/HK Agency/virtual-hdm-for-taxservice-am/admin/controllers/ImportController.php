<?php

function importTaxServiceData()
{
    if (!class_exists('importTaxServiceController')) {
        class ImportTaxServiceController
        {

            public function __construct()
            {
                $this->importFile();
            }

            public function importFile()
            {
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => __('Անբավարար իրավասություն', 'tax-service')], 403);
                }
                check_ajax_referer('taxservice_admin');

                if (empty($_FILES['file']['tmp_name'])) {
                    wp_send_json_error(['message' => __('Ֆայլ չի ընտրվել', 'tax-service')]);
                }

                $file      = $_FILES['file'];
                $path      = $file['tmp_name'];
                $filename  = $file['name'];
                $ext       = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $mime      = !empty($file['type']) ? $file['type'] : '';
                $size      = !empty($file['size']) ? (int)$file['size'] : 0;

                // Accept only small JSON export files
                if ($ext !== 'json' || $size <= 0 || $size > 2 * 1024 * 1024) { // 2MB limit
                    wp_send_json_error(['message' => __('Ֆայլի ֆորմատը կամ չափը սխալ է (թերևս պետք է JSON, մինչև 2ՄԲ)', 'tax-service')]);
                }

                // Read and parse JSON
                $raw = file_get_contents($path);
                $data = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                    wp_send_json_error(['message' => __('Սխալ JSON ֆորմատ', 'tax-service')]);
                }

                global $wpdb;

                // Validate structure
                $tables  = isset($data['tables']) && is_array($data['tables']) ? $data['tables'] : [];
                $options = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];

                // Whitelisted tables
                $allowed_tables = [
                    $wpdb->prefix . 'tax_service_report',
                    $wpdb->prefix . 'tax_service'
                ];

                // Import tables safely
                foreach ($tables as $table => $rows) {
                    if (!in_array($table, $allowed_tables, true)) {
                        continue; // skip non-whitelisted tables
                    }
                    if (!is_array($rows)) {
                        continue;
                    }

                    // Truncate table before import (safe since table is whitelisted)
                    $wpdb->query("TRUNCATE TABLE `{$table}`");

                    foreach ($rows as $row) {
                        if (!is_array($row)) { continue; }
                        // Sanitize keys to existing columns
                        $columns = array_keys($row);
                        $placeholders = [];
                        $values = [];
                        foreach ($columns as $col) {
                            $placeholders[] = '%s';
                            $values[] = isset($row[$col]) ? wp_json_encode($row[$col]) : null; // store as string safely
                        }
                        // Build and run insert
                        $cols_backticks = '`' . implode('`,`', array_map('sanitize_key', $columns)) . '`';
                        $sql = "INSERT INTO `{$table}` ({$cols_backticks}) VALUES (" . implode(',', array_fill(0, count($placeholders), '%s')) . ")";
                        $wpdb->query($wpdb->prepare($sql, ...$values));
                    }
                }

                // Import options (whitelist)
                $allowed_options = [
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

                foreach ($options as $key => $value) {
                    if (in_array($key, $allowed_options, true)) {
                        update_option($key, $value);
                    }
                }

                wp_send_json_success(['message' => __('Ֆայլը հաջողությամբ ներբեռնված է և տվյալները ներմուծված են', 'tax-service')]);
            }
        }
    }

    new ImportTaxServiceController();
}
