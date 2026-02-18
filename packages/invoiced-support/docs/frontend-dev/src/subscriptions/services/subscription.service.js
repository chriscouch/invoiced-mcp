(function () {
    'use strict';

    angular.module('app.subscriptions').factory('Subscription', Subscription);

    Subscription.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function Subscription($resource, $http, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/subscriptions/:id/:item',
            {
                id: '@id',
                item: '@item',
            },
            {
                findAll: {
                    method: 'GET',
                    params: {
                        include: 'customerName',
                        exclude: 'addons,discounts,taxes,ship_to,payment_source,approval,metadata',
                    },
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
                renewContract: {
                    method: 'POST',
                    params: {
                        item: 'renew_contract',
                    },
                },
                pause: {
                    method: 'POST',
                    params: {
                        item: 'pause',
                    },
                },
                resume: {
                    method: 'POST',
                    params: {
                        item: 'resume',
                    },
                },
                cancel: {
                    method: 'DELETE',
                },
                preview: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/subscriptions/preview',
                },
            },
        );
    }
})();
