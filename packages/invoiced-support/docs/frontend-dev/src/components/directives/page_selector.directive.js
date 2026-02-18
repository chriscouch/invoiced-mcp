(function () {
    'use strict';

    angular.module('app.components').directive('pageSelector', pageSelector);

    function pageSelector() {
        return {
            restrict: 'E',
            template:
                '<div class="page-selector" ng-if="pageCount>1 && pageCount<=100">' +
                '<select ng-model="$parent.page" ng-change="change($parent.page)" ng-options="n for n in [] | range:1:pageCount"></select>' +
                '</div>' +
                '<div class="page-selector" ng-if="pageCount>100">' +
                '<span class="page">{{page}}</span>' +
                '</div>',
            scope: {
                page: '=ngModel',
                pageCount: '=',
                callback: '&?ngChange',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.change = function (page) {
                        if (typeof $scope.callback === 'function') {
                            $scope.callback({
                                page: page,
                            });
                        }
                    };
                },
            ],
        };
    }
})();
