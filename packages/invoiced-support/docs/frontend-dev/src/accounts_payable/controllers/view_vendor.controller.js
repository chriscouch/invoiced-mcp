/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('ViewVendorController', ViewVendorController);

    ViewVendorController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$modal',
        '$rootScope',
        'LeavePageWarning',
        'Vendor',
        'Core',
        'BrowsingHistory',
        'Network',
        'Bill',
        'VendorCredit',
        'VendorPayment',
        'NetworkDocument',
        'Feature',
    ];

    function ViewVendorController(
        $scope,
        $state,
        $controller,
        $modal,
        $rootScope,
        LeavePageWarning,
        Vendor,
        Core,
        BrowsingHistory,
        Network,
        Bill,
        VendorCredit,
        VendorPayment,
        NetworkDocument,
        Feature,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Vendor;
        $scope.modelTitleSingular = 'Vendor';
        $scope.modelObjectType = 'vendor';

        //
        // Presets
        //

        $scope.transactions = [];
        $scope.transactionPage = 1;
        $scope.transactionsTab = 'bills';
        $scope.invitedToNetwork = null;
        let actionItems = [];

        let transactionsPerPage = 5;

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'approval_workflow';
            findParams.include = 'address';
        };

        $scope.postFind = function (vendor) {
            $scope.vendor = vendor;

            $rootScope.modelTitle = vendor.name;
            Core.setTitle(vendor.name);

            BrowsingHistory.push({
                id: vendor.id,
                type: 'vendor',
                title: vendor.name,
            });

            loadNetworkProfile(vendor);
            $scope.setTransactionsTab(vendor, 'bills');
            loadBalance(vendor.id);
            loadNetworkStatus(vendor.id);
            computeActionItems();

            return $scope.vendor;
        };

        $scope.isLoaded = function () {
            return $scope.loaded.networkProfile && $scope.loaded.balance;
        };

        $scope.editModal = function (vendor) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/vendors/edit.html',
                controller: 'EditVendorController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    model: function () {
                        return vendor;
                    },
                },
            });

            modalInstance.result.then(
                function (c) {
                    LeavePageWarning.unblock();

                    angular.extend(vendor, c);

                    $rootScope.modelTitle = vendor.name;

                    Core.setTitle(vendor.name);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        function loadNetworkStatus(vendorId) {
            $scope.invitedToNetwork = null;

            if (!Feature.hasFeature('network')) {
                return;
            }

            Network.invitations(
                {
                    'filter[vendor]': vendorId,
                },
                function (results) {
                    $scope.invitedToNetwork = false;
                    if (results.length > 0) {
                        $scope.invitedToNetwork = true;
                        $scope.invitedToNetworkEmail = results[0].email;
                    }
                    computeActionItems();
                },
                function () {
                    $scope.invitedToNetwork = false;
                    computeActionItems();
                },
            );
        }

        $scope.inviteToNetwork = function (vendor) {
            const modalInstance = $modal.open({
                templateUrl: 'network/views/invite.html',
                controller: 'InviteToNetworkController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    vendor: function () {
                        return vendor;
                    },
                    customer: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Your invitation has been sent', 'success');
                    $scope.invitedToNetwork = !vendor.network_connection; // Vendor could have been invited or existing connection assigned
                    computeActionItems();
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteNetworkConnection = function (vendor) {
            vex.dialog.confirm({
                message:
                    'Are you sure you want to remove this vendor from your network? No data or transactions will be deleted.',
                callback: function (result) {
                    if (result) {
                        Network.deleteConnection(
                            {
                                id: vendor.network_connection,
                                type: 'vendors',
                            },
                            function () {
                                vendor.active = false;
                                vendor.network_connection = null;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.hasActionItem = function (k) {
            return actionItems.indexOf(k) !== -1;
        };

        /* Transactions Card */

        $scope.setTransactionsTab = function (vendor, tab) {
            $scope.transactionsTab = tab;
            $scope.transactionPage = 1;
            loadTransactionData(vendor, tab);
        };

        function loadTransactionData(vendor, tab) {
            $scope.loaded.transactions = false;

            if (tab === 'network_documents') {
                if (vendor.network_connection) {
                    loadDocuments(vendor.network_connection);
                } else {
                    $scope.loaded.transactions = true;
                }
            } else if (tab === 'bills') {
                loadBills(vendor.id);
            } else if (tab === 'vendor_credits') {
                loadVendorCredits(vendor.id);
            } else if (tab === 'vendor_payments') {
                loadVendorPayments(vendor.id);
            }
        }

        $scope.prevTransactionPage = function (vendor) {
            $scope.transactionPage--;
            loadTransactionData(vendor, $scope.transactionsTab);
        };

        $scope.nextTransactionPage = function (vendor) {
            $scope.transactionPage++;
            loadTransactionData(vendor, $scope.transactionsTab);
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Vendor');

        function loadNetworkProfile(vendor) {
            if (!vendor.network_connection) {
                $scope.loaded.networkProfile = true;
                return;
            }

            $scope.loaded.networkProfile = false;
            Network.findVendor(
                { id: vendor.network_connection },
                function (vendor) {
                    $scope.networkProfile = vendor;
                    $scope.loaded.networkProfile = true;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.loaded.networkProfile = true;
                },
            );
        }

        function loadBills(id) {
            let params = {
                'filter[vendor]': id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'date DESC',
            };

            Bill.findAll(params, processLoadedTransactions, function (result) {
                $scope.loaded.transactions = true;
                Core.showMessage(result.data.message, 'error');
            });
        }

        function loadVendorCredits(id) {
            let params = {
                'filter[vendor]': id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'date DESC',
            };

            VendorCredit.findAll(params, processLoadedTransactions, function (result) {
                $scope.loaded.transactions = true;
                Core.showMessage(result.data.message, 'error');
            });
        }

        function loadVendorPayments(id) {
            let params = {
                'filter[vendor]': id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'date DESC',
            };

            VendorPayment.findAll(params, processLoadedTransactions, function (result) {
                $scope.loaded.transactions = true;
                Core.showMessage(result.data.message, 'error');
            });
        }

        function loadDocuments(id) {
            let params = {
                from: id,
                per_page: transactionsPerPage,
                page: $scope.transactionPage,
                sort: 'created_at DESC',
                sent: '0',
            };

            NetworkDocument.findAll(params, processLoadedTransactions, function (result) {
                $scope.loaded.transactions = true;
                Core.showMessage(result.data.message, 'error');
            });
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
            $scope.loaded.transactions = true;
        }

        function loadBalance(id) {
            if ($scope.loaded.balance || !id) {
                return;
            }

            Vendor.balance(
                {
                    id: id,
                },
                function (balance) {
                    $scope.balance = balance;
                    $scope.loaded.balance = true;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.loaded.balance = true;
                },
            );
        }

        function computeActionItems() {
            let vendor = $scope.vendor;

            // Needs invitation
            if (!vendor.network_connection && $scope.invitedToNetwork !== null) {
                if (!$scope.invitedToNetwork) {
                    actionItems = ['needs_invitation'];
                } else {
                    actionItems = ['invited'];
                }
            }
        }
    }
})();
