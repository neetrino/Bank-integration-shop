var $ = jQuery;
$(document).ready(function () {

    checkCheckboxes();

    $(document).on('change', '#woocommerce_hkd_idbank_save_card, #woocommerce_hkd_idbank_testmode', function () {
        checkCheckboxes();
    });

    $(document).on('mouseover', '.wrap-idbank .woocommerce-help-tip', function () {
        let parentId = $(this).parent().attr('for');
        if (parentId === 'woocommerce_hkd_idbank_save_card_button_text') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScriptIdBank.pluginsUrl + 'assets/images/bindingnew.jpg" width="300">');
        } else if(parentId === 'woocommerce_hkd_idbank_save_card_header') {
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScriptIdBank.pluginsUrl + 'assets/images/payment.jpg" width="300">');
        }else if(parentId === 'woocommerce_hkd_idbank_save_card_use_new_card'){
            $('#tiptip_content').css({
                'max-width': '300px',
                'width': '300px'
            }).html('<img src="'+ myScriptIdBank.pluginsUrl + 'assets/images/newcard.jpg" width="300">');
        }else{
            $('#tiptip_content').css({'max-width': '150px'});
        }
    });

    function checkCheckboxes() {
        $('.hiddenValueIdBank').parents('tr').hide();
        let saveCardMode = $('#woocommerce_hkd_idbank_save_card').is(':checked');
        let testMode = $('#woocommerce_hkd_idbank_testmode').is(':checked');
        if (saveCardMode) {
            $('.saveCardInfoIdBank').parents('tr').show();
        } else {
            $('.saveCardInfoIdBank').parents('tr').hide();
        }

        if(testMode){
            $('.testModeInfoIdBank').parents('tr').show();
        }else{
            $('.testModeInfoIdBank').parents('tr').hide();
        }

    }

    $('.terms_div_idbank').hide();

    $('a#toggle-terms_div_idbank').click(function () {
        if($('.terms_div_idbank').is(':visible')){
            $("#pluginMainWrap").css("width", "45%");
        }else{
            $("#pluginMainWrap").css("width", "60%");
        }
        $('.terms_div_idbank').slideToggle(500);
        return false;
    });
    $("#mainform .woocommerce-save-button").click(function () {
        if (
            ($('#terms_idbank').length &&  $("#terms_idbank").is(':checked')) || (!$('#terms_idbank').length)
        ) {
            return true;
        } else {
            $('.accept_terms_div_idbank').addClass('custom-select is-invalid');
            $('#terms-error_idbank').text('Խնդրում ենք համաձայնվել Հավելվածի Պայմաններ և դրույթներ -ին').show();
            return false;
        }
    });
});
