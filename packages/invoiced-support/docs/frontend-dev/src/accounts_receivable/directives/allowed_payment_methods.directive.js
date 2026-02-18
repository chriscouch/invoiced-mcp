(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('allowedPaymentMethods', allowedPaymentMethods);

    allowedPaymentMethods.$inject = ['Core'];

    function allowedPaymentMethods(Core) {
        return {
            restrict: 'E',
            template:
                '<div class="checkbox" ng-repeat="method in paymentMethods" ng-show="method.enabled">' +
                '<label class="noselect">' +
                '<input type="checkbox" ng-model="disabledMethods[method.id]" ng-true-value="false" ng-false-value="true" /> ' +
                '{{method.id|paymentMethodName}}' +
                '</label>' +
                '</div>' +
                '<div class="loading inline" ng-show="loading"></div>' +
                '<div class="no-payment-methods" ng-hide="hasMethods||loading">No payment methods are enabled</div>',
            scope: {
                disabledMethods: '=ngModel',
            },
            controller: [
                '$scope',
                'PaymentMethod',
                function ($scope, PaymentMethod) {
                    $scope.paymentMethods = [];

                    $scope.$watch('disabledMethods', function (disabledMethods) {
                        fill(disabledMethods, $scope.paymentMethods);
                    });

                    load();

                    function load() {
                        $scope.loading = true;
                        PaymentMethod.findAll(
                            { paginate: 'none' },
                            function (paymentMethods) {
                                $scope.loading = false;
                                fill($scope.disabledMethods, paymentMethods);
                                $scope.paymentMethods = paymentMethods;

                                $scope.hasMethods = false;
                                angular.forEach(paymentMethods, function (method) {
                                    if (method.enabled) {
                                        $scope.hasMethods = true;
                                    }
                                });
                            },
                            function (result) {
                                $scope.loading = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }

                    function fill(disabledMethods, paymentMethods) {
                        if (typeof disabledMethods !== 'object') {
                            return;
                        }

                        angular.forEach(paymentMethods, function (method) {
                            if (typeof disabledMethods[method.id] === 'undefined') {
                                disabledMethods[method.id] = false;
                            }
                        });
                    }
                },
            ],
        };
    }
})();
