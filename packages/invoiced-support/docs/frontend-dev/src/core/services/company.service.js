(function () {
    'use strict';

    angular.module('app.core').factory('Company', Company);

    Company.$inject = ['$resource', '$http', '$rootScope', 'CurrentUser', 'InvoicedConfig', 'selectedCompany'];

    function Company($resource, $http, $rootScope, CurrentUser, InvoicedConfig, selectedCompany) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/companies/:id/:action/:subid',
            {
                id: '@id',
            },
            {
                current: {
                    url: InvoicedConfig.apiBaseUrl + '/companies/current',
                },
                create: {
                    url: InvoicedConfig.baseUrl + '/auth/new_company',
                    method: 'POST',
                    noAuth: true,
                },
                find: {
                    method: 'GET',
                },
                edit: {
                    method: 'PATCH',
                    params: {
                        include: 'billing,features',
                    },
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let company = response;

                        angular.extend(selectedCompany, company);

                        return company;
                    }),
                },
                resendVerificationEmail: {
                    method: 'POST',
                    url: InvoicedConfig.baseUrl + '/companies/:id/resendVerificationEmail',
                    noAuth: true,
                },
                billingInfo: {
                    method: 'GET',
                    url: InvoicedConfig.baseUrl + '/companies/:id/billing',
                    noAuth: true,
                    transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                        if (status !== 200) {
                            return response;
                        }

                        angular.extend(selectedCompany, response.company);

                        return response;
                    }),
                },
                reactivateBilling: {
                    method: 'PUT',
                    url: InvoicedConfig.baseUrl + '/companies/:id/reactivate',
                    noAuth: true,
                },
                setDefaultPaymentMethod: {
                    method: 'PUT',
                    url: InvoicedConfig.baseUrl + '/companies/:id/default_payment_method',
                    noAuth: true,
                },
                cancelAccount: {
                    url: InvoicedConfig.baseUrl + '/companies/:id/cancel',
                    method: 'POST',
                    noAuth: true,
                },
                clearData: {
                    method: 'POST',
                    params: {
                        action: 'clear',
                    },
                },
                setupProgress: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/_setup',
                },
                changeUserCount: {
                    method: 'PUT',
                    url: InvoicedConfig.baseUrl + '/companies/:id/extra_users',
                    noAuth: true,
                },
                setCustomDomain: {
                    method: 'POST',
                    params: {
                        action: 'custom_domain',
                    },
                },
                attachments: {
                    url: InvoicedConfig.apiBaseUrl + '/customer_portal_attachments',
                    method: 'GET',
                    isArray: true,
                },
                attach: {
                    url: InvoicedConfig.apiBaseUrl + '/customer_portal_attachments',
                    method: 'POST',
                },
            },
        );
    }
})();
