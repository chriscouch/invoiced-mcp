(function () {
    'use strict';

    angular.module('app.files').factory('Attachment', Attachment);

    Attachment.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function Attachment($resource, $http, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/attachments/:id',
            {
                id: '@id',
            },
            {
                create: {
                    method: 'POST',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
