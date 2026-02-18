/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditEstimateController', EditEstimateController);

    EditEstimateController.$inject = [
        '$scope',
        '$stateParams',
        '$controller',
        '$rootScope',
        'Estimate',
        'Customer',
        'Core',
        'selectedCompany',
        'Settings',
        'MetadataCaster',
        'Feature',
    ];

    function EditEstimateController(
        $scope,
        $stateParams,
        $controller,
        $rootScope,
        Estimate,
        Customer,
        Core,
        selectedCompany,
        Settings,
        MetadataCaster,
        Feature,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.company = selectedCompany;
        $scope.model = Estimate;
        $scope.modelTitleSingular = 'Estimate';

        $scope.estimateOptions = {
            type: 'estimate',
            hasNumber: true,
            hasShipTo: true,
            hasDate: true,
            hasPaymentTerms: true,
            hasDueDate: false,
            hasExpiryDate: true,
            hasDeposit: true,
            calculateDueDate: false,
            hasPurchaseOrder: true,
            hasAmountPaid: false,
            hasTotal: true,
            hasBalance: false,
            hasAttachments: true,
            hasExpiringDiscounts: true,
        };

        $scope.deleteKeysForDuplicate = [
            'id',
            'name',
            'approved',
            'sent',
            'viewed',
            'closed',
            'invoice',
            'deposit_paid',
        ];

        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');

        //
        // Presets
        //

        // start with a blank estimate
        $scope.estimate = {
            name: 'Estimate',
            currency: $scope.company.currency,
            date: new Date(),
            _payment_terms: '',
            items: [],
            discounts: [],
            taxes: [],
            shipping: [],
            amount_paid: 0,
            total: 0,
            draft: true,
            attachments: [],
            deposit: 0,
            metadata: {},
            calculate_taxes: true,
        };
        let wasDraft = true;

        // check if a customer was provided through the route
        if ($stateParams.customer) {
            Customer.find(
                {
                    id: $stateParams.customer,
                },
                function (customer) {
                    $scope.estimate.customer = customer;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer';
            findParams.include = 'customer.address';
        };

        $scope.postFind = function (estimate) {
            estimate._payment_terms = estimate.payment_terms;
            // consider payment terms inherited when set
            estimate.inherit_terms = !!estimate.payment_terms;

            if ($scope.action === 'duplicate') {
                $scope.estimateOptions.inheritFromCustomer = false;

                // load new # for duplicates
                delete estimate.number;

                // duplicates start as drafts
                estimate.draft = true;

                // delete all line item IDs
                angular.forEach(estimate.items, function (item) {
                    delete item.id;

                    // generate temporary IDs for applied rates
                    angular.forEach(['discounts', 'taxes'], function (k) {
                        angular.forEach(item[k], function (appliedRate) {
                            appliedRate.id = generateAppliedRateID();
                        });
                    });
                });

                // remove Avalara calculated taxes
                for (let i in estimate.taxes) {
                    let tax = estimate.taxes[i];
                    if (tax.tax_rate && tax.tax_rate.id === 'AVATAX') {
                        estimate.taxes.splice(i, 1);
                        break;
                    }
                }

                // generate temporary IDs for applied rates
                angular.forEach(['discounts', 'taxes', 'shipping'], function (k) {
                    angular.forEach(estimate[k], function (appliedRate) {
                        appliedRate.id = generateAppliedRateID();
                    });
                });

                // set date to today
                estimate.date = new Date();

                estimate.calculate_taxes = true;

                estimate.attachments = [];

                // trigger a recalculation
                estimate._recalculate = true;
            } else {
                $rootScope.modelTitle = estimate.number;

                estimate.calculate_taxes = false;

                estimate.attachments = [];
                loadAttachments(estimate.id);
            }

            wasDraft = estimate.draft;

            $scope.estimate = estimate;

            return $scope.estimate;
        };

        $scope.issue = function (estimate) {
            estimate = angular.copy(estimate);
            estimate.draft = false;

            $scope.issuing = true;
            $scope.save(estimate, !!estimate.id);
            $scope.issuing = false;
        };

        $scope.preSave = function (estimate, cb) {
            let params = {
                id: estimate.id,
                name: estimate.name,
                number: estimate.number,
                currency: estimate.currency,
                customer: estimate.customer,
                date: estimate.date,
                discounts: estimate.discounts,
                taxes: estimate.taxes,
                shipping: estimate.shipping,
                draft: estimate.draft,
                calculate_taxes: estimate.calculate_taxes,
                notes: estimate.notes,
                purchase_order: estimate.purchase_order,
                ship_to: estimate.ship_to,
                payment_terms: estimate.payment_terms,
                deposit: estimate.deposit,
                expiration_date: estimate.expiration_date,
            };

            // only send draft parameter as false when issuing document
            if (!params.draft && !$scope.issuing) {
                delete params.draft;
            }

            // parse customer
            if (typeof params.customer == 'object') {
                params.customer = params.customer.id;
            }

            // parse dates
            if (params.date) {
                // The time of day on estimate dates should be 6am local time zone
                params.date = moment(params.date).hour(6).minute(0).second(0).unix();
            }
            if (params.expiration_date) {
                // The time of day on expiration dates should be 6pm local time zone
                params.expiration_date = moment(params.expiration_date).hour(18).minute(0).second(0).unix();
            }

            // parse items
            let itemReplaceKeys = ['unit_cost', 'amount'];
            let rateTypes = ['discounts', 'taxes', 'shipping'];
            let newItems = [];
            angular.forEach(estimate.items, function (item) {
                if (item.isBlank) {
                    return;
                }

                delete item.isBlank;
                delete item.object;
                delete item.created_at;
                delete item.amount;

                // remove item number separators
                for (let j in itemReplaceKeys) {
                    let el = item[itemReplaceKeys[j]];
                    if (typeof el == 'string') {
                        item[itemReplaceKeys[j]] = Core.parseFormattedNumber(
                            el,
                            $scope.company.decimal_separator,
                            $scope.company.thousands_separator,
                        );
                    }
                }

                // parse item
                if (typeof item.catalog_item === 'object' && item.catalog_item) {
                    item.catalog_item = item.catalog_item.id;
                }

                // parse applied rates
                angular.forEach(rateTypes, function (type) {
                    angular.forEach(item[type], function (appliedRate) {
                        if (appliedRate.id < 0) {
                            delete appliedRate.id;
                        }

                        delete appliedRate._amount;

                        if (appliedRate.expires) {
                            appliedRate.expires = moment(appliedRate.expires).startOf('day').unix();
                        }
                    });
                });

                newItems.push(item);
            });
            params.items = newItems;

            // parse applied rates
            angular.forEach(rateTypes, function (type) {
                angular.forEach(params[type], function (appliedRate) {
                    if (appliedRate.id < 0) {
                        delete appliedRate.id;
                    }

                    delete appliedRate._amount;

                    if (appliedRate.expires) {
                        appliedRate.expires = moment(appliedRate.expires).startOf('day').unix();
                    }
                });
            });

            // parse attachments
            if (estimate.attachments) {
                let attachments = [];
                angular.forEach(estimate.attachments, function (attachment) {
                    attachments.push(attachment.file.id);
                });
                params.attachments = attachments;
            }

            // parse metadata
            MetadataCaster.marshalForInvoiced('estimate', estimate.metadata, function (metadata) {
                params.metadata = metadata;
                if (0 === params.items.length) {
                    cb(params);
                    return;
                }

                // marshal line item metadata
                let marshalling = params.items.length;
                angular.forEach(params.items, function (item) {
                    MetadataCaster.marshalForInvoiced('line_item', item.metadata, function (metadata) {
                        item.metadata = metadata;
                        marshalling--;

                        if (0 === marshalling) {
                            cb(params);
                        }
                    });
                });
            });
        };

        //
        // Initialization
        //

        $scope.initializeEditPage();
        Core.setTitle('Edit Estimate');

        if ($scope.action === 'new') {
            Core.setTitle('New Estimate');

            loadSettings();
        }

        function loadSettings() {
            Settings.accountsReceivable(function (settings) {
                $scope.settings = settings;
                applySettings(settings);
            });
        }

        function applySettings(settings) {
            angular.extend($scope.estimate, {
                _payment_terms: settings.payment_terms,
            });

            $scope.loadTemplates(function (templates) {
                // apply any default template
                let tid = settings.default_template_id;
                if (tid && typeof templates[tid.toString()] !== 'undefined') {
                    $scope.replaceWithTemplate($scope.estimate, templates[tid]);
                }
            });
        }

        function loadAttachments(id) {
            if ($scope.loaded.attachments) {
                return;
            }

            Estimate.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.loaded.attachments = true;
                    $scope.estimate.attachments = attachments;
                },
            );
        }

        function generateAppliedRateID() {
            // generate a unique ID for ngRepeat track by
            // (use negative #s to prevent collisions with
            //  actual Applied Rate IDs)
            return -1 * Math.round(Math.random() * 100000);
        }
    }
})();
