(function () {
    'use strict';

    angular.module('app.sending').factory('TextMessage', TextMessage);

    TextMessage.$inject = ['$resource', 'InvoicedConfig'];

    function TextMessage($resource, InvoicedConfig) {
        let url = InvoicedConfig.apiBaseUrl + '/text_messages';

        return $resource(
            InvoicedConfig.apiBaseUrl + '/text_messages/:id',
            {
                id: '@id',
            },
            {
                find: {
                    method: 'GET',
                    cache: true,
                },
                relatedTexts: {
                    url: url + '/document/:documentType/:documentId',
                    method: 'GET',
                    isArray: true,
                },
            },
        );
    }
})();
