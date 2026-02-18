/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditInvoiceController', EditInvoiceController);

    EditInvoiceController.$inject = [
        '$scope',
        '$stateParams',
        '$controller',
        '$rootScope',
        '$translate',
        'Invoice',
        'Customer',
        'Core',
        'MetadataCaster',
        'selectedCompany',
        'Settings',
        'MerchantAccount',
        'Feature',
    ];

    function EditInvoiceController(
        $scope,
        $stateParams,
        $controller,
        $rootScope,
        $translate,
        Invoice,
        Customer,
        Core,
        MetadataCaster,
        selectedCompany,
        Settings,
        MerchantAccount,
        Feature,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.company = selectedCompany;
        $scope.model = Invoice;
        $scope.modelTitleSingular = 'Invoice';

        $scope.invoiceOptions = {
            type: 'invoice',
            hasNumber: true,
            hasShipTo: true,
            hasDate: true,
            hasPaymentTerms: true,
            hasDueDate: true,
            hasPurchaseOrder: true,
            hasAmountPaid: true,
            hasTotal: true,
            hasBalance: true,
            hasAttachments: true,
            hasExpiringDiscounts: true,
        };

        $scope.pdfOptions = [];
        $scope.selectedPdf = '__default__';

        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');
        $scope.hasTags = Feature.hasFeature('invoice_tags');

        $scope.deleteKeysForDuplicate = [
            'id',
            'name',
            'closed',
            'amount_paid',
            'paid',
            'subscription',
            'subscription_id',
            'needs_attention',
            'payment_plan',
        ];

        $scope.deleteMetadataKeysForDuplicate = [
            'intacct_invoice_id',
            'netsuite_invoice_id',
            'netsuite_id',
            'quickbooks_invoice_id',
            'xero_invoice_id',
        ];

        //
        // Presets
        //

        // start with a blank invoice
        $scope.invoice = {
            name: 'Invoice',
            currency: $scope.company.currency,
            date: new Date(),
            inherit_terms: true,
            _payment_terms: '',
            due_date: null,
            next_payment_attempt: null,
            items: [],
            discounts: [],
            taxes: [],
            shipping: [],
            total: 0,
            amount_paid: 0,
            balance: 0,
            chase: false,
            draft: true,
            disabled_payment_methods: {},
            attachments: [],
            metadata: {},
            calculate_taxes: true,
            late_fees: true,
        };
        let wasDraft = true;

        // check if a customer was provided through the route
        if ($stateParams.customer) {
            Customer.find(
                {
                    id: $stateParams.customer,
                },
                function (customer) {
                    $scope.invoice.customer = customer;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        $scope.selectedMerchantAccount = -1;

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer';
            findParams.include = 'disabled_payment_methods,customer.address';

            if ($scope.hasTags) {
                findParams.include += ',tags';
            }
        };

        $scope.postFind = function (invoice) {
            invoice._payment_terms = invoice.payment_terms;
            // consider payment terms inherited when set
            invoice.inherit_terms = !!invoice.payment_terms;

            if ($scope.action === 'duplicate') {
                $scope.invoiceOptions.inheritFromCustomer = false;

                // load new # for duplicates
                delete invoice.number;

                // duplicates start as drafts
                invoice.draft = true;

                // delete all line item IDs
                angular.forEach(invoice.items, function (item) {
                    delete item.id;

                    // generate temporary IDs for applied rates
                    angular.forEach(['discounts', 'taxes'], function (k) {
                        angular.forEach(item[k], function (appliedRate) {
                            appliedRate.id = generateAppliedRateID();
                        });
                    });
                });

                // remove Avalara calculated taxes
                for (let i in invoice.taxes) {
                    let tax = invoice.taxes[i];
                    if (tax.tax_rate && tax.tax_rate.id === 'AVATAX') {
                        invoice.taxes.splice(i, 1);
                        break;
                    }
                }

                // generate temporary IDs for applied rates
                angular.forEach(['discounts', 'taxes', 'shipping'], function (k) {
                    angular.forEach(invoice[k], function (appliedRate) {
                        appliedRate.id = generateAppliedRateID();
                    });
                });

                invoice.calculate_taxes = true;

                invoice.attachments = [];

                // trigger a recalculation
                invoice._recalculate = true;
            } else {
                $rootScope.modelTitle = invoice.number;

                invoice.calculate_taxes = false;

                invoice.attachments = [];
                loadAttachments(invoice.id);
            }

            wasDraft = invoice.draft;

            $scope.invoice = invoice;

            return $scope.invoice;
        };

        $scope.preSave = function (invoice, cb) {
            let params = {
                id: invoice.id,
                name: invoice.name,
                number: invoice.number,
                currency: invoice.currency,
                customer: invoice.customer,
                date: invoice.date,
                discounts: invoice.discounts,
                taxes: invoice.taxes,
                shipping: invoice.shipping,
                draft: invoice.draft,
                calculate_taxes: invoice.calculate_taxes,
                notes: invoice.notes,
                purchase_order: invoice.purchase_order,
                ship_to: invoice.ship_to,
                due_date: invoice.due_date,
                payment_terms: invoice.payment_terms,
                autopay: invoice.autopay,
                next_payment_attempt: invoice.next_payment_attempt,
                late_fees: invoice.late_fees,
                chase: invoice.chase,
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
                // The time of day on invoice dates should be 6am local time zone
                params.date = moment(params.date).hour(6).minute(0).second(0).unix();
            }

            if (params.due_date) {
                // The time of day on due dates should be 6pm local time zone
                params.due_date = moment(params.due_date).hour(18).minute(0).second(0).unix();
            }

            // Allow setting payment date on new invoices
            if (params.autopay && !params.id) {
                if (params.next_payment_attempt) {
                    params.next_payment_attempt = moment(params.next_payment_attempt).unix();
                } else {
                    delete params.next_payment_attempt;
                }
            } else {
                delete params.next_payment_attempt;
            }

            // parse items
            let itemReplaceKeys = ['unit_cost', 'amount'];
            let rateTypes = ['discounts', 'taxes', 'shipping'];
            let newItems = [];
            angular.forEach(invoice.items, function (item) {
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

                    if (appliedRate.from_payment_terms) {
                        delete appliedRate.coupon;
                    }
                });
            });

            // when not using custom terms, inherit autopay,
            // due_date, and payment_terms from customer
            // by not sending them to the API
            if (invoice.inherit_terms) {
                delete params.autopay;
                delete params.payment_terms;

                if (!invoice.mutable_due_date) {
                    delete params.due_date;
                }
            }

            // parse attachments
            if (invoice.attachments) {
                let attachments = [];
                angular.forEach(invoice.attachments, function (attachment) {
                    if ($scope.selectedPdf == attachment.file.id) {
                        params.pdf_attachment = $scope.selectedPdf;
                    }

                    attachments.push(attachment.file.id);
                });
                params.attachments = attachments;
            }

            // parse disabled payment methods
            if (invoice.disabled_payment_methods) {
                let methods = [];
                angular.forEach(invoice.disabled_payment_methods, function (disabled, method) {
                    if (disabled) {
                        methods.push(method);
                    }
                });
                params.disabled_payment_methods = methods;
            }

            // parse merchant account
            if ($scope.selectedMerchantAccount > 0) {
                params.merchant_account_routing = [
                    {
                        method: 'credit_card',
                        merchant_account: $scope.selectedMerchantAccount,
                    },
                    {
                        method: 'ach',
                        merchant_account: $scope.selectedMerchantAccount,
                    },
                ];
            }

            // parse metadata
            MetadataCaster.marshalForInvoiced('invoice', invoice.metadata, function (metadata) {
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

        $scope.postCreate = function (invoice) {
            if ($scope.hasMultiCurrency && selectedCompany.currencies.indexOf(invoice.currency) === -1) {
                selectedCompany.currencies.push(invoice.currency);
            }
        };

        $scope.issue = function (invoice) {
            invoice = angular.copy(invoice);
            invoice.draft = false;

            $scope.issuing = true;
            $scope.save(invoice, !!invoice.id);
            $scope.issuing = false;
        };

        $scope.switchToManual = function (invoice) {
            invoice.autopay = false;
            invoice._payment_terms = null;
            invoice.inherit_terms = false;
        };

        $scope.switchToAuto = function (invoice) {
            invoice.autopay = true;
            invoice._payment_terms = 'AutoPay';
            invoice.inherit_terms = true;
        };

        //
        // Initialization
        //

        $scope.initializeEditPage();
        Core.setTitle('Edit Invoice');
        loadMerchantAccounts();

        if ($scope.action === 'new') {
            Core.setTitle('New Invoice');
            $scope.invoiceOptions.hasPaymentDate = true;

            loadSettings();
        } else if ($scope.action === 'duplicate') {
            Core.setTitle('New Invoice');
            $scope.invoiceOptions.hasPaymentDate = true;
        }

        let attachmentCount = 0;
        $scope.$watch(
            'invoice',
            function (invoice) {
                if (typeof invoice.attachments !== 'object') {
                    return;
                }

                if (attachmentCount === invoice.attachments.length) {
                    return;
                }

                $scope.pdfOptions = [
                    {
                        id: '__default__',
                        name: 'Use Invoiced template',
                    },
                ];

                angular.forEach(invoice.attachments, function (attachment) {
                    if (attachment.file.type == 'application/pdf') {
                        $scope.pdfOptions.push({
                            id: attachment.file.id,
                            name: attachment.file.name,
                        });
                    }
                });

                attachmentCount = invoice.attachments.length;
            },
            true,
        );

        function loadSettings() {
            Settings.accountsReceivable(function (settings) {
                $scope.settings = settings;
                applySettings(settings);
            });
        }

        function applySettings(settings) {
            angular.extend($scope.invoice, {
                chase: settings.chase_new_invoices,
                _payment_terms: settings.payment_terms,
            });

            $scope.loadTemplates(function (templates) {
                // apply any default template
                let tid = settings.default_template_id;
                if (tid && typeof templates[tid.toString()] !== 'undefined') {
                    $scope.replaceWithTemplate($scope.invoice, templates[tid]);
                }
            });
        }

        function loadAttachments(id) {
            if ($scope.loaded.attachments) {
                return;
            }

            Invoice.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.loaded.attachments = true;
                    $scope.invoice.attachments = attachments;

                    angular.forEach(attachments, function (attachment) {
                        if (attachment.location === 'pdf') {
                            $scope.selectedPdf = attachment.file.id;
                        }
                    });
                },
            );
        }

        function loadMerchantAccounts() {
            if ($scope.loaded.merchantAccounts) {
                return;
            }

            MerchantAccount.findAll(
                {
                    'filter[deleted]': false,
                    paginate: 'none',
                },
                function (accounts) {
                    $scope.merchantAccounts = [{ id: -1, list_name: 'Default' }];
                    angular.forEach(accounts, function (merchantAccount) {
                        merchantAccount.list_name =
                            $translate.instant('payment_gateways.' + merchantAccount.gateway) +
                            ': ' +
                            merchantAccount.name;
                        $scope.merchantAccounts.push(merchantAccount);
                    });

                    $scope.loaded.merchantAccounts = true;
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
