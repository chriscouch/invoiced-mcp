/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewCreditNoteController', ViewCreditNoteController);

    ViewCreditNoteController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        'CreditNote',
        'Transaction',
        'Money',
        'InvoiceCalculator',
        'Core',
        'Settings',
        'BrowsingHistory',
        'DocumentControllerHelper',
        'Feature',
    ];

    function ViewCreditNoteController(
        $scope,
        $controller,
        $rootScope,
        $modal,
        $filter,
        CreditNote,
        Transaction,
        Money,
        InvoiceCalculator,
        Core,
        Settings,
        BrowsingHistory,
        DocumentControllerHelper,
        Feature,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = CreditNote;
        $scope.modelTitleSingular = 'Credit Note';
        $scope.modelObjectType = 'credit_note';

        //
        // Presets
        //

        $scope.lineItemPage = 1;
        $scope.transactions = [];

        let actionItems = [];
        $scope.step = 1;
        $scope.tab = 'summary';

        $scope.newComment = {
            attachments: [],
        };

        $scope.avatarOptions = {
            height: 35,
            width: 35,
        };

        $scope.editable = false;

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer,invoice';
            findParams.exclude = 'items';
        };

        $scope.postFind = function (creditNote) {
            $scope.creditNote = creditNote;
            $scope.customerName = creditNote.customer.name;

            $rootScope.modelTitle = $scope.creditNote.number;
            Core.setTitle($rootScope.modelTitle);

            // calculate the credit note subtotal lines
            $scope.totals = InvoiceCalculator.calculateSubtotalLines(creditNote);

            // prefill the email reply box
            $scope.prefillEmailReply = {
                network_connection: creditNote.customer.network_connection,
                subject: 'Credit Note # ' + creditNote.number,
            };

            // load related data
            loadLineItems(creditNote.id);
            loadAttachments(creditNote.id);
            loadTransactions(creditNote.id);
            loadSettings();

            // compute the step
            computeActionItems();

            determineSyncStatus(creditNote);

            BrowsingHistory.push({
                id: creditNote.id,
                type: 'credit_note',
                title: creditNote.number,
            });

            return $scope.creditNote;
        };

        $scope.issue = function (creditNote) {
            $scope.issuing = true;

            CreditNote.edit(
                {
                    id: creditNote.id,
                },
                {
                    draft: false,
                },
                function () {
                    $scope.issuing = false;

                    // reload the credit note
                    $scope.find(creditNote.id);
                },
                function (result) {
                    $scope.issuing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.setClosed = function (closed, creditNote) {
            CreditNote.edit(
                {
                    id: creditNote.id,
                },
                {
                    closed: closed,
                },
                function (_creditNote) {
                    creditNote.closed = _creditNote.closed;
                    creditNote.status = _creditNote.status;
                    computeActionItems();
                    Core.flashMessage(
                        'Credit Note # ' + creditNote.number + ' was ' + (closed ? 'closed' : 'reopened'),
                        'success',
                    );
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.void = function (creditNote) {
            DocumentControllerHelper.void($scope, creditNote, function (original) {
                creditNote.invoice = original.invoice;
                computeActionItems();
            });
        };

        $scope.deleteMessage = function (creditNote, action) {
            let escapeHtml = $filter('escapeHtml');
            action = action || 'delete';
            let customerName = creditNote.customer.name || creditNote.customerName;
            return (
                '<p>Are you sure you want to ' +
                action +
                ' this credit note?</p>' +
                '<p><strong>' +
                escapeHtml(creditNote.name) +
                ' <small>' +
                escapeHtml(creditNote.number) +
                '</small></strong><br/>' +
                'Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Total: ' +
                Money.currencyFormat(creditNote.total, creditNote.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(creditNote.date) +
                '</p>'
            );
        };

        $scope.hasActionItem = function (k) {
            return actionItems.indexOf(k) !== -1;
        };

        /* Payments */

        $scope.paymentModal = function (creditNote) {
            let customer =
                typeof creditNote.customer == 'object'
                    ? creditNote.customer
                    : {
                          id: creditNote.customer,
                          name: creditNote.customerName,
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
                            appliedCredits: [creditNote],
                            currency: creditNote.currency,
                            amount: '0',
                        };
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Payment for ' + creditNote.number + ' has been recorded', 'success');
                    $scope.find(creditNote.id);

                    if ($scope.loaded.transactions) {
                        $scope.loaded.transactions = false;
                        loadTransactions(creditNote.id);
                    }
                },
                function () {
                    // canceled
                },
            );
        };

        /* Sending */

        $scope.emailModal = function (creditNote) {
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
                        return creditNote;
                    },
                    paymentPlan: function () {
                        return null;
                    },
                    customerId: function () {
                        return creditNote.customer.id;
                    },
                    sendOptions: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    Core.flashMessage(result, 'success');
                    if (creditNote.status === 'not_sent') {
                        creditNote.status = 'sent';
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
        Core.setTitle('Credit Note');

        function computeActionItems() {
            let creditNote = $scope.creditNote;

            let items = [];

            if (creditNote.status === 'voided') {
                items.push('voided');
            } else if (creditNote.draft) {
                items.push('draft');
            } else if (creditNote.paid) {
                items.push('paid');
            } else if (creditNote.status === 'closed') {
                items.push('closed');
            } else {
                items.push('issued');
            }

            actionItems = items;
        }

        /* Line Items */

        $scope.prevLineItemPage = function () {
            $scope.lineItemPage--;
            $scope.loaded.lineItems = false;
            loadLineItems($scope.creditNote.id);
        };

        $scope.nextLineItemPage = function () {
            $scope.lineItemPage++;
            $scope.loaded.lineItems = false;
            loadLineItems($scope.creditNote.id);
        };

        function loadLineItems(id) {
            if ($scope.loaded.lineItems) {
                return;
            }

            let perPage = 25;
            CreditNote.lineItems(
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

            CreditNote.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                },
            );
        }

        /* Transactions */

        function loadTransactions(id) {
            if ($scope.loaded.transactions) {
                return;
            }

            Transaction.findAll(
                {
                    'filter[credit_note]': id,
                    sort: 'date DESC',
                    expand: 'invoice',
                    paginate: 'none',
                },
                function (transactions) {
                    $scope.transactions = transactions;
                    $scope.loaded.transactions = true;
                },
                function (result) {
                    $scope.loaded.transactions = true;
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

        function determineSyncStatus(creditNote) {
            CreditNote.accountingSyncStatus(
                {
                    id: creditNote.id,
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
