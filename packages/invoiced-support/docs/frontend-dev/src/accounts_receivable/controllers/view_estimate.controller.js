/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewEstimateController', ViewEstimateController);

    ViewEstimateController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        'Estimate',
        'Money',
        'Transaction',
        'InvoiceCalculator',
        'Core',
        'Settings',
        'DocumentControllerHelper',
        'InvoicedConfig',
        'File',
        'BrowsingHistory',
    ];

    function ViewEstimateController(
        $scope,
        $state,
        $controller,
        $rootScope,
        $modal,
        $filter,
        Estimate,
        Money,
        Transaction,
        InvoiceCalculator,
        Core,
        Settings,
        DocumentControllerHelper,
        InvoicedConfig,
        File,
        BrowsingHistory,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Estimate;
        $scope.modelTitleSingular = 'Estimate';
        $scope.modelObjectType = 'estimate';

        //
        // Presets
        //

        let actionItems = [];
        $scope.tab = 'summary';
        $scope.lineItemPage = 1;

        $scope.newComment = {
            attachments: [],
        };

        $scope.avatarOptions = {
            height: 35,
            width: 35,
        };
        $scope.payments = [];
        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer,invoice';
            findParams.exclude = 'items';
        };

        $scope.postFind = function (estimate) {
            $scope.estimate = estimate;
            $scope.customerName = estimate.customer.name;

            $rootScope.modelTitle = $scope.estimate.number;
            Core.setTitle($rootScope.modelTitle);

            // calculate the estimate subtotal lines
            $scope.totals = InvoiceCalculator.calculateSubtotalLines(estimate);

            // prefill the email reply box
            $scope.prefillEmailReply = {
                network_connection: estimate.customer.network_connection,
                subject: 'Estimate # ' + estimate.number,
            };

            // load related data
            loadLineItems(estimate.id);
            loadPayments(estimate.id);
            loadSettings();

            // compute the action items
            computeActionItems();

            BrowsingHistory.push({
                id: estimate.id,
                type: 'estimate',
                title: estimate.number,
            });

            return $scope.estimate;
        };

        $scope.issue = function (estimate) {
            $scope.issuing = true;

            Estimate.edit(
                {
                    id: estimate.id,
                },
                {
                    draft: false,
                },
                function () {
                    $scope.issuing = false;

                    // reload the estimate
                    $scope.find(estimate.id);
                },
                function (result) {
                    $scope.issuing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.markSent = function (sent, estimate) {
            Estimate.edit(
                {
                    id: estimate.id,
                },
                {
                    sent: sent,
                },
                function () {
                    if (estimate.status === 'not_sent') {
                        estimate.status = 'sent';
                    }
                    computeActionItems();
                    Core.flashMessage('Your estimate has been marked as sent', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.markApproved = function (estimate) {
            let initials = Core.generateInitials($scope.currentUser.name);
            let escapeHtml = $filter('escapeHtml');

            vex.dialog.confirm({
                message:
                    'Are you sure you want to mark the estimate as approved? Please only use this if your customer has approved the estimate outside of the customer portal. We will mark this as approved by "' +
                    escapeHtml(initials) +
                    '".',
                callback: function (result) {
                    if (result) {
                        Estimate.edit(
                            {
                                id: estimate.id,
                            },
                            {
                                approved: initials,
                            },
                            function () {
                                Core.flashMessage('Your changes have been saved', 'success');

                                // reload the estimate
                                $scope.find(estimate.id);
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.paymentModal = function (estimate) {
            let customer =
                typeof estimate.customer == 'object'
                    ? estimate.customer
                    : {
                          id: estimate.customer,
                          name: estimate.customerName,
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
                            preselected: [estimate],
                            currency: estimate.currency,
                            amount: estimate.deposit,
                        };
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Payment for ' + estimate.number + ' has been recorded', 'success');
                    $scope.find(estimate.id, function (newEstimate) {
                        angular.extend(estimate, newEstimate);
                    });

                    // reload payments
                    $scope.loaded.payments = false;
                    loadPayments(estimate.id);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.markDepositPaid = function (estimate, paid) {
            Estimate.edit(
                {
                    id: estimate.id,
                },
                {
                    deposit_paid: paid,
                    closed: false,
                },
                function () {
                    let msg = paid
                        ? 'Your estimate deposit has been marked as paid'
                        : 'Your estimate deposit has been unmarked as paid';
                    Core.flashMessage(msg, 'success');

                    // reload the estimate
                    $scope.find(estimate.id);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.setClosed = function (closed, estimate) {
            Estimate.edit(
                {
                    id: estimate.id,
                },
                {
                    closed: closed,
                },
                function (_estimate) {
                    estimate.status = _estimate.status;
                    estimate.closed = _estimate.closed;
                    computeActionItems();
                    Core.flashMessage('Your changes have been saved', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.void = function (estimate) {
            DocumentControllerHelper.void($scope, estimate, function (original) {
                estimate.invoice = original.invoice;
                computeActionItems();
            });
        };

        $scope.deleteMessage = function (estimate, action) {
            let escapeHtml = $filter('escapeHtml');
            action = action || 'delete';
            let customerName = estimate.customer.name || estimate.customerName;
            return (
                '<p>Are you sure you want to ' +
                action +
                ' this estimate?</p>' +
                '<p><strong>' +
                escapeHtml(estimate.name) +
                ' <small>' +
                escapeHtml(estimate.number) +
                '</small></strong><br/>' +
                'Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Total: ' +
                Money.currencyFormat(estimate.total, estimate.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(estimate.date) +
                '</p>'
            );
        };

        $scope.hasActionItem = function (k) {
            return actionItems.indexOf(k) !== -1;
        };

        $scope.viewApproval = function (estimate) {
            $modal.open({
                templateUrl: 'accounts_receivable/views/estimates/approval-details.html',
                controller: 'EstimateApprovalDetailsController',
                resolve: {
                    approval: function () {
                        return estimate.approval;
                    },
                },
                size: 'sm',
            });
        };

        /* Invoices */

        $scope.makeInvoiceFromEstimate = function (estimate) {
            $scope.saving = true;

            Estimate.makeInvoiceFromEstimate(
                {
                    id: estimate.id,
                },
                function (invoice) {
                    $scope.saving = false;

                    $state.go('manage.invoice.view.summary', {
                        id: invoice.id,
                    });
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        /* Line Items */

        $scope.prevLineItemPage = function () {
            $scope.lineItemPage--;
            $scope.loaded.lineItems = false;
            loadLineItems($scope.estimate.id);
        };

        $scope.nextLineItemPage = function () {
            $scope.lineItemPage++;
            $scope.loaded.lineItems = false;
            loadLineItems($scope.estimate.id);
        };

        function loadLineItems(id) {
            if ($scope.loaded.lineItems) {
                return;
            }

            let perPage = 25;
            Estimate.lineItems(
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

        $scope.loadAttachments = function (id) {
            if ($scope.loaded.attachments || !id) {
                return;
            }

            Estimate.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                },
            );
        };

        /* Emails */

        $scope.emailModal = function (estimate) {
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
                        return estimate;
                    },
                    paymentPlan: function () {
                        return null;
                    },
                    customerId: function () {
                        return estimate.customer.id;
                    },
                    sendOptions: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    Core.flashMessage(result, 'success');
                    if (estimate.status === 'not_sent') {
                        estimate.status = 'sent';
                    }
                    computeActionItems();
                },
                function () {
                    // canceled
                },
            );
        };

        /* Customer Portal */

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
        Core.setTitle('Estimate');

        function computeActionItems() {
            let estimate = $scope.estimate;

            // voided
            if (estimate.status == 'voided') {
                actionItems = ['voided'];
                return;
            }

            let items = [];

            // draft
            if (estimate.status === 'draft') {
                items.push('draft');
            }

            // sent / viewed status
            if (estimate.status === 'viewed') {
                items.push('viewed');
            } else if (
                !estimate.draft &&
                !estimate.closed &&
                estimate.status !== 'approved' &&
                estimate.status !== 'expired' &&
                estimate.status !== 'declined'
            ) {
                if (estimate.status === 'not_sent') {
                    if (estimate.customer.network_connection) {
                        items.push('network_will_send');
                    } else {
                        items.push('not_sent');
                    }
                } else {
                    items.push('sent');
                }
            }

            // expired
            if (estimate.status === 'expired') {
                items.push('expired');
            }

            // declined
            if (estimate.status === 'declined') {
                items.push('declined');
            }

            // approved / missing deposit
            if (estimate.status === 'approved') {
                if (estimate.deposit && !estimate.deposit_paid) {
                    items.push('needs_deposit');
                } else {
                    items.push('approved');
                }
            }

            // invoiced
            if (estimate.status === 'invoiced') {
                items.push('invoiced');
            }

            actionItems = items;
        }

        /* Payments */

        function loadPayments(id) {
            if ($scope.loaded.payments) {
                return;
            }

            Transaction.findAll(
                {
                    'filter[estimate]': id,
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
    }
})();
