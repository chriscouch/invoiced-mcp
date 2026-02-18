(function () {
    'use strict';

    angular.module('app.accounts_receivable').filter('gatewayName', gatewayName);

    gatewayName.$inject = ['$translate'];

    function gatewayName($translate) {
        return function (gatewayId) {
            let translationId = 'payment_gateways.' + gatewayId;
            let value = $translate.instant(translationId);
            if (value === translationId) {
                return gatewayId;
            }

            return value;
        };
    }
})();
