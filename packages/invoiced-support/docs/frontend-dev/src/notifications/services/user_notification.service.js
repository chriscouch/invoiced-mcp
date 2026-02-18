(function () {
    'use strict';

    angular.module('app.notifications').factory('UserNotification', UserNotification);

    UserNotification.$inject = ['$resource', 'InvoicedConfig'];

    function UserNotification($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/user_notifications',
            {},
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        per_page: 100,
                        expand: 'notification_event',
                    },
                },
                subscription: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/user_notifications/subscription',
                },
                latest: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/user_notifications/latest',
                },
            },
        );
    }
})();
