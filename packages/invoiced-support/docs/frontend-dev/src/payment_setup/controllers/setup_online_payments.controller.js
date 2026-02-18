(function () {
    'use strict';

    angular.module('app.payment_setup').controller('SetupOnlinePaymentsController', SetupOnlinePaymentsController);

    SetupOnlinePaymentsController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$window',
        '$translate',
        'selectedCompany',
        'PaymentMethod',
        'Integration',
        'MerchantAccount',
        'InvoicedConfig',
        'method',
        'Feature',
        'FlywirePayments',
    ];

    function SetupOnlinePaymentsController(
        $scope,
        $modalInstance,
        $modal,
        $window,
        $translate,
        selectedCompany,
        PaymentMethod,
        Integration,
        MerchantAccount,
        InvoicedConfig,
        method,
        Feature,
        FlywirePayments,
    ) {
        let featureFlag = 'card_payments';
        if (method.id === 'ach') {
            featureFlag = 'ach';
        } else if (method.id === 'direct_debit') {
            featureFlag = 'direct_debit';
        }
        let hasSubscription =
            typeof selectedCompany.billing !== 'undefined' && selectedCompany.billing.status !== 'not_subscribed';
        $scope.hasFeature = !hasSubscription || Feature.hasFeature(featureFlag);
        $scope.method = angular.copy(method);
        $scope.company = angular.copy(selectedCompany);
        $scope.method.convenience_fee = method.convenience_fee / 100;
        $scope.eligibility = { eligible: false };
        $scope.recommendedProcessor = null;
        $scope.hasTestMode = method.id === 'credit_card' || method.id === 'ach';

        // determine where the user is at
        if ($scope.method.gateway && $scope.method.enabled) {
            $scope.showProcessingOptions = false;
            $scope.canGoBack = false;
        } else {
            $scope.showProcessingOptions = true;
            $scope.canGoBack = true;
        }

        $scope.merchantAccounts = [];
        $scope.orderOptions = [
            { value: 0, name: 'Default Order' },
            { value: 40, name: '1st' },
            { value: 30, name: '2nd' },
            { value: 20, name: '3rd' },
            { value: 10, name: '4th' },
        ];

        $scope.selectGateway = function () {
            $scope.method.gateway = null;

            // Jump to add payment gateway screen if no existing configurations
            if ($scope.merchantAccounts.length !== 0) {
                $scope.showProcessingOptions = false;
                $scope.method.merchant_account = $scope.merchantAccounts[0];
            }
        };

        $scope.useTestGateway = function () {
            $scope.showProcessingOptions = false;
            $scope.method.gateway = 'test';
            $scope.method.merchant_account = null;
        };

        $scope.enable = enablePaymentMethod;
        $scope.disable = disablePaymentMethod;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadEligibility();
        loadMerchantAccounts();

        function loadMerchantAccounts() {
            $scope.loadingMerchantAccounts = true;
            MerchantAccount.findAll(
                {
                    'filter[deleted]': false,
                    paginate: 'none',
                },
                function (result) {
                    $scope.loadingMerchantAccounts = false;
                    $scope.merchantAccounts = filterMerchantAccounts(result);

                    // Find the matching merchant account
                    if ($scope.method.merchant_account) {
                        angular.forEach($scope.merchantAccounts, function (merchantAccount) {
                            if (merchantAccount.id == $scope.method.merchant_account.id) {
                                $scope.method.merchant_account = merchantAccount;
                            }
                        });
                    }
                },
                function (result) {
                    $scope.loadingMerchantAccounts = false;
                    $scope.error = result.data;
                },
            );
        }

        function filterMerchantAccounts(merchantAccounts) {
            // restrict the list of choices to gateways that support the payment method
            let filtered = [];
            angular.forEach(merchantAccounts, function (merchantAccount) {
                if (InvoicedConfig.paymentGatewaysByMethod[$scope.method.id].indexOf(merchantAccount.gateway) !== -1) {
                    merchantAccount.list_name =
                        $translate.instant('payment_gateways.' + merchantAccount.gateway) + ': ' + merchantAccount.name;
                    filtered.push(merchantAccount);
                }
            });

            return filtered;
        }

        function enablePaymentMethod() {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                enabled: true,
                gateway: $scope.method.gateway,
                merchant_account: null,
                min: $scope.method.min,
                max: $scope.method.max,
                order: $scope.method.order,
                convenience_fee: $scope.method.convenience_fee * 100,
            };

            if ($scope.method.merchant_account) {
                params.gateway = $scope.method.merchant_account.gateway;
                params.merchant_account = $scope.method.merchant_account.id;
            }

            PaymentMethod.edit(
                {
                    id: $scope.method.id,
                },
                params,
                function (returned) {
                    $scope.saving = false;
                    returned.merchant_account = $scope.method.merchant_account;
                    $modalInstance.close(returned);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function disablePaymentMethod() {
            $scope.saving = true;
            $scope.error = null;

            PaymentMethod.edit(
                {
                    id: $scope.method.id,
                },
                {
                    enabled: false,
                    gateway: null,
                    merchant_account: null,
                },
                function (returned) {
                    $scope.saving = false;
                    $modalInstance.close(returned);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function loadEligibility() {
            $scope.loadingEligibility = true;
            FlywirePayments.eligibility(
                function (result) {
                    $scope.eligibility = result;
                    $scope.loadingEligibility = false;
                    if (method.id === 'credit_card' || method.id === 'ach') {
                        $scope.recommendedProcessor = result.eligible ? 'flywire_payments' : 'stripe';
                    } else if (
                        method.id === 'bank_transfer' ||
                        method.id === 'online' ||
                        method.id === 'direct_debit'
                    ) {
                        $scope.recommendedProcessor = 'flywire';
                    }
                },
                function () {
                    // do nothing on error
                    $scope.loadingEligibility = false;
                },
            );
        }
    }
})();
