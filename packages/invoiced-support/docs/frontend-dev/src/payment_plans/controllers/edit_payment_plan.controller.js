/* globals moment */
(function () {
    'use strict';

    angular.module('app.payment_plans').controller('EditPaymentPlanController', EditPaymentPlanController);

    EditPaymentPlanController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'Invoice',
        'Money',
        'invoice',
        'paymentPlan',
        'DatePickerService',
    ];

    function EditPaymentPlanController(
        $scope,
        $modal,
        $modalInstance,
        Invoice,
        Money,
        invoice,
        paymentPlan,
        DatePickerService,
    ) {
        $scope.invoice = invoice;
        $scope.dpOpened = {};

        $scope.paymentPlan = {
            autopay: invoice.autopay,
            // Require approval if payment plan already requires approval
            // or if AutoPay is not enabled yet and no payment method on file.
            // The latter covers the case where AutoPay goes from off -> on.
            require_approval:
                paymentPlan.status === 'pending_signup' ||
                (!invoice.autopay && invoice.customer.payment_source === null),
            installments: [],
        };

        angular.forEach(paymentPlan.installments, function (installment) {
            if (installment.balance > 0) {
                installment = angular.copy(installment);
                installment.date = moment.unix(installment.date).toDate();
                $scope.paymentPlan.installments.push(installment);
            }
        });

        $scope.total = 0;
        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope.dpOpened[name] = true;
        };

        $scope.addInstallment = function (installments) {
            installments.push({
                date: new Date(),
                amount: 0,
            });
        };

        $scope.deleteInstallment = function (installments, i) {
            installments.splice(i, 1);
        };

        $scope.$watch(
            'paymentPlan',
            function (updated) {
                let total = 0;
                angular.forEach(updated.installments, function (installment) {
                    total += parseFloat(installment.amount);
                });
                $scope.total = Money.round(invoice.currency, total);
                $scope.remaining = Money.round(invoice.currency, invoice.balance - total);
            },
            true,
        );

        $scope.save = function (invoice, _paymentPlan) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                autopay: _paymentPlan.autopay,
                installments: [],
            };

            if (params.autopay) {
                params.require_approval = _paymentPlan.require_approval;
            }

            // include the original installments, although these won't be modified
            angular.forEach(paymentPlan.installments, function (installment) {
                if (installment.balance === 0) {
                    params.installments.push({
                        date: installment.date,
                        amount: installment.amount,
                        balance: installment.balance,
                    });
                }
            });

            angular.forEach(_paymentPlan.installments, function (installment) {
                params.installments.push({
                    // The time of day on due dates should be 6pm local time zone
                    date: moment(installment.date).hour(18).minute(0).second(0).unix(),
                    amount: installment.amount,
                });
            });

            Invoice.setPaymentPlan(
                {
                    id: invoice.id,
                },
                params,
                function (_paymentPlan) {
                    $scope.saving = false;

                    $modalInstance.close(_paymentPlan);
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
    }
})();
