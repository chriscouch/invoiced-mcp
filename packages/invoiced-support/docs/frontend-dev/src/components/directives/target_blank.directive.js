(function () {
    'use strict';

    angular.module('app.components').directive('targetBlank', targetBlank);

    function targetBlank() {
        return {
            restrict: 'A',
            template: '<div ng-bind-html="content"></div>',
            scope: {
                content: '=',
            },
            link: function ($scope, $element) {
                let blank = function () {
                    let elems = $element.prop('tagName') === 'A' ? $element : $element.find('a');
                    elems.attr('target', '_blank');
                };

                $scope.$watch('content', blank);
            },
        };
    }
})();
