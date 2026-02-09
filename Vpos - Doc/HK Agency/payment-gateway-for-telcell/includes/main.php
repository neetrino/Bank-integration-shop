<?php

add_action('plugins_loaded', 'hkdigital_init_telcell_gateway_class');
function hkdigital_init_telcell_gateway_class()
{
    global $pluginBaseNameTelcell;
    load_plugin_textdomain('hkd_telcell', false, $pluginBaseNameTelcell . '/languages/');

    if (class_exists('WC_Payment_Gateway')) {
        class HKDigital_Telcell_Gateway extends WC_Payment_Gateway
        {

            private $api_url;
            private $pluginDirUrl;
            private $ownerSiteUrl;
            private $currencies = ['AMD' => '051'];

            /**
             * HKDigital_Telcell_Gateway constructor.
             */
            public function __construct()
            {
                global $woocommerce;
                global $apiUrlTelcell;
                global $pluginDirUrlTelcell;

                $this->ownerSiteUrl = $apiUrlTelcell;
                $this->pluginDirUrl = $pluginDirUrlTelcell;

                $this->id = 'hkd_telcell';
                $this->icon = $this->pluginDirUrl . 'assets/images/logo_checkout_telcell.png';
                $this->has_fields = false;
                $this->method_title = esc_attr('Payment Gateway for telcell', 'hkd_telcell');
                $this->method_description = 'Pay with telcell payment system. Please note that the payment will be made in Armenian Dram.';
                if (is_admin()) {
                    if (isset($_POST['hkdigital_telcell_checkout_id'])) {
                        update_option('hkdigital_telcell_checkout_id', sanitize_text_field($_POST['hkdigital_telcell_checkout_id']));
                        $this->update_option('title', esc_attr('Pay via credit card', 'hkd_telcell'));
                        $this->update_option('valid_days', '1');
                        $this->update_option('description', esc_attr('Purchase by credit card. Please, note that purchase is going to be made by Armenian drams. ', 'hkd_telcell'));
                    }
                }
                $this->init_form_fields();
                $this->init_settings();
                $this->title = sanitize_text_field($this->get_option('title'));
                $this->description = sanitize_text_field($this->get_option('description'));
                $this->valid_days = $this->get_option('valid_days') === '' ? 1 : $this->get_option('valid_days');
                $this->enabled = $this->get_option('enabled');
                $this->empty_card = 'yes' === $this->get_option('empty_card');
                $this->payment_gateway_for_telcell_checkout_id = sanitize_text_field(get_option('hkdigital_telcell_checkout_id'));
                $this->language = $this->get_option('language');
                $this->testmode = 'yes' === $this->get_option('testmode');
                $this->shop_id = sanitize_text_field($this->testmode ? $this->get_option('test_shop_id') : $this->get_option('live_shop_id'));
                $this->shop_key = sanitize_text_field($this->testmode ? $this->get_option('test_shop_key') : $this->get_option('live_shop_key'));
                $this->debug = 'yes' === $this->get_option('debug');
                $this->currency = '֏';
                $this->language_payment_telcell = !empty($this->get_option('language_payment_telcell')) ? $this->get_option('language_payment_telcell') : 'hy';
                $this->api_url = $this->testmode == true ? 'https://telcellmoney.am/proto_test2/invoices' : 'https://telcellmoney.am/invoices';
                $this->successOrderStatusTelcell = $this->get_option('successOrderStatusTelcell');

                if ($this->debug) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) $this->log = $woocommerce->logger(); else $this->log = new WC_Logger();
                }

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                /**
                 * Complete callback url for telcell api
                 */
                add_action('woocommerce_api_telcell_redirect', array($this, 'webhook_telcell_redirect'));

                /**
                 * Result callback url for telcell api
                 */
                add_action('woocommerce_api_telcell_result', array($this, 'webhook_telcell_result'));

                add_filter('woocommerce_available_payment_gateways', array($this, 'filter_disable_gateways'), 1);

                add_action('admin_print_styles', array($this, 'enqueue_stylesheets'));

//                To do cancell payment
//                add_action('woocommerce_order_status_changed', array($this, 'statusChangeHook'), 10, 3);

                if (is_admin()) {
                    $this->checkActivation();
                }

            }


            public function checkActivation()
            {
                $today = date('Y-m-d');
                if (get_option('hkd_check_activation_telcell') !== $today) {
                    $payload = ['domain' => sanitize_text_field($_SERVER['SERVER_NAME']), 'enabled' => $this->enabled];
                    wp_remote_post($this->ownerSiteUrl . 'bank/telcell/checkStatusPluginActivation', array(
                        'sslverify' => false,
                        'method' => 'POST',
                        'headers' => array('Accept' => 'application/json'),
                        'body' => $payload
                    ));
                    update_option('hkd_check_activation_telcell', $today);
                }
            }

            public function statusChangeHook($order_id, $old_status, $new_status)
            {
                $order = wc_get_order($order_id);
                if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_telcell') {
                    if ($new_status == 'cancelled') {
                        return $this->cancelPayment($order_id);
                    }
                }
            }

            public function cancelPayment($order_id)
            {
                /* $reason */
                $order = wc_get_order($order_id);
                if (!$order->has_status('processing')) {
                    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
                    $issuer = $this->shop_id;
                    $issuerId = base64_encode($order_id);
                    $args = [
                        'clear_bill:issuer' => $issuer,
                        'issuer_id' => $issuerId,
                        'checksum' => hash('md5', $this->shop_key . $issuer . $issuerId)
                    ];
                    $response = wp_remote_post($this->api_url, [
                        'body' => json_encode($args),
                        'method' => 'POST'
                    ]);
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->ResponseCode == '00') {
                            $order->update_status('cancelled');
                            return true;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                            $order->update_status('processing');
                            die($body->ResponseMessage);
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                        $order->update_status('processing');
                        die('Order Cancel paymend #' . $order_id . '  failed.');
                    }
                }
            }


            private function getPaymentGatewayByOrder($order)
            {
                return wc_get_payment_gateway_by_order($order);
            }

            public function init_form_fields()
            {
                $debug = esc_attr('Log HKD telcell Gateway events, inside <code>woocommerce/logs/telcell.txt</code>', 'hkd_telcell');
                if (!version_compare(WOOCOMMERCE_VERSION, '2.0', '<')) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.2.0', '<'))
                        $debug = str_replace('telcell', $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)), $debug);
                    elseif (function_exists('wc_get_log_file_path')) {
                        $debug = str_replace('woocommerce/logs/telcell.txt', '<a href="/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)) . '-log" target="_blank">' . esc_attr('here', 'hkd_telcell') . '</a>', $debug);
                    }
                }
                $this->form_fields = array(
                    'language_payment_telcell' => array(
                        'title' => esc_attr('Plugin language', 'hkd_telcell'),
                        'type' => 'select',
                        'options' => [
                            'hy' => 'Հայերեն',
                            'ru_RU' => 'Русский',
                            'en_US' => 'English',
                        ],
                        'description' => esc_attr('Here you can change the language of the plugin control panel.', 'hkd_telcell'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'enabled' => array(
                        'title' => esc_attr('Enable/Disable', 'hkd_telcell'),
                        'label' => esc_attr('Enable payment gateway', 'hkd_telcell'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => esc_attr('Title', 'hkd_telcell'),
                        'type' => 'text',
                        'description' => esc_attr('User (website visitor) sees this title on order registry page as a title for purchase option.', 'hkd_telcell'),
                        'default' => esc_attr('Pay via credit card', 'hkd_telcell'),
                        'desc_tip' => true,
                        'placeholder' => esc_attr('Type the title', 'hkd_telcell')
                    ),
                    'description' => array(
                        'title' => esc_attr('Description', 'hkd_telcell'),
                        'type' => 'textarea',
                        'description' => esc_attr('User (website visitor) sees this description on order registry page in Telcell Wallet purchase option.', 'hkd_telcell'),
                        'default' => esc_attr('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'hkd_telcell'),
                        'desc_tip' => true,
                        'placeholder' => esc_attr('Type the description', 'hkd_telcell')
                    ),
                    'language' => array(
                        'title' => esc_attr('Language', 'hkd_telcell'),
                        'type' => 'select',
                        'options' => [
                            'am' => 'Հայերեն',
                            'ru' => 'Русский',
                            'en' => 'English',
                        ],
                        'description' => esc_attr('Here interface language of Telcell Wallet purchase can be regulated', 'hkd_telcell'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'testmode' => array(
                        'title' => esc_attr('Test mode', 'hkd_telcell'),
                        'label' => esc_attr('Enable test Mode', 'hkd_telcell'),
                        'type' => 'checkbox',
                        'description' => esc_attr('To test the testing version login and password provided by the Telcell Wallet should be typed', 'hkd_telcell'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'valid_days' => array(
                        'title' => esc_attr('Valid Days', 'hkd_telcell'),
                        'type' => 'number',
                        'description' => esc_attr('Valid days for close payment', 'hkd_telcell'),
                        'default' => 1,
                        'desc_tip' => true,
                        'required' => true,
                    ),
                    'debug' => array(
                        'title' => esc_attr('Debug Log', 'hkd_telcell'),
                        'type' => 'checkbox',
                        'label' => esc_attr('Enable debug mode', 'hkd_telcell'),
                        'default' => 'no',
                        'description' => $debug,
                    ),
                    'test_shop_id' => array(
                        'title' => esc_attr('Test User Name', 'hkd_telcell'),
                        'type' => 'text',
                    ),
                    'test_shop_key' => array(
                        'title' => esc_attr('Test Password', 'hkd_telcell'),
                        'type' => 'password',
                        'placeholder' => esc_attr('Enter password', 'hkd_telcell')
                    ),
                    'live_settings' => array(
                        'title' => esc_attr('Live Settings', 'hkd_telcell'),
                        'type' => 'hidden'
                    ),
                    'live_shop_id' => array(
                        'title' => esc_attr('User Name', 'hkd_telcell'),
                        'type' => 'text',
                        'placeholder' => esc_attr('Type the user name', 'hkd_telcell')
                    ),
                    'live_shop_key' => array(
                        'title' => esc_attr('Password', 'hkd_telcell'),
                        'type' => 'password',
                        'placeholder' => esc_attr('Type the password', 'hkd_telcell')
                    ),
                    'useful_functions' => array(
                        'title' => esc_attr('Useful functions', 'hkd_telcell'),
                        'type' => 'hidden'
                    ),
                    'empty_card' => array(
                        'title' => esc_attr('Cart totals', 'hkd_telcell'),
                        'label' => esc_attr('Activate shopping cart function', 'hkd_telcell'),
                        'type' => 'checkbox',
                        'description' => esc_attr('This feature ensures that the contents of the shopping cart are available at the time of order registration if the site buyer decides to change the payment method.', 'hkd_telcell'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'successOrderStatusTelcell' => array(
                        'title' => esc_attr('Success Order Status', 'hkd_telcell'),
                        'type' => 'select',
                        'options' => [
                            'processing' => esc_attr('Processing', 'hkd_telcell'),
                            'completed' => esc_attr('Completed', 'hkd_telcell'),
                        ],
                        'description' => esc_attr('Here you can select the status of confirmed payment orders.', 'hkd_telcell'),
                        'default' => 'processing',
                        'desc_tip' => true,
                    ),
                    'links' => array(
                        'title' => esc_attr('Links', 'hkd_telcell'),
                        'type' => 'hidden'
                    ),
                );

            }

            public function process_payment($order_id)
            {
                global $woocommerce;
                $order = wc_get_order($order_id);
                $price = ($this->testmode == true) ? 10 : intval($order->get_total());
                $shopKey = $this->shop_key;
                $issuer = $this->shop_id;
                $currency = $this->currency;
                $product = base64_encode(get_bloginfo('url'));
                $issuerId = base64_encode($order_id);
                $validDays = $this->valid_days;
                $order->update_status('pending');
                wc_reduce_stock_levels($order_id);
                if (!$this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                return array(
                    'result' => 'success',
                    'redirect' => $this->api_url . '?action=PostInvoice&issuer=' . $issuer . '&currency=' . $currency . '&price=' . $price . '&product=' . urlencode($product) . '&issuer_id=' . urlencode($issuerId) . '&valid_days=' . $validDays . '&lang=' . $this->language . '&security_code=' . $this->getTelcellSecurityCode($shopKey, $issuer, $currency, (string)$price, $product, $issuerId, (string)$validDays)
                );
            }

            function getTelcellSecurityCode($shop_key, $issuer, $currency, $price, $product, $issuer_id, $valid_days)
            {
                return hash('md5', $shop_key . $issuer . $currency . $price . $product . $issuer_id . $valid_days);
            }


            public function enqueue_stylesheets()
            {
                $plugin_url = $this->pluginDirUrl;
                wp_enqueue_style('hkd-style', $plugin_url . "assets/css/style.css");
                wp_enqueue_style('hkd-style-awesome', $plugin_url . "assets/css/font_awesome.css");
            }

            public function admin_options()
            {
                $validate = $this->validateFields();
                if (!$validate['success']) {
                    $message = $validate['message'];
                }
                if (!empty($message)) { ?>
                    <div id="message"
                         class="<?php echo ($validate['success']) ? 'updated' : 'error' ?> fade">
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <div class="wrap-content wrap-content-hkd"
                     style="width: 45%;display: inline-block;vertical-align: text-bottom;">
                    <h4><?= esc_attr('ONLINE PAYMENT GATEWAY', 'hkd_telcell') ?></h4>
                    <h3><?php echo esc_attr('TELCELL WALLET', 'hkd_telcell'); ?></h3>
                    <?php if (!$validate['success']): ?>
                        <div style="width: 400px; padding-bottom: 60px">
                            <p style="padding-bottom: 10px"><?php echo esc_attr('Before using the plugin, please contact the Telcell Wallet to receive respective regulations.', 'hkd_telcell'); ?></p>
                        </div>
                    <?php endif; ?>
                    <table class="form-table">
                        <?php if ($validate['success']) {
                            $this->generate_settings_html(); ?>
                            <tr valign="top">
                                <th scope="row">Callback Result Url(RESULT_URL)</th>
                                <td><?php echo get_site_url() ?>/wc-api/telcell_result</td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Redirect Url(REDIRECT_URL)</th>
                                <td><?php echo get_site_url() ?>/wc-api/telcell_redirect</td>
                            </tr>
                        <?php } elseif (!$validate['success'] && isset($validate['err_type']) && $validate['err_type'] == 'currency_error') {
                            ?>
                            <tr valign="top">
                                <td style="color: red"><?php echo esc_attr('This currency is not supported by payment system telcell', 'hkd_telcell'); ?></td>
                            </tr>
                        <?php } else { ?>
                            <tr valign="top">
                                <td style="display: block;width: 100%;padding-left: 0 !important;">
                                    <label style="display: block;padding-bottom: 3px"
                                           for="woocommerce_hkd_telcell_language_payment_telcell"><?php echo esc_attr('Plugin language', 'hkd_telcell') ?></label>
                                    <fieldset>
                                        <select class="select "
                                                name="woocommerce_hkd_telcell_language_payment_telcell"
                                                id="woocommerce_hkd_telcell_language_payment_telcell"
                                                style="">
                                            <option value="hy" <?php if ($this->language_payment_telcell == 'hy'): ?> selected <?php endif; ?> >
                                                Հայերեն
                                            </option>
                                            <option value="ru_RU" <?php if ($this->language_payment_telcell == 'ru_RU'): ?> selected <?php endif; ?> >
                                                Русский
                                            </option>
                                            <option value="en_US" <?php if ($this->language_payment_telcell == 'en_US'): ?> selected <?php endif; ?> >
                                                English
                                            </option>
                                        </select>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr valign="top">
                                <td style="display: block;width: 100%;padding-left: 0 !important;">
                                    <label style="display: block;padding-bottom: 3px"><?php echo esc_attr('Identification password', 'hkd_telcell'); ?></label>
                                    <input type="text"
                                           placeholder="<?php echo esc_attr('Example Telcellgayudcsu14', 'hkd_telcell'); ?>"
                                           name="hkdigital_telcell_checkout_id"
                                           id="hkdigital_telcell_checkout_id"
                                           value="<?php echo esc_html($this->payment_gateway_for_telcell_checkout_id); ?>"/>
                                </td>
                            </tr>

                        <?php } ?>
                    </table>
                    <?php if (!$validate['success']): ?>
                        <div>
                            <div style="margin-top: 50px;margin-bottom: 15px;">
                                <i style="font-size: 18px" class="phone-icon-2 fa fa-info-circle"></i>
                                <span style="width: calc(400px - 25px);display: inline-block;vertical-align: middle;font-size: 14px;font-weight:600;font-style: italic;font-family: sans-serif;">
                                    <?php echo esc_attr('To get acquainted with the conditions of identification and to obtain it, please contact the company.', 'hkd_telcell'); ?>
                        </span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="wrap-content wrap-content-hkd"
                     style="width: 29%;display: inline-block;position: absolute; padding-top: 75px;">
                <div class="wrap-content-hkd-400px">
                    <img src="<?php echo esc_html($this->pluginDirUrl); ?>assets/images/logo_telcell.png">
                    <div class="wrap-content-hkd-info">
                        <h2><?php echo esc_attr('Payment system', 'hkd_telcell'); ?></h2>
                        <div class="wrap-content-info">
                            <div class="phone-icon icon"><i class="fa fa-phone"></i></div>
                            <p><a href="tel:+37460272227">060 272227</a></p>
                            <div class="mail-icon icon"><i class="fa fa-envelope"></i></div>
                            <p><a href="mailto:10xbusiness@telcell.am">10xbusiness@telcell.am</a></p>
                            <div class="mail-icon icon"><i class="fa fa-link"></i></div>
                            <p><a target="_blank" href="https://www.telcell.am">Telcell.am</a></p>
                        </div>
                    </div>
                </div>
                <div class="wrap-content-hkd-400px">
                    <img width="341" height="140"
                         src="<?php echo esc_html($this->pluginDirUrl) ?>assets/images/hkserperator.png">
                </div>
                <div class="wrap-content-hkd-400px">
                    <img src="<?php echo esc_html($this->pluginDirUrl) ?>assets/images/logo_hkd.png">
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
                $go = get_option('hkdigital_dump_telcell');
                $wooCurrency = get_woocommerce_currency();

                if (!isset($this->currencies[$wooCurrency])) {
                    $this->update_option('enabled', 'no');
                    return ['message' => 'Դուք այժմ օգտագործում եք ' . $wooCurrency . ' արժույթը, այն չի սպասարկվում Telcell Wallet կողմից։ Հասանելի արժույթներն են ՝  ' .
                        implode(', ', array_keys($this->currencies)), 'success' => false, 'err_type' => 'currency_error'];
                }
                if ($this->payment_gateway_for_telcell_checkout_id == '') {
                    if (!empty($go)) {
                        update_option('hkdigital_dump_telcell', 'no');
                    } else {
                        add_option('hkdigital_dump_telcell', 'no');
                    };
                    $this->update_option('enabled', 'no');
                    return ['message' => esc_attr('You must fill token', 'hkd_telcell'), 'success' => false];
                }
                $res = wp_remote_post($this->ownerSiteUrl .
                    'bank/telcell/checkApiConnection', [
                    'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
                    'sslverify' => false,
                    'body' => [
                        'checkIn' => true
                    ]
                ]);
                if ($res) {

                    $response = wp_remote_post($this->ownerSiteUrl .
                        'bank/telcell/checkActivation', ['headers' => array('Accept' => 'application/json'), 'sslverify' => false, 'body' => ['domain' => sanitize_text_field($_SERVER['SERVER_NAME']), 'checkoutId' => $this->payment_gateway_for_telcell_checkout_id, 'lang' => $this->language_payment_telcell]]);
                    if (!is_wp_error($response)) {
                        if (!empty($go)) {
                            update_option('hkdigital_dump_telcell', 'yes');
                        } else {
                            add_option('hkdigital_dump_telcell', 'yes');
                        };
                        return json_decode($response['body'], true);
                    } else {
                        if (!empty($go)) {
                            update_option('hkdigital_dump_telcell', 'no');
                        } else {
                            add_option('hkdigital_dump_telcell', 'no');
                        };
                        $this->update_option('enabled', 'no');
                        return ['message' => esc_attr('Token not valid', 'hkd_telcell'), 'success' => false];
                    }
                } else {
                    if (get_option('hkdigital_dump_telcell') == 'yes') {
                        return ['message' => '', 'success' => true];
                    } else {
                        return ['message' => esc_attr('You must fill token', 'hkd_telcell'), 'success' => false];
                    }
                }
            }

            public function filter_disable_gateways($gateways)
            {
                $currency = get_woocommerce_currency();
                if (!isset($this->currencies[$currency])) {
                    unset($gateways['hkd_telcell']);
                }
                return $gateways;
            }


            /*
            * WebHook telcell Success Request
            */
            public function webhook_telcell_redirect()
            {
                $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                wp_redirect($this->get_return_url($order));
            }


            /*
             * WebHook telcell Result
             */
            public function webhook_telcell_result()
            {
                $orderId = sanitize_text_field($_REQUEST['issuer_id']);
                $order = wc_get_order($orderId);
                $status = sanitize_text_field($_REQUEST['status']);
                $invoice = sanitize_text_field($_REQUEST['invoice']);
                $payment_id = sanitize_text_field($_REQUEST['payment_id']);
                $currency = sanitize_text_field($_REQUEST['currency']);
                $sum = sanitize_text_field($_REQUEST['sum']);
                $time = sanitize_text_field($_REQUEST['time']);
                $checksum = sanitize_text_field($_REQUEST['checksum']);
                $serverSideHash = hash('md5', $this->shop_key . $invoice . $orderId . $payment_id . $currency . $sum . $time . $status);
                if ($serverSideHash === $checksum) {
                    if ($order !== false) {
                        if ($status === 'PAID') {
                            if ($this->debug) $this->log->add($this->id, 'success order ' . $orderId);
                            $order->update_status($this->successOrderStatusTelcell);
                        } else {
                            $order->update_status('failed');
                            if ($this->debug) $this->log->add($this->id, 'order is REJECTED: Response: ' . sanitize_text_field(json_encode($_REQUEST)));
                        }
                    } else {
                        $order->update_status('failed');
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with telcell callback: Response: ' . sanitize_text_field(json_encode($_REQUEST)));
                    }
                } else {
                    $order->update_status('failed');
                    if ($this->debug) $this->log->add($this->id, 'something went wrong with telcell callback checksum is wrong: Response: ' . sanitize_text_field(json_encode($_REQUEST)));
                }
            }
        }
    }


}
