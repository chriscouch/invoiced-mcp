(function () {
    'use strict';

    angular.module('app.settings').factory('Template', TemplateService);

    TemplateService.$inject = ['$resource', 'InvoicedConfig'];

    function TemplateService($resource, InvoicedConfig) {
        let url = InvoicedConfig.apiBaseUrl + '/templates/:id';

        return $resource(
            url,
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
            },
        );
    }
})();
