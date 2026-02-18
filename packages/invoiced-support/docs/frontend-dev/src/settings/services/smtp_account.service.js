(function () {
    'use strict';

    angular.module('app.settings').factory('SmtpAccount', SmtpAccount);

    SmtpAccount.$inject = ['$resource', 'InvoicedConfig'];

    function SmtpAccount($resource, InvoicedConfig) {
        let url = InvoicedConfig.apiBaseUrl + '/smtp_account';

        return $resource(
            url,
            {},
            {
                get: {
                    method: 'GET',
                },
                update: {
                    method: 'PATCH',
                },
                test: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/smtp_test',
                },
            },
        );
    }
})();
