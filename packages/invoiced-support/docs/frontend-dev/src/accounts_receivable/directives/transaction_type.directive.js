(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('transactionType', transactionType);

    function transactionType() {
        return {
            restrict: 'E',
            templateUrl: 'accounts_receivable/views/transactions/transaction-type.html',
            scope: {
                transaction: '=',
            },
        };
    }
})();
