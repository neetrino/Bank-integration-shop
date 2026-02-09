<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('plugins_loaded', 'hkdigital_init_fastshift_gateway_class');
function hkdigital_init_fastshift_gateway_class()
{
    global $pluginBaseNameFastshift;

    load_plugin_textdomain('payment-gateway-for-fastshift', false, $pluginBaseNameFastshift . '/languages/');

    if (class_exists('WC_Payment_Gateway')) {
        class HKDigital_Fastshift_Gateway extends WC_Payment_Gateway
        {
            private $ownerSiteUrl;
            private $pluginDirUrl;
            private $currencies = ['AMD' => '051'];

            private $api_url;

            /**
             * HKDigital_Fastshift_Gateway constructor.
             */
            public function __construct()
            {
                global $woocommerce;
                global $apiUrlFastshift;
                global $pluginDirUrlFastshift;

                $this->ownerSiteUrl = $apiUrlFastshift;
                $this->pluginDirUrl = $pluginDirUrlFastshift;

                $this->id = 'payment-gateway-for-fastshift';
                $this->icon = $this->pluginDirUrl . 'assets/images/fastshift-logo.png';
                $this->has_fields = false;
                $this->method_title = 'Payment Gateway for Fast Shift';
                $this->method_description = 'Pay with Fast Shift payment system. Please note that the payment will be made in Armenian Dram.';
                if (isset($_POST['hkdigital_payment-gateway-for-fastshift_verification_id'])) {
                    update_option('hkdigital_payment-gateway-for-fastshift_verification_id', sanitize_text_field($_POST['hkdigital_payment-gateway-for-fastshift_verification_id']));
                    $this->update_option('title', esc_html__('Pay via credit card', 'payment-gateway-for-fastshift'));
                    $this->update_option('description', esc_html__('Purchase by credit card. Please, note that purchase is going to be made by Armenian drams. ', 'payment-gateway-for-fastshift'));
                }
                $this->init_form_fields();
                $this->init_settings();
                $this->title = sanitize_text_field($this->get_option('title'));
                $this->description = sanitize_text_field($this->get_option('description'));
                $this->enabled = $this->get_option('enabled');
                $this->empty_card = 'yes' === $this->get_option('empty_card');
                $this->hkd_fastshift_checkout_id = sanitize_text_field(get_option('hkdigital_payment-gateway-for-fastshift_verification_id'));
                $this->successOrderStatus = $this->get_option('successOrderStatus');
                $this->testmode = 'yes' === $this->get_option('testmode');
                $this->bearerToken = sanitize_text_field($this->testmode ? $this->get_option('test_bearer_token') : $this->get_option('live_bearer_token'));
                $this->debug = 'yes' === $this->get_option('debug');
                $this->language_payment_fastshift = !empty($this->get_option('hkdigital_language_payment_fastshift')) ? $this->get_option('hkdigital_language_payment_fastshift') : 'hy';
                $this->api_url = $this->testmode == true ? esc_url('http://test-merchants.fastshift.am/api/en/') : esc_url('http://merchants.fastshift.am/api/en/');

                if ($this->debug) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) $this->log = $woocommerce->logger(); else $this->log = new WC_Logger();
                }

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                /**
                 * Result callback url for fast shift api
                 */
                add_action('woocommerce_api_fastshift_response', array($this, 'webhook_fastshift_response'));

                add_filter('woocommerce_available_payment_gateways', array($this, 'filter_disable_gateways'), 1);

                add_action('admin_print_styles', array($this, 'enqueue_stylesheets'));

                if (is_admin()) {
                    $this->checkActivation();
                }

                // WP cron
                add_action('cronCheckOrderFastshift', array($this, 'cronCheckOrderFastshift'));

            }

            public function cronCheckOrderFastshift()
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
                        AND pm.meta_value = 'payment-gateway-for-fastshift'
                        ORDER BY pm.meta_value ASC, pm.post_id DESC
                    ");
                foreach ($orders as $order) {
                    $postDateGmt = $order->post_date_gmt;
                    $diffTimeMinutes = (strtotime(date("Y-m-d H:i:s")) - strtotime($postDateGmt)) / 60;
                    if ($diffTimeMinutes > 30) {
                        $order = wc_get_order($order->ID);
                        $order->update_status('failed');
                        if ($this->debug) $this->log->add($this->id, 'Order status was changed to Failed #' . sanitize_text_field($order->ID));
                    }
                }
            }

            public function checkActivation()
            {
                $today = date('Y-m-d');
                if (get_option('hkdigtial_check_activation_fastshift') !== $today) {
                    $payload = ['domain' => sanitize_text_field($_SERVER['SERVER_NAME']), 'enabled' => $this->enabled];
                    wp_remote_post($this->ownerSiteUrl . 'bank/fastshift/checkStatusPluginActivation', array(
                        'sslverify' => false,
                        'method' => 'POST',
                        'headers' => array('Accept' => 'application/json'),
                        'body' => $payload
                    ));
                    update_option('hkdigtial_check_activation_fastshift', $today);
                }
            }

            public function init_form_fields()
            {
                $debug = esc_html__('Log HKD Fast Shift Gateway events, inside ', 'payment-gateway-for-fastshift') . '<code>' . esc_html__('woocommerce/logs/fastshift.txt', 'payment-gateway-for-fastshift') . '</code>';
                if (!version_compare(WOOCOMMERCE_VERSION, '2.0', '<')) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.2.0', '<'))
                        $debug = str_replace('fastshift', $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)), $debug);
                    elseif (function_exists('wc_get_log_file_path')) {
                        $debug = str_replace('woocommerce/logs/fastshift.txt', '<a href="/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . sanitize_text_field($this->id) . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)) . '-log" target="_blank">' . esc_html__('here', 'payment-gateway-for-fastshift') . '</a>', $debug);
                    }
                }
                $this->form_fields = array(
                    'language_payment_fastshift' => array(
                        'title' => esc_html__('Plugin language', 'payment-gateway-for-fastshift'),
                        'type' => 'select',
                        'options' => [
                            'hy' => 'Հայերեն',
                            'ru_RU' => 'Русский',
                            'en_US' => 'English',
                        ],
                        'description' => esc_html__('Here you can change the language of the plugin control panel.', 'payment-gateway-for-fastshift'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'enabled' => array(
                        'title' => esc_html__('Enable/Disable', 'payment-gateway-for-fastshift'),
                        'label' => esc_html__('Enable payment gateway', 'payment-gateway-for-fastshift'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => esc_html__('Title', 'payment-gateway-for-fastshift'),
                        'type' => 'text',
                        'description' => esc_html__('User (website visitor) sees this title on order registry page as a title for purchase option.', 'payment-gateway-for-fastshift'),
                        'default' => esc_html__('Pay via credit card', 'payment-gateway-for-fastshift'),
                        'desc_tip' => true,
                        'placeholder' => esc_html__('Type the title', 'payment-gateway-for-fastshift')
                    ),
                    'description' => array(
                        'title' => esc_html__('Description', 'payment-gateway-for-fastshift'),
                        'type' => 'textarea',
                        'description' => esc_html__('User (website visitor) sees this description on order registry page in bank purchase option.', 'payment-gateway-for-fastshift'),
                        'default' => esc_html__('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'payment-gateway-for-fastshift'),
                        'desc_tip' => true,
                        'placeholder' => esc_html__('Type the description', 'payment-gateway-for-fastshift')
                    ),
                    'debug' => array(
                        'title' => esc_html__('Debug Log', 'payment-gateway-for-fastshift'),
                        'type' => 'checkbox',
                        'label' => esc_html__('Enable debug mode', 'payment-gateway-for-fastshift'),
                        'default' => 'no',
                        'description' => $debug,
                    ),
                    'testmode' => array(
                        'title' => esc_html__('Test mode', 'payment-gateway-for-fastshift'),
                        'label' => esc_html__('Enable test Mode', 'payment-gateway-for-fastshift'),
                        'type' => 'checkbox',
                        'description' => esc_html__('To test the testing version login and password provided by the bank should be typed', 'payment-gateway-for-fastshift'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'test_bearer_token' => array(
                        'title' => esc_html__('Test Fast Shift Bearer Token', 'payment-gateway-for-fastshift'),
                        'type' => 'password',
                        'placeholder' => esc_html__('Enter Test Fast Shift Bearer Token', 'payment-gateway-for-fastshift')
                    ),
                    'live_settings' => array(
                        'title' => esc_html__('Live Settings', 'payment-gateway-for-fastshift'),
                        'type' => 'hidden',
                    ),
                    'live_bearer_token' => array(
                        'title' => esc_html__('Live Bearer Token', 'payment-gateway-for-fastshift'),
                        'type' => 'password',
                        'placeholder' => esc_html__('Type Live Bearer Token', 'payment-gateway-for-fastshift')
                    ),
                    'useful_functions' => array(
                        'title' => esc_html__('Useful functions', 'payment-gateway-for-fastshift'),
                        'type' => 'hidden'
                    ),
                    'empty_card' => array(
                        'title' => esc_html__('Cart totals', 'payment-gateway-for-fastshift'),
                        'label' => esc_html__('Activate shopping cart function', 'payment-gateway-for-fastshift'),
                        'type' => 'checkbox',
                        'description' => esc_html__('This feature ensures that the contents of the shopping cart are available at the time of order registration if the site buyer decides to change the payment method.', 'payment-gateway-for-fastshift'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'successOrderStatus' => array(
                        'title' => esc_html__('Success Order Status', 'payment-gateway-for-fastshift'),
                        'type' => 'select',
                        'options' => [
                            'processing' => esc_html__('Processing', 'payment-gateway-for-fastshift'),
                            'completed' => esc_html__('Completed', 'payment-gateway-for-fastshift'),
                        ],
                        'description' => esc_html__('Here you can select the status of confirmed payment orders.', 'payment-gateway-for-fastshift'),
                        'default' => 'processing',
                        'desc_tip' => true,
                    ),
                    'links' => array(
                        'title' => esc_html__('Links', 'payment-gateway-for-fastshift'),
                        'type' => 'hidden'
                    )
                );

            }

            public function process_payment($order_id)
            {
                global $woocommerce;
                $order = wc_get_order($order_id);
                $amount = $order->get_total();
                $order->update_status('pending');
                $descriptionUrl = preg_replace("(^https?://)", "", get_site_url());

                $args = [
                    "order_number" => $this->generateOrderNumberGUID(),
                    "amount" => (int)$amount,
                    "description" => $descriptionUrl,
                    "callback_url" => get_site_url() . '/wc-api/fastshift_response?order_id=' . $order_id,
                ];

                $response = wp_remote_post($this->api_url . 'vpos/order/register', [
                    'headers' => [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Authorization' => 'Bearer ' . $this->bearerToken
                    ],
                    'body' => json_encode($args),
                    'method' => 'POST'
                ]);


                if (!is_wp_error($response)) {
                    $body = json_decode($response['body']);
                    if ($body->status === "OK") {
                        $order->update_status('pending');
                        wc_reduce_stock_levels($order_id);
                        if (!$this->empty_card) {
                            $woocommerce->cart->empty_cart();
                        }
                        update_post_meta($order_id, 'OrderGuid', sanitize_text_field($body->data->order->order_number));
                        return array('result' => 'success', 'redirect' => $body->data->redirect_url);
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' failed.');
                        $order->update_status('failed', json_encode($body->errors));
                        wc_add_notice(esc_html__('Please try again.', 'payment-gateway-for-fastshift'), 'error');
                    }
                } else {
                    if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' failed.');
                    $order->update_status('failed');
                    wc_add_notice(esc_html__('Connection error.', 'payment-gateway-for-fastshift'), 'error');
                }
            }

            private function generateOrderNumberGUID()
            {
                $charId = md5(uniqid(rand(), true));
                $hyphen = chr(45);
                return substr($charId, 0, 8) . $hyphen
                    . substr($charId, 8, 4) . $hyphen
                    . substr($charId, 12, 4) . $hyphen
                    . substr($charId, 16, 4) . $hyphen
                    . substr($charId, 20, 12);
            }

            public function enqueue_stylesheets()
            {
                $plugin_url = $this->pluginDirUrl;
                wp_enqueue_script('hkd-fastshift-front-admin-js', $plugin_url . "assets/js/admin.js");
                wp_enqueue_style('hkd-style-fastshift', $plugin_url . "assets/css/style.css");
                wp_enqueue_style('hkd-style-awesome', $plugin_url . "assets/css/font_awesome.css");
            }

            public function admin_options()
            {
                $validate = $this->validateFields();

                if (!$validate['success']) {
                    $message = $validate['message'];
                }
                if (!empty($message)) { ?>
                    <div id="message" class="<?php echo ($validate['success']) ? 'updated' : 'error' ?> fade">
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <div class="wrap-fastshift wrap-content wrap-content-hkd" id="pluginMainWrap">
                <h4><?php echo esc_html__('Online payment gateway', 'payment-gateway-for-fastshift') ?></h4>
                <h3 class="text-label-step"><?php echo esc_html__('Fast Shift', 'payment-gateway-for-fastshift'); ?></h3>
                <?php if (!$validate['success']): ?>
                <div class="w-100">
                    <p class="mb-10"><?php echo esc_html__('Before using the plugin, please contact the bank to receive respective regulations.', 'payment-gateway-for-fastshift'); ?></p>
                </div>
            <?php endif; ?>

                <?php if ($validate['success']) { ?>
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                    <tr valign="top">
                        <th scope="row">
                            <?php echo esc_html__('Ip Address', 'payment-gateway-for-fastshift'); ?>
                        </th>
                        <td>
                            <div class="d-flex">
                                <span id="ipAddress"><?php
                                    if (function_exists('exec')) {
                                        $ipAddress = exec("ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1'");
                                        if (!$ipAddress) {
                                            $ipAddress = gethostbyname(gethostname());
                                        }
                                    } else {
                                        $ipAddress = gethostbyname(gethostname());
                                    }
                                    echo esc_html($ipAddress); ?></span>
                                <span class="pl-10 " id="copyToClipboard"><i class="fa fa-copy cursor-pointer"></i>
                            </div>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Fastshift callback Url</th>
                        <td><?php echo esc_url(get_site_url()) ?>/wc-api/fastshift_response</td>
                    </tr>
                </table>
            <?php } else { ?>
                <div class="step-inner-content mt-40">

                    <div class="budget-area">
                        <label class="input-label-verification"><?php echo esc_html__('Plugin language', 'payment-gateway-for-fastshift'); ?></label>
                        <select name="woocommerce_payment-gateway-for-fastshift_language_payment_fastshift">
                            <option value="hy" <?php if ($this->language_payment_fastshift == 'hy'): ?> selected <?php endif; ?>>
                                Հայերեն
                            </option>
                            <option value="ru_RU" <?php if ($this->language_payment_fastshift == 'ru_RU'): ?> selected <?php endif; ?> >
                                Русский
                            </option>
                            <option value="en_US" <?php if ($this->language_payment_fastshift == 'en_US'): ?> selected <?php endif; ?> >
                                English
                            </option>
                        </select>
                    </div>

                    <h2 class="verification-label text-center">
                        <?php echo esc_html__('IDENTIFICATION', 'payment-gateway-for-fastshift'); ?>
                    </h2>
                    <p class="mt-5 font-size-14 text-center">
                        <?php echo esc_html__('Contact us at the specified phone number or e-mail address for authentication.', 'payment-gateway-for-fastshift'); ?>
                    </p>
                    <div class="form-inner-area">
                        <label class="input-label-verification"><?php echo esc_html__('Identification password', 'payment-gateway-for-fastshift'); ?></label>
                        <input type="text" name="hkdigital_payment-gateway-for-fastshift_verification_id"
                               id="hkdigital_payment-gateway-for-fastshift_verification_id" class="form-control "
                               value="<?php echo esc_html($this->hkd_fastshift_checkout_id); ?>"
                               minlength="2"
                               placeholder="Օրինակ՝ FastShiftgayudcsu14">
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
                            <span><?php echo esc_html__('I have read and agree to the Plugin', 'payment-gateway-for-fastshift'); ?> <a
                                        href="javascript:"
                                        id="toggle-terms_div"><b>  <?php echo esc_html__('Terms & Conditions', 'payment-gateway-for-fastshift'); ?></b></a></span>&nbsp;<abbr
                                    class="required" title="required">*</abbr>
                            <br>
                            <span id="terms-error" class="error"></span>
                        </label>
                    </div>
                </div>
                <div class="mt-10">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label for="woocommerce_payment-gateway-for-fastshift_links"><?php echo esc_html__('Links', 'payment-gateway-for-fastshift'); ?></label>
                            </th>
                            <td class="forminp">
                                <fieldset>
                                    <legend class="screen-reader-text">
                                        <span><?php echo esc_html__('Links', 'payment-gateway-for-fastshift'); ?></span>
                                    </legend>
                                    <input class="input-text regular-input " type="hidden"
                                           name="woocommerce_payment-gateway-for-fastshift_links"
                                           id="woocommerce_payment-gateway-for-fastshift_links" style="" value=""
                                           placeholder="">
                                </fieldset>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <?php echo esc_html__('Ip Address', 'payment-gateway-for-fastshift'); ?>
                            </th>
                            <td>
                                <div class="d-flex">
                                   <span id="ipAddress"><?php
                                       if (function_exists('exec')) {
                                           $ipAddress = exec("ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1'");
                                           if (!$ipAddress) {
                                               $ipAddress = gethostbyname(gethostname());
                                           }
                                       } else {
                                           $ipAddress = gethostbyname(gethostname());
                                       }
                                       echo esc_html($ipAddress); ?></span>
                                    <span class="pl-10 " id="copyToClipboard"><i class="fa fa-copy cursor-pointer"></i>
                                </div>
                </div>
                </td>
                </tr>
                </table>
                </div>
            <?php } ?>

                </div>
                <div class="wrap-fastshift wrap-content wrap-content-hkd" id="paymentInfoBlock">
                    <div class="wrap-content-hkd-400px">
                        <img src="<?php echo esc_url($this->pluginDirUrl); ?>assets/images/fastshift-admin.png">
                        <div class="wrap-content-hkd-info hkd-fastshift">
                            <h2> <?php echo esc_html__('Payment system', 'payment-gateway-for-fastshift'); ?></h2>
                            <div class="wrap-content-info">
                                <div class="phone-icon icon"><i class="fa fa-phone"></i></div>
                                <p><a href="tel:010510010">010 510 010</a></p>
                                <div class="mail-icon icon"><i class="fa fa-envelope"></i></div>
                                <p><a href="mailto:info@fastshift.am">info@fastshift.am</a></p>
                                <div class="mail-icon icon"><i class="fa fa-link"></i></div>
                                <p><a target="_blank" href="https://fastshift.am">fastshift.am</a></p>
                            </div>
                        </div>
                    </div>
                    <div class="wrap-content-hkd-400px">
                        <img width="341" height="140"
                             src="<?php echo esc_url($this->pluginDirUrl); ?>assets/images/hkserperator.png">
                    </div>
                    <div class=" wrap-content-hkd-400px">
                        <img src="<?php echo esc_url($this->pluginDirUrl); ?>assets/images/logo_hkd.png">
                        <div class="wrap-content-hkd-info">
                            <div class="wrap-content-info">
                                <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                                </div>
                                <p><a href="tel:060777999">060 777 999</a></p>
                                <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                                </div>
                                <p><a href="tel:033779779">033 779 779</a></p>
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
            /**
             * @return array|mixed|object
             */
            public function validateFields()
            {
                $go = get_option('hkdigital_dump');
                $wooCurrency = get_woocommerce_currency();
                if (!isset($this->currencies[$wooCurrency])) {
                    $this->update_option('enabled', 'no');
                    return ['message' => 'Դուք այժմ օգտագործում եք ' . $wooCurrency . ' արժույթը, այն չի սպասարկվում բանկի կողմից։ Հասանելի արժույթներն են ՝  ' .
                        implode(', ', array_keys($this->currencies)), 'success' => false, 'err_msg' => 'currency_error'];
                }
                if ($this->hkd_fastshift_checkout_id == '') {
                    if (!empty($go)) {
                        update_option('hkdigital_dump', 'no');
                    } else {
                        add_option('hkdigital_dump', 'no');
                    };
                    $this->update_option('enabled', 'no');
                    return ['message' => esc_html__('You must fill token', 'payment-gateway-for-fastshift'), 'success' => false];
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
                        'bank/fastshift/checkActivation', ['headers' => array('Accept' => 'application/json'), 'sslverify' => false, 'body' => ['domain' => sanitize_text_field($_SERVER['SERVER_NAME']), 'checkoutId' => $this->hkd_fastshift_checkout_id, 'lang' => $this->language_payment_fastshift]]);
                    if (!is_wp_error($response)) {
                        if (!empty($go)) {
                            update_option('hkdigital_dump', 'yes');
                        } else {
                            add_option('hkdigital_dump', 'yes');
                        };
                        return json_decode($response['body'], true);
                    } else {
                        if (!empty($go)) {
                            update_option('hkdigital_dump', 'no');
                        } else {
                            add_option('hkdigital_dump', 'no');
                        };
                        $this->update_option('enabled', 'no');
                        return ['message' => esc_html__('Token not valid', 'payment-gateway-for-fastshift'), 'success' => false];
                    }
                } else {
                    if (get_option('hkdigital_dump') == 'yes') {
                        return ['message' => '', 'success' => true];
                    } else {
                        return ['message' => esc_html__('You must fill token', 'payment-gateway-for-fastshift'), 'success' => false];
                    }
                }

            }

            public function filter_disable_gateways($gateways)
            {
                $currency = get_woocommerce_currency();

                if (!isset($this->currencies[$currency])) {
                    unset($gateways['payment-gateway-for-fastshift']);
                }

                return $gateways;
            }

            /*
             * WebHook fastshift result 2 requests
             */
            public function webhook_fastshift_response()
            {
                global $woocommerce;
                if ($this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                $order_id = sanitize_text_field($_GET['order_id']);
                $order = wc_get_order(sanitize_text_field($order_id));
                $orderNumber = get_post_meta($order_id, 'OrderGuid', true);
                if (!$orderNumber) {
                    $orderNumber = sanitize_text_field($_GET['order_number']);
                }
                $response = wp_remote_get($this->api_url . 'vpos/order/status/' . $orderNumber, [
                    'headers' => [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Authorization' => 'Bearer ' . $this->bearerToken
                    ],
                ]);

                if (!is_wp_error($response)) {
                    $body = json_decode($response['body']);
                    if ($body->status === 'OK') {
                        if ($body->data->order->status === 'completed') {
                            $order->update_status($this->successOrderStatus);
                            wp_redirect($order->get_checkout_order_received_url());
                            exit;
                        } else {
                            $order->update_status('failed', json_encode($body->data));
                            wp_redirect($this->get_return_url($order));
                            exit;
                        }
                    } else {
                        $order->update_status('failed', json_encode($body->errors));
                        wp_redirect($this->get_return_url($order));
                        exit;
                    }
                } else {
                    $order->update_status('failed');
                    wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                    exit;
                }
            }
        }
    }
}