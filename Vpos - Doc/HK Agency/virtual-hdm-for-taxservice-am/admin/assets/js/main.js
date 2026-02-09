var $ = jQuery;

$(function () {

    $('#attachImport').on('change', function () {
        var formData = new FormData();
        formData.append('file', $('#attachImport')[0].files[0]);
        formData.append('action', 'importTaxService');
        formData.append('_ajax_nonce', my_ajax_object.nonce);
        let isAccepted = confirm("Ներբեռնել կցված ֆայլը ?");
        if(isAccepted){
            $.ajax({
                type: "POST",
                dataType: "json",
                url: my_ajax_object.ajax_url,
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                   var data = res && res.data ? res.data : res;
                   if (data && data.message) {
                       alert(data.message);
                   }
                },
                error: function () {
                }
            });
        }

    });

    $('#exportTaxService').on('click', function () {
        $.ajax({
            type: "POST",
            dataType: "json",
            url: my_ajax_object.ajax_url,
            data: {
                action: 'exportTaxService',
                _ajax_nonce: my_ajax_object.nonce
            },
            success: function (res) {
                var data = res && res.data ? res.data : res;
                if (res && res.success && data) {
                    if (data.message) { alert(data.message); }
                    if (data.exportFilePath) { window.open(data.exportFilePath); }
                    if (data.certificateFilePath) { window.open(data.certificateFilePath); }
                }
            },
            error: function () {
            }
        });
    });
    
    // Select Dropdown
    $('html').on('click', function () {
        $('.select .dropdown').hide();
    });
    $('.select').on('click', function (event) {
        event.stopPropagation();
    });
    $('.select .select-control').on('click', function () {
        $(this).parent().next().toggle();
    })
    $('.select .dropdown li').on('click', function () {
        $(this).parent().toggle();
        var text = $(this).attr('rel');
        $(this).parent().prev().find('div').text(text);
    })

    // date picker
    $('.datepicker').datepicker({
        clearBtn: true,
        format: "dd/mm/yyyy"
    });

    $(".step-box-content ").on('click', function () {

        $(".step-box-content").removeClass("active");
        $(this).addClass("active");
    });

    $(".services-select-option.payment-option li").on('click', function () {
        if($(this).hasClass("active")){
            $(this).removeClass("active");
            $(`.payment_gateways-settings.activeSelect`).addClass('d-none');
        }else{
            $(".services-select-option li").removeClass("active");
            $(this).addClass("active");

            if ($(this).parents('.services-select-option').hasClass('payment-option')) {
                let type = $(this).attr('data-type');
                $(`.payment_gateways-settings.activeSelect`).addClass('d-none');
                $(`.payment_gateways-settings[data-type="${type}"]`).removeClass('d-none').addClass('activeSelect');
            }
        }
    });
    $(".services-select-option.type-option li").on('click', function () {

        $(".services-select-option li").removeClass("active");
        $(this).addClass("active");

        if ($(this).parents('.services-select-option').hasClass('payment-option')) {
            let type = $(this).attr('data-type');
            $(this).find('input').prop('checked', true);

            $(`.payment_gateways-settings.activeSelect`).addClass('d-none');
            $(`.payment_gateways-settings[data-type="${type}"]`).removeClass('d-none').addClass('activeSelect');
        }
    });


    $(".opti-list ul li").on('click', function (e) {
        $(this).find('input[type=checkbox]').prop("checked", !$(this).find('input[type=checkbox]').prop("checked"));

        if ($(this).hasClass("active")) {
            $(this).removeClass("active");
        } else {
            $(this).addClass("active");
        }
    });

    $('input[type=checkbox]').click(function (e) {
        e.stopPropagation();
        return true;
    });

    $(".plan-icon-text").on('click', function () {
        $(this).find('input[type=radio]').prop("checked", true);
        $(".plan-icon-text").removeClass("active");
        $(this).addClass("active");
    });

    $('#active_payment_service').on('change', function () {
        let val = $(this).val();
        $(`.payment_gateways-settings.activeSelect`).addClass('d-none');
        $(`.payment_gateways-settings[data-type="${val}"]`).removeClass('d-none').addClass('activeSelect');

    })

    $('.multisteps-form__progress-btn').on('click', function () {
        let id = parseInt($(this).attr('data-id'));
        let currentId = parseInt($('.multisteps-form__progress-btn.current').attr('data-id'));
        let verificationId = $('#hkd_tax_service_verification_id').val();

        if (currentId === 0) {
            if(!verificationId.length){
                $('#hkd_tax_service_verification_id').addClass('custom-select is-invalid');
                $('#hkd_tax_service_verification_id-error').text('Խնդրում ենք անցնել նույնականացում։ Հարցերի դեպքում զանգահարել 033 779-779 հեռախոսահամարով։').show();
            }else if(!$('#terms').is(':checked')){
                $('.accept_terms_div').addClass('custom-select is-invalid');
                $('#hkd_tax_service_verification_id-error').text('Խնդրում ենք համաձայնվել Հավելվածի Պայմաններ և դրույթներ -ին').show();
            }else{
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: my_ajax_object.ajax_url,
                    data: {
                        action: 'checkTaxServiceVerification',
                        verificationId: verificationId,
                        _ajax_nonce: my_ajax_object.nonce
                    },
                    success: function (res) {
                        if (res.success) {
                            $("html, body").animate({
                                scrollTop: 0
                            }, 600);
                            setActiveStep(id);
                            setActivePanel(id);
                        } else {
                            $('#hkd_tax_service_verification_id').addClass('custom-select is-invalid');
                            $('#hkd_tax_service_verification_id-error').text(res.message).show();
                        }
                    },
                    error: function () {
                    }
                });
            }

        } else {
            $("html, body").animate({
                scrollTop: 0
            }, 600);
            setActiveStep(id);
            setActivePanel(id);
        }


    })


    //multi form ===================================
    //DOM elements
    const DOMstrings = {
        stepsBtnClass: 'multisteps-form__progress-btn',
        stepsBtns: document.querySelectorAll(`.multisteps-form__progress-btn`),
        stepsBar: document.querySelector('.multisteps-form__progress'),
        stepsForm: document.querySelector('.multisteps-form__form'),
        stepFormPanelClass: 'multisteps-form__panel',
        stepFormPanels: document.querySelectorAll('.multisteps-form__panel'),
        stepPrevBtnClass: 'js-btn-prev',
        stepNextBtnClass: 'js-btn-next'
    };


    //remove class from a set of items
    const removeClasses = (elemSet, className) => {

        elemSet.forEach(elem => {

            elem.classList.remove(className);

        });

    };

    //return exect parent node of the element
    const findParent = (elem, parentClass) => {

        let currentNode = elem;

        while (!currentNode.classList.contains(parentClass)) {
            currentNode = currentNode.parentNode;
        }

        return currentNode;

    };

    //get active button step number
    const getActiveStep = elem => {
        return Array.from(DOMstrings.stepsBtns).indexOf(elem);
    };

    //set all steps before clicked (and clicked too) to active
    const setActiveStep = activeStepNum => {

        //remove active state from all the state
        removeClasses(DOMstrings.stepsBtns, 'js-active');
        removeClasses(DOMstrings.stepsBtns, 'current');

        //set picked items to active
        DOMstrings.stepsBtns.forEach((elem, index) => {
            if (index <= activeStepNum) {
                elem.classList.add('js-active');
                $(elem).addClass(index);

            }

            if (index == activeStepNum) {
                elem.classList.add('current');
            }


        });
    };

    //get active panel
    const getActivePanel = () => {

        let activePanel;

        DOMstrings.stepFormPanels.forEach(elem => {

            if (elem.classList.contains('js-active')) {

                activePanel = elem;

            }

        });

        return activePanel;

    };

    //open active panel (and close unactive panels)
    const setActivePanel = activePanelNum => {

        const animation = $(DOMstrings.stepFormPanels, 'js-active').attr("data-animation");

        //remove active class from all the panels
        removeClasses(DOMstrings.stepFormPanels, 'js-active');
        removeClasses(DOMstrings.stepFormPanels, animation);
        removeClasses(DOMstrings.stepFormPanels, 'animate__animated');

        //show active panel
        DOMstrings.stepFormPanels.forEach((elem, index) => {
            if (index === activePanelNum) {

                elem.classList.add('js-active');
                // stepFormPanels
                elem.classList.add('animate__animated', animation);

                setTimeout(function () {
                    removeClasses(DOMstrings.stepFormPanels, 'animate__animated', animation);
                }, 1200);

                setFormHeight(elem);

            }
        });

    };


    //set form height equal to current panel height
    const formHeight = activePanel => {

        const activePanelHeight = activePanel.offsetHeight;

        DOMstrings.stepsForm.style.height = `${activePanelHeight}px`;

    };

    const setFormHeight = () => {
        const activePanel = getActivePanel();

        formHeight(activePanel);
    };

    //STEPS BAR CLICK FUNCTION
    DOMstrings.stepsBar.addEventListener('click', e => {

        //check if click target is a step button
        const eventTarget = e.target;

        if (!eventTarget.classList.contains(`${DOMstrings.stepsBtnClass}`)) {
            return;
        }

        //get active button step number
        const activeStep = getActiveStep(eventTarget);

        //set all steps before clicked (and clicked too) to active
        // setActiveStep(activeStep);

        //open active panel
        // setActivePanel(activeStep);
    });

    //PREV/NEXT BTNS CLICK
    DOMstrings.stepsForm.addEventListener('click', e => {

        const eventTarget = e.target;

        //check if we clicked on `PREV` or NEXT` buttons
        if (!(eventTarget.classList.contains(`${DOMstrings.stepPrevBtnClass}`) || eventTarget.classList.contains(`${DOMstrings.stepNextBtnClass}`))) {
            return;
        }

        //find active panel
        const activePanel = findParent(eventTarget, `${DOMstrings.stepFormPanelClass}`);

        let activePanelNum = Array.from(DOMstrings.stepFormPanels).indexOf(activePanel);


        //set active step and active panel onclick
        if (eventTarget.classList.contains(`${DOMstrings.stepPrevBtnClass}`)) {
            activePanelNum--;

            setActiveStep(activePanelNum);
            setActivePanel(activePanelNum);

        } else if (eventTarget.classList.contains(`${DOMstrings.stepNextBtnClass}`)) {

            var form = $('#wizard');
            form.validate();


            var parent_fieldset = $('.multisteps-form__panel.js-active');
            var next_step = true;

            let verificationId = $('#hkd_tax_service_verification_id').val();
            if (parent_fieldset.hasClass('verificationStep')) {
                if(!verificationId.length){
                    $('#hkd_tax_service_verification_id').addClass('custom-select is-invalid');
                    $('#hkd_tax_service_verification_id-error').text('Խնդրում ենք անցնել նույնականացում։ Հարցերի դեպքում զանգահարել 033 779-779 հեռախոսահամարով։').show();
                }else if(!$('#terms').is(':checked')){
                    $('.accept_terms_div').addClass('custom-select is-invalid');
                    $('#hkd_tax_service_verification_id-error').text('Խնդրում ենք համաձայնվել Հավելվածի Պայմաններ և դրույթներ -ին').show();
                }else {
                    $('.accept_terms_div').removeClass('custom-select is-invalid');
                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: my_ajax_object.ajax_url,
                        data: {
                            action: 'checkTaxServiceVerification',
                            verificationId: verificationId,
                            _ajax_nonce: my_ajax_object.nonce
                        },
                        success: function (res) {
                            if (res.success) {
                                $("html, body").animate({
                                    scrollTop: 0
                                }, 600);
                                activePanelNum++;
                                setActiveStep(activePanelNum);
                                setActivePanel(activePanelNum);
                            } else {
                                $('#hkd_tax_service_verification_id').addClass('custom-select is-invalid');
                                $('#hkd_tax_service_verification_id-error').text(res.message).show();
                            }
                        },
                        error: function () {
                        }
                    });
                }
            } else {
                parent_fieldset.find('.required').each(function () {
                    next_step = false;
                    var form = $('.required');
                    form.validate();
                    $(this).addClass('custom-select is-invalid');
                });

                if (next_step === true || form.valid() === true) {
                    $("html, body").animate({
                        scrollTop: 0
                    }, 600);
                    activePanelNum++;
                    setActiveStep(activePanelNum);
                    setActivePanel(activePanelNum);
                }

            }


        }


    });

    //SETTING PROPER FORM HEIGHT ONLOAD
    window.addEventListener('load', setFormHeight, true);

    //SETTING PROPER FORM HEIGHT ONRESIZE
    window.addEventListener('resize', setFormHeight, true);
})