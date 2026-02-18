(function () {
    'use strict';

    angular.module('app.collections').directive('accountBalanceBar', accountBalanceBar);

    function accountBalanceBar() {
        return {
            restrict: 'E',
            template:
                '<a href="" tooltip-placement="top">' +
                '<div class="balance-bar">' +
                '<div class="bg" style="width:{{bgWidth}}%;"></div>' +
                '<div class="title"><money amount="balance" currency="currency"></money></div>' +
                '</div>' +
                '</a>',
            scope: {
                balance: '=',
                currency: '=',
                max: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    update();

                    function update() {
                        if ($scope.max === 0) {
                            $scope.bgWidth = 100;
                        } else {
                            $scope.bgWidth = Math.min(100, Math.max(0, ($scope.balance / $scope.max) * 100));
                        }
                    }
                },
            ],
        };
    }
})();
