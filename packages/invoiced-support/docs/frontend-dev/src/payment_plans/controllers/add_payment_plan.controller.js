/* globals moment */
(function () {
    'use strict';

    angular.module('app.payment_plans').controller('AddPaymentPlanController', AddPaymentPlanController);

    AddPaymentPlanController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'Invoice',
        'PaymentPlanCalculator',
        'Money',
        'invoice',
        'DatePickerService',
    ];

    function AddPaymentPlanController(
        $scope,
        $modal,
        $modalInstance,
        Invoice,
        PaymentPlanCalculator,
        Money,
        invoice,
        DatePickerService,
    ) {
        $scope.invoice = invoice;
        $scope.dpOpened = {};
        $scope.type = 'calculator';

        $scope.paymentPlan = {
            autopay: invoice.autopay,
            // Require approval if no payment method on file
            require_approval: invoice.customer.payment_source === null,
            installments: [],
        };

        $scope.constraints = {
            start_date: invoice.date,
            currency: invoice.currency,
            total: invoice.balance,
            interval: 'months',
        };

        $scope.total = 0;
        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope.dpOpened[name] = true;
        };

        $scope.isBackdatedPlan = function (type, start_date, installments) {
            let yesterday = new Date(new Date().setDate(new Date().getDate() - 1));
            yesterday.setHours(23);
            yesterday.setMinutes(59);
            yesterday.setSeconds(59);

            if (type === 'calculator') {
                return yesterday >= start_date;
            } else {
                return installments.some(function (installment) {
                    return yesterday >= installment.date;
                });
            }
        };

        $scope.addInstallment = function (paymentPlan) {
            paymentPlan.installments.push({
                date: new Date(),
                amount: 0,
            });
        };

        $scope.deleteInstallment = function (paymentPlan, i) {
            paymentPlan.installments.splice(i, 1);
        };

        $scope.calculate = calculate;

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

        $scope.save = function (invoice, paymentPlan) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                installments: [],
                autopay: paymentPlan.autopay,
            };

            if (params.autopay) {
                params.require_approval = paymentPlan.require_approval;
            }

            angular.forEach(paymentPlan.installments, function (installment) {
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

        $scope.addInstallment($scope.paymentPlan);

        function calculate(constraints) {
            constraints = angular.copy(constraints);

            // build installment spacing
            if (constraints.interval_count && constraints.interval) {
                constraints.installment_spacing = moment.duration(constraints.interval_count, constraints.interval);
                delete constraints.interval_count;
                delete constraints.interval;
            }

            // remove unselected constraints
            if (!$scope.withNumInstallments) {
                delete constraints.num_installments;
            }
            if (!$scope.withEndDate) {
                delete constraints.end_date;
            }
            if (!$scope.withSpacing) {
                delete constraints.installment_spacing;
            }
            if (!$scope.withAmount) {
                delete constraints.installment_amount;
            }

            // now let's build it!
            $scope.calculatorError = false;
            try {
                let schedule = PaymentPlanCalculator.build(constraints);
                PaymentPlanCalculator.verify(schedule, constraints);
                $scope.paymentPlan.installments = schedule;
                $scope.calculated = true;
            } catch (e) {
                $scope.calculatorError = e;
            }
        }
    }
})();
