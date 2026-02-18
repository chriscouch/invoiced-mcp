/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewInvoiceController', ViewInvoiceController);

    ViewInvoiceController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        'Invoice',
        'Transaction',
        'InvoiceDistribution',
        'Note',
        'Settings',
        'Money',
        'InvoiceCalculator',
        'Core',
        'LeavePageWarning',
        'selectedCompany',
        'InvoicedConfig',
        'BrowsingHistory',
        'Permission',
        'DocumentControllerHelper',
        'File',
        'Feature',
    ];

    function ViewInvoiceController(
        $scope,
        $controller,
        $rootScope,
        $modal,
        $filter,
        Invoice,
        Transaction,
        InvoiceDistribution,
        Note,
        Settings,
        Money,
        InvoiceCalculator,
        Core,
        LeavePageWarning,
        selectedCompany,
        InvoicedConfig,
        BrowsingHistory,
        Permission,
        DocumentControllerHelper,
        File,
        Feature,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Invoice;
        $scope.modelTitleSingular = 'Invoice';
        $scope.modelObjectType = 'invoice';
        $scope.hasTags = Feature.hasFeature('invoice_tags');
        $scope.hasInvoiceDistributions = Feature.hasFeature('invoice_distributions');
        $scope.hasSendingPermissions = Permission.hasSomePermissions([
            'text_messages.send',
            'letters.send',
            'emails.send',
        ]);

        //
        // Presets
        //

        $scope.payments = [];
        $scope.followUpNotes = [];
        $scope.lineItemPage = 1;
        $scope.showLineDetails = {};
        $scope.distributions = [];
        $scope.newNoteForm = false;
        $scope.newNote = { notes: '' };
        $scope.chaseState = [];
        $scope.delivery = {
            chase_schedule: [],
        };

        let actionItems = [];
        $scope.tab = 'summary';

        $scope.newComment = {
            attachments: [],
            mark_resolved: true,
        };

        $scope.avatarOptions = {
            height: 35,
            width: 35,
        };

        $scope.hidePaymentPlan = true;
        $scope.$watchGroup(['invoice', 'paymentPlan'], function (newVal) {
            let invoice = newVal[0];
            if (!invoice) {
                return;
            }
            let paymentPlan = newVal[1];
            let hasExpiringDiscount = function (item) {
                return item.expires !== null;
            };
            $scope.hidePaymentPlan = Boolean(
                invoice.paid ||
                    invoice.closed ||
                    paymentPlan ||
                    invoice.status === 'pending' ||
                    invoice.discounts.some(hasExpiringDiscount),
            );
        });

        $scope.editable = false;

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer,subscription.plan';
            findParams.include = 'expected_payment_date';
            findParams.exclude = 'items';

            if ($scope.hasTags) {
                findParams.include += ',tags';
            }
        };

        $scope.postFind = function (invoice) {
            $scope.invoice = invoice;

            $scope.customerName = invoice.customer.name;

            $rootScope.modelTitle = $scope.invoice.number;
            Core.setTitle($rootScope.modelTitle);

            if (invoice.expected_payment_date && invoice.expected_payment_date.date) {
                $scope.promiseToPay = invoice.expected_payment_date;
            }

            // calculate the invoice subtotal lines
            $scope.totals = InvoiceCalculator.calculateSubtotalLines(invoice);
            $scope.showCountry = $scope.invoice.customer.country !== $scope.company.country;

            // prefill the email reply box
            $scope.prefillEmailReply = {
                network_connection: invoice.customer.network_connection,
                subject: 'Invoice # ' + invoice.number,
            };

            // load related data
            loadDelivery(invoice.id);
            loadChaseState(invoice.id);
            loadLineItems(invoice.id);
            loadAttachments(invoice.id);
            loadPayments(invoice.id);
            loadDistributions(invoice.id);
            loadNotes(invoice.id);
            loadPaymentPlan(invoice.id);
            loadSettings();

            // compute the action items
            computeActionItems();

            determineSyncStatus(invoice);

            BrowsingHistory.push({
                id: invoice.id,
                type: 'invoice',
                title: invoice.number,
            });

            return $scope.invoice;
        };

        $scope.issue = function (invoice) {
            $scope.issuing = true;

            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    draft: false,
                },
                function () {
                    $scope.issuing = false;

                    // reload the invoice
                    $scope.find(invoice.id);
                },
                function (result) {
                    $scope.issuing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.markSent = function (sent, invoice) {
            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    sent: sent,
                },
                function (_invoice) {
                    invoice.status = _invoice.status;
                    computeActionItems();
                    Core.flashMessage('Invoice # ' + invoice.number + ' was marked as sent', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.setNeedsAttention = function (value, invoice) {
            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    needs_attention: value,
                },
                function (_invoice) {
                    invoice.needs_attention = _invoice.needs_attention;
                    computeActionItems();
                    Core.flashMessage('Invoice # ' + invoice.number + ' was updated', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.setChase = function (chase, invoice) {
            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    chase: chase,
                },
                function (_invoice) {
                    invoice.chase = chase;
                    invoice.next_chase_on = _invoice.next_chase_on;
                    computeActionItems();
                    Core.flashMessage(
                        'Invoice # ' + invoice.number + ' had chasing ' + (chase ? 'enabled' : 'disabled'),
                        'success',
                    );
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.setAutoPay = function (enabled, invoice, paymentPlan) {
            let params = {
                autopay: enabled,
            };

            if (!enabled && !paymentPlan) {
                params.payment_terms = '';
            }

            Invoice.edit(
                {
                    id: invoice.id,
                },
                params,
                function (_invoice) {
                    invoice.autopay = _invoice.autopay;
                    invoice.payment_terms = _invoice.payment_terms;
                    invoice.due_date = _invoice.due_date;
                    invoice.next_payment_attempt = _invoice.next_payment_attempt;
                    computeActionItems();
                    Core.flashMessage(
                        'Invoice # ' + invoice.number + ' had AutoPay ' + (enabled ? 'enabled' : 'disabled'),
                        'success',
                    );
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.scheduleChasing = function (invoice) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/schedule-chasing.html',
                controller: 'ScheduleChasingController',
                resolve: {
                    invoice: function () {
                        return invoice;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (_invoice) {
                    angular.extend(invoice, _invoice);
                    computeActionItems();
                    Core.flashMessage('Invoice # ' + invoice.number + ' was scheduled for chasing', 'success');
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.setClosed = function (closed, invoice) {
            Invoice.edit(
                {
                    id: invoice.id,
                },
                {
                    closed: closed,
                },
                function (_invoice) {
                    invoice.closed = _invoice.closed;
                    invoice.status = _invoice.status;
                    invoice.payment_url = _invoice.payment_url;
                    computeActionItems();
                    Core.flashMessage(
                        'Invoice # ' + invoice.number + ' was ' + (closed ? 'closed' : 'reopened'),
                        'success',
                    );
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.deleteMessage = function (invoice, action) {
            let escapeHtml = $filter('escapeHtml');
            action = action || 'delete';
            let customerName = invoice.customer.name || invoice.customerName;
            return (
                '<p>Are you sure you want to ' +
                action +
                ' this invoice?</p>' +
                '<p><strong>' +
                escapeHtml(invoice.name) +
                ' <small>' +
                escapeHtml(invoice.number) +
                '</small></strong><br/>' +
                'Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Total: ' +
                Money.currencyFormat(invoice.total, invoice.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(invoice.date) +
                '</p>'
            );
        };

        $scope.void = function (invoice) {
            DocumentControllerHelper.void($scope, invoice, function (original) {
                invoice.subscription = original.subscription;
                computeActionItems();
            });
        };

        $scope.setBadDebt = function (invoice) {
            Invoice.bad_debt(
                {
                    id: invoice.id,
                },
                function () {
                    reloadInvoice($scope.invoice);
                    $scope.loaded.payments = false;
                    loadPayments($scope.invoice.id);
                    Core.flashMessage('Invoice # ' + invoice.number + ' was written of as a bad debt', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.hasActionItem = function (k) {
            return actionItems.indexOf(k) !== -1;
        };

        /* Payment Plans */

        $scope.addPaymentPlan = function (invoice) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'payment_plans/views/add.html',
                controller: 'AddPaymentPlanController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    invoice: function () {
                        return invoice;
                    },
                },
            });

            modalInstance.result.then(
                function (paymentPlan) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('A payment plan was added for invoice # ' + invoice.number, 'success');

                    $scope.paymentPlan = paymentPlan;
                    reloadInvoice(invoice);
                    loadChaseState(invoice.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editPaymentPlan = function (invoice, paymentPlan) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'payment_plans/views/edit.html',
                controller: 'EditPaymentPlanController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    invoice: function () {
                        return invoice;
                    },
                    paymentPlan: function () {
                        return paymentPlan;
                    },
                },
            });

            modalInstance.result.then(
                function (paymentPlan) {
                    LeavePageWarning.unblock();
                    Core.flashMessage('The payment plan was modified for invoice # ' + invoice.number, 'success');

                    $scope.paymentPlan = paymentPlan;
                    reloadInvoice(invoice);
                    loadChaseState(invoice.id);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.viewApproval = function (paymentPlan) {
            $modal.open({
                templateUrl: 'payment_plans/views/approval-details.html',
                controller: 'PaymentPlanApprovalDetailsController',
                resolve: {
                    approval: function () {
                        return paymentPlan.approval;
                    },
                },
                size: 'sm',
            });
        };

        $scope.cancelPaymentPlan = function (invoice) {
            vex.dialog.confirm({
                message: 'Are you sure you want to cancel this payment plan?',
                callback: function (result) {
                    if (result) {
                        $scope.deletingPaymentPlan = true;
                        Invoice.cancelPaymentPlan(
                            {
                                id: invoice.id,
                            },
                            function () {
                                $scope.paymentPlan = false;
                                $scope.deletingPaymentPlan = false;

                                reloadInvoice(invoice);
                                loadChaseState(invoice.id);

                                Core.flashMessage(
                                    'The payment plan for invoice # ' + invoice.number + ' has been canceled',
                                    'success',
                                );
                            },
                            function (result) {
                                $scope.deletingPaymentPlan = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        /* Payments */

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
                            makeDefault: true,
                        };
                    },
                },
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (paymentSource) {
                    Core.flashMessage('A payment method was added for ' + customer.name, 'success');
                    $scope.invoice.customer.payment_source = paymentSource;
                    reloadInvoice($scope.invoice);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.paymentModal = function (invoice) {
            let customer =
                typeof invoice.customer == 'object'
                    ? invoice.customer
                    : {
                          id: invoice.customer,
                          name: invoice.customerName,
                      };

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
                            preselected: [invoice],
                            currency: invoice.currency,
                            amount: invoice.balance,
                        };
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Payment for ' + invoice.number + ' has been recorded', 'success');
                    if ($scope.paymentPlan) {
                        // reload payment plan
                        $scope.loaded.payment_plan = false;
                    }
                    $scope.find(invoice.id, function (newInvoice) {
                        angular.extend(invoice, newInvoice);
                    });

                    // reload payments
                    $scope.loaded.payments = false;
                    loadPayments(invoice.id);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.setAutoPayAttempt = function (invoice) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/set-autopay-attempt.html',
                controller: 'SetAutoPayAttemptController',
                resolve: {
                    invoice: function () {
                        return invoice;
                    },
                },
                size: 'sm',
            });

            modalInstance.result.then(
                function (date) {
                    Core.flashMessage(
                        'The AutoPay date for invoice # ' + invoice.number + ' has been updated',
                        'success',
                    );
                    invoice.next_payment_attempt = date;
                    computeActionItems();
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.toggleScheduledTask = function (distribution) {
            InvoiceDistribution.edit(
                {
                    id: distribution.id,
                },
                {
                    enabled: !distribution.enabled,
                },
                function (_distribution) {
                    angular.extend(distribution, _distribution);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
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

        $scope.addNote = function (invoice, note) {
            $scope.creatingNote = true;
            Note.create(
                {
                    invoice_id: invoice.id,
                    notes: note.notes,
                },
                function (_note) {
                    LeavePageWarning.unblock();
                    $scope.creatingNote = false;

                    if (_note.user) {
                        _note.name = _note.user.first_name + ' ' + _note.user.last_name;
                    }

                    _note._draft = _note.notes;
                    $scope.followUpNotes.splice(0, 0, _note);
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

        $scope.deleteNote = function (note) {
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

                                for (let i in $scope.followUpNotes) {
                                    if ($scope.followUpNotes[i].id == note.id) {
                                        $scope.followUpNotes.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function () {
                                $scope.deletingNote = false;
                            },
                        );
                    }
                },
            });
        };

        $scope.setPromiseToPay = function (invoice, promiseToPay) {
            promiseToPay = promiseToPay || null;
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/set-expected-payment-date.html',
                controller: 'SetExpectedPaymentDateController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    invoice: function () {
                        return invoice;
                    },
                    expectedPaymentDate: function () {
                        return promiseToPay;
                    },
                },
            });

            modalInstance.result.then(
                function (_promiseToPay) {
                    Core.flashMessage(
                        'The expected payment date for invoice # ' + invoice.number + ' have been updated',
                        'success',
                    );
                    $scope.promiseToPay = _promiseToPay;
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.pay = function (invoice) {
            $scope.paying = true;

            Invoice.pay(
                {
                    id: invoice.id,
                },
                {},
                function (_invoice) {
                    $scope.paying = false;

                    Core.flashMessage(
                        'The AutoPay attempt for invoice # ' +
                            invoice.number +
                            ' succeeded. It should be available in the dashboard shortly.',
                        'success',
                    );

                    // don't merge these properties because they have already been expanded
                    delete _invoice.customer;
                    delete _invoice.subscription;

                    angular.extend(invoice, _invoice);

                    // parse the invoice response
                    $scope.postFind(invoice);

                    // reload payments
                    $scope.loaded.payments = false;
                    loadPayments(invoice.id);

                    // reload any payment plan
                    if ($scope.paymentPlan) {
                        $scope.loaded.payment_plan = false;
                        loadPaymentPlan(invoice.id);
                    }
                },
                function (result) {
                    $scope.paying = false;
                    Core.showMessage('Collection attempt failed with reason: ' + result.data.message, 'error');
                },
            );
        };

        /* Sending */

        $scope.sendModal = function (invoice) {
            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document.html',
                controller: 'SendDocumentController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                windowClass: 'send-document-modal',
                resolve: {
                    model: function () {
                        return $scope.model;
                    },
                    _document: function () {
                        return invoice;
                    },
                    paymentPlan: function () {
                        return $scope.paymentPlan;
                    },
                    customerId: function () {
                        return invoice.customer.id;
                    },
                    sendOptions: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    Core.flashMessage(result, 'success');
                    if (invoice.status === 'not_sent') {
                        invoice.status = 'sent';
                    }
                    computeActionItems();
                },
                function () {
                    // canceled
                },
            );
        };

        /* Billing Portal */

        $scope.urlModal = function (url) {
            $modal.open({
                templateUrl: 'components/views/url-modal.html',
                controller: 'URLModalController',
                resolve: {
                    url: function () {
                        return url;
                    },
                    customer: function () {
                        return null;
                    },
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Invoice');

        function reloadInvoice(invoice) {
            let original = angular.copy(invoice);
            $scope.loading = true;
            Invoice.find(
                {
                    id: invoice.id,
                },
                function (updated) {
                    $scope.loading = false;

                    angular.extend(invoice, updated);

                    // restore expanded objects
                    invoice.customer = original.customer;
                    invoice.subscription = original.subscription;

                    computeActionItems();
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function computeActionItems() {
            let invoice = $scope.invoice;
            let paymentPlan = $scope.paymentPlan;
            let customer = invoice.customer;

            // voided
            if (invoice.status === 'voided') {
                actionItems = ['voided'];
                return;
            }

            let items = [];

            // needs attention
            if (invoice.needs_attention && !invoice.closed) {
                items.push('needs_attention');
            }

            // draft
            if (invoice.status === 'draft') {
                items.push('draft');
            }

            // autopay / payment plans
            if (invoice.autopay && !invoice.closed && !invoice.paid && invoice.status !== 'pending') {
                if (paymentPlan && paymentPlan.status === 'pending_signup') {
                    items.push('payment_plan_needs_approval');
                } else if (!customer.payment_source) {
                    items.push('missing_payment_source');
                } else if (!invoice.next_payment_attempt && !invoice.draft) {
                    if (invoice.attempt_count === 0) {
                        items.push('no_scheduled_payment_attempt');
                    } else {
                        items.push('exhausted_payment_attempts');
                    }
                } else if (invoice.next_payment_attempt) {
                    if (invoice.attempt_count === 0) {
                        if (paymentPlan) {
                            items.push('scheduled_installment_payment');
                        } else {
                            items.push('scheduled_payment_attempt');
                        }
                    } else {
                        if (paymentPlan) {
                            items.push('failed_installment_payment');
                        } else {
                            items.push('failed_payment_attempt');
                        }
                    }
                }
            }

            // chasing scheduled (v1)
            if (invoice.next_chase_on && Feature.hasFeature('legacy_chasing')) {
                if (customer.email) {
                    items.push('scheduled_chasing');
                } else {
                    items.push('scheduled_chasing_no_email');
                }
            }

            // sent / viewed status
            if (!invoice.autopay && invoice.status === 'viewed') {
                items.push('viewed');
            } else if (
                !invoice.autopay &&
                !invoice.draft &&
                !invoice.closed &&
                !invoice.paid &&
                invoice.status !== 'pending' &&
                invoice.status !== 'past_due' &&
                !invoice.next_chase_on
            ) {
                if (invoice.status === 'not_sent') {
                    if (customer.network_connection) {
                        items.push('network_will_send');
                    } else {
                        items.push('not_sent');
                    }
                } else {
                    items.push('sent');
                }
            }

            // bad debt
            if (invoice.status === 'bad_debt') {
                items.push('bad_debt');
            }

            // past due
            if (invoice.status === 'past_due' && invoice.due_date) {
                items.push('past_due');
            }

            // pending payment
            if (invoice.status === 'pending') {
                items.push('pending_payment');
            }

            // paid
            if (invoice.status === 'paid') {
                items.push('paid');
            }

            actionItems = items;
        }

        /* Line Items */

        $scope.prevLineItemPage = function () {
            $scope.lineItemPage--;
            $scope.loaded.lineItems = false;
            loadLineItems($scope.invoice.id);
        };

        $scope.nextLineItemPage = function () {
            $scope.lineItemPage++;
            $scope.loaded.lineItems = false;
            loadLineItems($scope.invoice.id);
        };

        function loadLineItems(id) {
            if ($scope.loaded.lineItems) {
                return;
            }

            let perPage = 25;
            Invoice.lineItems(
                {
                    id: id,
                    per_page: perPage,
                    page: $scope.lineItemPage,
                },
                function (lineItems, headers) {
                    angular.forEach(lineItems, function (item) {
                        item.hasMetadata = Object.keys(item.metadata).length > 0;
                    });

                    $scope.totalLineItems = headers('X-Total-Count');
                    let links = Core.parseLinkHeader(headers('Link'));

                    // compute page count from pagination links
                    $scope.lineItemPageCount = links.last.match(/[\?\&]page=(\d+)/)[1];

                    let start = ($scope.lineItemPage - 1) * perPage + 1;
                    let end = start + lineItems.length - 1;
                    $scope.lineItemRange = start + '-' + end;

                    $scope.lineItems = lineItems;
                    $scope.loaded.lineItems = true;
                },
                function (result) {
                    $scope.loaded.lineItems = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        /* Attachments */

        function loadAttachments(id) {
            if ($scope.loaded.attachments) {
                return;
            }

            Invoice.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                },
            );
        }

        /* Chasing */

        $scope.editCadence = function (invoice) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/edit-delivery.html',
                controller: 'EditDeliveryController',
                resolve: {
                    invoice: function () {
                        return invoice;
                    },
                    delivery: function () {
                        if ($scope.loaded.delivery) {
                            return $scope.delivery;
                        }

                        return null;
                    },
                    chaseState: function () {
                        if ($scope.loaded.chaseState) {
                            return $scope.chaseState;
                        }

                        return null;
                    },
                },
                size: 'lg',
            });

            modalInstance.result.then(
                function (delivery) {
                    $scope.delivery = delivery;
                    loadChaseState(invoice.id);
                },
                function () {
                    // canceled
                },
            );
        };

        function loadDelivery(id) {
            if (!Feature.hasFeature('invoice_chasing')) {
                $scope.loaded.delivery = true;
                return;
            }

            if ($scope.loaded.delivery) {
                return;
            }

            Invoice.getDelivery(
                {
                    id: id,
                },
                function (delivery) {
                    $scope.delivery = delivery;
                    $scope.loaded.delivery = true;
                },
                function (result) {
                    $scope.loaded.delivery = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadChaseState(id) {
            if (!Feature.hasFeature('invoice_chasing')) {
                $scope.loaded.chaseState = true;
                return;
            }

            Invoice.getChaseState(
                {
                    id: id,
                },
                function (state) {
                    $scope.chaseState = state;
                    $scope.loaded.chaseState = true;
                },
                function (result) {
                    $scope.loaded.chaseState = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        /* Distributions */

        function loadDistributions(id) {
            if (!$scope.hasInvoiceDistributions || $scope.loaded.distributions) {
                return;
            }

            Invoice.distributions(
                {
                    id: id,
                },
                function (distributions) {
                    $scope.distributions = distributions;
                    $scope.loaded.distributions = true;
                },
            );
        }

        function loadNotes(id) {
            if ($scope.loaded.followUpNotes) {
                return;
            }

            Invoice.notes(
                {
                    id: id,
                },
                function (notes) {
                    $scope.followUpNotes = notes;

                    angular.forEach($scope.followUpNotes, function (note) {
                        note._draft = note.notes;

                        // name
                        if (note.user) {
                            note.name = note.user.first_name + ' ' + note.user.last_name;
                        } else {
                            note.name = 'Customer';
                        }
                    });

                    $scope.loaded.followUpNotes = true;
                },
            );
        }

        /* Payments */

        function loadPayments(id) {
            if ($scope.loaded.payments) {
                return;
            }

            Transaction.findAll(
                {
                    'filter[invoice]': id,
                    sort: 'date DESC',
                    paginate: 'none',
                },
                function (transactions) {
                    $scope.payments = transactions;
                    $scope.loaded.payments = true;
                },
                function (result) {
                    $scope.loaded.payments = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadPaymentPlan(id) {
            if ($scope.loaded.payment_plan || !$scope.invoice.payment_plan) {
                return;
            }

            // attempt to load the payment plan, although we do not
            // know for certain yet if the invoice has one
            Invoice.paymentPlan(
                {
                    id: id,
                },
                function (paymentPlan) {
                    $scope.loaded.payment_plan = true;
                    $scope.paymentPlan = paymentPlan;
                    computeActionItems();
                },
                function (result) {
                    $scope.loaded.payment_plan = true;
                    if (result.status == 404) {
                        return;
                    }

                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        /* Settings */

        function loadSettings() {
            Settings.accountsReceivable(
                function (settings) {
                    $scope.settings = settings;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        /* Accounting Sync */

        function determineSyncStatus(invoice) {
            Invoice.accountingSyncStatus(
                {
                    id: invoice.id,
                },
                function (syncStatus) {
                    $scope.syncedObject = syncStatus;
                    $scope.editable = !(
                        syncStatus.synced &&
                        syncStatus.source === 'accounting_system' &&
                        !Feature.hasFeature('accounting_record_edits')
                    );

                    if (syncStatus.last_synced) {
                        let lastSynced = moment.unix(syncStatus.last_synced);
                        $scope.syncedObject.last_synced = lastSynced.format('dddd, MMM Do YYYY, h:mm a');
                        $scope.syncedObject.last_synced_ago = lastSynced.fromNow();
                    }

                    computeActionItems();
                },
                function () {
                    $scope.editable = true;
                },
            );
        }
    }
})();
