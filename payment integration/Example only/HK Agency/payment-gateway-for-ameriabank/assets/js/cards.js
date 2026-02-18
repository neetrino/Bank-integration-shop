/*
* Ajax to Delete Binding from user
 */
var $ = jQuery;
$(document).ready(function () {
    $(document).on('click', '.hk-remove-card-ameria', function () {
        let currentElement = $(this);
        let bindingId  = $(this).attr('data-id');
        $.ajax({
            type: "post",
            dataType: "json",
            url: "/wc-api/delete_binding_ameria",  //this is wordpress Woocomerce file which is already avaiable in wordpress
            data: {bindingId: bindingId},
            dataType: 'json',
            success: function(response){
                if(response.status){
                    currentElement.parent().remove();
                }
            }
        });
    })
});
