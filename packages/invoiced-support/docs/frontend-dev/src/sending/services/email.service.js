(function () {
    'use strict';

    angular.module('app.sending').factory('Email', Email);

    Email.$inject = ['$resource', 'InvoicedConfig'];

    function Email($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/emails/:id',
            {
                id: '@id',
            },
            {
                find: {
                    method: 'GET',
                    cache: true,
                },
            },
        );
    }
})();
