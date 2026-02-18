(function () {
    'use strict';

    angular.module('app.payment_setup').factory('FlywirePayments', FlywirePayments);

    FlywirePayments.$inject = ['$resource', 'InvoicedConfig'];

    function FlywirePayments($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl,
            {},
            {
                eligibility: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/flywire/eligibility',
                },
                getAccount: {
                    url: InvoicedConfig.apiBaseUrl + '/flywire/account',
                    method: 'GET',
                },
                editPayoutSettings: {
                    url: InvoicedConfig.apiBaseUrl + '/flywire/payout_settings',
                    method: 'POST',
                },
            },
        );
    }
})();
