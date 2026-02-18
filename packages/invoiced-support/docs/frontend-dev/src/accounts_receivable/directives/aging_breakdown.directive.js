(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('agingBreakdown', agingBreakdown);

    function agingBreakdown() {
        return {
            restrict: 'E',
            template:
                '<div class="aging-breakdown">' +
                '<div class="bar">' +
                '<a href="" tooltip="{{title(entry)}}" class="bg {{class(entry)}}" style="width:{{bgWidth(entry.amount)}}%" ng-if="entry.amount > 0" ng-repeat="entry in aging"></a>' +
                '</div>' +
                '</div>',
            scope: {
                aging: '=',
                currency: '=',
            },
            controller: [
                '$scope',
                'Money',
                'selectedCompany',
                function ($scope, Money, selectedCompany) {
                    let total = 0;

                    $scope.$watch('aging', function () {
                        total = 0;
                        angular.forEach($scope.aging, function (row) {
                            if (row.amount > 0) {
                                total += row.amount;
                            }
                        });
                    });

                    $scope.bgWidth = function (amount) {
                        if (!total || amount < 0) {
                            return 0;
                        } else {
                            return Math.round((amount / total) * 100 * 1000) / 1000.0;
                        }
                    };

                    $scope.class = function (entry) {
                        return 'age-severity-' + entry.severity;
                    };

                    $scope.title = function (entry) {
                        return (
                            entry.title +
                            ': ' +
                            Money.currencyFormat(entry.amount, $scope.currency, selectedCompany.moneyFormat)
                        );
                    };
                },
            ],
        };
    }
})();
