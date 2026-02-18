(function () {
    'use strict';

    angular.module('app.accounts_receivable').filter('paymentMethodName', paymentMethodName);

    paymentMethodName.$inject = ['$translate'];

    function paymentMethodName($translate) {
        return function (id) {
            if (!id) {
                return '';
            }

            let key = 'payment_method.' + id;
            let translated = $translate.instant(key);
            if (translated !== key) {
                return translated;
            }

            return id;
        };
    }
})();
