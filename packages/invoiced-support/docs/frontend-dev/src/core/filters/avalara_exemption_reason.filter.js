(function () {
    'use strict';

    angular.module('app.core').filter('avalaraExemptionReason', avalaraExemptionReason);

    avalaraExemptionReason.$inject = ['InvoicedConfig'];

    function avalaraExemptionReason(InvoicedConfig) {
        return function (entityCode) {
            if (!entityCode) {
                return '';
            }

            if (typeof InvoicedConfig.avalaraEntityCodes[entityCode] !== 'undefined') {
                return InvoicedConfig.avalaraEntityCodes[entityCode];
            }

            return entityCode;
        };
    }
})();
