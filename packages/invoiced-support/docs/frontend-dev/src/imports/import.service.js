(function () {
    'use strict';

    angular.module('app.imports').factory('Import', Import);

    Import.$inject = ['$resource', 'InvoicedConfig'];

    function Import($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/imports/:id',
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
                    params: {
                        include: 'updated_at',
                        expand: 'user',
                    },
                },
                create: {
                    method: 'POST',
                },
                importedObjects: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/imports/:id/imported_objects',
                    isArray: true,
                },
            },
        );
    }
})();
