<?php
add_action('plugins_loaded', 'hkdigital_init_inecobank_gateway_class');

if (!function_exists('hkdigital_init_inecobank_gateway_class')) {

    function hkdigital_init_inecobank_gateway_class()
    {
        global $pluginBaseNameIneco;
        unload_textdomain('wc-inecobank-payment-gateway');
        load_textdomain('wc-inecobank-payment-gateway', $pluginBaseNameIneco . '/languages/wc-inecobank-payment-gateway-hy.mo');
        load_textdomain('wc-inecobank-payment-gateway', $pluginBaseNameIneco . '/languages/wc-inecobank-payment-gateway-en_US.mo');
        load_textdomain('wc-inecobank-payment-gateway', $pluginBaseNameIneco . '/languages/wc-inecobank-payment-gateway-ru_RU.mo');
        load_plugin_textdomain('wc-inecobank-payment-gateway', false, $pluginBaseNameIneco . '/languages');

        if (class_exists('WC_Payment_Gateway')) {
            class WC_HKD_Inecobank_Arca_Gateway extends WC_Payment_Gateway
            {
                private $api_url;
                private $ownerSiteUrl;
                private $pluginDirUrl;
                private $currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978'];
                private $currency_code = '051';

                /**
                 * WC_HKD_Inecobank_Arca_Gateway constructor.
                 */
                public function __construct()
                {
                    global $woocommerce;
                    global $bankErrorCodesByDiffLanguageIneco;
                    global $apiUrlIneco;
                    global $pluginDirUrlIneco;

                    $this->ownerSiteUrl = $apiUrlIneco;
                    $this->pluginDirUrl = $pluginDirUrlIneco;
                    /* Add support Refund orders */
                    $this->supports = [
                        'products',
                        'refunds',
                        'subscriptions',
                        'subscription_cancellation',
                        'subscription_suspension',
                        'subscription_reactivation',
                        'subscription_amount_changes',
                        'subscription_date_changes',
                        'subscription_payment_method_change',
                        'subscription_payment_method_change_customer',
                        'subscription_payment_method_change_admin',
                        'multiple_subscriptions',
                        'gateway_scheduled_payments'
                    ];

                    $this->id = 'hkd_inecobank';
                    $this->icon = $this->pluginDirUrl . 'assets/images/logo_inecobank.png';
                    $this->has_fields = true;
                    $this->method_title = 'Payment Gateway for Inecobank';
                    $this->method_description = 'Pay with  Inecobank payment system. Please note that the payment will be made in Armenian Dram.';

                    if (is_admin()) {
                        if (isset($_POST['hkd_inecobank_checkout_id']) && $_POST['hkd_inecobank_checkout_id'] != '') {
                            update_option('hkd_inecobank_checkout_id', sanitize_text_field($_POST['hkd_inecobank_checkout_id']));
                            $this->update_option('title', __('Pay via credit card', 'wc-inecobank-payment-gateway'));
                            $this->update_option('description', __('Purchase by credit card. Please, note that purchase is going to be made by Armenian drams. ', 'wc-inecobank-payment-gateway'));
                            $this->update_option('save_card_button_text', __('Add a credit card', 'wc-inecobank-payment-gateway'));
                            $this->update_option('save_card_header', __('Purchase safely by using your saved credit card', 'wc-inecobank-payment-gateway'));
                            $this->update_option('save_card_use_new_card', __('Use a new credit card', 'wc-inecobank-payment-gateway'));
                        }
                    }

                    $this->init_form_fields();
                    $this->init_settings();

                    $this->title = $this->get_option('title');
                    $this->description = $this->get_option('description');
                    $language = get_locale();
                    $this->language_payment_inecobank = ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US') ? $language : 'hy';
                    $this->enabled = $this->get_option('enabled');
                    $this->hkd_arca_checkout_id = get_option('hkd_inecobank_checkout_id');
                    $this->language = $this->get_option('language');
                    $this->secondTypePayment = 'yes' === $this->get_option('secondTypePayment');
                    $this->empty_card = 'yes' === $this->get_option('empty_card');
                    $this->two_url_sent = 'yes' === $this->get_option('two_url_sent');
                    $this->testmode = 'yes' === $this->get_option('testmode');
                    $this->user_name = $this->testmode ? $this->get_option('test_user_name') : $this->get_option('live_user_name');
                    $this->password = $this->testmode ? $this->get_option('test_password') : $this->get_option('live_password');
                    $this->binding_user_name = $this->get_option('binding_user_name');
                    $this->binding_password = $this->get_option('binding_password');
                    $this->debug = 'yes' === $this->get_option('debug');
                    $this->save_card = 'yes' === $this->get_option('save_card');
                    $this->save_card_button_text = !empty($this->get_option('save_card_button_text')) ? $this->get_option('save_card_button_text') : __('Add a credit card', 'wc-inecobank-payment-gateway');
                    $this->save_card_header = !empty($this->get_option('save_card_header')) ? $this->get_option('save_card_header') : __('Purchase safely by using your saved credit card', 'wc-inecobank-payment-gateway');
                    $this->save_card_use_new_card = !empty($this->get_option('save_card_use_new_card')) ? $this->get_option('save_card_use_new_card') : __('Use a new credit card', 'wc-inecobank-payment-gateway');

                    $this->successOrderStatus = $this->get_option('successOrderStatus');

                    $this->multi_currency = 'yes' === $this->get_option('multi_currency');
                    $this->api_url = 'https://pg.inecoecom.am/payment/rest';
                    if ($this->debug) {
                        if (version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) $this->log = $woocommerce->logger(); else $this->log = new WC_Logger();
                    }
                    if ($this->multi_currency) {
                        $this->currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978'];
                        $wooCurrency = get_woocommerce_currency();
                        $this->currency_code = $this->currencies[$wooCurrency];
                    }

                    // process the Change Payment "transaction"
                    add_action('woocommerce_scheduled_subscription_payment', array($this, 'process_subscription_payment'), 10, 3);

                    /**
                     * Success callback url for inecoBank payment api
                     */
                    add_action('woocommerce_api_delete_binding_inecobank', array($this, 'delete_binding_inecobank'));

                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                    /**
                     * Success callback url for inecobank payment api
                     */
                    add_action('woocommerce_api_inecobank_successful', array($this, 'webhook_inecobank_successful'));

                    /**
                     * Failed callback url for inecobank payment api
                     */
                    add_action('woocommerce_api_inecobank_failed', array($this, 'webhook_inecobank_failed'));
                    /**
                     * styles and fonts for inecobank payment plugin
                     */
                    add_action('admin_print_styles', array($this, 'enqueue_stylesheets'));

                    /*
                  * Add Credit Card Menu in My Account
                  */
                    if (is_user_logged_in() && $this->save_card && $this->binding_user_name != '' && $this->binding_password != '') {
                        add_filter('query_vars', array($this, 'queryVarsCards'), 0);
                        add_filter('woocommerce_account_menu_items', array($this, 'addCardLinkMenu'));
                        add_action('woocommerce_account_cards_endpoint', array($this, 'CardsPageContent'));
                    }


                    if ($this->secondTypePayment) {
                        add_filter('woocommerce_admin_order_actions', array($this, 'add_custom_order_status_actions_button'), 100, 2);
                        add_action('admin_head', array($this, 'add_custom_order_status_actions_button_css'));
                    }

                    add_action('woocommerce_order_status_changed', array($this, 'statusChangeHook'), 10, 3);
                    add_action('woocommerce_order_edit_status', array($this, 'statusChangeHookSubscription'), 10, 2);

                    if (is_admin()) {
                        $this->checkActivation();
                    }

                    $this->bankErrorCodesByDiffLanguage = $bankErrorCodesByDiffLanguageIneco;
                    // WP cron
                    add_action('cronCheckOrderIneco', array($this, 'cronCheckOrderIneco'));
                }

                public function cronCheckOrder()
                {
                    global $wpdb;
                    $orders = $wpdb->get_results("
                        SELECT p.*
                        FROM {$wpdb->prefix}postmeta AS pm
                        LEFT JOIN {$wpdb->prefix}posts AS p
                        ON pm.post_id = p.ID
                        WHERE p.post_type = 'shop_order'
                        AND ( p.post_status = 'wc-on-hold' OR p.post_status = 'wc-pending')
                        AND pm.meta_key = '_payment_method'
                        AND pm.meta_value = 'hkd_inecobank'
                        ORDER BY pm.meta_value ASC, pm.post_id DESC
                    ");
                    foreach ($orders as $order) {
                        $order = wc_get_order($order->get_id());
                        $paymentID = get_post_meta($order->get_id(), 'PaymentID', true);
                        if ($paymentID) {
                            $url = $this->api_url . '/getOrderStatus.do';
                            $requestParams = [
                                'orderId' => $paymentID,
                                'language' => $this->language,
                                'password' => $this->password,
                                'userName' => $this->user_name
                            ];
                            $response = $this->postRequestPayment($url, $requestParams);
                            if (!is_wp_error($response)) {
                                $body = json_decode($response['body']);
                                if ($this->secondTypePayment) {
                                    if ($body->OrderStatus == 1) {
                                        $order->update_status($this->successOrderStatus);
                                        if ($this->debug) $this->log->add($this->id, 'Order status was changed to ' . $this->successOrderStatus . ' via cron job. Status code is 1. #' . $order->get_id());
                                    }
                                }
                                if ($body->OrderStatus == 2) {
                                    $order->update_status($this->successOrderStatus);
                                    if ($this->debug) $this->log->add($this->id, 'Order status was changed to ' . $this->successOrderStatus . ' via cron job #' . $order->get_id());
                                }
                                if ($body->OrderStatus == 3) {
                                    $order->update_status('cancelled');
                                    if ($this->debug) $this->log->add($this->id, 'Order status was changed to Cancelled via cron job. Status Code is 3 #' . $order->get_id());
                                }
                                if ($body->OrderStatus == 4) {
                                    $order->update_status('refund');
                                    if ($this->debug) $this->log->add($this->id, 'Order status was changed to Refund via cron job. Status Code is 4 #' . $order->get_id());
                                }

                                if ($body->OrderStatus == 6) {
                                    $order->update_status('cancelled');
                                    if ($this->debug) $this->log->add($this->id, 'Order status was changed to Failed #' . $order->get_id());
                                }

                            }
                        }
                    }
                }


                public function checkActivation()
                {
                    $today = date('Y-m-d');
                    if (get_option('hkd_check_activation_ineco') !== $today) {
                        $payload = ['domain' => sanitize_text_field($_SERVER['SERVER_NAME']), 'enabled' => $this->enabled];
                        wp_remote_post($this->ownerSiteUrl . 'bank/ineco/checkStatusPluginActivation', array(
                            'sslverify' => false,
                            'method' => 'POST',
                            'headers' => array('Accept' => 'application/json'),
                            'body' => $payload
                        ));
                        update_option('hkd_check_activation_ineco', $today);
                    }
                }

                public function statusChangeHookSubscription($order_id, $new_status)
                {
                    $order = wc_get_order($order_id);
                    if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_inecobank' && $this->secondTypePayment) {
                        if ($order->get_parent_id() > 0) {
                            if ($new_status == 'active') {
                                return $this->confirmPayment($order_id, $new_status);
                            } else if ($new_status == 'cancelled') {
                                return $this->cancelPayment($order_id);
                            }
                        }
                    }
                }

                public function statusChangeHook($order_id, $old_status, $new_status)
                {
                    $order = wc_get_order($order_id);
                    if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_inecobank') {
                        if ($new_status == 'completed' && $this->secondTypePayment) {
                            return $this->confirmPayment($order_id, $new_status);
                        } else if ($new_status == 'cancelled') {
                            return $this->cancelPayment($order_id, $old_status);
                        }
                    }
                }

                private function getPaymentGatewayByOrder($order)
                {
                    return wc_get_payment_gateway_by_order($order);
                }


                public function add_custom_order_status_actions_button_css()
                {
                    echo '<style>.column-wc_actions a.cancel::after { content: "\2716" !important; color: red; }</style>';
                }

                public function add_custom_order_status_actions_button($actions, $order)
                {
                    if (isset($this->getPaymentGatewayByOrder($order)->id) && $this->getPaymentGatewayByOrder($order)->id == 'hkd_inecobank') {
                        if ($order->has_status(array('processing'))) {
                            $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->get_id();
                            $actions['cancelled'] = array(
                                'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=cancelled&order_id=' . $order_id), 'woocommerce-mark-order-status'),
                                'name' => __('Cancel Order', 'woocommerce'),
                                'action' => "cancel custom",
                            );
                        }
                    }
                    return $actions;
                }


                public function confirmPayment($order_id, $new_status)
                {
                    /* $reason */
                    $order = wc_get_order($order_id);
                    if (!$order->has_status('processing')) {
                        $PaymentID = get_post_meta($order_id, 'PaymentID', true);
                        $isBindingOrder = get_post_meta($order_id, 'isBindingOrder', true);
                        $amount = floatval($order->get_total()) * 100;
                        $requestParams = [
                            'amount' => $amount,
                            'currency' => $this->currency_code,
                            'orderId' => $PaymentID
                        ];

                        if ($isBindingOrder) {
                            $requestParams['password'] = $this->binding_password;
                            $requestParams['userName'] = $this->binding_user_name;
                        } else {
                            $requestParams['password'] = $this->password;
                            $requestParams['userName'] = $this->user_name;
                        }
                        $requestParams['language'] = $this->language;
                        $response = $this->postRequestPayment($this->api_url . '/deposit.do', $requestParams);
                        if (!is_wp_error($response)) {
                            $body = json_decode($response['body']);
                            if ($body->errorCode == 0) {
                                if ($new_status == 'completed') {
                                    $order->update_status('completed');
                                } else {
                                    $order->update_status('active');
                                }
                                return true;
                            } else {
                                if ($this->debug) $this->log->add($this->id, 'Order confirm paymend #' . $order_id . '  failed.');
                                if ($new_status == 'completed') {
                                    $order->update_status('processing', $body->errorMessage);
                                } else {
                                    $order->update_status('on-hold', $body->errorMessage);
                                }
                                die($body->errorMessage);
                            }
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order confirm paymend #' . $order_id . '  failed.');
                            if ($new_status == 'completed') {
                                $order->update_status('processing');
                            } else {
                                $order->update_status('on-hold');
                            }
                            die('Order confirm paymend #' . $order_id . '  failed.');
                        }
                    }
                }

                public function cancelPayment($order_id, $old_status = '')
                {
                    if ($old_status === '') $old_status = $this->successOrderStatus;
                    /* $reason */
                    $order = wc_get_order($order_id);
                    if ($order->is_paid()) {
                        $date = $order->get_date_paid();
                        $orderDate = $date->date('Y-m-d H:i:s');
                        $nowDate = date('Y-m-d H:i:s');
                        $hourDiff = round((strtotime($nowDate) - strtotime($orderDate)) / 3600, 1);

                        if ($hourDiff < 72) {
                            if (($old_status === $this->successOrderStatus && !$this->secondTypePayment) || ($this->secondTypePayment)) {
                                $PaymentID = get_post_meta($order_id, 'PaymentID', true);
                                $isBindingOrder = get_post_meta($order_id, 'isBindingOrder', true);
                                $requestParams = [
                                    'orderId' => $PaymentID
                                ];
                                if ($isBindingOrder) {
                                    $requestParams['password'] = $this->binding_password;
                                    $requestParams['userName'] = $this->binding_user_name;
                                } else {
                                    $requestParams['password'] = $this->password;
                                    $requestParams['userName'] = $this->user_name;
                                }
                                $response = $this->postRequestPayment($this->api_url . '/reverse.do', $requestParams);
                                if (!is_wp_error($response)) {
                                    $body = json_decode($response['body']);
                                    if ($body->errorCode == 0) {
                                        $order->update_status('cancelled');
                                        return true;
                                    } else {
                                        if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                                        $order->update_status('processing');
                                        die($body->errorMessage);
                                    }
                                } else {
                                    if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                                    $order->update_status('processing');
                                    die('Order Cancel paymend #' . $order_id . '  failed.');
                                }
                            }
                        }
                    }
                }

                /* Refund order process */
                public function process_refund($order_id, $amount = null, $reason = '')
                {
                    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
                    /* $reason */
                    $order = wc_get_order($order_id);
                    $isBindingOrder = get_post_meta($order_id, 'isBindingOrder', true);
                    $requestParams = [
                        'amount' =>  floatval($amount) * 100,
                        'currency' => $this->currency_code,
                        'orderId' => $PaymentID,
                        'language' => $this->language
                    ];

                    if ($isBindingOrder) {
                        $requestParams['password'] = $this->binding_password;
                        $requestParams['userName'] = $this->binding_user_name;
                    } else {
                        $requestParams['password'] = $this->password;
                        $requestParams['userName'] = $this->user_name;
                    }

                    $response = $this->postRequestPayment($this->api_url . '/refund.do', $requestParams);
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            $order->update_status('refund');
                            return true;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order refund paymend #' . $order_id . ' canceled or failed.');
                            return false;
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order refund paymend #' . $order_id . ' canceled or failed.');
                        return false;
                    }

                }

                public function queryVarsCards($vars)
                {
                    $vars[] = 'cards';
                    return $vars;
                }

                public function CardsPageContent()
                {
                    $plugin_url = $this->pluginDirUrl;
                    wp_enqueue_style('hkd-front-style', $plugin_url . "assets/css/cards.css");
                    wp_enqueue_script('hkd-front-js', $plugin_url . "assets/js/cards.js");
                    $html = '<div id="hkdigital_binding_info_inecobank">';
                    $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_inecobank');
                    if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                        $html .= '<h4 class="card_payment_title card_page">' . __('Your card list', 'wc-inecobank-payment-gateway') . '</h4>
                              <h2 class="card_payment_second card_page">' . __('You can Delete Cards', 'wc-inecobank-payment-gateway') . '</h2>
                                <ul class="card_payment_list">';
                        foreach ($bindingInfo as $key => $bindingItem) {
                            $html .= '<li class="card_item">
                                        <span class="card_subTitile">
                                        ' . __($bindingItem['cardAuthInfo']['cardholderName'] . ' |  &#8226; &#8226; &#8226; &#8226; ' . $bindingItem['cardAuthInfo']['panEnd'] . ' (expires ' . $bindingItem['cardAuthInfo']['expiration'] . ')', 'wc-inecobank-payment-gateway') . '
                                         </span>
                                         <img src="' . $this->pluginDirUrl . 'assets/images/card_types/' . $bindingItem['cardAuthInfo']['type'] . '.png" class="card_logo big_img" alt="card"/>
                                         <svg  class="svg-trash-inecobank" data-id="' . $bindingItem['bindingId'] . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                            <path fill="#ed2353"
                                                  d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z"></path>
                                        </svg>
                                    </li>';
                        }
                        $html .= '</ul>
                            </div>';
                    } else {
                        $html .= '<div class="check-box noselect">
                                    <span>
                                      ' . __('No Saved Cards', 'wc-inecobank-payment-gateway') . '
                                    </span>
                                 </div>';
                    }
                    echo $html;
                }

                public function addCardLinkMenu($items)
                {
                    $items['cards'] = 'Credit Cards';
                    return $items;
                }

                /*
                 * Delete Saved Card AJAX
                 */
                public function delete_binding_inecobank()
                {
                    try {
                        $bindingIdForDelete = $_REQUEST['bindingId'];
                        $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_inecobank');
                        if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                            foreach ($bindingInfo as $key => $item) {
                                if ($item['bindingId'] == $bindingIdForDelete) {
                                    unset($bindingInfo[$key]);
                                }
                            }
                            delete_user_meta(get_current_user_id(), 'bindingInfo_inecobank');
                            if (count($bindingInfo) > 0)
                                add_user_meta(get_current_user_id(), 'bindingInfo_inecobank', array_values($bindingInfo));
                            $payload = [
                                'userName' => $this->user_name,
                                'password' => $this->password,
                                'bindingId' => $bindingIdForDelete
                            ];
                            $this->postRequestPayment($this->api_url . 'unBindCard.do', $payload);
                            $response = ['status' => true];
                        } else {
                            $response = ['status' => false];
                        }
                    } catch (Exception $e) {
                        $response = ['status' => false];
                    }
                    echo json_encode($response);
                    exit;
                }

                public function payment_fields()
                {
                    $plugin_url = $this->pluginDirUrl;
                    wp_enqueue_style('hkd-front-style-inecobank', $plugin_url . "assets/css/cards.css");
                    wp_enqueue_script('hkd-front-js-inecobank', $plugin_url . "assets/js/cards.js");
                    $description = $this->get_description();
                    if ($description) {
                        echo wpautop(wptexturize($description));  // @codingStandardsIgnoreLine.
                    }
                    if (is_user_logged_in() && $this->save_card && $this->binding_user_name != '' && $this->binding_password != '') {
                        $html = '<div id="hkdigital_binding_info_inecobank">';
                        $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_inecobank');
                        if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                            $html .= '<h4 class="card_payment_title"> ' . $this->save_card_header . ' </h4>
                                <ul class="card_payment_list">';

                            foreach ($bindingInfo as $key => $bindingItem) {
                                $html .= '<li class="card_item">
                                        <input   id="' . $bindingItem['bindingId'] . '" name="bindingType" value="' . $bindingItem['bindingId'] . '" type="radio" class="input-radio" name="payment_card" >
                                        <label for="' . $bindingItem['bindingId'] . '">
                                        ' . __($bindingItem['cardAuthInfo']['cardholderName'] . ' |  &#8226; &#8226; &#8226; &#8226; ' . $bindingItem['cardAuthInfo']['panEnd'] . ' (expires ' . $bindingItem['cardAuthInfo']['expiration'] . ')') . '
                                         </label>';
                                if ($bindingItem['cardAuthInfo']['type'] != '') {
                                    $html .= '<img src="' . $this->pluginDirUrl . 'assets/images/card_types/' . $bindingItem['cardAuthInfo']['type'] . '.png" class="card_logo" alt="card">';
                                }
                                $html .= '<svg  class="svg-trash-inecobank" data-id="' . $bindingItem['bindingId'] . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                            <path fill="#ed2353"
                                                  d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z"></path>
                                        </svg>
                                    </li>';
                            }
                            $html .= '<li class="card_item">
                                        <input id="payment_newCard" type="radio" class="input-radio" name="bindingType" value="saveCard">
                                        <label for="payment_newCard">
                                        ' . $this->save_card_use_new_card . '
                                         </label>
                                    </li>';
                            $html .= '</ul>
                            </div>';
                        } else {
                            $html .= '<div class="check-box noselect">
                                    <input type="checkbox" id="saveCard_inecobank" name="bindingType" value="saveCard"/>
                                    <label for="saveCard_inecobank"> <span class="check"><svg class="svg-check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                        <path fill="#ffffff" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path>
                                    </svg> </span>
                                       ' . $this->save_card_button_text . '
                                    </label>
                                 </div>';
                        }
                        echo $html;
                    }
                }

                public function init_form_fields()
                {
                    $debug = __('Log HKD ARCA Gateway events, inside <code>woocommerce/logs/inecobank.txt</code>', 'wc-inecobank-payment-gateway');
                    if (!version_compare(WOOCOMMERCE_VERSION, '2.0', '<')) {
                        if (version_compare(WOOCOMMERCE_VERSION, '2.2.0', '<'))
                            $debug = str_replace('inecobank', $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)), $debug);
                        elseif (function_exists('wc_get_log_file_path')) {
                            $debug = str_replace('woocommerce/logs/inecobank.txt', '<a href="/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)) . '-log" target="_blank">' . __('here', 'wc-inecobank-payment-gateway') . '</a>', $debug);
                        }
                    }
                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __('Enable/Disable', 'wc-inecobank-payment-gateway'),
                            'label' => __('Enable payment gateway', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'description' => '',
                            'default' => 'no'
                        ),
                        'title' => array(
                            'title' => __('Title', 'wc-inecobank-payment-gateway'),
                            'type' => 'text',
                            'description' => __('User (website visitor) sees this title on order registry page as a title for purchase option.', 'wc-inecobank-payment-gateway'),
                            'default' => __('Pay via credit card', 'wc-inecobank-payment-gateway'),
                            'desc_tip' => true,
                            'placeholder' => __('Type the title', 'wc-inecobank-payment-gateway')
                        ),
                        'description' => array(
                            'title' => __('Description', 'wc-inecobank-payment-gateway'),
                            'type' => 'textarea',
                            'description' => __('User (website visitor) sees this description on order registry page in bank purchase option.', 'wc-inecobank-payment-gateway'),
                            'default' => __('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'wc-inecobank-payment-gateway'),
                            'desc_tip' => true,
                            'placeholder' => __('Type the description', 'wc-inecobank-payment-gateway')
                        ),
                        'language' => array(
                            'title' => __('Language', 'wc-inecobank-payment-gateway'),
                            'type' => 'select',
                            'options' => [
                                'hy' => 'Հայերեն',
                                'ru' => 'Русский',
                                'en' => 'English',
                            ],
                            'description' => __('Here interface language of bank purchase can be regulated', 'wc-inecobank-payment-gateway'),
                            'default' => 'hy',
                            'desc_tip' => true,
                        ),
                        'successOrderStatus' => array(
                            'title' => __('Success Order Status', 'wc-inecobank-payment-gateway'),
                            'type' => 'select',
                            'options' => [
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                            ],
                            'description' => __('Here you can select the status of confirmed payment orders.', 'wc-inecobank-payment-gateway'),
                            'default' => 'processing',
                            'desc_tip' => true,
                        ),
                        'multi_currency' => array(
                            'title' => __('Multi-Currency', 'wc-inecobank-payment-gateway'),
                            'label' => __('Enable Multi-Currency', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'description' => __('This action, if permitted by the bank, enables to purchase by multiple currencies', 'wc-inecobank-payment-gateway'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'debug' => array(
                            'title' => __('Debug Log', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'label' => __('Enable debug mode', 'wc-inecobank-payment-gateway'),
                            'default' => 'no',
                            'description' => $debug,
                        ),
                        'testmode' => array(
                            'title' => __('Test mode', 'wc-inecobank-payment-gateway'),
                            'label' => __('Enable test Mode', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'description' => __('To test the testing version login and password provided by the bank should be typed', 'wc-inecobank-payment-gateway'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'test_user_name' => array(
                            'title' => __('Test User Name', 'wc-inecobank-payment-gateway'),
                            'type' => 'text',
                            'class' => 'testModeInfoIneco hiddenValueIneco'
                        ),
                        'test_password' => array(
                            'title' => __('Test Password', 'wc-inecobank-payment-gateway'),
                            'type' => 'password',
                            'placeholder' => __('Enter password', 'wc-inecobank-payment-gateway'),
                            'class' => 'testModeInfoIneco hiddenValueIneco'
                        ),
                        'secondTypePayment' => array(
                            'title' => __('Two-stage Payment', 'wc-inecobank-payment-gateway'),
                            'label' => __('Enable payment confirmation function', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'description' => __('two-stage: when the payment amount is first blocked on the buyer’s account and then at the second stage is withdrawn from the account', 'wc-inecobank-payment-gateway'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'save_card' => array(
                            'title' => __('Save Card Admin', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'label' => __('Enable "Save Card" function', 'wc-inecobank-payment-gateway'),
                            'default' => 'no',
                            'desc_tip' => true,
                            'description' => __('Enable Save Card', 'wc-inecobank-payment-gateway'),
                        ),
                        'save_card_button_text' => array(
                            'title' => __('New binding card text', 'wc-inecobank-payment-gateway'),
                            'placeholder' => __('Type the save card checkbox text', 'wc-inecobank-payment-gateway'),
                            'type' => 'text',
                            'default' => __('Add a credit card', 'wc-inecobank-payment-gateway'),
                            'desc_tip' => true,
                            'description' => ' ',
                            'class' => 'saveCardInfoIneco hiddenValueIneco',
                        ),
                        'save_card_header' => array(
                            'title' => __('Save card description text', 'wc-inecobank-payment-gateway'),
                            'placeholder' => __('Type the save card description text', 'wc-inecobank-payment-gateway'),
                            'type' => 'text',
                            'default' => __('Purchase safely by using your saved credit card', 'wc-inecobank-payment-gateway'),
                            'desc_tip' => true,
                            'description' => ' ',
                            'class' => 'saveCardInfoIneco hiddenValueIneco',
                        ),
                        'save_card_use_new_card' => array(
                            'title' => __('Use new card text', 'wc-inecobank-payment-gateway'),
                            'placeholder' => __('Type the use new card text', 'wc-inecobank-payment-gateway'),
                            'type' => 'text',
                            'default' => __('Use a new credit card', 'wc-inecobank-payment-gateway'),
                            'desc_tip' => true,
                            'description' => ' ',
                            'class' => 'saveCardInfoIneco hiddenValueIneco'
                        ),
                        'binding_user_name' => array(
                            'title' => __('Binding User Name', 'wc-inecobank-payment-gateway'),
                            'type' => 'text',
                            'class' => 'saveCardInfoIneco hiddenValueIneco'
                        ),
                        'binding_password' => array(
                            'title' => __('Binding Password', 'wc-inecobank-payment-gateway'),
                            'type' => 'password',
                            'placeholder' => __('Enter password', 'wc-inecobank-payment-gateway'),
                            'class' => 'saveCardInfoIneco hiddenValueIneco'
                        ),
                        'live_settings' => array(
                            'title' => __('Live Settings', 'wc-inecobank-payment-gateway'),
                            'type' => 'hidden'
                        ),
                        'live_user_name' => array(
                            'title' => __('User Name', 'wc-inecobank-payment-gateway'),
                            'type' => 'text',
                            'placeholder' => __('Type the user name', 'wc-inecobank-payment-gateway')
                        ),
                        'live_password' => array(
                            'title' => __('Password', 'wc-inecobank-payment-gateway'),
                            'type' => 'password',
                            'placeholder' => __('Type the password', 'wc-inecobank-payment-gateway')
                        ),
                        'useful_functions' => array(
                            'title' => __('Useful functions', 'wc-inecobank-payment-gateway'),
                            'type' => 'hidden'
                        ),
                        'empty_card' => array(
                            'title' => __('Cart totals', 'wc-inecobank-payment-gateway'),
                            'label' => __('Activate shopping cart function', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'description' => __('This feature ensures that the contents of the shopping cart are available at the time of order registration if the site buyer decides to change the payment method.', 'wc-inecobank-payment-gateway'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'two_url_sent' => array(
                            'title' => __('URL Setting', 'wc-inecobank-payment-gateway'),
                            'label' => __('Enable individual URL functionality', 'wc-inecobank-payment-gateway'),
                            'type' => 'checkbox',
                            'description' => __('When enabled, the response received from the bank is attached to individual URLs.', 'wc-inecobank-payment-gateway'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'links' => array(
                            'title' => __('Links', 'wc-inecobank-payment-gateway'),
                            'type' => 'hidden'
                        ),
                    );
                }


                public function process_payment($order_id)
                {

                    global $woocommerce;
                    if (isset($_REQUEST['bindingType'])) $bindingType = sanitize_text_field($_REQUEST['bindingType']);

                    $order = wc_get_order($order_id);
                    $amount = floatval($order->get_total()) * 100;
                    $requestParams = [
                        'amount' => $amount,
                        'currency' => $this->currency_code,
                        'orderNumber' => $order_id,
                        'language' => $this->language

                    ];

                    if (isset($bindingType) && $bindingType != 'saveCard') {
                        $requestParams['password'] = $this->binding_password;
                        $requestParams['userName'] = $this->binding_user_name;
                    } else {
                        $requestParams['password'] = $this->password;
                        $requestParams['userName'] = $this->user_name;
                    }
                    $requestParams['description'] = 'Order N' . $order_id;
                    $requestParams['returnUrl'] = get_site_url() . '/wc-api/inecobank_successful?order=' . $order_id;
                    if ($this->two_url_sent) {
                        $requestParams['failUrl'] = get_site_url() . '/wc-api/inecobank_failed?order=' . $order_id;
                    }
                    $requestParams['jsonParams'] = '{"FORCE_3DS2":"true"}';
                    if (isset($bindingType) && $bindingType != 'saveCard') {
                        $requestParams['clientId'] = get_current_user_id();
                        $url = ($this->secondTypePayment) ? $this->api_url . '/registerPreAuth.do' : $this->api_url . '/register.do';
                        $response = $this->postRequestPayment($url, $requestParams);
                        $body = json_decode($response['body']);
                        $payload = [
                            'userName' => $this->binding_user_name,
                            'password' => $this->binding_password,
                            'mdOrder' => $body->orderId,
                            'bindingId' => sanitize_text_field($_REQUEST['bindingType'])
                        ];
                        update_post_meta($order_id, 'PaymentID', $body->orderId);
                        $response = $this->postRequestPayment($this->api_url . '/paymentOrderBinding.do', $payload);
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            $order->update_status($this->successOrderStatus);
                            update_post_meta($order_id, 'isBindingOrder', 1);
                            wc_reduce_stock_levels($order_id);
                            if (!$this->empty_card) {
                                $woocommerce->cart->empty_cart();
                            }
                            return array('result' => 'success', 'redirect' => $body->redirect);
                        } else if ($body->errorCode == 1) {
                            $order_id = $this->duplicate_order($order);
                            return $this->process_payment($order_id);
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                            $order->update_status('failed', $body->errorMessage);
                            wc_add_notice(__('Please try again.', 'wc-inecobank-payment-gateway'), 'error');
                        }
                    }
                    update_post_meta($order_id, 'isBindingOrder', 0);
                    if (($this->save_card && $this->binding_user_name != '' && $this->binding_password != '' && is_user_logged_in() && isset($bindingType) && $bindingType == 'saveCard') || (function_exists('wcs_get_subscriptions_for_order') && !empty(wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any'))))) {
                        $requestParams['clientId'] = get_current_user_id();
                    }

                    $url = ($this->secondTypePayment) ? $this->api_url . '/registerPreAuth.do' : $this->api_url . '/register.do';
                    $response = $this->postRequestPayment($url, $requestParams);
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            $order->update_status('pending');
                            wc_reduce_stock_levels($order_id);
                            if (!$this->empty_card) {
                                $woocommerce->cart->empty_cart();
                            }
                            update_post_meta($order_id, 'PaymentID', $body->orderId);
                            return array('result' => 'success', 'redirect' => $body->formUrl);
                        } else if ($body->errorCode == 1) {
                            $order_id = $this->duplicate_order($order);
                            return $this->process_payment($order_id);
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                            $order->update_status('failed', $body->errorMessage);
                            wc_add_notice(__('Please try again.', 'wc-inecobank-payment-gateway'), 'error');
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                        $order->update_status('failed');
                        wc_add_notice(__('Connection error.', 'wc-inecobank-payment-gateway'), 'error');
                        return array('result' => 'success', 'redirect' => get_permalink(get_option('woocommerce_checkout_page_id')));
                    }
                }


                /**
                 * Function to create the duplicate of the order.
                 *
                 * @param mixed $post
                 * @return int
                 */
                public function duplicate_order( $post ) {

                    $original_order_id = $post->get_id();
                    $original_order = $post;
                    $order_id = $this->create_order();
                    if ( is_wp_error( $order_id ) ){
                        $msg = 'Unable to create order: ' . $order_id->get_error_message();;
                        throw new Exception( $msg );
                    } else {
                        $order = new WC_Order($order_id);
                        $this->duplicate_order_header($original_order_id, $order_id);
                        $this->duplicate_billing_fieds($original_order_id, $order_id);
                        $this->duplicate_shipping_fieds($original_order_id, $order_id);
                        $this->duplicate_line_items($original_order, $order_id);
                        $this->duplicate_shipping_items($original_order, $order_id);
                        $this->duplicate_coupons($original_order, $order_id);
                        $this->duplicate_payment_info($original_order_id, $order_id, $order);
                        $order->calculate_taxes();
                        $order->calculate_totals();
                        $this->add_order_note($original_order_id, $order);
                        return $order_id;
                    }
                }

                private function create_order() {
                    $order = wc_create_order();
                    $order_id = $order->get_id();
                    $_order = new WC_Order( $order_id );
                    $_order->update_status('pending');
                    return $order_id;
                }

                private function duplicate_order_header($original_order_id, $order_id)
                {
                    update_post_meta($order_id, '_order_shipping', get_post_meta($original_order_id, '_order_shipping', true));
                    update_post_meta($order_id, '_order_discount', get_post_meta($original_order_id, '_order_discount', true));
                    update_post_meta($order_id, '_cart_discount', get_post_meta($original_order_id, '_cart_discount', true));
                    update_post_meta($order_id, '_order_tax', get_post_meta($original_order_id, '_order_tax', true));
                    update_post_meta($order_id, '_order_shipping_tax', get_post_meta($original_order_id, '_order_shipping_tax', true));
                    update_post_meta($order_id, '_order_total', get_post_meta($original_order_id, '_order_total', true));
                    update_post_meta($order_id, '_order_key', 'wc_' . apply_filters('woocommerce_generate_order_key', uniqid('order_')));
                    update_post_meta($order_id, '_customer_user', get_post_meta($original_order_id, '_customer_user', true));
                    update_post_meta($order_id, '_order_currency', get_post_meta($original_order_id, '_order_currency', true));
                    update_post_meta($order_id, '_prices_include_tax', get_post_meta($original_order_id, '_prices_include_tax', true));
                    update_post_meta($order_id, '_customer_ip_address', get_post_meta($original_order_id, '_customer_ip_address', true));
                    update_post_meta($order_id, '_customer_user_agent', get_post_meta($original_order_id, '_customer_user_agent', true));
                }

                private function duplicate_billing_fieds($original_order_id, $order_id)
                {
                    update_post_meta($order_id, '_billing_city', get_post_meta($original_order_id, '_billing_city', true));
                    update_post_meta($order_id, '_billing_state', get_post_meta($original_order_id, '_billing_state', true));
                    update_post_meta($order_id, '_billing_postcode', get_post_meta($original_order_id, '_billing_postcode', true));
                    update_post_meta($order_id, '_billing_email', get_post_meta($original_order_id, '_billing_email', true));
                    update_post_meta($order_id, '_billing_phone', get_post_meta($original_order_id, '_billing_phone', true));
                    update_post_meta($order_id, '_billing_address_1', get_post_meta($original_order_id, '_billing_address_1', true));
                    update_post_meta($order_id, '_billing_address_2', get_post_meta($original_order_id, '_billing_address_2', true));
                    update_post_meta($order_id, '_billing_country', get_post_meta($original_order_id, '_billing_country', true));
                    update_post_meta($order_id, '_billing_first_name', get_post_meta($original_order_id, '_billing_first_name', true));
                    update_post_meta($order_id, '_billing_last_name', get_post_meta($original_order_id, '_billing_last_name', true));
                    update_post_meta($order_id, '_billing_company', get_post_meta($original_order_id, '_billing_company', true));
                }

                private function duplicate_shipping_fieds($original_order_id, $order_id)
                {
                    update_post_meta($order_id, '_shipping_country', get_post_meta($original_order_id, '_shipping_country', true));
                    update_post_meta($order_id, '_shipping_first_name', get_post_meta($original_order_id, '_shipping_first_name', true));
                    update_post_meta($order_id, '_shipping_last_name', get_post_meta($original_order_id, '_shipping_last_name', true));
                    update_post_meta($order_id, '_shipping_company', get_post_meta($original_order_id, '_shipping_company', true));
                    update_post_meta($order_id, '_shipping_address_1', get_post_meta($original_order_id, '_shipping_address_1', true));
                    update_post_meta($order_id, '_shipping_address_2', get_post_meta($original_order_id, '_shipping_address_2', true));
                    update_post_meta($order_id, '_shipping_city', get_post_meta($original_order_id, '_shipping_city', true));
                    update_post_meta($order_id, '_shipping_state', get_post_meta($original_order_id, '_shipping_state', true));
                    update_post_meta($order_id, '_shipping_postcode', get_post_meta($original_order_id, '_shipping_postcode', true));
                }

                private function duplicate_line_items($original_order, $order_id)
                {
                    foreach ($original_order->get_items() as $originalOrderItem) {
                        $itemName = $originalOrderItem['name'];
                        $qty = $originalOrderItem['qty'];
                        $lineTotal = $originalOrderItem['line_total'];
                        $lineTax = $originalOrderItem['line_tax'];
                        $productID = $originalOrderItem['product_id'];
                        $item_id = wc_add_order_item($order_id, array(
                            'order_item_name' => $itemName,
                            'order_item_type' => 'line_item'
                        ));

                        wc_add_order_item_meta($item_id, '_qty', $qty);
                        wc_add_order_item_meta($item_id, '_tax_class', $originalOrderItem['tax_class']);
                        wc_add_order_item_meta($item_id, '_product_id', $productID);
                        wc_add_order_item_meta($item_id, '_variation_id', $originalOrderItem['variation_id']);
                        wc_add_order_item_meta($item_id, '_line_subtotal', wc_format_decimal($lineTotal));
                        wc_add_order_item_meta($item_id, '_line_total', wc_format_decimal($lineTotal));
                        wc_add_order_item_meta($item_id, '_line_tax', wc_format_decimal($lineTax));
                        wc_add_order_item_meta($item_id, '_line_subtotal_tax', wc_format_decimal($originalOrderItem['line_subtotal_tax']));
                    }
                }

                private function duplicate_shipping_items($original_order, $order_id)
                {
                    $original_order_shipping_items = $original_order->get_items('shipping');

                    foreach ($original_order_shipping_items as $original_order_shipping_item) {
                        $item_id = wc_add_order_item($order_id, array(
                            'order_item_name' => $original_order_shipping_item['name'],
                            'order_item_type' => 'shipping'
                        ));
                        if ($item_id) {
                            wc_add_order_item_meta($item_id, 'method_id', $original_order_shipping_item['method_id']);
                            wc_add_order_item_meta($item_id, 'cost', wc_format_decimal($original_order_shipping_item['cost']));
                        }
                    }
                }

                private function duplicate_coupons($original_order, $order_id)
                {
                    $original_order_coupons = $original_order->get_items('coupon');
                    foreach ($original_order_coupons as $original_order_coupon) {
                        $item_id = wc_add_order_item($order_id, array(
                            'order_item_name' => $original_order_coupon['name'],
                            'order_item_type' => 'coupon'
                        ));
                        if ($item_id) {
                            wc_add_order_item_meta($item_id, 'discount_amount', $original_order_coupon['discount_amount']);
                        }
                    }
                }

                private function duplicate_payment_info($original_order_id, $order_id, $order)
                {
                    update_post_meta($order_id, '_payment_method', get_post_meta($original_order_id, '_payment_method', true));
                    update_post_meta($order_id, '_payment_method_title', get_post_meta($original_order_id, '_payment_method_title', true));
                }

                private function add_order_note($original_order_id, $order)
                {
                    $updateNote = 'This order was duplicated from order ' . $original_order_id . '.';
                    $order->add_order_note($updateNote);
                }

                public function enqueue_stylesheets()
                {
                    $plugin_url = $this->pluginDirUrl;
                    wp_enqueue_script('hkd-inecobank-front-admin-js', $plugin_url . "assets/js/admin.js");
                    wp_localize_script('hkd-inecobank-front-admin-js', 'myScriptIneco', array(
                        'pluginsUrl' => $plugin_url,
                    ));
                    wp_enqueue_style('hkd-style-inecobank', $plugin_url . "assets/css/style.css");
                    wp_enqueue_style('hkd-style-awesome-inecobank', $plugin_url . "assets/css/font_awesome.css");
                }

                public function process_subscription_payment($order_id)
                {
                    $order = wc_get_order($order_id);
                    if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_inecobank') {
                        $bindingInfo = get_user_meta($order->get_user_id(), 'recurringChargeINECO' . (int)$order->get_parent_id());

                        $amount = floatval($order->get_total()) * 100;
                        $requestParams = [
                            'amount' => $amount,
                            'currency' => $this->currency_code,
                            'orderNumber' => rand(10000000, 99999999),
                            'language' => $this->language,
                            'password' => $this->binding_password,
                            'userName' => $this->binding_user_name,
                            'description' => 'order number' . $order_id,
                            'returnUrl' => get_site_url() . '/wc-api/inecobank_successful?order=' . $order_id,
                            'clientId' => get_current_user_id(),
                            'jsonParams' => '{"FORCE_3DS2":"true"}'
                        ];
                        if ($this->two_url_sent) {
                            $requestParams['failUrl'] = get_site_url() . '/wc-api/inecobank_failed?order=' . $order_id;
                        }
                        $url = ($this->secondTypePayment) ? $this->api_url . '/registerPreAuth.do' : $this->api_url . '/register.do';
                        $response = $this->postRequestPayment($url, $requestParams);
                        $body = json_decode($response['body']);
                        update_post_meta($order_id, 'PaymentID', $body->orderId);
                        $payload = [
                            'userName' => $this->binding_user_name,
                            'password' => $this->binding_password,
                            'mdOrder' => $body->orderId,
                            'bindingId' => $bindingInfo[0]['bindingId']
                        ];
                        $response = $this->postRequestPayment($this->api_url . '/paymentOrderBinding.do', $payload);
                        if (!is_wp_error($response)) {
                            $body = json_decode($response['body']);
                            if ($body->errorCode == 0) {
                                if ($this->secondTypePayment) {
                                    $order->update_status('on-hold');
                                } else {
                                    $order->update_status('active');
                                }
                                $parts = parse_url($body->redirect);
                                parse_str($parts['query'], $query);
                                update_post_meta($order_id, 'isBindingOrder', 1);
                                return true;
                            } else {
                                if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                                $order->update_status('pending-cancel');
                                echo "<pre>";
                                print_r($body);
                                echo "error";
                                exit;
                            }
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback.');
                            $order->update_status('pending-cancel', 'WP Error binding payment');
                            echo "error";
                            exit;
                        }
                    }
                }

                public function admin_options()
                {
                    $validate = $this->validateFields();

                    if (!$validate['success']) {
                        $message = $validate['message'];
                    }

                    if (!empty($message)) { ?>
                        <div id="message" class="<?= ($validate['success']) ? 'updated' : 'error' ?> fade">
                            <p><?php echo $message; ?></p>
                        </div>
                    <?php } ?>
                    <div class="wrap-inecobank wrap-content wrap-content-hkd"
                         id="pluginMainWrap">
                        <h4><?= __('ONLINE PAYMENT GATEWAY', 'wc-inecobank-payment-gateway') ?></h4>
                        <h3><?= __('INECO BANK', 'wc-inecobank-payment-gateway') ?></h3>
                        <?php if (!$validate['success']): ?>
                            <div class="w-100">
                                <p class="mb-10"><?php echo __('Before using the plugin, please contact the bank to receive respective regulations.', 'wc-inecobank-payment-gateway'); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($validate['success']) { ?>
                            <table class="form-table">
                                <?php
                                $this->generate_settings_html()
                                ?>
                                <tr valign="top">
                                    <th scope="row">Inecobank callback Url Success</th>
                                    <td><?= get_site_url() ?>/wc-api/inecobank_successful</td>
                                </tr>
                                <tr valign="top" class="failed_url_ineco">
                                    <th scope="row">Inecobank callback Url Failed</th>
                                    <td><?= get_site_url() ?>/wc-api/inecobank_failed</td>
                                </tr>
                            </table>
                        <?php } else { ?>
                            <div class="step-inner-content mt-40">
                                <h2 class="verification-label text-center">
                                    <?php echo __('IDENTIFICATION', 'wc-inecobank-payment-gateway'); ?>
                                </h2>
                                <p class="mt-5 font-size-14 text-center">
                                    <?php echo __('Contact us at the specified phone number or e-mail address for authentication.', 'wc-inecobank-payment-gateway'); ?>
                                </p>
                                <div class="form-inner-area">
                                    <label class="input-label-verification"
                                           for="wc-inecobank-payment-gateway_verification_id"><?php echo __('Identification password', 'wc-inecobank-payment-gateway'); ?></label>
                                    <input type="text" name="hkd_inecobank_checkout_id"
                                           id="hkd_arca_checkout_id" class="form-control "
                                           value="<?php echo esc_html($this->hkd_arca_checkout_id); ?>"
                                           minlength="2"
                                           placeholder="<?php echo __('Example Inecobankgayudcsu14', 'wc-inecobank-payment-gateway') ?>">
                                </div>
                                <div class="blue terms_div_ineco">
                                    <iframe src="<?php echo esc_html($this->pluginDirUrl) ?>terms/terms.html"
                                            height="100%" width="100%" title="Terms Iframe"></iframe>
                                </div>
                                <div class="text-center accept_terms_div_ineco mb-10">
                                    <label class="checkbox">
                                        <input type="checkbox"
                                               class="accept_terms_ineco"
                                               name="terms" id="terms_ineco">
                                        <span><?php echo __('I have read and agree to the Plugin', 'wc-inecobank-payment-gateway'); ?> <a
                                                    href="javascript:"
                                                    id="toggle-terms_div_ineco"><b>  <?php echo __('Terms & Conditions', 'wc-inecobank-payment-gateway'); ?></b></a></span>&nbsp;<abbr
                                                class="required" title="required">*</abbr>
                                        <br>
                                        <span id="terms-error_ineco" class="error"></span>
                                    </label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="wrap-inecobank wrap-content wrap-content-hkd"
                         id="paymentInfoBlock">
                        <div class="wrap-content-hkd-400px">
                            <img src="<?= $this->pluginDirUrl ?>assets/images/Inecobank.png">
                            <div class="wrap-content-hkd-info">

                                <h2><?php echo __('Payment system', 'wc-inecobank-payment-gateway'); ?></h2>

                                <div class="wrap-content-info">
                                    <div class="phone-icon icon"><i class="fa fa-phone"></i></div>
                                    <p><a href="tel:+37410510527">010 510 527</a></p>
                                    <div class="mail-icon icon"><i class="fa fa-envelope"></i></div>
                                    <p><a href="mailto:inecobank@inecobank.am">inecobank@inecobank.am</a></p>
                                    <div class="mail-icon icon"><i class="fa fa-link"></i></div>
                                    <p><a target="_blank" href="https://inecobank.am">inecobank.am</a></p>
                                </div>
                            </div>
                        </div>
                        <div class="wrap-content-hkd-400px">
                            <img width="341" height="140"
                                 src="<?= $this->pluginDirUrl ?>assets/images/hkserperator.png">
                        </div>
                        <div class=" wrap-content-hkd-400px">
                            <img src="<?= $this->pluginDirUrl ?>assets/images/logo_hkd.png">
                            <div class="wrap-content-hkd-info">
                                <div class="wrap-content-info">
                                    <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                                    </div>
                                    <p><a href="tel:+37460777999">060 777 999</a></p>
                                    <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                                    </div>
                                    <p><a href="tel:+37433779779">033 779 779</a></p>
                                    <div class="mail-icon-2 icon"><i class="fa fa-envelope"></i></div>
                                    <p><a href="mailto:support@hkdigital.am">support@hkdigital.am</a></p>
                                    <div class="mail-icon-2 icon"><i class="fa fa-link"></i></div>
                                    <p><a target="_blank" href="https://www.hkdigital.am">hkdigital.am</a></p>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php }

                /**
                 * @return array|mixed|object
                 */
                public function validateFields()
                {
                    $go = get_option('hkdigital_dump_ineco');
                    $wooCurrency = get_woocommerce_currency();

                    if (!isset($this->currencies[$wooCurrency])) {
                        $this->update_option('enabled', 'no');
                        return ['message' => 'Դուք այժմ օգտագործում եք ' . $wooCurrency . ' արժույթը, այն չի սպասարկվում բանկի կողմից։
                                          Հասանելի արժույթներն են ՝  ' . implode(', ', array_keys($this->currencies)), 'success' => false, 'err_msg' => 'currency_error'];
                    }
                    if ($this->hkd_arca_checkout_id == '') {
                        if (!empty($go)) {
                            update_option('hkdigital_dump_ineco', 'no');
                        } else {
                            add_option('hkdigital_dump_ineco', 'no');
                        };
                        $this->update_option('enabled', 'no');
                        return ['message' => __('You must fill token', 'wc-inecobank-payment-gateway'), 'success' => false];
                    }
                    $ch = curl_init($this->ownerSiteUrl .
                        'bank/ineco/checkApiConnection');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['checkIn' => true]));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                        ]
                    );
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    $res = curl_exec($ch);
                    curl_close($ch);
                    if ($res) {
                        $response = wp_remote_post($this->ownerSiteUrl .
                            'bank/ineco/checkActivation', ['headers' => array('Accept' => 'application/json'), 'sslverify' => false, 'body' => ['domain' => $_SERVER['SERVER_NAME'], 'checkoutId' => $this->hkd_arca_checkout_id, 'lang' => $this->language_payment_inecobank]]);
                        if (!is_wp_error($response)) {
                            if (!empty($go)) {
                                update_option('hkdigital_dump_ineco', 'yes');
                            } else {
                                add_option('hkdigital_dump_ineco', 'yes');
                            };
                            return json_decode($response['body'], true);
                        } else {
                            if (!empty($go)) {
                                update_option('hkdigital_dump_ineco', 'no');
                            } else {
                                add_option('hkdigital_dump_ineco', 'no');
                            };
                            $this->update_option('enabled', 'no');
                            return ['message' => __('Token not valid', 'wc-inecobank-payment-gateway'), 'success' => false];
                        }
                    } else {
                        if (get_option('hkdigital_dump_ineco') == 'yes') {
                            return ['message' => '', 'status' => 'success'];
                        } else {
                            return ['message' => __('You must fill token', 'wc-inecobank-payment-gateway'), 'success' => false];
                        }
                    }

                }


                public function webhook_inecobank_successful()
                {
                    global $woocommerce;
                    if ($this->empty_card) {
                        $woocommerce->cart->empty_cart();
                    }
                    if (isset($_REQUEST['order']) && $_REQUEST['order'] !== '') {
                        $isBindingOrder = get_post_meta(sanitize_text_field($_REQUEST['order']), 'isBindingOrder', true);
                        if (isset($_REQUEST['orderId'])) {
                            $paymentID = sanitize_text_field($_REQUEST['orderId']);
                        } else {
                            $paymentID = get_post_meta(sanitize_text_field($_REQUEST['order']), 'PaymentID', true);
                        }


                        $requestParams = [
                            'orderId' => $paymentID,
                            'language' => $this->language,
                        ];
                        $url = $this->api_url . '/getOrderStatusExtended.do';
                        if ($isBindingOrder) {
                            $requestParams['password'] = $this->binding_password;
                            $requestParams['userName'] = $this->binding_user_name;
                        } else {
                            $requestParams['password'] = $this->password;
                            $requestParams['userName'] = $this->user_name;
                        }
                        $response = $this->postRequestPayment($url, $requestParams);

                        $body = json_decode($response['body']);

                        $user_meta_key = 'bindingInfo_inecobank';
                        if (isset($body->bindingInfo->bindingId)) {
                            add_user_meta(get_current_user_id(), 'recurringChargeINECO' . $_REQUEST['order'], ['bindingId' => $body->bindingInfo->bindingId]);
                        }
                        if (isset($body->orderStatus) && ($body->orderStatus == 2 || $body->orderStatus == 1 || $body->orderStatus == 5)) {
                            if ($this->save_card && $this->binding_user_name != '' && $this->binding_password != '' && is_user_logged_in() && isset($body->bindingInfo) && isset($body->cardAuthInfo)) {
                                $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_inecobank');
                                $findCard = false;
                                if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                                    foreach ($bindingInfo as $key => $bindingItem) {
                                        if ($bindingItem['cardAuthInfo']['expiration'] == substr($body->cardAuthInfo->expiration, 0, 4) . '/' . substr($body->cardAuthInfo->expiration, 4) && $bindingItem['cardAuthInfo']['panEnd'] == substr($body->cardAuthInfo->pan, -4)) {
                                            $findCard = true;
                                        }
                                    }
                                }
                                if (!$findCard) {
                                    $metaArray = array(
                                        'active' => true,
                                        'bindingId' => $body->bindingInfo->bindingId,
                                        'cardAuthInfo' => [
                                            'expiration' => substr($body->cardAuthInfo->expiration, 0, 4) . '/' . substr($body->cardAuthInfo->expiration, 4),
                                            'cardholderName' => $body->cardAuthInfo->cardholderName,
                                            'pan' => substr($body->cardAuthInfo->pan, 0, 4) . str_repeat('*', strlen($body->cardAuthInfo->pan) - 8) . substr($body->cardAuthInfo->pan, -4),
                                            'panEnd' => substr($body->cardAuthInfo->pan, -4),
                                            'type' => $this->getCardType($body->cardAuthInfo->pan)
                                        ],
                                    );
                                    $user_id = $body->bindingInfo->clientId;
                                    add_user_meta($user_id, $user_meta_key, $metaArray);
                                }
                            }
                            update_post_meta(sanitize_text_field($_REQUEST['order']), 'PaymentID', $paymentID);
                            $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                            $order->update_status($this->successOrderStatus);
                            if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_REQUEST['order']) . ' successfully added to ' . $this->successOrderStatus);
                            echo $this->get_return_url($order);
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                            if (isset($this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode])) {
                                $errMessage = $this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode];
                                $order->add_order_note($errMessage, true);
                                $order->update_status('failed');
                                if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_GET['orderId']) . '. Error: ' . $this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode]);
                                update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIneco', $this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode]);
                            } else {
                                $order->update_status('failed');
                                update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIneco', __('Please try again later.', 'wc-inecobank-payment-gateway'));
                            }
                            wp_redirect($this->get_return_url($order));
                            exit;
                        }
                    }

                    if (isset($_REQUEST['paymentID']) && $_REQUEST['paymentID'] !== '') {
                        $requestParams = [];
                        $requestParams['orderId'] = sanitize_text_field($_REQUEST['paymentID']);
                        $requestParams['language'] = $this->language;
                        $requestParams['password'] = $this->password;
                        $requestParams['userName'] = $this->user_name;
                        $response = wp_remote_post($this->api_url . '/getOrderStatus.do', array(
                            'method' => 'POST',
                            'body' => http_build_query($requestParams),
                            'sslverify' => is_ssl(),
                            'timeout' => 60
                        ));
                        if (!is_wp_error($response)) {
                            $body = json_decode($response['body']);
                            if (isset($body->orderStatus) && $body->orderStatus == 2) {
                                if ($body->errorCode == 0) {
                                    $order = wc_get_order($body->orderNumber);
                                    $order->update_status($this->successOrderStatus);
                                    if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_REQUEST['order']) . ' successfully added to ' . $this->successOrderStatus);
                                    wp_redirect($this->get_return_url($order));
                                    exit;
                                } else {
                                    if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_REQUEST['orderId']) . '. Error: ' . $body->errorMessage);
                                }
                            } else {
                                $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                                if (isset($this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode])) {
                                    $errMessage = $this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode];
                                    $order->add_order_note($errMessage, true);
                                    $order->update_status('failed');
                                    if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_GET['orderId']) . '. Error: ' . $this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode]);
                                    update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIneco', $this->bankErrorCodesByDiffLanguage[$this->language][$body->errorCode]);
                                } else {
                                    $order->update_status('failed');
                                    update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIneco', __('Please try again later.', 'wc-inecobank-payment-gateway'));
                                }
                                wp_redirect($this->get_return_url($order));
                                exit;
                            }
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_REQUEST['paymentID']));
                        }
                    }

                    wc_add_notice(__('Please try again later.', 'wc-inecobank-payment-gateway'), 'error');
                    wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                    exit;
                }

                public function webhook_inecobank_failed()
                {
                    global $woocommerce;
                    if ($this->empty_card) {
                        $woocommerce->cart->empty_cart();
                    }
                    if (isset($_GET['orderId']) && $_GET['orderId'] !== '') {
                        $order = wc_get_order(sanitize_text_field($_GET['order']));
                        if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_GET['order']) . ' failed.');
                        $requestParams = [
                            'orderId' => sanitize_text_field($_GET['orderId']),
                            'password' => $this->password,
                            'userName' => $this->user_name,
                            'language' => $this->language
                        ];
                        $url = $this->api_url . '/getOrderStatus.do';
                        $response = $this->postRequestPayment($url, $requestParams);

                        if (!is_wp_error($response)) {
                            $body = json_decode($response['body']);
                            if (isset($this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse])) {
                                $order = new WC_Order(sanitize_text_field($_GET['order']));
                                $errMessage = $this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse];
                                $order->add_order_note($errMessage, true);
                                $order->update_status('failed');
                                if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_GET['orderId']) . '. Error: ' . $this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse]);
                                update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIneco', $this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse]);
                            } else {
                                $order->update_status('failed');
                                update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIneco', __('Please try again later.', 'wc-inecobank-payment-gateway'));
                            }
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with  Arca callback: #' . sanitize_text_field($_GET['orderId']) . '. Error: ' . $body->errorMessage);
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            $order->update_status('failed');
                            wc_add_notice(__('Please try again later.', 'wc-inecobank-payment-gateway'), 'error');
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_GET['orderId']));
                        }
                    }
                    wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                    exit;
                }


                public function getCardType($cardNumber)
                {
                    $explodedCardNumber = explode('*', $cardNumber);
                    $explodedCardNumber[1] = mt_rand(100000, 999999);
                    $cardNumber = implode('', $explodedCardNumber);
                    $type = '';
                    $regex = [
                        'electron' => '/^(4026|417500|4405|4508|4844|4913|4917)\d+$/',
                        'maestro' => '/^(5018|5020|5038|5612|5893|6304|6759|6761|6762|6763|0604|6390)\d+$/',
                        'dankort' => '/^(5019)\d+$/',
                        'interpayment' => '/^(636)\d+$/',
                        'unionpay' => '/^(62|88)\d+$/',
                        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
                        'master_card' => '/^5[1-5][0-9]{14}$/',
                        'amex' => '/^3[47][0-9]{13}$/',
                        'diners' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
                        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
                        'jcb' => '/^(?:2131|1800|35\d{3})\d{11}$/'
                    ];
                    foreach ($regex as $key => $item) {
                        if (preg_match($item, $cardNumber)) {
                            $type = $key;
                            break;
                        }
                    }
                    return $type;
                }

                public function postRequestPayment($url, $requestParams)
                {
                    return wp_remote_post($url, array(
                        'method' => 'POST',
                        'timeout' => 15,
                        'redirection' => 5,
                        'httpversion' => '1.1',
                        'blocking' => true,
                        'headers' => array(),
                        'body' => $requestParams,
                        'cookies' => array(),
                        'sslverify' => false
                    ));
                }
            }
        }
    }
}