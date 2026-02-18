(function () {
    'use strict';

    angular.module('app.metadata').factory('Metadata', Metadata);

    Metadata.$inject = ['$resource', 'InvoicedConfig'];

    function Metadata($resource, InvoicedConfig) {
        let Metadata = $resource(
            '',
            {},
            {
                automationFields: {
                    method: 'get',
                    url: InvoicedConfig.apiBaseUrl + '/_metadata/automation_fields',
                    cache: true,
                },
                importFields: {
                    method: 'get',
                    url: InvoicedConfig.apiBaseUrl + '/_metadata/import_fields',
                    cache: true,
                },
                reportFields: {
                    method: 'get',
                    url: InvoicedConfig.apiBaseUrl + '/_metadata/report_fields',
                    cache: true,
                },
            },
        );

        return Metadata;
    }
})();
