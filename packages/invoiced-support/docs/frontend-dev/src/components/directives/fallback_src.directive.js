(function () {
    'use strict';

    angular.module('app.components').directive('fallbackSrc', fallbackSrc);

    function fallbackSrc() {
        return {
            link: function postLink(scope, iElement, iAttrs) {
                iElement.bind('error', function () {
                    angular.element(this).attr('src', iAttrs.fallbackSrc);
                });
            },
        };
    }
})();
