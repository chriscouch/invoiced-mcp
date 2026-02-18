(function () {
    'use strict';

    angular.module('app.content').factory('SupportTicket', SupportTicket);

    SupportTicket.$inject = ['$resource', 'InvoicedConfig'];

    function SupportTicket($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.baseUrl + '/support_tickets',
            {},
            {
                create: {
                    method: 'POST',
                    noAuth: true,
                },
            },
        );
    }
})();
