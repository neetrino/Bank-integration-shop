var $ = jQuery;
$(document).ready(function () {
    checkCheckboxes();

    $(document).on('change', '#woocommerce_hkd_inecobank_save_card, #woocommerce_hkd_inecobank_testmode, #woocommerce_hkd_inecobank_two_url_sent', function () {
        checkCheckboxes();
    });

    $(document).on('change', '#woocommerce_hkd_inecobank_secondTypePayment', function () {
        if($(this).is(':checked')) {
            $('#woocommerce_hkd_inecobank_successOrderStatus').val('processing').trigger('change').attr('disabled', 'disabled');
        }else{
            $('#woocommerce_hkd_inecobank_successOrderStatus').prop('disabled', false);
        }
    });

    $(document).on('mouseover', '.wrap-inecobank .woocommerce-help-tip', function () {
        let parentId = $(this).parent().attr('for');
        if (parentId === 'woocommerce_hkd_inecobank_save_card_button_text') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="' + myScriptIneco.pluginsUrl + 'assets/images/bindingnew.jpg" width="300">');
        } else if (parentId === 'woocommerce_hkd_inecobank_save_card_header') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="' + myScriptIneco.pluginsUrl + 'assets/images/payment.jpg" width="300">');
        } else if (parentId === 'woocommerce_hkd_inecobank_save_card_use_new_card') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="' + myScriptIneco.pluginsUrl + 'assets/images/newcard.jpg" width="300">');
        } else {
            $('#tiptip_content').css({'max-width': '150px'});
        }
    });

    function checkCheckboxes() {
        $('.hiddenValueIneco').parents('tr').hide();
        let saveCardMode = $('#woocommerce_hkd_inecobank_save_card').is(':checked');
        let testMode = $('#woocommerce_hkd_inecobank_testmode').is(':checked');
        let urlOption = $('#woocommerce_hkd_inecobank_two_url_sent').is(':checked');
        console.log(testMode);
        if (saveCardMode) {
            $('.saveCardInfoIneco').parents('tr').show();
        } else {
            $('.saveCardInfoIneco').parents('tr').hide();
        }
        if (testMode) {
            $('.testModeInfoIneco').parents('tr').show();
        } else {
            $('.testModeInfoIneco').parents('tr').hide();
        }
        console.log(urlOption)
        if (urlOption) {
            $('.failed_url_ineco').show();
        } else {
            $('.failed_url_ineco').hide();
        }
    }


    $('.terms_div_ineco').hide();

    $('a#toggle-terms_div_ineco').click(function () {
        if($('.terms_div_ineco').is(':visible')){
            $(".wrap-inecobank").css("width", "45%");
        }else{
            $(".wrap-inecobank").css("width", "60%");
        }
        $('.terms_div_ineco').slideToggle(500);
        return false;
    });
    $("#mainform .woocommerce-save-button").click(function () {
        if (
            ($('#terms_ineco').length &&  $("#terms_ineco").is(':checked')) || (!$('#terms_ineco').length)
        ) {
            return true;
        } else {
            $('.accept_terms_div_ineco').addClass('custom-select is-invalid');
            $('#terms-error_ineco').text('Խնդրում ենք համաձայնվել Հավելվածի Պայմաններ և դրույթներ -ին').show();

            return false;
        }
    });
});
