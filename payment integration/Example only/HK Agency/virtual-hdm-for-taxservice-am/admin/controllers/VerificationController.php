<?php

function checkTaxServiceVerification()
{
    if (!class_exists('VerificationController')) {
        class VerificationController
        {
            private $taxServiceVerificationId;
            private $ownerSiteUrl = 'https://plugins.hkdigital.am/api/';

            public function __construct()
            {
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(['message' => __('Անբավարար իրավասություն', 'tax-service')], 403);
                }
                check_ajax_referer('taxservice_admin');

                $this->taxServiceVerificationId = isset($_REQUEST['verificationId']) ? sanitize_text_field(wp_unslash($_REQUEST['verificationId'])) : '';
                $this->validateFields();
            }

            public function validateFields()
            {
                if ($this->taxServiceVerificationId === '') {
                    wp_send_json_error(['message' => __('Խնդրում ենք անցնել նույնականացում։ Հարցերի դեպքում զանգահարել 033 779-779 հեռախոսահամարով։', 'tax-service')]);
                }
                update_option('hkd_tax_service_verification_id', $this->taxServiceVerificationId);
                $response = wp_remote_post($this->ownerSiteUrl .
                    'bank/tax-service/checkActivation', [
                    'headers' =>
                        array('Accept' => 'application/json'),
                    'sslverify' => false,
                    'body' => [
                        'domain' => isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : '',
                        'checkoutId' => $this->taxServiceVerificationId,
                        'lang' => 'hy'
                    ]]);
                if (!is_wp_error($response)) {
                    $body = isset($response['body']) ? $response['body'] : '';
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        wp_send_json_success($decoded);
                    }
                    wp_send_json_success(['message' => $body]);
                } else {
                    wp_send_json_error(['message' => __('Հարցման խնդիր։', 'tax-service')]);
                }
            }
        }
    }

    new VerificationController();
}
