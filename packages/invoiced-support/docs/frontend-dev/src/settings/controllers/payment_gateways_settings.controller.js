/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('PaymentGatewaysSettingsController', PaymentGatewaysSettingsController);

    PaymentGatewaysSettingsController.$inject = [
        '$scope',
        '$modal',
        'selectedCompany',
        'Core',
        'MerchantAccount',
        'PaymentMethod',
        'LeavePageWarning',
    ];

    function PaymentGatewaysSettingsController(
        $scope,
        $modal,
        selectedCompany,
        Core,
        MerchantAccount,
        PaymentMethod,
        LeavePageWarning,
    ) {
        $scope.company = angular.copy(selectedCompany);

        $scope.edit = editMerchantAccount;
        $scope.delete = deleteMerchantAccount;

        Core.setTitle('Payment Gateways');
        loadMerchantAccounts();

        function loadMerchantAccounts() {
            $scope.loading = true;
            PaymentMethod.findAll(
                { paginate: 'none' },
                function (paymentMethods) {
                    MerchantAccount.findAll(
                        {
                            include: 'credentials',
                            'filter[deleted]': false,
                            paginate: 'none',
                        },
                        function (result) {
                            $scope.loading = false;
                            $scope.merchantAccounts = result;
                            angular.forEach($scope.merchantAccounts, function (merchantAccount) {
                                merchantAccount.usedByPaymentMethods = [];
                                angular.forEach(paymentMethods, function (paymentMethod) {
                                    if (paymentMethod.merchant_account === merchantAccount.id) {
                                        merchantAccount.usedByPaymentMethods.push(paymentMethod);
                                    }
                                });
                            });
                        },
                        function (result) {
                            $scope.loading = false;
                            Core.showMessage(result.data.message, 'danger');
                        },
                    );
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'danger');
                },
            );
        }

        function editMerchantAccount(merchantAccount) {
            LeavePageWarning.block();
            const modalInstance = $modal.open({
                templateUrl: 'payment_setup/views/edit-merchant-account.html',
                controller: 'EditMerchantAccountController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    gateway: function () {
                        return merchantAccount.gateway;
                    },
                    existingAccount: function () {
                        return merchantAccount;
                    },
                },
            });

            modalInstance.result.then(
                function (_merchantAccount) {
                    angular.extend(merchantAccount, _merchantAccount);
                    LeavePageWarning.unblock();
                },
                function () {
                    LeavePageWarning.unblock();
                },
            );
        }

        function deleteMerchantAccount(merchantAccount) {
            vex.dialog.confirm({
                message: 'Are you sure you want to remove this payment gateway configuration?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting = true;
                        MerchantAccount.delete(
                            {
                                id: merchantAccount.id,
                            },
                            function () {
                                $scope.deleting = false;
                                // remove the configuration locally
                                for (let i in $scope.merchantAccounts) {
                                    if ($scope.merchantAccounts[i].id === merchantAccount.id) {
                                        $scope.merchantAccounts.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deleting = false;
                                Core.showMessage(result.data.message, 'danger');
                            },
                        );
                    }
                },
            });
        }
    }
})();
