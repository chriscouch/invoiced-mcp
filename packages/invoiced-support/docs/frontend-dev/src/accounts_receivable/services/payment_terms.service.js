(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('PaymentTerms', PaymentTerms);

    PaymentTerms.$inject = ['$resource', '$http', '$cacheFactory', 'InvoicedConfig'];

    function PaymentTerms($resource, $http, $cacheFactory, InvoicedConfig) {
        let PaymentTerms = $resource(
            InvoicedConfig.apiBaseUrl + '/payment_terms/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    cache: true,
                },
                create: {
                    method: 'POST',
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

        PaymentTerms.clearCache = clearCache;

        return PaymentTerms;

        function clearCache(response) {
            // clear the cache
            let cache = $cacheFactory.get('$http');
            cache.remove(InvoicedConfig.apiBaseUrl + '/payment_terms');

            return response;
        }
    }
})();
