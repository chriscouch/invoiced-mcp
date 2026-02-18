/* globals moment */
(function () {
    'use strict';

    angular.module('app.integrations').controller('SageAccountingSettingsController', SageAccountingSettingsController);

    SageAccountingSettingsController.$inject = [
        '$scope',
        '$timeout',
        'AppDirectory',
        'Integration',
        'Core',
        'selectedCompany',
        'DatePickerService',
        'AccountingSyncSettings',
    ];

    function SageAccountingSettingsController(
        $scope,
        $timeout,
        AppDirectory,
        Integration,
        Core,
        selectedCompany,
        DatePickerService,
        AccountingSyncSettings,
    ) {
        $scope.connectUrl = AppDirectory.get('sage_accounting').connectUrl + '?company=' + selectedCompany.id;
        $scope.loading = 0;

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.merchantAccounts = [];

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        $scope.addPaymentAccount = function () {
            AccountingSyncSettings.addPaymentAccount($scope.syncProfile.payment_accounts);
        };

        $scope.deletePaymentAccount = function (i) {
            AccountingSyncSettings.deletePaymentAccount($scope.syncProfile.payment_accounts, i);
        };

        $scope.save = saveSettings;

        loadSettings();
        loadMerchantAccounts();

        function loadSettings() {
            $scope.loading++;
            $scope.error = null;

            AccountingSyncSettings.loadSettings(
                'sage_accounting',
                (integration, syncProfile) => {
                    $scope.loading--;

                    $scope.connected = integration.connected;
                    $scope.integration = integration;
                    $scope.syncProfile = syncProfile;

                    $scope.syncAllInvoices = !$scope.syncProfile.invoice_start_date;

                    if (!$scope.syncAllInvoices) {
                        $scope.syncProfile.invoice_start_date = moment
                            .unix($scope.syncProfile.invoice_start_date)
                            .toDate();
                    }
                },
                () => {
                    $scope.loading--;
                },
            );
        }

        function loadMerchantAccounts() {
            $scope.loadingMerchantAccounts = true;
            AccountingSyncSettings.loadMerchantAccounts(
                function (result) {
                    $scope.loadingMerchantAccounts = false;
                    $scope.merchantAccounts = result;
                },
                function (err) {
                    $scope.loadingMerchantAccounts = false;
                    $scope.error = err;
                },
            );
        }

        function saveSettings(syncProfile) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                read_customers: syncProfile.read_customers,
                read_invoices: syncProfile.read_invoices,
                read_credit_notes: syncProfile.read_credit_notes,
                read_invoices_as_drafts: syncProfile.read_invoices_as_drafts,
                read_pdfs: syncProfile.read_pdfs,
                invoice_start_date: syncProfile.invoice_start_date,
                write_payments: syncProfile.write_payments,
                payment_accounts: AccountingSyncSettings.buildPaymentAccountsForSave(
                    'sage_accounting',
                    syncProfile.payment_accounts,
                    err => {
                        $scope.saving = false;
                        $scope.error = err;
                    },
                ),
            };

            if ($scope.syncAllInvoices) {
                params.invoice_start_date = null;
            } else {
                params.invoice_start_date = moment(params.invoice_start_date).unix();
            }

            if ($scope.error) {
                return;
            }

            if (!syncProfile.id) {
                params.integration = 'sage_accounting';
            }

            AccountingSyncSettings.saveSyncProfile(
                syncProfile,
                params,
                result => {
                    $scope.saving = false;
                    $scope.syncProfile.id = result.id;
                },
                err => {
                    $scope.saving = false;
                    $scope.error = err;
                },
            );
        }
    }
})();
