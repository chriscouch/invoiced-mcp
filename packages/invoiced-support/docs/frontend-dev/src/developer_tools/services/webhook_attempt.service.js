(function () {
    'use strict';

    angular.module('app.developer_tools').factory('WebhookAttempt', WebhookAttempt);

    WebhookAttempt.$inject = ['$resource', 'InvoicedConfig'];

    function WebhookAttempt($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/webhook_attempts/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                retry: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/webhook_attempts/:id/retries',
                },
            },
        );
    }
})();
