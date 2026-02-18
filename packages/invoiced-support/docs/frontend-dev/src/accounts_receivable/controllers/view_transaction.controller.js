/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewTransactionController', ViewTransactionController);

    ViewTransactionController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$rootScope',
        '$modal',
        '$filter',
        'LeavePageWarning',
        'Transaction',
        'Money',
        'Core',
        'PaymentCalculator',
        'InvoicedConfig',
        'BrowsingHistory',
        'Feature',
    ];

    function ViewTransactionController(
        $scope,
        $state,
        $controller,
        $rootScope,
        $modal,
        $filter,
        LeavePageWarning,
        Transaction,
        Money,
        Core,
        PaymentCalculator,
        InvoicedConfig,
        BrowsingHistory,
        Feature,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Transaction;
        $scope.modelTitleSingular = 'Transaction';
        $scope.modelObjectType = 'transaction';
        $scope.stripeDashboardUrl = InvoicedConfig.stripeDashboardUrl;
        $scope.gocardlessDashboardUrl = InvoicedConfig.gocardlessDashboardUrl;
        $scope.appliedTo = [];
        $scope.editable = false;

        //
        // Presets
        //

        $scope.tab = 'summary';

        //
        // Methods
        //

        $scope.editModal = function (transaction) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/transactions/edit.html',
                controller: 'EditTransactionController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    transaction: function () {
                        return transaction;
                    },
                },
            });

            modalInstance.result.then(
                function (_transaction) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('Your changes have been saved', 'success');

                    // update transaction
                    angular.extend(transaction, _transaction);

                    // recalculate
                    $scope.postFind(transaction);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.refund = function (transaction) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/transactions/refund.html',
                controller: 'RefundTransactionController',
                resolve: {
                    payment: function () {
                        return transaction;
                    },
                    maxAmount: function () {
                        return transaction.amount - $scope.refunded;
                    },
                },
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (refunds) {
                    Core.flashMessage('Your refund has been processed', 'success');

                    //filter unapplied
                    refunds = refunds.filter(function (item) {
                        return item.id;
                    });
                    // add to refunds list
                    if (refunds.length) {
                        $scope.transaction.children = $scope.transaction.children.concat(refunds);
                    }
                    // recalculate
                    calculate($scope.transaction);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.markFailed = function (transaction) {
            vex.dialog.confirm({
                message:
                    'This will mark the payment as failed and unapply the amount from any invoice balances. Are you sure?',
                callback: function (result) {
                    if (result) {
                        $scope.markingAsFailed = true;

                        // mark this transaction as failed
                        // and any immediate children
                        let queue = [transaction];
                        angular.forEach(transaction.children, function (subTransaction) {
                            if (transaction.type === 'payment' || transaction.type === 'charge') {
                                queue.push(subTransaction);
                            }
                        });

                        markTransactionsFailed(queue);
                    }
                },
            });
        };

        $scope.isClickable = function (split) {
            return split.invoice || split.credit_note || split.estimate;
        };

        $scope.goSplit = function ($event, split) {
            if (split.invoice) {
                $state.go('manage.invoice.view.summary', {
                    id: split.invoice.id,
                });
            } else if (split.credit_note) {
                $state.go('manage.credit_note.view.summary', {
                    id: split.credit_note.id,
                });
            } else if (split.estimate) {
                $state.go('manage.estimate.view.summary', {
                    id: split.estimate.id,
                });
            }
        };

        $scope.editNotes = function (transaction) {
            LeavePageWarning.block();
            $scope.editingNotes = true;
            $scope.newNotes = transaction.notes;
        };

        $scope.cancelNotesEdit = function () {
            LeavePageWarning.unblock();
            $scope.editingNotes = false;
        };

        $scope.saveNotes = function (transaction, notes) {
            $scope.saving = true;

            Transaction.edit(
                {
                    id: transaction.id,
                },
                {
                    notes: notes,
                },
                function () {
                    $scope.saving = false;
                    $scope.editingNotes = false;
                    transaction.notes = notes;
                    LeavePageWarning.unblock();
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer,invoice,credit_note,estimate';
            findParams.include = 'children';
        };

        $scope.postFind = function (transaction) {
            // If this belongs to a payment then
            // redirect to the payment
            if (transaction.payment) {
                $state.go('manage.payment.view.summary', {
                    id: transaction.payment,
                });
                return transaction;
            }

            // If this is a sub-transaction then
            // redirect to the parent transaction
            if (transaction.parent_transaction) {
                $state.go('manage.transaction.view.summary', {
                    id: transaction.parent_transaction,
                });
                return transaction;
            }

            $scope.transaction = transaction;

            $rootScope.modelTitle = 'Transaction';
            if (transaction.type == 'refund') {
                $rootScope.modelTitle = 'Refund';
            } else if (transaction.type == 'adjustment') {
                $rootScope.modelTitle = transaction.amount < 0 ? 'Credit' : 'Adjustment';
            }
            Core.setTitle($rootScope.modelTitle);

            calculate($scope.transaction);

            determineSyncStatus(transaction);

            BrowsingHistory.push({
                id: transaction.id,
                type: 'transaction',
                title:
                    transaction.customer.name +
                    ': ' +
                    Money.currencyFormat(transaction.amount, transaction.currency, $scope.company.moneyFormat),
            });

            return $scope.transaction;
        };

        $scope.deleteMessage = function (transaction) {
            let customerName = transaction.customer.name || transaction.customerName;
            let escapeHtml = $filter('escapeHtml');

            return (
                '<p>Are you sure you want to delete this payment?</p>' +
                '<p>Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Amount: ' +
                Money.currencyFormat(transaction.amount, transaction.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(transaction.date) +
                '<br/>' +
                'Method: ' +
                escapeHtml(transaction.method) +
                '</p>'
            );
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Transaction');

        function calculate(transaction) {
            let result = PaymentCalculator.calculateTree(transaction);

            $scope.appliedTo = result.appliedTo;
            $scope.paid = result.paid;
            $scope.credited = result.credited;
            $scope.refunded = result.refunded;
            $scope.net = result.net;

            $scope.isRefundable =
                transaction.type == 'payment' && transaction.status == 'succeeded' && $scope.paid > $scope.refunded;

            // do not show the splits if this is a credit or adjustment with no attached document
            if (
                transaction.type === 'adjustment' &&
                result.appliedTo.length === 1 &&
                !transaction.invoice &&
                !transaction.credit_note
            ) {
                $scope.appliedTo = [];
            }
        }

        function determineSyncStatus(transaction) {
            Transaction.accountingSyncStatus(
                {
                    id: transaction.id,
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
                },
                function () {
                    $scope.editable = true;
                },
            );
        }

        function markTransactionsFailed(queue) {
            if (queue.length === 0) {
                $scope.transaction.status = 'failed';
                $scope.markingAsFailed = false;
                return;
            }

            Transaction.edit(
                {
                    id: queue[0].id,
                },
                {
                    status: 'failed',
                },
                function (transaction) {
                    transaction.status = 'failed';
                    queue.splice(0, 1);
                    markTransactionsFailed(queue);
                },
                function (result) {
                    $scope.markingAsFailed = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
