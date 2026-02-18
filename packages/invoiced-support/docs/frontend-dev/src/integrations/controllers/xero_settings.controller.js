/* globals moment */
(function () {
    'use strict';

    angular.module('app.integrations').controller('XeroSettingsController', XeroSettingsController);

    XeroSettingsController.$inject = [
        '$scope',
        '$filter',
        '$timeout',
        'AppDirectory',
        'Integration',
        'Core',
        'selectedCompany',
        'Feature',
        'DatePickerService',
        'AccountingSyncSettings',
    ];

    function XeroSettingsController(
        $scope,
        $filter,
        $timeout,
        AppDirectory,
        Integration,
        Core,
        selectedCompany,
        Feature,
        DatePickerService,
        AccountingSyncSettings,
    ) {
        let escapeHtml = $filter('escapeHtml');
        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');
        $scope.connectUrl = AppDirectory.get('xero').connectUrl + '?company=' + selectedCompany.id + '&r=settings';
        $scope.loading = 0;

        $scope.select2Options = {
            depositToAccount: {
                data: [],
                formatSelection: formatAccountName,
                formatResult: formatAccountResult,
                placeholder: 'Select an account',
                width: '100%',
            },
            salesAccount: {
                data: [],
                formatSelection: formatAccountName,
                formatResult: formatAccountResult,
                placeholder: 'Select an account',
                width: '100%',
            },
            discountAccount: {
                data: [],
                formatSelection: formatAccountName,
                formatResult: formatAccountResult,
                placeholder: 'Select an account',
                width: '100%',
            },
            salesTaxAccount: {
                data: [],
                formatSelection: formatAccountName,
                formatResult: formatAccountResult,
                placeholder: 'Select an account',
                width: '100%',
            },
            taxType: {
                data: [],
                formatSelection: formatTaxType,
                formatResult: formatTaxTypeResult,
                placeholder: 'Select a tax rate',
                width: '100%',
            },
        };

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.tab = 'behavior';
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
                'xero',
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

            $scope.loading++;
            Integration.settings(
                {
                    id: 'xero',
                },
                function (result) {
                    $scope.loading--;

                    // Invoiced Account
                    $scope.select2Options.depositToAccount.data = result.bank_accounts;
                    angular.forEach($scope.select2Options.depositToAccount.data, function (account) {
                        account.id = account.AccountID;
                        account.text = account.Name;
                    });

                    // Sales Account
                    $scope.select2Options.salesAccount.data = result.sales_accounts;
                    angular.forEach($scope.select2Options.salesAccount.data, function (account) {
                        account.id = account.Code;
                        account.text = account.Name;
                    });

                    // Discount Account
                    $scope.select2Options.discountAccount.data = result.sales_accounts.concat(result.expense_accounts);
                    angular.forEach($scope.select2Options.discountAccount.data, function (account) {
                        account.id = account.Code;
                        account.text = account.Name;
                    });

                    // Sales Tax Account
                    $scope.select2Options.salesTaxAccount.data = result.liability_accounts;
                    angular.forEach($scope.select2Options.salesTaxAccount.data, function (account) {
                        account.id = account.Code;
                        account.text = account.Name;
                    });
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
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
                auto_sync: syncProfile.auto_sync,
                read_customers: syncProfile.read_customers,
                read_invoices: syncProfile.read_invoices,
                read_pdfs: syncProfile.read_pdfs,
                read_payments: syncProfile.read_payments,
                read_credit_notes: syncProfile.read_credit_notes,
                write_customers: syncProfile.write_customers,
                write_invoices: syncProfile.write_invoices,
                write_payments: syncProfile.write_payments,
                write_credit_notes: syncProfile.write_credit_notes,
                write_convenience_fees: syncProfile.write_convenience_fees,
                undeposited_funds_account: syncProfile.undeposited_funds_account,
                item_account: syncProfile.item_account,
                discount_account: syncProfile.discount_account,
                sales_tax_account: syncProfile.sales_tax_account,
                convenience_fee_account: syncProfile.convenience_fee_account,
                send_item_code: syncProfile.send_item_code,
                tax_mode: syncProfile.tax_mode,
                read_invoices_as_drafts: syncProfile.read_invoices_as_drafts,
                invoice_start_date: syncProfile.invoice_start_date,
                payment_accounts: AccountingSyncSettings.buildPaymentAccountsForSave(
                    'xero',
                    syncProfile.payment_accounts,
                    err => {
                        $scope.saving = false;
                        $scope.error = err;
                    },
                ),
            };

            if (params.undeposited_funds_account !== null && typeof params.undeposited_funds_account === 'object') {
                params.undeposited_funds_account = params.undeposited_funds_account.Code;
            }

            if (params.item_account !== null && typeof params.item_account === 'object') {
                params.item_account = params.item_account.Code;
            }

            if (params.discount_account !== null && typeof params.discount_account === 'object') {
                params.discount_account = params.discount_account.Code;
            }

            if (params.sales_tax_account !== null && typeof params.sales_tax_account === 'object') {
                params.sales_tax_account = params.sales_tax_account.Code;
            }

            if (params.convenience_fee_account !== null && typeof params.convenience_fee_account === 'object') {
                params.convenience_fee_account = params.convenience_fee_account.Code;
            }

            if ($scope.syncAllInvoices) {
                params.invoice_start_date = null;
            } else {
                params.invoice_start_date = moment(params.invoice_start_date).unix();
            }

            Integration.editSyncProfile(
                {
                    id: 'xero',
                },
                params,
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your Xero settings have been saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function formatAccountName(account) {
            return escapeHtml(account.Name);
        }

        function formatAccountResult(account) {
            return (
                "<div class='title'>" +
                escapeHtml(account.Name) +
                '</div>' +
                "<div class='details'>" +
                escapeHtml(account.Code) +
                '</div>'
            );
        }

        function formatTaxType(taxRate) {
            return escapeHtml(taxRate.Name);
        }

        function formatTaxTypeResult(taxRate) {
            return (
                "<div class='title'>" +
                escapeHtml(taxRate.Name) +
                '</div>' +
                "<div class='details'>" +
                escapeHtml(taxRate.TaxType) +
                '</div>'
            );
        }
    }
})();
