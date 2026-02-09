<?php

add_action('init', 'redirectIdram', 9);
if (!function_exists('redirectIdram')) {
function redirectIdram()
{
    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'redirect_idram_form' &&
        isset($_REQUEST['EDP_LANGUAGE']) &&
        isset($_REQUEST['EDP_REC_ACCOUNT']) &&
        isset($_REQUEST['EDP_AMOUNT']) &&
        isset($_REQUEST['EDP_BILL_NO'])) { ?>
        <form id="redirect_to_idram" style="display: none" action="https://banking.idram.am/Payment/GetPayment"
              method="POST">
            <input type="hidden" name="EDP_LANGUAGE" value="<?php echo esc_attr($_REQUEST['EDP_LANGUAGE']) ?>">
            <input type="hidden" name="EDP_REC_ACCOUNT" value="<?php echo esc_attr($_REQUEST['EDP_REC_ACCOUNT']) ?>">
            <input type="hidden" name="EDP_DESCRIPTION" value="Order #<?php echo esc_attr($_REQUEST['EDP_BILL_NO']) ?>">
            <input type="hidden" name="EDP_AMOUNT" value="<?php echo esc_attr($_REQUEST['EDP_AMOUNT']) ?>">
            <input type="hidden" name="EDP_BILL_NO" value="<?php echo esc_attr($_REQUEST['EDP_BILL_NO']) ?>">
            <input type="submit" value="submit">
        </form>
        <script>document.addEventListener('DOMContentLoaded', function () {
                document.getElementById("redirect_to_idram").submit();
            });</script>
        <?php die;
    }
}
}