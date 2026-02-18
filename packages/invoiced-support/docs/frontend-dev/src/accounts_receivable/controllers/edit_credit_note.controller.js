/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditCreditNoteController', EditCreditNoteController);

    EditCreditNoteController.$inject = [
        '$scope',
        '$stateParams',
        '$controller',
        '$rootScope',
        'CreditNote',
        'Invoice',
        'Customer',
        'Core',
        'MetadataCaster',
        'selectedCompany',
        'Feature',
    ];

    function EditCreditNoteController(
        $scope,
        $stateParams,
        $controller,
        $rootScope,
        CreditNote,
        Invoice,
        Customer,
        Core,
        MetadataCaster,
        selectedCompany,
        Feature,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.company = selectedCompany;
        $scope.model = CreditNote;
        $scope.modelTitleSingular = 'Credit Note';

        $scope.creditNoteOptions = {
            type: 'credit_note',
            hasNumber: true,
            hasDate: true,
            hasPaymentTerms: false,
            hasDueDate: false,
            hasPurchaseOrder: true,
            hasAmountPaid: false,
            hasTotal: true,
            hasBalance: true,
            hasAttachments: true,
            hasExpiringDiscounts: false,
            hasTerms: false,
        };

        $scope.deleteKeysForDuplicate = ['id', 'name', 'sent', 'viewed', 'closed', 'invoice'];

        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');

        //
        // Presets
        //

        // start with a blank credit note
        $scope.creditNote = {
            name: 'Credit Note',
            currency: $scope.company.currency,
            date: new Date(),
            items: [],
            discounts: [],
            taxes: [],
            shipping: [],
            total: 0,
            balance: 0,
            draft: true,
            attachments: [],
            metadata: {},
            calculate_taxes: true,
        };

        // check if an invoice or customer was provided through the route
        if ($stateParams.invoice) {
            Invoice.find(
                {
                    id: $stateParams.invoice,
                    expand: 'customer',
                },
                function (invoice) {
                    $scope.creditNote.customer = invoice.customer;
                    $scope.creditNote.invoice = invoice.id;
                    $scope.creditNote.purchase_order = invoice.purchase_order;
                    $scope.creditNote.items = angular.copy(invoice.items);

                    // delete all line item IDs
                    angular.forEach($scope.creditNote.items, function (item) {
                        delete item.id;

                        // generate temporary IDs for applied rates
                        angular.forEach(['discounts', 'taxes'], function (k) {
                            angular.forEach(item[k], function (appliedRate) {
                                appliedRate.id = generateAppliedRateID();
                            });
                        });
                    });

                    // generate temporary IDs for applied rates
                    angular.forEach(['discounts', 'taxes', 'shipping'], function (k) {
                        $scope.creditNote[k] = angular.copy(invoice[k]);
                        angular.forEach($scope.creditNote[k], function (appliedRate) {
                            appliedRate.id = generateAppliedRateID();
                        });
                    });

                    // remove Avalara calculated taxes
                    for (let i in $scope.creditNote.taxes) {
                        let tax = $scope.creditNote.taxes[i];
                        if (tax.tax_rate && tax.tax_rate.id === 'AVATAX') {
                            $scope.creditNote.taxes.splice(i, 1);
                            break;
                        }
                    }

                    // trigger a recalculation
                    $scope.creditNote._recalculate = true;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        } else if ($stateParams.customer) {
            Customer.find(
                {
                    id: $stateParams.customer,
                },
                function (customer) {
                    $scope.creditNote.customer = customer;
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

        $scope.postFind = function (creditNote) {
            if ($scope.action === 'duplicate') {
                $scope.creditNoteOptions.inheritFromCustomer = false;

                // load new # for duplicates
                delete creditNote.number;

                // duplicates start as drafts
                creditNote.draft = true;

                // delete all line item IDs
                angular.forEach(creditNote.items, function (item) {
                    delete item.id;

                    // generate temporary IDs for applied rates
                    angular.forEach(['discounts', 'taxes'], function (k) {
                        angular.forEach(item[k], function (appliedRate) {
                            appliedRate.id = generateAppliedRateID();
                        });
                    });
                });

                // remove Avalara calculated taxes
                for (let i in creditNote.taxes) {
                    let tax = creditNote.taxes[i];
                    if (tax.tax_rate && tax.tax_rate.id === 'AVATAX') {
                        creditNote.taxes.splice(i, 1);
                        break;
                    }
                }

                // generate temporary IDs for applied rates
                angular.forEach(['discounts', 'taxes', 'shipping'], function (k) {
                    angular.forEach(creditNote[k], function (appliedRate) {
                        appliedRate.id = generateAppliedRateID();
                    });
                });

                creditNote.calculate_taxes = true;

                creditNote.attachments = [];

                // trigger a recalculation
                creditNote._recalculate = true;
            } else {
                $rootScope.modelTitle = creditNote.number;

                creditNote.calculate_taxes = false;

                creditNote.attachments = [];
                loadAttachments(creditNote.id);
            }

            $scope.creditNote = creditNote;

            return $scope.creditNote;
        };

        $scope.preSave = function (creditNote, cb) {
            let params = {
                id: creditNote.id,
                name: creditNote.name,
                number: creditNote.number,
                currency: creditNote.currency,
                customer: creditNote.customer,
                date: creditNote.date,
                discounts: creditNote.discounts,
                taxes: creditNote.taxes,
                shipping: creditNote.shipping,
                draft: creditNote.draft,
                calculate_taxes: creditNote.calculate_taxes,
                notes: creditNote.notes,
                purchase_order: creditNote.purchase_order,
                invoice: creditNote.invoice,
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
                // The time of day on credit note dates should be 6am local time zone
                params.date = moment(params.date).hour(6).minute(0).second(0).unix();
            }

            // parse items
            let itemReplaceKeys = ['unit_cost', 'amount'];
            let rateTypes = ['discounts', 'taxes', 'shipping'];
            let newItems = [];
            angular.forEach(creditNote.items, function (item) {
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
            if (creditNote.attachments) {
                let attachments = [];
                angular.forEach(creditNote.attachments, function (attachment) {
                    attachments.push(attachment.file.id);
                });
                params.attachments = attachments;
            }

            // parse metadata
            MetadataCaster.marshalForInvoiced('credit_note', creditNote.metadata, function (metadata) {
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

        $scope.issue = function (creditNote) {
            creditNote.draft = false;

            $scope.issuing = true;
            $scope.save(creditNote, !!creditNote.id);
            $scope.issuing = false;
        };

        //
        // Initialization
        //

        $scope.initializeEditPage();
        Core.setTitle('Edit Credit Note');

        if ($scope.action === 'new') {
            Core.setTitle('New Credit Note');
        }

        function loadAttachments(id) {
            if ($scope.loaded.attachments) {
                return;
            }

            CreditNote.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                    $scope.creditNote.attachments = attachments;
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
