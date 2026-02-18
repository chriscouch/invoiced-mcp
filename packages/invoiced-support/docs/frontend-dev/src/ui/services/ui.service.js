(function () {
    'use strict';

    angular.module('app.components').factory('Ui', Ui);

    Ui.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function Ui($resource, $http, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/ui/filters/:id',
            {
                id: '@id',
            },
            {
                list: {
                    method: 'GET',
                    isArray: true,
                },
                create: {
                    method: 'POST',
                },
                update: {
                    method: 'PATCH',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
