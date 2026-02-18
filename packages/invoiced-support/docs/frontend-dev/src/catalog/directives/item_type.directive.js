(function () {
    'use strict';

    angular.module('app.catalog').directive('itemType', itemType);

    function itemType() {
        return {
            restrict: 'E',
            template:
                '<span class="label label-info" ng-show="item.type==\'service\'">Service</span>' +
                '<span class="label label-success" ng-show="item.type==\'product\'">Product</span>' +
                '<span class="label label-primary" ng-show="item.type==\'hours\'">Hour</span>' +
                '<span class="label label-primary" ng-show="item.type==\'days\'">Day</span>' +
                '<span class="label label-primary" ng-show="item.type==\'month\'">Month</span>' +
                '<span class="label label-primary" ng-show="item.type==\'year\'">Year</span>' +
                '<span class="label label-danger" ng-show="item.type==\'expense\'">Expense</span>' +
                '<span class="label label-danger" ng-show="item.type==\'shipping\'">Shipping</span>' +
                '<span class="label label-warning" ng-show="item.type==\'plan\'">Plan</span>',
            scope: {
                item: '=',
            },
        };
    }
})();
