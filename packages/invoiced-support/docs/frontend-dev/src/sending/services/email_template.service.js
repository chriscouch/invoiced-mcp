(function () {
    'use strict';

    angular.module('app.sending').factory('EmailTemplate', EmailTemplate);

    EmailTemplate.$inject = ['$resource', '$http', '$cacheFactory', 'InvoicedConfig'];

    function EmailTemplate($resource, $http, $cacheFactory, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/email_templates/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                find: {
                    method: 'GET',
                    cache: true,
                },
                create: {
                    method: 'POST',
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(function (response) {
                        // clear the cache
                        let cache = $cacheFactory.get('$http');
                        cache.remove(InvoicedConfig.apiBaseUrl + '/email_templates/' + response.id);

                        return response;
                    }),
                },
            },
        );
    }
})();
