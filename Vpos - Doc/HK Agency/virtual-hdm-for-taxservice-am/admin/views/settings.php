<div class="wrapper tax-service-div mt-5">
    <div class="steps-area steps-area-absolute">
        <div class="image-holder">
            <img src="<?php echo esc_html($pluginUrl) ?>admin/assets/img/side-img.jpg" alt="">
        </div>
        <div class="steps clearfix">
            <ul class="tablist multisteps-form__progress">
                <li data-tooltip-text="Նույնականացում"
                    class="multisteps-form__progress-btn js-active <?php if (!$verifiedPlugin['success']) {
                        echo "current";
                    } ?>" data-id="0">
                    <span>1</span>
                </li>
                <li data-tooltip-text="Կազմակերպության նույնականացում"
                    class="multisteps-form__progress-btn <?php if ($verifiedPlugin['success']) {
                        echo "js-active";
                        if (!$settingsCompleted) echo "current";
                    } ?>" data-id="1">
                    <span>2</span>
                </li>
                <li data-tooltip-text="Հարկման կարգավորում"
                    class="multisteps-form__progress-btn <?php if ($verifiedPlugin['success'] && $settingsCompleted) {
                        echo "js-active";
                    } ?>" data-id="2">
                    <span>3</span>
                </li>
                <li data-tooltip-text="Ապրանքների կարգավորում"
                    class="multisteps-form__progress-btn <?php if ($verifiedPlugin['success'] && $settingsCompleted) {
                        echo "js-active";
                    } ?>" data-id="3">
                    <span>4</span>
                </li>
                <li data-tooltip-text="Կտրոնի տպման կարգավորում"
                    class="multisteps-form__progress-btn last <?php if ($verifiedPlugin['success'] && $settingsCompleted) {
                        echo "js-active current";
                    } ?>" data-id="4">
                    <span>5</span>
                </li>
            </ul>
        </div>
    </div>
    <form class="multisteps-form__form" action="" id="wizard" method="POST" enctype="multipart/form-data">
        <div class="form-area position-relative">
            <!-- div 1 -->
            <div class="multisteps-form__panel verificationStep <?php if (!$verifiedPlugin['success']) {
                echo "js-active";
            } ?>" id="verificationStep" data-animation="slideHorz">
                <div class="wizard-forms">
                    <div class="inner pb-100 clearfix">
                        <div class="form-content pera-content">
                            <div class="step-inner-content">
                                <span class="step-no bottom-line">Քայլ 1</span>
                                <div class="step-progress float-right">
                                    <span>5 քայլից 1-ը կատարված է</span>
                                    <div class="step-progress-bar">
                                        <div class="progress">
                                            <div class="progress-bar" style="width:20%"></div>
                                        </div>
                                    </div>
                                </div>
                                <h2 class="text-label-step">ՆՈՒՅՆԱԿԱՆԱՑՈՒՄ</h2>
                                <p class="mt-5 d-flex font-size-18">
                                    <?php echo __('Նույնականացում անցնելու համար կապ հաստատեք մեզ հետ նշված հեռախոսահամարով կամ էլ-հասցեով:', 'tax-service'); ?>
                                </p>
                                <div class="form-inner-area">
                                    <input type="text" name="hkd_tax_service_verification_id"
                                           id="hkd_tax_service_verification_id" class="form-control "
                                           value="<?php echo esc_html($this->taxServiceVerificationId); ?>"
                                           minlength="2"
                                           placeholder="Օրինակ՝ TaxService_gayudcsu14">
                                    <label id="hkd_tax_service_verification_id-error" class="error"
                                           for="hkd_tax_service_verification_id"></label>
                                </div>
                                <div class="blue terms_div">
                                    <iframe src="<?php echo esc_html($pluginUrl) ?>terms/terms.html" height="100%" width="100%" title="Terms Iframe"></iframe>
                                </div>
                                <?php if ($verifiedPlugin['success'] && isset($verifiedPlugin['data']['valid_at'])): ?>
                                    <span class="mt-5">
                                                <p class="font-size-18 valid_at_text font-size-18 valid_at_text pl-2 pr-2"> <i
                                                            class="fa fa-info-circle"
                                                            aria-hidden="true"></i> Վավեր է մինչև <?php echo esc_html(date("Y-m-d", strtotime($verifiedPlugin['data']['valid_at']))) ?></p>
                                            </span>
                                <?php endif; ?>
                                <div class="text-center accept_terms_div">
                                    <label class="checkbox">
                                        <input type="checkbox"
                                               class="accept_terms"
                                               name="terms" id="terms" <?php if ($verifiedPlugin['success']) {
                                            echo "checked";
                                        } ?>>
                                        <span>Ես կարդացել եմ և համաձայն եմ Հավելվածի <a
                                                    href="javascript:"
                                                    id="toggle-terms_div"><b>Պայմաններ և դրույթներ</b></a> -ին</span>&nbsp;<abbr
                                                class="required" title="required">*</abbr>
                                    </label>
                                </div>

                                <div class="mt-3 text-center">
                                    <small>
                                        <b>Սույն մոդուլի(Plugin) պարունակությունը պաշպանված է հեղինակային և հարակից
                                            իրավունքների մասին Հայաստանի Հանրապետության օրենսդրությամբ:
                                            Արգելվում է պարունակության վերարտադրումը, տարածումը, նկարազարդումը,
                                            հարմարեցումը և այլ ձևերով վերափոխումը,
                                            ինչպես նաև այլ եղանակներով օգտագործումը, եթե մինչև նման օգտագործումը ձեռք չի
                                            բերվել ԷՅՋԿԱ ԴԻՋԻՏԱԼ ԷՋԵՆՍԻ ՍՊԸ-ի թույլտվությունը:</b></small>

                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.inner -->
                    <div class="actions">
                        <ul>
                            <li class="disable" aria-disabled="true"><span class="js-btn-next" title="NEXT">Backward <i
                                            class="fa fa-angle-right"></i></span></li>
                            <li><span class="js-btn-next" title="NEXT">Հաջորդը <i class="fa fa-angle-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- div 2 -->
            <div class="multisteps-form__panel <?php if ($verifiedPlugin['success'] && !$settingsCompleted) {
                echo "js-active";
            } ?>" data-animation="slideHorz">
                <div class="wizard-forms">
                    <div class="inner pb-100 clearfix">
                        <div class="form-content pera-content">
                            <div class="step-inner-content">
                                <span class="step-no bottom-line">Քայլ 2</span>
                                <div class="step-progress float-right">
                                    <span>5 քայլից 2-ը կատարված է</span>
                                    <div class="step-progress-bar">
                                        <div class="progress">
                                            <div class="progress-bar" style="width:40%"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-inner-area">
                                    <h2><?php echo __("Կազմակերպության Նույնականացում", 'tax-service') ?></h2>
                                    <div class="mt-5">
                                        <div>
                                            <label for="hkd_tax_service_tin"
                                                   class="d-flex"><?php echo __('Կազմակերպության ՀՎՀՀ', 'tax-service') ?></label>
                                            <input class="form-control  " type="text" minlength="2"
                                                   name="hkd_tax_service_tin"
                                                   id="hkd_tax_service_tin" style=""
                                                   placeholder="154872485"
                                                   value="<?php echo esc_html($taxServiceTin) ?>"

                                            >
                                        </div>
                                        <div>
                                            <label for="hkd_tax_service_register_number"
                                                   class="d-flex"><?php echo __('Էլեկտրոնային ՀԴՄ գրանցման համար', 'tax-service') ?></label>
                                            <input class="form-control  " type="text" minlength="2"
                                                   name="hkd_tax_service_register_number"
                                                   id="hkd_tax_service_register_number"
                                                   placeholder="154872485"
                                                   value="<?php echo esc_html($taxServiceRegisterNumber) ?>"

                                            >
                                        </div>
                                        <div>
                                            <label for="hkd_tax_service_passphrase"
                                                   class="d-flex"><?php echo __('Կցագրված Ֆայլի Գաղտնաբառը', 'tax-service') ?></label>
                                            <input class="form-control" type="password" minlength="2"
                                                   name="hkd_tax_service_passphrase"
                                                   id="hkd_tax_service_passphrase"
                                                   placeholder="123456"
                                                   value="<?php echo esc_html($taxServiceFilePassphrase) ?>"
                                            >
                                        </div>
                                        <div class="upload-documents  mt-5">
                                            <div class="upload-araa">
                                                <input type="hidden" value="" name="fileContent" id="fileContent">
                                                <input type="hidden" value="" name="filename" id="filename">
                                                <div class="upload-icon float-left">
                                                    <i class="fas fa-cloud-upload-alt <?php if ($taxServiceUploadFilePath) {
                                                        echo 'uploaded';
                                                    } ?>"></i>
                                                </div>
                                                <div class="upload-text">
                                                    <span class="uploadFileText">
                                                        <?php if ($taxServiceUploadFilePath): ?>
                                                            <?php echo esc_html(basename($taxServiceUploadFilePath)) . ' Ֆայլը կցված է'; ?>
                                                        <?php else: ?>
                                                            (Կցագրվող ֆայլի ֆորմատը՝ .crt)
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="upload-option text-center">
                                                    <label for="attach">Բեռնել Սերտիֆիկատ ֆայլը</label>
                                                    <input id="attach" name="attach" style="visibility:hidden;"
                                                           type="file">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="upload-documents  mt-5">
                                            <div class="upload-araa">
                                                <input type="hidden" value="" name="fileContentKey" id="fileContentKey">
                                                <input type="hidden" value="" name="filenameKey" id="filenameKey">
                                                <div class="upload-icon float-left">
                                                    <i class="fas fa-cloud-upload-alt <?php if ($taxServiceUploadFileKey) {
                                                        echo 'uploaded';
                                                    } ?>"></i>
                                                </div>
                                                <div class="upload-text">
                                                    <span class="uploadFileTextCrt">
                                                        <?php if ($taxServiceUploadFileKey): ?>
                                                            <?php echo esc_html(basename($taxServiceUploadFileKey)) . ' Ֆայլը կցված է'; ?>
                                                        <?php else: ?>
                                                            (Կցագրվող ֆայլի ֆորմատը՝ .key)
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="upload-option text-center">
                                                    <label for="attachKey">Բեռնել հարցման Բանալի ֆայլը</label>
                                                    <input id="attachKey" name="attachKey" style="visibility:hidden;"
                                                           type="file">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="blue file_div">
                                            <h3>Էլ․ ՀԴՄ գրանցում</h3>
                                            <p>Փաստաթղթի նպատակն է ներկայացնել՝ Էլեկտրոնային ՀԴՄ-ն ՊԵԿ բազային
                                                ինտեգրելու տարբերակները, տվյալներ ուղարկելու մեթոդները։</p>
                                            <p>ՊԵԿ-ում Էլ․ ՀԴՄ գրանցելու և ՀԴՄ գրանցման համարը ստանալու համար անհրաժեշտ
                                                է տնտեսվարողի կողմից ՀՆԷՀ համակարգի Հաշվետվություններ բաժնից
                                                ներակայացնել u6 դիմում։</p>
                                            <img src="<?php echo esc_html($pluginUrl) ?>admin/assets/img/tax-service/image1.jpg"
                                                 alt="">
                                            <p>Նախքան u6 դիմում ՊԵԿ հանձնելը՝ դիմումի մեջ լրացվում է 5․ Էլեկտրոնային
                                                ՀԴՄ-ի տվյալներ բաժինը՝ Սերտիֆիկատի ստորագրման հարցում դաշտում կցելով
                                                սերտիֆիկատի ստորագրման հարցման ֆայլը (Certificate Sign Request
                                                (*.CSR))</p>
                                            <img src="<?php echo esc_html($pluginUrl) ?>admin/assets/img/tax-service/image2.gif"
                                                 alt="">
                                            <p><b>Սերտիֆիկատի ստորագրման հարցման ֆայլը (Certificate Sign Request
                                                    (*.CSR))</b> գեներացնելու համար անհրաժեշտ է կատարել հետևյալը՝</p>
                                            <ul>
                                                <li>Համակարգչի մեջ տեղադրել OpenSSL ծրագիրը</li>
                                                <li>Java JDK և OpenSSL ծրագրերի bin թղթապանակները պետք է ավելացված լինեն
                                                    օպերացիոն համակարգի path փոփոխականին (Environment Variables))
                                                </li>
                                                <li>
                                                    <p>Անհրաժեշտ է ստեղծել անձնական/հանրային բանալիները (private/public
                                                        key) և սերտիֆիկատի բովանդակությունը ( *.jks ֆայլը)՝</p>
                                                    <p>Command Prompt -ում աշխատացնել ներքոնշյալ հրամանը (zzz փոխարինել
                                                        ՀՎՀՀ - ով)</p>
                                                    <code>keytool -genkeypair -v -keyalg RSA -alias zzz -keysize 2048
                                                        -validity 3650 -dname "CN=Zzz Tin, OU=Zzz Tin, O=Zzz Tin,
                                                        L=Yerevan, ST=Yerevan,
                                                        C=AM" -keystore zzz.jks -keypass 123456 -storepass 123456</code>
                                                    <p>Արդյունք։ ստեղծվում է *․jks ընդլայնում ունեցող ֆայլ այն
                                                        թղթապանակում, որտեղից, որ աշխատացվել է վերոհիշյալ հրամանը
                                                        (օրինակում zzz.jks ֆայլը C:\Users\User թղթապանակում)։</p>
                                                </li>
                                                <li>
                                                    <p>Անհրաժեշտ է ստեղծել սերտիֆիկատի ստորագրման հարցման ֆայլը
                                                        (Certificate Sign Request (*.csr))`
                                                        Սերտիֆիկատի ստորագրման հարցման ֆայլը (Certificate Sign Request
                                                        (*.csr))` ստեղծելու համար Command Prompt -ում աշխատացնել
                                                        հետեվյալ հրամանը (zzz փոխարինել ՀՎՀՀ - ով)՝
                                                    </p>
                                                    <code>keytool -certreq -alias zzz -keyalg RSA -keystore zzz.jks
                                                        -storepass 123456 -file zzz.csr</code>
                                                    <p>Արդյունք։</p>
                                                    <img src="<?php echo esc_html($pluginUrl) ?>admin/assets/img/tax-service/image3.gif"
                                                         alt="">
                                                </li>
                                                <li>u6 դիմումի մեջ Սերտիֆիկատի ստորագրման հարցում դաշտում սեղմել Կցել
                                                    կոճակը
                                                </li>
                                                <li>Բացվող պատուհանի օգնությամբ բեռնել սերտիֆիկատի ստորագրման հարցման
                                                    ֆայլը (*.csr)
                                                </li>
                                                <li>Գրանցել լրացված u6 դիմումը</li>
                                                <li>
                                                    <p> Փաստաթղթի հաստատումից հետո կստեղծվի սերտիֆիկատը՝ *․crt ֆայլը,
                                                        որը հնարավոր է ներբեռնել ՀՆԷՀ համակարգի ՀԴՄ-ների ցանկ էջից
                                                        կոճակի օգնությամբ։</p>
                                                    <img src="<?php echo esc_html($pluginUrl) ?>admin/assets/img/tax-service/image4.jpg"
                                                         alt="">
                                                </li>
                                                <li>Սեղմել Ներբեռնել սերտիֆիկատ կոճակը՝ *․crt ֆայլը գեներացնելու համար
                                                </li>
                                                <li>Ներբեռնել ca.crt ֆայլը ՀՀ ՊԵԿ կայքից՝
                                                    <a href="https://petekamutner.am/Shared/Documents/_ts/_os/New_Generation_CCMs/ca.crt"
                                                       target="_blank">ՀՀ ՊԵԿ հավաստագիրը էլեկտրոնային ՀԴՄ-ի վեբ
                                                        ծառայությանն ինտեգրման համար (CA root)</a>
                                                </li>
                                                <li>
                                                    <p>Անհրաժեշտ է ՀՆԷՀ համակարգի ՀԴՄ-ների ցանկ էջից ներբեռնած *.crt և
                                                        ՊԵԿ կայքից ներբեռնած ca.crt ֆայլերը ներմուծել zzz.jks ֆայլի
                                                        մեջ։</p>

                                                    <code>keytool -import -trustcacerts -alias zzz -keystore zzz.jks
                                                        -storepass 123456 -file zzz.crt</code>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="text-center">
                                            <a href="javascript:" id="toggle-file_div"><b>Էլեկտրոնային ՀԴՄ գրանցելու և
                                                    հարցման ֆայլ գեներացնելու օգտվողի ձեռնարկ</b></a>
                                        </div>



                                        <div class="upload-documents  mt-5">
                                            <div class="upload-araa">
                                                <input type="hidden" value="" name="fileContentImport" id="fileContentImport">
                                                <input type="hidden" value="" name="filenameImport" id="filenameImport">
                                                <div class="upload-text">
                                                    <span class="uploadFileText">
                                                         (Կցագրվող ֆայլի ֆորմատը՝ .sql)
                                                    </span>
                                                </div>
                                                <div class="upload-option text-center" id="import-upload-document">
                                                    <label for="attachImport">Ներբեոնել Էլ․ ՀԴՄ-ի Կարգավորումները և տվյալները</label>
                                                    <input id="attachImport" name="attachImport" style="visibility:hidden;"
                                                           type="file">
                                                </div>
                                            </div>
                                        </div>


                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.inner -->
                    <div class="actions">
                        <ul>
                            <li><span class="js-btn-prev" title="BACK"><i class="fa fa-angle-left"></i> Վերադարձ </span>
                            </li>
                            <li><span class="js-btn-next" title="NEXT">Հաջորդը <i class="fa fa-angle-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- div 3 -->
            <div class="multisteps-form__panel" data-animation="slideHorz">
                <div class="wizard-forms">
                    <div class="inner pb-100 clearfix">
                        <div class="form-content pera-content">
                            <div class="step-inner-content">
                                <span class="step-no bottom-line">Քայլ 3</span>
                                <div class="step-progress float-right">
                                    <span>5 քայլից 3-ը կատարված է</span>
                                    <div class="step-progress-bar">
                                        <div class="progress">
                                            <div class="progress-bar" style="width:60%"></div>
                                        </div>
                                    </div>
                                </div>
                                <h2>Հարկման կարգավորում</h2>
                                <p class="subtitle-tax-service">Այս բաժնի կարգավորումները կապահովեն Ձեր ՀԴՄ կտրոնի
                                    հարկման տեսակի, բաժինների, գանձապահի և ԱԱՀ-ի հետ կապված տեղեկատվությունը։</p>
                                <div class="services-select-option type-option">
                                    <p class="d-flex">Հարկման տեսակը՝</p>
                                    <ul class="row">
                                        <li class="bg-white col-md-6 <?php if ($taxServiceTaxType === 'vat'): ?> active <?php endif; ?>">
                                            <label>ԱԱՀ-ով հարկվող
                                                <input type="radio" name="tax_type"
                                                       value="vat" <?php if ($taxServiceTaxType === 'vat'): ?> checked <?php endif; ?>>
                                            </label>
                                        </li>
                                        <li class="bg-white  col-md-6 <?php if ($taxServiceTaxType === 'without_vat'): ?> active <?php endif; ?>">
                                            <label>ԱԱՀ-ով չհարկվող
                                                <input type="radio" name="tax_type"
                                                       value="without_vat" <?php if ($taxServiceTaxType === 'without_vat'): ?> checked <?php endif; ?>>
                                            </label>
                                        </li>
                                        <li class="bg-white col-md-6 <?php if ($taxServiceTaxType === 'around_tax'): ?> active <?php endif; ?>">
                                            <label>Շրջ. հարկ
                                                <input type="radio" name="tax_type"
                                                       value="around_tax" <?php if ($taxServiceTaxType === 'around_tax'): ?> checked <?php endif; ?>>
                                            </label>
                                        </li>
                                        <li class="bg-white col-md-6 <?php if ($taxServiceTaxType === 'micro'): ?> active <?php endif; ?>">
                                            <label> Միկրոձեռնարկատիրություն
                                                <input type="radio" name="tax_type"
                                                       value="micro" <?php if ($taxServiceTaxType === 'micro'): ?> checked <?php endif; ?>>
                                            </label>
                                        </li>
                                    </ul>
                                </div>

                                <div class="have_both_type mt-2">
                                    <input type="checkbox" name="have_both_vat" id="have_both_vat"
                                           value="yes" <?php if ($taxServiceCheckBothType === 'yes'): ?> checked <?php endif; ?> >
                                    <label for="have_both_vat" class="label_have_both_vat">Ունեք ԱԱՀ-ով չհարկվող
                                        ապրանքներ ? <span
                                                data-tooltip-text="Եթե հանդիսանում եք ԱԱՀ-ով հարկվող , սակայն ունեք ապրանքներ որոնք ենթակա չեն ԱԱՀ հարկման, ապա ակտիվացնելով այս գործառույթը Դուք կկարողանաք ցանկացած ապրանք նշել որ այն ԱԱՀ-ով հարկվող չէ։ Ապրանքի էջում միացնելով համապատասխան դաշտը, կտրոնի մեջ կավելանա առանձին բաժին ԱԱՀ-ով չհարկվող ապրանքների համար։"><i
                                                    class="fas fa-question-circle"></i></span> </label>
                                </div>

                                <div class="verification-code-same-div mt-2">
                                    <input type="checkbox" name="verification-code-same" id="verification-code-same"
                                           value="yes" <?php if ($taxServiceVerificationCodeSameSKU === 'yes'): ?> checked <?php endif; ?> >
                                    <label for="verification-code-same" class="label_verification-code-same">Ապրանքի
                                        SKU-ն համապատասխանեցնել ապրանքի կոդի հետ? <span
                                                data-tooltip-text="Այս գործառույթը կիրառելու ժամանակ ապրանքի SKU դաշտում նշված կոդը և ապրանքի կոդը կլինեն նույնը, իսկ գործառույթի անջատված ժամանակ հարկավոր է մուտքագրել առանձին ապրանքի կոդ։"><i
                                                    class="fas fa-question-circle"></i></span> </label>
                                </div>
                                <div class="row mt-5">
                                    <div class="col-md-3 vat_percent_div">
                                        <div class="form-inner-area">
                                            <p class="d-flex">ԱՀՀ (%)
                                                <span data-type="aah" class="tooltip-image">
                                                    <i class="fas fa-question-circle"></i>
                                                    <span>
                                                        <img class="callout"
                                                             src="<?php echo esc_html($pluginUrl) ?>admin/assets/img/settings/aah.jpg"/>
                                                    </span>
                                                </span></p>
                                            <input type="number" step="0.01" name="vat_percent" placeholder="16,67%"
                                                   value="<?php echo esc_html($taxServiceVatPercent) ? esc_html($taxServiceVatPercent) : 16.67 ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-inner-area">
                                            <p class="d-flex">Գանձապահ
                                                <span data-type="gandzapah" class="tooltip-image">
                                                    <i class="fas fa-question-circle"></i>
                                                    <span>
                                                        <img class="callout"
                                                             src="<?php echo esc_html($pluginUrl) ?>admin/assets/img/settings/ganzapah.jpg"/>
                                                    </span>
                                                </span></p>
                                            <input type="text" name="treasurer" placeholder="1"
                                                   value="<?php echo esc_html($taxServiceTreasurer) ? esc_html($taxServiceTreasurer) : 1 ?>">
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- ./inner -->
                    <div class="actions">
                        <ul>
                            <li><span class="js-btn-prev" title="BACK"><i class="fa fa-angle-left"></i> Վերադարձ </span>
                            </li>
                            <li><span class="js-btn-next" title="NEXT">Հաջորդը <i class="fa fa-angle-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- div 4 -->
            <div class="multisteps-form__panel" data-animation="slideHorz">
                <div class="wizard-forms">
                    <div class="inner pb-100 clearfix">
                        <div class="form-content pera-content">
                            <div class="step-inner-content">
                                <span class="step-no bottom-line">Քայլ 4</span>
                                <div class="step-progress float-right">
                                    <span>5 քայլից 4-ը կատարված է</span>
                                    <div class="step-progress-bar">
                                        <div class="progress">
                                            <div class="progress-bar" style="width:80%"></div>
                                        </div>
                                    </div>
                                </div>
                                <h2>Ապրանքների կարգավորում</h2>
                                <p class="subtitle-tax-service">Այս բաժնում Դուք կարող եք կարգավորել ապրանքների և
                                    առաքման ծառայության հետ կապված տվյալները։</p>
                                <div class="step-content-area">
                                    <div>
                                        <div class="form-inner-area">
                                            <p class="d-flex atg-code-title">Ապրանքի <a
                                                        href="javascript:" id="toggle-atg-code-div"><b> &nbsp; ԱՏԳ
                                                        կոդ</b>
                                                    <span data-tooltip-text="Այստեղ մուտքագրված ԱՏԳ կոդը ավտոմատ կերպով կկիրառվի բոլոր այն ապրանքների վրա, որոնց համար առանձին ԱՏԳ կոդ նշված չէ։ Առանձին ապրանքին ԱՏԳ կոդ կարող եք նշել տվյալ ապրանքի էջում։"><i
                                                                class="fas fa-question-circle"></i></span></a>
                                            </p>
                                            <div class="atg-code-info">

                                                <small> <b>Պետք է լինի
                                                        համապատասխան ցանկից, որը
                                                        կարելի է գտնել <a
                                                                href="https://www.petekamutner.am/Content.aspx?itn=tsOSNewCCM"
                                                                target="_blank">taxservice.am</a>
                                                        կայքում
                                                        մուտք գործելով ԷԼԵԿՏՐՈՆԱՅԻՆ
                                                        ԾԱՌԱՅՈՒԹՅՈՒՆՆԵՐ բաժնի ՆՈՐ
                                                        ՍԵՐՆԴԻ ՀՍԿԻՉ-ԴՐԱՄԱՐԿՂԱՅԻՆ
                                                        ՄԵՔԵՆԱՆԵՐ ենթաբաժին, որտեղ
                                                        վերջում կան երկու Excel
                                                        փաստաթղթեր՝ <a
                                                                href="<?php echo esc_html($pluginUrl) ?>admin/assets/excel/1406.xlsx"
                                                                target="_blank">N 1406-Ն</a> և <a
                                                                href="<?php echo esc_html($pluginUrl) ?>admin/assets/excel/875.xlsx"
                                                                target="_blank">N 875-Ն</a>
                                                        որոնք և պարունակում են ԱՏԳ
                                                        կոդեր: </b>
                                                </small>

                                            </div>

                                            <input type="text" name="atg_code" class="atgCode_input w-25 mt-3"
                                                   placeholder="օր․՝ 49.42"
                                                   value="<?php echo esc_html($taxServiceAtgCode); ?>">
                                            <div class="blue atg-code-div">
                                                <h3 class="text-center">ԱՏԳ կոդեր</h3>
                                                <input type="number" id="search-atg-code"
                                                       step="0.01"
                                                       style="background-image: url('<?php echo esc_html($pluginUrl) ?>admin/assets/img/searchicon.png');"
                                                       class="mt-2" placeholder="Փնտրել ԱՏԳ կոդը․․․"
                                                       title="Ապրանքի ԱՏԳ կոդ">
                                                <table id="atg-codes-table" class="mt-2">
                                                    <tr>
                                                        <th>ԱՏԳ Կոդ</th>
                                                        <th>Նկարագրություն</th>
                                                    </tr>
                                                    <?php foreach ($atgCodes as $key => $atgCode): ?>
                                                        <tr>
                                                            <td><?php echo esc_html($key) ?></td>
                                                            <td><?php echo esc_html($atgCode) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="budget-area">
                                        <p class="d-flex unit-title ">Չափման միավոր չնշված ապրանքների համար
                                            <span data-tooltip-text="Տվյալ ցանկում ընտրված չափման միավորը ավտոմատ կերպով կկիրառվի բոլոր այն ապրանքների վրա, որոնց համար առանձին չափման միավոր ընտրված չէ։ Առանձին չափման միավոր կարող եք ընտրել տվյալ ապրանքի էջում։"><i
                                                        class="fas fa-question-circle"></i></span>
                                        </p>
                                        <select name="units_value">
                                            <?php foreach ($units as $value): ?>
                                                <option value="<?php echo esc_html($value); ?>" <?php if (trim($taxServiceUnitsValue) == trim($value)): ?> selected <?php endif; ?>><?php echo esc_html($value) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <div class="enable-shipping-div mt-2">
                                            <input type="checkbox" name="hkd_tax_service_shipping_activated"
                                                   id="hkd_tax_service_shipping_activated"
                                                   value="1" <?php if ($taxServiceShippingActivated): ?> checked <?php endif; ?> >
                                            <label for="hkd_tax_service_shipping_activated"
                                                   class="label_hkd_tax_service_shipping_activated"> Առաքման
                                                ծառայություն <span
                                                        data-tooltip-text="Եթե ունեք առաքման ծառայություն կարող եք ակտիվացնել այս գործառույթը։"><i
                                                            class="fas fa-question-circle"></i></span> </label>
                                        </div>
                                        <div class="shipping-data">
                                            <div class="row mt-4">
                                                <div class="col-md-6">

                                                    <div class="form-inner-area">
                                                        <p class="d-flex shipping-text">Նկարագրություն<span
                                                                    data-tooltip-text="Այս տեքստը երևալու է կտրոնի մեջ առաքման ծառայությանը որպես նկարագրություն։"><i
                                                                        class="fas fa-question-circle"></i></span></p>
                                                        <input type="text" name="hkd_tax_service_shipping_description"
                                                               placeholder="օր․՝ Առաքման ծառայություն"
                                                               value="<?php echo esc_html($taxServiceShippingDescription) ? esc_html($taxServiceShippingDescription) : 'Առաքման ծառայություն' ?>">
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="form-inner-area">
                                                        <p class="d-flex shipping-text">Առաքման ԱՏԳ դասիչ <span
                                                                    data-tooltip-text="Այստեղ հարկավոր է մուտքագրել Առաքման ծառայության համարնախատեսված ԱՏԳ դասիչը"><i
                                                                        class="fas fa-question-circle"></i></span></p>
                                                        <input type="number" name="hkd_tax_service_shipping_atg_code"
                                                               step="0.01"
                                                               placeholder="օր․՝ 49.42"
                                                               value="<?php echo esc_html($taxServiceShippingAtgCode) ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-md-6">
                                                    <div class="form-inner-area">
                                                        <p class="d-flex shipping-text">Հերթական համար<span
                                                                    data-tooltip-text="Առաքման ծառայության հերթական համար"><i
                                                                        class="fas fa-question-circle"></i></span></p>
                                                        <input type="text" name="hkd_tax_service_shipping_good_code"
                                                               placeholder="օր․՝ 007"
                                                               value="<?php echo esc_html($taxServiceShippingGoodCode) ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="budget-area">
                                                        <p class="d-flex shipping-text">Չափման միավոր</p>
                                                        <select class="select_hkd_tax_service_shipping_unit_value"
                                                                name="hkd_tax_service_shipping_unit_value">
                                                            <?php foreach ($units as $value): ?>
                                                                <option value="<?php echo esc_html($value); ?>" <?php if ($taxServiceShippingUnitValue === $value): ?> selected <?php endif; ?>><?php echo esc_html($value) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.inner -->
                    <div class="actions">
                        <ul>
                            <li><span class="js-btn-prev" title="BACK"><i class="fa fa-angle-left"></i> Վերադարձ </span>
                            </li>
                            <li><span class="js-btn-next" title="NEXT">Հաջորդը <i class="fa fa-angle-right"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- div 5 -->
            <div class="multisteps-form__panel <?php if ($verifiedPlugin['success'] && $settingsCompleted) {
                echo "js-active";
            } ?>"
             data-animation="slideHorz">
            <div class="wizard-forms">
                <div class="inner pb-100 clearfix">
                    <div class="form-content pera-content">
                        <div class="step-inner-content">
                            <span class="step-no bottom-line">Քայլ 5</span>
                            <div class="step-progress float-right">
                                <span>5 քայլից 5-ը կատարված է</span>
                                <div class="step-progress-bar">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:100%"></div>
                                    </div>
                                </div>
                            </div>
                            <h2>Կտրոնի տպման կարգավորում</h2>
                            <p class="subtitle-tax-service">Տվյալ քայլի ընթացքում հարկավոր է կարգավորել կտրոնի տպման
                                կարգավիճակը ցանկացած վճարման տարբերակի համար ըստ Ձեր նախնտրած ձևի։</p>
                            <div class="step-content-field">
                                <div class="services-select-option payment-option">
                                    <ul>
                                        <?php foreach ($data['list'] as $key => $item): ?>
                                            <li class="bg-white" data-type="<?php echo esc_html($key) ?>">
                                                <label><?php echo esc_html($item) ?><input
                                                            type="checkbox" checked></label></li>
                                            <div class="payment_gateways-settings d-none"
                                                 data-type="<?php echo esc_html($key) ?>">
                                                <hr>
                                                <div style="display: flex">
                                                    <fieldset>
                                                        <legend class="screen-reader-text">
                                                            <span><?php echo __('Միացնել / Անջատել', 'tax-service') ?></span>
                                                        </legend>
                                                        <label for="hkd_tax_service_enabled<?php echo esc_html($key) ?>">
                                                            <input class="" type="checkbox"
                                                                   name="hkd_tax_service_enabled[<?php echo esc_html($key) ?>]"
                                                                   id="hkd_tax_service_enabled<?php echo esc_html($key) ?>"
                                                                   style="" value="1"
                                                                <?php if (isset($taxServiceEnabledServices[$key]) && $taxServiceEnabledServices[$key]): ?> checked <?php endif; ?>
                                                            ></label><br>
                                                    </fieldset>
                                                    <span style="text-transform: uppercase;">Միացնել / Անջատել (<?php echo esc_html($item) ?>)</span><br>
                                                </div>
                                                <div style="display: flex; flex-direction: column;">
                                                    <span style="color: #4ec3cc"><i class="fa fa-info-circle"
                                                                                    aria-hidden="true"></i>Այս գործառույթը վերաբերվում է միայն այս վճարման տարբերակին</span>
                                                    <p>Տպելու Կարգավորումներ</p>
                                                    <div>
                                                        <label for="hkd_tax_service_type_automatically<?php echo esc_html($key) ?>">
                                                            <input type="radio"
                                                                   id="hkd_tax_service_type_automatically<?php echo esc_html($key) ?>"
                                                                   name="hkd_tax_service_type[<?php echo esc_html($key) ?>]"
                                                                   value="automatically"
                                                                   class="automatically_print_input input_print"
                                                                <?php if (isset($taxServiceEnabledServicesType[$key]) && $taxServiceEnabledServicesType[$key] === 'automatically'): ?> checked <?php endif; ?>
                                                            >
                                                            <?php echo __('Ավտոմատ տպել', 'tax-service') ?>
                                                            <span data-tooltip-text="Այս տարբերակի դեպքում կտրոնի տպման գործընթացը տեղի է ունում պատվերի հաջողությամբ գրանցման ժամանակ պատվերի գրանցման էջում։ Դուք սույն դաշտում կարող եք ընտրել տպման կարգավիճակը ('Ընթացքի մեջ' կամ 'Ավարտված')։"><i
                                                                        class="fas fa-question-circle"></i></span>
                                                        </label><br>
                                                        <label for="hkd_tax_service_type_manually<?php echo esc_html($key) ?>">
                                                            <input type="radio"
                                                                   id="hkd_tax_service_type_manually<?php echo esc_html($key) ?>"
                                                                   name="hkd_tax_service_type[<?php echo esc_html($key) ?>]"
                                                                   value="manually"
                                                                   class="input_print"
                                                                <?php if (isset($taxServiceEnabledServicesType[$key]) && $taxServiceEnabledServicesType[$key] === 'manually'): ?> checked <?php endif; ?>
                                                            >
                                                            <?php echo __('Ձեռքով տպել', 'tax-service') ?>
                                                            <span data-tooltip-text="Այս տարբերակի դեպքում Դուք կտրոնի տպման գործընթացը պետք է կատարեք ինքնուրույն՝ մուտք գործելով պատվերների գրանցման էջ և այնտեղ 'Տպել ՀԴՄ' կոճակի միջոցով կատարել այն։"><i
                                                                        class="fas fa-question-circle"></i></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <hr>
                                            </div>

                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="budget-area automatically_print_status_div d-none">
                                    <p class="d-flex">Կտրոնի ավտոմատ տպման կարգավիճակ<span
                                                data-tooltip-text="Այս գործառույթի շնորհիվ դուք կարող եք ընտրել կտրոնի ավտոմատ տպման ընթացքը պատվերի ձևակերպման որ կարգավիճակում լինի։"><i
                                                    class="fas fa-question-circle"></i></span></p>
                                    <select name="automatically_print_status">
                                        <option value="processing" <?php if ($taxServiceAutomaticallyPrintStatus === 'processing'): ?> selected <?php endif; ?>>
                                            Processing (Ընթացքի մեջ)
                                        </option>
                                        <option value="completed" <?php if ($taxServiceAutomaticallyPrintStatus === 'completed'): ?> selected <?php endif; ?>>
                                            Complete (Ավարտված)
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <p class="d-flex" style="color: #5f5f63;font-size: 23px;font-weight: 700;padding-bottom: 10px;">Այլ գործառույթներ</p>
                                    <div class=" mt-2">
                                        <a href="javascript:" id="exportTaxService"><i
                                                    class="fas fa-file-export"></i>&nbsp;&nbsp; Արտահանել Էլ․ ՀԴՄ-ի Կարգավորումները և տվյալները</a>
                                    </div>
                                    <div class=" mt-2">
                                        <input type="number" style="width: 90px;" step="1" name="seq_number" placeholder="1"  id="seq_number_tax_service"
                                               value="<?php echo esc_html($taxServiceSeqNumber) ?>">
                                        <label for="seq_number_tax_service" class="label_send-refund-to-user">Կտրոնի հերթական համարը? <span
                                                    data-tooltip-text="Կտրոնի հերթտական համարը"><i
                                                        class="fas fa-question-circle"></i></span> </label>
                                    </div>
                                    <div class="send-refund-to-user-div mt-2">
                                        <input type="checkbox" name="send-refund-to-user" id="send-refund-to-user"
                                               value="yes" <?php if ($taxServiceSendRefundToUser === 'yes'): ?> checked <?php endif; ?> >
                                        <label for="send-refund-to-user" class="label_send-refund-to-user">Ուղարկե՞լ վերադարձի ՀԴՄ կտրոնը հաճախորդին? <span
                                                    data-tooltip-text="Ակտիվացնելով այս գործառույթը Ձեր հաճախորդին ավտոմատ կերպով կուղարկվի վերադարձված ՀԴՄ կտրոնը։"><i
                                                        class="fas fa-question-circle"></i></span> </label>
                                    </div>
                                    <div class="send-to-admin-div mt-2">
                                        <input type="checkbox" name="send-to-admin" id="send-to-admin"
                                               value="yes" <?php if ($taxServiceSendToAdmin === 'yes'): ?> checked <?php endif; ?> >
                                        <label for="send-to-admin" class="label_send-to-admin">Ուղարկե՞լ ՀԴՄ կտրոնի օրինակը կայքի էլ-հասցեին? <span
                                                    data-tooltip-text="Ակտիվացնելով այս գործառույթը կայքի՝ պատվերների ընդունման համար հասանելի էլ-հասցեին ավտոմատ կերպով կուղարկվի տվյալ պատվերի ՀԴՄ կտրոնի օրինակը։"><i
                                                        class="fas fa-question-circle"></i></span> </label>
                                    </div>
                                    <div class="send-refund-to-admin-div mt-2">
                                        <input type="checkbox" name="send-refund-to-admin" id="send-refund-to-admin"
                                               value="yes" <?php if ($taxServiceSendRefundToAdmin === 'yes'): ?> checked <?php endif; ?> >
                                        <label for="send-refund-to-admin" class="label_send-refund-to-admin">Ուղարկե՞լ ՀԴՄ կտրոնի վերադարձի օրինակը կայքի էլ-հասցեին? <span
                                                    data-tooltip-text="Ակտիվացնելով այս գործառույթը կայքի՝ պատվերների ընդունման համար հասանելի էլ-հասցեին ավտոմատ կերպով կուղարկվի տվյալ պատվերի Վերադարձ կատարած ՀԴՄ կտրոնի օրինակը։"><i
                                                        class="fas fa-question-circle"></i></span> </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="saveSettings" value="1">
                <!-- /.inner -->
                <div class="actions">
                    <ul>
                        <li><span class="js-btn-prev" title="BACK"><i class="fa fa-angle-left"></i> Վերադարձ </span>
                        </li>
                        <li>
                            <button type="submit"
                                    title="NEXT"><?php echo ($taxServiceApiActivated) ? 'Պահպանել' : 'Ակտիվացնել'; ?> <i
                                        class="fa fa-angle-right"></i></button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        </div>
    </form>
</div>

