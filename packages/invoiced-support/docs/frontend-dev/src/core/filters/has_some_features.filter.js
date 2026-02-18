(function () {
    'use strict';

    angular.module('app.core').filter('hasSomeFeatures', hasSomeFeatures);

    hasSomeFeatures.$inject = ['Feature'];

    function hasSomeFeatures(Feature) {
        return function (features) {
            return Feature.hasSomeFeatures(features);
        };
    }
})();
