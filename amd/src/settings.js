define(['jquery'], function($) {

    return {
        init: function() {
            var enableFields = function() {
                if ($('#id_s_enrol_payment_definetaxes').is(':checked')) {
                    $('#id_s_enrol_payment_countrytax').prop('disabled', false);
                    $('#id_s_enrol_payment_taxdefinitions').prop('disabled', false);
                } else {
                    $('#id_s_enrol_payment_countrytax').prop('disabled', true);
                    $('#id_s_enrol_payment_taxdefinitions').prop('disabled', true);
                }
            };
            enableFields();
            $( "#id_s_enrol_payment_definetaxes" ).on( "click", enableFields );
        }
    };
});