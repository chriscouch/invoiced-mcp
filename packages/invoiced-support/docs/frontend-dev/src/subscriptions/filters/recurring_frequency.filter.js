(function () {
    'use strict';

    angular.module('app.subscriptions').filter('recurringFrequency', recurringFrequency);

    function recurringFrequency() {
        let intervalsS = {
            day: 'Daily',
            week: 'Weekly',
            month: 'Monthly',
            year: 'Yearly',
        };
        let intervalsP = {
            day: 'days',
            week: 'weeks',
            month: 'months',
            year: 'years',
        };
        return function (interval_count, interval, titleize) {
            titleize = titleize || false;
            let value = name(interval_count, interval);

            return titleize ? value : value.toLowerCase();
        };

        function name(interval_count, interval) {
            // shortcuts
            if (interval_count == 3 && interval == 'month') {
                return 'Quarterly';
            }

            if (interval_count == 6 && interval == 'month') {
                return 'Semiannually';
            }

            if (interval_count == 1) {
                return intervalsS[interval];
            }

            return 'Every ' + interval_count + ' ' + intervalsP[interval];
        }
    }
})();
