(function () {
    'use strict';

    angular.module('app.settings').directive('notificationFrequency', notificationFrequency);

    function notificationFrequency() {
        return {
            restrict: 'E',
            templateUrl: 'settings/views/notifications_frequency_directive.html',
            scope: {
                notifications: '=',
                saveNotification: '=',
                enableSelection: '=',
            },
        };
    }
})();
