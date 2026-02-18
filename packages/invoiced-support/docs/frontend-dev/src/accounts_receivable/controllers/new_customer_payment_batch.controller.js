(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('NewCustomerPaymentBatchController', NewCustomerPaymentBatchController);

    NewCustomerPaymentBatchController.$inject = [
        '$scope',
        '$modal',
        '$state',
        '$q',
        '$translate',
        'CustomerPaymentBatch',
        'LeavePageWarning',
        'Core',
        'AchFileFormat',
        'selectedCompany',
    ];

    function NewCustomerPaymentBatchController(
        $scope,
        $modal,
        $state,
        $q,
        $translate,
        CustomerPaymentBatch,
        LeavePageWarning,
        Core,
        AchFileFormat,
        selectedCompany,
    ) {
        $scope.loading = 0;
        $scope.availablePaymentMethods = [
            {
                id: 'ach',
                name: $translate.instant('payment_method.ach'),
            },
        ];
        $scope.charges = [];
        $scope.payFromOptions = [];
        $scope.checked = false;
        $scope.total = 0;
        $scope.parameters = {
            name: 'Payment Batch',
            number: null,
            ach_file_format: null,
            payment_method: 'ach',
        };
        $scope.paymentTotal = 0;
        $scope.currency = 'usd';

        Core.setTitle('Send Payment');
        LeavePageWarning.watchForm($scope, 'paymentForm');

        $scope.selectAll = function (checked) {
            angular.forEach($scope.charges, function (charge) {
                charge.checked = checked;
            });
        };

        $scope.formValid = function () {
            const charges = $scope.charges.filter(function (charge) {
                return charge.checked;
            });

            if (!charges.length) {
                return false;
            }

            return true;
        };

        $scope.save = function (parameters, charges) {
            $scope.saving = true;

            const ids = charges
                .filter(function (charge) {
                    return charge.checked;
                })
                .map(function (charge) {
                    return {
                        id: charge.id,
                    };
                });

            const params = {
                name: parameters.name,
                number: parameters.number,
                charges: ids,
                ach_file_format: parameters.ach_file_format.id,
                payment_method: parameters.payment_method,
            };

            CustomerPaymentBatch.create(
                params,
                function (batchPayment) {
                    $scope.saving = false;
                    LeavePageWarning.unblock();
                    $state.go('manage.customer_payment_batches.summary', { id: batchPayment.id });
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.$watch(
            'charges',
            function () {
                $scope.paymentTotal = 0;
                $scope.currency = null;
                angular.forEach($scope.charges, function (charge) {
                    if (charge.checked) {
                        $scope.currency = $scope.currency || charge.currency;
                        $scope.paymentTotal += charge.amount;
                    }
                });
                $scope.currency = $scope.currency || selectedCompany.currency;
            },
            true,
        );

        load();

        function load() {
            $scope.loading++;
            CustomerPaymentBatch.chargesToProcess(
                { expand: 'customer' },
                function (charges) {
                    angular.forEach(charges, function (charge) {
                        charge.checked = false;
                    });
                    $scope.charges = charges;

                    --$scope.loading;
                },
                function (result) {
                    --$scope.loading;
                    Core.showMessage(result.data.message, 'error');
                },
            );

            loadAchFileFormats();
        }

        function loadAchFileFormats() {
            $scope.payFromOptions = [];
            $scope.loading++;
            AchFileFormat.findAll(
                function (accounts) {
                    angular.forEach(accounts, function (account) {
                        account.type = 'bank_account';
                    });
                    $scope.payFromOptions = $scope.payFromOptions.concat(accounts);
                    --$scope.loading;
                },
                function (result) {
                    --$scope.loading;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
