(function () {
    'use strict';

    angular.module('app.settings').factory('Settings', Settings);

    Settings.$inject = ['$resource', '$http', '$cacheFactory', 'InvoicedConfig'];

    function Settings($resource, $http, $cacheFactory, InvoicedConfig) {
        let url = InvoicedConfig.apiBaseUrl + '/settings';

        return $resource(
            url,
            {
                id: '@id',
            },
            {
                accountsPayable: {
                    method: 'GET',
                    url: url + '/accounts_payable',
                    cache: true,
                },
                editAccountsPayable: {
                    method: 'PATCH',
                    url: url + '/accounts_payable',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                accountsReceivable: {
                    method: 'GET',
                    url: url + '/accounts_receivable',
                    cache: true,
                },
                editAccountsReceivable: {
                    method: 'PATCH',
                    url: url + '/accounts_receivable',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                cashApplication: {
                    method: 'GET',
                    url: url + '/cash_application',
                    cache: true,
                },
                editCashApplication: {
                    method: 'PATCH',
                    url: url + '/cash_application',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                customerPortal: {
                    method: 'GET',
                    url: url + '/customer_portal',
                    cache: true,
                },
                editCustomerPortal: {
                    method: 'PATCH',
                    url: url + '/customer_portal',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                saml: {
                    method: 'GET',
                    url: url + '/saml',
                    cache: true,
                },
                editSaml: {
                    method: 'POST',
                    url: url + '/saml',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                subscriptionBilling: {
                    method: 'GET',
                    url: url + '/subscription_billing',
                    cache: true,
                },
                editSubscriptionBilling: {
                    method: 'PATCH',
                    url: url + '/subscription_billing',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
            },
        );

        function clearCache(response) {
            $cacheFactory.get('$http').remove(url + '/accounts_receivable');
            $cacheFactory.get('$http').remove(url + '/accounts_payable');
            $cacheFactory.get('$http').remove(url + '/cash_application');
            $cacheFactory.get('$http').remove(url + '/customer_portal');
            $cacheFactory.get('$http').remove(url + '/saml');
            $cacheFactory.get('$http').remove(url + '/subscription_billing');
            return response;
        }
    }
})();
