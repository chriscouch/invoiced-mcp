(function () {
    'use strict';

    angular.module('app.settings').factory('CspTrustedSite', CspTrustedSite);

    CspTrustedSite.$inject = ['$resource', 'InvoicedConfig'];

    function CspTrustedSite($resource, InvoicedConfig) {
        let url = InvoicedConfig.apiBaseUrl + '/csp_trusted_sites/:id';

        return $resource(
            url,
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                create: {
                    method: 'POST',
                },
                edit: {
                    method: 'PATCH',
                },
            },
        );
    }
})();
