(function () {
    'use strict';

    angular.module('app.core').filter('metricNumber', metricNumber);

    function metricNumber() {
        return function (n, digits) {
            digits = typeof digits === 'undefined' ? 1 : digits;
            let thresh = 1000;
            if (Math.abs(n) < thresh) {
                return n;
            }
            let units = ['k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
            let u = -1;
            do {
                n /= thresh;
                ++u;
            } while (Math.abs(n) >= thresh && u < units.length - 1);

            return n.toFixed(digits) + ' ' + units[u];
        };
    }
})();
