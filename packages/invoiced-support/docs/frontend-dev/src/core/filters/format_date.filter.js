/* globals moment */
(function () {
    'use strict';

    angular.module('app.core').filter('formatDate', formatDate);

    formatDate.$inject = ['Core'];

    function formatDate(Core) {
        return function (date, date_format) {
            if (typeof date_format === 'undefined') {
                return date;
            }

            if (!isNaN(date) && !(date instanceof Date)) {
                // Attempt to parse UNIX timestamp
                if (date <= 0) {
                    return '';
                }

                date = moment.unix(date).toDate();
            } else if (typeof date === 'string') {
                // Attempt to parse ISO-8601 formats
                let mDate = moment(date);
                if (!mDate.isValid()) {
                    return date;
                }

                date = mDate;
            }

            if (date_format === 'relative') {
                return moment(date).fromNow();
            }

            return moment(date).format(Core.phpDateFormatToMoment(date_format));
        };
    }
})();
