<?php

add_action('plugins_loaded', 'hkd_init_idram_gateway_class');
function hkd_init_idram_gateway_class()
{
    global $pluginBaseNameIdram;
    load_plugin_textdomain('hk-idram-payment-gateway', false, $pluginBaseNameIdram . '/languages/');

    if (class_exists('WC_Payment_Gateway')) {
        class WC_HKD_Idram_Gateway extends WC_Payment_Gateway
        {
            private $ownerSiteUrl;
            private $pluginDirUrl;
            private $currencies = [
                'AMD' => '051',
            ];

            /**
             * WC_HKD_Idram_Gateway constructor.
             */
            public function __construct()
            {
                global $woocommerce;
                global $apiUrlIdram;
                global $pluginDirUrlIdram;

                $this->ownerSiteUrl = $apiUrlIdram;
                $this->pluginDirUrl = $pluginDirUrlIdram;
                
                $this->id = 'hk-idram-payment-gateway';
                $this->has_fields = false;
                $this->method_title = 'Payment Gateway for Idram';
                $this->method_description = 'Pay with Idram payment system. Please note that the payment will be made in Armenian Dram.';
                if (isset($_POST['hkd_idram_checkout_id'])) {
                    update_option('hkd_idram_checkout_id', sanitize_text_field($_POST['hkd_idram_checkout_id']));
                    $this->update_option('title', __('Pay via credit card', 'hk-idram-payment-gateway'));
                    $this->update_option('description', __('Purchase by credit card. Please, note that purchase is going to be made by Armenian drams. ', 'hk-idram-payment-gateway'));
                }
                $this->init_form_fields();
                $this->init_settings();
                $this->title = sanitize_text_field($this->get_option('title'));
                $this->mode = $this->get_option('mode');
                $this->enabledRocketLine = 'yes' === $this->get_option('enabledRocketLine');
                $this->tooltipType = $this->get_option('tooltipType');

                if($this->isMobile() && $this->enabledRocketLine){
                    if ($this->mode === 'white'){
                        $this->icon =  ($this->tooltipType === 'withZero') ?  $this->pluginDirUrl . 'assets/images/mobile/idram_rocket_0_checkout_white_mobile.png' :  $this->pluginDirUrl . 'assets/images/mobile/idram_rocket_checkout_white_mobile.png' ;
                    }else{
                        $this->icon =  ($this->tooltipType === 'withZero') ?  $this->pluginDirUrl . 'assets/images/mobile/idram_rocket_0_checkout_dark_mobile.png' :  $this->pluginDirUrl . 'assets/images/mobile/idram_rocket_checkout_dark_mobile.png' ;
                    }
                }else{
                    $this->icon = ($this->mode === 'white') ? $this->pluginDirUrl . 'assets/images/idram_white.png' :  $this->pluginDirUrl . 'assets/images/idram_dark.png';
                }

                $this->description = sanitize_text_field($this->get_option('description'));
                $this->enabled = $this->get_option('enabled');
                $this->empty_card = 'yes' === $this->get_option('empty_card');
                $this->hkd_idram_checkout_id = sanitize_text_field(get_option('hkd_idram_checkout_id'));
                $this->language = $this->get_option('language');
                $this->successOrderStatus = $this->get_option('successOrderStatus');
                $this->testmode = 'yes' === $this->get_option('testmode');
                $this->edp_rec_account = sanitize_text_field($this->testmode ? $this->get_option('test_edp_rec_account') : $this->get_option('live_edp_rec_account'));
                $this->secret_key = sanitize_text_field($this->testmode ? $this->get_option('test_secret_key') : $this->get_option('live_secret_key'));
                $this->debug = 'yes' === $this->get_option('debug');
                $this->language_payment_idram = !empty($this->get_option('language_payment_idram')) ? $this->get_option('language_payment_idram') : 'hy';

                if ($this->debug) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) $this->log = $woocommerce->logger(); else $this->log = new WC_Logger();
                }

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                /**
                 * Complete callback url for idram api
                 */
                add_action('woocommerce_api_idram_complete', array($this, 'webhook_idram_complete'));

                /**
                 * Fail callback url for idram api
                 */
                add_action('woocommerce_api_idram_fail', array($this, 'webhook_idram_fail'));

                /**
                 * Result callback url for idram api
                 */
                add_action('woocommerce_api_idram_result', array($this, 'webhook_idram_result'));

                add_filter('woocommerce_available_payment_gateways', array($this, 'filter_disable_gateways'), 1);

                add_action('admin_print_styles', array($this, 'enqueue_stylesheets'));

                if (is_admin()) {
                    $this->checkActivation();
                }

                if(is_checkout() && $this->enabledRocketLine){

                    wp_enqueue_script('hkd-idram-front-checkout-js', $this->pluginDirUrl . "assets/js/checkout.js", array('jquery'), '', true);

                    if ($this->mode === 'white'){
                        $url =  ($this->tooltipType === 'withZero') ?  $this->pluginDirUrl . 'assets/images/idram_rocket_0_checkout_white.png' :  $this->pluginDirUrl . 'assets/images/idram_rocket_checkout_white.png' ;
                    }else{
                        $url =  ($this->tooltipType === 'withZero') ?  $this->pluginDirUrl . 'assets/images/idram_rocket_0_checkout_dark.png' :  $this->pluginDirUrl . 'assets/images/idram_rocket_checkout_dark.png' ;
                    }
                    wp_localize_script('hkd-idram-front-checkout-js', 'data', array(
                        'imgUrl' => $url,
                    ));
                }
                // WP cron
                add_action('cronCheckOrderIdram', array($this, 'cronCheckOrderIdram'));

            }

            public function isMobile() {
                return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
            }

            public function cronCheckOrderIdram()
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
                        AND pm.meta_value = 'hk-idram-payment-gateway'
                        ORDER BY pm.meta_value ASC, pm.post_id DESC
                    ");
                foreach ($orders as $order) {
                    $postDateGmt = $order->post_date_gmt;
                    $diffTimeMinutes=(strtotime(date("Y-m-d H:i:s"))-strtotime($postDateGmt))/60;
                    if($diffTimeMinutes > 30){
                        $order = wc_get_order($order->ID);
                        $order->update_status('failed');
                        if ($this->debug) $this->log->add($this->id, 'Order status was changed to Failed #' . sanitize_text_field($order->ID));
                    }
                }
            }

            public function checkActivation()
            {
                $today = date('Y-m-d');
                if(get_option('hkd_check_activation_idram') !== $today) {
                    $payload = ['domain' => $_SERVER['SERVER_NAME'], 'enabled' => $this->enabled];
                    wp_remote_post($this->ownerSiteUrl . 'bank/idram/checkStatusPluginActivation', array(
                        'sslverify' => false,
                        'method' => 'POST',
                        'headers' => array('Accept' => 'application/json'),
                        'body' => $payload
                    ));
                    update_option('hkd_check_activation_idram', $today );
                }
            }


            public function init_form_fields()
            {
                $debug = __('Log HKD IDRAM Gateway events, inside <code>woocommerce/logs/idram.txt</code>', 'hk-idram-payment-gateway');
                if (!version_compare(WOOCOMMERCE_VERSION, '2.0', '<')) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.2.0', '<'))
                        $debug = str_replace('idram', $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)), $debug);
                    elseif (function_exists('wc_get_log_file_path')) {
                        $debug = str_replace('woocommerce/logs/idram.txt', '<a href="/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . sanitize_text_field($this->id) . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)) . '-log" target="_blank">' . __('here', 'hk-idram-payment-gateway') . '</a>', $debug);
                    }
                }
                $this->form_fields = array(
                    'language_payment_idram' => array(
                        'title' => __('Plugin language', 'hk-idram-payment-gateway'),
                        'type' => 'select',
                        'options' => [
                            'hy' => 'Հայերեն',
                            'ru_RU' => 'Русский',
                            'en_US' => 'English',
                        ],
                        'description' => __('Here you can change the language of the plugin control panel.', 'hk-idram-payment-gateway'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'hk-idram-payment-gateway'),
                        'label' => __('Enable payment gateway', 'hk-idram-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'hk-idram-payment-gateway'),
                        'type' => 'text',
                        'description' => __('User (website visitor) sees this title on order registry page as a title for purchase option.', 'hk-idram-payment-gateway'),
                        'default' => __('Pay via credit card', 'hk-idram-payment-gateway'),
                        'desc_tip' => true,
                        'placeholder' => __('Type the title', 'hk-idram-payment-gateway')
                    ),
                    'description' => array(
                        'title' => __('Description', 'hk-idram-payment-gateway'),
                        'type' => 'textarea',
                        'description' => __('User (website visitor) sees this description on order registry page in bank purchase option.', 'hk-idram-payment-gateway'),
                        'default' => __('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'hk-idram-payment-gateway'),
                        'desc_tip' => true,
                        'placeholder' => __('Type the description', 'hk-idram-payment-gateway')
                    ),
                    'language' => array(
                        'title' => __('Language', 'hk-idram-payment-gateway'),
                        'type' => 'select',
                        'options' => [
                            'AM' => 'Հայերեն',
                            'RU' => 'Русский',
                            'EN' => 'English',
                        ],
                        'description' => __('Here interface language of bank purchase can be regulated', 'hk-idram-payment-gateway'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'mode' => array(
                        'title' => __('Idram logo color', 'hk-idram-payment-gateway'),
                        'type' => 'select',
                        'options' => [
                            'white' => __('White', 'hk-idram-payment-gateway'),
                            'colorful' => __('Colorful', 'hk-idram-payment-gateway'),
                        ],
                        'description' => __('With this feature, you can change the checkout page logo color with one touch to match your website.', 'hk-idram-payment-gateway'),
                        'default' => 'colorful',
                        'desc_tip' => true,
                    ),
                    'enabledRocketLine' => array(
                        'title' => __('Rocket Line', 'hk-idram-payment-gateway'),
                        'label' => __('Enable the Rocket Line feature', 'hk-idram-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('This feature will inform your customer about the Rocket Line digital loan. Please select the option available to you.', 'hk-idram-payment-gateway'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'tooltipType' => array(
                        'title' => '',
                        'type' => 'select',
                        'options' => [
                            'withoutZero' => __('Rocket Line digital loan', 'hk-idram-payment-gateway'),
                            'withZero' =>  __('Rocket Line 0% digital loan', 'hk-idram-payment-gateway'),
                        ],
                        'default' => 'withoutZero',
                        'class' => 'tooltipTypeIdram hiddenValueIdram'
                    ),
                    'testmode' => array(
                        'title' => __('Test mode', 'hk-idram-payment-gateway'),
                        'label' => __('Enable test Mode', 'hk-idram-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('To test the testing version login and password provided by the bank should be typed', 'hk-idram-payment-gateway'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'debug' => array(
                        'title' => __('Debug Log', 'hk-idram-payment-gateway'),
                        'type' => 'checkbox',
                        'label' => __('Enable debug mode', 'hk-idram-payment-gateway'),
                        'default' => 'no',
                        'description' => $debug,
                    ),
                    'test_edp_rec_account' => array(
                        'title' => __('Test User Name', 'hk-idram-payment-gateway'),
                        'type' => 'text',
                    ),
                    'test_secret_key' => array(
                        'title' => __('Test Password', 'hk-idram-payment-gateway'),
                        'type' => 'password',
                        'placeholder' => __('Enter password', 'hk-idram-payment-gateway')
                    ),
                    'live_settings' => array(
                        'title' => __('Live Settings', 'hk-idram-payment-gateway'),
                        'type' => 'hidden'
                    ),
                    'live_edp_rec_account' => array(
                        'title' => __('User Name', 'hk-idram-payment-gateway'),
                        'type' => 'text',
                        'placeholder' => __('Type the user name', 'hk-idram-payment-gateway')
                    ),
                    'live_secret_key' => array(
                        'title' => __('Password', 'hk-idram-payment-gateway'),
                        'type' => 'password',
                        'placeholder' => __('Type the password', 'hk-idram-payment-gateway')
                    ),
                    'useful_functions' => array(
                        'title' => __('Useful functions', 'hk-idram-payment-gateway'),
                        'type' => 'hidden'
                    ),
                    'empty_card' => array(
                        'title' => __('Cart totals', 'hk-idram-payment-gateway'),
                        'label' => __('Activate shopping cart function', 'hk-idram-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('This feature ensures that the contents of the shopping cart are available at the time of order registration if the site buyer decides to change the payment method.', 'hk-idram-payment-gateway'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'successOrderStatus' => array(
                        'title' => __('Success Order Status', 'hk-idram-payment-gateway'),
                        'type' => 'select',
                        'options' => [
                            'processing' => __('Processing', 'hk-idram-payment-gateway'),
                            'completed' => __('Completed', 'hk-idram-payment-gateway'),
                        ],
                        'description' => __('Here you can select the status of confirmed payment orders.', 'hk-idram-payment-gateway'),
                        'default' => 'processing',
                        'desc_tip' => true,
                    ),
                    'links' => array(
                        'title' => __('Links', 'hk-idram-payment-gateway'),
                        'type' => 'hidden'
                    ),
                );

            }

            public function process_payment($order_id)
            {
                global $woocommerce;
                $order = wc_get_order($order_id);
                $amount = $order->get_total();
                $order->update_status('pending');
                wc_reduce_stock_levels($order_id);
                if($this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                return [
                    'result' => 'success',
                    'redirect' => add_query_arg(array(
                        'action' => 'redirect_idram_form',
                        'EDP_LANGUAGE' => $this->language,
                        'EDP_REC_ACCOUNT' => $this->edp_rec_account,
                        'EDP_AMOUNT' => $amount,
                        'EDP_BILL_NO' => $order_id,
                    ))
                ];
            }

            public function enqueue_stylesheets()
            {
                $plugin_url = $this->pluginDirUrl;
                wp_enqueue_script('hkd-idram-front-admin-js', $plugin_url . "assets/js/script.js");
                wp_enqueue_style('hkd-style', $plugin_url . "assets/css/style.css");
                wp_enqueue_style('hkd-style-awesome', $plugin_url . "assets/css/font_awesome.css");
            }

            public function admin_options()
            {
                $validate = $this->validateFields();
                if ($validate['status'] !== 'success') {
                    $message = $validate['message'];
                }
                if (!empty($message)) { ?>
                <div id="message" class="<?php echo ($validate['status'] === 'success') ? 'updated' : 'error' ?> fade">
                    <p><?php echo esc_html($message); ?></p></div><?php } ?>
                <div class="wrap-content wrap-content-hkd"
                     style="width: 45%;display: inline-block;vertical-align: text-bottom;">
                    <h3><?php echo __('Payment Gateway for Idram', 'hk-idram-payment-gateway'); ?></h3>
                    <table class="form-table">
                        <?php if ($validate['status'] === 'success') {
                            $this->generate_settings_html(); ?>
                            <tr valign="top">
                                <th scope="row">Callback Success Url(SUCCESS_URL)</th>
                                <td><?php echo esc_url(get_site_url()) ?>/wc-api/idram_complete</td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Callback Fail Url(FAIL_URL)</th>
                                <td><?php echo esc_url(get_site_url()) ?>/wc-api/idram_fail</td>
                            </tr>
                            <tr valign="top">
                            <th scope="row">Callback Result Url(RESULT_URL)</th>
                            <td><?php echo esc_url(get_site_url()) ?>/wc-api/idram_result</td>
                            </tr><?php } elseif ($validate['status'] === 'currency_error') {
                            ?>
                            <tr valign="top">
                                <td style="color: red"><?php echo  __('This currency is not supported by payment system Idram', 'hk-idram-payment-gateway'); ?></td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>
                <div class="wrap-content wrap-content-hkd"
                     style="width: 29%;display: inline-block;position: absolute; padding-top: 75px;">
                <div class="wrap-content-hkd-400px">
                    <img src="<?php echo esc_url($this->pluginDirUrl) . 'assets/images/idram_dark.svg'; ?>">
                    <div class="wrap-content-hkd-info">
                        <h2><?php echo __('Payment system', 'hk-idram-payment-gateway'); ?></h2>
                        <div class="wrap-content-info">
                            <div class="phone-icon icon"><i class="fa fa-phone"></i></div>
                            <p><a href="tel:+37460700700">060700700</a></p>
                            <div class="mail-icon icon"><i class="fa fa-envelope"></i></div>
                            <p><a href="mailto:commerce@idram.am">commerce@idram.am</a></p>
                            <div class="mail-icon icon"><i class="fa fa-link"></i></div>
                            <p><a target="_blank" href="https://www.idram.am">idram.am</a></p>
                        </div>
                    </div>
                </div>
                <div class="wrap-content-hkd-400px">
                    <img width="341" height="140"
                         src="<?php echo esc_url($this->pluginDirUrl) ?>assets/images/hkserperator.png">
                </div>
                <div class="wrap-content-hkd-400px">
                    <img src="<?php echo esc_url($this->pluginDirUrl) ?>assets/images/logo_hkd.png">
                    <div class="wrap-content-hkd-info">
                        <div class="wrap-content-info">
                            <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                            </div>
                            <p><a href="tel:+37460777999">060777999</a></p>
                            <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                            </div>
                            <p><a href="tel:+37433779779">033779779</a></p>
                            <div class="mail-icon-2 icon"><i class="fa fa-envelope"></i></div>
                            <p><a href="mailto:support@hkdigital.am">support@hkdigital.am</a></p>
                            <div class="mail-icon-2 icon"><i class="fa fa-link"></i></div>
                            <p><a target="_blank" href="https://www.hkdigital.am">hkdigital.am</a></p>
                        </div>
                    </div>
                </div>
                </div><?php
            }

            /**
             * @return array|mixed|object
             */
            public function validateFields()
            {
                $wooCurrency = get_woocommerce_currency();
                if (!isset($this->currencies[$wooCurrency])) {
                    $this->update_option('enabled', 'no');
                    return ['message' => 'Դուք այժմ օգտագործում եք ' . $wooCurrency . ' արժույթը, այն չի սպասարկվում Իդրամ ՍՊԸ-ի կողմից, խնդրում ենք օգտագործել ' .
                        implode(', ', array_keys($this->currencies)) . ' արժույթը։', 'status' => 'currency_error'];
                }
                return ['message' => '', 'status' => 'success'];
            }

            public function filter_disable_gateways($gateways)
            {
                $currency = get_woocommerce_currency();

                if (!isset($this->currencies[$currency])) {
                    unset($gateways['hk-idram-payment-gateway']);
                }

                return $gateways;
            }

            /*
            * WebHook idram Success Request
            */
            public function webhook_idram_complete()
            {
                global $woocommerce;
                if(!$this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                $order = wc_get_order(sanitize_text_field($_REQUEST['EDP_BILL_NO']));
                if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_REQUEST['EDP_BILL_NO'] ). ' successfully added to ' . esc_html($this->successOrderStatus));
                wp_redirect($this->get_return_url($order));
            }

            /*
            * WebHook idram Fail Request
            */
            public function webhook_idram_fail()
            {
                global $woocommerce;
                if(!$this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                $order = wc_get_order(sanitize_text_field($_REQUEST['EDP_BILL_NO']));
                if ($order !== false) $order->update_status('failed');
                wc_add_notice(__('Please try again.', 'hk-idram-payment-gateway'), 'error');
                if ($this->debug) $this->log->add($this->id, 'something went wrong with Idram callback: Response: ' . serialize($_REQUEST));
                wp_redirect($this->get_return_url($order));
            }

            /*
             * WebHook idram result 2 requests
             */
            public function webhook_idram_result()
            {
                if (isset($_REQUEST['EDP_PRECHECK']) && isset($_REQUEST['EDP_BILL_NO']) &&
                    isset($_REQUEST['EDP_REC_ACCOUNT']) && isset($_REQUEST['EDP_AMOUNT'])) {
                    if (sanitize_text_field($_REQUEST['EDP_PRECHECK']) == "YES") {
                        if (sanitize_text_field($_REQUEST['EDP_REC_ACCOUNT']) == $this->edp_rec_account) {
                            if (wc_get_order(sanitize_text_field($_REQUEST['EDP_BILL_NO'])) !== false) {
                                $order = wc_get_order(sanitize_text_field($_REQUEST['EDP_BILL_NO']));
                                $amount = $order->get_total();
                                if ($amount == sanitize_text_field($_REQUEST['EDP_AMOUNT'])) {
                                    echo("OK");
                                    die;
                                } else {
                                    if ($this->debug) $this->log->add($this->id, 'something went wrong with Idram callback: Response: ' . serialize($_REQUEST));
                                }
                            } else {
                                if ($this->debug) $this->log->add($this->id, 'something went wrong with Idram callback: Response: ' . serialize($_REQUEST));
                            }
                        }
                    }
                }
                if (isset($_REQUEST['EDP_PAYER_ACCOUNT']) && isset($_REQUEST['EDP_BILL_NO']) &&
                    isset($_REQUEST['EDP_REC_ACCOUNT']) && isset($_REQUEST['EDP_AMOUNT'])
                    && isset($_REQUEST['EDP_TRANS_ID']) && isset($_REQUEST['EDP_CHECKSUM'])) {
                    $txtToHash =
                        $this->edp_rec_account . ":" .
                        sanitize_text_field($_REQUEST['EDP_AMOUNT']) . ":" .
                        $this->secret_key . ":" .
                        sanitize_text_field($_REQUEST['EDP_BILL_NO']) . ":" .
                        sanitize_text_field($_REQUEST['EDP_PAYER_ACCOUNT']) . ":" .
                        sanitize_text_field($_REQUEST['EDP_TRANS_ID']) . ":" .
                        sanitize_text_field($_REQUEST['EDP_TRANS_DATE']);
                    if (strtoupper(sanitize_text_field($_REQUEST['EDP_CHECKSUM'])) != strtoupper(md5($txtToHash))) {
                        if (wc_get_order(sanitize_text_field($_REQUEST['EDP_BILL_NO'])) !== false) {
                            $order = wc_get_order(sanitize_text_field($_REQUEST['EDP_BILL_NO']));
                            $order->update_status('failed');
                        }
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with Idram callback: Response: ' . serialize($_REQUEST));
                    } else {
                        if (wc_get_order($_REQUEST['EDP_BILL_NO']) !== false) {
                            $order = wc_get_order(sanitize_text_field($_REQUEST['EDP_BILL_NO']));
                            $amount = $order->get_total();
                            if ($amount == sanitize_text_field($_REQUEST['EDP_AMOUNT'])) {
                                if ($this->debug) $this->log->add($this->id, 'success');
                                $order->update_status($this->successOrderStatus);
                                echo("OK");
                                die;
                            } else {
                                $order->update_status('failed');
                                if ($this->debug) $this->log->add($this->id, 'something went wrong with Idram callback: Response: ' . serialize($_REQUEST));
                            }
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Idram callback: Response: ' . serialize($_REQUEST));
                        }
                    }
                }
            }
        }
    }
}