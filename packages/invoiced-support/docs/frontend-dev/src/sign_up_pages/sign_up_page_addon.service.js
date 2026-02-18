(function () {
    'use strict';

    angular.module('app.sign_up_pages').factory('SignUpPageAddon', SignUpPageAddon);

    SignUpPageAddon.$inject = ['$resource', 'InvoicedConfig'];

    function SignUpPageAddon($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/sign_up_page_addons/:id',
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
                    url: InvoicedConfig.apiBaseUrl + '/sign_up_page_addons',
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
