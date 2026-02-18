var $ = jQuery;

$( document ).ready(function() {

    $('.print-tax-service').on('click', function(e){
        let orderId = $(this).attr('data-id');
        $.ajax({
            type: "POST",
            dataType: "json",
            url: my_ajax_object.ajax_url,
            data: {
                action: 'getPrintBody',
                type: 'print',
                orderId: orderId,
                _ajax_nonce: my_ajax_object.nonce
            },
            success: function (res) {
                printContent(res.html);
            },
            error: function () {
            }
        });
    });
    $('.printRefund-tax-service').on('click', function(e){
        let orderId = $(this).attr('data-id');
        $.ajax({
            type: "POST",
            dataType: "json",
            url: my_ajax_object.ajax_url,
            data: {
                action: 'getPrintBody',
                type: 'refund',
                orderId: orderId,
                _ajax_nonce: my_ajax_object.nonce
            },
            success: function (res) {
                printContent(res.html);
            },
            error: function () {
            }
        });
    });

    function printContent(printDoc){
        var mywindow = window.open('', 'PRINT', 'height=700,width=1000');
        mywindow.document.write(printDoc);
        mywindow.document.close();
        mywindow.focus();
        setTimeout(function () {
            mywindow.print();

            mywindow.close();

        }, 1000)
        return true;
    }
});




