(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('CustomerGenericEmail', CustomerGenericEmail);

    CustomerGenericEmail.$inject = ['$resource', 'InvoicedConfig'];

    function CustomerGenericEmail($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/customers/:id/emails/generic',
            {
                id: '@id',
            },
            {
                email: {
                    method: 'POST',
                    isArray: true,
                },
            },
        );
    }
})();
