(function () {
    'use strict';

    angular.module('app.developer_tools').factory('OAuthApplication', OAuthApplication);

    OAuthApplication.$inject = ['$resource', 'InvoicedConfig'];

    function OAuthApplication($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/oauth_applications/:id',
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
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
