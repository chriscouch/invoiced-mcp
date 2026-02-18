(function () {
    'use strict';

    angular.module('app.components').directive('columnArrangementCell', columnArrangementCell);

    function columnArrangementCell() {
        return {
            restrict: 'E',
            templateUrl: 'ui/views/column-arrangement-cell.html',
            scope: {
                data: '=',
                column: '=',
                currency: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.type = $scope.column.type || 'string';
                    $scope.default = $scope.column.defaultValue || '';
                    let hierarchy = $scope.column.id.split('.');
                    $scope.enumClass = '';

                    $scope.$watch(
                        'data',
                        function (data) {
                            $scope.value = data
                                ? hierarchy.reduce(function (accumulator, currentValue) {
                                      return accumulator ? accumulator[currentValue] : null;
                                  }, data)
                                : '';
                        },
                        true,
                    );

                    $scope.getEnumValue = function (column, value) {
                        $scope.enumClass = '';
                        for (let i in column.values) {
                            if (column.values[i].value === value) {
                                $scope.enumClass = column.values[i].class || '';

                                return column.values[i].text;
                            }
                        }

                        return value;
                    };
                },
            ],
        };
    }
})();
