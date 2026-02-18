(function () {
    'use strict';

    angular.module('app.settings').controller('PaymentMethodsSettingsController', PaymentMethodsSettingsController);

    PaymentMethodsSettingsController.$inject = [
        '$scope',
        '$translate',
        '$modal',
        'selectedCompany',
        'PaymentMethod',
        'InvoicedConfig',
        'LeavePageWarning',
        'Core',
        'FlywirePayments',
    ];

    function PaymentMethodsSettingsController(
        $scope,
        $translate,
        $modal,
        selectedCompany,
        PaymentMethod,
        InvoicedConfig,
        LeavePageWarning,
        Core,
        FlywirePayments,
    ) {
        $scope.methods = {};
        $scope.offlineMethods = '';
        $scope.loading = 0;

        $scope.achAllowed = selectedCompany.country === 'US';
        $scope.deprecatedPaymentGateways = [];

        const possibleMethods = [
            'ach',
            'bank_transfer',
            'cash',
            'check',
            'credit_card',
            'direct_debit',
            'online',
            'other',
            'paypal',
            'wire_transfer',
        ];

        loadPaymentMethods();

        $scope.hasOffline = function () {
            if (Object.keys($scope.methods).length === 0) {
                return false;
            }

            // this is kinda lazy putting this here
            // could compute this only when the offline methods
            // are loaded or change
            let methods = [];

            if ($scope.methods.wire_transfer.enabled) {
                methods.push('Wire Transfer');
            }

            if ($scope.methods.check.enabled) {
                methods.push($translate.instant('payment_method.check'));
            }

            if ($scope.methods.cash.enabled) {
                methods.push('Cash');
            }

            if ($scope.methods.other.enabled) {
                methods.push('Other');
            }

            $scope.offlineMethods = methods.join(', ');

            return (
                $scope.methods.wire_transfer.enabled ||
                $scope.methods.check.enabled ||
                $scope.methods.cash.enabled ||
                $scope.methods.other.enabled
            );
        };

        $scope.setupOnline = function (methodId) {
            const modalInstance = $modal.open({
                templateUrl: 'payment_setup/views/accept-online-payments.html',
                controller: 'SetupOnlinePaymentsController',
                windowClass: 'accept-online-payments-modal',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    method: function () {
                        return $scope.methods[methodId];
                    },
                },
            });

            modalInstance.result.then(function () {
                loadPaymentMethods();
            });
        };

        $scope.setupPayPal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'payment_setup/views/accept-paypal.html',
                controller: 'SetupPayPalPaymentsController',
                windowClass: 'accept-online-payments-modal',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    method: function () {
                        return $scope.methods.paypal;
                    },
                },
            });

            modalInstance.result.then(function () {
                loadPaymentMethods();
            });
        };

        $scope.setupOfflinePayments = function () {
            LeavePageWarning.block();
            const modalInstance = $modal.open({
                templateUrl: 'payment_setup/views/accept-offline-payments.html',
                controller: 'SetupOfflinePaymentsController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    methods: function () {
                        return $scope.methods;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();
                    loadPaymentMethods();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        Core.setTitle('Payment Methods');

        function loadPaymentMethods() {
            $scope.loading++;

            PaymentMethod.findAll(
                {
                    expand: 'merchant_account',
                    paginate: 'none',
                },
                function (paymentMethods) {
                    let paymentMethodsByType = {};
                    const blankMethod = {
                        enabled: false,
                        gateway: null,
                        meta: null,
                        isFlywire: false,
                    };

                    angular.forEach(possibleMethods, function (method) {
                        paymentMethodsByType[method] = angular.copy(blankMethod);
                        paymentMethodsByType[method].id = method;
                    });

                    angular.forEach(paymentMethods, function (paymentMethod) {
                        if (typeof paymentMethodsByType[paymentMethod.id] === 'undefined') {
                            paymentMethodsByType[paymentMethod.id] = angular.copy(blankMethod);
                            paymentMethodsByType[paymentMethod.id].id = paymentMethod.id;
                        }

                        paymentMethod.isFlywire =
                            paymentMethod.gateway === 'flywire' || paymentMethod.gateway === 'flywire_payments';
                        angular.extend(paymentMethodsByType[paymentMethod.id], paymentMethod);
                    });

                    $scope.methods = paymentMethodsByType;
                    FlywirePayments.eligibility({}, determineDeprecatedGateways);

                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function determineDeprecatedGateways(eligibility) {
            if (!eligibility.deprecated || InvoicedConfig.environment === 'sandbox') {
                return;
            }

            const deprecated = [
                {
                    id: 'nmi',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'payflowpro',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'cardknox',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'lawpay',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'braintree',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'intuit',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'authorizenet',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'cybersource',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'moneris',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'orbital',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'stripe',
                    terminationDate: 'April 30, 2025',
                },
                {
                    id: 'vantiv',
                    terminationDate: 'April 30, 2025',
                },
            ];

            $scope.deprecatedPaymentGateways = [];
            angular.forEach(deprecated, gateway => {
                if (
                    $scope.methods.credit_card.gateway === gateway.id ||
                    $scope.methods.ach.gateway === gateway.id ||
                    $scope.methods.direct_debit.gateway === gateway.id
                ) {
                    $scope.deprecatedPaymentGateways.push(gateway);
                }
            });
        }
    }
})();
