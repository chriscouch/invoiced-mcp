(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('applyReceivePayment', applyReceivePayment);

    function applyReceivePayment() {
        return {
            restrict: 'E',
            templateUrl: 'accounts_receivable/views/payments/apply-receive-payment.html',
            scope: {
                payment: '=',
                applyForm: '=form',
            },
        };
    }
})();
