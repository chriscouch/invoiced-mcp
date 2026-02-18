(function () {
    'use strict';

    angular.module('app.core').filter('hasFeature', hasFeature);

    hasFeature.$inject = ['Feature'];

    function hasFeature(Feature) {
        return function (feature) {
            return Feature.hasFeature(feature);
        };
    }
})();
