(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('paymentMethodSelector', paymentMethodSelector);

    function paymentMethodSelector() {
        return {
            restrict: 'E',
            template:
                '<div class="invoiced-select">' +
                '<select ng-model="model" ng-options="m.id as m.name for m in methods"></select>' +
                '</div>',
            scope: {
                model: '=ngModel',
                disabledMethods: '=?',
            },
            controller: [
                '$scope',
                '$translate',
                'selectedCompany',
                function ($scope, $translate, selectedCompany) {
                    $scope.methods = [
                        {
                            name: $translate.instant('payment_method.bank_transfer'),
                            id: 'bank_transfer',
                        },
                        {
                            name: $translate.instant('payment_method.cash'),
                            id: 'cash',
                        },
                        {
                            name: $translate.instant('payment_method.check'),
                            id: 'check',
                        },
                        {
                            name: $translate.instant('payment_method.credit_card'),
                            id: 'credit_card',
                        },
                        {
                            name: $translate.instant('payment_method.direct_debit'),
                            id: 'direct_debit',
                        },
                        {
                            name: $translate.instant('payment_method.eft'),
                            id: 'eft',
                        },
                        {
                            name: $translate.instant('payment_method.online'),
                            id: 'online',
                        },
                        {
                            name: $translate.instant('payment_method.other'),
                            id: 'other',
                        },
                        {
                            name: $translate.instant('payment_method.paypal'),
                            id: 'paypal',
                        },
                        {
                            name: $translate.instant('payment_method.wire_transfer'),
                            id: 'wire_transfer',
                        },
                    ];

                    // U.S. companies have an ACH method instead of EFT
                    if (selectedCompany.country === 'US') {
                        $scope.methods[5] = {
                            name: $translate.instant('payment_method.ach'),
                            id: 'ach',
                        };
                        $scope.methods.sort(function (a, b) {
                            return a.name > b.name ? 1 : -1;
                        });
                    }

                    // remove any disabled methods
                    $scope.disabledMethods = $scope.disabledMethods || [];

                    // warning: O(N^2)
                    angular.forEach($scope.disabledMethods, function (method) {
                        for (let i in $scope.methods) {
                            if ($scope.methods[i].id === method) {
                                $scope.methods.splice(i, 1);
                            }
                        }
                    });
                },
            ],
        };
    }
})();
