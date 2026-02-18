/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditTransactionController', EditTransactionController);

    EditTransactionController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'selectedCompany',
        'Transaction',
        'CustomField',
        'Money',
        'Core',
        'transaction',
        'DatePickerService',
    ];

    function EditTransactionController(
        $scope,
        $modal,
        $modalInstance,
        selectedCompany,
        Transaction,
        CustomField,
        Money,
        Core,
        transaction,
        DatePickerService,
    ) {
        $scope.transaction = angular.copy(transaction);
        $scope.company = selectedCompany;

        $scope.dateOptions = DatePickerService.getOptions();

        // payment amount cannot exceed existing amount + remaining invoice balance
        let maxAmountCents = -1;
        if ($scope.transaction.invoice) {
            maxAmountCents = Money.normalizeToZeroDecimal(
                $scope.transaction.currency,
                $scope.transaction.amount + $scope.transaction.invoice.balance,
            );
        }
        $scope.maxAmount = Money.denormalizeFromZeroDecimal($scope.transaction.currency, maxAmountCents);

        $scope.title = 'Payment';
        if ($scope.transaction.type == 'adjustment') {
            $scope.title = 'Credit';
            // reversing the sign since this form is from the credit POV
            // normally the amount for a credit transaction is negative
            $scope.transaction.amount = $scope.transaction.amount * -1;

            if ($scope.transaction.amount < 0) {
                $scope.title = 'Adjustment';
            }
        } else if ($scope.transaction.type == 'refund') {
            $scope.title = 'Refund';
        }

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.changeAmount = function (amount) {
            let amountCents = Money.normalizeToZeroDecimal($scope.transaction.currency, amount);
            let invalid = maxAmountCents >= 0 && amountCents > maxAmountCents;
            $scope.invalidAmount = $scope.paymentForm.$invalid = invalid;
        };

        $scope.save = function (_transaction) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                notes: _transaction.notes,
                metadata: _transaction.metadata,
            };

            if (_transaction.type == 'payment') {
                params.method = _transaction.method;
                params.date = moment(_transaction.date).unix();
                params.amount = parseFloat(
                    parseFloat(Core.parseFormattedNumber(_transaction.amount)).formatMoney(
                        2,
                        $scope.company.decimal_separator,
                        '',
                    ),
                );
                params.gateway_id = _transaction.gateway_id;
            } else if (_transaction.type == 'adjustment') {
                params.amount = parseFloat(
                    parseFloat(Core.parseFormattedNumber(_transaction.amount)).formatMoney(
                        2,
                        $scope.company.decimal_separator,
                        '',
                    ),
                );
                // reversing the sign since this form is from the credit POV
                // normally the amount for a credit transaction is negative
                params.amount = params.amount * -1;
            }

            Transaction.edit(
                {
                    id: _transaction.id,
                },
                params,
                function (updatedTransaction) {
                    $scope.saving = false;
                    // remove relationships
                    delete updatedTransaction.customer;
                    delete updatedTransaction.invoice;
                    delete updatedTransaction.credit_note;
                    $modalInstance.close(updatedTransaction);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadCustomFields();

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    $scope.customFields = [];
                    angular.forEach(customFields, function (customField) {
                        if (customField.object === 'transaction') {
                            $scope.customFields.push(customField);
                        }
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
