(function () {
    'use strict';

    angular.module('app.exports').factory('Export', Export);

    Export.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function Export($resource, $http, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/exports/:id',
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
                },
                create: {
                    method: 'POST',
                },
                cancel: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
