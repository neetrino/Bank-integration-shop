<?php
add_action('woocommerce_thankyou', 'woocomerceTaxServiceThankYouPage', 999);
function woocomerceTaxServiceThankYouPage($order_id)
{
    global $tax_service_plugin_url;
    $order = wc_get_order($order_id);
    $taxServiceReport = get_post_meta($order->get_id(), 'TaxServiceReport', true);
    if ($taxServiceReport) {
        $taxServiceReportDecoded = json_decode($taxServiceReport, true);
        $taxServiceTin = get_option('hkd_tax_service_tin');
        $taxServiceGlobalAtgCode = get_option('hkd_tax_service_atg_code');
        $taxServiceGlobalUnitValue = get_option('hkd_tax_service_units_value');
        $taxServiceTaxType = get_option('hkd_tax_service_tax_type');
        $taxServiceVatPercent = get_option('hkd_tax_service_vat_percent');
        $taxServiceCheckBothType = get_option('hkd_tax_service_check_both_type');
        $taxServiceTreasurer = get_option('hkd_tax_service_treasurer');
        $taxServiceShippingAtgCode = get_option('hkd_tax_service_shipping_atg_code');
        $taxServiceShippingDescription = get_option('hkd_tax_service_shipping_description');
        $taxServiceShippingGoodCode = get_option('hkd_tax_service_shipping_good_code');
        $taxServiceShippingUnitValue = get_option('hkd_tax_service_shipping_unit_value');
        $taxServiceShippingActivated = get_option('hkd_tax_service_shipping_activated');
        $items = $order->get_items();
        $taxServiceVerificationCodeSameSKU = get_option('hkd_tax_service_verification_code_same_sku');
        $count = 0;
        wp_enqueue_style('bootstrap-css-checkout', $tax_service_plugin_url . "admin/assets/css/bootstrap.min.css");
        $vatPrice = 0;
        $withoutPrice = 0;
        if ($taxServiceTaxType === 'vat' && $taxServiceCheckBothType != 'no') {
            foreach ($items as $item) {
                $checkIsVaTValue = get_post_meta($item->get_product_id(), 'checkIsVaT', true);
                if ($checkIsVaTValue === 'yes') {
                    $vatPrice += $item->get_total();
                } else {
                    $withoutPrice += $item->get_total();
                }
            }
        } else if ($taxServiceTaxType === 'vat') {
            foreach ($items as $item) {
                $vatPrice += $item->get_total();
            }
            if ($order->get_shipping_total() > 0 && $taxServiceShippingActivated) {
                $vatPrice += (float)$order->get_shipping_total();
            }
        }
        $isSale = false;
        $salePrice = 0;
        foreach ($items as $item) {
            $product = $item->get_product();
            if ($product->is_on_sale()) {
                $isSale = true;
                break;
            }
        }
        $receiptTime = date("Y-m-d H:i:s", substr($taxServiceReportDecoded['time'], 0, 10));


        $printQr = explode(',', $taxServiceReportDecoded['qr']);
        foreach ($printQr as $item) {
            if (strpos($item, 'Receipt_ID') !== false) {
                $receiptId = str_replace('Receipt_ID:', '', $item);
                $receiptId = sprintf("%08d", $receiptId);

            }
        }

        if (!function_exists('get_http_response_code_checkout')) {
            function get_http_response_code_checkout($url)
            {
                $headers = get_headers($url);
                return substr($headers[0], 9, 3);
            }
        }

        if (get_http_response_code_checkout('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . str_replace(' ', '', esc_html($taxServiceReportDecoded['qr']))) != "200") {
            if (get_http_response_code_checkout('https://quickchart.io/chart?cht=qr&chs=300x300&chl=' . str_replace(' ', '', esc_html($taxServiceReportDecoded['qr']))) != "200") {
                $QRUrl = 'https://qrcode.tec-it.com/API/QRCode?data=' . str_replace(' ', '', esc_html($taxServiceReportDecoded['qr']));
            } else {
                $QRUrl = 'https://quickchart.io/chart?cht=qr&chs=300x300&chl=' . str_replace(' ', '', esc_html($taxServiceReportDecoded['qr']));
            }
        } else {
            $QRUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . str_replace(' ', '', esc_html($taxServiceReportDecoded['qr']));
        }
        ?>
        <div class="container">
            <div class="card mt-3">
                <div class="card-header"> ԿՏՐՈՆԻ ՖԻՍԿԱԼ ՀԱՄԱՐ
                    <strong><?php echo esc_html($taxServiceReportDecoded['fiscal']) ?></strong> <span
                            class="float-right"> <strong>ՊԱՏՎԵՐԻ ՀԱՄԱՐ</strong> <?php echo esc_html($order_id) ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-5">
                        <div class="mt-4 col-xl-6 col-lg-6 col-md-6 col-sm-12">
                            <h6>Կազմակերպության տվյալներ</h6>
                            <div><strong><?php echo esc_html($taxServiceReportDecoded['taxpayer']) ?></strong>
                            </div>
                            <div><strong><?php echo esc_html($taxServiceReportDecoded['address']) ?></strong></div>
                            <div>ՀՎՀՀ՝ <strong><?php echo esc_html($taxServiceTin) ?></strong></div>
                            <div>ԳՀ՝ <strong><?php echo esc_html($taxServiceReportDecoded['crn']) ?></strong></div>
                            <div>ՍՀ՝ <strong><?php echo esc_html($taxServiceReportDecoded['sn']) ?></strong></div>
                            <div>Կայք՝ <strong><?php echo esc_html(get_bloginfo('url')) ?></strong></div>
                        </div>
                        <div class="mt-4 col-xl-4 col-lg-4 col-md-6 col-sm-12">
                            <h6>Հաճախորդի տվյալներ</h6>
                            <div>
                                <strong><?php echo esc_html($order->get_billing_first_name()) . ' ' . esc_html($order->get_billing_last_name()); ?></strong>
                            </div>
                            <?php if ($order->get_billing_address_1()): ?>
                                <div>Հասցե՝ <strong><?php echo esc_html($order->get_billing_address_1()); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div>Էլ-հասցե՝ <strong><?php echo esc_html($order->get_billing_email()); ?></strong></div>
                            <div>Հեռախոս՝ <strong><?php echo esc_html($order->get_billing_phone()); ?></strong></div>
                        </div>
                        <div class="mt-4 col-xl-2 col-lg-2 col-md-12 col-sm-12 d-flex justify-content-lg-end justify-content-md-center justify-content-xs-start">
                            <div class="row align-items-center" style="font-size: 0.9rem;">
                                <div class="col-auto"><img
                                            src="<?php echo $QRUrl; ?>"
                                            class="img-fluid" alt="" style="max-width: 114px;"></div>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($receiptId)): ?>
                        <div class="row" style="margin-top: 20px;">
                            <div class="mt-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                <div>Էլեկտրոնային կտրոնի հերթական համար (ԿՀ) -
                                    <strong><?php echo esc_html($receiptId) ?></strong></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="row" style="margin-top: 20px;">
                        <div class="mt-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                            <div>Կտրոնի գրանցման/տպման ամիս ամսաթիվ ու ժամ: (&#177; 4)
                                <strong><?php echo esc_html($receiptTime); ?></strong></div>
                        </div>
                    </div>
                    <div class="row mb-2 mt-12">
                        <div class="mt-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                            <div>ԳԱՆՁԱՊԱՀ ։ <strong><?php echo esc_html($taxServiceTreasurer) ?></strong></div>
                        </div>
                    </div>
                    <div class="row mb-3" style="margin-top: 20px;">
                        <div class="mt-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                            <?php
                            switch ($taxServiceTaxType) {
                                case 'vat':
                                    echo '<div><strong> ԲԱԺԻՆ՝ 1 </strong></div>';
                                    echo '<div>/ԱԱՀ-ով հարկվող / = ' . esc_html(number_format((float)$vatPrice, 2)) . '</div>';
                                    break;
                                case 'without_vat':
                                    echo '<div><strong>ԲԱԺԻՆ՝ 2</strong></div>';
                                    echo '<div>/ԱԱՀ-ով չհարկվող/ = ' . esc_html(number_format((float)$taxServiceReportDecoded['total'], 2)) . '</div>';
                                    break;
                                case 'around_tax':
                                    echo '<div><strong>ԲԱԺԻՆ՝ 3</strong></div>';
                                    echo '<div>/Շրջ. հարկ/ = ' . esc_html(number_format((float)$taxServiceReportDecoded['total'], 2)) . '</div>';
                                    break;
                                case 'micro':
                                    echo '<div><strong>ԲԱԺԻՆ՝ 7</strong></div>';
                                    echo '<div>/Միկրոձեռնարկատիրություն/ = ' . esc_html(number_format((float)$taxServiceReportDecoded['total'], 2)) . '</div>';
                                    break;
                            }
                            ?>

                            <?php if ($taxServiceTaxType === 'vat'): ?>
                                <div>Որից ԱԱՀ
                                    = <?php echo esc_html(number_format((float)(($vatPrice * $taxServiceVatPercent) / 100), 2)); ?></div>
                            <?php else: ?>
                                <div>Որից ԱԱՀ = 0.00</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive-sm" style="overflow: auto; margin-top: 20px;">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th class="center" style="color:#3B4B58;">#</th>
                                <th style="color:#3B4B58;">ԱՏԳ կոդ</th>
                                <th style="color:#3B4B58;">Ապրանքի Կոդ</th>
                                <th class="right" style="color:#3B4B58;">Նկարագրություն</th>
                                <th class="center" style="color:#3B4B58;">Քանակ</th>
                                <th class="right" style="color:#3B4B58;">Չափման միավոր</th>
                                <th class="right" style="color:#3B4B58;">Միավորի գին</th>
                                <?php if ($isSale): ?>
                                    <th class="right" style="color:#3B4B58;">Զեղչ</th>
                                <?php endif; ?>
                                <th class="right" style="color:#3B4B58;">Ընդամենը</th>
                            </tr>
                            </thead>
                            <tbody>

                            <?php foreach ($items as $item):
                                $count++;
                                $product = $item->get_product();

                                $productAtgCode = get_post_meta($item->get_product_id(), 'atgCode', true);
                                $productUnitsValue = get_post_meta($item->get_product_id(), 'unitValue', true);
                                $checkIsVaTValue = get_post_meta($item->get_product_id(), 'checkIsVaT', true);
                                $productValidationCode = get_post_meta($item->get_product_id(), 'validationCode', true);
                                $itemAtgCode = ($productAtgCode) ? $productAtgCode : $taxServiceGlobalAtgCode;
                                $validationCode = ($productValidationCode) ? $productValidationCode : (($taxServiceVerificationCodeSameSKU != 'no') ? $product->get_sku() : 'no');
                                $itemUnit = ($productUnitsValue && $productAtgCode) ? $productUnitsValue : $taxServiceGlobalUnitValue;
                                if ($checkIsVaTValue !== 'yes' && $taxServiceCheckBothType != 'no' && $taxServiceTaxType === 'vat') continue;
                                ?>
                                <tr>
                                    <td class="center"><?php echo esc_html($count) ?></td>
                                    <td class="left strong"><?php echo esc_html($itemAtgCode) ?></td>
                                    <td class="left"><?php echo esc_html($validationCode) ?></td>
                                    <td class="right"><?php echo esc_html($item->get_name()) ?></td>
                                    <td class="center"><?php echo esc_html($item->get_quantity()) ?></td>
                                    <td class="center"><?php echo esc_html($itemUnit) ?></td>
                                    <td class="center"><?php echo esc_html($product->get_regular_price()) . ' դրամ'; ?></td>
                                    <?php if ($isSale): ?>
                                        <td class="right"><?php if ($product->is_on_sale()) {
                                                $isSale = true;
                                                $salePrice += ((float)$product->get_regular_price() - (float)$product->get_sale_price());
                                                echo $product->get_regular_price() - $product->get_sale_price() . ' դրամ';
                                            } ?></td>
                                    <?php endif; ?>
                                    <td class="center"><?php echo esc_html($item->get_total()) . ' դրամ'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($order->get_shipping_total() > 0 && $taxServiceShippingActivated): $count++; ?>
                                <tr>
                                    <td class="center"><?php echo esc_html($count) ?></td>
                                    <td class="left strong"><?php echo esc_html($taxServiceShippingAtgCode) ?></td>
                                    <td class="left"><?php echo esc_html($taxServiceShippingGoodCode) ?></td>
                                    <td class="right"><?php echo esc_html($taxServiceShippingDescription) ?></td>
                                    <td class="center">1</td>
                                    <td class="right"><?php echo esc_html($taxServiceShippingUnitValue) ?></td>
                                    <td class="right"><?php echo esc_html($order->get_shipping_total()) . ' դրամ'; ?></td>
                                    <?php if ($isSale): ?>
                                        <td class="right"></td>
                                    <?php endif; ?>
                                    <td class="right"><?php echo esc_html($order->get_shipping_total()) . ' դրամ'; ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($taxServiceCheckBothType != 'no' && $taxServiceTaxType === 'vat' && $withoutPrice != 0): ?>
                        <div class="row mb-3" style="margin-top: 20px;">
                            <div class="mt-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                <div><strong>ԲԱԺԻՆ՝ 2</strong></div>
                                <div> /ԱՀՀ-ով չհարկվող/ = <?php echo esc_html(number_format((float)$withoutPrice, 2)) ?>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive-sm" style="overflow: auto;box-sizing: border-box;">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th class="center" style="color:#3B4B58;">#</th>
                                    <th style="color:#3B4B58;">ԱՏԳ կոդ</th>
                                    <th style="color:#3B4B58;">Ապրանքի Կոդ</th>
                                    <th class="right" style="color:#3B4B58;">Նկարագրություն</th>
                                    <th class="center" style="color:#3B4B58;">Քանակ</th>
                                    <th class="right" style="color:#3B4B58;">Չափման միավոր</th>
                                    <th class="right" style="color:#3B4B58;">Միավորի գին</th>
                                    <?php if ($isSale): ?>
                                        <th class="right" style="color:#3B4B58;">Զեղչ</th>
                                    <?php endif; ?>
                                    <th class="right" style="color:#3B4B58;">Ընդամենը</th>
                                </tr>
                                </thead>
                                <tbody>

                                <?php foreach ($items as $item):
                                    $count++;
                                    $product = $item->get_product();
                                    $productAtgCode = get_post_meta($item->get_product_id(), 'atgCode', true);
                                    $productUnitsValue = get_post_meta($item->get_product_id(), 'unitValue', true);
                                    $checkIsVaTValue = get_post_meta($item->get_product_id(), 'checkIsVaT', true);
                                    $productValidationCode = get_post_meta($item->get_product_id(), 'validationCode', true);
                                    $itemAtgCode = ($productAtgCode) ? $productAtgCode : $taxServiceGlobalAtgCode;
                                    $validationCode = ($productValidationCode) ? $productValidationCode : (($taxServiceVerificationCodeSameSKU != 'no') ? $product->get_sku() : 'no');
                                    $itemUnit = ($productUnitsValue && $productAtgCode) ? $productUnitsValue : $taxServiceGlobalUnitValue;
                                    if ($checkIsVaTValue === 'yes') continue;
                                    ?>
                                    <tr>
                                        <td class="center"><?php echo esc_html($count) ?></td>
                                        <td class="left strong"><?php echo esc_html($itemAtgCode); ?></td>
                                        <td class="left"><?php echo esc_html($validationCode); ?></td>
                                        <td class="right"><?php echo esc_html($item->get_name()); ?></td>
                                        <td class="center"><?php echo esc_html($item->get_quantity()) ?></td>
                                        <td class="center"><?php echo esc_html($itemUnit) ?></td>
                                        <td class="center"><?php echo esc_html($product->get_regular_price()); ?></td>
                                        <?php if ($isSale): ?>
                                            <td class="right"><?php if ($product->is_on_sale()) {
                                                    $isSale = true;
                                                    $salePrice += ((float)$product->get_regular_price() - (float)$product->get_sale_price());
                                                    echo $product->get_regular_price() - $product->get_sale_price() . ' դրամ';
                                                } ?></td>
                                        <?php endif; ?>
                                        <td class="center"><?php echo esc_html($item->get_total()); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>


                    <?php endif; ?>

                    <div class="row mb-3" style="margin-top: 20px;">
                        <div class="col-lg-4 col-sm-5"><strong> (Ֆ)</strong></div>
                        <div class="col-lg-6 col-sm-5 ml-auto text-right">
                            <div class="mt-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                                <div>
                                    <strong>Ընդամենը՝&nbsp;&nbsp;&nbsp;<?php echo esc_html(number_format((float)$taxServiceReportDecoded['total'], 2)) ?></strong>
                                </div>
                                <?php if ($isSale): ?>
                                    <div>
                                        <strong>Զեղչ՝&nbsp;&nbsp;&nbsp;<?php echo esc_html(number_format((float)$salePrice, 2)); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order->get_payment_method() == 'cod'): ?>
                                    <div>
                                        <strong>Առձեռն՝&nbsp;&nbsp;&nbsp;<?php echo esc_html(number_format((float)$taxServiceReportDecoded['total'], 2)) ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order->get_payment_method() != 'cod'): ?>
                                    <div>
                                        <strong>Անկանխիկ՝&nbsp;&nbsp;&nbsp;<?php echo esc_html(number_format((float)$taxServiceReportDecoded['total'], 2)) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
