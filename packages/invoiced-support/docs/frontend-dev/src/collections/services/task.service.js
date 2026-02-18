(function () {
    'use strict';

    angular.module('app.collections').factory('Task', Task);

    Task.$inject = ['$resource', 'InvoicedConfig'];

    function Task($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/tasks/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        expand: 'customer,user_id,completed_by_user_id',
                    },
                },
                new: {
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
