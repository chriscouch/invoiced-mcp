(function () {
    'use strict';

    angular.module('app.network').controller('ViewNetworkDocumentController', ViewNetworkDocumentController);

    ViewNetworkDocumentController.$inject = [
        '$scope',
        '$stateParams',
        '$state',
        'selectedCompany',
        'Core',
        'NetworkDocument',
        'BrowsingHistory',
        'Bill',
        'VendorCredit',
        'Invoice',
        'CreditNote',
        'Estimate',
    ];

    function ViewNetworkDocumentController(
        $scope,
        $stateParams,
        $state,
        selectedCompany,
        Core,
        NetworkDocument,
        BrowsingHistory,
        Bill,
        VendorCredit,
        Invoice,
        CreditNote,
        Estimate,
    ) {
        load();

        function load() {
            $scope.loading = true;
            NetworkDocument.find(
                {
                    id: $stateParams.id,
                    include: 'detail,current_status_reason,vendor,customer',
                },
                function (doc) {
                    $scope.sentToMe = doc.to_company.username === selectedCompany.username;

                    // Check if there is a transaction in our database we can go to instead for enriched functionality
                    if ($scope.sentToMe) {
                        redirectToApDocument(doc);
                    } else {
                        redirectToArDocument(doc);
                    }
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function redirectToApDocument(doc) {
            if (doc.type === 'Invoice') {
                Bill.findAll(
                    {
                        'filter[network_document]': $stateParams.id,
                    },
                    function (bills) {
                        if (bills.length > 0) {
                            $state.go('manage.bill.view.summary', { id: bills[0].id });
                        } else {
                            finishLoad(doc);
                        }
                    },
                    function () {
                        finishLoad(doc);
                    },
                );
            } else if (doc.type === 'CreditNote') {
                VendorCredit.findAll(
                    {
                        'filter[network_document]': $stateParams.id,
                    },
                    function (vendorCredits) {
                        if (vendorCredits.length > 0) {
                            $state.go('manage.vendor_credit.view.summary', { id: vendorCredits[0].id });
                        } else {
                            finishLoad(doc);
                        }
                    },
                    function () {
                        finishLoad(doc);
                    },
                );
            } else {
                finishLoad(doc);
            }
        }

        function redirectToArDocument(doc) {
            if (doc.type === 'Invoice') {
                Invoice.findAll(
                    {
                        'filter[network_document]': $stateParams.id,
                    },
                    function (invoices) {
                        if (invoices.length > 0) {
                            $state.go('manage.invoice.view.summary', { id: invoices[0].id });
                        } else {
                            finishLoad(doc);
                        }
                    },
                    function () {
                        finishLoad(doc);
                    },
                );
            } else if (doc.type === 'CreditNote') {
                CreditNote.findAll(
                    {
                        'filter[network_document]': $stateParams.id,
                    },
                    function (creditNotes) {
                        if (creditNotes.length > 0) {
                            $state.go('manage.credit_note.view.summary', { id: creditNotes[0].id });
                        } else {
                            finishLoad(doc);
                        }
                    },
                    function () {
                        finishLoad(doc);
                    },
                );
            } else if (doc.type === 'Quotation') {
                Estimate.findAll(
                    {
                        'filter[network_document]': $stateParams.id,
                    },
                    function (estimates) {
                        if (estimates.length > 0) {
                            $state.go('manage.estimate.view.summary', { id: estimates[0].id });
                        } else {
                            finishLoad(doc);
                        }
                    },
                    function () {
                        finishLoad(doc);
                    },
                );
            } else {
                finishLoad(doc);
            }
        }

        function finishLoad(doc) {
            $scope.loading = false;
            $scope.doc = doc;
            Core.setTitle(doc.type + ' ' + doc.reference);

            BrowsingHistory.push({
                id: doc.id,
                type: 'network_document',
                title: doc.type + ' ' + doc.reference,
            });
        }
    }
})();
