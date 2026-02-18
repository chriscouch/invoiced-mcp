(function () {
    'use strict';

    angular.module('app.catalog').directive('itemTypeSelector', itemTypeSelector);

    function itemTypeSelector() {
        return {
            restrict: 'E',
            template:
                '<div class="invoiced-select">' +
                '<select tabindex="{{tabindex}}" ng-model="model">' +
                '<option value="">- None</option>' +
                '<option value="product">Product</option>' +
                '<option value="service">Service</option>' +
                '<option value="hours">Hour</option>' +
                '<option value="days">Day</option>' +
                '<option value="month">Month</option>' +
                '<option value="year">Year</option>' +
                '<option value="expense">Expense</option>' +
                '<option value="shipping">Shipping</option>' +
                '</select>' +
                '</div>',
            scope: {
                model: '=ngModel',
                tabindex: '=?ngTabindex',
            },
        };
    }
})();
