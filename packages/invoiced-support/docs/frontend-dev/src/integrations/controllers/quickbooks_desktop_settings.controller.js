/* globals moment */
(function () {
    'use strict';

    angular
        .module('app.integrations')
        .controller('QuickBooksDesktopSettingsController', QuickBooksDesktopSettingsController);

    QuickBooksDesktopSettingsController.$inject = [
        '$scope',
        '$timeout',
        '$modal',
        'Integration',
        'AccountingSyncProfile',
        'Core',
        'DatePickerService',
    ];

    function QuickBooksDesktopSettingsController(
        $scope,
        $timeout,
        $modal,
        Integration,
        AccountingSyncProfile,
        Core,
        DatePickerService,
    ) {
        $scope.loading = 0;
        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        $scope.save = saveSettings;
        $scope.connect = connectQuickBooksDesktop;

        loadSettings();

        function loadSettings() {
            $scope.loading++;
            $scope.error = null;

            Integration.retrieve(
                {
                    id: 'quickbooks_desktop',
                },
                function (integration) {
                    $scope.loading--;
                    $scope.integration = integration;
                    if (integration.extra.sync_profile) {
                        $scope.syncProfile = integration.extra.sync_profile;
                        // unix on invoice_start_date for picker compatibility
                        $scope.syncProfile.invoice_start_date = moment($scope.syncProfile.invoice_start_date).unix();
                    } else {
                        $scope.syncProfile = {
                            read_customers: true,
                            read_credit_notes: true,
                            read_invoices: true,
                            read_payments: true,
                            write_payments: true,
                            invoice_start_date: null,
                        };
                    }

                    $scope.syncAllInvoices = !$scope.syncProfile.invoice_start_date;
                    if (!$scope.syncAllInvoices) {
                        $scope.syncProfile.invoice_start_date = moment
                            .unix($scope.syncProfile.invoice_start_date)
                            .toDate();
                    }
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function saveSettings(syncProfile) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                read_customers: syncProfile.read_customers,
                read_credit_notes: syncProfile.read_credit_notes,
                read_invoices: syncProfile.read_invoices,
                read_payments: syncProfile.read_payments,
                write_payments: syncProfile.write_payments,
            };

            if ($scope.syncAllInvoices) {
                params.invoice_start_date = null;
            } else {
                params.invoice_start_date = moment(syncProfile.invoice_start_date).unix();
            }

            if (syncProfile.id) {
                AccountingSyncProfile.edit(
                    {
                        id: syncProfile.id,
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
                params.integration = 'quickbooks_desktop';
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

        function connectQuickBooksDesktop() {
            $modal.open({
                templateUrl: 'integrations/views/quickbooks-desktop-setup.html',
                controller: 'QuickBooksDesktopSetupController',
                backdrop: 'static',
                keyboard: false,
            });
        }
    }
})();
