/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewCustomerController', ViewCustomerController);

    ViewCustomerController.$inject = [
        '$scope',
        '$stateParams',
        '$state',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        '$window',
        'LeavePageWarning',
        'Customer',
        'Estimate',
        'CreditNote',
        'Invoice',
        'Subscription',
        'Payment',
        'Transaction',
        'Dashboard',
        'Core',
        'BillingPortal',
        'InvoiceCalculator',
        'InvoicedConfig',
        'CurrentUser',
        'Note',
        'Task',
        'BrowsingHistory',
        'Permission',
        'EmailThread',
        'Feature',
        'Member',
        'UserNotification',
        'Network',
        'Settings',
        'PaymentDisplayHelper',
        'PaymentLink',
        'PaymentLinkHelper',
    ];

    function ViewCustomerController(
        $scope,
        $stateParams,
        $state,
        $controller,
        $rootScope,
        $modal,
        $filter,
        $window,
        LeavePageWarning,
        Customer,
        Estimate,
        CreditNote,
        Invoice,
        Subscription,
        Payment,
        Transaction,
        Dashboard,
        Core,
        BillingPortal,
        InvoiceCalculator,
        InvoicedConfig,
        CurrentUser,
        Note,
        Task,
        BrowsingHistory,
        Permission,
        EmailThread,
        Feature,
        Member,
        UserNotification,
        Network,
        Settings,
        PaymentDisplayHelper,
        PaymentLink,
        PaymentLinkHelper,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Customer;
        $scope.modelTitleSingular = 'Customer';
        $scope.modelObjectType = 'customer';
        $scope.stripeDashboardUrl = InvoicedConfig.stripeDashboardUrl;
        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');
        $scope.hasPermissions = Permission.hasSomePermissions([
            'payments.create',
            'credits.create',
            'charges.create',
            'subscriptions.create',
            'estimates.create',
            'invoices.create',
        ]);

        //
        // Presets
        //

        $scope.customer = {
            type: 'company',
            country: $scope.company.country,
        };

        $scope.transactions = [];
        $scope.transactionPage = 1;
        $scope.transactionsTab = 'invoices';
        $scope.contacts = [];
        $scope.childCustomers = [];
        $scope.subscriptions = [];
        $scope.linkToPayments = true;
        $scope.paymentSources = [];
        $scope.showMorePaymentSource = {};
        $scope.showingCompletedTasks = false;
        $scope.subscriptionPage = 1;
        $scope.collectionActivity = {};
        $scope.newNoteForm = false;
        $scope.newNote = { notes: '' };
        $scope.invitedToNetwork = false;
        $scope.threads = [];

        $scope.currency = $scope.company.currency;

        $scope.period = {
            start: moment().subtract(1, 'years').toDate(),
            end: moment().toDate(),
            period: ['years', 1],
        };

        $scope.dashboardContext = {
            customer: $stateParams.id,
            currency: $scope.company.currency,
        };
        $scope.timeToPayOptions = {
            gauge: true,
            min: 0,
            max: 45,
        };
        $scope.ceiOptions = {
            gauge: true,
            min: 0,
            max: 100,
        };
        $scope.dsoOptions = {
            gauge: true,
            min: 0,
            max: 45,
        };
        $scope.epOptions = {};

        $scope.member = null;
        $scope.attachments = [];

        let transactionsPerPage = 5;

        loadCustomerPortalSettings();

        //
        // Methods
        //

        Member.current(
            function (member) {
                $scope.member = member;
            },
            function (result) {
                $scope.error = result.data;
            },
        );

        $scope.subscription = function (value) {
            UserNotification.subscription(
                {
                    subscription: value,
                    customer_id: $scope.customer.id,
                },
                function (data) {
                    $scope.customer.subscribed = data.subscribe;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.preFind = function (findParams) {
            findParams.expand = 'parent_customer,chasing_cadence,late_fee_schedule,owner';
            findParams.include = 'address,subscribed';
        };

        $scope.postFind = function (customer) {
            $scope.customer = customer;

            if ($scope.hasMultiCurrency && customer.currency) {
                $scope.changeCurrency(customer.currency);
            }

            $rootScope.modelTitle = customer.name;
            Core.setTitle(customer.name);

            $scope.chasingCadence = customer.chasing_cadence;
            parseChasing(customer, $scope.chasingCadence);

            loadNetworkProfile(customer);
            $scope.setTransactionsTab(customer, 'invoices');
            determineSyncStatus(customer);

            BrowsingHistory.push({
                id: customer.id,
                type: 'customer',
                title: customer.name,
            });

            loadAttachments(customer.id);
            loadNetworkStatus(customer.id);

            return $scope.customer;
        };

        $scope.isLoaded = function () {
            return $scope.loaded.subscriptions && $scope.loaded.threads && $scope.loaded.upcomingInvoice;
        };

        /* Balance */

        $scope.changeCurrency = function (currency) {
            $scope.loaded.balance = false;
            $scope.currency = currency;
            $scope.loadBalance($scope.modelId, currency);

            if ($state.is('manage.customer.view.report')) {
                $scope.dashboardContext.currency = currency;
            }
        };

        $scope.loadBalance = function (id, currency) {
            if ($scope.loaded.balance || !id) {
                return;
            }

            Customer.balance(
                {
                    id: id,
                    currency: currency,
                },
                function (balance) {
                    balance.net_outstanding = balance.total_outstanding - balance.open_credit_notes;

                    $scope.balance = balance;
                    $scope.loaded.balance = true;
                    parseChasing($scope.customer, $scope.chasingCadence);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.showBalanceHistory = function (customer, balance) {
            $modal.open({
                templateUrl: 'accounts_receivable/views/customers/credit-balance-history.html',
                controller: 'CreditBalanceHistoryController',
                resolve: {
                    balance: function () {
                        return balance;
                    },
                    customer: function () {
                        return customer;
                    },
                },
                size: 'sm',
            });
        };

        $scope.mergeCustomer = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/merge-customer.html',
                controller: 'MergeCustomerController',
                windowClass: 'merge-customer-modal',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    customer: function () {
                        return customer;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    // reload the page
                    $window.location.reload();
                },
                function () {
                    // canceled
                },
            );
        };

        function loadNetworkProfile(customer) {
            if (!customer.network_connection) {
                $scope.loaded.networkProfile = true;
                return;
            }

            $scope.loaded.networkProfile = false;
            Network.findCustomer(
                { id: customer.network_connection },
                function (networkCustomer) {
                    $scope.networkProfile = networkCustomer;
                    $scope.loaded.networkProfile = true;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.loaded.networkProfile = true;
                },
            );
        }

        /* Transactions Card */

        $scope.setTransactionsTab = function (customer, tab) {
            $scope.transactionsTab = tab;
            $scope.transactionPage = 1;
            loadTransactionData(customer, tab);
        };

        $scope.prevTransactionPage = function (customer) {
            $scope.transactionPage--;
            loadTransactionData(customer, $scope.transactionsTab);
        };

        $scope.nextTransactionPage = function (customer) {
            $scope.transactionPage++;
            loadTransactionData(customer, $scope.transactionsTab);
        };

        function loadTransactionData(customer, tab) {
            $scope.loaded.transactions = false;

            if (tab === 'invoices') {
                loadInvoices(customer.id);
            } else if (tab === 'credit_notes') {
                loadCreditNotes(customer.id);
            } else if (tab === 'payment_links') {
                loadPaymentLinks(customer.id);
            } else if (tab === 'estimates') {
                loadEstimates(customer.id);
            } else if (tab === 'payments') {
                loadPayments(customer.id);
            }
        }

        function processLoadedTransactions(documents, headers) {
            $scope.totalTransactions = headers('X-Total-Count');
            let links = Core.parseLinkHeader(headers('Link'));

            // compute page count from pagination links
            $scope.transactionPageCount = links.last.match(/[\?\&]page=(\d+)/)[1];

            let start = ($scope.transactionPage - 1) * transactionsPerPage + 1;
            let end = start + documents.length - 1;
            $scope.transactionRange = start + '-' + end;

            $scope.transactions = documents;
            $scope.loaded.transactions = true;
        }

        /* Estimates */

        function loadPaymentLinks(id) {
            let params = {
                'filter[customer]': id,
                'filter[deleted]': '0',
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'id DESC',
                include: 'items',
            };

            PaymentLink.findAll(
                params,
                function (paymentLinks, headers) {
                    processLoadedTransactions(paymentLinks, headers);

                    angular.forEach(paymentLinks, function (paymentLink) {
                        paymentLink.price = PaymentLinkHelper.calculateTotalPrice(paymentLink);
                    });
                },
                function (result) {
                    $scope.loaded.transactions = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        /* Estimates */

        function loadEstimates(id) {
            let params = {
                'filter[customer]': id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'date DESC',
            };

            Estimate.findAll(params, processLoadedTransactions, function (result) {
                $scope.loaded.transactions = true;
                Core.showMessage(result.data.message, 'error');
            });
        }

        /* Credit Notes */

        function loadCreditNotes(id) {
            let params = {
                'filter[customer]': id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'date DESC',
            };

            CreditNote.findAll(params, processLoadedTransactions, function (result) {
                $scope.loaded.transactions = true;
                Core.showMessage(result.data.message, 'error');
            });
        }

        /* Invoices */

        function loadInvoices(id) {
            let params = {
                'filter[customer]': id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'date DESC',
            };

            Invoice.findAll(
                params,
                function (invoices, headers) {
                    processLoadedTransactions(invoices, headers);

                    $scope.outstandingMax = 0;
                    angular.forEach(invoices, function (invoice) {
                        if (invoice.balance > $scope.outstandingMax) {
                            $scope.outstandingMax = invoice.balance;
                        }
                    });
                },
                function (result) {
                    $scope.loaded.transactions = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        /* Pending Line Items */

        $scope.loadUpcomingInvoice = function (id) {
            if ($scope.loaded.upcomingInvoice || !id) {
                return;
            }

            Customer.upcomingInvoice(
                {
                    id: id,
                },
                function (invoice) {
                    $scope.loaded.upcomingInvoice = true;
                    $scope.upcomingInvoice = calculateInvoice(invoice);
                },
                function (result) {
                    $scope.loaded.upcomingInvoice = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.lineItemModal = function (customer) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-pending-line-item.html',
                controller: 'EditPendingLineItemController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    lineItem: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();
                    $scope.loaded.upcomingInvoice = false;
                    $scope.loadUpcomingInvoice(customer.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.invoiceNow = function (customer) {
            $scope.invoicing = true;

            Customer.invoice(
                {
                    id: customer.id,
                },
                {},
                function (invoice) {
                    $scope.invoicing = false;
                    $state.go('manage.invoice.view.summary', {
                        id: invoice.id,
                    });
                },
                function (result) {
                    $scope.invoicing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.editLineItem = function (customer, lineItem) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-pending-line-item.html',
                controller: 'EditPendingLineItemController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    lineItem: function () {
                        return lineItem;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();
                    $scope.loaded.upcomingInvoice = false;
                    $scope.loadUpcomingInvoice(customer.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.deleteLineItem = function (customer, line) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this pending line item?',
                callback: function (result) {
                    if (result) {
                        $scope.deletingLine = true;

                        Customer.deleteLineItem(
                            {
                                customer: customer.id,
                                id: line.id,
                            },
                            function () {
                                $scope.deletingLine = false;
                                $scope.loaded.upcomingInvoice = false;
                                $scope.loadUpcomingInvoice(customer.id);
                            },
                            function (result) {
                                $scope.deletingLine = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        /* Report */

        $scope.loadDashboard = function (id, currency) {
            if (cached[currency]) {
                loadDashboard(cached[currency]);

                return;
            }

            if (!id) {
                return;
            }

            $scope.loaded.dashboard = false;

            Dashboard.get(
                {
                    customer: id,
                    currency: currency,
                },
                function (dashboard) {
                    loadDashboard(dashboard);
                },
                function () {
                    $scope.loaded.dashboard = true;
                },
            );
        };

        $scope.dateRange = function (entry) {
            let range = {};
            let start = issuedAfter(entry);
            let end = issuedBefore(entry);
            if (start) {
                range.start = start;
            }
            if (end) {
                range.end = end;
            }

            return encodeURIComponent(angular.toJson(range));
        };

        function issuedBefore(entry) {
            if (entry.lower === -1) {
                return null;
            }

            if (entry.lower === 0) {
                return moment().format('YYYY-MM-DD');
            }

            return moment().subtract(entry.lower, 'days').format('YYYY-MM-DD');
        }

        function issuedAfter(entry) {
            if (entry.upper === null) {
                return null;
            }

            if (entry.upper === -1) {
                return moment().add(1, 'day').format('YYYY-MM-DD');
            }

            return moment().subtract(entry.upper, 'days').format('YYYY-MM-DD');
        }

        /* Customer Profiles */

        $scope.editModal = function (customer) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-customer.html',
                controller: 'EditCustomerController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return customer;
                    },
                },
            });

            modalInstance.result.then(
                function (c) {
                    LeavePageWarning.unblock();

                    angular.extend(customer, c);

                    $rootScope.modelTitle = customer.name;

                    Core.setTitle(customer.name);

                    $scope.loaded.paymentSources = false;
                    $scope.loadPaymentSources(customer.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.deleteMessage = function (customer) {
            let escapeHtml = $filter('escapeHtml');

            return (
                '<p>Are you sure you want to delete this customer?</p>' +
                '<p><strong>' +
                escapeHtml(customer.name) +
                ' <small>' +
                escapeHtml(customer.number) +
                '</small></strong></p>' +
                "<p class='text-danger'>Any associated estimates, invoices, subscriptions, and payments will be permanently deleted.</p>"
            );
        };

        $scope.editNotes = function (customer) {
            LeavePageWarning.block();
            $scope.editingNotes = true;
            $scope.newNotes = customer.notes;
        };

        $scope.cancelNotesEdit = function () {
            LeavePageWarning.unblock();
            $scope.editingNotes = false;
        };

        $scope.saveNotes = function (customer, notes) {
            $scope.saving = true;

            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    notes: notes,
                },
                function () {
                    $scope.saving = false;
                    $scope.editingNotes = false;
                    customer.notes = notes;
                    LeavePageWarning.unblock();
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.setActiveStatus = function (customer, active) {
            $scope.saving = true;

            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    active: active,
                },
                function () {
                    $scope.saving = false;
                    customer.active = active;
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        /* Network */

        function loadNetworkStatus(customerId) {
            $scope.invitedToNetwork = false;

            if (!Feature.hasFeature('network')) {
                return;
            }

            Network.invitations(
                {
                    'filter[customer]': customerId,
                },
                function (results) {
                    $scope.invitedToNetwork = false;
                    if (results.length > 0) {
                        $scope.invitedToNetwork = true;
                        $scope.invitedToNetworkEmail = results[0].email;
                    }
                },
                function () {
                    $scope.invitedToNetwork = false;
                },
            );
        }

        $scope.inviteToNetwork = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'network/views/invite.html',
                controller: 'InviteToNetworkController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    vendor: function () {
                        return null;
                    },
                    customer: function () {
                        return customer;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Your invitation has been sent', 'success');
                    $scope.invitedToNetwork = !customer.network_connection; // Customer could have been invited or existing connection assigned
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteNetworkConnection = function (customer) {
            vex.dialog.confirm({
                message:
                    'Are you sure you want to remove this customer from your network? No data or transactions will be deleted.',
                callback: function (result) {
                    if (result) {
                        Customer.edit(
                            {
                                id: customer.id,
                            },
                            {
                                network_connection: null,
                            },
                            function () {
                                customer.network_connection = null;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        /* Customer Portal */

        function loadCustomerPortalSettings() {
            $scope.customerPortalDisabled = false;
            Settings.customerPortal(settings => {
                $scope.customerPortalDisabled = !settings.enabled;
            });
        }

        $scope.signInLink = function (customer) {
            let token = BillingPortal.generateLoginToken(customer);

            return BillingPortal.loginUrl(token);
        };

        $scope.urlModal = function (url) {
            $modal.open({
                templateUrl: 'components/views/url-modal.html',
                controller: 'URLModalController',
                resolve: {
                    url: function () {
                        return url;
                    },
                    customer: function () {
                        return $scope.customer;
                    },
                },
            });
        };

        /* Statements */

        $scope.generateStatement = function (customer) {
            $modal.open({
                templateUrl: 'accounts_receivable/views/customers/generate-statement.html',
                controller: 'GenerateStatementController',
                windowClass: 'generate-statement-modal',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                },
            });
        };

        /* Consolidated Invoicing */

        $scope.consolidateInvoices = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/consolidate-invoices.html',
                controller: 'ConsolidateInvoicesController',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (consolidatedInvoice) {
                    $state.go('manage.invoice.view.summary', { id: consolidatedInvoice.id });
                },
                function () {
                    // canceled
                },
            );
        };

        /* Contacts */

        $scope.loadContacts = function (id) {
            if ($scope.loaded.contacts || !id) {
                return;
            }

            Customer.contacts(
                {
                    id: id,
                    expand: 'role',
                    sort: 'name ASC',
                },
                function (contacts) {
                    $scope.contacts = contacts;
                    $scope.loaded.contacts = true;
                },
            );
        };

        $scope.contactModal = function (model) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/customers/edit-contact.html',
                controller: 'EditContactController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return (
                            model || {
                                country: $scope.customer.country,
                                primary: true,
                            }
                        );
                    },
                    customer: function () {
                        return $scope.customer;
                    },
                },
            });

            modalInstance.result.then(
                function (contact) {
                    // merge into contacts
                    let found = false;
                    angular.forEach($scope.contacts, function (c) {
                        if (c.id == contact.id) {
                            angular.extend(c, contact);
                            found = true;
                        }
                    });

                    if (!found) {
                        $scope.contacts.push(contact);
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteContact = function (contact) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this contact?',
                callback: function (result) {
                    if (result) {
                        Customer.deleteContact(
                            {
                                id: $scope.customer.id,
                                subid: contact.id,
                            },
                            function () {
                                for (let i in $scope.contacts) {
                                    if ($scope.contacts[i].id == contact.id) {
                                        $scope.contacts.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                        );
                    }
                },
            });
        };

        /* Child Customers */

        $scope.loadChildCustomers = loadChildCustomers;

        function loadChildCustomers(id) {
            if ($scope.loaded.childCustomers || !id) {
                return;
            }

            Customer.findAll(
                {
                    'filter[parent_customer]': id,
                    per_page: 5,
                    sort: 'name ASC',
                },
                function (childCustomers, headers) {
                    $scope.childCustomers = childCustomers;
                    $scope.childCustomers.originalLength = headers('X-Total-Count');
                    $scope.loaded.childCustomers = true;
                },
            );
        }

        /* Payment Methods */

        $scope.loadPaymentSources = function (id) {
            if ($scope.loaded.paymentSources || !id) {
                return;
            }

            Customer.paymentSources(
                {
                    id: id,
                    include_hidden: true,
                },
                function (paymentSources) {
                    $scope.loaded.paymentSources = true;
                    $scope.paymentSources = [];
                    angular.forEach(paymentSources, function (paymentSource) {
                        // only display active payment methods
                        // or if it's a canceled gocardless mandate
                        paymentSource.description = PaymentDisplayHelper.format(paymentSource);
                        if (paymentSource.object === 'card') {
                            paymentSource.brand = PaymentDisplayHelper.formatCardBrand(paymentSource.brand);
                        }
                        if (
                            paymentSource.chargeable ||
                            (paymentSource.gateway === 'gocardless' && paymentSource.failure_reason)
                        ) {
                            $scope.paymentSources.push(paymentSource);
                        }
                    });
                },
            );
        };

        $scope.addPaymentSource = function (customer) {
            let makeDefault = !customer.payment_source;
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/add-payment-source.html',
                controller: 'AddPaymentSourceController',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    options: function () {
                        return {
                            makeDefault: makeDefault,
                        };
                    },
                },
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function () {
                    $scope.loaded.paymentSources = false;
                    $scope.loadPaymentSources(customer.id);
                    $scope.find(customer.id); // reload customer
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.togglePaymentSource = function (k) {
            $scope.showMorePaymentSource[k] = !$scope.showMorePaymentSource[k];
        };

        $scope.canMakeDefaultPaymentSource = function (paymentSource, customer) {
            if (!paymentSource.chargeable) {
                return false;
            }

            if ($scope.isDefaultPaymentSource(paymentSource, customer)) {
                return false;
            }

            return true;
        };

        $scope.isDefaultPaymentSource = function (paymentSource, customer) {
            return (
                customer.payment_source &&
                customer.payment_source.object == paymentSource.object &&
                customer.payment_source.id == paymentSource.id
            );
        };

        $scope.canDeletePaymentSource = function (paymentSource) {
            return paymentSource.chargeable;
        };

        $scope.verifyBankAccount = function (customer, bankAccount) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/verify-bank-account.html',
                controller: 'VerifyBankAccountController',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    bankAccount: function () {
                        return bankAccount;
                    },
                },
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function () {
                    $scope.loaded.paymentSources = false;
                    $scope.loadPaymentSources(customer.id);
                    $scope.find(customer.id); // reload customer
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.makePaymentSourceDefault = function (customer, paymentSource) {
            $scope.makingDefault = true;

            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    default_source_type: paymentSource.object,
                    default_source_id: paymentSource.id,
                },
                function () {
                    $scope.makingDefault = false;
                    customer.payment_source = paymentSource;
                    LeavePageWarning.unblock();
                },
                function (result) {
                    $scope.makingDefault = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.deletePaymentSource = function (customer, paymentSource, k) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this payment method?',
                callback: function (result) {
                    if (result && paymentSource.object == 'card') {
                        $scope.deletingSource = true;
                        Customer.deleteCard(
                            {
                                id: customer.id,
                                subid: paymentSource.id,
                            },
                            function () {
                                $scope.deletingSource = false;
                                deleteSource(paymentSource);
                                $scope.find(customer.id); // reload customer
                                $scope.showMorePaymentSource[k] = false;
                            },
                            function (result) {
                                $scope.deletingSource = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    } else if (result && paymentSource.object == 'bank_account') {
                        $scope.deletingSource = true;
                        Customer.deleteBankAccount(
                            {
                                id: customer.id,
                                subid: paymentSource.id,
                            },
                            function () {
                                $scope.deletingSource = false;
                                deleteSource(paymentSource);
                                $scope.find(customer.id); // reload customer
                                $scope.showMorePaymentSource[k] = false;
                            },
                            function (result) {
                                $scope.deletingSource = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.reinstateMandate = function (customer, paymentSource) {
            vex.dialog.confirm({
                message: 'Are you sure you want to reinstate this mandate?',
                callback: function (result) {
                    if (result) {
                        $scope.reinstatingMandate = true;
                        Customer.reinstateMandate(
                            {
                                id: customer.id,
                                subid: paymentSource.id,
                            },
                            function () {
                                $scope.reinstatingMandate = false;
                                Core.flashMessage(
                                    'Your request has been submitted to reinstate this mandate has been sent.',
                                    'success',
                                );
                                paymentSource.verified = false;
                                paymentSource.chargeable = true;
                                paymentSource.failure_reason = null;
                            },
                            function (result) {
                                $scope.reinstatingMandate = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.bankAccountCanBeVerified = function (paymentSource) {
            return (
                paymentSource.object == 'bank_account' &&
                !paymentSource.verified &&
                paymentSource.gateway != 'gocardless' &&
                !paymentSource.gateway_setup_intent
            );
        };

        $scope.mandateCanBeReinstated = function (paymentSource) {
            return paymentSource.gateway == 'gocardless' && !paymentSource.chargeable;
        };

        /* Payments */

        function loadPayments(id) {
            let params = {
                'filter[customer]': id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'date DESC',
            };

            Payment.findAll(
                params,
                function (payments, headers) {
                    if (payments.length > 0) {
                        processLoadedTransactions(payments, headers);
                    } else {
                        Transaction.findAll(
                            params,
                            function (transactions) {
                                if (transactions.length > 0) {
                                    $scope.linkToPayments = false;
                                }

                                let payments = [];
                                angular.forEach(transactions, function (transaction) {
                                    // Convert into a payment object for presentation
                                    payments.push({
                                        id: transaction.id,
                                        object: transaction.object,
                                        method: transaction.method,
                                        date: transaction.date,
                                        currency: transaction.currency,
                                        amount:
                                            transaction.type === 'refund' ? -transaction.amount : transaction.amount,
                                        charge: null,
                                        balance: 0,
                                        source: '',
                                        reference: transaction.type !== 'charge' ? transaction.gateway_id : null,
                                    });
                                });

                                processLoadedTransactions(payments, headers);
                            },
                            function (result) {
                                $scope.loaded.transactions = true;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
                function (result) {
                    $scope.loaded.transactions = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        $scope.paymentModal = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/receive-payment.html',
                controller: 'ReceivePaymentController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    options: function () {
                        return {
                            customer: customer,
                            currency: $scope.currency,
                        };
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    // reload balance
                    if ($scope.loaded.balance) {
                        $scope.loaded.balance = false;
                        $scope.loadBalance(customer.id, $scope.currency);
                    }

                    // reload transactions
                    loadTransactionData(customer, $scope.transactionsTab);

                    // reload payment methods
                    if ($scope.loaded.paymentSources) {
                        $scope.loaded.paymentSources = false;
                        $scope.loadPaymentSources(customer.id);
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.creditModal = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/add-credit-balance-adjustment.html',
                controller: 'AddCreditBalanceAdjustmentController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    currency: function () {
                        // attempt to add credits in customer's main currency if possible
                        if ($scope.balance) {
                            return $scope.balance.currency;
                        }

                        return $scope.company.currency;
                    },
                    amount: function () {
                        return null;
                    },
                    customer: function () {
                        return customer;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    // reload balance
                    if ($scope.loaded.balance) {
                        $scope.loaded.balance = false;
                        $scope.loadBalance(customer.id, $scope.currency);
                    }

                    // reload transactions
                    loadTransactionData(customer, $scope.transactionsTab);
                },
                function () {
                    // canceled
                },
            );
        };

        /* EmailThreads */

        $scope.loadEmailThreads = function (id) {
            if ($scope.loaded.threads || !id) {
                return;
            }

            Settings.accountsReceivable(function (settings) {
                $scope.inboxId = settings.inbox;

                EmailThread.findAll(
                    {
                        inboxid: settings.inbox,
                        'filter[customer]': id,
                        per_page: 3,
                        paginate: 'none',
                    },
                    function (result) {
                        $scope.threads = result;
                        $scope.loaded.threads = true;
                    },
                    function (result) {
                        $scope.loaded.threads = true;
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });
        };

        /* Subscriptions */

        $scope.prevSubscriptionPage = function () {
            $scope.subscriptionPage--;
            $scope.loaded.subscriptions = false;
            $scope.loadSubscriptions($scope.customer.id);
        };

        $scope.nextSubscriptionPage = function () {
            $scope.subscriptionPage++;
            $scope.loaded.subscriptions = false;
            $scope.loadSubscriptions($scope.customer.id);
        };

        $scope.loadSubscriptions = function (id) {
            // don't load subscriptions unless enabled
            if (!Feature.hasFeature('subscriptions')) {
                $scope.loaded.subscriptions = true;
                return;
            }

            if ($scope.loaded.subscriptions || !id) {
                return;
            }

            let perPage = 5;

            Subscription.findAll(
                {
                    'filter[customer]': id,
                    include: 'ship_to',
                    exclude: '',
                    expand: 'plan,addons.plan,addons.catalog_item',
                    per_page: perPage,
                    page: $scope.subscriptionPage,
                },
                function (subscriptions, headers) {
                    $scope.subscriptions = subscriptions;
                    $scope.loaded.subscriptions = true;

                    $scope.totalSubscriptions = headers('X-Total-Count');
                    let links = Core.parseLinkHeader(headers('Link'));

                    // compute page count from pagination links
                    $scope.subscriptionPageCount = links.last.match(/[\?\&]page=(\d+)/)[1];

                    let start = ($scope.subscriptionPage - 1) * perPage + 1;
                    let end = start + subscriptions.length - 1;
                    $scope.subscriptionRange = start + '-' + end;
                },
                function (result) {
                    $scope.loaded.subscriptions = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.addSubscriptionModal = function () {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/new-subscription.html',
                controller: 'NewSubscriptionController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    customer: function () {
                        return $scope.customer;
                    },
                },
            });

            modalInstance.result.then(
                function (subscription) {
                    LeavePageWarning.unblock();

                    $state.go('manage.subscription.view.summary', {
                        id: subscription.id,
                    });
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editSubscriptionModal = function (subscription, customer) {
            LeavePageWarning.block();
            subscription.customer = customer;

            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/edit-subscription.html',
                controller: 'EditSubscriptionController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    LeavePageWarning.unblock();
                    angular.extend(subscription, result);

                    // reload transactions, upcoming invoice, and balance
                    // these are all objects that could have been modified
                    loadTransactionData(customer, $scope.transactionsTab);
                    $scope.loaded.upcomingInvoice = false;
                    $scope.loadUpcomingInvoice($scope.customer.id);
                    $scope.loaded.balance = false;
                    $scope.loadBalance($scope.customer.id, $scope.currency);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.cancelSubscription = function (subscription) {
            const modalInstance = $modal.open({
                templateUrl: 'subscriptions/views/cancel-subscription.html',
                controller: 'CancelSubscriptionController',
                resolve: {
                    subscription: function () {
                        return subscription;
                    },
                },
            });

            modalInstance.result.then(
                function (updated) {
                    angular.extend(subscription, updated);

                    if (subscription.status === 'canceled') {
                        Core.flashMessage('Your subscription has been canceled', 'success');
                        for (let i in $scope.subscriptions) {
                            if ($scope.subscriptions[i].id == subscription.id) {
                                $scope.subscriptions.splice(i, 1);
                                break;
                            }
                        }

                        // reload upcoming invoice
                        $scope.loaded.upcomingInvoice = false;
                        $scope.loadUpcomingInvoice($scope.customer.id);
                    } else {
                        Core.flashMessage('Your subscription has been updated', 'success');
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.hasMetadata = function (object) {
            return Object.keys(object.metadata).length > 0;
        };

        $scope.assignSignUpPageModal = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'sign_up_pages/assign-sign-up-page.html',
                controller: 'AssignSignUpPageController',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (updated) {
                    Core.flashMessage(
                        "The sign up page has been updated. You can access this customer's sign up URL by going to Actions > Sign Up URL.",
                        'success',
                    );
                    angular.extend(customer, updated);
                },
                function () {
                    // canceled
                },
            );
        };

        /* Collections */

        $scope.loadCollectionActivity = function (id) {
            if ($scope.loaded.collectionActivity || !id) {
                return;
            }

            Customer.collectionActivity(
                {
                    id: id,
                },
                function (activity) {
                    $scope.collectionActivity = activity;

                    angular.forEach(activity.steps, function (step) {
                        step.complete = step.successful && (!step.to_do_task || step.to_do_task.complete);
                        step.executed = !!step.last_run;
                    });

                    $scope.loaded.collectionActivity = true;
                },
                function (result) {
                    $scope.loaded.collectionActivity = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.loadCollectionNotes = function (id) {
            if ($scope.loaded.collectionNotes || !id) {
                return;
            }

            Customer.notes(
                {
                    id: id,
                },
                function (notes) {
                    angular.forEach(notes, function (note) {
                        note._draft = note.notes;
                        if (note.user) {
                            note.name = note.user.first_name + ' ' + note.user.last_name;
                        }
                    });

                    $scope.collectionNotes = notes;
                    $scope.loaded.collectionNotes = true;
                },
                function (result) {
                    $scope.loaded.collectionNotes = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.addPromiseToPay = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'collections/views/add-promise-to-pay.html',
                controller: 'AddPromiseToPayController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    customer: function () {
                        return customer;
                    },
                },
                size: 'lg',
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Your promise-to-pay for ' + customer.name + ' has been added', 'success');
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.newNote = function () {
            $scope.newNoteForm = true;
            LeavePageWarning.block();
        };

        $scope.cancelNote = function () {
            $scope.newNoteForm = false;
            LeavePageWarning.unblock();
        };

        $scope.addNote = function (customer, note) {
            $scope.creatingNote = true;
            Note.create(
                {
                    customer_id: customer.id,
                    notes: note.notes,
                },
                function (_note) {
                    LeavePageWarning.unblock();
                    $scope.creatingNote = false;

                    if (_note.user) {
                        _note.name = _note.user.first_name + ' ' + _note.user.last_name;
                    }

                    _note._draft = _note.notes;
                    $scope.collectionNotes.splice(0, 0, _note);
                    $scope.newNote.notes = '';
                    $scope.newNoteForm = false;
                },
                function (result) {
                    $scope.creatingNote = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.editNote = function (note) {
            note._edit = true;
            LeavePageWarning.block();
        };

        $scope.cancelNoteEdit = function (note) {
            note._edit = false;
            LeavePageWarning.unblock();
        };

        $scope.saveNoteEdit = function (note) {
            $scope.editingNote = true;
            Note.edit(
                {
                    id: note.id,
                },
                {
                    notes: note._draft,
                },
                function () {
                    LeavePageWarning.unblock();
                    $scope.editingNote = false;
                    note.notes = note._draft;
                    note._edit = false;
                },
                function (result) {
                    $scope.editingNote = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.deleteNote = function (note, i) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this note?',
                callback: function (result) {
                    if (result) {
                        $scope.deletingNote = true;

                        Note.delete(
                            {
                                id: note.id,
                            },
                            function () {
                                $scope.deletingNote = false;
                                $scope.collectionNotes.splice(i, 1);
                            },
                            function (result) {
                                $scope.deletingNote = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.showCompletedTasks = function (customer, completed) {
            $scope.showingCompletedTasks = completed;
            $scope.loaded.tasks = false;
            $scope.loadTasks(customer.id);
        };

        $scope.loadTasks = function (id) {
            if ($scope.loaded.tasks || !id) {
                return;
            }

            Customer.tasks(
                {
                    id: id,
                    'filter[complete]': $scope.showingCompletedTasks ? '1' : '0',
                    expand: 'user_id,completed_by_user_id',
                    sort: 'due_date ASC',
                },
                function (tasks) {
                    angular.forEach(tasks, function (task) {
                        if (task.user_id) {
                            task.user_id.name = task.user_id.first_name + ' ' + task.user_id.last_name;
                        }

                        if (task.completed_by_user_id) {
                            task.completed_by_user_id.name =
                                task.completed_by_user_id.first_name + ' ' + task.completed_by_user_id.last_name;
                        }
                    });

                    $scope.tasks = tasks;
                    $scope.loaded.tasks = true;
                },
                function (result) {
                    $scope.loaded.tasks = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.addTask = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'collections/views/tasks/edit.html',
                controller: 'EditTaskController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    task: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (_task) {
                    Core.flashMessage('Your task for ' + customer.name + ' has been added', 'success');
                    if (_task.user) {
                        _task.user.name = _task.user.first_name + ' ' + _task.user.last_name;
                    }

                    $scope.tasks.splice(0, 0, _task);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.editTask = function (task) {
            const modalInstance = $modal.open({
                templateUrl: 'collections/views/tasks/edit.html',
                controller: 'EditTaskController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    customer: function () {
                        return false;
                    },
                    task: function () {
                        return task;
                    },
                },
            });

            modalInstance.result.then(
                function (_task) {
                    Core.flashMessage('Your task has been updated', 'success');
                    angular.extend(task, _task);
                    if (task.user) {
                        task.user.name = task.user.first_name + ' ' + task.user.last_name;
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.markComplete = function (task, complete) {
            $scope.saving = true;
            $scope.error = null;
            Task.edit(
                {
                    id: task.id,
                },
                {
                    complete: complete,
                    completed_by_user_id: CurrentUser.profile.id,
                },
                function (_task) {
                    $scope.saving = false;
                    angular.extend(task, _task);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.saving = false;
                },
            );
        };

        $scope.deleteTask = function (task, i) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this task?',
                callback: function (result) {
                    if (result) {
                        $scope.deletingTask = true;

                        Task.delete(
                            {
                                id: task.id,
                            },
                            function () {
                                $scope.deletingTask = false;
                                $scope.tasks.splice(i, 1);
                            },
                            function (result) {
                                $scope.deletingTask = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.selectChasingCadence = function (customer, cadence) {
            const modalInstance = $modal.open({
                templateUrl: 'collections/views/assign-chasing-cadence.html',
                controller: 'AssignChasingCadenceController',
                resolve: {
                    customer: function () {
                        return customer;
                    },
                    cadence: function () {
                        return cadence;
                    },
                },
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (updated) {
                    Core.flashMessage("The customer's chasing settings have been updated.", 'success');
                    angular.extend(customer, updated);
                    $scope.chasingCadence = updated.chasing_cadence;
                    parseChasing(customer, $scope.chasingCadence);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.pauseChasingCadence = function (customer) {
            $scope.saving = true;
            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    chase: false,
                    next_chase_step: null,
                },
                function () {
                    $scope.saving = false;
                    customer.next_chase_step = null;
                    customer.chase = false;
                    parseChasing(customer, $scope.chasingCadence);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.chasingStepDescription = function (step) {
            let parts = step.schedule.split(':');
            let type = parts[0];
            let value = '';
            if (parts.length > 1) {
                let args = parts[1].split(',');
                value = parseInt(args[0]);
            }

            if (type === 'age') {
                return 'Age: ' + value + ' day' + (value != 1 ? 's' : '');
            } else if (type === 'past_due_age') {
                return 'Past Due Age: ' + value + ' day' + (value != 1 ? 's' : '');
            }
        };

        $scope.stepWasCompleted = function (step) {
            return getActivityProperty(step, 'complete');
        };

        $scope.stepWasSkipped = function (step, index) {
            let activity = getCollectionActivity(step);
            if (!activity) {
                return false;
            }

            if (activity.executed) {
                return false;
            }

            // if the next chase step is false then that means we are at the end of the schedule
            if ($scope.atChasingEnd) {
                return true;
            }

            return index < $scope.nextChaseStepIndex;
        };

        $scope.lastRunTime = function (step) {
            return getActivityProperty(step, 'last_run');
        };

        $scope.stepWasSuccessful = function (step) {
            return getActivityProperty(step, 'successful');
        };

        $scope.stepErrorMessage = function (step) {
            return getActivityProperty(step, 'message');
        };

        $scope.stepHasOpenTask = function (step) {
            let task = getActivityProperty(step, 'to_do_task');

            return task ? !task.complete : false;
        };

        function getCollectionActivity(step) {
            if (!$scope.loaded.collectionActivity) {
                return null;
            }

            if (typeof $scope.collectionActivity.steps[step.id] === 'undefined') {
                return null;
            }

            return $scope.collectionActivity.steps[step.id];
        }

        function getActivityProperty(step, prop) {
            let activity = getCollectionActivity(step);

            return activity ? activity[prop] : null;
        }

        function parseChasing(customer, cadence) {
            if (!cadence || !$scope.balance) {
                return;
            }

            if (cadence.next_run > 0) {
                cadence.next_run = moment.unix(cadence.next_run).calendar();
            }

            // Determine if we are at the beginning or end
            // of the schedule. We are at the beginning
            // if there is no next step scheduled and the
            // customer is paid in full. We are at the end
            // if the customer owes money and does not have
            // a next step.
            $scope.nextChaseStep = false;
            $scope.nextChaseStepIndex = -1;
            $scope.atChasingBegin = !$scope.balance.net_outstanding;
            $scope.atChasingEnd = false;

            if ($scope.atChasingBegin || !customer.chase || cadence.paused) {
                return;
            }

            if (customer.next_chase_step) {
                $scope.nextChaseStep = customer.next_chase_step;

                // find the index of the next chase step
                for (let i = 0; i < cadence.steps.length; i++) {
                    if (cadence.steps[i].id == customer.next_chase_step) {
                        $scope.nextChaseStepIndex = i;
                        break;
                    }
                }
            } else {
                $scope.atChasingEnd = true;
            }
        }

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Customer');
        $scope.loadBalance($scope.modelId, $scope.currency);

        let cached = {};

        function calculateInvoice(invoice) {
            $scope.upcomingInvoiceTotals = InvoiceCalculator.calculateSubtotalLines(invoice);

            // we do not want to show the bill now button
            // if there are no pending line items
            $scope.hasPendingLineItems = false;
            angular.forEach(invoice.items, function (item) {
                if (item.id) {
                    $scope.hasPendingLineItems = true;
                }

                item.hasMetadata = Object.keys(item.metadata).length > 0;
            });

            return invoice;
        }

        function loadDashboard(dashboard) {
            cached[dashboard.currency] = dashboard;
            $scope.dashboard = angular.copy(dashboard);

            // Max Aging
            $scope.agingMax = 0;
            $scope.aging = [];
            let daySuffix = dashboard.aging_date === 'due_date' ? 'Days Past Due' : 'Days Old';
            let numBuckets = Object.keys($scope.dashboard.aging).length;
            let i = 0;
            angular.forEach($scope.dashboard.aging, function (row) {
                if (row.amount > $scope.agingMax) {
                    $scope.agingMax = row.amount;
                }

                let upper = null;
                if (i < numBuckets - 1) {
                    upper = parseInt($scope.dashboard.aging[i + 1].age_lower) - 1;
                }

                // severity is a value 1 (lowest) - 6 (highest)
                // this maps an arbitrary number of aging buckets (not always 6)
                // onto this severity range
                let severity = Math.ceil(((i + 1) * 6) / numBuckets);

                let title;
                if (row.age_lower == -1) {
                    title = 'Current';
                } else if (upper) {
                    title = row.age_lower + ' - ' + upper + ' ' + daySuffix;
                } else {
                    title = row.age_lower + '+ ' + daySuffix;
                }

                $scope.aging.push({
                    lower: parseInt(row.age_lower),
                    upper: upper,
                    amount: row.amount,
                    severity: severity,
                    title: title,
                    count: row.count,
                });
                i++;
            });

            // Max Outstanding Balance
            $scope.outstandingMax = 0;
            angular.forEach($scope.dashboard.outstanding, function (invoice) {
                if (invoice.balance > $scope.outstandingMax) {
                    $scope.outstandingMax = invoice.balance;
                }

                Invoice.parseFromResponse(invoice);
            });

            $scope.loaded.dashboard = true;
        }

        function deleteSource(paymentSource) {
            for (let i in $scope.paymentSources) {
                let paymentSource2 = $scope.paymentSources[i];
                if (paymentSource2.id == paymentSource.id && paymentSource2.object == paymentSource.object) {
                    $scope.paymentSources.splice(i, 1);
                    break;
                }
            }
        }

        function determineSyncStatus(customer) {
            Customer.accountingSyncStatus(
                {
                    id: customer.id,
                },
                function (syncStatus) {
                    $scope.syncedObject = syncStatus;

                    if (syncStatus.last_synced) {
                        let lastSynced = moment.unix(syncStatus.last_synced);
                        $scope.syncedObject.last_synced = lastSynced.format('dddd, MMM Do YYYY, h:mm a');
                        $scope.syncedObject.last_synced_ago = lastSynced.fromNow();
                    }
                },
            );
        }

        /* Attachments */
        function loadAttachments(id) {
            if ($scope.loaded.attachments) {
                return;
            }

            Customer.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                },
            );
        }
    }
})();
