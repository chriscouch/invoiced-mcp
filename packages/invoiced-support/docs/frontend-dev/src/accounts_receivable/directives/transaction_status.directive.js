(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('transactionStatus', transactionStatus);

    function transactionStatus() {
        return {
            restrict: 'E',
            template:
                '<span class="label label-danger" ng-show="transaction.status==\'failed\'">Failed</span>' +
                '<span class="label label-warning" ng-show="transaction.status==\'pending\'">Pending</span>' +
                '<span class="label label-success" ng-show="transaction.status==\'succeeded\'">Succeeded</span>',
            scope: {
                transaction: '=',
            },
        };
    }
})();
