<?php

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'hkdigital_init_idbank_gateway_class');
if (!function_exists('hkdigital_init_idbank_gateway_class')) {
    function hkdigital_init_idbank_gateway_class()
    {
        global $pluginBaseNameIdBank;


        if (class_exists('WC_Payment_Gateway')) {
            class HKDigital_Idbank_Arca_Gateway extends WC_Payment_Gateway
            {
                // API and plugin info
                private $api_url;
                private $ownerSiteUrl;
                private $pluginDirUrl;

                // Currency handling
                private $currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978'];
                private $currency_code = '051';

                // WooCommerce gateway properties (declare to avoid dynamic property deprecations)
                public $id;
                public $icon;
                public $has_fields;
                public $method_title;
                public $method_description;
                public $supports = [];

                // Settings/options
                public $title;
                public $description;
                public $language_payment_idbank;
                public $enabled;
                public $hkd_arca_checkout_id;
                public $language;
                public $secondTypePayment;
                public $empty_card;
                public $testmode;
                public $user_name;
                public $password;
                public $binding_user_name;
                public $binding_password;
                public $debug;
                public $save_card;
                public $save_card_button_text;
                public $save_card_header;
                public $save_card_use_new_card;
                public $multi_currency;
                public $successOrderStatus;
                public $bankErrorCodesByDiffLanguage;

                // Logger
                public $log;

                /**
                 * HKDigital_Idbank_Arca_Gateway constructor.
                 */
                public function __construct()
                {
                    global $woocommerce;
                    global $bankErrorCodesByDiffLanguageIdBank;
                    global $apiUrlIdBank;
                    global $pluginDirUrlIdBank;

                    $this->ownerSiteUrl = $apiUrlIdBank;
                    $this->pluginDirUrl = $pluginDirUrlIdBank;

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

                    $this->id = 'hkd_idbank';
                    $this->icon = $this->pluginDirUrl . 'assets/images/logo_idbank.png';
                    $this->has_fields = false;
                    $this->method_title = 'Payment Gateway for Idbank';
                    $this->method_description = 'Pay with  Idbank payment system. Please note that the payment will be made in Armenian Dram.';
                   
                    if (is_admin()) {
                        if (isset($_POST['hkd_idbank_checkout_id']) && sanitize_text_field($_POST['hkd_idbank_checkout_id']) != '') {
                            update_option('hkd_idbank_checkout_id', sanitize_text_field($_POST['hkd_idbank_checkout_id']));
                            // Use plain strings here to avoid triggering translations before the init hook
                            $this->update_option('title', 'Pay via credit card');
                            $this->update_option('description', 'Purchase by credit card. Please, note that purchase is going to be made in Armenian drams.');
                            $this->update_option('save_card_button_text', 'Add a credit card');
                            $this->update_option('save_card_header', 'Purchase safely by using your saved credit card');
                            $this->update_option('save_card_use_new_card', 'Use a new credit card');
                        }
                    }

                    // Initialize form fields immediately so settings (title/description/etc.) are available in admin and checkout.
                    // We previously deferred this to `init` to avoid early i18n loading, but the JIT notice source has been addressed elsewhere.
                    // Initializing here restores proper settings UI without triggering early translation loading.
                    $this->init_form_fields();
                    $this->init_settings();
                    $this->title = $this->get_option('title');
                    $this->description = $this->get_option('description');

                    $language = get_locale();
                    $this->language_payment_idbank = ($language === 'hy' || $language === 'ru_RU' || $language === 'en_US') ? $language : 'hy';
                    $this->enabled = $this->get_option('enabled');
                    $this->hkd_arca_checkout_id = get_option('hkd_idbank_checkout_id');
                    $this->language = $this->get_option('language');
                    $this->secondTypePayment = 'yes' === $this->get_option('secondTypePayment');
                    $this->empty_card = 'yes' === $this->get_option('empty_card');
                    $this->testmode = 'yes' === $this->get_option('testmode');
                    $this->user_name = $this->testmode ? $this->get_option('test_user_name') : $this->get_option('live_user_name');
                    $this->password = $this->testmode ? $this->get_option('test_password') : $this->get_option('live_password');
                    $this->binding_user_name = $this->get_option('binding_user_name');
                    $this->binding_password = $this->get_option('binding_password');
                    $this->debug = 'yes' === $this->get_option('debug');
                    $this->save_card = 'yes' === $this->get_option('save_card');
                    // Use plain strings here to avoid triggering translations too early; labels are translated in form fields
                    $this->save_card_button_text = !empty($this->get_option('save_card_button_text')) ? $this->get_option('save_card_button_text') : 'Add a credit card';
                    $this->save_card_header = !empty($this->get_option('save_card_header')) ? $this->get_option('save_card_header') : 'Purchase safely by using your saved credit card';
                    $this->save_card_use_new_card = !empty($this->get_option('save_card_use_new_card')) ? $this->get_option('save_card_use_new_card') : 'Use a new credit card';
                    $this->multi_currency = 'yes' === $this->get_option('multi_currency');
                    $this->api_url = !$this->testmode ? 'https://ipay.arca.am/payment/rest' : 'https://ipaytest.arca.am:8445/payment/rest';

                    $this->successOrderStatus = $this->get_option('successOrderStatus');

                    if ($this->debug) {
                        if (function_exists('wc_get_logger')) {
                            $this->log = wc_get_logger();
                        } elseif (isset($woocommerce) && is_object($woocommerce) && method_exists($woocommerce, 'logger')) {
                            $this->log = $woocommerce->logger();
                        } else {
                            $this->log = class_exists('WC_Logger') ? new WC_Logger() : null;
                        }
                    }
                    if ($this->multi_currency) {
                        $this->currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978'];
                        $wooCurrency = get_woocommerce_currency();
                        if (isset($this->currencies[$wooCurrency])) {
                            $this->currency_code = $this->currencies[$wooCurrency];
                        } else {
                            // Fallback to AMD (051) if store currency is not mapped
                            $this->currency_code = '051';
                            if ($this->debug && $this->log) {
                                $this->log->warning('Unsupported currency for IDBank mapping: ' . $wooCurrency . '. Falling back to AMD (051).', ['source' => $this->id]);
                            }
                        }
                    }

                    // process the Change Payment "transaction"
                    add_action('woocommerce_scheduled_subscription_payment', array($this, 'process_subscription_payment'), 10, 3);


                    /**
                     * Success callback url for IDBANK payment api
                     */
                    add_action('woocommerce_api_delete_binding_idbank', array($this, 'delete_binding_idbank'));

                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                    /**
                     * Success callback url for idbank payment api
                     */
                    add_action('woocommerce_api_idbank_successful', array($this, 'webhook_idbank_successful'));

                    /**
                     * Failed callback url for idbank payment api
                     */
                    add_action('woocommerce_api_idbank_failed', array($this, 'webhook_idbank_failed'));
                    /**
                     * styles and fonts for idbank payment plugin
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

                    if (is_admin()) {
                        $this->checkActivation();
                    }
                    
                    if ($this->secondTypePayment) {
                        add_filter('woocommerce_admin_order_actions', array($this, 'add_custom_order_status_actions_button'), 100, 2);
                        add_action('admin_head', array($this, 'add_custom_order_status_actions_button_css'));
                    }

                    add_action('woocommerce_order_status_changed', array($this, 'statusChangeHook'), 10, 3);
                    add_action('woocommerce_order_edit_status', array($this, 'statusChangeHookSubscription'), 10, 2);

                    $this->bankErrorCodesByDiffLanguage = $bankErrorCodesByDiffLanguageIdBank;

                    // WP cron
                    add_action('cronCheckOrderIDBank', array($this, 'cronCheckOrderIDBank'));
                }

                public function cronCheckOrderIDBank()
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
                        AND pm.meta_value = 'hkd_idbank'
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
                    try {
                        $today = date('Y-m-d');
                        if (get_option('hkd_check_activation_idbank') !== $today) {
                            $payload = ['domain' => sanitize_text_field($_SERVER['SERVER_NAME']), 'enabled' => $this->enabled];
                            wp_remote_post($this->ownerSiteUrl . 'bank/id_bank/checkStatusPluginActivation', array(
                                'sslverify' => false,
                                'method' => 'POST',
                                'headers' => array('Accept' => 'application/json'),
                                'body' => $payload
                            ));
                            update_option('hkd_check_activation_idbank', $today);
                        }
                    } catch (Exception $e) {

                    }
                }

                public function statusChangeHookSubscription($order_id, $new_status)
                {
                    $order = wc_get_order($order_id);
                    if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_idbank' && $this->secondTypePayment) {
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
                    if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_idbank') {
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
                    if (isset($this->getPaymentGatewayByOrder($order)->id) && $this->getPaymentGatewayByOrder($order)->id == 'hkd_idbank') {
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
                    /* $reason */
                    $order = wc_get_order($order_id);
                    $requestParams = [
                        'amount' => $amount,
                        'currency' => $this->currency_code,
                        'orderNumber' => $order_id,
                        'password' => $this->password,
                        'userName' => $this->user_name,
                        'language' => $this->language
                    ];

                    $response = $this->postRequestPayment($this->api_url . '/refund.do', $requestParams);
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            $order->update_status('refunded');
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
                    $nonce = wp_create_nonce('hkd_idbank_delete_binding');
                    $html = '<div id="hkdigital_binding_info_idbank" data-delete-binding-nonce="' . esc_attr( $nonce ) . '" data-api-url="' . esc_url( home_url('/wc-api/delete_binding_idbank') ) . '">';
                    $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_idbank');
                    if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                        $html .= '<h4 class="card_payment_title card_page">' . __('Your card list', 'payment-gateway-for-idbank') . '</h4>
                              <h2 class="card_payment_second card_page">' . __('You can Delete Cards', 'payment-gateway-for-idbank') . '</h2>
                                <ul class="card_payment_list">';
                        foreach ($bindingInfo as $key => $bindingItem) {
                            $holder = isset($bindingItem['cardAuthInfo']['cardholderName']) ? $bindingItem['cardAuthInfo']['cardholderName'] : '';
                            $panEnd = isset($bindingItem['cardAuthInfo']['panEnd']) ? $bindingItem['cardAuthInfo']['panEnd'] : '';
                            $exp    = isset($bindingItem['cardAuthInfo']['expiration']) ? $bindingItem['cardAuthInfo']['expiration'] : '';
                            $type   = isset($bindingItem['cardAuthInfo']['type']) ? $bindingItem['cardAuthInfo']['type'] : '';
                            $bindingId = isset($bindingItem['bindingId']) ? $bindingItem['bindingId'] : '';

                            $label = sprintf(
                                /* translators: 1: Cardholder name, 2: last digits of PAN, 3: expiration date */
                                __('%1$s | •••• %2$s (expires %3$s)', 'payment-gateway-for-idbank'),
                                $holder,
                                $panEnd,
                                $exp
                            );

                            $html .= '<li class="card_item">'
                                   . '<span class="card_subTitile">' . esc_html($label) . '</span>'
                                   . ( $type !== '' ? '<img src="' . esc_url($this->pluginDirUrl . 'assets/images/card_types/' . $type . '.png') . '" class="card_logo big_img" alt="' . esc_attr__('Card type', 'payment-gateway-for-idbank') . '"/>' : '' )
                                   . '<svg  class="svg-trash-idbank" data-id="' . esc_attr($bindingId) . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">'
                                   . '   <path fill="#ed2353" d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z"></path>'
                                   . '</svg>'
                                   . '</li>';
                        }
                        $html .= '</ul>
                            </div>';
                    } else {
                        $html .= '<div class="check-box noselect">
                                    <span>
                                      ' . __('No Saved Cards', 'payment-gateway-for-idbank') . '
                                    </span>
                                 </div>';
                    }
                    echo $html;
                }

                public function addCardLinkMenu($items)
                {
                    $items['cards'] = __('Credit Cards', 'payment-gateway-for-idbank');
                    return $items;
                }
                
                public function delete_binding_idbank()
                {
                    // Allow only logged-in users and POST requests. Nonce is supported but optional for backward compatibility.
                    if (!is_user_logged_in()) {
                        wp_send_json_error(['message' => 'Unauthorized'], 401);
                    }
                    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                        wp_send_json_error(['message' => 'Method Not Allowed'], 405);
                    }

                    $bindingIdForDelete = isset($_REQUEST['bindingId']) ? sanitize_text_field(wp_unslash($_REQUEST['bindingId'])) : '';
                    if ($bindingIdForDelete === '') {
                        wp_send_json_error(['message' => 'Missing bindingId'], 400);
                    }

                    // Require nonce for CSRF protection (now enforced; front-end sends it via data attribute)
                    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
                    if (!$nonce || !wp_verify_nonce($nonce, 'hkd_idbank_delete_binding')) {
                        wp_send_json_error(['message' => 'Invalid or missing nonce'], 403);
                    }

                    try {
                        $user_id = get_current_user_id();

                        // Support both legacy and current meta keys for compatibility
                        $meta_keys = ['bindingInfo_idbank', 'bindingInfo'];
                        $found_key = null;
                        $bindingInfo = [];
                        foreach ($meta_keys as $key_name) {
                            $maybe = get_user_meta($user_id, $key_name);
                            if (is_array($maybe) && count($maybe) > 0) {
                                $bindingInfo = $maybe;
                                $found_key = $key_name;
                                break;
                            }
                        }

                        if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                            foreach ($bindingInfo as $key => $item) {
                                if (isset($item['bindingId']) && (string)$item['bindingId'] === (string)$bindingIdForDelete) {
                                    unset($bindingInfo[$key]);
                                }
                            }
                            if ($found_key) {
                                delete_user_meta($user_id, $found_key);
                                if (count($bindingInfo) > 0) {
                                    add_user_meta($user_id, $found_key, array_values($bindingInfo));
                                }
                            }

                            // Call unbind on bank API
                            $payload = [
                                'userName' => $this->user_name,
                                'password' => $this->password,
                                'bindingId' => $bindingIdForDelete,
                            ];
                            $this->postRequestPayment($this->api_url . '/unBindCard.do', $payload);

                            wp_send_json_success(['status' => true]);
                        } else {
                            wp_send_json_error(['status' => false, 'message' => 'Binding not found'], 404);
                        }
                    } catch (Exception $e) {
                        if ($this->debug && $this->log) {
                            $this->log->error('IDBank delete_binding exception: ' . $e->getMessage(), ['source' => $this->id]);
                        }
                        wp_send_json_error(['status' => false, 'message' => 'Unexpected error'], 500);
                    }
                }

                public function payment_fields()
                {
                    $plugin_url = $this->pluginDirUrl;
                    wp_enqueue_style('hkd-front-style-idbank', $plugin_url . "assets/css/cards.css");
                    wp_enqueue_script('hkd-front-js-idbank', $plugin_url . "assets/js/cards.js");
                    $description = $this->get_description();
                    if ($description) {
                        echo wpautop(wptexturize($description));  // @codingStandardsIgnoreLine.
                    }
                    if (is_user_logged_in() && $this->save_card && $this->binding_user_name != '' && $this->binding_password != '') {
                        $nonce = wp_create_nonce('hkd_idbank_delete_binding');
                        $html = '<div id="hkdigital_binding_info_idbank" data-delete-binding-nonce="' . esc_attr( $nonce ) . '" data-api-url="' . esc_url( home_url('/wc-api/delete_binding_idbank') ) . '">';
                        $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_idbank');
                        if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                            $html .= '<h4 class="card_payment_title">' . esc_html($this->save_card_header) . '</h4>'
                                . '<ul class="card_payment_list">';

                            foreach ($bindingInfo as $key => $bindingItem) {
                                $bindingId = isset($bindingItem['bindingId']) ? $bindingItem['bindingId'] : '';
                                $holder    = isset($bindingItem['cardAuthInfo']['cardholderName']) ? $bindingItem['cardAuthInfo']['cardholderName'] : '';
                                $panEnd    = isset($bindingItem['cardAuthInfo']['panEnd']) ? $bindingItem['cardAuthInfo']['panEnd'] : '';
                                $exp       = isset($bindingItem['cardAuthInfo']['expiration']) ? $bindingItem['cardAuthInfo']['expiration'] : '';
                                $type      = isset($bindingItem['cardAuthInfo']['type']) ? $bindingItem['cardAuthInfo']['type'] : '';

                                $label = sprintf(
                                    /* translators: 1: Cardholder name, 2: last digits of PAN, 3: expiration date */
                                    __('%1$s | •••• %2$s (expires %3$s)', 'payment-gateway-for-idbank'),
                                    $holder,
                                    $panEnd,
                                    $exp
                                );

                                $html .= '<li class="card_item">'
                                      .  '<input id="' . esc_attr($bindingId) . '" name="bindingType" value="' . esc_attr($bindingId) . '" type="radio" class="input-radio">'
                                      .  '<label for="' . esc_attr($bindingId) . '">' . esc_html($label) . '</label>'
                                      .  ( $type !== '' ? '<img src="' . esc_url($this->pluginDirUrl . 'assets/images/card_types/' . $type . '.png') . '" class="card_logo" alt="' . esc_attr__('Card type', 'payment-gateway-for-idbank') . '">' : '' )
                                      .  '<svg class="svg-trash-idbank" data-id="' . esc_attr($bindingId) . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">'
                                      .  '  <path fill="#ed2353" d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z"></path>'
                                      .  '</svg>'
                                      .  '</li>';
                            }

                            $html .= '<li class="card_item">'
                                  .  '<input id="payment_newCard" type="radio" class="input-radio" name="bindingType" value="saveCard">'
                                  .  '<label for="payment_newCard">' . esc_html($this->save_card_use_new_card) . '</label>'
                                  .  '</li>';

                            $html .= '</ul>'
                                  .  '</div>';
                        } else {
                            $html .= '<div class="check-box noselect">'
                                  .  '<input type="checkbox" id="saveCard_idbank" name="bindingType" value="saveCard"/>'
                                  .  '<label for="saveCard_idbank"> <span class="check"><svg class="svg-check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">'
                                  .  '<path fill="#ffffff" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg> </span>'
                                  .  esc_html($this->save_card_button_text)
                                  .  '</label>'
                                  .  '</div>';
                        }
                        echo $html;
                    }
                }


                public function init_form_fields()
                {
                    // Keep a generic description to avoid calling deprecated WooCommerce functions
                    $debug = __('Enable logging. View logs in WooCommerce → Status → Logs.', 'payment-gateway-for-idbank');

                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __('Enable/Disable', 'payment-gateway-for-idbank'),
                            'label' => __('Enable payment gateway', 'payment-gateway-for-idbank'),
                            'type' => 'checkbox',
                            'description' => '',
                            'default' => 'no'
                        ),
                        'title' => array(
                            'title' => __('Title', 'payment-gateway-for-idbank'),
                            'type' => 'text',
                            'description' => __('User (website visitor) sees this title on order registry page as a title for purchase option.', 'payment-gateway-for-idbank'),
                            'default' => __('Pay via credit card', 'payment-gateway-for-idbank'),
                            'desc_tip' => true,
                            'placeholder' => __('Type the title', 'payment-gateway-for-idbank')
                        ),
                        'description' => array(
                            'title' => __('Description', 'payment-gateway-for-idbank'),
                            'type' => 'textarea',
                            'description' => __('User (website visitor) sees this description on order registry page in bank purchase option.', 'payment-gateway-for-idbank'),
                            'default' => __('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'payment-gateway-for-idbank'),
                            'desc_tip' => true,
                            'placeholder' => __('Type the description', 'payment-gateway-for-idbank')
                        ),
                        'language' => array(
                            'title' => __('Language', 'payment-gateway-for-idbank'),
                            'type' => 'select',
                            'options' => [
                                'hy' => 'Հայերեն',
                                'ru' => 'Русский',
                                'en' => 'English',
                            ],
                            'description' => __('Here interface language of bank purchase can be regulated', 'payment-gateway-for-idbank'),
                            'default' => 'hy',
                            'desc_tip' => true,
                        ),
                        'successOrderStatus' => array(
                            'title' => __('Success Order Status', 'payment-gateway-for-idbank'),
                            'type' => 'select',
                            'options' => [
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                            ],
                            'description' => __('Here you can select the status of confirmed payment orders.', 'payment-gateway-for-idbank'),
                            'default' => 'processing',
                            'desc_tip' => true,
                        ),
                        'multi_currency' => array(
                            'title' => __('Multi-Currency', 'payment-gateway-for-idbank'),
                            'label' => __('Enable Multi-Currency', 'payment-gateway-for-idbank'),
                            'type' => 'checkbox',
                            'description' => __('This action, if permitted by the bank, enables to purchase by multiple currencies', 'payment-gateway-for-idbank'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'debug' => array(
                            'title' => __('Debug Log', 'payment-gateway-for-idbank'),
                            'type' => 'checkbox',
                            'label' => __('Enable debug mode', 'payment-gateway-for-idbank'),
                            'default' => 'no',
                            'description' => $debug,
                        ),
                        'testmode' => array(
                            'title' => __('Test mode', 'payment-gateway-for-idbank'),
                            'label' => __('Enable test Mode', 'payment-gateway-for-idbank'),
                            'type' => 'checkbox',
                            'description' => __('To test the testing version login and password provided by the bank should be typed', 'payment-gateway-for-idbank'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'test_user_name' => array(
                            'title' => __('Test User Name', 'payment-gateway-for-idbank'),
                            'type' => 'text',
                            'class' => 'testModeInfoIdBank hiddenValueIdBank'
                        ),
                        'test_password' => array(
                            'title' => __('Test Password', 'payment-gateway-for-idbank'),
                            'type' => 'password',
                            'placeholder' => __('Enter password', 'payment-gateway-for-idbank'),
                            'class' => 'testModeInfoIdBank hiddenValueIdBank'
                        ),
                        'secondTypePayment' => array(
                            'title' => __('Two-stage Payment', 'payment-gateway-for-idbank'),
                            'label' => __('Enable payment confirmation function', 'payment-gateway-for-idbank'),
                            'type' => 'checkbox',
                            'description' => __('two-stage: when the payment amount is first blocked on the buyer’s account and then at the second stage is withdrawn from the account', 'payment-gateway-for-idbank'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'save_card' => array(
                            'title' => __('Save Card Admin', 'payment-gateway-for-idbank'),
                            'type' => 'checkbox',
                            'label' => __('Enable "Save Card" function', 'payment-gateway-for-idbank'),
                            'default' => 'no',
                            'desc_tip' => true,
                            'description' => __('Enable Save Card', 'payment-gateway-for-idbank'),
                        ),
                        'save_card_button_text' => array(
                            'title' => __('New binding card text', 'payment-gateway-for-idbank'),
                            'placeholder' => __('Type the save card checkbox text', 'payment-gateway-for-idbank'),
                            'type' => 'text',
                            'default' => __('Add a credit card', 'payment-gateway-for-idbank'),
                            'desc_tip' => true,
                            'description' => ' ',
                            'class' => 'saveCardInfoIdBank hiddenValueIdBank',
                        ),
                        'save_card_header' => array(
                            'title' => __('Save card description text', 'payment-gateway-for-idbank'),
                            'placeholder' => __('Type the save card description text', 'payment-gateway-for-idbank'),
                            'type' => 'text',
                            'default' => __('Purchase safely by using your saved credit card', 'payment-gateway-for-idbank'),
                            'desc_tip' => true,
                            'description' => ' ',
                            'class' => 'saveCardInfoIdBank hiddenValueIdBank',
                        ),
                        'save_card_use_new_card' => array(
                            'title' => __('Use new card text', 'payment-gateway-for-idbank'),
                            'placeholder' => __('Type the use new card text', 'payment-gateway-for-idbank'),
                            'type' => 'text',
                            'default' => __('Use a new credit card', 'payment-gateway-for-idbank'),
                            'desc_tip' => true,
                            'description' => ' ',
                            'class' => 'saveCardInfoIdBank hiddenValueIdBank'
                        ),
                        'binding_user_name' => array(
                            'title' => __('Binding User Name', 'payment-gateway-for-idbank'),
                            'type' => 'text',
                            'class' => 'saveCardInfoIdBank hiddenValueIdBank',
                        ),
                        'binding_password' => array(
                            'title' => __('Binding Password', 'payment-gateway-for-idbank'),
                            'type' => 'password',
                            'placeholder' => __('Enter password', 'payment-gateway-for-idbank'),
                            'class' => 'saveCardInfoIdBank hiddenValueIdBank'
                        ),
                        'live_settings' => array(
                            'title' => __('Live Settings', 'payment-gateway-for-idbank'),
                            'type' => 'hidden'
                        ),
                        'live_user_name' => array(
                            'title' => __('User Name', 'payment-gateway-for-idbank'),
                            'type' => 'text',
                            'placeholder' => __('Type the user name', 'payment-gateway-for-idbank')
                        ),
                        'live_password' => array(
                            'title' => __('Password', 'payment-gateway-for-idbank'),
                            'type' => 'password',
                            'placeholder' => __('Type the password', 'payment-gateway-for-idbank')
                        ),
                        'useful_functions' => array(
                            'title' => __('Useful functions', 'payment-gateway-for-idbank'),
                            'type' => 'hidden'
                        ),
                        'empty_card' => array(
                            'title' => __('Cart totals', 'payment-gateway-for-idbank'),
                            'label' => __('Activate shopping cart function', 'payment-gateway-for-idbank'),
                            'type' => 'checkbox',
                            'description' => __('This feature ensures that the contents of the shopping cart are available at the time of order registration if the site buyer decides to change the payment method.', 'payment-gateway-for-idbank'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'links' => array(
                            'title' => __('Links', 'payment-gateway-for-idbank'),
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
                    $requestParams['description'] = 'order number ' . $order_id;
                    $requestParams['returnUrl'] = get_site_url() . '/wc-api/idbank_successful?order=' . $order_id;
                    $requestParams['failUrl'] = get_site_url() . '/wc-api/idbank_failed?order=' . $order_id;
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
                        $response = $this->postRequestPayment($this->api_url . '/paymentOrderBinding.do', $payload);
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            $order->update_status($this->successOrderStatus);
                            $parts = parse_url($body->redirect);
                            parse_str($parts['query'], $query);
                            update_post_meta($order_id, 'PaymentID', $query['orderId']);
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
                            wc_add_notice(__('Please try again.', 'payment-gateway-for-idbank'), 'error');
                            return array('result' => 'success', 'redirect' => get_permalink(get_option('woocommerce_checkout_page_id')));
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
                            wc_add_notice(__('Please try again.', 'payment-gateway-for-idbank'), 'error');
                            return array('result' => 'success', 'redirect' => get_permalink(get_option('woocommerce_checkout_page_id')));
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                        $order->update_status('failed');
                        wc_add_notice(__('Connection error.', 'payment-gateway-for-idbank'), 'error');
                        return array('result' => 'success', 'redirect' => get_permalink(get_option('woocommerce_checkout_page_id')));
                    }
                }

                /**
                 * Function to create the duplicate of the order.
                 *
                 * @param mixed $post
                 * @return int
                 */
                public function duplicate_order($post)
                {

                    $original_order_id = $post->get_id();
                    $original_order = $post;
                    $order_id = $this->create_order();
                    if (is_wp_error($order_id)) {
                        $msg = 'Unable to create order: ' . $order_id->get_error_message();;
                        throw new Exception($msg);
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

                private function create_order()
                {
                    $order = wc_create_order();
                    $order_id = $order->get_id();
                    $_order = new WC_Order($order_id);
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
                    wp_enqueue_script('hkd-idbank-front-admin-js', $plugin_url . "assets/js/admin.js");
                    wp_localize_script('hkd-idbank-front-admin-js', 'myScriptIdBank', array(
                        'pluginsUrl' => $plugin_url,
                    ));
                    wp_enqueue_style('hkd-style', $plugin_url . "assets/css/style.css");
                    wp_enqueue_style('hkd-style-awesome', $plugin_url . "assets/css/font_awesome.css");
                }

                public function process_subscription_payment($order_id)
                {
                    $order = wc_get_order($order_id);
                    if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_idbank') {
                        $bindingInfo = get_user_meta($order->get_user_id(), 'recurringChargeIDBANK' . (int)$order->get_parent_id());

                        $amount = floatval($order->get_total()) * 100;
                        $requestParams = [
                            'amount' => $amount,
                            'currency' => $this->currency_code,
                            'orderNumber' => rand(10000000, 99999999),
                            'language' => $this->language,
                            'password' => $this->binding_password,
                            'userName' => $this->binding_user_name,
                            'description' => 'order number' . $order_id,
                            'returnUrl' => get_site_url() . '/wc-api/idbank_successful?order=' . $order_id,
                            'failUrl' => get_site_url() . '/wc-api/idbank_failed?order=' . $order_id,
                            'clientId' => get_current_user_id(),
                            'jsonParams' => '{"FORCE_3DS2":"true"}'
                        ];

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
                    <div class="wrap-idbank wrap-content wrap-content-hkd"
                         id="pluginMainWrap">
                        <h4><?= __('ONLINE PAYMENT GATEWAY', 'payment-gateway-for-idbank') ?></h4>
                        <h3><?= __('ID BANK', 'payment-gateway-for-idbank') ?></h3>
                        <?php if (!$validate['success']): ?>
                            <div class="w-100">
                                <p class="mb-10"><?php echo __('Before using the plugin, please contact the bank to receive respective regulations.', 'payment-gateway-for-idbank'); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($validate['success']) { ?>
                            <table class="form-table">
                                <?php
                                $this->generate_settings_html()
                                ?>
                                <tr valign="top">
                                    <th scope="row">Idbank callback Url Success</th>
                                    <td><?= get_site_url() ?>/wc-api/idbank_successful</td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Idbank callback Url Failed</th>
                                    <td><?= get_site_url() ?>/wc-api/idbank_failed</td>
                                </tr>
                            </table>
                        <?php } else { ?>
                            <div class="step-inner-content mt-40">
                                <h2 class="verification-label text-center">
                                    <?php echo __('IDENTIFICATION', 'payment-gateway-for-idbank'); ?>
                                </h2>
                                <p class="mt-5 font-size-14 text-center">
                                    <?php echo __('Contact us at the specified phone number or e-mail address for authentication.', 'payment-gateway-for-idbank'); ?>
                                </p>
                                <div class="form-inner-area">
                                    <label class="input-label-verification"
                                           for="hkd_idbank_checkout_id"><?php echo __('Identification password', 'payment-gateway-for-idbank'); ?></label>
                                    <input type="text" name="hkd_idbank_checkout_id"
                                           id="hkd_idbank_checkout_id" class="form-control "
                                           value="<?php echo esc_html($this->hkd_arca_checkout_id); ?>"
                                           minlength="2"
                                           placeholder="<?php echo __('Example Idbankgayudcsu14', 'payment-gateway-for-idbank') ?>">
                                </div>
                                <div class="blue terms_div_idbank">
                                    <iframe src="<?php echo esc_html($this->pluginDirUrl) ?>terms/terms.html"
                                            height="100%" width="100%" title="Terms Iframe"></iframe>
                                </div>
                                <div class="text-center accept_terms_div_idbank mb-10">
                                    <label class="checkbox">
                                        <input type="checkbox"
                                               class="accept_terms"
                                               name="terms" id="terms_idbank">
                                        <span><?php echo __('I have read and agree to the Plugin', 'payment-gateway-for-idbank'); ?> <a
                                                    href="javascript:"
                                                    id="toggle-terms_div_idbank"><b>  <?php echo __('Terms & Conditions', 'payment-gateway-for-idbank'); ?></b></a></span>&nbsp;<abbr
                                                class="required" title="required">*</abbr>
                                        <br>
                                        <span id="terms-error_idbank" class="error"></span>
                                    </label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="wrap-idbank wrap-content wrap-content-hkd"
                         id="paymentInfoBlock">
                        <div class="wrap-content-hkd-400px">
                            <img src="<?= $this->pluginDirUrl ?>assets/images/idbank.png">
                            <div class="wrap-content-hkd-info">
                                <h2><?php echo __('Payment system', 'payment-gateway-for-idbank'); ?></h2>

                                <div class="wrap-content-info">
                                    <div class="phone-icon icon"><i class="fa fa-phone"></i></div>
                                    <p><a href="tel:+37410593333">010 59 33 33</a></p>
                                    <div class="mail-icon icon"><i class="fa fa-envelope"></i></div>
                                    <p><a href="mailto:info@idbank.am">info@idbank.am</a></p>
                                    <div class="mail-icon icon"><i class="fa fa-link"></i></div>
                                    <p><a target="_blank" href="https://idbank.am">idbank.am</a></p>
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
                    <?php
                }

                public function validateFields()
                {
                    $go = get_option('hkdump');
                    $wooCurrency = get_woocommerce_currency();

                    if (!isset($this->currencies[$wooCurrency])) {
                        $this->update_option('enabled', 'no');
                        return ['message' => 'Դուք այժմ օգտագործում եք ' . $wooCurrency . ' արժույթը, այն չի սպասարկվում բանկի կողմից։
                                          Հասանելի արժույթներն են ՝  ' . implode(', ', array_keys($this->currencies)), 'success' => false, 'err_msg' => 'currency_error'];
                    }
                    if ($this->hkd_arca_checkout_id == '') {
                        if (!empty($go)) {
                            update_option('hkdump', 'no');
                        } else {
                            add_option('hkdump', 'no');
                        };
                        $this->update_option('enabled', 'no');
                        return ['message' => __('You must fill token', 'payment-gateway-for-idbank'), 'success' => false];
                    }
                    $ch = curl_init($this->ownerSiteUrl .
                        'bank/id_bank/checkApiConnection');
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
                            'bank/id_bank/checkActivation', ['headers' => array('Accept' => 'application/json'), 'sslverify' => false, 'body' => ['domain' => $_SERVER['SERVER_NAME'], 'checkoutId' => $this->hkd_arca_checkout_id, 'lang' => $this->language_payment_idbank]]);
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
                            return ['message' => __('Token not valid', 'payment-gateway-for-idbank'), 'success' => false];
                        }
                    } else {
                        if (get_option('hkdump') == 'yes') {
                            return ['message' => '', 'status' => 'success'];
                        } else {
                            return ['message' => __('You must fill token', 'payment-gateway-for-idbank'), 'success' => false];
                        }
                    }

                }

                public function webhook_idbank_successful()
                {
                    global $woocommerce;
                    if ($this->empty_card) {
                        $woocommerce->cart->empty_cart();
                    }
                    if (isset($_REQUEST['order']) && $_REQUEST['order'] !== '') {
                        $isBindingOrder = get_post_meta($_REQUEST['order'], 'isBindingOrder', true);
                        $requestParams = [
                            'orderId' => sanitize_text_field($_REQUEST['orderId']),
                            'language' => $this->language,
                        ];
                        $url = $this->api_url . '/getOrderStatusExtended.do';
                        if ($isBindingOrder) {
                            $requestParams['userName'] = $this->binding_user_name;
                            $requestParams['password'] = $this->binding_password;
                        } else {
                            $requestParams['userName'] = $this->user_name;
                            $requestParams['password'] = $this->password;
                        }
                        $response = $this->postRequestPayment($url, $requestParams);
                        $body = json_decode($response['body']);
                        $user_meta_key = 'bindingInfo_idbank';
                        if (isset($body->bindingInfo->bindingId)) {
                            add_user_meta(get_current_user_id(), 'recurringChargeIDBANK' . $_REQUEST['order'], ['bindingId' => $body->bindingInfo->bindingId]);
                        }
                        if (isset($body->orderStatus) && ($body->orderStatus == 2 || $body->orderStatus == 1)) {
                            if ($this->save_card && $this->binding_user_name != '' && $this->binding_password != '' && is_user_logged_in() && isset($body->bindingInfo) && isset($body->cardAuthInfo)) {
                                $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_idbank_bank');
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
                            update_post_meta(sanitize_text_field($_REQUEST['order']), 'PaymentID', sanitize_text_field($_REQUEST['orderId']));
                            $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                            $order->update_status($this->successOrderStatus);
                            if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_REQUEST['order']) . ' successfully added to processing');
                            echo $this->get_return_url($order);
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                            $order->update_status('failed');
                            $order->add_order_note($body->errorMessage, true);
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with  Arca callback: #' . sanitize_text_field($_GET['order']));
                        }
                    }

                    if (isset($_REQUEST['orderId']) && sanitize_text_field($_REQUEST['orderId']) !== '') {
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
                            if ($body->errorCode == 0) {
                                if (isset($body->orderStatus) && $body->orderStatus == 2) {
                                    $order = wc_get_order($body->OrderNumber);
                                    $order->update_status($this->successOrderStatus);
                                    if ($this->debug) $this->log->add($this->id, 'Order #' . $body->OrderNumber . ' successfully added to processing.');
                                    wp_redirect($this->get_return_url($order));
                                    exit;
                                }
                            } else {
                                if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_REQUEST['orderId']) . '. Error: ' . $body->errorMessage);
                            }
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_REQUEST['orderId']));
                        }
                    }

                    wc_add_notice(__('Please try again later.', 'payment-gateway-for-idbank'), 'error');
                    wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                    exit;
                }

                public function webhook_idbank_failed()
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
                                update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIDBank', $this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse]);
                            } else {
                                $order->update_status('failed');
                                update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageIDBank', __('Please try again later.', 'payment-gateway-for-idbank'));
                            }
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_GET['orderId']) . '. Error: ' . $body->errorMessage);
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            $order->update_status('failed');
                            wc_add_notice(__('Please try again later.', 'payment-gateway-for-idbank'), 'error');
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