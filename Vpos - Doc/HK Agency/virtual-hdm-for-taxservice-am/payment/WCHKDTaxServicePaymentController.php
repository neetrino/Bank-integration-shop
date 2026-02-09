<?php


if (class_exists('WC_Payment_Gateway') && !class_exists('WCHKDTaxServicePaymentController')) {

    class WCHKDTaxServicePaymentController extends WC_Payment_Gateway
    {
        private $taxApiUrl;
        private $taxRegisterNumber;
        private $taxServiceEnabled;
        private $taxServiceShippingAtgCode;
        private $taxServiceShippingDescription;
        private $taxServiceType;
        private $taxServiceUploadFileCrtName;
        private $taxServiceUploadFileKeyName;
        private $taxServiceFilePassphrase;
        private $taxServiceGlobalAtgCode;
        private $taxServiceGlobalUnitValue;
        private $taxServiceShippingGoodCode;
        private $taxServiceShippingUnitValue;
        private $taxServiceShippingActivated;
        private $taxServiceVerificationCodeSameSKU;
        private $taxServiceTaxType;
        private $tax_service_dirname;
        private $taxServiceAutomaticallyPrintStatus;
        // Declared to avoid dynamic property deprecations on PHP 8.2+
        private $taxServiceUploadFilePath;
        private $taxServiceActivated;

        public function __construct()
        {
            global $tax_service_dirname;

            $this->tax_service_dirname = $tax_service_dirname;
            $this->taxApiUrl = 'https://ecrm.taxservice.am/taxsystem-rs-vcr/api/v1.0';
            $this->taxServiceUploadFilePath = get_option('hkd_tax_service_upload_file_path');
            $this->taxServiceUploadFileCrtName = get_option('hkd_tax_service_upload_file_crt');
            $this->taxServiceUploadFileKeyName = get_option('hkd_tax_service_upload_file_key');
            $this->taxServiceFilePassphrase = get_option('hkd_tax_service_passphrase');
            $this->taxServiceGlobalAtgCode = get_option('hkd_tax_service_atg_code');
            $this->taxServiceGlobalUnitValue = get_option('hkd_tax_service_units_value');
            $this->taxRegisterNumber = get_option('hkd_tax_service_register_number');
            $this->taxServiceActivated = get_option('hkd_tax_service_api_activated');
            $this->taxServiceShippingAtgCode = get_option('hkd_tax_service_shipping_atg_code');
            $this->taxServiceTaxType = get_option('hkd_tax_service_tax_type');
            $this->taxServiceShippingDescription = get_option('hkd_tax_service_shipping_description');
            $this->taxServiceShippingGoodCode = get_option('hkd_tax_service_shipping_good_code');
            $this->taxServiceShippingUnitValue = get_option('hkd_tax_service_shipping_unit_value');
            $this->taxServiceShippingActivated = get_option('hkd_tax_service_shipping_activated');
            $this->taxServiceVerificationCodeSameSKU = get_option('hkd_tax_service_verification_code_same_sku');
            $this->taxServiceEnabled = get_option('hkd_tax_service_enabled');
            $this->taxServiceType = get_option('hkd_tax_service_enabled_type');
            $this->taxServiceAutomaticallyPrintStatus = get_option('hkd_tax_service_automatically_print_status');

            add_action('woocommerce_order_status_changed', array($this, 'statusChangeHook'), 3, 3);
            add_action('woocommerce_order_edit_status', array($this, 'statusChangeHookSubscription'), 3, 2);
            add_action('wp_ajax_print_hdm_manually', array($this, 'checkOrderActions'));
            add_filter('manage_edit-shop_order_columns', array($this, 'addTaxServiceColumnInOrdersPage'));
            add_action('manage_shop_order_posts_custom_column', array($this, 'addTaxServiceOrderContent'), 10, 2);
            add_filter('woocommerce_shop_order_list_table_columns', array($this, 'addTaxServiceColumnInOrdersPage'));
            add_action('woocommerce_shop_order_list_table_custom_column', array($this, 'addTaxServiceOrderContentHook2'), 10, 2);

            if (is_admin()) {
                add_action('wp_ajax_getPrintBody', array($this, 'getPrintBody'));
            }
        }

        public function getPrintBody()
        {
            // Capability + nonce checks
            if ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) {
                wp_send_json_error(['message' => __('Անբավարար իրավասություն', 'tax-service')], 403);
            }
            check_ajax_referer('taxservice_admin');

            // Sanitize input
            $request = isset($_POST) ? $this->hkd_recursive_sanitize_text_field(wp_unslash($_POST)) : [];
            $type = isset($request['type']) ? sanitize_text_field($request['type']) : '';
            $orderId = isset($request['orderId']) ? absint($request['orderId']) : 0;
            if (empty($type) || empty($orderId)) {
                wp_send_json_error(['message' => __('Սխալ_PARAMS', 'tax-service')], 400);
            }

            // Optional: verify order exists
            $order = wc_get_order($orderId);
            if (!$order) {
                wp_send_json_error(['message' => __('Պատվերը չի գտնվել', 'tax-service')], 404);
            }

            // Render template safely
            ob_start();
            if ($type === 'print') {
                include($this->tax_service_dirname . '/checkout/email.php');
            } else {
                include($this->tax_service_dirname . '/checkout/refund.php');
            }
            $output = ob_get_clean();

            wp_send_json_success(['html' => $output]);
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

        public function addTaxServiceColumnInOrdersPage($columns)
        {
            $columns['tax-service'] = __('Էլեկտրոնային ՀԴՄ', 'tax-service');
            $columns['tax-service-status'] = __('Էլեկտրոնային ՀԴՄ Կարգավիճակ', 'tax-service');
            return $columns;
        }

        public function addTaxServiceOrderContentHook2($column, $order)
        {
            $this->addTaxServiceOrderContent($column, $order->get_id());
        }

        public function addTaxServiceOrderContent($column, $post_id)
        {
            if ('tax-service-status' === $column) {
                $printData = $this->checkByOrderId($post_id);
                if (!$printData) {
                    echo '<mark class="order-status status-trash"><span>Not Print</span></mark>';
                } else {
                    switch ($printData->status) {
                        case 'print':
                            echo '<mark class="order-status status-processing"><span>' . esc_html($printData->status) . '</span></mark>';
                            break;
                        case 'copy':
                            echo '<mark class="order-status status-completed"><span>' . esc_html($printData->status) . '</span></mark>';
                            break;
                        case 'refund':
                            echo '<mark class="order-status status-on-hold"><span>' . esc_html($printData->status) . '</span></mark>';
                            break;

                    }
                }
            }

            if ('tax-service' === $column) {
                $order = wc_get_order($post_id);

                if ($order->has_status('processing') || $order->has_status('completed')) {
                    $thisGatewayId = $this->getPaymentGatewayByOrder($order)->id;
                    $taxEnabled = false;
                    if (!empty($this->taxServiceEnabled)) {
                        foreach ($this->taxServiceEnabled as $key => $item) {
                            if ($thisGatewayId == $key && $item) {
                                $taxEnabled = true;
                            }
                        }
                    }
                    if ($taxEnabled) {
                        if (isset($this->taxServiceType[$thisGatewayId])) {
                            if ($this->taxServiceType[$thisGatewayId] == 'manually') {
                                $printData = $this->checkByOrderId($post_id);
                                if (!$printData) {
                                    $url = admin_url('admin-ajax.php?action=print_hdm_manually&order_id=' . $post_id);
                                    echo '<p><a class="button wc-action-button wc-action-button" href="' . esc_url($url) . '"> Տպել ՀԴՄ կտրոն </a></p>';
                                }
                            } else {
                                $printData = $this->checkByOrderId($post_id);
                                if (!$printData) {
                                    $url = admin_url('admin-ajax.php?action=print_hdm_manually&order_id=' . $post_id);
                                    echo '<p><a class="button wc-action-button wc-action-button" href="' . esc_url($url) . '"> Տպել ՀԴՄ կտրոն </a></p>';
                                }
                            }
                        }
                    }
                }
            }
        }

        public function checkOrderActions()
        {
            if (isset($_GET['action']) && isset($_GET['order_id']) && esc_attr($_GET['action']) === 'print_hdm_manually') {
                $order = wc_get_order(esc_attr($_GET['order_id']));
                $thisGatewayId = $this->getPaymentGatewayByOrder($order)->id;
                if (isset($this->taxServiceType[$thisGatewayId]) && ($this->taxServiceType[$thisGatewayId] == 'manually' || $this->taxServiceType[$thisGatewayId] == 'automatically'))
                    $print = $this->printTaxPayment($order, $this->taxServiceType[$thisGatewayId]);
                if (!$print) {
                    wp_redirect('/wp-admin/admin.php?page=tax-service-errors', 301);
                    exit();
                }
            }
            wp_redirect("/wp-admin/edit.php?post_type=shop_order", 301);
            exit();
        }

        public function statusChangeHookSubscription($order_id, $new_status)
        {
            if ($new_status == 'completed' || $new_status == 'processing') {
                $order = wc_get_order($order_id);
                $thisGatewayId = $this->getPaymentGatewayByOrder($order)->id;
                $taxEnabled = false;
                if (!empty($this->taxServiceEnabled)) {
                    foreach ($this->taxServiceEnabled as $key => $item) {
                        if ($thisGatewayId == $key) {
                            $taxEnabled = true;
                        }
                    }
                }
                if ($taxEnabled) {
                    if (isset($this->taxServiceType[$thisGatewayId])) {
                        if ($this->taxServiceType[$thisGatewayId] == 'automatically' && $new_status == $this->taxServiceAutomaticallyPrintStatus) {
                            return $this->printTaxPayment($order, $this->taxServiceType[$thisGatewayId]);
                        }
                    } else {
                        if ($new_status == $this->taxServiceAutomaticallyPrintStatus) {
                            return $this->printTaxPayment($order);
                        }
                    }
                }
            }
        }

        public function statusChangeHook($order_id, $old_status, $new_status)
        {
            return $this->statusChangeHookSubscription($order_id, $new_status);
        }


        private function getPaymentGatewayByOrder($order)
        {
            return wc_get_payment_gateway_by_order($order);
        }

        public function taxServiceGetAndUpdateSeq()
        {
            $seq = get_option('taxServiceSeqNumber', 1);
            update_option('taxServiceSeqNumber', intval($seq) + 1);
            return intval($seq) + 1;
        }

        public function printTaxPayment($order, $type = 'manually')
        {
            $items = $order->get_items();
            $orderId = $order->get_id();
            $paymentGateway = $order->get_payment_method();

            if (!$this->taxRegisterNumber) {
                ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'CRN is not defined', 'CRN is not defined', $paymentGateway);
                return false;
            }

            $printData = $this->checkByOrderId($orderId);
            if ($printData) {
                ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Order Already Printed', 'Order Already Printed', $paymentGateway);
                return false;
            }

            switch ($this->taxServiceTaxType) {
                case 'without_vat':
                    $dep = 2;
                    break;
                case 'around_tax':
                    $dep = 3;
                    break;
                case 'micro':
                    $dep = 7;
                    break;
                default:
                    $dep = 1;
                    break;
            }

            $body = array(
                'mode' => 2,
                'crn' => $this->taxRegisterNumber,
                'seq' => $this->taxServiceGetAndUpdateSeq(),
                'cashierId' => 1,
                'items' => array()
            );

            $totalAmount = $order->get_total();
            if ($order->get_payment_method() == 'cod') {
                $body['cashAmount'] = (float)$totalAmount;
            } else {
                $body['cardAmount'] = (float)$totalAmount;
            }

            if ($order->get_shipping_total() > 0 && $this->taxServiceShippingActivated) {
                $body['items'][] = array(
                    'dep' => $dep,
                    'adgCode' => $this->taxServiceShippingAtgCode,
                    'goodCode' => $this->taxServiceShippingGoodCode,
                    'goodName' => $this->taxServiceShippingDescription,
                    'quantity' => 1,
                    'unit' => $this->taxServiceShippingUnitValue,
                    'price' => $order->get_shipping_total()
                );
            }

            foreach ($items as $item) {
                $product = wc_get_product($item->get_product_id());
                $productAtgCode = get_post_meta($item->get_product_id(), 'atgCode', true);
                $productUnitsValue = get_post_meta($item->get_product_id(), 'unitValue', true);
                $productValidationCode = get_post_meta($item->get_product_id(), 'validationCode', true);
                $validationCode = ($productValidationCode) ? $productValidationCode : (($this->taxServiceVerificationCodeSameSKU != 'no') ? $product->get_sku() : 'no');
                $itemAtgCode = ($productAtgCode) ? $productAtgCode : $this->taxServiceGlobalAtgCode;
                $itemUnit = ($productUnitsValue && $productAtgCode) ? $productUnitsValue : $this->taxServiceGlobalUnitValue;
                $item_data = array(
                    'dep' => $dep,
                    'adgCode' => $itemAtgCode,
                    'goodCode' => $validationCode,
                    'goodName' => mb_substr($item->get_name(), 0, 30),
                    'quantity' => number_format((float)$item->get_quantity(), 1, '.', ''),
                    'unit' => $itemUnit,
                    'price' => number_format((float)$item->get_total() / $item->get_quantity(), 1, '.', '')
                );

                if ($product->is_on_sale()) {
                    if ($product->is_type('simple')) {
                        $sale_price = $product->get_sale_price();
                        $regular_price = $product->get_regular_price();
                    } elseif ($product->is_type('variable')) {
                        $sale_price = $product->get_variation_sale_price('min', true);
                        $regular_price = $product->get_variation_regular_price('max', true);
                    }
                    $item_data['discount'] = (float)$regular_price - (float)$sale_price;
                    $item_data['discountType'] = 2;
                    $item_data['price'] += (float)$item_data['discount'];
                }
                $body['items'][] = $item_data;
            }
            $requestData = $this->sendRequestTaxService('/print', $body);

            if (!$requestData['status']) {
                ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Tax service print request Error', $requestData['response'], $paymentGateway);
                RequestLogService::hkdTaxServiceRequestReport($orderId, $this->taxApiUrl . '/print', $body, ['error' => $requestData['response']], $paymentGateway);
                return false;
            } else {
                $response = json_decode($requestData['response'], true);
                RequestLogService::hkdTaxServiceRequestReport($orderId, $this->taxApiUrl . '/print', $body, $response, $paymentGateway);
                if (empty($response['error'])) {
                    if ($response['code'] == 0) {
                        $qr = $response['result']['qr'];
                        $args = [
                            'order_id' => $orderId,
                            'crn' => $response['result']['crn'],
                            'sn' => $response['result']['sn'],
                            'tin' => $response['result']['tin'],
                            'taxpayer' => $response['result']['taxpayer'],
                            'address' => $response['result']['address'],
                            'time' => $response['result']['time'],
                            'status' => 'print',
                            'fiscal' => $response['result']['fiscal'],
                            'total' => $response['result']['total'],
                            'qr' => $qr,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        $insert = $this->hkdTaxServiceInsertTaxSuccessReport($args);
                        $insert = true;
                        if ($insert) {
                            $args['receiptId'] = $response['result']['receiptId'];
                            update_post_meta($orderId, 'TaxServiceReport', json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            extract(['orderId' => $orderId]);
                            ob_start();
                            include($this->tax_service_dirname . '/checkout/email.php');
                            $message = ob_get_contents();
                            $email_subject = "Ձեր $orderId պատվերի էլեկտրոնային ՀԴՄ կտրոնը";
                            $admin_email = get_option('admin_email');
                            $user_email = $order->get_billing_email();
                            ob_end_clean();

                            $headers = array('From: ' . get_bloginfo('name') . ' <' . $admin_email . '>');
                            add_filter('wp_mail_content_type', function () {
                                return 'text/html';
                            });

                            add_filter('wp_mail_charset', function () {
                                return 'UTF-8';
                            });
                            wp_mail($user_email, $email_subject, $message, $headers);

                            $taxServiceSendToAdmin = get_option('hkd_tax_service_send_to_admin');
                            if ($taxServiceSendToAdmin === 'yes') {
                                extract(['orderId' => $orderId]);
                                ob_start();
                                include($this->tax_service_dirname . '/checkout/email.php');
                                $message = ob_get_contents();
                                $email_subject = "$orderId պատվերի էլեկտրոնային ՀԴՄ կտրոնի օրինակը";
                                $admin_email = get_option('admin_email');
                                ob_end_clean();
                                $headers = array('From: ' . get_bloginfo('name') . ' <' . $admin_email . '>');
                                add_filter('wp_mail_content_type', function () {
                                    return 'text/html';
                                });
                                add_filter('wp_mail_charset', function () {
                                    return 'UTF-8';
                                });
                                wp_mail($admin_email, $email_subject, $message, $headers);
                            }
                            return true;
                        }
                    } else {
                        ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Tax service print request response Error', $response['errorMessage'], $paymentGateway);
                        return false;
                    }
                } else {
                    ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Tax service print request response Error', $response['error'], $paymentGateway);
                    return false;
                }
            }
        }

        private function getOrderIdFromReportId($reportId)
        {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "tax_service  WHERE id = %d", $reportId));
        }

        private function checkByOrderId($orderId)
        {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "tax_service  WHERE order_id = %d", $orderId));
        }

        private function checkByFailedOrderId($orderId)
        {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "tax_service_report  WHERE order_id = %d", $orderId));
        }

        private function updateStatusPaymentReport($reportId, $status)
        {
            global $wpdb;
            $data = array('status' => $status);
            $where = array('id' => $reportId);
            return $wpdb->update($wpdb->prefix . "tax_service", $data, $where);
        }

        public function refundPayment($reportId)
        {
            $printData = $this->getOrderIdFromReportId($reportId);
            $orderId = $printData->order_id;
            $order = wc_get_order($orderId);
            $paymentGateway = $order->get_payment_method();
            $items = $order->get_items();
            $taxServiceQRCode = $printData->qr;
            $qr_data = explode(', ', $taxServiceQRCode);
            $receipt_id = '';
            if (!empty($qr_data)) {
                foreach ($qr_data as $value) {
                    $value = trim($value);
                    if (strpos($value, 'Receipt_ID:') === 0) {
                        $receipt_id = trim(str_replace('Receipt_ID:', '', $value));
                        break;
                    }
                }
            }

            if (!empty($receipt_id)) {

                $body = array(
                    'crn' => $this->taxRegisterNumber,
                    'seq' => $this->taxServiceGetAndUpdateSeq(),
                    'receiptId' => $receipt_id,
                    'returnItemList' => array()
                );
                $receiptProductId = 0;

                $totalAmount = $order->get_total();
                if (!$this->taxServiceShippingActivated) {
                    $totalAmount = $totalAmount - $order->get_shipping_total();
                }
                if ($order->get_payment_method() == 'cod') {
                    $body['cashAmountForReturn'] = $totalAmount;
                } else {
                    $body['cardAmountForReturn'] = $totalAmount;
                }
                if ($order->get_shipping_total() > 0 && $this->taxServiceShippingActivated) {
                    $body['returnItemList'][] = array(
                        'receiptProductId' => $receiptProductId,
                        'quantity' => 1
                    );
                    $receiptProductId++;
                }

                foreach ($items as $item) {
                    $body['returnItemList'][] = array(
                        'receiptProductId' => $receiptProductId,
                        'quantity' => $item->get_quantity()
                    );
                    $receiptProductId++;
                }

                $requestData = $this->sendRequestTaxService('/printReturnReceipt', $body);

                if ($requestData['status']) {
                    $response = json_decode($requestData['response'], true);
                    RequestLogService::hkdTaxServiceRequestReport($orderId, $this->taxApiUrl . '/printReturnReceipt', $body, $response, $paymentGateway);
                    if (empty($response['error'])) {
                        if ($response['code'] == 0) {

                            $args = [
                                'order_id' => $orderId,
                                'crn' => $response['result']['crn'],
                                'sn' => $response['result']['sn'],
                                'tin' => $response['result']['tin'],
                                'taxpayer' => $response['result']['taxpayer'],
                                'address' => $response['result']['address'],
                                'time' => $response['result']['time'],
                                'receiptId' => $response['result']['receiptId'],
                                'status' => 'print',
                                'fiscal' => $response['result']['fiscal'],
                                'total' => $response['result']['total'],
                                'qr' => $response['result']['qr'],
                                'created_at' => date('Y-m-d H:i:s'),
                            ];
                            update_post_meta($orderId, 'TaxServiceReturnReport', json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            $this->updateStatusPaymentReport($reportId, 'refund');
                            $taxServiceSendRefundToUser = get_option('hkd_tax_service_send_refund_to_user');
                            $taxServiceSendRefundToAdmin = get_option('hkd_tax_service_send_refund_to_admin');
                            if ($taxServiceSendRefundToUser === 'yes') {
                                extract(['orderId' => $orderId]);
                                ob_start();
                                include($this->tax_service_dirname . '/checkout/refund.php');
                                $message = ob_get_contents();
                                $email_subject = "Ձեր $orderId պատվերի վերադարձի էլեկտրոնային ՀԴՄ կտրոնը";
                                $admin_email = get_option('admin_email');
                                $user_email = $order->get_billing_email();
                                ob_end_clean();
                                $headers = array('From: ' . get_bloginfo('name') . ' <' . $admin_email . '>');
                                add_filter('wp_mail_content_type', function () {
                                    return 'text/html';
                                });

                                add_filter('wp_mail_charset', function () {
                                    return 'UTF-8';
                                });
                                wp_mail($user_email, $email_subject, $message, $headers);
                            }


                            if ($taxServiceSendRefundToAdmin === 'yes') {
                                extract(['orderId' => $orderId]);
                                ob_start();
                                include($this->tax_service_dirname . '/checkout/refund.php');
                                $message = ob_get_contents();
                                $email_subject = "$orderId պատվերի վերադարձի էլեկտրոնային ՀԴՄ կտրոնի օրինակը";
                                $admin_email = get_option('admin_email');
                                ob_end_clean();
                                $headers = array('From: ' . get_bloginfo('name') . ' <' . $admin_email . '>');
                                add_filter('wp_mail_content_type', function () {
                                    return 'text/html';
                                });

                                add_filter('wp_mail_charset', function () {
                                    return 'UTF-8';
                                });
                                wp_mail($admin_email, $email_subject, $message, $headers);
                            }

                            return true;
                        } else {
                            ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Tax service refund request response Error', $response['errorMessage'], $paymentGateway);
                            return false;
                        }
                    } else {
                        ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Tax service refund request response Error', $response['error'], $paymentGateway);
                        return false;
                    }
                } else {
                    RequestLogService::hkdTaxServiceRequestReport($orderId, $this->taxApiUrl . '/printReturnReceipt', $body, ['error' => $requestData['response']], $paymentGateway);
                }
            }
        }

        public function copyPrintPayment($reportId)
        {
            $printData = $this->getOrderIdFromReportId($reportId);

            $orderId = $printData->order_id;
            $order = wc_get_order($orderId);
            $taxServiceQRCode = $printData->qr;
            $qr_data = explode(', ', $taxServiceQRCode);
            $paymentGateway = $order->get_payment_method();
            $receipt_id = '';
            if (!empty($qr_data)) {
                foreach ($qr_data as $value) {
                    $value = trim($value);
                    if (strpos($value, 'Receipt_ID:') === 0) {
                        $receipt_id = trim(str_replace('Receipt_ID:', '', $value));
                        break;
                    }
                }
            }

            if (!empty($receipt_id)) {
                $body = array(
                    'crn' => $this->taxRegisterNumber,
                    'seq' => $this->taxServiceGetAndUpdateSeq(),
                    'receiptId' => $receipt_id,
                );

                $requestData = $this->sendRequestTaxService('/printCopy', $body);
                if ($requestData['status']) {
                    $response = json_decode($requestData['response'], true);
                    RequestLogService::hkdTaxServiceRequestReport($orderId, $this->taxApiUrl . '/printCopy', $body, $response, $paymentGateway);
                    if (empty($response['error'])) {
                        if ($response['code'] == 0) {
                            $this->updateStatusPaymentReport($reportId, 'copy');

                            extract(['orderId' => $orderId]);
                            ob_start();
                            include($this->tax_service_dirname . '/checkout/email.php');
                            $message = ob_get_contents();
                            $email_subject = "Ձեր $orderId պատվերի էլեկտրոնային ՀԴՄ կտրոնը";
                            $admin_email = get_option('admin_email');
                            $user_email = $order->get_billing_email();
                            ob_end_clean();

                            $headers = array('From: ' . get_bloginfo('name') . ' <' . $admin_email . '>');
                            add_filter('wp_mail_content_type', function () {
                                return 'text/html';
                            });

                            add_filter('wp_mail_charset', function () {
                                return 'UTF-8';
                            });
                            wp_mail($user_email, $email_subject, $message, $headers);

                            return true;
                        } else {
                            ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Tax service copy request response Error', $response['errorMessage'], $paymentGateway);
                            return false;
                        }
                    } else {
                        ErrorService::hkdTaxServiceInsertTaxErrorReport($orderId, 'Tax service copy request response Error', $response['error'], $paymentGateway);
                        return false;
                    }
                } else {
                    RequestLogService::hkdTaxServiceRequestReport($orderId, $this->taxApiUrl . '/printCopy', $body, ['error' => $requestData['response']], $paymentGateway);
                    return false;
                }
            }
            return false;
        }

        public function hkdTaxServiceInsertTaxSuccessReport($args = array())
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'tax_service';
            if ($wpdb->insert($table_name, $args)) {
                return $wpdb->insert_id;
            }
            return false;
        }

        public function sendRequestTaxService($path, $body)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->taxApiUrl . $path);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json"
            ));
            curl_setopt($ch, CURLOPT_SSLCERT, $this->taxServiceUploadFileCrtName);
            curl_setopt($ch, CURLOPT_SSLKEY, $this->taxServiceUploadFileKeyName);
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->taxServiceFilePassphrase);
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
}
