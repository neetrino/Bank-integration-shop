var $ = jQuery;
$(document).ready(function () {
    $('.terms_div').hide();

    $('a#toggle-terms_div').click(function () {
        if($('.terms_div').is(':visible')){
            $(".wrap-fastshift").css("width", "45%");
        }else{
            $(".wrap-fastshift").css("width", "60%");
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

    $('#copyToClipboard').click(function () {
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val($('#ipAddress').text()).select();
        document.execCommand("copy");
        $temp.remove();
    });

});