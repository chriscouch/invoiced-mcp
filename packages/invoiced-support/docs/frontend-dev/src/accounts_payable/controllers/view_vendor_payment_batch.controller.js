/* globals vex */
(function () {
    'use strict';

    angular
        .module('app.accounts_payable')
        .controller('ViewVendorPaymentBatchController', ViewVendorPaymentBatchController);

    ViewVendorPaymentBatchController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        '$modal',
        '$q',
        '$timeout',
        'Core',
        'VendorPaymentBatch',
        'VendorPayment',
        'BrowsingHistory',
        'PaymentDisplayHelper',
    ];

    function ViewVendorPaymentBatchController(
        $scope,
        $controller,
        $rootScope,
        $modal,
        $q,
        $timeout,
        Core,
        VendorPaymentBatch,
        VendorPayment,
        BrowsingHistory,
        PaymentDisplayHelper,
    ) {
        $scope.loading = true;
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = VendorPaymentBatch;
        $scope.modelTitleSingular = 'Payment Batch';
        $scope.modelObjectType = 'vendor_payment_batch';

        $scope.printable = false;
        $scope.hasPaymentFile = false;
        $scope.payments = [];
        $scope.paymentPage = 1;
        $scope.items = [];
        $scope.failures = [];
        let reload;
        let reloadCount = 0;

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'bank_account,card,member';
        };

        $scope.print = function (paymentBatch) {
            $scope.printing = true;
            VendorPaymentBatch.printCheck(
                paymentBatch,
                function () {
                    $scope.printing = false;
                },
                function (error) {
                    $scope.printing = false;
                    Core.flashMessage(error.message, 'error');
                },
            );
        };

        $scope.paymentFile = function (paymentBatch) {
            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/payment_batches/payment-file.html',
                controller: 'GeneratePaymentFileController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(function (effectiveDate) {
                $scope.generating = true;
                VendorPaymentBatch.downloadPaymentFile(
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
            VendorPaymentBatch.pay(
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
                        VendorPaymentBatch.delete(
                            {
                                id: paymentBatch.id,
                            },
                            function (paymentBatch2) {
                                paymentBatch.status = paymentBatch2.status;
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

            if (paymentBatch.card) {
                paymentBatch.card.name = PaymentDisplayHelper.formatCard(
                    paymentBatch.card.brand,
                    paymentBatch.card.last4,
                );
            }

            $rootScope.modelTitle = paymentBatch.number;
            Core.setTitle(paymentBatch.number);

            BrowsingHistory.push({
                id: paymentBatch.id,
                type: 'vendor_payment_batch',
                title: paymentBatch.number,
            });

            $scope.loading = false;
            $scope.printable = paymentBatch.payment_method === 'print_check' && paymentBatch.status === 'Finished';
            $scope.hasPaymentFile = paymentBatch.payment_method === 'ach' && paymentBatch.status === 'Finished';
            $scope.payable = paymentBatch.status === 'Created';
            $scope.voidable = paymentBatch.status === 'Created';

            loadPaymentItems(paymentBatch.id);

            if (paymentBatch.status === 'Finished' || paymentBatch.status === 'Voided') {
                loadVendorPayments(paymentBatch.id);
            }

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
            loadVendorPayments(paymentBatch.id);
        };

        $scope.nextPaymentPage = function (paymentBatch) {
            $scope.paymentPage++;
            loadVendorPayments(paymentBatch.id);
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

        function loadVendorPayments(id) {
            let paymentsPerPage = 10;
            let params = {
                'filter[vendor_payment_batch]': id,
                expand: 'vendor',
                per_page: paymentsPerPage,
                page: $scope.paymentPage,
            };
            $scope.loadedPayments = false;

            VendorPayment.findAll(
                params,
                function (payments, headers) {
                    $scope.totalPayments = headers('X-Total-Count');
                    let links = Core.parseLinkHeader(headers('Link'));

                    // compute page count from pagination links
                    $scope.paymentPageCount = links.last.match(/[\?\&]page=(\d+)/)[1];

                    let start = ($scope.paymentPage - 1) * paymentsPerPage + 1;
                    let end = start + payments.length - 1;
                    $scope.paymentRange = start + '-' + end;

                    $scope.payments = payments;
                    $scope.loadedPayments = true;
                },
                function (result) {
                    $scope.loadedPayments = true;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadPaymentItems(id) {
            if ($scope.loadingItems) {
                return;
            }

            $scope.loadingItems = true;
            $scope.items = [];

            let finishItems = function () {
                $scope.loadingItems = false;
                $scope.failures = [];
                angular.forEach($scope.items, function (item) {
                    if (item.error) {
                        $scope.failures.push(item);
                    }
                });
            };

            let loadItems = function (page) {
                VendorPaymentBatch.getItems(
                    {
                        id: id,
                        page: page,
                        expand: 'vendor',
                    },
                    function (items, headers) {
                        $scope.items = $scope.items.concat(items);
                        let links = Core.parseLinkHeader(headers('Link'));
                        if (links.next) {
                            // Load the next page
                            loadItems(page + 1);
                        } else {
                            // We're finished loading all pages
                            finishItems();
                        }
                    },
                    function (result) {
                        $scope.loadingItems = false;
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            };

            loadItems(1);
        }
    }
})();
