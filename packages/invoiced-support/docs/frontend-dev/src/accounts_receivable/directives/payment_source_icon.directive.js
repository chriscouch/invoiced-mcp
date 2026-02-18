(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('paymentSourceIcon', paymentSourceIcon);

    paymentSourceIcon.inject = ['PaymentDisplayHelper'];

    function paymentSourceIcon(PaymentDisplayHelper) {
        let cardBrandClasses = {
            'american-express': 'fab fa-cc-amex',
            'diners-club': 'fab fa-cc-diners-club',
            discover: 'fab fa-cc-discover',
            jcb: 'fab fa-cc-jcb',
            mastercard: 'fab fa-cc-mastercard',
            visa: 'fab fa-cc-visa',
        };

        return {
            restrict: 'E',
            template:
                '<a href="" class="payment-source-icon no-margin" ng-class="{unverified: unverified}" tooltip="{{tooltip}}" tooltip-placement="right">' +
                '<span class="{{icon}}"></span>' +
                '</a>',
            scope: {
                source: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.$watch('source', function (source) {
                        if (!source) {
                            return;
                        }

                        $scope.unverified = false;

                        if (source.object === 'card') {
                            let iconClass = 'fad fa-credit-card';
                            let brand = (source.brand || '').toLowerCase().replace(' ', '-');
                            if (typeof cardBrandClasses[brand] !== 'undefined') {
                                iconClass = cardBrandClasses[brand];
                            }

                            $scope.icon = iconClass;

                            $scope.tooltip = PaymentDisplayHelper.formatCard(
                                source.brand,
                                source.last4,
                                source.exp_month,
                                source.exp_year,
                            );
                        } else if (source.object === 'bank_account') {
                            $scope.icon = 'fad fa-university';
                            $scope.unverified = !source.verified;

                            $scope.tooltip = PaymentDisplayHelper.formatBankAccount(source.bank_name, source.last4);

                            if ($scope.unverified) {
                                $scope.tooltip += ', Unverified';
                            }
                        }
                    });
                },
            ],
        };
    }
})();
