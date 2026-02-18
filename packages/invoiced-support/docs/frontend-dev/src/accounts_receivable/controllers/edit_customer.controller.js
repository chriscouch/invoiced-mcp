(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditCustomerController', EditCustomerController);

    EditCustomerController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        '$translate',
        'Customer',
        'Settings',
        'Member',
        'CurrentUser',
        'CustomField',
        'MerchantAccount',
        'MetadataCaster',
        'selectedCompany',
        'Core',
        'InvoicedConfig',
        'model',
        'Feature',
        'LateFeeSchedule',
    ];

    function EditCustomerController(
        $scope,
        $modal,
        $modalInstance,
        $translate,
        Customer,
        Settings,
        Member,
        CurrentUser,
        CustomField,
        MerchantAccount,
        MetadataCaster,
        selectedCompany,
        Core,
        InvoicedConfig,
        model,
        Feature,
        LateFeeSchedule,
    ) {
        $scope.company = selectedCompany;

        // we're creating a new customer when
        // false or an object without an ID is passed in
        if (model === false || !model.id) {
            $scope.customer = {
                type: 'company',
                country: $scope.company.country,
                currency: $scope.company.currency,
                payment_terms: '',
                autopay_delay_days: -1,
                convenience_fee: true,
                taxable: true,
                taxes: [],
                chase: true,
                language: '',
                owner: CurrentUser.profile.id,
                credit_hold: false,
                credit_limit: null,
                late_fee_schedule: null,
                metadata: {},
            };

            $scope.hasLateFees = false;
            $scope.hasParent = false;
            $scope.hasCreditLimit = false;
            $scope.excludeCustomerIds = [];

            loadSettings();

            // set the default values using the object passed in
            if (typeof model === 'object') {
                angular.extend($scope.customer, model);
            }
        } else {
            // otherwise, we're editing an existing customer
            $scope.customer = angular.copy(model);
            if (!$scope.customer.language) {
                $scope.customer.language = ''; // used for ng-select to recognize code
            }
            if (!$scope.customer.metadata) {
                $scope.customer.metadata = false;
            }
            if (model.owner && typeof model.owner === 'object') {
                $scope.customer.owner = model.owner.id;
            }
            if (model.late_fee_schedule && typeof model.late_fee_schedule === 'object') {
                $scope.customer.late_fee_schedule = model.late_fee_schedule.id;
                $scope.hasLateFees = true;
            }

            $scope.hasParent = !!$scope.customer.parent_customer;
            $scope.hasCreditLimit = !!$scope.customer.credit_limit;
            $scope.excludeCustomerIds = [$scope.customer.id];
        }

        loadCustomFields();
        loadDisabledPaymentMethods();
        loadMerchantAccounts();
        loadLateFeeSchedules();

        $scope.rateListOptions = {
            types: false,
        };

        $scope.tab = 'basic';
        $scope.hasAutoPay = Feature.hasFeature('autopay');
        $scope.hasInternationalization = Feature.hasFeature('internationalization');
        $scope.autoPay = $scope.customer.autopay;
        $scope.card = {};
        $scope.disabledPaymentMethods = {};

        $scope.autoPayOptions = [{ amount: -1, name: 'Default' }];
        for (let i = 0; i <= 45; i++) {
            $scope.autoPayOptions.push({
                amount: i,
                name: i === 1 ? '+1 day' : '+' + i + ' days',
            });
        }

        $scope.avalaraEntityCodes = InvoicedConfig.avalaraEntityCodes;

        $scope.changeType = function (model) {
            $scope.changeCountry(model.country);
        };

        $scope.changeCountry = function (country) {
            $scope.hasTaxId = Core.countryHasBuyerTaxId(country);

            let locale = 'en_' + country;
            $scope.cityLabel = $translate.instant('address.city', {}, null, locale);
            $scope.stateLabel = $translate.instant('address.state', {}, null, locale);
            $scope.postalCodeLabel = $translate.instant('address.postal_code', {}, null, locale);
        };

        $scope.selectRatesModal = function (rates, type) {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-rate.html',
                controller: 'AddRateController',
                resolve: {
                    currency: function () {
                        return $scope.company.currency;
                    },
                    ignore: function () {
                        return rates;
                    },
                    type: function () {
                        return type;
                    },
                    options: function () {
                        return {};
                    },
                },
                windowClass: 'add-rate-modal',
            });

            modalInstance.result.then(
                function (rate) {
                    rates.push(rate);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.addPaymentSource = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/add-payment-source.html',
                controller: 'AddPaymentSourceController',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    options: function () {
                        return {
                            returnToken: true,
                        };
                    },
                },
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (token) {
                    $scope.paymentSourceToken = token;
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deletePaymentSource = function () {
            $scope.paymentSourceToken = null;
        };

        $scope.save = function (_model, disabledPaymentMethods) {
            $scope.saving = true;

            let customer = angular.copy(_model);

            if (customer.type === 'person') {
                delete customer.attention_to;
            }

            angular.forEach(
                [
                    'balance',
                    'created_at',
                    'updated_at',
                    'statement_pdf_url',
                    'payment_source',
                    'sign_up_page',
                    'sign_up_url',
                    'object',
                    'chasing_cadence',
                    'next_chase_step',
                    'ach_gateway_id',
                    'cc_gateway_id',
                    'late_fee_schedule_id',
                    'address',
                    'network_connection_id',
                    'subscribed',
                    'aging',
                ],
                function (key) {
                    delete customer[key];
                },
            );

            // clean up email address
            // this will remove the Unicode zero-space word joiner that can be present
            if (customer.email) {
                customer.email = customer.email.replace(/\u2060/g, '');
            }

            // set tax rates and avalara fields depending on taxable status
            customer.taxes = [];
            if (customer.taxable) {
                customer.avalara_entity_use_code = null;
                customer.avalara_exemption_number = null;
                angular.forEach(_model.taxes, function (rate) {
                    customer.taxes.push(rate.id);
                });
            }

            // parse late fee schedule
            let lateFeeSchedule = customer.late_fee_schedule;
            if ($scope.hasLateFees && lateFeeSchedule) {
                if (typeof lateFeeSchedule === 'object') {
                    customer.late_fee_schedule = lateFeeSchedule.id;
                } else {
                    angular.forEach($scope.lateFeeSchedules, function (schedule) {
                        if (schedule.id === customer.late_fee_schedule) {
                            lateFeeSchedule = schedule;
                        }
                    });
                }
            } else {
                customer.late_fee_schedule = null;
                lateFeeSchedule = null;
            }

            // parse parent customer
            let parentCustomer = customer.parent_customer;
            if ($scope.hasParent && parentCustomer) {
                if (typeof parentCustomer === 'object') {
                    customer.parent_customer = parentCustomer.id;
                }
            } else {
                customer.parent_customer = null;
                parentCustomer = null;
            }

            // parse owner
            let owner = customer.owner;
            if (owner && typeof owner === 'object') {
                customer.owner = owner.id;
            }

            // parse autopay
            customer.autopay = $scope.autoPay;
            if ($scope.autoPay) {
                delete customer.payment_terms;

                if ($scope.paymentSourceToken) {
                    customer.payment_source = {
                        method: $scope.paymentSourceToken.type,
                    };
                    if ($scope.paymentSourceToken.gateway === 'stripe') {
                        customer.payment_source.gateway_token = $scope.paymentSourceToken.id;
                    } else {
                        customer.payment_source.invoiced_token = $scope.paymentSourceToken.id;
                    }
                }
            }

            // parse credit limit
            if (!$scope.hasCreditLimit) {
                customer.credit_limit = null;
            }

            // parse metadata
            MetadataCaster.marshalForInvoiced('customer', customer.metadata, function (metadata) {
                if (metadata !== false) {
                    customer.metadata = metadata;
                } else {
                    delete customer.metadata;
                }
                saveCustomer(customer, lateFeeSchedule, owner, parentCustomer, disabledPaymentMethods);
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.changeType($scope.customer);

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function loadSettings() {
            Settings.accountsReceivable(function (settings) {
                $scope.customer.type = settings.default_customer_type;
                $scope.customer.payment_terms = settings.payment_terms;
                $scope.autoPay = settings.default_collection_mode === 'auto';
            });
        }

        function loadCustomFields() {
            CustomField.all(
                function (customFields) {
                    $scope.customFields = [];
                    angular.forEach(customFields, function (customField) {
                        if (customField.object === 'customer') {
                            $scope.customFields.push(customField);
                        }
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadDisabledPaymentMethods() {
            if (!$scope.customer.id) {
                return;
            }

            Customer.getDisabledPaymentMethods(
                {
                    id: $scope.customer.id,
                },
                function (methods) {
                    angular.forEach(methods, function (disabled) {
                        $scope.disabledPaymentMethods[disabled.method] = true;
                    });
                },
            );
        }

        function loadMerchantAccounts() {
            $scope.loadingMerchantAccounts = true;
            MerchantAccount.findAll(
                {
                    'filter[deleted]': false,
                    paginate: 'none',
                },
                function (result) {
                    $scope.loadingMerchantAccounts = false;
                    $scope.achMerchantAccounts = filterMerchantAccounts(result, 'ach');
                    $scope.creditCardMerchantAccounts = filterMerchantAccounts(result, 'credit_card');
                },
                function (result) {
                    $scope.loadingMerchantAccounts = false;
                    $scope.error = result.data;
                },
            );
        }

        function filterMerchantAccounts(merchantAccounts, method) {
            let filtered = [];
            angular.forEach(merchantAccounts, function (merchantAccount) {
                if (InvoicedConfig.paymentGatewaysByMethod[method].indexOf(merchantAccount.gateway) !== -1) {
                    merchantAccount.list_name =
                        $translate.instant('payment_gateways.' + merchantAccount.gateway) + ': ' + merchantAccount.name;
                    filtered.push(merchantAccount);
                }
            });

            return filtered;
        }

        function loadLateFeeSchedules() {
            $scope.loadingLateFeeSchedules = true;
            LateFeeSchedule.findAll(
                { paginate: 'none' },
                function (result) {
                    $scope.loadingLateFeeSchedules = false;
                    $scope.lateFeeSchedules = result;
                    angular.forEach(result, function (schedule) {
                        if (schedule.default && !$scope.customer.id) {
                            $scope.customer.late_fee_schedule = schedule.id;
                            $scope.hasLateFees = true;
                        }
                    });
                },
                function (result) {
                    $scope.loadingLateFeeSchedules = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveCustomer(customer, lateFeeSchedule, owner, parentCustomer, disabledPaymentMethods) {
            customer.payment_terms = customer.payment_terms
                ? typeof customer.payment_terms === 'object'
                    ? customer.payment_terms.name
                    : customer.payment_terms
                : null;
            customer.disabled_payment_methods = [];
            angular.forEach(disabledPaymentMethods, function (disabled, method) {
                if (disabled) {
                    customer.disabled_payment_methods.push(method);
                }
            });

            if (typeof customer.id !== 'undefined') {
                $scope.error = null;
                let customerId = customer.id;
                delete customer.id;
                Customer.edit(
                    {
                        id: customerId,
                    },
                    customer,
                    function (_customer) {
                        $scope.saving = false;
                        _customer.late_fee_schedule = lateFeeSchedule;
                        _customer.parent_customer = parentCustomer;
                        _customer.owner = owner;
                        $modalInstance.close(_customer);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            } else {
                $scope.error = null;
                Customer.create(
                    customer,
                    function (_customer) {
                        $scope.saving = false;
                        _customer.parent_customer = parentCustomer;
                        _customer.owner = owner;
                        $modalInstance.close(_customer);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            }
        }
    }
})();
