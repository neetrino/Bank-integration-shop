var $ = jQuery;

$(document).ready(function () {

    $("#files").change(function () {
        filename = this.files[0].name
        // console.log(filename);
    });

    function UploadFile() {
        var reader = new FileReader();
        var file = document.getElementById('attach').files[0];
        reader.onload = function () {
            document.getElementById('fileContent').value = reader.result;
            document.getElementById('filename').value = file.name;
            $('.uploadFileText').text(file.name + ' Ֆայլը կցված է');
            if(!$('.fa-cloud-upload-alt').hasClass('uploaded'))
            $('.fa-cloud-upload-alt').addClass('uploaded');
        }
        reader.readAsDataURL(file);
    }

    function UploadFileKey() {
        var reader = new FileReader();
        var file = document.getElementById('attachKey').files[0];
        reader.onload = function () {
            document.getElementById('fileContentKey').value = reader.result;
            document.getElementById('filenameKey').value = file.name;
            $('.uploadFileTextCrt').text(file.name + ' Ֆայլը կցված է');
            if(!$('.fa-cloud-upload-alt').hasClass('uploaded'))
                $('.fa-cloud-upload-alt').addClass('uploaded');
        }
        reader.readAsDataURL(file);
    }

    function importFile() {
        var reader = new FileReader();
        var file = document.getElementById('attachImport').files[0];
        reader.onload = function () {
            document.getElementById('fileContentImport').value = reader.result;
            document.getElementById('filenameImport').value = file.name;
            $('.uploadFileTextImport').text(file.name + ' Ֆայլը կցված է');
            if(!$('.fa-cloud-upload-alt').hasClass('uploaded'))
            $('.fa-cloud-upload-alt').addClass('uploaded');
        }
        reader.readAsDataURL(file);
    }
    $('#attach').change(function(){ UploadFile(); });
    $('#attachKey').change(function(){ UploadFileKey(); });
    $('#attachImport').change(function(){ importFile(); });

    $(document).ready(function () {
        $('.conditional').conditionize();
        $('.terms_div').hide();
        $('.atg-code-div').hide();
        $('.file_div').hide();
        $('a#toggle-terms_div').click(function() {
            $('.terms_div').slideToggle(500);
            return false;
        });
        $('a#toggle-file_div').click(function() {
            $('.file_div').slideToggle(500);
            return false;
        });
        $('a#toggle-atg-code-div').click(function() {
            $('.atg-code-div').slideToggle(500);
            return false;
        });

        $('#search-atg-code').on('keyup', function() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("search-atg-code");
            filter = input.value.toUpperCase();
            table = document.getElementById("atg-codes-table");
            tr = table.getElementsByTagName("tr");
            for (i = 0; i < tr.length; i++) {
                firstTd = tr[i].getElementsByTagName("td")[0];
                secondTd = tr[i].getElementsByTagName("td")[1];
                if (firstTd && secondTd) {
                    txtValue = firstTd.textContent || firstTd.innerText;
                    txtSecondValue = secondTd.textContent || secondTd.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1 || txtSecondValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        });
        getRadioValue();
        getHaveBothVatCheckboxValue();
        checkShipping();
        checkAutomaticallyPrintStatus();
        $('input[name=tax_type]').on('change', function() {
            getRadioValue();
        });

        $('input[name=have_both_vat]').on('change', function() {
            getHaveBothVatCheckboxValue();
        });
        $('input.input_print').on('change', function() {
            checkAutomaticallyPrintStatus();
        });

        $('input[name=hkd_tax_service_shipping_activated]').on('change', function() {
            checkShipping();
        });
        
        function checkAutomaticallyPrintStatus() {
            let showDiv = false;
            $( ".automatically_print_input" ).each(function( index ) {
                if($(this).is(':checked')){
                    showDiv = true;
                }
            });
            if(showDiv){
                $('.automatically_print_status_div').removeClass('d-none')
            }else{
                if( !$('.automatically_print_status_div').hasClass('d-none')){
                    $('.automatically_print_status_div').addClass('d-none')
                }
            }
        }


        function getHaveBothVatCheckboxValue() {
            if( $('input[name=have_both_vat]').is(':checked')){
                $(".section-2-div").show();
            }else{
                $(".section-2-div").hide();
            }
        }



        function checkShipping() {
            if( $('input[name=hkd_tax_service_shipping_activated]').is(':checked')){
                $(".shipping-data").show();
            }else{
                $(".shipping-data").hide();
            }
        }
        function getRadioValue() {
           let currentVal = $('input[name=tax_type]:checked').val();
           $('.have_both_type').hide();
           $('.vat_percent_div').hide();
           if(currentVal === 'vat'){
               $('.vat_percent_div').show();
               $('.have_both_type').show();
               getHaveBothVatCheckboxValue();
           }else{
               $(".section-2-div").hide();
           }
        }
    });
})