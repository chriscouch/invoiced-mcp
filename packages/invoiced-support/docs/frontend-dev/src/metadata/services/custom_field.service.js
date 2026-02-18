(function () {
    'use strict';

    angular.module('app.metadata').factory('CustomField', CustomField);

    CustomField.$inject = ['$resource', '$http', '$q', 'Core', 'InvoicedConfig', 'selectedCompany'];

    function CustomField($resource, $http, $q, Core, InvoicedConfig, selectedCompany) {
        let customFieldsCache = {};

        let CustomField = $resource(
            InvoicedConfig.apiBaseUrl + '/custom_fields/:id',
            {},
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
                    url: InvoicedConfig.apiBaseUrl + '/custom_fields',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );

        CustomField.getByObject = function (object) {
            return $q(function (resolve) {
                CustomField.all(
                    function (customFields) {
                        let data = [];
                        angular.forEach(customFields, function (customField) {
                            if (customField.object === object) {
                                data.push(customField);
                            }
                        });
                        resolve(data);
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                        resolve();
                    },
                );
            });
        };

        CustomField.all = function (success, error) {
            if (typeof customFieldsCache[selectedCompany.id] !== 'undefined') {
                success(customFieldsCache[selectedCompany.id]);
                return;
            }

            customFieldsCache[selectedCompany.id] = [];
            CustomField.findAll(
                { paginate: 'none' },
                function (result) {
                    customFieldsCache[selectedCompany.id] = result;
                    success(result);
                },
                error,
            );
        };

        CustomField.clearCache = clearCache;

        return CustomField;

        function clearCache(response) {
            if (typeof customFieldsCache[selectedCompany.id] !== 'undefined') {
                delete customFieldsCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
