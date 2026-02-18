(function () {
    'use strict';

    angular.module('app.components').directive('stateSelector', stateSelector);

    function stateSelector() {
        return {
            restrict: 'E',
            template:
                '<input type="text" class="form-control" ng-if="!isSelect" ng-model="$parent.model" ng-change="$parent.change($parent.model)" placeholder="{{placeholder}}" />' +
                '<div class="invoiced-select" ng-if="isSelect">' +
                '<select ng-model="$parent.model" ng-options="state.code as state.name for state in states" ng-change="$parent.change($parent.model)" ng-required="required"></select>' +
                '</div>',
            scope: {
                model: '=ngModel',
                country: '=country',
                required: '=isRequired',
                placeholder: '=placeholder',
                callback: '&?ngChange',
            },
            controller: [
                'InvoicedConfig',
                '$scope',
                function (InvoicedConfig, $scope) {
                    process($scope.country, false);
                    $scope.$watch('country', process);

                    $scope.change = function (state) {
                        if (typeof $scope.callback === 'function') {
                            $scope.callback({
                                state: state,
                            });
                        }
                    };

                    function process(countryId, old) {
                        if (!countryId) {
                            $scope.isSelect = false;
                        }

                        // no change
                        if (countryId === old) {
                            return;
                        }

                        angular.forEach(InvoicedConfig.countries, function (country) {
                            if (country.code == countryId) {
                                if (country.states) {
                                    $scope.states = angular.copy(country.states);
                                    $scope.states.push({
                                        code: '',
                                        name: '',
                                    });
                                    $scope.isSelect = true;
                                } else {
                                    $scope.isSelect = false;
                                }
                                return;
                            }
                        });
                    }
                },
            ],
        };
    }
})();
