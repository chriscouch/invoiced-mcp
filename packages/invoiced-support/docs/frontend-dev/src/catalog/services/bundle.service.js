(function () {
    'use strict';

    angular.module('app.catalog').factory('Bundle', BundleService);

    BundleService.$inject = ['$resource', '$http', 'InvoicedConfig', 'selectedCompany'];

    function BundleService($resource, $http, InvoicedConfig, selectedCompany) {
        let bundlesCache = {};

        let Bundle = $resource(
            InvoicedConfig.apiBaseUrl + '/bundles/:id',
            {},
            {
                findAll: {
                    method: 'GET',
                    params: {
                        sort: 'name ASC',
                        per_page: 100,
                    },
                    isArray: true,
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/bundles',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );

        Bundle.all = function (success, error) {
            if (typeof bundlesCache[selectedCompany.id] !== 'undefined') {
                success(bundlesCache[selectedCompany.id]);
                return;
            }

            bundlesCache[selectedCompany.id] = [];
            Bundle.findAll(
                { paginate: 'none' },
                function (result) {
                    bundlesCache[selectedCompany.id] = result;
                    success(result);
                },
                error,
            );
        };

        Bundle.clearCache = clearCache;

        return Bundle;

        function clearCache(response) {
            if (typeof bundlesCache[selectedCompany.id] !== 'undefined') {
                delete bundlesCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
