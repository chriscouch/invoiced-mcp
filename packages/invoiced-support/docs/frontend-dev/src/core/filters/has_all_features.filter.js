(function () {
    'use strict';

    angular.module('app.core').filter('hasAllFeatures', hasAllFeatures);

    hasAllFeatures.$inject = ['Feature'];

    function hasAllFeatures(Feature) {
        return function (features) {
            return Feature.hasAllFeatures(features);
        };
    }
})();
