(function () {
    'use strict';

    angular.module('app.accounts_receivable').filter('paymentSourceName', paymentSourceName);

    paymentSourceName.$inject = ['$translate'];

    function paymentSourceName($translate) {
        return function (payment) {
            if (!payment) {
                return '';
            }

            if (payment.bank_account_name) {
                return payment.bank_account_name;
            }

            let key = 'payments.source.' + payment.source;
            let translated = $translate.instant(key);
            if (translated !== key) {
                return translated;
            }

            return payment.source;
        };
    }
})();
