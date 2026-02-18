(function () {
    'use strict';

    angular.module('app.components').directive('expandingTextarea', expandingTextarea);

    function expandingTextarea() {
        return {
            restrict: 'A',
            link: function (scope, element, attrs) {
                let $element = $(element);

                // The expanding textarea plugin is incompatible
                // with the Grammarly extension because of
                // how Grammarly modifies the DOM. This disables
                // Grammarly for this particular textarea.
                $element.attr('data-gramm', 'false');

                $element.expandingTextarea();

                scope.$watch(attrs.ngModel, function () {
                    $element.expandingTextarea('resize');
                });
            },
        };
    }
})();
