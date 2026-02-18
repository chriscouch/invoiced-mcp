(function () {
    'use strict';

    angular.module('app.payment_setup').factory('AchFileFormat', AchFileFormat);

    AchFileFormat.$inject = ['$resource', 'InvoicedConfig'];

    function AchFileFormat($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/ach_file_formats/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                retrieve: {
                    method: 'GET',
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
