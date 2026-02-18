/*
* Ajax to Delete Binding from user (with nonce support and robust response handling)
*/
var $ = jQuery;
$(document).ready(function () {
    $(document).on('click', '.svg-trash-idbank', function (e) {
        e.preventDefault();
        var $current = $(this);
        var bindingId = $current.attr('data-id') || '';
        if (!bindingId) { return; }

        // Read nonce from the nearest container rendered by PHP
        var $container = $current.closest('#hkdigital_binding_info_idbank');
        var nonce = ($container.data('delete-binding-nonce') || '').toString();

        // Resolve endpoint URL from container (supports subdirectory installs); fallback to default
        var apiUrl = ($container.data('api-url') || '/wc-api/delete_binding_idbank').toString();
        $.ajax({
            type: 'POST',
            url: apiUrl,
            dataType: 'json',
            data: {
                bindingId: bindingId,
                nonce: nonce
            },
            success: function (response) {
                // Support both legacy `{ status: true }` and WP `wp_send_json_success({status:true})`
                var ok = false;
                if (response) {
                    if (response.status === true) { ok = true; }
                    else if (response.success === true && response.data && response.data.status === true) { ok = true; }
                }
                if (ok) {
                    $current.parent().remove();
                } else {
                    // Optionally, handle/display error states silently
                    // console.warn('Delete binding failed', response);
                }
            },
            error: function () {
                // Optionally, handle network/server errors silently
                // console.error('Network error while deleting binding');
            }
        });
    });
});
