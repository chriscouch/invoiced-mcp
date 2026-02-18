(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('invoiceBalanceBar', invoiceBalanceBar);

    function invoiceBalanceBar() {
        return {
            restrict: 'E',
            template:
                '<a href="" tooltip="{{percentPaid}}% paid" tooltip-placement="top">' +
                '<div class="balance-bar">' +
                '<div class="bg" style="width:{{bgWidth}}%;"><div class="paid" style="width:{{paidWidth}}%;"></div></div>' +
                '<div class="title"><money amount="invoice.balance" currency="invoice.currency"></money></div>' +
                '</div>' +
                '</a>',
            scope: {
                invoice: '=',
                max: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.$watch('invoice', update);

                    function update(invoice) {
                        if (!invoice) {
                            return;
                        }

                        if ($scope.max === 0) {
                            $scope.bgWidth = 100;
                        } else {
                            $scope.bgWidth = Math.min(100, Math.max(0, (invoice.balance / $scope.max) * 100));
                        }

                        if (invoice.total === 0) {
                            $scope.paidWidth = 100;
                            $scope.percentPaid = 100;
                        } else {
                            $scope.paidWidth = Math.min(
                                100,
                                Math.max(0, ((invoice.total - invoice.balance) / invoice.total) * 100),
                            );
                            $scope.percentPaid = Math.round($scope.paidWidth);
                        }
                    }
                },
            ],
        };
    }
})();
