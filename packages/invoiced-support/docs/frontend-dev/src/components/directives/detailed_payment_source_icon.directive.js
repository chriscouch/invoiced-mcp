(function () {
    'use strict';

    angular.module('app.components').directive('detailedPaymentSourceIcon', detailedPaymentSourceIcon);

    function detailedPaymentSourceIcon() {
        return {
            restrict: 'E',
            template:
                '<card-icon brand="source.brand" ng-if="source.object==\'card\'"></card-icon>' +
                '<div class="bank-account-icon" ng-if="source.object==\'bank_account\'">' +
                '<span class="fad fa-university"></span>' +
                '</div>',
            scope: {
                source: '=',
            },
        };
    }
})();
