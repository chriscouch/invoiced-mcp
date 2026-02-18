(function () {
    'use strict';

    angular.module('app.core').filter('split', split);

    function split() {
        return function (input, splitChar) {
            return typeof input === 'string' ? input.split(splitChar) : '';
        };
    }
})();
