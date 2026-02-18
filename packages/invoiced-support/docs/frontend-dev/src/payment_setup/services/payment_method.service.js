(function () {
    'use strict';

    angular.module('app.payment_setup').factory('PaymentMethod', PaymentMethodService);

    PaymentMethodService.$inject = [
        '$resource',
        '$http',
        '$cacheFactory',
        'InvoicedConfig',
        'selectedCompany',
        'Money',
    ];

    function PaymentMethodService($resource, $http, $cacheFactory, InvoicedConfig, selectedCompany, Money) {
        let PaymentMethod = $resource(
            InvoicedConfig.apiBaseUrl + '/payment_methods/:id/:item',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    cache: true,
                    isArray: true,
                    transformResponse: appendTransform($http.defaults.transformResponse, transformGetResponse),
                },
                find: {
                    method: 'GET',
                },
                edit: {
                    method: 'PATCH',
                    transformRequest: appendTransform($http.defaults.transformRequest, transformPatchRequest),
                    transformResponse: appendTransform($http.defaults.transformResponse, transformPatchResponse),
                },
            },
        );

        function appendTransform(defaults, transform) {
            defaults = angular.isArray(defaults) ? defaults : [defaults];
            return defaults.concat(transform);
        }

        function transformGetResponse(data) {
            angular.forEach(data, function (value) {
                denormalizePaymentLimits(value);
            });
            return data;
        }

        function transformPatchRequest(data) {
            if (typeof data === 'string') {
                //if data is string convert to JS array
                data = JSON.parse(data);
            }
            normalizePaymentLimits(data);
            data = JSON.stringify(data);
            return data;
        }

        function transformPatchResponse(data) {
            denormalizePaymentLimits(data);
            $cacheFactory.get('$http').remove(InvoicedConfig.apiBaseUrl + '/payment_methods?expand=merchant_account');
            $cacheFactory
                .get('$http')
                .remove(InvoicedConfig.apiBaseUrl + '/payment_methods?expand=merchant_account&paginate=none');
            $cacheFactory.get('$http').remove(InvoicedConfig.apiBaseUrl + '/payment_methods?paginate=none');
            $cacheFactory.get('$http').remove(InvoicedConfig.apiBaseUrl + '/payment_methods');
            return data;
        }

        function normalizePaymentLimits(data) {
            let originalMax = data.max;
            data.min = Money.normalizeToZeroDecimal(selectedCompany.currency, data.min);
            data.max = Money.normalizeToZeroDecimal(selectedCompany.currency, data.max);
            if (data.max === 0 && originalMax !== 0) {
                data.max = null;
            }
        }

        function denormalizePaymentLimits(data) {
            data.min = Money.denormalizeFromZeroDecimal(selectedCompany.currency, data.min);
            data.max = Money.denormalizeFromZeroDecimal(selectedCompany.currency, data.max);
            if (data.max === 0) {
                data.max = null;
            }
        }

        return PaymentMethod;
    }
})();
