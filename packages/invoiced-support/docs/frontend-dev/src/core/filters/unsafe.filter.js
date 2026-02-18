(function () {
    'use strict';

    angular.module('app.core').filter('unsafe', unsafe);

    unsafe.$inject = ['$sce'];

    function unsafe($sce) {
        return function (val) {
            return $sce.trustAsHtml(val);
        };
    }
})();
