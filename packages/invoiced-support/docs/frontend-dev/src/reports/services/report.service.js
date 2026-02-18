(function () {
    'use strict';

    angular.module('app.reports').factory('Report', Report);

    Report.$inject = ['$resource', 'InvoicedConfig'];

    function Report($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/reports/:id',
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
                refresh: {
                    url: InvoicedConfig.apiBaseUrl + '/reports/:id/refresh',
                    method: 'POST',
                },
                download: {
                    url: InvoicedConfig.apiBaseUrl + '/reports/:id/download',
                    method: 'POST',
                },
                findAllSaved: {
                    url: InvoicedConfig.apiBaseUrl + '/saved_reports',
                    method: 'GET',
                    isArray: true,
                },
                createSavedReport: {
                    url: InvoicedConfig.apiBaseUrl + '/saved_reports',
                    method: 'POST',
                },
                editSavedReport: {
                    url: InvoicedConfig.apiBaseUrl + '/saved_reports/:id',
                    method: 'PATCH',
                },
                deleteSavedReport: {
                    url: InvoicedConfig.apiBaseUrl + '/saved_reports/:id',
                    method: 'DELETE',
                },
                findAllScheduled: {
                    url: InvoicedConfig.apiBaseUrl + '/scheduled_reports',
                    method: 'GET',
                    isArray: true,
                },
                createScheduledReport: {
                    url: InvoicedConfig.apiBaseUrl + '/scheduled_reports',
                    method: 'POST',
                },
                editScheduledReport: {
                    url: InvoicedConfig.apiBaseUrl + '/scheduled_reports/:id',
                    method: 'PATCH',
                },
                deleteScheduledReport: {
                    url: InvoicedConfig.apiBaseUrl + '/scheduled_reports/:id',
                    method: 'DELETE',
                },
            },
        );
    }
})();
