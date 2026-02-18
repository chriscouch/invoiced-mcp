(function () {
    'use strict';

    angular.module('app.components').directive('bankAccount', bankAccount);

    function bankAccount() {
        return {
            restrict: 'E',
            templateUrl: 'components/views/bank-account.html',
            scope: {
                bankAccount: '=',
            },
        };
    }
})();
