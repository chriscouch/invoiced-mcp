(function () {
    'use strict';

    angular.module('app.components').directive('countrySelector', countrySelector);

    function countrySelector() {
        let countries;

        return {
            restrict: 'E',
            template:
                '<div class="invoiced-select">' +
                '<select ng-model="model" name="country" ng-options="c.code as c.country for c in countries" ng-change="change(model)" ng-required="required"></select>' +
                '</div>',
            scope: {
                model: '=ngModel',
                required: '=isRequired',
                callback: '&?ngChange',
                defaultValue: '=?',
            },
            controller: [
                'InvoicedConfig',
                '$scope',
                function (InvoicedConfig, $scope) {
                    if (!countries) {
                        countries = angular.copy(InvoicedConfig.countries);
                    }
                    if ($scope.defaultValue === undefined) {
                        $scope.defaultValue = '';
                    }
                    $scope.countries = countries.filter(function (country) {
                        return country.code !== '';
                    });
                    $scope.countries.push({
                        code: '',
                        country: $scope.defaultValue,
                    });

                    $scope.change = function (country) {
                        if (typeof $scope.callback === 'function') {
                            $scope.callback({
                                country: country,
                            });
                        }
                    };
                },
            ],
        };
    }
})();
