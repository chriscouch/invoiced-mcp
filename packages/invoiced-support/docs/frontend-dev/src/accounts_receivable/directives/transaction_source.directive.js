(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('transactionSource', transactionSource);

    function transactionSource() {
        return {
            restrict: 'E',
            template:
                '<span ng-if="source&&source.object==\'card\'">{{formatCard(source)}}</span>' +
                '<span ng-if="source&&source.object==\'bank_account\'">{{formatBankAccount(source.bank_name, source.last4)}}</span>' +
                '<span ng-if="!source"><em>None</em></span>',
            scope: {
                source: '=',
                short: '=?',
            },
            controller: [
                '$scope',
                'PaymentDisplayHelper',
                function ($scope, PaymentDisplayHelper) {
                    $scope.formatBankAccount = PaymentDisplayHelper.formatBankAccount;
                    $scope.formatCard = function (card) {
                        if ($scope.short) {
                            return PaymentDisplayHelper.formatCard(card.brand, card.last4);
                        }

                        return PaymentDisplayHelper.formatCard(card.brand, card.last4, card.exp_month, card.exp_year);
                    };
                },
            ],
        };
    }
})();
