(function () {
    'use strict';

    angular.module('app.sending').factory('Letter', Letter);

    Letter.$inject = ['$resource', 'InvoicedConfig'];

    function Letter($resource, InvoicedConfig) {
        let url = InvoicedConfig.apiBaseUrl + '/letters';

        return $resource(
            InvoicedConfig.apiBaseUrl + '/letters/:id',
            {
                id: '@id',
            },
            {
                find: {
                    method: 'GET',
                    cache: true,
                    params: {
                        include: 'detail',
                    },
                },
                relatedLetters: {
                    url: url + '/document/:documentType/:documentId',
                    method: 'GET',
                    isArray: true,
                },
            },
        );
    }
})();
