(function () {
    'use strict';

    angular.module('app.components').directive('toggle', toggle);

    function toggle() {
        return {
            restrict: 'E',
            template:
                '<label class="css-switch">' +
                '<input type="checkbox" ng-checked="model==1" ng-model="model" value="1" ng-change="change(model)" />' +
                '<span class="slider round"></span>' +
                '<span class="off">Off</span>' +
                '<span class="on">On</span>' +
                '</label>',
            scope: {
                model: '=ngModel',
                callback: '&?ngChange',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.change = function (value) {
                        if (typeof $scope.callback === 'function') {
                            $scope.callback({
                                value: value,
                            });
                        }
                    };
                },
            ],
        };
    }
})();
