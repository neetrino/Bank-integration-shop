var $ = jQuery;

$( document ).ready(function() {
    var atgCodes = atgCodes_object.atgCodes;
    var atgUndefinedMessage = atgCodes_object.atgUndefinedMessage;
    var currenntAtgCode =  $('.atgCode_input').val();
    if(currenntAtgCode.length) checkinfoMesage(currenntAtgCode);
    
    $('input.atgCode_input').on('focusout',function(e){
        let currenntAtgCode = $(this).val();
        checkinfoMesage(currenntAtgCode)
    });

    function checkinfoMesage(currenntAtgCode){
        $('.atgCodeInfo').remove();
        if(typeof atgCodes[currenntAtgCode] !== "undefined"){
            $('.atgCode_input').after(`<div><p class="atgCodeInfo" style="color:#1dbe72">${atgCodes[currenntAtgCode]}</p></div>`);
            $('.atgCode_input').css({'border-color': 'green'});
        }else{
            $('.atgCode_input').after(`<div><p class="atgCodeInfo" style="color:#e55f5f">${atgUndefinedMessage}</p></div>`);
            $('.atgCode_input').css({'border-color': 'red'});
        }
    }
});