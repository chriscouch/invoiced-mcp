/* globals vex */
(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('ViewCustomerPaymentBatchController', ViewCustomerPaymentBatchController);

    ViewCustomerPaymentBatchController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        '$modal',
        '$q',
        '$timeout',
        'Core',
        'CustomerPaymentBatch',
        'BrowsingHistory',
    ];

    function ViewCustomerPaymentBatchController(
        $scope,
        $controller,
        $rootScope,
        $modal,
        $q,
        $timeout,
        Core,
        CustomerPaymentBatch,
        BrowsingHistory,
    ) {
        $scope.loading = true;
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = CustomerPaymentBatch;
        $scope.modelTitleSingular = 'Payment Batch';
        $scope.modelObjectType = 'customer_payment_batch';

        $scope.hasPaymentFile = false;
        $scope.items = [];
        $scope.paymentPage = 1;
        let reload;
        let reloadCount = 0;

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'ach_file_format';
        };

        $scope.paymentFile = function (paymentBatch) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_receivable/views/payment_batches/payment-file.html',
                controller: 'GeneratePaymentFileController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(function (effectiveDate) {
                $scope.generating = true;
                CustomerPaymentBatch.downloadPaymentFile(
                    paymentBatch,
                    {
                        effective_date: effectiveDate,
                    },
                    function () {
                        $scope.generating = false;
                    },
                    function (error) {
                        $scope.generating = false;
                        Core.flashMessage(error.message, 'error');
                    },
                );
            });
        };

        $scope.pay = function (paymentBatch) {
            CustomerPaymentBatch.complete(
                {
                    id: paymentBatch.id,
                },
                {},
                function () {
                    Core.flashMessage('The payment batch has been queued for processing.', 'success');
                    $scope.find(paymentBatch.id);
                },
                function (result) {
                    Core.flashMessage(result.data.message, 'error');
                },
            );
        };

        $scope.delete = function (paymentBatch) {
            vex.dialog.confirm({
                message: 'Are you sure you want to void this payment batch? This operation is irreversible.',
                callback: function (result) {
                    if (result) {
                        CustomerPaymentBatch.delete(
                            {
                                id: paymentBatch.id,
                            },
                            function (paymentBatch2) {
                                paymentBatch.status = paymentBatch2.status;
                                $scope.hasPaymentFile = false;
                                $scope.payable = false;
                                $scope.voidable = false;
                                $scope.items = [];
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.postFind = function (paymentBatch) {
            $scope.paymentBatch = paymentBatch;

            $rootScope.modelTitle = paymentBatch.number;
            Core.setTitle(paymentBatch.number);

            BrowsingHistory.push({
                id: paymentBatch.id,
                type: 'customer_payment_batch',
                title: paymentBatch.number,
            });

            $scope.loading = false;
            $scope.hasPaymentFile = paymentBatch.payment_method === 'ach' && paymentBatch.status === 'Finished';
            $scope.payable = paymentBatch.status === 'Created';
            $scope.voidable = paymentBatch.status === 'Created';

            loadPaymentItems(paymentBatch.id);

            if (paymentBatch.status === 'Processing') {
                reload = $timeout(function () {
                    $scope.find($scope.modelId);
                }, Math.min(5000, reloadCount * 1000));
                reloadCount++;
            }

            return $scope.paymentBatch;
        };

        $scope.prevPaymentPage = function (paymentBatch) {
            $scope.paymentPage--;
            loadPaymentItems(paymentBatch.id);
        };

        $scope.nextPaymentPage = function (paymentBatch) {
            $scope.paymentPage++;
            loadPaymentItems(paymentBatch.id);
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Payment Batch');

        // cancel any reload promises after leaving the page
        $scope.$on('$stateChangeStart', function () {
            if (reload) {
                $timeout.cancel(reload);
            }
        });

        function loadPaymentItems(id) {
            let paymentsPerPage = 10;
            let params = {
                id: id,
                expand: 'charge.customer',
                per_page: paymentsPerPage,
                page: $scope.paymentPage,
            };
            $scope.loadingItems = true;

            CustomerPaymentBatch.getItems(
                params,
                function (items, headers) {
                    $scope.totalPayments = headers('X-Total-Count');
                    let links = Core.parseLinkHeader(headers('Link'));

                    // compute page count from pagination links
                    $scope.paymentPageCount = links.last.match(/[\?\&]page=(\d+)/)[1];

                    let start = ($scope.paymentPage - 1) * paymentsPerPage + 1;
                    let end = start + items.length - 1;
                    $scope.paymentRange = start + '-' + end;

                    $scope.items = items;
                    $scope.loadingItems = false;
                },
                function (result) {
                    $scope.loadingItems = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
