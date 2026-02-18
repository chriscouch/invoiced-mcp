(function () {
    'use strict';

    angular.module('app.core').filter('range', range);

    function range() {
        return function (input, min, max) {
            min = parseInt(min); //Make string input int
            max = parseInt(max);
            for (let i = min; i <= max; i++) {
                input.push(i);
            }
            return input;
        };
    }
})();
