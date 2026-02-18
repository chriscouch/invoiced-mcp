(function () {
    'use strict';

    angular.module('app.user_management').factory('Role', Role);

    Role.$inject = ['$resource', '$q', 'InvoicedConfig', 'selectedCompany'];

    function Role($resource, $q, InvoicedConfig, selectedCompany) {
        let RoleCache = {};

        let RoleResource = $resource(
            InvoicedConfig.apiBaseUrl + '/roles/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
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

        RoleResource.all = function (params, success, error) {
            if (RoleCache[selectedCompany.id] === undefined) {
                //promise is needed to handle multiple concurrent requests
                RoleCache[selectedCompany.id] = $q(function (resolve, reject) {
                    RoleResource.findAll(params, resolve, reject);
                });
            }
            RoleCache[selectedCompany.id].then(success, error);
        };
        return RoleResource;
    }
})();
