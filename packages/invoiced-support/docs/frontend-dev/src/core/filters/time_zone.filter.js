(function () {
    'use strict';

    angular.module('app.core').filter('timeZone', timeZone);

    function timeZone() {
        return function (timeZone) {
            if (typeof timeZone === 'undefined' || !timeZone) {
                return '';
            }

            return timeZone.replace('_', ' ');
        };
    }
})();
