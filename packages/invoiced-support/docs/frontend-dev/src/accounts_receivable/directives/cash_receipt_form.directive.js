/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('cashReceiptForm', cashReceiptForm);

    function cashReceiptForm() {
        return {
            restrict: 'E',
            templateUrl: 'accounts_receivable/views/payments/new-cash-receipt.html',
            scope: {
                options: '=',
                callback: '&?callback',
            },
            controller: [
                '$scope',
                '$rootScope',
                '$q',
                'Payment',
                'Core',
                'Feature',
                'DatePickerService',
                'CustomField',
                'ReceivePaymentForm',
                'selectedCompany',
                function (
                    $scope,
                    $rootScope,
                    $q,
                    Payment,
                    Core,
                    Feature,
                    DatePickerService,
                    CustomField,
                    ReceivePaymentForm,
                    selectedCompany,
                ) {
                    $scope.options = $scope.options || {};

                    $scope.payment = {
                        currency: $scope.options.currency || selectedCompany.currency,
                        customer: null,
                        date: new Date(),
                        method: 'other',
                        amount: $scope.options.amount,
                        reference: '',
                        notes: '',
                        metadata: {},
                    };

                    $scope.dateOptions = DatePickerService.getOptions();
                    $scope.openDatepicker = function ($event, name) {
                        $event.stopPropagation();
                        $scope[name] = true;
                    };

                    $scope.save = savePayment;

                    makeForm();
                    loadCustomFields();

                    $scope.$watch(
                        'payment',
                        function (current, old) {
                            if (current === old) {
                                return;
                            }

                            // check if selected customer has changed
                            if (!angular.equals(current.customer, old.customer)) {
                                selectedCustomer();
                            }

                            // when the currency has changed, set it on the apply form
                            if (current.currency !== old.currency) {
                                $scope.applyForm.setCurrency($scope.payment.currency);
                            }

                            // when the payment amount has changed, set it on the apply form
                            if (current.amount != old.amount) {
                                $scope.applyForm.setPaymentAmount($scope.payment.amount);
                            }
                        },
                        true,
                    );

                    $scope.$on('receivePaymentCustomer', function (event, customer) {
                        if (!$scope.payment.customer || customer.id !== $scope.payment.customer.id) {
                            $scope.payment.customer = customer;
                        }
                    });

                    function makeForm() {
                        $scope.applyForm = new ReceivePaymentForm(
                            $scope.payment.currency,
                            $scope.payment.amount,
                            Boolean($scope.options.preselected),
                        );

                        // apply preselected customer
                        if ($scope.options.customer) {
                            $scope.payment.customer = $scope.options.customer;
                            $scope.customerPreselected = true;
                            selectedCustomer();
                        }

                        // apply preselected credits
                        if ($scope.options.appliedCredits) {
                            angular.forEach($scope.options.appliedCredits, function (credit) {
                                $scope.applyForm.preselectCredit(credit);
                            });
                        }

                        // apply preselected documents
                        if ($scope.options.preselected) {
                            angular.forEach($scope.options.preselected, function (doc) {
                                $scope.applyForm.addDocument(doc, true);
                            });
                        }

                        // apply preselected amount
                        if ($scope.options.amount) {
                            $scope.applyForm.changedAmount();
                        }
                    }

                    function loadCustomFields() {
                        $q.all([CustomField.getByObject('payment')]).then(function (data) {
                            $scope.customFields = data[0];
                        });
                    }

                    function selectedCustomer() {
                        let customer = $scope.payment.customer;

                        // set payment currency from customer currency
                        if (
                            Feature.hasFeature('multi_currency') &&
                            customer.currency &&
                            !$scope.applyForm.itemPreselected
                        ) {
                            $scope.payment.currency = customer.currency;
                            $scope.applyForm.setCurrency($scope.payment.currency);
                        }

                        $scope.applyForm.reset();

                        $rootScope.$broadcast('receivePaymentCustomer', customer);

                        $scope.applyForm.loadData(customer.id);
                    }

                    function savePayment(payment, applyForm) {
                        $scope.saving = true;
                        $scope.error = null;

                        let appliedTo = applyForm.serializeAppliedTo();
                        let hasAppliedCredit = false;
                        for (let i in appliedTo) {
                            if (appliedTo[i].type === 'applied_credit') {
                                hasAppliedCredit = true;
                                break;
                            }
                        }

                        let date = moment(payment.date);
                        //INVD-3765 if the payment is applied to a credit, set the time to 6pm (past) or now (today)
                        if (hasAppliedCredit) {
                            if (date.isBefore(moment(), 'day')) {
                                date = date.hour(18).minute(0).second(0);
                            } else if (date.isSame(moment(), 'day')) {
                                date = moment();
                            }
                        }

                        let params = {
                            customer: parseInt(payment.customer.id),
                            date: date.unix(),
                            method: payment.method,
                            reference: payment.reference,
                            currency: payment.currency,
                            notes: payment.notes,
                            amount: payment.amount,
                            applied_to: appliedTo,
                        };

                        params.metadata = {};
                        angular.forEach(payment.metadata, function (val, key) {
                            if (val) {
                                params.metadata[key] = val;
                            }
                        });

                        Payment.create(
                            params,
                            function (result) {
                                $scope.saving = false;
                                if (typeof $scope.callback === 'function') {
                                    $scope.callback({
                                        payment: result,
                                    });
                                }
                            },
                            function (result) {
                                $scope.saving = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            ],
        };
    }
})();
