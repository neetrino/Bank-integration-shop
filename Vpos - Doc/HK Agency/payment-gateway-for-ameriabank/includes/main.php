<?php

add_action('plugins_loaded', 'hkd_init_ameriabank_gateway_class');
function hkd_init_ameriabank_gateway_class()
{
    global $pluginBaseNameAmeria;

    unload_textdomain('payment-gateway-for-ameriabank');
    load_textdomain('payment-gateway-for-ameriabank', $pluginBaseNameAmeria . '/languages/payment-gateway-for-ameriabank-hy.mo');
    load_textdomain('payment-gateway-for-ameriabank', $pluginBaseNameAmeria . '/languages/payment-gateway-for-ameriabank-en_US.mo');
    load_textdomain('payment-gateway-for-ameriabank', $pluginBaseNameAmeria . '/languages/payment-gateway-for-ameriabank-ru_RU.mo');
    load_plugin_textdomain('payment-gateway-for-ameriabank', false, $pluginBaseNameAmeria . '/languages');

//    load_plugin_textdomain('payment-gateway-for-ameriabank', false, $pluginBaseNameAmeria . '/languages/');

    if (class_exists('WC_Payment_Gateway')) {
        class WC_HKD_Ameriabank_Arca_Gateway extends WC_Payment_Gateway
        {
            private $api_url;
            private $ownerSiteUrl;
            private $pluginDirUrl;
            private $currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978', 'SEK' => '752'];
            private $currency_code = '051';

            /**
             * WC_HKD_Ameriabank_Arca_Gateway constructor.
             */
            public function __construct()
            {
                global $woocommerce;
                global $bankErrorCodesByDiffLanguageAmeria;
                global $apiUrlAmeria;
                global $pluginDirUrlAmeria;

                $this->ownerSiteUrl = $apiUrlAmeria;
                $this->pluginDirUrl = $pluginDirUrlAmeria;

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

                $this->id = 'hkd_ameriabank';
                $this->icon = $this->pluginDirUrl . 'assets/images/logo_ameriabank.png';
                $this->has_fields = false;
                $this->method_title = 'Payment gateway for Ameriabank';
                $this->method_description = 'Pay with  Ameriabank payment system. Please note that the payment will be made in Armenian Dram.';

                if (is_admin()) {
                    if (isset($_POST['hkd_ameriabank_checkout_id']) && $_POST['hkd_ameriabank_checkout_id'] != '') {
                        update_option('hkd_ameriabank_checkout_id', sanitize_text_field($_POST['hkd_ameriabank_checkout_id']));
                        $this->update_option('title', __('Pay via credit card', 'payment-gateway-for-ameriabank'));
                        $this->update_option('description', __('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'payment-gateway-for-ameriabank'));
                        $this->update_option('save_card_button_text', __('Add a credit card', 'payment-gateway-for-ameriabank'));
                        $this->update_option('save_card_header', __('Purchase safely by using your saved credit card', 'payment-gateway-for-ameriabank'));
                        $this->update_option('save_card_use_new_card', __('Use a new credit card', 'payment-gateway-for-ameriabank'));
                    }
                }

                $this->init_form_fields();
                $this->init_settings();

                $this->title = !empty($this->get_option('title')) ? $this->get_option('title') : __('Pay via credit card', 'payment-gateway-for-ameriabank');
                $this->clientID = $this->get_option('ClientID');
                $language = get_locale();
                $this->language_payment = ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US') ? $language : 'hy';
                $this->enabled = $this->get_option('enabled');
                $this->hkd_arca_checkout_id = get_option('hkd_ameriabank_checkout_id');
                $this->language = $this->get_option('language');
                $this->testmode = 'yes' === $this->get_option('testmode');
                $this->secondTypePayment = 'yes' === $this->get_option('secondTypePayment');
                $this->empty_card = 'yes' === $this->get_option('empty_card');
                $this->user_name = $this->get_option('user_name');
                $this->password = $this->get_option('password');
                $this->save_card_button_text = !empty($this->get_option('save_card_button_text')) ? $this->get_option('save_card_button_text') : __('Add a credit card', 'payment-gateway-for-ameriabank');
                $this->save_card_header = !empty($this->get_option('save_card_header')) ? $this->get_option('save_card_header') : __('Purchase safely by using your saved credit card', 'payment-gateway-for-ameriabank');
                $this->save_card_use_new_card = !empty($this->get_option('save_card_use_new_card')) ? $this->get_option('save_card_use_new_card') : __('Use a new credit card', 'payment-gateway-for-ameriabank');
                $this->debug = 'yes' === $this->get_option('debug');
                $this->multi_currency = 'yes' === $this->get_option('multi_currency');
                $this->description = $this->testmode == true ? __('Test payment', 'payment-gateway-for-ameriabank') : $this->get_option('description');
                $this->api_url = $this->testmode == true ? 'https://servicestest.ameriabank.am/VPOS/' : 'https://services.ameriabank.am/VPOS/';
                $this->save_card = 'yes' === $this->get_option('save_card');

                $this->successOrderStatus = $this->get_option('successOrderStatus');

                if ($this->debug) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) $this->log = $woocommerce->logger(); else $this->log = new WC_Logger();
                }
                if ($this->multi_currency) {
                    $this->currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978', 'SEK' => '752', 'GBP' => '826'];
                    $wooCurrency = get_woocommerce_currency();
                    $this->currency_code = $this->currencies[$wooCurrency];
                }

                // process the Change Payment "transaction"
                add_action('woocommerce_scheduled_subscription_payment', array($this, 'process_subscription_payment'), 10, 3);

                add_action('woocommerce_api_delete_binding_ameria', array($this, 'delete_binding'));


                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                /**
                 * Success callback url for ameriabank payment api
                 */
                add_action('woocommerce_api_ameriabank_successful', array($this, 'webhook_ameriabank_successful'));

                /**
                 * Failed callback url for ameriabank payment api
                 */
                add_action('woocommerce_api_ameriabank_response', array($this, 'webhook_ameriabank_response'));
                /**
                 * styles and fonts for ameriabank payment plugin
                 */
                add_action('admin_print_styles', array($this, 'enqueue_stylesheets'));

                if (is_user_logged_in() && $this->save_card) {
                    add_filter('query_vars', array($this, 'queryVarsCards'));
                    add_filter('woocommerce_account_menu_items', array($this, 'addCardLinkMenu'));
                    add_action('woocommerce_account_cards_endpoint', array($this, 'CardsPageContent'));
                }

                if (is_admin()) {
                    $this->checkActivation();
                }

                if ($this->secondTypePayment) {
                    add_filter('woocommerce_admin_order_actions', array($this, 'add_custom_order_status_actions_button'), 100, 2);
                    add_action('admin_head', array($this, 'add_custom_order_status_actions_button_css'));

                }

                add_action('woocommerce_order_status_changed', array($this, 'statusChangeHook'), 10, 3);
                add_action('woocommerce_order_edit_status', array($this, 'statusChangeHookSubscription'), 10, 2);

                $this->bankErrorCodesByDiffLanguage = $bankErrorCodesByDiffLanguageAmeria;

                // WP cron
                add_action('cronCheckOrderAmeria', array($this, 'cronCheckOrderAmeria'));
            }

            public function cronCheckOrderAmeria()
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
                        AND pm.meta_value = 'hkd_ameriabank'
                        ORDER BY pm.meta_value ASC, pm.post_id DESC
                    ");
                foreach ($orders as $order) {
                    $order = wc_get_order($order->ID);
                    $paymentID = get_post_meta($order->ID, 'PaymentID', true);
                    if ($paymentID) {
                        $args = [
                            "PaymentID" => $paymentID,
                            "Username" => $this->user_name,
                            "Password" => $this->password,
                        ];
                        $response = wp_remote_post($this->api_url . 'api/VPOS/GetPaymentDetails', [
                            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                            'body' => json_encode($args),
                            'method' => 'POST'
                        ]);
                        if (!is_wp_error($response)) {
                            $body = json_decode($response['body']);
                            if ($this->secondTypePayment) {
                                if ($body->OrderStatus == 1) {
                                    $order->update_status($this->successOrderStatus);
                                    if ($this->debug) $this->log->add($this->id, 'Order status was changed to ' . $this->successOrderStatus . ' via cron job. Status code is 1. #' . $order->ID);
                                }
                            }
                            if ($body->OrderStatus == 2) {
                                $order->update_status($this->successOrderStatus);
                                if ($this->debug) $this->log->add($this->id, 'Order status was changed to ' . $this->successOrderStatus . ' via cron job #' . $order->ID);
                            }
                            if ($body->OrderStatus == 3) {
                                $order->update_status('cancelled');
                                if ($this->debug) $this->log->add($this->id, 'Order status was changed to Cancelled via cron job. Status Code is 3 #' . $order->ID);
                            }
                            if ($body->OrderStatus == 4) {
                                $order->update_status('refund');
                                if ($this->debug) $this->log->add($this->id, 'Order status was changed to Refund via cron job. Status Code is 4 #' . $order->ID);
                            }

                            if ($body->OrderStatus == 6) {
                                $order->update_status('cancelled');
                                if ($this->debug) $this->log->add($this->id, 'Order status was changed to Failed #' . $order->ID);
                            }

                        }
                    }
                }
            }

            public function statusChangeHookSubscription($order_id, $new_status)
            {
                $order = wc_get_order($order_id);
                if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_ameriabank' && $this->secondTypePayment) {
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
                if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_ameriabank') {
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
                if (isset($this->getPaymentGatewayByOrder($order)->id) && $this->getPaymentGatewayByOrder($order)->id == 'hkd_ameriabank') {
                    if ($order->has_status(array('processing'))) {
                        $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
                        $actions['cancelled'] = array(
                            'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=cancelled&order_id=' . $order_id), 'woocommerce-mark-order-status'),
                            'name' => __('Cancel Order', 'woocommerce'),
                            'action' => "cancel custom",
                        );
                    }
                }
                return $actions;
            }

            public function checkActivation()
            {
                $today = date('Y-m-d');
                if (get_option('hkd_check_activation_ameria') !== $today) {
                    $payload = ['domain' => $_SERVER['SERVER_NAME'], 'enabled' => $this->enabled];
                    wp_remote_post($this->ownerSiteUrl . 'bank/ameria/checkStatusPluginActivation', array(
                        'sslverify' => false,
                        'method' => 'POST',
                        'headers' => array('Accept' => 'application/json'),
                        'body' => $payload
                    ));
                    update_option('hkd_check_activation_ameria', $today);
                }
            }


            /**
             * Process a confirmation Payment if supported.
             *
             * @param int $order_id Order ID.
             * @return bool|WP_Error
             */
            public function confirmPayment($order_id, $new_status)
            {
                /* $reason */
                $order = wc_get_order($order_id);
                if (!$order->has_status('processing')) {
                    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
                    $amount = ($this->testmode == true) ? 10.0 : floatval($order->get_total());
                    $args = [
                        'PaymentID' => $PaymentID,
                        'Username' => $this->user_name,
                        'Password' => $this->password,
                        'Amount' => $amount,
                    ];
                    $response = wp_remote_post($this->api_url . 'api/VPOS/ConfirmPayment', [
                        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                        'body' => json_encode($args),
                        'method' => 'POST'
                    ]);

                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->ResponseCode == '00') {
                            if ($new_status == 'completed') {
                                $order->update_status('completed');
                            } else {
                                $order->update_status('active');
                            }
                            return true;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order confirm paymend #' . $order_id . '  failed.');
                            if ($new_status == 'completed') {
                                $order->update_status('processing', $body->ResponseMessage);
                            } else {
                                $order->update_status('pending', $body->ResponseMessage);
                            }
                            die($body->ResponseMessage);
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order confirm paymend #' . $order_id . '  failed.');
                        if ($new_status == 'completed') {
                            $order->update_status('processing');
                        } else {
                            $order->update_status('pending');
                        }
                        die('Order confirm paymend #' . $order_id . '  failed.');
                    }
                }
            }

            /**
             * Process a Cancel Payment if supported.
             *
             * @param int $order_id Order ID.
             * @return bool|WP_Error
             */
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
                            $args = [
                                'PaymentID' => $PaymentID,
                                'Username' => $this->user_name,
                                'Password' => $this->password,
                            ];
                            $response = wp_remote_post($this->api_url . 'api/VPOS/CancelPayment', [
                                'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                                'body' => json_encode($args),
                                'method' => 'POST'
                            ]);
                            if (!is_wp_error($response)) {
                                $body = json_decode($response['body']);
                                if (isset($body->ResponseCode)) {
                                    if ($body->ResponseCode == '00') {
                                        $order->update_status('cancelled');
                                        return true;
                                    } else {
                                        if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                                        if ($order->get_parent_id() > 0) {
                                            $order->update_status('pending');
                                        } else {
                                            $order->update_status('processing');
                                        }
                                        die($body->ResponseMessage);
                                    }
                                } else {
                                    $order->update_status('processing');
                                    die($body->Message);
                                }
                            } else {
                                if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                                if ($order->get_parent_id() > 0) {
                                    $order->update_status('pending');
                                } else {
                                    $order->update_status('processing');
                                }
                                die('Order Cancel paymend #' . $order_id . '  failed.');
                            }
                        }
                    }
                }

            }


            public function queryVarsCards($vars)
            {
                $vars[] = 'cards';
                return $vars;
            }


            /**
             * Process a refund if supported.
             *
             * @param int $order_id Order ID.
             * @param float $amount Refund amount.
             * @param string $reason Refund reason.
             * @return bool|WP_Error
             */
            public function process_refund($order_id, $amount = null, $reason = '')
            {
                /* $reason */
                $order = wc_get_order($order_id);

                $PaymentID = get_post_meta($order_id, 'PaymentID', true);

                $amount = ($this->testmode == true) ? 10.0 : floatval($amount);
                $args = [
                    'PaymentID' => $PaymentID,
                    'Username' => $this->user_name,
                    'Password' => $this->password,
                    'Amount' => $amount,
                ];

                $response = wp_remote_post($this->api_url . 'api/VPOS/RefundPayment', [
                    'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                    'body' => json_encode($args),
                    'method' => 'POST'
                ]);

                if (!is_wp_error($response)) {
                    $body = json_decode($response['body']);
                    if ($body->ResponseCode == '00') {
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

            public function init_form_fields()
            {
                $debug = __('Log Payment Gateway for Ameriabank events, inside <code>woocommerce/logs/ameriabank.txt</code>', 'payment-gateway-for-ameriabank');
                if (!version_compare(WOOCOMMERCE_VERSION, '2.0', '<')) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.2.0', '<'))
                        $debug = str_replace('ameriabank', $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)), $debug);
                    elseif (function_exists('wc_get_log_file_path')) {
                        $debug = str_replace('woocommerce/logs/ameriabank.txt', '<a href="/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)) . '-log" target="_blank">' . __('here', 'payment-gateway-for-ameriabank') . '</a>', $debug);
                    }
                }

                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'payment-gateway-for-ameriabank'),
                        'label' => __('Enable payment gateway', 'payment-gateway-for-ameriabank'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'payment-gateway-for-ameriabank'),
                        'type' => 'text',
                        'description' => __('User (website visitor) sees this title on order registry page as a title for purchase option.', 'payment-gateway-for-ameriabank'),
                        'default' => __('Pay via credit card', 'payment-gateway-for-ameriabank'),
                        'desc_tip' => true,
                        'placeholder' => __('Type the title', 'payment-gateway-for-ameriabank')
                    ),
                    'description' => array(
                        'title' => __('Description', 'payment-gateway-for-ameriabank'),
                        'type' => 'textarea',
                        'description' => __('User (website visitor) sees this description on order registry page in bank purchase option.', 'payment-gateway-for-ameriabank'),
                        'default' => __('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'payment-gateway-for-ameriabank'),
                        'desc_tip' => true,
                        'placeholder' => __('Type the description', 'payment-gateway-for-ameriabank')
                    ),
                    'language' => array(
                        'title' => __('Language', 'payment-gateway-for-ameriabank'),
                        'type' => 'select',
                        'options' => [
                            'am' => 'Հայերեն',
                            'ru' => 'Русский',
                            'en' => 'English',
                        ],
                        'description' => __('Here interface language of bank purchase can be regulated', 'payment-gateway-for-ameriabank'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'successOrderStatus' => array(
                        'title' => __('Success Order Status', 'payment-gateway-for-ameriabank'),
                        'type' => 'select',
                        'options' => [
                            'processing' => 'Processing',
                            'completed' => 'Completed',
                        ],
                        'description' => __('Here you can select the status of confirmed payment orders.', 'payment-gateway-for-ameriabank'),
                        'default' => 'processing',
                        'desc_tip' => true,
                    ),
                    'testmode' => array(
                        'title' => __('Test mode', 'payment-gateway-for-ameriabank'),
                        'label' => __('Enable test Mode', 'payment-gateway-for-ameriabank'),
                        'type' => 'checkbox',
                        'description' => __('To test the testing version login and password provided by the bank should be typed', 'payment-gateway-for-ameriabank'),
                        'default' => 'yes',
                        'desc_tip' => true,
                    ),
                    'secondTypePayment' => array(
                        'title' => __('Two-stage Payment', 'payment-gateway-for-ameriabank'),
                        'label' => __('Enable payment confirmation function', 'payment-gateway-for-ameriabank'),
                        'type' => 'checkbox',
                        'description' => __('two-stage: when the payment amount is first blocked on the buyer’s account and then at the second stage is withdrawn from the account', 'payment-gateway-for-ameriabank'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'save_card' => array(
                        'title' => __('Card Binding', 'payment-gateway-for-ameriabank'),
                        'type' => 'checkbox',
                        'label' => __('Enable card binding option', 'payment-gateway-for-ameriabank'),
                        'default' => 'no',
                        'desc_tip' => true,
                        'description' => __('The function of the card binding is available only with the permission of the bank.', 'payment-gateway-for-ameriabank'),
                    ),
                    'save_card_button_text' => array(
                        'title' => __('New binding card text', 'payment-gateway-for-ameriabank'),
                        'placeholder' => __('Type the save card checkbox text', 'payment-gateway-for-ameriabank'),
                        'type' => 'text',
                        'default' => __('Add a credit card', 'payment-gateway-for-ameriabank'),
                        'desc_tip' => true,
                        'description' => ' ',
                        'class' => 'saveCardInfoAmeria hiddenValueAmeria',
                    ),
                    'save_card_header' => array(
                        'title' => __('Save card description text', 'payment-gateway-for-ameriabank'),
                        'placeholder' => __('Type the save card description text', 'payment-gateway-for-ameriabank'),
                        'type' => 'text',
                        'default' => __('Purchase safely by using your saved credit card', 'payment-gateway-for-ameriabank'),
                        'desc_tip' => true,
                        'description' => ' ',
                        'class' => 'saveCardInfoAmeria hiddenValueAmeria',
                    ),
                    'save_card_use_new_card' => array(
                        'title' => __('Use new card text', 'payment-gateway-for-ameriabank'),
                        'placeholder' => __('Type the use new card text', 'payment-gateway-for-ameriabank'),
                        'type' => 'text',
                        'default' => __('Use a new credit card', 'payment-gateway-for-ameriabank'),
                        'desc_tip' => true,
                        'description' => ' ',
                        'class' => 'saveCardInfoAmeria hiddenValueAmeria'
                    ),
                    'multi_currency' => array(
                        'title' => __('Multi-Currency', 'payment-gateway-for-ameriabank'),
                        'label' => __('Enable Multi-Currency', 'payment-gateway-for-ameriabank'),
                        'type' => 'checkbox',
                        'description' => __('This action, if permitted by the bank, enables to purchase by multiple currencies', 'payment-gateway-for-ameriabank'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'debug' => array(
                        'title' => __('Debug Log', 'payment-gateway-for-ameriabank'),
                        'type' => 'checkbox',
                        'label' => __('Enable debug mode', 'payment-gateway-for-ameriabank'),
                        'default' => 'no',
                        'description' => $debug,
                    ),
                    'ClientID' => array(
                        'title' => __('ClientID', 'payment-gateway-for-ameriabank'),
                        'type' => 'text',
                        'placeholder' => __('Type the client ID', 'payment-gateway-for-ameriabank')
                    ),
                    'user_name' => array(
                        'title' => __('User Name', 'payment-gateway-for-ameriabank'),
                        'type' => 'text',
                        'placeholder' => __('Type the user name', 'payment-gateway-for-ameriabank')
                    ),
                    'password' => array(
                        'title' => __('Password', 'payment-gateway-for-ameriabank'),
                        'type' => 'password',
                        'placeholder' => __('Type the password', 'payment-gateway-for-ameriabank')
                    ),
                    'useful_functions' => array(
                        'title' => __('Useful functions', 'payment-gateway-for-ameriabank'),
                        'type' => 'hidden'
                    ),
                    'empty_card' => array(
                        'title' => __('Cart totals', 'payment-gateway-for-ameriabank'),
                        'label' => __('Activate shopping cart function', 'payment-gateway-for-ameriabank'),
                        'type' => 'checkbox',
                        'description' => __('This feature ensures that the contents of the shopping cart are available at the time of order registration if the site buyer decides to change the payment method.', 'payment-gateway-for-ameriabank'),
                        'default' => 'yes',
                        'desc_tip' => true,
                    ),
                    'links' => array(
                        'title' => __('Links', 'payment-gateway-for-ameriabank'),
                        'type' => 'hidden'
                    ),
                );
            }

            public function process_payment($order_id)
            {
                global $woocommerce;
                $bindingType = $_REQUEST['bindingType'];
                $order = wc_get_order($order_id);
                $amount = ($this->testmode == true) ? 10.0 : floatval($order->get_total());
                if ((isset($bindingType) && $bindingType != 'saveCardAmeria')) {
                    $args = [
                        "ClientID" => $this->clientID,
                        "Amount" => $amount,
                        "OrderID" => ($this->testmode == true) ? rand(1000000, 2346000) : $order_id,
                        "BackURL" => get_site_url() . '/wc-api/ameriabank_response',
                        "Username" => $this->user_name,
                        "Password" => $this->password,
                        "Description" => '',
                        "Currency" => $this->currency_code,
                        "Opaque" => $order_id,
                        "language" => $this->language,
                        "CardHolderID" => $bindingType,
                        "PaymentType" => 6,
                    ];

                    $response = wp_remote_post($this->api_url . 'api/VPOS/MakeBindingPayment', [
                        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                        'body' => json_encode($args),
                        'method' => 'POST'
                    ]);
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);

                        add_user_meta(get_current_user_id(), 'recurringChargeAmeria' . $order_id, ['bindingId' => $body->CardHolderID]);
                        if ($body->ResponseCode == '00') {
                            $order = wc_get_order($order_id);
                            update_post_meta($order_id, 'PaymentID', sanitize_text_field($body->PaymentID));
                            $order->update_status($this->successOrderStatus);
                            if ($this->debug) $this->log->add($this->id, 'Order #' . $order_id . ' successfully added to processing.');
                            return array('result' => 'success', 'redirect' => $order->get_checkout_order_received_url());
                        } else if ($body->ResponseCode == '01') {
                            $order_id = $this->duplicate_order($order);
                            return $this->process_payment($order_id);
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Ameriabank Arca callback: #' . esc_attr($_REQUEST['orderId']) . '. Error: ' . esc_attr($body->errorMessage));
                            $order = wc_get_order($_REQUEST['orderId']);
                            if ($order) {
                                $order->update_status('failed');
                            }
                            wc_add_notice(__('Please try again.', 'payment-gateway-for-ameriabank'), 'error');
                            return array('result' => 'success', 'redirect' => get_permalink(get_option('woocommerce_checkout_page_id')));
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with Ameriabank Arca callback: #' . esc_attr($_REQUEST['orderId']));
                        $order = wc_get_order($_REQUEST['orderId']);
                        if ($order) {
                            $order->update_status('failed');
                        }
                        wc_add_notice(__('Please try again.', 'payment-gateway-for-ameriabank'), 'error');
                        return array('result' => 'success', 'redirect' => get_permalink(get_option('woocommerce_checkout_page_id')));
                    }
                }
                $args = [
                    "ClientID" => $this->clientID,
                    "Amount" => $amount,
                    "OrderID" => ($this->testmode == true) ? rand(1000000, 2346000) : $order_id,
                    "BackURL" => get_site_url() . '/wc-api/ameriabank_response',
                    "Username" => $this->user_name,
                    "Password" => $this->password,
                    "Description" => '',
                    "Currency" => $this->currency_code,
                    "Opaque" => $order_id,
                    "language" => $this->language,
                ];

                if ((isset($bindingType) && $bindingType == 'saveCardAmeria') || (function_exists('wcs_get_subscriptions_for_order') && !empty(wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any'))))) {
                    $args["CardHolderID"] = md5(get_current_user_id() . microtime());
                }


                $response = wp_remote_post($this->api_url . 'api/VPOS/InitPayment', [
                    'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                    'body' => json_encode($args),
                    'method' => 'POST'
                ]);
                if (!is_wp_error($response)) {
                    $body = json_decode($response['body']);

                    if ($body->ResponseCode == 1 && $body->ResponseMessage === "OK") {
                        $order->update_status('pending');
                        wc_reduce_stock_levels($order_id);
                        if (!$this->empty_card) {
                            $woocommerce->cart->empty_cart();
                        }
                        update_post_meta($order_id, 'PaymentID', sanitize_text_field($body->PaymentID));
                        return array('result' => 'success', 'redirect' => $this->api_url . "/Payments/Pay?id=" . $body->PaymentID . "&lang=" . $this->language);
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                        $order->update_status('failed', $body->errorMessage);
                        wc_add_notice(__('Please try again.', 'payment-gateway-for-ameriabank'), 'error');
                    }
                } else {
                    if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                    $order->update_status('failed');
                    wc_add_notice(__('Connection error.', 'payment-gateway-for-ameriabank'), 'error');
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

            public function CardsPageContent()
            {
                $plugin_url = $this->pluginDirUrl;
                wp_enqueue_style('hkd-front-style-ameria', $plugin_url . "assets/css/cards.css");
                wp_enqueue_script('hkd-front-js-ameria', $plugin_url . "assets/js/cards.js");
                $html = '<div id="hkdigital_binding_info">';
                $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfoAmeria');
                if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                    $html .= '<h4 class="card_payment_title card_page">' . __('Your card list', 'payment-gateway-for-ameriabank') . '</h4>
                              <h2 class="card_payment_second card_page">' . __('You can Delete Cards', 'payment-gateway-for-ameriabank') . '</h2>
                                <ul class="card_payment_list">';
                    foreach ($bindingInfo as $key => $bindingItem) {
                        $html .= '<li class="card_item">
                                        <span class="card_subTitile">
                                        ' . __($bindingItem['cardAuthInfo']['cardholderName'] . ' |  &#8226; &#8226; &#8226; &#8226; ' . $bindingItem['cardAuthInfo']['panEnd'] . ' (expires ' . $bindingItem['cardAuthInfo']['expiration'] . ')', 'payment-gateway-for-ameriabank') . '
                                         </span>
                                         <img src="' . $this->pluginDirUrl . 'assets/images/card_types/' . $bindingItem['cardAuthInfo']['type'] . '.png" class="card_logo big_img" alt="card"/>
                                         <svg  class="hk-remove-card-ameria" data-id="' . $bindingItem['bindingId'] . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
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
                                      ' . __('No Saved Cards', 'payment-gateway-for-ameriabank') . '
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
             * Todo Delete Saved Card AJAX
             */
            public function delete_binding()
            {
                try {
                    $bindingIdForDelete = $_REQUEST['bindingId'];
                    $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfoAmeria');
                    if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                        foreach ($bindingInfo as $key => $item) {
                            if ($item['bindingId'] == $bindingIdForDelete) {
                                unset($bindingInfo[$key]);
                            }
                        }
                        delete_user_meta(get_current_user_id(), 'bindingInfoAmeria');
                        if (count($bindingInfo) > 0)
                            add_user_meta(get_current_user_id(), 'bindingInfoAmeria', array_values($bindingInfo));

                        $payload = [
                            "ClientID" => $this->clientID,
                            "Username" => $this->user_name,
                            "Password" => $this->password,
                            "CardHolderID" => $bindingIdForDelete,
                            "PaymentType" => 6,
                        ];
                        wp_remote_post($this->api_url . 'api/VPOS/DeactivateBinding', array(
                            'method' => 'POST',
                            'body' => http_build_query($payload),
                            'sslverify' => is_ssl(),
                            'timeout' => 60
                        ));

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


            public function process_subscription_payment($order_id)
            {
                $order = wc_get_order($order_id);
                if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_ameriabank') {
                    $amount = ($this->testmode == true) ? 10.0 : floatval($order->get_total());
                    $bindingInfo = get_user_meta($order->get_user_id(), 'recurringChargeAmeria' . (int)$order->get_parent_id());
                    $order->update_status('on-hold');
                    $args = [
                        "ClientID" => $this->clientID,
                        "Amount" => $amount,
                        "OrderID" => rand(100000000, 999999999),
                        "BackURL" => get_site_url() . '/wc-api/ameriabank_response',
                        "Username" => $this->user_name,
                        "Password" => $this->password,
                        "Description" => $this->description,
                        "Currency" => $this->currency_code,
                        "Opaque" => $order_id,
                        "language" => $this->language,
                        "CardHolderID" => $bindingInfo[0]['bindingId'],
                        "PaymentType" => 6,
                    ];
                    $response = wp_remote_post($this->api_url . 'api/VPOS/MakeBindingPayment', [
                        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                        'body' => json_encode($args),
                        'method' => 'POST'
                    ]);

                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->ResponseCode == '00') {
                            update_post_meta($order_id, 'PaymentID', sanitize_text_field($body->PaymentID));
                            if ($this->secondTypePayment) {
                                $order->update_status('on-hold');
                            } else {
                                $order->update_status('active');
                            }
                            if ($this->debug) $this->log->add($this->id, 'Order #' . $order_id . ' successfully added to processing.');
                            return true;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Ameriabank Arca callback:');
                            $order->update_status('pending-cancel');
                            echo "<pre>";
                            print_r($body);
                            echo "error";
                            exit;
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with Ameriabank Arca callback.');
                        $order->update_status('pending-cancel', 'WP Error binding payment');
                        echo "error";
                        exit;
                    }
                }
            }

            /* Todo
             *
             */
            public function payment_fields()
            {
                $plugin_url = $this->pluginDirUrl;
                wp_enqueue_style('hkd-front-style-ameria', $plugin_url . "assets/css/cards.css");
                wp_enqueue_script('hkd-front-js-ameria', $plugin_url . "assets/js/cards.js");
                $description = $this->get_description();
                if ($description) {
                    echo wpautop(wptexturize($description));  // @codingStandardsIgnoreLine.
                }
                if (is_user_logged_in() && $this->save_card) {
                    $html = '<div id="hkdigital_binding_info">';
                    $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfoAmeria');
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
                            $html .= '<svg  class="hk-remove-card-ameria" data-id="' . $bindingItem['bindingId'] . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                            <path fill="#ed2353"
                                                  d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z"></path>
                                        </svg>
                                    </li>';
                        }
                        $html .= '<li class="card_item">
                                        <input id="payment_newCard_Ameria" type="radio" class="input-radio" name="bindingType" value="saveCardAmeria">
                                        <label for="payment_newCard_Ameria">
                                         ' . $this->save_card_use_new_card . '
                                         </label>
                                    </li>';
                        $html .= '</ul>
                            </div>';
                    } else {
                        $html .= '<div class="check-box noselect">
                                    <input type="checkbox" id="saveCardAmeria" name="bindingType" value="saveCardAmeria"/>
                                    <label for="saveCardAmeria"> <span class="check"><svg class="svg-check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                        <path fill="#ffffff" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path>
                                    </svg> </span>
                                      ' . $this->save_card_button_text . '
                                    </label>
                                 </div>';
                    }
                    echo $html;
                }
            }

            public function enqueue_stylesheets()
            {
                $plugin_url = $this->pluginDirUrl;

                wp_enqueue_script('hkd-ameria-front-admin-js', $plugin_url . "assets/js/admin.js");
                wp_localize_script('hkd-ameria-front-admin-js', 'myScriptAmeria', array(
                    'pluginsUrl' => $plugin_url,
                ));
                wp_enqueue_style('hkd-style-ameria', $plugin_url . "assets/css/style.css");
                wp_enqueue_style('hkd-style-awesome-ameria', $plugin_url . "assets/css/font_awesome.css");
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
                <div class="wrap-ameriabank wrap-content wrap-content-hkd" id="pluginMainWrap">
                    <h4><?php echo __('Online payment gateway', 'payment-gateway-for-ameriabank') ?></h4>
                    <h3><?php echo __('Ameriabank CJSC', 'payment-gateway-for-ameriabank'); ?></h3>
                    <?php if (!$validate['success']): ?>
                        <div class="w-100">
                            <p class="mb-10"><?php echo __('Before using the plugin, please contact the bank to receive respective regulations.', 'payment-gateway-for-ameriabank'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($validate['success']) { ?>
                        <table class="form-table">
                            <?php $this->generate_settings_html(); ?>
                            <tr valign="top">
                                <th scope="row">Ameriabank callback Url Success</th>
                                <td><?= get_site_url() ?>/wc-api/ameriabank_successful</td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Ameriabank callback Url Failed</th>
                                <td><?= get_site_url() ?>/wc-api/ameriabank_failed</td>
                            </tr>
                        </table>
                    <?php } else { ?>
                        <div class="step-inner-content mt-40">

                            <h2 class="verification-label text-center">
                                <?php echo __('IDENTIFICATION', 'payment-gateway-for-ameriabank'); ?>
                            </h2>
                            <p class="mt-5 font-size-14 text-center">
                                <?php echo __('Contact us at the specified phone number or e-mail address for authentication.', 'payment-gateway-for-ameriabank'); ?>
                            </p>
                            <div class="form-inner-area">
                                <label class="input-label-verification"
                                       for="payment-gateway-for-ameriabank_verification_id"><?php echo __('Identification password', 'payment-gateway-for-ameriabank'); ?></label>
                                <input type="text" name="hkd_ameriabank_checkout_id"
                                       id="payment-gateway-for-ameriabank_verification_id" class="form-control "
                                       value="<?php echo esc_html($this->hkd_arca_checkout_id); ?>"
                                       minlength="2"
                                       placeholder="Օրինակ՝ Ameriagayudcsu14">
                            </div>
                            <div class="blue terms_div">
                                <iframe src="<?php echo esc_html($this->pluginDirUrl) ?>terms/terms.html"
                                        height="100%" width="100%" title="Terms Iframe"></iframe>
                            </div>
                            <div class="text-center accept_terms_div mb-10">
                                <label class="checkbox">
                                    <input type="checkbox"
                                           class="accept_terms"
                                           name="terms" id="terms">
                                    <span><?php echo __('I have read and agree to the Plugin', 'payment-gateway-for-ameriabank'); ?> <a
                                                href="javascript:"
                                                id="toggle-terms_div"><b>  <?php echo __('Terms & Conditions', 'payment-gateway-for-ameriabank'); ?></b></a></span>&nbsp;<abbr
                                            class="required" title="required">*</abbr>
                                    <br>
                                    <span id="terms-error" class="error"></span>
                                </label>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="wrap-ameriabank wrap-content wrap-content-hkd" id="paymentInfoBlock">
                    <div class="wrap-content-hkd-400px">
                        <img src="<?= $this->pluginDirUrl ?>assets/images/ameriabank.png">
                        <div class="wrap-content-hkd-info hkd-ameria">
                            <h2> <?php echo __('Payment system', 'payment-gateway-for-ameriabank'); ?></h2>
                            <div class="wrap-content-info">
                                <div class="phone-icon icon"><i class="fa fa-phone"></i></div>
                                <p><a href="tel:+37410561111">010 56 11 11</a></p>
                                <div class="mail-icon icon"><i class="fa fa-envelope"></i></div>
                                <p><a href="mailto:vpos.support@ameriabank.am">vpos.support@ameriabank.am</a></p>
                                <div class="mail-icon icon"><i class="fa fa-link"></i></div>
                                <p><a target="_blank" href="https://ameriabank.am">ameriabank.am</a></p>
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
                $go = get_option('hkdump');
                $wooCurrency = get_woocommerce_currency();

                if (!isset($this->currencies[$wooCurrency])) {
                    $this->update_option('enabled', 'no');
                    return ['message' => 'Դուք այժմ օգտագործում եք ' . $wooCurrency . ' արժույթը, այն չի սպասարկվում բանկի կողմից։ Հասանելի արժույթներն են ՝  ' .
                        implode(', ', array_keys($this->currencies)), 'success' => false, 'err_msg' => 'currency_error'];
                }
                if ($this->hkd_arca_checkout_id == '') {
                    if (!empty($go)) {
                        update_option('hkdump', 'no');
                    } else {
                        add_option('hkdump', 'no');
                    };
                    $this->update_option('enabled', 'no');
                    return ['message' => __('You must fill token', 'payment-gateway-for-ameriabank'), 'success' => false];
                }
                $ch = curl_init($this->ownerSiteUrl .
                    'bank/ameria/checkApiConnection');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['checkIn' => true]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Accept' => 'application/json'
                    ]
                );
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                $res = curl_exec($ch);
                curl_close($ch);
                if ($res) {
                    $response = wp_remote_post($this->ownerSiteUrl .
                        'bank/ameria/checkActivation', ['headers' => array('Accept' => 'application/json'), 'sslverify' => false, 'body' => ['domain' => $_SERVER['SERVER_NAME'], 'checkoutId' => $this->hkd_arca_checkout_id, 'lang' => $this->language_payment]]);
                    if (!is_wp_error($response)) {
                        if (!empty($go)) {
                            update_option('hkdump', 'yes');
                        } else {
                            add_option('hkdump', 'yes');
                        };
                        return json_decode($response['body'], true);
                    } else {
                        if (!empty($go)) {
                            update_option('hkdump', 'no');
                        } else {
                            add_option('hkdump', 'no');
                        };
                        $this->update_option('enabled', 'no');
                        return ['message' => __('Token not valid', 'payment-gateway-for-ameriabank'), 'success' => false];
                    }
                } else {
                    if (get_option('hkdump') == 'yes') {
                        return ['message' => '', 'success' => true];
                    } else {
                        return ['message' => __('You must fill token', 'payment-gateway-for-ameriabank'), 'success' => false];
                    }
                }

            }


            public function webhook_ameriabank_successful()
            {

                if (isset($_REQUEST['order']) && $_REQUEST['order'] !== '') {
                    $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                    $order->update_status($this->successOrderStatus);
                    if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_REQUEST['order']) . ' successfully added to processing');
                    echo $this->get_return_url($order);
                    wp_redirect($this->get_return_url($order));
                    exit;
                }

                if (isset($_REQUEST['orderId']) && $_REQUEST['orderId'] !== '') {

                    $response = wp_remote_post($this->api_url . '/getOrderStatus.do?orderId=' . sanitize_text_field($_REQUEST['orderId']) . '&language=' . $this->language . '&password=' . $this->password . '&userName=' . $this->user_name);

                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->ResponseCode == 1 && $body->ResponseMessage === "OK") {
                            $order = wc_get_order($body->OrderNumber);
                            $order->update_status($this->successOrderStatus);
                            if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_REQUEST['order']) . ' successfully added to ' . $this->successOrderStatus);
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Ameriabank Arca callback: #' . esc_attr($_REQUEST['orderId']) . '. Error: ' . esc_attr($body->errorMessage));
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with Ameriabank Arca callback: #' . esc_attr($_REQUEST['orderId']));
                    }
                }

                wc_add_notice(__('Please try again later.', 'payment-gateway-for-ameriabank'), 'error');
                wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                exit;
            }

            public function webhook_ameriabank_response()
            {
                global $woocommerce;
                if ($this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                $order = wc_get_order(sanitize_text_field($_GET['opaque']));
                $args = [
                    "PaymentID" => sanitize_text_field($_GET['paymentID']),
                    "Username" => $this->user_name,
                    "Password" => $this->password,
                ];

                $response = wp_remote_post($this->api_url . 'api/VPOS/GetPaymentDetails', [
                    'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                    'body' => json_encode($args),
                    'method' => 'POST'
                ]);

                if (!is_wp_error($response)) {
                    $body = json_decode($response['body']);

                    if ($body->ResponseCode == '00') {
                        $user_meta_key = 'bindingInfoAmeria';
                        if ($this->save_card && is_user_logged_in() && isset($body->CardHolderID) && $body->CardHolderID) {
                            add_user_meta(get_current_user_id(), 'recurringChargeAmeria' . $order->get_id(), ['bindingId' => $body->CardHolderID]);

                            $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfoAmeria');
                            $findCard = false;
                            if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                                foreach ($bindingInfo as $key => $bindingItem) {
                                    if ($bindingItem['cardAuthInfo']['expiration'] == substr($body->ExpDate, 0, 4) . '/' . substr($body->ExpDate, 4) && $bindingItem['cardAuthInfo']['panEnd'] == substr($body->CardNumber, -4)) {
                                        $findCard = true;
                                    }
                                }
                            }
                            if (!$findCard) {
                                $metaArray = array(
                                    'active' => true,
                                    'bindingId' => $body->CardHolderID,
                                    'cardAuthInfo' => [
                                        'expiration' => substr($body->ExpDate, 0, 4) . '/' . substr($body->ExpDate, 4),
                                        'cardholderName' => $body->ClientName,
                                        'pan' => substr($body->CardNumber, 0, 4) . str_repeat('*', strlen($body->CardNumber) - 8) . substr($body->CardNumber, -4),
                                        'panEnd' => substr($body->CardNumber, -4),
                                        'type' => $this->getCardType($body->CardNumber)
                                    ],
                                );
                                $user_id = get_current_user_id();
                                add_user_meta($user_id, $user_meta_key, $metaArray);
                            }
                        }
                        update_post_meta(sanitize_text_field($_GET['opaque']), 'PaymentID', sanitize_text_field($_GET['paymentID']));
                        $order->update_status($this->successOrderStatus);
                        wp_redirect($order->get_checkout_order_received_url());
                        exit;
                    } else {
                        if (isset($this->bankErrorCodesByDiffLanguage[$this->language][$body->ResponseCode])) {
                            $errMessage = $this->bankErrorCodesByDiffLanguage[$this->language][$body->ResponseCode];
                            $order->add_order_note($errMessage, true);
                            $order->update_status('failed');
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Ameriabank  callback: #' . sanitize_text_field($_GET['opaque']) . '. Error: ' . $this->bankErrorCodesByDiffLanguage[$this->language][$body->ResponseCode]);
                            update_post_meta(sanitize_text_field($_GET['opaque']), 'FailedMessageAmeria', $this->bankErrorCodesByDiffLanguage[$this->language][$body->ResponseCode]);
                        } else {
                            $order->update_status('failed');
                            update_post_meta(sanitize_text_field($_GET['opaque']), 'FailedMessageAmeria', __('Please try again later.', 'payment-gateway-for-ameriabank'));
                        }
                        wp_redirect($this->get_return_url($order));
                        exit;
                    }
                } else {
                    $order->update_status('failed');
                    wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                    exit;
                }

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
        }
    }

}
