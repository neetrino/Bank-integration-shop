<?php

if (!class_exists('ProductSettingsController')) {
    class ProductSettingsController
    {
        public $plugin_url;
        private $taxServiceVerificationCodeSameSKU;
        private $taxServiceCheckBothType;

        public function __construct()
        {
            global $tax_service_plugin_url;

            $this->plugin_url = $tax_service_plugin_url;
            $this->taxServiceVerificationCodeSameSKU = get_option('hkd_tax_service_verification_code_same_sku');
            $this->taxServiceCheckBothType = get_option('hkd_tax_service_check_both_type');
            // Step 1 - Adding a custom tab to the Products Metabox
            add_filter('woocommerce_product_data_tabs', array($this, 'add_tax_service_product_data_tab'), 99, 1);

            // Step 2 - Adding and POPULATING (with data) custom fields in custom tab for Product Metabox
            add_action('woocommerce_product_data_panels', array($this, 'add_tax_service_product_data_fields'));

            // Step 3 - Saving custom fields data of custom products tab metabox
            add_action('woocommerce_process_product_meta', array($this, 'tax_service_process_product_meta_fields_save'));
        }

        public function add_tax_service_product_data_tab($product_data_tabs)
        {
            $product_data_tabs['tax-service'] = array(
                'label' => __('Էլեկտրոնային ՀԴՄ', 'tax-service'), // translatable
                'target' => 'tax_service_product_data', // translatable
            );
            return $product_data_tabs;
        }

        public function add_tax_service_product_data_fields()
        {
            global $post;
            $post_id = $post->ID;
            $atgCode = get_post_meta($post_id, 'atgCode', true);
            $unitsValue = get_post_meta($post_id, 'unitValue', true);
            $checkIsVaTValue = get_post_meta($post_id, 'checkIsVaT', true);
            $validationCode = get_post_meta($post_id, 'validationCode', true);

            echo '<div id="tax_service_product_data" class="panel woocommerce_options_panel">';
            $units = ATGCodeService::getUnits();
            $atgCodes = ATGCodeService::getATGCodes();

            wp_enqueue_script('hkd-tax-service-product-settings', $this->plugin_url . "admin/assets/js/productSetting.js", array(), microtime());
            wp_localize_script('hkd-tax-service-product-settings', 'atgCodes_object', array('atgCodes' => $atgCodes, 'atgUndefinedMessage' => __('Մեր հավելման ցանկում նման ԱՏԳ կոդ չի հայտնաբերվել', 'tax-service')));

            // 1. atgCode input field
            woocommerce_wp_text_input(array(
                'id' => 'atgCode',
                'name' => 'atgCode',
                'class' => 'atgCode_input',
                'placeholder' => __('Ապրանքի ԱՏԳ կոդ', 'tax-service'), // (optional)
                'label' => __('Ապրանքի ԱՏԳ կոդ', 'tax-service'), // (optional)
                'description' => __('Պետք է լինի համապատասխան ցանկից, որը կարելի է գտնել taxservice.am կայքում մուտք գործելով ԷԼԵԿՏՐՈՆԱՅԻՆ ԾԱՌԱՅՈՒԹՅՈՒՆՆԵՐ բաժնի ՆՈՐ ՍԵՐՆԴԻ ՀՍԿԻՉ-ԴՐԱՄԱՐԿՂԱՅԻՆ ՄԵՔԵՆԱՆԵՐ ենթաբաժին, որտեղ վերջում կան երկու Excel փաստաթղթեր՝ N 1406-Ն և N 875-Ն որոնք և պարունակում են ԱՏԳ կոդեր:', 'tax-service'), // (optional)
                'desc_tip' => true,
                'type' => 'number',
                'custom_attributes' => array('step' => 'any', 'min' => '0'),
                'value' => $atgCode,
            ));

            // 2. Select field Units
            woocommerce_wp_select(array(
                'id' => 'unitValue',
                'name' => 'unitValue',
                'label' => __('Ապրանքի չափման միավոր', 'tax-service'),
                'options' => $units,
                'value' => $unitsValue,
            ));


            if ($this->taxServiceCheckBothType === 'yes') {
                // 3. Checkbox field checkIsVaT
                woocommerce_wp_checkbox(array(
                    'id' => 'checkIsVaT',
                    'style' => 'margin: 5px 0!important; vertical-align: middle; float: left',
                    'name' => 'checkIsVaT',
                    'class' => 'checkIsVaT-checkbox',
                    'label' => __('ԱԱՀ-ով չհարկվող ապրանք', 'tax-service'),
                    'description' => __('Եթե այս ապրանքը ԱԱՀ-ով հարկվող չէ ապա ակտիվացրեք այս գործառույթը', 'tax-service'),
                    'desc_tip' => true,
                    'value' => $checkIsVaTValue,
                ));
            }

            if ($this->taxServiceVerificationCodeSameSKU !== 'yes') {
                // 4. validation code input field
                woocommerce_wp_text_input(array(
                    'id' => 'validationCode',
                    'name' => 'validationCode',
                    'class' => 'validationCode_input',
                    'placeholder' => __('Ապրանքի կոդ', 'tax-service'),
                    'label' => __('Ապրանքի կոդ', 'tax-service'),
                    'description' => __('Առավելագույնը 50 նիշ, չի կարող լինել դատարկ', 'tax-service'),
                    'desc_tip' => true,
                    'type' => 'text',
                    'value' => $validationCode,
                ));
            }
            echo '</div>';
        }

        public function tax_service_process_product_meta_fields_save($post_id)
        {
            // save atg code
            if (isset($_POST['atgCode']))
                update_post_meta($post_id, 'atgCode', sanitize_text_field($_POST['atgCode']));

            // save unit value
            if (isset($_POST['unitValue']))
                update_post_meta($post_id, 'unitValue', sanitize_text_field($_POST['unitValue']));

            // save check is vat
            if (isset($_POST['checkIsVaT']) && $this->taxServiceCheckBothType === 'yes')
                update_post_meta($post_id, 'checkIsVaT', sanitize_text_field($_POST['checkIsVaT']));

            // save validation code
            if (isset($_POST['validationCode']) && $this->taxServiceVerificationCodeSameSKU !== 'yes')
                update_post_meta($post_id, 'validationCode', sanitize_text_field($_POST['validationCode']));
        }
    }
}

new ProductSettingsController();
