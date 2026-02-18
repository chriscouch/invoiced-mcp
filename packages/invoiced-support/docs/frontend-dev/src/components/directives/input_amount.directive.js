(function () {
    'use strict';

    angular.module('app.components').directive('inputAmount', inputAmount);

    function inputAmount() {
        return {
            restrict: 'A',
            template:
                '<div class="input-amount" ng-class="{\'is-percent\':!!isRate,\'with-selector\':hasSelector}">' +
                '<div class="addon currency-sign">{{currency|currencySymbol}}</div>' +
                '<input class="form-control" type="number" step="{{step}}" min="{{min}}" max="{{max}}" autocomplete="off" tabindex="{{tabindex}}" ng-model="value" ng-change="change()" ng-required="required" />' +
                '<div class="addon percent">%</div>' +
                '<div class="addon selector"><button type="button" class="btn" ng-click="toggleRate()"><span class="fas fa-repeat"></span></button></div>' +
                '</div>',
            scope: {
                currency: '=',
                isRate: '=',
                tabindex: '=ngTabindex',
                value: '=ngModel',
                change: '=ngChange',
                required: '=?',
                hasSelector: '=?',
                step: '@',
                min: '=?',
                max: '=?',
            },
            link: function ($scope, element, attrs) {
                if (!$scope.step) {
                    attrs.$set('step', 'any');
                }

                $scope.toggleRate = function () {
                    $scope.isRate = !$scope.isRate;
                };
            },
        };
    }
})();
