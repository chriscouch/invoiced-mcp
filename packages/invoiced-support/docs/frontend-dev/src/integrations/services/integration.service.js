(function () {
    'use strict';

    angular.module('app.integrations').factory('Integration', IntegrationService);

    IntegrationService.$inject = ['$resource', 'InvoicedConfig'];

    function IntegrationService($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/integrations/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                },
                retrieve: {
                    method: 'GET',
                },
                enqueueSync: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/:id/enqueue_sync',
                    method: 'POST',
                },
                syncStatus: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/:id/sync_status',
                    method: 'GET',
                },
                getQuickBooksDesktopSyncs: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/syncs',
                    method: 'GET',
                },
                cancelQuickBooksDesktopSync: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/syncs/:id',
                    method: 'DELETE',
                },
                skipQuickBooksDesktopRecord: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/syncs/skipped_records',
                    method: 'POST',
                },
                syncedQuickBooksDesktopRecords: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/syncs/:id/records',
                    method: 'GET',
                    isArray: true,
                },
                settings: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/:id/settings',
                    method: 'GET',
                },
                editSyncProfile: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/:id/sync_profile',
                    method: 'PATCH',
                },
                avalaraCompanies: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/avalara/companies',
                    method: 'POST',
                    isArray: true,
                },
                intacctSalesDocumentTypes: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/intacct/sales_document_types',
                    method: 'GET',
                },
                earthClassMailInboxes: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/earth_class_mail/inboxes',
                    method: 'POST',
                    isArray: true,
                },
                connect: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/:id',
                    method: 'POST',
                },
                disconnect: {
                    url: InvoicedConfig.apiBaseUrl + '/integrations/:id',
                    method: 'DELETE',
                },
            },
        );
    }
})();
