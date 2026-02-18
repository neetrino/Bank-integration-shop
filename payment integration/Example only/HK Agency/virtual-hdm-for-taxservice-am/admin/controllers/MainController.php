<?php

function plugin_page()
{
    global $wpdb;
    if (class_exists('WC_Payment_Gateway')) {
        class MainController extends WC_Payment_Gateway
        {
            private $taxServiceVerificationId;
            private $ownerSiteUrl = 'https://plugins.hkdigital.am/api/';
            public $plugin_url;
            public $page;
            private $tax_service_dirname;
            private $taxApiUrl;

            public function __construct()
            {
                global $tax_service_plugin_url;
                global $tax_service_dirname;
                $this->taxApiUrl = 'https://ecrm.taxservice.am/taxsystem-rs-vcr/api/v1.0';
                $this->plugin_url = $tax_service_plugin_url;
                $this->page = sanitize_text_field($_GET['page']);
                $this->tax_service_dirname = $tax_service_dirname;
                $list = $this->getPaymentGatewayList();
                $this->taxServiceVerificationId = get_option('hkd_tax_service_verification_id');
                $this->showTemplate($list);
            }

            public function getPaymentGatewayList()
            {
                $wc_gateways = new WC_Payment_Gateways();
                $payment_gateways = $wc_gateways->get_available_payment_gateways();
                $availableGateways = [];
                foreach ($payment_gateways as $gateway_id => $gateway) {
                    $availableGateways[$gateway_id] = $gateway->method_title;
                }
                return $availableGateways;
            }

            public function showTemplate($list)
            {
                $atgCodes = ATGCodeService::getATGCodes();
                $units = ATGCodeService::getUnits();
                $template = '';
                switch ($this->page) {
                    case 'tax-service-report':
                        $template = $this->tax_service_dirname . '/admin/views/report-table.php';
                        break;
                    case 'tax-service-request-log':
                        $template = $this->tax_service_dirname . '/admin/views/request-log-table.php';
                        break;
                    case 'tax-service-errors':
                        $template = $this->tax_service_dirname . '/admin/views/report_error.php';
                        break;
                    case 'tax-service-settings':
                        $this->enqueueScripts($atgCodes);
                        add_filter('admin_footer_text', array($this, 'remove_footer_admin'));
                        if (isset($_POST['saveSettings'])) {
                            $this->saveTaxServiceSettings($_POST);
                        }
                        $template = $this->tax_service_dirname . '/admin/views/settings.php';
                        break;
                }
                $settingsCompleted = get_option('hkd_tax_settings_completed');
                $taxServiceRegisterNumber = get_option('hkd_tax_service_register_number');
                $taxServiceFilePassphrase = get_option('hkd_tax_service_passphrase');
                $taxServiceTin = get_option('hkd_tax_service_tin');
                $taxServiceUploadFilePath = get_option('hkd_tax_service_upload_file_crt');
                $taxServiceUploadFileKey = get_option('hkd_tax_service_upload_file_key');
                $taxServiceTaxType = get_option('hkd_tax_service_tax_type');
                $taxServiceCheckBothType = get_option('hkd_tax_service_check_both_type');
                $taxServiceVatPercent = get_option('hkd_tax_service_vat_percent');
                $taxServiceTreasurer = get_option('hkd_tax_service_treasurer');
                $taxServiceAtgCode = get_option('hkd_tax_service_atg_code');
                $taxServiceUnitsValue = get_option('hkd_tax_service_units_value');
                $taxServiceApiActivated = get_option('hkd_tax_service_api_activated');
                $taxServiceApiUrl = get_option('hkd_tax_service_api_url');
                $taxServiceEnabledServices = get_option('hkd_tax_service_enabled');

                $taxServiceEnabledServicesType = get_option('hkd_tax_service_enabled_type');
                $taxServiceVerificationCodeSameSKU = get_option('hkd_tax_service_verification_code_same_sku');
                $taxServiceShippingAtgCode = get_option('hkd_tax_service_shipping_atg_code');
                $taxServiceShippingDescription = get_option('hkd_tax_service_shipping_description');
                $taxServiceShippingGoodCode = get_option('hkd_tax_service_shipping_good_code');
                $taxServiceShippingUnitValue = get_option('hkd_tax_service_shipping_unit_value');
                $taxServiceAutomaticallyPrintStatus = get_option('hkd_tax_service_automatically_print_status');
                $taxServiceShippingActivated = get_option('hkd_tax_service_shipping_activated');
                $taxServiceSendRefundToUser = get_option('hkd_tax_service_send_refund_to_user');
                $taxServiceSendRefundToAdmin = get_option('hkd_tax_service_send_refund_to_admin');
                $taxServiceSendToAdmin = get_option('hkd_tax_service_send_to_admin');
                $taxServiceSeqNumber = get_option('taxServiceSeqNumber');

                $taxServicePluginVerified = $this->validateFields();

                $data = [
                    'pluginUrl' => $this->plugin_url,
                    'list' => $list,
                    'settingsCompleted' => $settingsCompleted,
                    'taxServiceRegisterNumber' => $taxServiceRegisterNumber,
                    'taxServiceTin' => $taxServiceTin,
                    'taxServiceUploadFilePath' => $taxServiceUploadFilePath,
                    'taxServiceFilePassphrase' => $taxServiceFilePassphrase,
                    'taxServiceUploadFileKey' => $taxServiceUploadFileKey,
                    'taxServiceSendRefundToUser' => $taxServiceSendRefundToUser,
                    'taxServiceSendRefundToAdmin' => $taxServiceSendRefundToAdmin,
                    'taxServiceSendToAdmin' => $taxServiceSendToAdmin,
                    'taxServiceTaxType' => $taxServiceTaxType,
                    'taxServiceCheckBothType' => $taxServiceCheckBothType,
                    'taxServiceVatPercent' => $taxServiceVatPercent,
                    'taxServiceTreasurer' => $taxServiceTreasurer,
                    'taxServiceAtgCode' => $taxServiceAtgCode,
                    'taxServiceUnitsValue' => $taxServiceUnitsValue,
                    'taxServiceEnabledServices' => $taxServiceEnabledServices,
                    'taxServiceEnabledServicesType' => $taxServiceEnabledServicesType,
                    'taxServiceApiActivated' => $taxServiceApiActivated,
                    'taxServiceVerificationCodeSameSKU' => $taxServiceVerificationCodeSameSKU,
                    'taxServiceApiUrl' => $taxServiceApiUrl,
                    'verifiedPlugin' => $taxServicePluginVerified,
                    'taxServiceShippingAtgCode' => $taxServiceShippingAtgCode,
                    'taxServiceShippingDescription' => $taxServiceShippingDescription,
                    'taxServiceShippingGoodCode' => $taxServiceShippingGoodCode,
                    'taxServiceShippingUnitValue' => $taxServiceShippingUnitValue,
                    'taxServiceShippingActivated' => $taxServiceShippingActivated,
                    'taxServiceAutomaticallyPrintStatus' => $taxServiceAutomaticallyPrintStatus,
                    'taxServiceSeqNumber' => $taxServiceSeqNumber,
                    'atgCodes' => $atgCodes,
                    'units' => $units
                ];

                extract($data);
                if (file_exists($template)) {
                    include $template;
                }
            }

            private function hkd_recursive_sanitize_text_field($array)
            {
                foreach ($array as $key => &$value) {
                    if (is_array($value)) {
                        $value = $this->hkd_recursive_sanitize_text_field($value);
                    } else {
                        $value = sanitize_text_field($value);
                    }
                }
                return $array;
            }

            public function saveTaxServiceSettings($request)
            {
                $request = $this->hkd_recursive_sanitize_text_field($request);
                $taxServiceRegisterNumber = isset($request['hkd_tax_service_register_number']) ? $request['hkd_tax_service_register_number'] : '';
                $taxServiceFilePassphrase = isset($request['hkd_tax_service_passphrase']) ? $request['hkd_tax_service_passphrase'] : '';
                $taxServiceTin = isset($request['hkd_tax_service_tin']) ? $request['hkd_tax_service_tin'] : '';
                $taxServiceTaxType = isset($request['tax_type']) ? $request['tax_type'] : 'vat';
                $taxServiceCheckBothType = (isset($request['have_both_vat']) && $taxServiceTaxType === 'vat') ? $request['have_both_vat'] : 'no';
                $taxServiceVerificationCodeSameSKU = isset($request['verification-code-same']) ? $request['verification-code-same'] : 'no';
                $taxServiceVatPercent = isset($request['vat_percent']) ? $request['vat_percent'] : 16.67;
                $taxServiceTreasurer = isset($request['treasurer']) ? $request['treasurer'] : 1;
                $taxServiceAtgCode = isset($request['atg_code']) ? $request['atg_code'] : '';
                $taxServiceUnitsValue = isset($request['units_value']) ? $request['units_value'] : 'Հատ';
                $taxServiceEnabledServices = isset($request['hkd_tax_service_enabled']) ? $request['hkd_tax_service_enabled'] : 0;
                $taxServiceEnabledServicesType = isset($request['hkd_tax_service_type']) ? $request['hkd_tax_service_type'] : [];
                $taxServiceShippingAtgCode = isset($request['hkd_tax_service_shipping_atg_code']) ? $request['hkd_tax_service_shipping_atg_code'] : '56.21';
                $taxServiceAutomaticallyPrintStatus = isset($request['automatically_print_status']) ? $request['automatically_print_status'] : 'processing';
                $taxServiceShippingDescription = isset($request['hkd_tax_service_shipping_description']) ? $request['hkd_tax_service_shipping_description'] : 'Առաքում';;
                $taxServiceShippingGoodCode = isset($request['hkd_tax_service_shipping_good_code']) ? $request['hkd_tax_service_shipping_good_code'] : '';
                $taxServiceShippingUnitValue = isset($request['hkd_tax_service_shipping_unit_value']) ? $request['hkd_tax_service_shipping_unit_value'] : 'Հատ';
                $taxServiceShippingActivated = isset($request['hkd_tax_service_shipping_activated']) ? 1 : 0;
                $taxServiceSendRefundToUser = isset($request['send-refund-to-user']) ? $request['send-refund-to-user'] : 'no';
                $taxServiceSendRefundToAdmin = isset($request['send-refund-to-admin']) ? $request['send-refund-to-admin'] : 'no';
                $taxServiceSendToAdmin = isset($request['send-to-admin']) ? $request['send-to-admin'] : 'no';
                $taxServiceSeqNumber = isset($request['seq_number']) ? $request['seq_number'] : 1;
                $newFile = false;
                $directory_path = '/tax-service';
                $upload = wp_upload_dir();
                $upload_dir = $upload['basedir'] . $directory_path;
                $savePath = $upload['basedir'] . $directory_path;
                if (isset($_FILES['attach']) && !empty($_FILES["attach"]["name"])) {
                    $files = $this->hkd_recursive_sanitize_text_field($_FILES['attach']);
                    $permissions = 0777;
                    $oldmask = umask(0);
                    if (!is_dir($upload_dir)) mkdir($upload_dir, $permissions);
                    umask($oldmask);
                    chmod($upload_dir, $permissions);
                    $taxServiceUploadFilePath = $upload_dir . '/' . $files['name'];
                    move_uploaded_file($files['tmp_name'], $taxServiceUploadFilePath);
                    $savePathCrt = $savePath . '/' . $files['name'];
                    update_option('hkd_tax_service_upload_file_crt', $savePathCrt);
                    $newFile = true;
                } else {
                    $savePathCrt = get_option('hkd_tax_service_upload_file_crt');
                }

                if (isset($_FILES['attachKey']) && !empty($_FILES["attachKey"]["name"])) {
                    $files = $this->hkd_recursive_sanitize_text_field($_FILES['attachKey']);
                    $permissions = 0777;
                    $oldmask = umask(0);
                    if (!is_dir($upload_dir)) mkdir($upload_dir, $permissions);
                    umask($oldmask);
                    chmod($upload_dir, $permissions);
                    $taxServiceUploadFilePath = $upload_dir . '/' . $files['name'];
                    move_uploaded_file($files['tmp_name'], $taxServiceUploadFilePath);
                    $savePathKey = $savePath . '/' . $files['name'];
                    update_option('hkd_tax_service_upload_file_key', $savePathKey);
                    $newFile = true;
                } else {
                    $savePathKey = get_option('hkd_tax_service_upload_file_key');
                }

                update_option('hkd_tax_settings_completed', 1);
                update_option('hkd_tax_service_register_number', $taxServiceRegisterNumber);
                update_option('hkd_tax_service_passphrase', $taxServiceFilePassphrase);
                update_option('taxServiceSeqNumber', $taxServiceSeqNumber);
                update_option('hkd_tax_service_automatically_print_status', $taxServiceAutomaticallyPrintStatus);
                update_option('hkd_tax_service_tin', $taxServiceTin);
                update_option('hkd_tax_service_tax_type', $taxServiceTaxType);
                update_option('hkd_tax_service_check_both_type', $taxServiceCheckBothType);
                update_option('hkd_tax_service_verification_code_same_sku', $taxServiceVerificationCodeSameSKU);
                update_option('hkd_tax_service_vat_percent', $taxServiceVatPercent);
                update_option('hkd_tax_service_treasurer', $taxServiceTreasurer);
                update_option('hkd_tax_service_atg_code', $taxServiceAtgCode);
                update_option('hkd_tax_service_units_value', $taxServiceUnitsValue);
                update_option('hkd_tax_service_enabled', $taxServiceEnabledServices);
                update_option('hkd_tax_service_enabled_type', $taxServiceEnabledServicesType);
                update_option('hkd_tax_service_shipping_atg_code', $taxServiceShippingAtgCode);
                update_option('hkd_tax_service_shipping_description', $taxServiceShippingDescription);
                update_option('hkd_tax_service_shipping_good_code', $taxServiceShippingGoodCode);
                update_option('hkd_tax_service_shipping_unit_value', $taxServiceShippingUnitValue);
                update_option('hkd_tax_service_shipping_activated', $taxServiceShippingActivated);
                update_option('hkd_tax_service_send_refund_to_user', $taxServiceSendRefundToUser);
                update_option('hkd_tax_service_send_refund_to_admin', $taxServiceSendRefundToAdmin);
                update_option('hkd_tax_service_send_to_admin', $taxServiceSendToAdmin);

                $taxServiceApiActivated = get_option('hkd_tax_service_api_activated');
                $taxServiceApiDepartmentsConfigured = get_option('hkd_tax_service_api_departments_activated');
                if ((!$taxServiceApiActivated && $savePathCrt && $savePathKey) || $newFile) {
                    $body = array(
                        'crn' => $taxServiceRegisterNumber,
                        'tpin' => $taxServiceTin,
                    );
                    $requestData = $this->sendRequestTaxService($savePathCrt, $savePathKey,$taxServiceFilePassphrase,'/activate', $body);
                    if (!$requestData['status']) {
                        ErrorService::hkdTaxServiceInsertTaxErrorReport(0, 'Tax service activate request Error', $requestData['response'], 'no');
                        RequestLogService::hkdTaxServiceRequestReport(0, $this->taxApiUrl . '/activate', $body, ['error' => $requestData['response']], 'No Payment');
                        update_option('hkd_tax_service_api_activated', 0);
                    } else {
                        $response = json_decode($requestData['response'], true);
                        RequestLogService::hkdTaxServiceRequestReport(0, $this->taxApiUrl . '/activate', $body, $response, 'No Payment');
                        if ($response['code'] == 0) {
                            $this->configureDepartments($taxServiceRegisterNumber, $savePathCrt, $savePathKey,$taxServiceFilePassphrase);
                            update_option('hkd_tax_service_api_activated', 1);
                        } else {
                            ErrorService::hkdTaxServiceInsertTaxErrorReport(0, 'Tax service activate request response Error', $response['error'], 'no');
                            update_option('hkd_tax_service_api_activated', 0);
                        }
                    }
                }else{
                    if(!$taxServiceApiDepartmentsConfigured){
                        $this->configureDepartments($taxServiceRegisterNumber, $savePathCrt, $savePathKey,$taxServiceFilePassphrase);
                    }
                }
            }


            public function configureDepartments($taxServiceRegisterNumber, $savePathCrt, $savePathKey,$taxServiceFilePassphrase)
            {
                $departments = [
                    "crn" => $taxServiceRegisterNumber,
                    "seq" => $this->taxServiceGetAndUpdateSeq(),
                    "departments" => [
                        [
                            "dep" => 1,
                            "taxRegime" => "1"
                        ],
                        [
                            "dep" => 2,
                            "taxRegime" => "2"
                        ],
                        [
                            "dep" => 3,
                            "taxRegime" => "3"
                        ],
                        [
                            "dep" => 7,
                            "taxRegime" => "7"
                        ]
                    ]
                ];
                $requestData = $this->sendRequestTaxService($savePathCrt, $savePathKey,$taxServiceFilePassphrase,'/configureDepartments', $departments);
                if (!$requestData['status']) {
                    update_option('hkd_tax_service_api_departments_activated', 0);
                    ErrorService::hkdTaxServiceInsertTaxErrorReport(0, 'Tax service department request Error', $requestData['response'], "no");
                    RequestLogService::hkdTaxServiceRequestReport(0, $this->taxApiUrl . '/configureDepartments', $departments, ['error' => $requestData['response']], 'No Payment');
                    return false;
                }else{
                    $bodyDepartments = json_decode($requestData['response'], true);
                    RequestLogService::hkdTaxServiceRequestReport(0, $this->taxApiUrl . '/configureDepartments', $departments, $bodyDepartments, 'No Payment');
                    if ($bodyDepartments['code'] == 0) {
                        update_option('hkd_tax_service_api_departments_activated', 1);
                    }else{
                        update_option('hkd_tax_service_api_departments_activated', 0);
                        ErrorService::hkdTaxServiceInsertTaxErrorReport(0, 'Tax service department request Error Response ', json_encode($bodyDepartments), "no");
                        return false;
                    }
                }

            }

            public function remove_footer_admin()
            {
                echo '<span id="footer-thankyou">Developed by <b><a href="https://hkdigital.am/" target="_blank">HK DIGITAL AGENCY LLC</a></b></span>';
            }

            public function taxServiceGetAndUpdateSeq()
            {
                $seq = get_option('taxServiceSeqNumber', 1);
                update_option('taxServiceSeqNumber', intval($seq) + 1);
                return intval($seq) + 1;
            }

            public function enqueueScripts($atgCodes)
            {
                wp_enqueue_script("jquery");
                wp_enqueue_script('hkd-tax-service-admin-jquery-validate', $this->plugin_url . "admin/assets/js/jquery.validate.min.js");
                wp_enqueue_script('hkd-tax-service-admin-datepicker', $this->plugin_url . "admin/assets/js/bootstrap-datepicker.min.js");
                wp_enqueue_script('hkd-tax-service-admin-bootstrap', $this->plugin_url . "admin/assets/js/bootstrap.min.js");
                wp_enqueue_script('hkd-tax-service-admin-flexible', $this->plugin_url . "admin/assets/js/conditionize.flexible.jquery.min.js");
                wp_enqueue_script('hkd-tax-service-admin-main', $this->plugin_url . "admin/assets/js/main.js", array(), microtime());
                wp_enqueue_script('hkd-tax-service-admin-switch', $this->plugin_url . "admin/assets/js/switch.js", array(), microtime());
                wp_enqueue_script('hkd-tax-service-admin-settings', $this->plugin_url . "admin/assets/js/settings.js", array(), microtime());
                wp_localize_script('hkd-tax-service-admin-main', 'my_ajax_object', array(
                                    'ajax_url' => admin_url('admin-ajax.php'),
                                    'nonce'    => wp_create_nonce('taxservice_admin')
                                ));
                wp_enqueue_style('hkd-tax-service-admin-bootstrap-css', $this->plugin_url . "admin/assets/css/bootstrap.min.css");
                wp_enqueue_style('hkd-tax-service-admin-datepicker-css', $this->plugin_url . "admin/assets/css/bootstrap-datepicker.css");
                wp_enqueue_style('hkd-tax-service-admin-animate-css', $this->plugin_url . "admin/assets/css/animate.min.css");
                wp_enqueue_style('hkd-tax-service-admin-fontawesome-css', $this->plugin_url . "admin/assets/css/fontawesome-all.css");
                wp_enqueue_style('hkd-tax-service-admin-fonts-css', $this->plugin_url . "admin/assets/css/fonts.css");
                wp_enqueue_style('hkd-tax-service-admin-style-css', $this->plugin_url . "admin/assets/css/style.css");
                wp_enqueue_style('hkd-tax-service-admin-switch-css', $this->plugin_url . "admin/assets/css/colors/switch.css");
                wp_enqueue_script('hkd-tax-service-product-settings', $this->plugin_url . "admin/assets/js/productSetting.js", array(), microtime());
                wp_localize_script('hkd-tax-service-product-settings', 'atgCodes_object', array('atgCodes' => $atgCodes, 'atgUndefinedMessage' => __('Մեր հավելման ցանկում նման ԱՏԳ կոդ չի հայտնաբերվել', 'tax-service')));
            }

            public function validateFields()
            {
                if ($this->taxServiceVerificationId == '') {
                    update_option('hkd_tax_service_activate', false);
                    return ['message' => __('Խնդրում ենք անցնել նույնականացում։ Հարցերի դեպքում զանգահարել 033 779-779 հեռախոսահամարով։', 'wc-hkdigital-acba-gateway'), 'success' => false];
                }
                $response = wp_remote_post($this->ownerSiteUrl .
                    'bank/tax-service/checkActivation', [
                    'headers' =>
                        array('Accept' => 'application/json'),
                    'sslverify' => false,
                    'body' => [
                        'domain' => sanitize_text_field($_SERVER['SERVER_NAME']),
                        'checkoutId' => $this->taxServiceVerificationId,
                        'lang' => 'hy'
                    ]]);
                if (!is_wp_error($response)) {
                    return json_decode($response['body'], true);
                } else {
                    $this->update_option('enabled', 'no');
                    return ['message' => __('Հարցման խնդիր։', 'wc-hkdigital-acba-gateway'), 'success' => false];
                }
            }

            public function sendRequestTaxService($crtPath, $keyPath, $passphrase, $path, $body)
            {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->taxApiUrl . $path);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json"
                ));
                curl_setopt($ch, CURLOPT_SSLCERT, $crtPath);
                curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
                curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $passphrase);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    return [
                        'status' => false,
                        'response' => curl_error($ch)
                    ];
                }
                curl_close($ch);
                return [
                    'status' => true,
                    'response' => $response
                ];
            }
        }

        new MainController();
    }
}