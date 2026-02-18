(function () {
    'use strict';

    angular.module('app.core').filter('htmlEntities', htmlEntities);

    function htmlEntities() {
        return function (str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        };
    }
})();
