/* global moment */
(function () {
    'use strict';

    angular.module('app.integrations').controller('NetSuiteSettingsController', NetSuiteSettingsController);

    NetSuiteSettingsController.$inject = [
        '$scope',
        '$modal',
        '$timeout',
        'Integration',
        'Core',
        'AccountingSyncProfile',
    ];

    function NetSuiteSettingsController($scope, $modal, $timeout, Integration, Core, AccountingSyncProfile) {
        $scope.loading = 0;

        $scope.connect = connectNetSuite;
        $scope.syncProfile = {
            integration: 'netsuite',
            write_customers: false,
            write_invoices: false,
            write_credit_notes: false,
            invoice_start_date: null,
            parameters: {
                fallback_item_id: null,
                discountitem: null,
                taxlineitem: null,
                location: null,
            },
        };
        $scope.syncAllInvoices = true;
        $scope.save = save;

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        loadSettings();

        function loadSettings() {
            $scope.loading++;
            $scope.error = null;

            Integration.retrieve(
                {
                    id: 'netsuite',
                },
                function (integration) {
                    $scope.loading--;
                    $scope.connected = integration.connected;
                    $scope.integration = integration;
                    const syncProfile = integration.extra.sync_profile;
                    $scope.syncProfile = {
                        id: syncProfile.id,
                        integration: 'netsuite',
                        write_customers: syncProfile.write_customers,
                        write_invoices: syncProfile.write_invoices,
                        write_credit_notes: syncProfile.write_credit_notes,
                        invoice_start_date: syncProfile.invoice_start_date
                            ? moment.unix(syncProfile.invoice_start_date).toDate()
                            : null,
                        parameters: {
                            fallback_item_id: syncProfile.parameters.fallback_item_id,
                            discountitem: syncProfile.parameters.discountitem,
                            taxlineitem: syncProfile.parameters.taxlineitem,
                            location: syncProfile.parameters.location,
                        },
                    };
                    $scope.syncAllInvoices = $scope.syncProfile.invoice_start_date === null;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function connectNetSuite() {
            const modalInstance = $modal.open({
                templateUrl: 'integrations/views/connect-netsuite.html',
                controller: 'ConnectNetSuiteController',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(function () {
                loadSettings();
            });
        }

        function save() {
            $scope.saving = true;
            $scope.error = null;

            const params = angular.copy($scope.syncProfile);
            params.invoice_start_date =
                !$scope.syncAllInvoices && params.invoice_start_date ? moment(params.invoice_start_date).unix() : null;
            delete params.id;

            if ($scope.syncProfile.id) {
                AccountingSyncProfile.edit(
                    {
                        id: $scope.syncProfile.id,
                    },
                    params,
                    function () {
                        $scope.saving = false;
                        Core.flashMessage('Your integration settings have been saved', 'success');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                AccountingSyncProfile.create(
                    params,
                    function (result) {
                        $scope.saving = false;
                        Core.flashMessage('Your integration settings have been saved', 'success');
                        $scope.syncProfile.id = result.id;
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        }
    }
})();
