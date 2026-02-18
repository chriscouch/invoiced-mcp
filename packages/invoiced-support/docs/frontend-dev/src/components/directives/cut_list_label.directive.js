(function () {
    'use strict';

    angular.module('app.components').directive('cutListLabel', cutListLabel);

    function cutListLabel() {
        return {
            restrict: 'E',
            template:
                '<div class="row label-cut-row" ng-if="total > count">' +
                '<div class="col-sm-12 label-cut-column">' +
                '<div class="total-records" ng-bind-html="\'general.showing_first\'|translate:{count:count, total: total}"></div>' +
                '</div>' +
                '</div>',
            scope: {
                count: '=',
                total: '=',
            },
        };
    }
})();
