(function () {
    'use strict';

    angular.module('app.settings').factory('Inbox', InboxService);

    InboxService.$inject = ['$resource', 'InvoicedConfig'];

    function InboxService($resource, InvoicedConfig) {
        let url = InvoicedConfig.apiBaseUrl + '/inboxes';

        return $resource(
            url + '/:id',
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
                    cache: true,
                },
                send: {
                    url: url + '/:id/emails',
                    method: 'POST',
                    transformRequest: appendTransform,
                },
                migrate: {
                    url: url + '/migrate',
                    method: 'POST',
                },
            },
        );

        function appendTransform(data) {
            data.to = data.to.map(mapRecipient);
            data.cc = data.cc ? data.cc.map(mapRecipient) : [];
            data.bcc = data.bcc ? data.bcc.map(mapRecipient) : [];
            return angular.toJson(data);
        }

        function mapRecipient(obj) {
            return {
                name: obj.name,
                email_address: obj.email_address,
                id: obj.id === obj.email_address ? null : obj.id,
            };
        }
    }
})();
