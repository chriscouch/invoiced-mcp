(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('Note', Note);

    Note.$inject = ['$resource', 'InvoicedConfig'];

    function Note($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/notes/:id',
            {
                id: '@id',
            },
            {
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
