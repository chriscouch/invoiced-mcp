/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewPaymentController', ViewPaymentController);

    ViewPaymentController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$modal',
        '$filter',
        '$timeout',
        '$translate',
        'LeavePageWarning',
        'Payment',
        'Money',
        'Core',
        'InvoicedConfig',
        'File',
        'Transaction',
        'Feature',
        'BrowsingHistory',
        'Estimate',
        'Invoice',
        'CreditNote',
        'Attachment',
        'Flywire',
        'Dispute',
    ];

    function ViewPaymentController(
        $scope,
        $state,
        $controller,
        $modal,
        $filter,
        $timeout,
        $translate,
        LeavePageWarning,
        Payment,
        Money,
        Core,
        InvoicedConfig,
        File,
        Transaction,
        Feature,
        BrowsingHistory,
        Estimate,
        Invoice,
        CreditNote,
        Attachment,
        Flywire,
        Dispute,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Payment;
        $scope.modelTitleSingular = 'Payment';
        $scope.modelObjectType = 'payment';
        $scope.stripeDashboardUrl = InvoicedConfig.stripeDashboardUrl;
        $scope.gocardlessDashboardUrl = InvoicedConfig.gocardlessDashboardUrl;
        $scope.appliedTo = [];
        $scope.previous = null;
        $scope.next = null;
        $scope.index = null;
        $scope.total = null;
        $scope.editable = false;
        $scope.notesEditable = false;
        $scope.editingApplication = false;
        $scope.savingDispute = false;
        $scope.acceptDispute = acceptDispute;
        $scope.defendDispute = defendDispute;
        $scope.uploadDisputeDocument = uploadDisputeDocument;
        $scope.removeDisputeDocument = removeDisputeDocument;
        $scope.disputeReasons = [];

        //
        // Presets
        //

        $scope.tab = 'summary';

        //
        // Methods
        //

        $scope.editModal = function (payment) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/edit.html',
                controller: 'EditPaymentController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    payment: function () {
                        return payment;
                    },
                },
            });

            modalInstance.result.then(
                function (_payment) {
                    LeavePageWarning.unblock();

                    Core.flashMessage('Your changes have been saved', 'success');

                    // update payment
                    angular.extend(payment, _payment);

                    // recalculate
                    $scope.postFind(payment);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer,bank_feed_transaction,flywire_payment';
            findParams.include = 'applied_to,bank_account_name,flywire_payment';
        };

        $scope.postFind = function (payment) {
            payment.applied = payment.balance <= 0;
            $scope.payment = payment;
            loadAttachments(payment.id);
            if (payment.flywire_payment && payment.flywire_payment.id) {
                loadPayouts(payment.flywire_payment.id);
            }
            if (!$scope.payment.attachments) {
                $scope.payment.attachments = [];
            }

            // Load the unapplied payment list for
            // the previous/next buttons if the payment
            // is unapplied.
            if (!payment.applied && !payment.voided) {
                loadAdjacentPayments(payment.id);
            }

            determineSyncStatus(payment);

            BrowsingHistory.push({
                id: payment.id,
                type: 'payment',
                title:
                    (payment.customer ? payment.customer.name : 'Payment') +
                    ': ' +
                    Money.currencyFormat(payment.amount, payment.currency, $scope.company.moneyFormat),
            });

            $scope.net = payment.amount;
            $scope.isRefundable = false;
            if (payment.charge) {
                $scope.net = Money.round(
                    payment.charge.currency,
                    payment.charge.amount - payment.charge.amount_refunded,
                );
                $scope.isRefundable = $scope.net > 0 && payment.charge.status === 'succeeded';
            }

            if (payment.flywire_payment) {
                $scope.isRefundable = $scope.isRefundable && payment.flywire_payment.status === 'delivered';
            }

            const key = 'payments.source.' + payment.source;
            let translated = $translate.instant(key);
            if (translated !== key) {
                $scope.sourceName = translated;
            } else {
                $scope.sourceName = payment.source;
            }

            // load documents for each split
            angular.forEach(payment.applied_to, function (appliedTo) {
                if (
                    appliedTo.type === 'estimate' ||
                    appliedTo.type === 'invoice' ||
                    appliedTo.type === 'credit_note' ||
                    appliedTo.type === 'applied_credit' ||
                    appliedTo.type === 'document_adjustment'
                ) {
                    loadDocumentsForSplit(appliedTo);
                }
            });

            return $scope.payment;
        };

        $scope.editApplication = function () {
            $scope.editingApplication = true;
        };

        $scope.cancelEditingApplication = function () {
            $scope.editingApplication = false;
        };

        $scope.apply = function (loadNext) {
            $scope.editingApplication = false;
            if (loadNext && $scope.next) {
                $state.go('manage.payment.view.summary', {
                    id: $scope.next.id,
                });
            } else {
                $scope.find($scope.payment.id); // reload payment
            }
        };

        $scope.sendReceipt = function (payment) {
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
                        return payment;
                    },
                    paymentPlan: function () {
                        return null;
                    },
                    customerId: function () {
                        return payment.customer.id;
                    },
                    sendOptions: function () {
                        return {};
                    },
                },
            });

            modalInstance.result.then(
                function (result) {
                    Core.flashMessage(result, 'success');
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteMessage = function (payment) {
            let customerName = payment.customer ? payment.customer.name : null;
            let escapeHtml = $filter('escapeHtml');

            return (
                '<p>Are you sure you want to void this payment? All applications of this payment will be permanently deleted.</p>' +
                '<p>Customer: ' +
                escapeHtml(customerName) +
                '<br/>' +
                'Amount: ' +
                Money.currencyFormat(payment.amount, payment.currency, $scope.company.moneyFormat) +
                '<br/>' +
                'Date: ' +
                $filter('formatCompanyDate')(payment.date) +
                '<br/>' +
                'Method: ' +
                escapeHtml(payment.method) +
                '</p>'
            );
        };

        $scope.postDelete = function () {
            // override delete method so we do not redirect
            $scope.payment.voided = true;
            $scope.payment.applied_to = [];
        };

        $scope.addedFiles = function (files) {
            // create the file objects on Invoiced
            angular.forEach(files, function (_file) {
                File.create(_file).then(function (file) {
                    // attach to the document
                    $scope.payment.attachments.push({
                        file: file,
                    });

                    Attachment.create(
                        {
                            parent_type: 'payment',
                            parent_id: $scope.payment.id,
                            file_id: file.id,
                            location: 'attachment',
                        },
                        function () {
                            // do nothing
                        },
                        function (result) {
                            $scope.error = result.data;
                        },
                    );
                });
            });
        };

        $scope.refund = function (charge) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payments/refund-charge.html',
                controller: 'RefundChargeController',
                resolve: {
                    charge: function () {
                        return charge;
                    },
                },
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Your refund has been processed', 'success');
                    $scope.find($scope.payment.id); // reload payment
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.editNotes = function (payment) {
            LeavePageWarning.block();
            $scope.editingNotes = true;
            $scope.newNotes = payment.notes;
        };

        $scope.cancelNotesEdit = function () {
            LeavePageWarning.unblock();
            $scope.editingNotes = false;
        };

        $scope.saveNotes = function (payment, notes) {
            $scope.saving = true;

            Payment.edit(
                {
                    id: payment.id,
                },
                {
                    notes: notes,
                },
                function () {
                    $scope.saving = false;
                    $scope.editingNotes = false;
                    payment.notes = notes;
                    LeavePageWarning.unblock();
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.isImage = function (file) {
            return file.type.split('/')[0] === 'image' && file.url;
        };

        // cancel any reload promises after leaving the page
        $scope.$on('$stateChangeStart', function () {
            if ($scope.reload) {
                $timeout.cancel($scope.reload);
            }
        });

        function loadAttachments(id) {
            Payment.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                    $scope.payment.attachments = attachments;
                },
            );
        }

        function loadPayouts(id) {
            Flywire.getPaymentPayouts(
                {
                    id: id,
                    expand: 'disbursement',
                },
                function (payouts) {
                    $scope.payouts = payouts;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadAdjacentPayments(id) {
            let filterFinished = !$scope.payment.applied && !$scope.payment.voided;

            let findParams = {};
            if (filterFinished) {
                findParams = {
                    'filter[voided]': '0',
                    'filter[applied]': '0',
                    sort: 'id ASC',
                };
            }

            Payment.all(findParams, function (payments) {
                let index = null;
                let filteredPayments = [];
                angular.forEach(payments, function (payment) {
                    payment.applied = payment.balance <= 0;

                    if (payment.id === id) {
                        filteredPayments.push(payment);
                        index = filteredPayments.length - 1;
                    } else if (!payment.applied && !payment.voided) {
                        filteredPayments.push(payment);
                    }
                });
                if (index !== null && filteredPayments.length > 1) {
                    $scope.index = index + 1;
                    $scope.total = filteredPayments.length;
                    if (index === 0) {
                        $scope.previous = filteredPayments[payments.length - 1];
                    } else {
                        $scope.previous = filteredPayments[index - 1];
                    }
                    if (index + 1 >= filteredPayments.length) {
                        $scope.next = filteredPayments[0];
                    } else {
                        $scope.next = filteredPayments[index + 1];
                    }
                }
            });
        }

        function determineSyncStatus(payment) {
            Payment.accountingSyncStatus(
                {
                    id: payment.id,
                },
                function (syncStatus) {
                    $scope.syncedObject = syncStatus;
                    // payments synced from an accounting system or charge cannot be edited
                    $scope.editable =
                        !(
                            syncStatus.synced &&
                            syncStatus.source === 'accounting_system' &&
                            !Feature.hasFeature('accounting_record_edits')
                        ) && !payment.charge;
                    // notes can be edited if there is a charge
                    $scope.notesEditable = !(
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
                    $scope.notesEditable = true;
                },
            );
        }

        function loadDocumentsForSplit(appliedTo) {
            let documentTypes = [];
            // need to load credit note?
            if (appliedTo.credit_note != null) {
                documentTypes.push('credit_note');
            }
            // need to load estimate?
            if (appliedTo.estimate != null) {
                documentTypes.push('estimate');
            }
            // need to load invoice?
            if (appliedTo.invoice != null) {
                documentTypes.push('invoice');
            }

            angular.forEach(documentTypes, function (type) {
                switch (type) {
                    case 'credit_note': {
                        appliedTo.creditNote = {
                            id: appliedTo.credit_note,
                            object: 'credit_note',
                            number: 'Credit Note',
                            name: null,
                        };
                        loadCreditNote(appliedTo.credit_note, function (creditNote) {
                            appliedTo.creditNote = creditNote;
                        });

                        break;
                    }
                    case 'estimate': {
                        appliedTo._estimate = {
                            id: appliedTo.estimate,
                            object: 'estimate',
                            number: 'Estimate',
                            name: null,
                        };
                        loadEstimate(appliedTo.estimate, function (estimate) {
                            appliedTo._estimate = estimate;
                        });
                        break;
                    }
                    case 'invoice': {
                        appliedTo._invoice = {
                            id: appliedTo.invoice,
                            object: 'invoice',
                            number: 'Invoice',
                            name: null,
                        };
                        loadInvoice(appliedTo.invoice, function (invoice) {
                            appliedTo._invoice = invoice;
                        });
                        break;
                    }
                }
            });
        }

        function loadCreditNote(id, callback) {
            CreditNote.find(
                {
                    id: id,
                },
                {
                    exclude: 'items,discounts,taxes,shipping,ship_to,payment_source,metadata',
                },
                function (creditNote) {
                    callback(creditNote);
                },
            );
        }

        function loadEstimate(id, callback) {
            Estimate.find(
                {
                    id: id,
                },
                {
                    exclude: 'items,discounts,taxes,shipping,ship_to,metadata',
                },
                function (estimate) {
                    callback(estimate);
                },
            );
        }

        function loadInvoice(id, callback) {
            Invoice.find(
                {
                    id: id,
                },
                {
                    exclude: 'items,discounts,taxes,shipping,ship_to,payment_source,metadata',
                },
                function (invoice) {
                    callback(invoice);
                },
            );
        }

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Payment');

        function uploadDisputeDocument() {
            $modal.open({
                templateUrl: 'accounts_receivable/views/payments/upload-dispute-documents.html',
                controller: 'UploadDisputeDocumentsController',
                size: 'md',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    disputeObject: function () {
                        return $scope.payment.charge.disputed;
                    },
                },
            });
        }

        function removeDisputeDocument() {
            $modal.open({
                templateUrl: 'accounts_receivable/views/payments/remove-dispute-document.html',
                controller: 'RemoveDisputeDocumentsController',
                size: 'md',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    disputeObject: function () {
                        return $scope.payment.charge.disputed;
                    },
                },
            });
        }

        function defendDispute() {
            $modal.open({
                templateUrl: 'accounts_receivable/views/payments/defend-dispute.html',
                controller: 'DefendDisputeController',
                size: 'md',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    disputeObject: function () {
                        return $scope.payment.charge.disputed;
                    },
                },
            });
        }
        function acceptDispute() {
            vex.dialog.confirm({
                message: $translate.instant('payments.view.notices.accept_dispute'),
                callback: function (result) {
                    if (result) {
                        $scope.savingDispute = true;
                        Dispute.acceptDispute(
                            {
                                id: $scope.payment.charge.disputed.id,
                            },
                            {},
                            function () {
                                $scope.savingDispute = false;
                                Core.flashMessage(
                                    $translate.instant('payments.view.notices.accept_dispute_success'),
                                    'success',
                                );
                            },
                            function (result) {
                                $scope.savingDispute = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        }
    }
})();
