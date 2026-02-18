(function () {
    'use strict';

    angular.module('app.settings').factory('Notification', Notification);

    Notification.$inject = ['$resource', 'InvoicedConfig'];

    function Notification($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/notifications/:id',
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
