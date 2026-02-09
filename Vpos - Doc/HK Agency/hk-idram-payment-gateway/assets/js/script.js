var $ = jQuery;
$(document).ready(function () {

    checkEnableRocketLine();

    $(document).on('change', '#woocommerce_hk-idram-payment-gateway_enabledRocketLine', function () {
        checkEnableRocketLine();
    });

    function checkEnableRocketLine() {
        $('.hiddenValueIdram').parents('tr').hide();
        let enableRocketLine = $('#woocommerce_hk-idram-payment-gateway_enabledRocketLine').is(':checked');
        if (enableRocketLine) {
            $('.tooltipTypeIdram').parents('tr').show();
        } else {
            $('.tooltipTypeIdram').parents('tr').hide();
        }
    }
});
