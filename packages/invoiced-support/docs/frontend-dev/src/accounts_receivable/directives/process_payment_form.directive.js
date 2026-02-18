(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('processPaymentForm', processPaymentForm);

    function processPaymentForm() {
        return {
            restrict: 'E',
            templateUrl: 'accounts_receivable/views/payments/process-payment.html',
            scope: {
                options: '=',
                callback: '&?callback',
            },
            controller: [
                '$scope',
                '$rootScope',
                '$q',
                'selectedCompany',
                'Customer',
                'Core',
                'PaymentMethod',
                'Settings',
                'PaymentTokens',
                'Charge',
                'Feature',
                'DatePickerService',
                'CustomField',
                'ReceivePaymentForm',
                'PaymentDisplayHelper',
                function (
                    $scope,
                    $rootScope,
                    $q,
                    selectedCompany,
                    Customer,
                    Core,
                    PaymentMethod,
                    Settings,
                    PaymentTokens,
                    Charge,
                    Feature,
                    DatePickerService,
                    CustomField,
                    ReceivePaymentForm,
                    PaymentDisplayHelper,
                ) {
                    $scope.options = $scope.options || {};

                    $scope.payment = {
                        currency: $scope.options.currency || selectedCompany.currency,
                        customer: null,
                        date: new Date(),
                        method: 'other',
                        amount: $scope.options.amount,
                        gateway_id: '',
                        notes: '',
                        metadata: {},
                    };

                    $scope.payWith = 'credit_card';
                    $scope.cvc = '';
                    $scope.vaultMethod = false;
                    $scope.card = {};
                    $scope.bankAccount = {
                        country: 'US',
                        currency: 'usd',
                        account_holder_type: 'individual',
                        account_holder_name: null,
                        account_number: '',
                        routing_number: '',
                        type: 'checking',
                    };
                    $scope.convenienceFee = 0;
                    $scope.paymentMethods = [];
                    $scope.customer = null;

                    $scope.dateOptions = DatePickerService.getOptions();
                    $scope.openDatepicker = function ($event, name) {
                        $event.stopPropagation();
                        $scope[name] = true;
                    };

                    $scope.requiresCvc = function () {
                        if ($scope.payWith.indexOf('saved:') === 0) {
                            return false;
                        }

                        // process payment with a saved method
                        let source = $scope.payWith.split(':');
                        let sourceType = source[0];
                        if (sourceType !== 'card') {
                            return false;
                        }

                        return $scope.settings ? $scope.settings.saved_cards_require_cvc : false;
                    };

                    $scope.processPayment = processPayment;

                    makeForm();
                    loadCustomFields();
                    buildPaymentSources();
                    loadPaymentMethods();
                    loadSettings();

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

                            // check if the currency has changed
                            if (current.currency !== old.currency) {
                                $scope.applyForm.setCurrency($scope.payment.currency);
                            }

                            // check if the amount has changed
                            if (current.amount != old.amount) {
                                $scope.applyForm.setPaymentAmount($scope.payment.amount);
                            }
                        },
                        true,
                    );

                    $scope.$watchGroup(['payWith', 'paymentMethods'], calculateConvenienceFee, true);

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

                        loadPaymentSources(customer);
                        $scope.customer = null;
                        calculateConvenienceFee();

                        // prefill billing info, when available
                        $scope.card.name = customer.name;
                        $scope.card.address_line1 = customer.address1;
                        $scope.card.address_line2 = null;
                        $scope.card.address_city = customer.city;
                        $scope.card.address_state = customer.state;
                        $scope.card.address_zip = customer.postal_code;
                        $scope.card.address_country = customer.country;

                        $scope.bankAccount.account_holder_name = customer.name;

                        $scope.receiptEmail = customer.email;

                        $scope.applyForm.reset();

                        $rootScope.$broadcast('receivePaymentCustomer', customer);

                        $scope.applyForm.loadData(customer.id);
                    }

                    // Loads payment methods for this customer.
                    function loadPaymentSources(customer) {
                        $scope.loadingPaymentSources = true;

                        Customer.paymentSources(
                            {
                                id: customer.id,
                            },
                            function (paymentSources) {
                                $scope.loadingPaymentSources = false;
                                $scope.payWith = 'credit_card'; // reset the payment method selection

                                let newPaymentSources = [];
                                angular.forEach(paymentSources, function (paymentSource) {
                                    let description = PaymentDisplayHelper.format(paymentSource);
                                    if (!description) {
                                        description = 'Saved method';
                                    }

                                    let key = paymentSource.object + ':' + paymentSource.id;

                                    // select the default payment method as the default
                                    // payment option, when available
                                    if (isDefaultPaymentSource(customer, paymentSource)) {
                                        $scope.payWith = key;
                                    }

                                    newPaymentSources.push({
                                        key: key,
                                        description: description,
                                    });
                                });

                                // prepend list of new payment methods
                                buildPaymentSources(newPaymentSources);
                            },
                            function (result) {
                                $scope.loadingPaymentSources = false;
                                $scope.error = result.data;
                            },
                        );
                    }

                    // Loads the available payment methods
                    function loadPaymentMethods() {
                        $scope.loadingPaymentMethods = true;

                        PaymentMethod.findAll(
                            { paginate: 'none' },
                            function (paymentMethods) {
                                $scope.paymentMethods = paymentMethods;
                                $scope.loadingPaymentMethods = false;
                                $scope.acceptsCreditCards = false;
                                $scope.acceptsACH = false;
                                angular.forEach(paymentMethods, function (paymentMethod) {
                                    if (paymentMethod.id === 'ach' && paymentMethod.enabled) {
                                        $scope.acceptsACH = paymentMethod.gateway;
                                    } else if (paymentMethod.id === 'credit_card' && paymentMethod.enabled) {
                                        $scope.acceptsCreditCards = paymentMethod.gateway;
                                    }
                                });
                                if (!$scope.acceptsCreditCards && $scope.payWith === 'credit_card') {
                                    $scope.payWith = 'ach';
                                }
                            },
                            function (result) {
                                $scope.loading = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }

                    function loadSettings() {
                        Settings.accountsReceivable(function (settings) {
                            $scope.settings = settings;
                        });
                    }

                    function buildPaymentSources(paymentSources) {
                        $scope.paymentSources = paymentSources || [];
                        $scope.paymentSources = $scope.paymentSources.concat([
                            {
                                key: 'credit_card',
                                description: 'Card',
                            },
                            {
                                key: 'ach',
                                description: 'ACH',
                            },
                        ]);
                    }

                    function isDefaultPaymentSource(customer, paymentSource) {
                        return (
                            customer.payment_source &&
                            customer.payment_source.object === paymentSource.object &&
                            customer.payment_source.id == paymentSource.id
                        );
                    }

                    function calculateConvenienceFee() {
                        $scope.convenienceFee = 0;

                        if (!$scope.payment.customer || !$scope.payWith || !$scope.paymentMethods) {
                            return;
                        }

                        // Load full customer record to access the convenience fee setting
                        if (!$scope.customer) {
                            if (typeof $scope.payment.customer.convenience_fee !== 'undefined') {
                                $scope.customer = $scope.payment.customer;
                            } else {
                                Customer.find({ id: $scope.payment.customer.id }, function (customer) {
                                    $scope.customer = customer;
                                    calculateConvenienceFee();
                                });
                                return;
                            }
                        }

                        if (!$scope.customer.convenience_fee) {
                            return;
                        }

                        let card = $scope.paymentMethods.find(function (item) {
                            return item.id === 'credit_card';
                        });

                        if (card && ($scope.payWith === 'credit_card' || $scope.payWith.indexOf('card:') === 0)) {
                            $scope.convenienceFee = card.convenience_fee / 100;
                        }
                    }

                    function processPayment(payment, applyForm) {
                        $scope.saving = true;
                        $scope.error = null;

                        // build the charge request
                        let params = {
                            customer: payment.customer.id,
                            currency: payment.currency,
                            amount: payment.amount,
                            applied_to: applyForm.serializeAppliedTo(),
                            notes: payment.notes,
                        };

                        params.metadata = {};
                        angular.forEach(payment.metadata, function (val, key) {
                            if (val) {
                                params.metadata[key] = val;
                            }
                        });

                        if ($scope.receiptEmail) {
                            params.receipt_email = $scope.receiptEmail;
                        }

                        // tokenize payment information?
                        if ($scope.payWith === 'credit_card') {
                            params.method = 'credit_card';
                            let card = angular.copy($scope.card);

                            PaymentTokens.tokenizeCard(
                                card,
                                $scope.acceptsCreditCards,
                                function (result) {
                                    // success
                                    processPaymentWithToken(params, result);

                                    $scope.$apply();
                                },
                                function (message) {
                                    // error
                                    $scope.saving = false;
                                    $scope.error = {
                                        message: message,
                                    };

                                    $scope.$apply();
                                },
                            );
                        } else if ($scope.payWith === 'ach') {
                            params.method = 'ach';
                            let bankAccount = angular.copy($scope.bankAccount);

                            PaymentTokens.tokenizeBankAccount(
                                bankAccount,
                                $scope.acceptsACH,
                                function (result) {
                                    // success
                                    processPaymentWithToken(params, result);

                                    $scope.$apply();
                                },
                                function (message) {
                                    // error
                                    $scope.saving = false;
                                    $scope.error = {
                                        message: message,
                                    };

                                    $scope.$apply();
                                },
                            );
                        } else {
                            // process payment with a saved method
                            let source = $scope.payWith.split(':');
                            params.payment_source_type = source[0];
                            params.payment_source_id = source[1];

                            // pass along required cvc (optional)
                            if ($scope.cvc) {
                                params.cvc = $scope.cvc;
                            }

                            Charge.create(
                                params,
                                function (payment) {
                                    $scope.saving = false;
                                    if (typeof $scope.callback === 'function') {
                                        $scope.callback({
                                            payment: payment,
                                        });
                                    }
                                },
                                function (result) {
                                    $scope.saving = false;
                                    $scope.error = result.data;
                                },
                            );
                        }
                    }

                    function processPaymentWithToken(params, token) {
                        if (token.gateway) {
                            params.gateway_token = token.id;
                        } else {
                            params.invoiced_token = token.id;
                        }

                        // vault payment method?
                        if ($scope.vaultMethod) {
                            params.vault_method = true;
                        }

                        Charge.create(
                            params,
                            function (payment) {
                                $scope.saving = false;
                                if (typeof $scope.callback === 'function') {
                                    $scope.callback({
                                        payment: payment,
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
