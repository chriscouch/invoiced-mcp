(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('autopayIcon', autopayIcon);

    function autopayIcon() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="autopay-icon" ng-if="isEnabled" ' +
                'tooltip="AutoPay enabled" tooltip-placement="right">' +
                '<span class="fad fa-plane-alt"></span>' +
                '</a>',
            scope: {
                isEnabled: '=',
            },
        };
    }
})();
