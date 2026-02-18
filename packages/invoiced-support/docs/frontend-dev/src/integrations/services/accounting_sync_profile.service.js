(function () {
    'use strict';

    angular.module('app.integrations').factory('AccountingSyncProfile', AccountingSyncProfile);

    AccountingSyncProfile.$inject = ['$resource', 'InvoicedConfig'];

    function AccountingSyncProfile($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/accounting_sync_profiles/:id',
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
            },
        );
    }
})();
