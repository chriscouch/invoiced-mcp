/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('editableInvoice', editableInvoice);

    function editableInvoice() {
        let defaultInvoiceOptions = {
            type: 'invoice',
            hasCustomer: true,
            hasNumber: true,
            hasShipTo: false,
            hasDate: true,
            hasPaymentTerms: false,
            hasDueDate: false,
            hasExpiryDate: false,
            hasPaymentDate: false,
            hasDeposit: false,
            calculateDueDate: true,
            hasPurchaseOrder: false,
            hasAmountPaid: true,
            hasBalance: true,
            hasTotal: true,
            allowCustomRates: true,
            hasAttachments: false,
            hasExpiringDiscounts: false,
            inheritFromCustomer: true,
        };

        return {
            restrict: 'E',
            templateUrl: 'accounts_receivable/views/editable-invoice.html',
            scope: {
                invoice: '=',
                options: '=',
                company: '=',
            },
            controller: [
                '$scope',
                '$timeout',
                '$modal',
                'File',
                'InvoicedConfig',
                'InvoiceCalculator',
                'Core',
                'CustomField',
                'Feature',
                'Customer',
                'localStorageService',
                'DatePickerService',
                'Settings',
                function (
                    $scope,
                    $timeout,
                    $modal,
                    File,
                    InvoicedConfig,
                    InvoiceCalculator,
                    Core,
                    CustomField,
                    Feature,
                    Customer,
                    localStorageService,
                    DatePickerService,
                    Settings,
                ) {
                    //
                    // Initialization
                    //

                    $scope.showLineItemCustomFields = getDefaultLineItemCustomFieldVisibility();

                    let newItem = {
                        type: null,
                        quantity: 1,
                        name: '',
                        description: '',
                        unit_cost: '',
                        discountable: true,
                        discounts: [],
                        taxable: true,
                        taxes: [],
                        metadata: {},
                    };

                    let rateObjectKeys = {
                        discounts: 'coupon',
                        discount: 'coupon',
                        taxes: 'tax_rate',
                        tax: 'tax_rate',
                        shipping: 'shipping_rate',
                    };

                    $scope.sortableOptions = {
                        handle: '.sortable-handle',
                        placholder: 'sortable-placeholder',
                        items: '.item-row:not(.blank)',
                    };

                    $scope.more = {};

                    let maxLineItems = 100;
                    let loadingAutoPayDate = false;
                    let lastSetAutoPayDate = null;

                    let customRatesCalulated = false;
                    $scope.$watch(
                        'invoice',
                        function (current, old) {
                            if (!current || current === old) {
                                return;
                            }

                            if (
                                !current.id &&
                                current.customer &&
                                typeof current.customer === 'object' &&
                                (typeof old.customer !== typeof current.customer ||
                                    (old.customer &&
                                        typeof old.customer === 'object' &&
                                        current.customer.id != old.customer.id))
                            ) {
                                selectedCustomer();
                            }

                            // when an existing invoice is loaded for the
                            // first time, we want to populate the customRates
                            // array so they can be edited
                            if ((current._recalculate || current.id) && !customRatesCalulated) {
                                computeCustomRates();
                                customRatesCalulated = true;
                                delete current._recalculate;
                            }

                            changePaymentTerms();
                            calculate();

                            $scope.allowCustomerSelect =
                                $scope.options.hasCustomer &&
                                !(typeof $scope.invoice.id !== 'undefined' && $scope.invoice.id > 0);

                            $scope.showCountry =
                                $scope.invoice.customer &&
                                typeof $scope.invoice.customer === 'object' &&
                                $scope.invoice.customer.country &&
                                $scope.invoice.customer.country !== $scope.company.country;
                        },
                        true,
                    );

                    $scope.options = angular.extend(angular.copy(defaultInvoiceOptions), $scope.options);

                    $scope.allowCustomerSelect =
                        $scope.options.hasCustomer &&
                        !(typeof $scope.invoice.id !== 'undefined' && $scope.invoice.id > 0);

                    loadCustomFields();
                    changePaymentTerms();
                    dateFormat();
                    calculate();

                    if ($scope.allowCustomerSelect && typeof $scope.invoice.customer === 'object') {
                        selectedCustomer();
                    }

                    //
                    // Methods
                    //

                    $scope.openDatepicker = function ($event, name) {
                        $event.stopPropagation();
                        $scope[name] = true;
                        // this is needed to ensure the datepicker
                        // can be opened again
                        $timeout(function () {
                            $scope[name] = false;
                        });
                    };

                    $scope.addDeposit = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'accounts_receivable/views/estimates/add-deposit.html',
                            controller: 'AddDepositController',
                            resolve: {
                                document: function () {
                                    return $scope.invoice;
                                },
                            },
                            size: 'sm',
                        });

                        modalInstance.result.then(
                            function (amount) {
                                $scope.invoice.deposit = amount;
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.toggleLineDiscountable = function (item) {
                        item.discountable = !item.discountable;

                        if (!item.discountable) {
                            item.discounts = [];
                        }
                    };

                    $scope.toggleLineTaxable = function (item) {
                        item.taxable = !item.taxable;

                        if (!item.taxable) {
                            item.taxes = [];
                        }
                    };

                    $scope.duplicateLineItem = function (item) {
                        item = angular.copy(item);

                        if (typeof item.$$hashKey !== 'undefined') {
                            delete item.$$hashKey;
                        }

                        if (typeof item.id !== 'undefined') {
                            delete item.id;
                        }

                        if (typeof item.catalog_item !== 'undefined') {
                            delete item.catalog_item;
                        }

                        // generate temporary IDs for applied rates
                        angular.forEach(['discounts', 'taxes'], function (k) {
                            if (typeof item[k] !== 'undefined') {
                                angular.forEach(item[k], function (appliedRate) {
                                    appliedRate.id = generateID();
                                });
                            }
                        });

                        // insert before the last blank line
                        $scope.invoice.items.splice($scope.invoice.items.length - 1, 0, item);
                    };

                    $scope.deleteLineItem = function (item) {
                        if ($scope.invoice.items.length === 1) {
                            return;
                        }

                        // no need to ask about deleting a blank line
                        if (lineIsBlank(item)) {
                            deleteLine(item);
                            return;
                        }

                        vex.dialog.confirm({
                            message: 'Are you sure you want to delete this line item?',
                            callback: function (result) {
                                if (result) {
                                    $scope.$apply(function () {
                                        deleteLine(item);
                                    });
                                }
                            },
                        });
                    };

                    $scope.addItem = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'catalog/views/add-item.html',
                            controller: 'AddItemController',
                            resolve: {
                                currency: function () {
                                    return $scope.invoice.currency;
                                },
                                requireCurrency: function () {
                                    return false;
                                },
                                multiple: function () {
                                    return true;
                                },
                            },
                            backdrop: 'static',
                            keyboard: false,
                            windowClass: 'add-item-modal',
                        });

                        modalInstance.result.then(
                            function (items) {
                                // add each item as a new line item
                                angular.forEach(items, function (item) {
                                    addItem(item, 1);
                                });
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.addBundle = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'catalog/views/add-bundle.html',
                            controller: 'AddBundleController',
                            resolve: {
                                currency: function () {
                                    return $scope.invoice.currency;
                                },
                            },
                            windowClass: 'add-bundle-modal',
                        });

                        modalInstance.result.then(
                            function (bundle) {
                                // add each item as a new line item
                                angular.forEach(bundle.items, function (item) {
                                    addItem(item.catalog_item, item.quantity);
                                });
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.addRateModal = function (appliedRates, type, allowCustom) {
                        allowCustom = allowCustom || false;

                        const modalInstance = $modal.open({
                            templateUrl: 'catalog/views/add-rate.html',
                            controller: 'AddRateController',
                            resolve: {
                                currency: function () {
                                    return $scope.invoice.currency;
                                },
                                ignore: function () {
                                    // pull out just the rate objects
                                    let rates = [];
                                    let k = rateObjectKeys[type];
                                    angular.forEach(appliedRates, function (appliedRate) {
                                        if (appliedRate[k]) {
                                            rates.push(appliedRate[k]);
                                        }
                                    });

                                    return rates;
                                },
                                type: function () {
                                    return type;
                                },
                                options: function () {
                                    return {
                                        allowCustom: allowCustom && $scope.options.allowCustomRates,
                                    };
                                },
                            },
                            windowClass: 'add-rate-modal',
                        });

                        modalInstance.result.then(
                            function (rate) {
                                if (rate === 0) {
                                    addCustomRate(appliedRates, type);
                                } else {
                                    addRate(appliedRates, type, rate);
                                }
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.addShipping = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'accounts_receivable/views/add-shipping.html',
                            controller: 'AddShippingController',
                            backdrop: 'static',
                            keyboard: false,
                            resolve: {
                                shipTo: function () {
                                    return $scope.invoice.ship_to;
                                },
                                customer: function () {
                                    return $scope.invoice.customer;
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (shipTo) {
                                $scope.invoice.ship_to = shipTo;
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.addShippingAmount = function () {
                        addCustomRate($scope.invoice.shipping, 'shipping');
                    };

                    $scope.deleteRate = function (appliedRate, type) {
                        deleteRate($scope.invoice[type], type, appliedRate);
                    };

                    $scope.setDiscountExpiration = function (appliedRate) {
                        const modalInstance = $modal.open({
                            templateUrl: 'accounts_receivable/views/set-discount-expiration.html',
                            controller: 'SetDiscountExpirationController',
                            resolve: {
                                appliedRate: function () {
                                    return appliedRate;
                                },
                            },
                            size: 'sm',
                        });

                        modalInstance.result.then(
                            function (date) {
                                appliedRate.expires = date;
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.addedFiles = function (file) {
                        $scope.invoice.attachments.push({
                            file: file,
                        });
                    };

                    $scope.deleteAttachment = function (attachment) {
                        vex.dialog.confirm({
                            message: 'Are you sure you want to remove this attachment?',
                            callback: function (result) {
                                if (result) {
                                    $scope.$apply(function () {
                                        deleteAttachment(attachment);
                                    });
                                }
                            },
                        });
                    };

                    $scope.toggleLineItemCustomFieldVisibility = function () {
                        $scope.showLineItemCustomFields = !$scope.showLineItemCustomFields;
                        localStorageService.set('showLineItemCustomFields', $scope.showLineItemCustomFields);
                    };

                    function getDefaultLineItemCustomFieldVisibility() {
                        let storedValue = localStorageService.get('showLineItemCustomFields');
                        if (null != storedValue) {
                            return JSON.parse(storedValue);
                        }

                        return true;
                    }

                    function loadCustomFields() {
                        CustomField.all(
                            function (customFields) {
                                $scope.customFields = [];
                                $scope.lineItemCustomFields = [];
                                angular.forEach(customFields, function (customField) {
                                    if (customField.object === $scope.options.type) {
                                        $scope.customFields.push(customField);
                                    }

                                    if (customField.object === 'line_item') {
                                        $scope.lineItemCustomFields.push(customField);
                                    }
                                });

                                if ($scope.invoice.customer) {
                                    inheritCustomerMetadata($scope.invoice.customer);
                                }
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }

                    function changePaymentTerms() {
                        if ($scope.invoice.autopay) {
                            if (!$scope.invoice.next_payment_attempt) {
                                setAutoPayDate($scope.invoice.customer);
                            } else {
                                // AutoPay attempt cannot be before issue date
                                if ($scope.invoice.date !== lastSetAutoPayDate) {
                                    setAutoPayDate($scope.invoice.customer);
                                }
                            }
                        }

                        if (typeof $scope.invoice._payment_terms === 'object' && $scope.invoice._payment_terms) {
                            applyPaymentTerms($scope.invoice._payment_terms);
                        } else if ($scope.invoice._payment_terms === null) {
                            $scope.invoice.payment_terms = null;
                        }
                    }

                    function applyPaymentTerms(paymentTerms) {
                        $scope.invoice.payment_terms = paymentTerms.name;

                        // determine if the due date is mutable
                        if ($scope.options.hasDueDate) {
                            $scope.invoice.mutable_due_date = true;
                        }

                        // calculate the due date
                        if (paymentTerms.due_in_days > 0 && $scope.options.calculateDueDate) {
                            $scope.invoice.due_date = moment($scope.invoice.date)
                                .add(paymentTerms.due_in_days, 'days')
                                .toDate();
                            $scope.invoice.mutable_due_date = false;
                        }

                        // apply an early discount
                        let i;
                        if (paymentTerms.discount_expires_in_days > 0 && $scope.options.calculateDueDate) {
                            // check for an existing early discount
                            let found = false;
                            let notChanged = false;
                            for (i in $scope.invoice.discounts) {
                                let _discount = $scope.invoice.discounts[i];
                                if (_discount.from_payment_terms) {
                                    found = true;
                                    let expiresTimestamp = moment($scope.invoice.date)
                                        .add(paymentTerms.discount_expires_in_days, 'days')
                                        .unix();
                                    notChanged =
                                        moment(_discount.expires).unix() === expiresTimestamp &&
                                        _discount.coupon.value === paymentTerms.discount_value;
                                    break;
                                }
                            }

                            if (found && notChanged) {
                                return; // nothing else to do
                            } else if (found && !notChanged) {
                                $scope.invoice.discounts.splice(i, 1); // remove the previously calculated discount
                            }

                            // calculate and add the early discount
                            $scope.invoice.discounts.push({
                                id: generateID(),
                                coupon: {
                                    id: generateID(),
                                    name: 'Early Payment',
                                    value: paymentTerms.discount_value,
                                    is_percent: paymentTerms.discount_is_percent,
                                },
                                from_payment_terms: true,
                                expires: moment($scope.invoice.date)
                                    .add(paymentTerms.discount_expires_in_days, 'days')
                                    .toDate(),
                            });
                        } else {
                            // remove any previously calculated early discounts
                            for (i in $scope.invoice.discounts) {
                                if ($scope.invoice.discounts[i].from_payment_terms) {
                                    $scope.invoice.discounts.splice(i, 1);
                                }
                            }
                        }
                    }

                    function selectedCustomer() {
                        // This check only happens once. The intention is that the
                        // initial customer from duplicating an invoice does not
                        // override the invoice values. After this if the customer
                        // is changed then it should override the invoice values
                        // with the customer's settings.
                        if (!$scope.options.inheritFromCustomer) {
                            $scope.options.inheritFromCustomer = true;
                            return;
                        }

                        let customer = $scope.invoice.customer;

                        // Set invoice currency from customer currency
                        if (Feature.hasFeature('multi_currency') && customer.currency) {
                            $scope.invoice.currency = customer.currency;
                        }

                        // inherit customer's payment terms
                        $scope.invoice.inherit_terms = true;

                        // inherit customer's AutoPay
                        inheritCustomerAutoPay(customer);

                        // inherit customer's taxes
                        if (customer.taxes) {
                            inheritCustomerTaxes(customer);
                        }

                        // inherit customer's metadata
                        if ($scope.customFields) {
                            inheritCustomerMetadata(customer);
                        }
                    }

                    function inheritCustomerAutoPay(customer) {
                        if (!$scope.options.inheritFromCustomer) {
                            return;
                        }

                        $scope.invoice.autopay = customer.autopay;

                        if (customer.autopay) {
                            $scope.invoice._payment_terms = 'AutoPay';
                            setAutoPayDate(customer);
                        } else {
                            if (customer.payment_terms) {
                                $scope.invoice._payment_terms = customer.payment_terms;
                            } else {
                                // when the customer doesn't have payment terms
                                // specified, the user is free to choose any terms
                                $scope.invoice.inherit_terms = false;
                            }
                        }
                    }

                    function inheritCustomerTaxes(customer) {
                        if (!$scope.options.inheritFromCustomer) {
                            return;
                        }

                        angular.forEach(customer.taxes, function (tax) {
                            addRate($scope.invoice.taxes, 'taxes', tax);
                        });
                    }

                    function inheritCustomerMetadata(customer) {
                        // If the customer came from the search index
                        // then it would not have properly formed metadata
                        if (typeof customer.metadata !== 'object' || Array.isArray(customer.metadata)) {
                            Customer.find(
                                {
                                    id: customer.id,
                                },
                                function (result) {
                                    $scope.invoice.customer = result;
                                    // prevent infinite loop
                                    if (typeof customer.metadata === 'object') {
                                        inheritCustomerMetadata(result);
                                    }
                                },
                            );
                            return;
                        }

                        if (!$scope.options.inheritFromCustomer) {
                            return;
                        }

                        angular.forEach($scope.customFields, function (field) {
                            let customerValue = customer.metadata[field.id];
                            if (typeof customerValue !== 'undefined') {
                                $scope.invoice.metadata[field.id] = customerValue;
                            }
                        });
                    }

                    // Sets the invoice payment date when AutoPay is enabled
                    // based on the customer and company payment delay settings
                    function setAutoPayDate(customer) {
                        if (loadingAutoPayDate) {
                            return;
                        }

                        loadingAutoPayDate = true;
                        Settings.accountsReceivable(function (settings) {
                            if (typeof customer.autopay_delay_days === 'undefined') {
                                Customer.find(
                                    {
                                        id: customer.id,
                                    },
                                    function (result) {
                                        customer.autopay_delay_days = result.autopay_delay_days;
                                        applyAutoPayDelay(customer, settings);
                                    },
                                    function () {
                                        // load global setting if there is an error
                                        customer.autopay_delay_days = -1;
                                        applyAutoPayDelay(customer, settings);
                                    },
                                );
                            } else {
                                applyAutoPayDelay(customer, settings);
                            }
                        });
                    }

                    function applyAutoPayDelay(customer, settings) {
                        loadingAutoPayDate = false;
                        let delay = settings.autopay_delay_days;
                        if (customer.autopay_delay_days >= 0) {
                            delay = customer.autopay_delay_days;
                        }
                        $scope.invoice.next_payment_attempt = moment($scope.invoice.date).add(delay, 'days').toDate();
                        lastSetAutoPayDate = $scope.invoice.date;
                    }

                    function dateFormat() {
                        $scope.dateOptions = DatePickerService.getOptions();
                    }

                    function addBlankLines(n) {
                        if ($scope.invoice.items.length > maxLineItems) {
                            return;
                        }

                        for (let i = 0; i < n; i++) {
                            let item = angular.copy(newItem);
                            item.isBlank = true;
                            $scope.invoice.items.push(item);
                        }
                    }

                    function addItem(item, quantity) {
                        let newLine = {
                            catalog_item: item.id,
                            name: item.name,
                            quantity: quantity,
                            unit_cost: item.unit_cost,
                            type: item.type,
                            description: item.description,
                            discountable: item.discountable,
                            taxable: item.taxable,
                            taxes: angular.copy(item.taxes),
                            metadata: angular.copy(item.metadata),
                        };

                        $scope.invoice.items.splice(-1, 0, newLine);
                    }

                    function deleteLine(item) {
                        for (let i in $scope.invoice.items) {
                            if ($scope.invoice.items[i].$$hashKey == item.$$hashKey) {
                                $scope.invoice.items.splice(i, 1);
                                break;
                            }
                        }
                    }

                    function calculate() {
                        // ensure that there is always at least 1 blank line
                        let hasBlank = false;
                        for (let i in $scope.invoice.items) {
                            let item = $scope.invoice.items[i];
                            if (item.isBlank) {
                                if (lineIsBlank(item)) {
                                    hasBlank = true;
                                    break;
                                } else {
                                    item.isBlank = false;
                                }
                            }
                        }

                        if (!hasBlank) {
                            addBlankLines(1);
                        }

                        InvoiceCalculator.calculate($scope.invoice, $scope.company.moneyFormat);
                    }

                    function lineIsBlank(line) {
                        return (
                            line.name === '' &&
                            !line.unit_cost &&
                            line.description === '' &&
                            line.discounts.length === 0 &&
                            line.taxes.length === 0
                        );
                    }

                    function addCustomRate(appliedRates, type) {
                        // check if there is already a custom rate
                        // because only 1 should be added
                        let hasCustomRate = false;
                        let customRateId = false;
                        let k = rateObjectKeys[type];
                        angular.forEach(appliedRates, function (appliedRate) {
                            if (!appliedRate[k]) {
                                customRateId = appliedRate.id;
                                hasCustomRate = true;
                            }
                        });

                        if (hasCustomRate) {
                            // focus the existing custom rate input
                            $timeout(function () {
                                $('#custom-rate-' + customRateId + ' input').focus();
                            });

                            return;
                        }

                        let appliedRate = {
                            id: generateID(),
                            amount: '',
                            _amount: '',
                        };
                        appliedRate[k] = null;

                        appliedRates.push(appliedRate);

                        // focus the new custom rate input once
                        // it has been rendered
                        $timeout(function () {
                            $('#custom-rate-' + appliedRate.id + ' input').focus();
                        });
                    }

                    function addRate(appliedRates, type, rate) {
                        let k = rateObjectKeys[type];

                        // block duplicates
                        for (let i in appliedRates) {
                            let rate2 = appliedRates[i][k];
                            if (rate2 && rate2.id == rate.id) {
                                // duplicate found
                                return;
                            }
                        }

                        let appliedRate = {
                            id: generateID(),
                        };
                        appliedRate[k] = rate;

                        appliedRates.push(appliedRate);
                    }

                    function generateID() {
                        // generate a unique ID for ngRepeat track by
                        // (use negative #s to prevent collisions with
                        //  actual Applied Rate IDs)
                        return -1 * Math.round(Math.random() * 100000);
                    }

                    function deleteRate(appliedRates, type, appliedRate) {
                        let k = rateObjectKeys[type];
                        for (let i in appliedRates) {
                            let appliedRate2 = appliedRates[i];

                            // check if the Applied Rate IDs match
                            if (appliedRate.id && appliedRate2.id == appliedRate.id) {
                                appliedRates.splice(i, 1);
                                break;
                            }

                            // or the Rate IDs match
                            if (appliedRate[k] && appliedRate2[k] && appliedRate2[k].id == appliedRate[k].id) {
                                appliedRates.splice(i, 1);
                                break;
                            }
                        }
                    }

                    function computeCustomRates() {
                        // adds rates with custom amounts to the customRates
                        // array so they can be edited
                        angular.forEach(['discounts', 'taxes', 'shipping'], function (type) {
                            let k = rateObjectKeys[type];
                            angular.forEach($scope.invoice[type], function (appliedRate) {
                                if (!appliedRate[k]) {
                                    appliedRate._amount = appliedRate.amount;
                                }
                            });
                        });
                    }

                    function deleteAttachment(attachment) {
                        for (let i in $scope.invoice.attachments) {
                            if ($scope.invoice.attachments[i].file.id === attachment.file.id) {
                                $scope.invoice.attachments.splice(i, 1);
                                break;
                            }
                        }
                    }
                },
            ],
        };
    }
})();
