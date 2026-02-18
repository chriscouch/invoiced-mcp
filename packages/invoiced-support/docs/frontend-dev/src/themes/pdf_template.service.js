(function () {
    'use strict';

    angular.module('app.themes').factory('PdfTemplate', PdfTemplateService);

    PdfTemplateService.$inject = ['$resource', 'InvoicedConfig'];

    function PdfTemplateService($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/pdf_templates/:id',
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
