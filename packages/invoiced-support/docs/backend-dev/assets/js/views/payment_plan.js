(function () {
    'use strict';

    $(function () {
        $('#acceptPaymentPlan').change(function () {
            if ($(this).is(':checked')) {
                $('.payment-button').removeAttr('disabled');
            } else {
                $('.payment-button').attr('disabled', 'disabled');
            }
        });
    });
})();
