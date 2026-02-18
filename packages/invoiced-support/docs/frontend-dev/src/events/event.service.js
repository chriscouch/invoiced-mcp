(function () {
    'use strict';

    angular.module('app.events').factory('Event', EventService);

    EventService.$inject = ['$resource', 'InvoicedConfig'];

    function EventService($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/events/:id',
            {
                id: '@id',
                webhookId: '@webhookId',
            },
            {
                findAll: {
                    method: 'GET',
                    params: {
                        include: 'message,user',
                    },
                    isArray: true,
                },
                retrieve: {
                    method: 'GET',
                    params: {
                        include: 'user',
                    },
                },
                webhooks: {
                    url: InvoicedConfig.apiBaseUrl + '/events/:id/webhooks',
                    method: 'GET',
                    isArray: true,
                },
                retryWebhook: {
                    url: InvoicedConfig.apiBaseUrl + '/events/:id/webhooks/:webhookId/retries',
                    method: 'POST',
                },
            },
        );
    }
})();
