(function () {
    'use strict';

    angular.module('app.subscriptions').filter('intervalDuration', intervalDuration);

    function intervalDuration() {
        let intervals = {
            day: 'days',
            week: 'weeks',
            month: 'months',
            year: 'years',
        };
        return function (interval_count, interval) {
            if (interval_count == 1) {
                return intervals[interval];
            } else {
                return interval_count + ' ' + intervals[interval];
            }
        };
    }
})();
