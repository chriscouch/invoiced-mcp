(function () {
    'use strict';

    angular.module('app.settings').factory('CompanyNotificationSetting', CompanyNotificationSetting);

    CompanyNotificationSetting.$inject = ['$resource', 'InvoicedConfig'];

    function CompanyNotificationSetting($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/company_notifications/settings',
            {},
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                setAll: {
                    method: 'POST',
                    isArray: true,
                },
                convert: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/company_notifications/users',
                    isArray: true,
                },
            },
        );
    }
})();
