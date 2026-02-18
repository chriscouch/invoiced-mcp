(function () {
    'use strict';

    angular.module('app.integrations').directive('syncStatus', syncStatus);

    function syncStatus() {
        return {
            restrict: 'E',
            templateUrl: 'integrations/views/sync-status.html',
            scope: {
                syncedObject: '=',
                editable: '=',
                objectType: '=',
            },
            controller: [
                '$scope',
                '$modal',
                'InvoicedConfig',
                function ($scope, $modal, InvoicedConfig) {
                    $scope.externalLink = null;
                    $scope.externalLinkTransKey = '';

                    $scope.viewDetails = function (reconciliationError) {
                        const modalInstance = $modal.open({
                            templateUrl: 'integrations/views/sync-error.html',
                            controller: 'SyncErrorDetailsController',
                            resolve: {
                                reconciliationError: function () {
                                    return reconciliationError;
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (updated) {
                                if (!updated) {
                                    $scope.syncedObject.error = null;
                                } else {
                                    angular.extend(reconciliationError, updated);
                                }
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.$watch('syncedObject', function (syncedObject) {
                        if (syncedObject) {
                            generateExternalLink(syncedObject);
                        }
                    });

                    function generateExternalLink(syncedObject) {
                        $scope.externalLink = null;
                        $scope.externalLinkTransKey = '';

                        if (!syncedObject.synced) {
                            return;
                        }

                        if (syncedObject.accounting_system === 'quickbooks_online') {
                            $scope.externalLinkTransKey = 'quickbooks_online.view_on_quickbooks';
                            let qboUrl = InvoicedConfig.quickbooksAppUrl;
                            if ($scope.objectType === 'customer') {
                                $scope.externalLink =
                                    qboUrl + '/app/customerdetail?nameId=' + syncedObject.accounting_id;
                            } else if ($scope.objectType === 'invoice') {
                                $scope.externalLink = qboUrl + '/app/invoice?txnId=' + syncedObject.accounting_id;
                            } else if ($scope.objectType === 'credit_note') {
                                $scope.externalLink = qboUrl + '/app/creditmemo?txnId=' + syncedObject.accounting_id;
                            } else if ($scope.objectType === 'payment') {
                                $scope.externalLink = qboUrl + '/app/recvpayment?txnId=' + syncedObject.accounting_id;
                            }
                        }

                        if (syncedObject.accounting_system === 'xero') {
                            $scope.externalLinkTransKey = 'xero.view_on_xero';
                            if ($scope.objectType === 'customer') {
                                $scope.externalLink = 'https://go.xero.com/Contacts/View/' + syncedObject.accounting_id;
                            } else if ($scope.objectType === 'invoice') {
                                $scope.externalLink =
                                    'https://go.xero.com/AccountsReceivable/View.aspx?invoiceID=' +
                                    syncedObject.accounting_id;
                            } else if ($scope.objectType === 'credit_note') {
                                $scope.externalLink =
                                    'https://go.xero.com/AccountsReceivable/ViewCreditNote.aspx?creditNoteID=' +
                                    syncedObject.accounting_id;
                            } else if ($scope.objectType === 'payment') {
                                $scope.externalLink =
                                    'https://go.xero.com/Bank/ViewTransaction.aspx?bankTransactionID=' +
                                    syncedObject.accounting_id;
                            }
                        }
                    }
                },
            ],
        };
    }
})();
