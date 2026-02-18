(function () {
    'use strict';

    angular.module('app.accounts_payable').factory('ECheck', ECheck);

    ECheck.$inject = ['$resource', 'InvoicedConfig'];

    function ECheck($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/checks/:id',
            {
                id: '@id',
            },
            {
                list: {
                    method: 'GET',
                    isArray: true,
                },
                send: {
                    method: 'POST',
                },
            },
        );
    }
})();
