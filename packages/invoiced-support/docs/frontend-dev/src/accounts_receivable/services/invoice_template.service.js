(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('InvoiceTemplate', InvoiceTemplateService);

    InvoiceTemplateService.$inject = ['$resource', '$http', 'selectedCompany', 'InvoicedConfig', 'Core'];

    function InvoiceTemplateService($resource, $http, selectedCompany, InvoicedConfig, Core) {
        let InvoiceTemplate = $resource(
            InvoicedConfig.apiBaseUrl + '/invoice_templates/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
            },
        );

        InvoiceTemplate.templates = function (cb, refresh) {
            refresh = refresh || false;

            // load from API
            if (!selectedCompany.invoiceTemplates || refresh) {
                InvoiceTemplate.findAll(
                    { paginate: 'none' },
                    function (templates) {
                        selectedCompany.invoiceTemplates = {};

                        angular.forEach(templates, function (template) {
                            selectedCompany.invoiceTemplates[template.id] = template;
                        });

                        cb(selectedCompany.invoiceTemplates);
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                        selectedCompany.invoiceTemplates = {};
                        cb(selectedCompany.invoiceTemplates);
                    },
                );
                // load from cache
            } else {
                cb(selectedCompany.invoiceTemplates);
            }
        };

        return InvoiceTemplate;
    }
})();
