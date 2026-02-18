(function () {
    'use strict';

    angular.module('app.sending').factory('SmsTemplate', SmsTemplate);

    SmsTemplate.$inject = ['$resource', '$http', '$cacheFactory', 'InvoicedConfig'];

    function SmsTemplate($resource, $http, $cacheFactory, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/sms_templates/:id',
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
                edit: {
                    method: 'PATCH',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
