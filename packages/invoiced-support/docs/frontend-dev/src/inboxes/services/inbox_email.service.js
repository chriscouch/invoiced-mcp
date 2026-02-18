(function () {
    'use strict';

    angular.module('app.settings').factory('InboxEmail', InboxEmailService);

    InboxEmailService.$inject = ['$resource', 'InvoicedConfig', 'Core'];

    function InboxEmailService($resource, InvoicedConfig, Core) {
        let InboxEmail = $resource(
            InvoicedConfig.apiBaseUrl + '/inboxes/:inboxid/emails',
            {
                id: '@id',
                inboxid: '@inboxid',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                find: {
                    url: InvoicedConfig.apiBaseUrl + '/emails/:id',
                    method: 'GET',
                },
                attachments: {
                    url: InvoicedConfig.apiBaseUrl + '/emails/:id/attachments',
                    method: 'GET',
                    isArray: true,
                },
                message: {
                    url: InvoicedConfig.apiBaseUrl + '/emails/:id/message',
                    method: 'GET',
                },
            },
        );

        InboxEmail.getEmailMessage = function (email) {
            let previewLength = 200;
            if (email.message === undefined) {
                this.message(
                    {
                        id: email.id,
                    },
                    function (result) {
                        email.message = result.html;
                        email.text = result.text;
                        email.preview = null;
                        if (result.text) {
                            email.preview =
                                result.text.length > previewLength
                                    ? result.text.substring(0, previewLength) + '...'
                                    : result.text;
                        }
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            }
        };

        InboxEmail.getEmailAttachments = function (email) {
            if (email.attachments === undefined) {
                this.attachments(
                    {
                        id: email.id,
                    },
                    function (result) {
                        email.attachments = result;
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            }
        };

        return InboxEmail;
    }
})();
