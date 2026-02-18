(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('ViewFlywireDisbursementController', ViewFlywireDisbursementController);

    ViewFlywireDisbursementController.$inject = [
        '$scope',
        '$stateParams',
        'Core',
        'ObjectDeepLink',
        'Money',
        'Flywire',
    ];

    function ViewFlywireDisbursementController($scope, $stateParams, Core, ObjectDeepLink, Money, Flywire) {
        $scope.loading = 0;
        $scope.disbursement = {};
        $scope.payouts = [];
        $scope.transactions = [];
        $scope.transactionPage = 1;
        $scope.transactionsTab = 'payments';
        $scope.loadedTransactions = false;

        let transactionsPerPage = 25;

        $scope.goToObject = function (id) {
            ObjectDeepLink.goTo('payment', id);
        };

        ++$scope.loading;
        Flywire.getDisbursement(
            { id: $stateParams.id },
            function (disbursement) {
                $scope.setTransactionsTab(disbursement, $scope.transactionsTab);
                $scope.disbursement = disbursement;
                --$scope.loading;
            },
            function (result) {
                --$scope.loading;
                Core.showMessage(result.data.message, 'error');
            },
        );

        $scope.setTransactionsTab = function (disbursement, tab) {
            $scope.transactionsTab = tab;
            $scope.transactionPage = 1;
            loadTransactionData(disbursement, tab);
        };

        $scope.prevTransactionPage = function (disbursement) {
            $scope.transactionPage--;
            loadTransactionData(disbursement, $scope.transactionsTab);
        };

        $scope.nextTransactionPage = function (disbursement) {
            $scope.transactionPage++;
            loadTransactionData(disbursement, $scope.transactionsTab);
        };

        function loadTransactionData(disbursement, tab) {
            $scope.loadedTransactions = false;

            if (tab === 'payments') {
                loadPayments(disbursement.id);
            } else if (tab === 'refunds') {
                loadRefunds(disbursement.id);
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
            $scope.loadedTransactions = true;
        }

        function loadPayments(id) {
            Flywire.getDisbursementPayouts(
                {
                    id: id,
                    expand: 'payment',
                },
                function (payouts, headers) {
                    processLoadedTransactions(payouts, headers);
                },
                function (result) {
                    $scope.loadedTransactions = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadRefunds(id) {
            Flywire.findAllRefunds(
                {
                    expand: 'ar_refund,payment',
                    'filter[disbursement]': id,
                },
                function (refunds, headers) {
                    processLoadedTransactions(refunds, headers);
                },
                function (result) {
                    $scope.loadedTransactions = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
