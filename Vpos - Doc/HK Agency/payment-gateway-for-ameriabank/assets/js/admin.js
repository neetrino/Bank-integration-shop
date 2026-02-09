var $ = jQuery;
$(document).ready(function () {

    checkCheckboxes();

    $(document).on('change', '#woocommerce_hkd_ameriabank_save_card', function () {
        checkCheckboxes();
    });

    $(document).on('change', '#woocommerce_hkd_ameriabank_secondTypePayment', function () {
        if($(this).is(':checked')) {
            $('#woocommerce_hkd_ameriabank_successOrderStatus').val('processing').trigger('change').attr('disabled', 'disabled');
        }else{
            $('#woocommerce_hkd_ameriabank_successOrderStatus').prop('disabled', false);
        }
    });

    $(document).on('mouseover', '․wrap-ameriabank .woocommerce-help-tip', function () {
        let parentId = $(this).parent().attr('for');
        if (parentId === 'woocommerce_hkd_ameriabank_save_card_button_text') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScriptAmeria.pluginsUrl + 'assets/images/bindingnew.jpg" width="300">');
        } else if(parentId === 'woocommerce_hkd_ameriabank_save_card_header') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScriptAmeria.pluginsUrl + 'assets/images/payment.jpg" width="300">');
        }else if(parentId === 'woocommerce_hkd_ameriabank_save_card_use_new_card'){
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScriptAmeria.pluginsUrl + 'assets/images/newcard.jpg" width="300">');
        }else{
            $('#tiptip_content').css({'max-width': '150px'});
        }
    });

    function checkCheckboxes() {
        $('.hiddenValueAmeria').parents('tr').hide();
        let saveCardMode = $('#woocommerce_hkd_ameriabank_save_card').is(':checked');
        if (saveCardMode) {
            $('.saveCardInfoAmeria').parents('tr').show();
        } else {
            $('.saveCardInfoAmeria').parents('tr').hide();
        }
    }

    $('.terms_div').hide();

    $('a#toggle-terms_div').click(function () {
        if($('.terms_div').is(':visible')){
            $(".wrap-ameriabank").css("width", "45%");
        }else{
            $(".wrap-ameriabank").css("width", "60%");
        }
        $('.terms_div').slideToggle(500);
        return false;
    });
    $("#mainform .woocommerce-save-button").click(function () {
        if (
            ($('#terms').length &&  $("#terms").is(':checked')) || (!$('#terms').length)
        ) {
            return true;
        } else {
            $('.accept_terms_div').addClass('custom-select is-invalid');
            $('#terms-error').text('Խնդրում ենք համաձայնվել Հավելվածի Պայմաններ և դրույթներ -ին').show();

            return false;
        }
    });
});
