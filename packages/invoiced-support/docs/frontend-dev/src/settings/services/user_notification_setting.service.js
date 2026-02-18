(function () {
    'use strict';

    angular.module('app.settings').factory('UserNotificationSetting', UserNotificationSetting);

    UserNotificationSetting.$inject = ['$resource', 'InvoicedConfig'];

    function UserNotificationSetting($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/user_notifications/settings',
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
            },
        );
    }
})();
