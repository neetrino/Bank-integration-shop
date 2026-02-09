<?php

function exportSettingsAndData()
{
    if (!class_exists('ExportTaxServiceController')) {
        class ExportTaxServiceController
        {

            public function __construct()
            {
                $this->exportFileAndSave();
            }

            public function exportFileAndSave()
            {
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => __('Անբավարար իրավասություն', 'tax-service')], 403);
                }
                check_ajax_referer('taxservice_admin');

                $exportFilePath = ExportTaxService::exportSettingsAndData();
                $certificateFilePath = get_option('hkd_tax_service_upload_file_path');
                wp_send_json_success([
                    'exportFilePath' => $exportFilePath,
                    'certificateFilePath' => $certificateFilePath,
                    'message' => __('Ֆայլը հաջողությամբ ստեղծվեց', 'tax-service')
                ]);
            }
        }
    }

    new ExportTaxServiceController();
}
