/* globals moment */
(function () {
    'use strict';

    angular
        .module('app.integrations')
        .controller('QuickBooksOnlineSettingsController', QuickBooksOnlineSettingsController);

    QuickBooksOnlineSettingsController.$inject = [
        '$scope',
        '$filter',
        '$timeout',
        'AppDirectory',
        'Integration',
        'CustomField',
        'Core',
        'selectedCompany',
        'Feature',
        'DatePickerService',
        'AccountingSyncSettings',
    ];

    function QuickBooksOnlineSettingsController(
        $scope,
        $filter,
        $timeout,
        AppDirectory,
        Integration,
        CustomField,
        Core,
        selectedCompany,
        Feature,
        DatePickerService,
        AccountingSyncSettings,
    ) {
        let escapeHtml = $filter('escapeHtml');
        $scope.hasFeature = Feature.hasFeature('accounting_sync');
        $scope.connectUrl = AppDirectory.get('quickbooks_online').connectUrl + '?company=' + selectedCompany.id;
        $scope.loading = 0;

        $scope.customFieldsMapping = [];

        $scope.select2Options = {
            depositToAccount: {
                data: [],
                formatSelection: formatAccountName,
                formatResult: formatAccountResult,
                placeholder: 'Select an account',
                width: '100%',
            },
            incomeAccount: {
                data: [],
                formatSelection: formatAccountName,
                formatResult: formatAccountResult,
                placeholder: 'Select an account',
                width: '100%',
            },
            taxCode: {
                data: [],
                formatSelection: formatTaxCodeName,
                formatResult: formatTaxCodeResult,
                placeholder: 'Select a tax code',
                width: '100%',
            },
            customFields: {
                data: [],
                formatSelection: formatCustomFieldName,
                formatResult: formatCustomFieldResult,
                placeholder: 'Select a custom field on Invoiced',
                width: '100%',
            },
            customFieldsQbo: {
                data: [],
                formatSelection: formatQboCustomFieldName,
                formatResult: formatQboCustomFieldResult,
                placeholder: 'Select a custom field on QuickBooks',
                width: '100%',
            },
        };

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.tab = 'behavior';
        $scope.merchantAccounts = [];

        $scope.numCustomFields = 3;
        let customFieldToken = ':-:';

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        $scope.addCustomFieldMapping = function () {
            $scope.customFieldsMapping.push({
                qbo: '',
                invoiced: '',
            });
        };

        $scope.addPaymentAccount = function () {
            AccountingSyncSettings.addPaymentAccount($scope.syncProfile.payment_accounts, true);
        };

        $scope.deletePaymentAccount = function (i) {
            AccountingSyncSettings.deletePaymentAccount($scope.syncProfile.payment_accounts, i);
        };

        $scope.removeCustomFieldMapping = function (i) {
            $scope.customFieldsMapping.splice(i, 1);
        };

        $scope.save = saveSettings;

        loadSettings();
        loadMerchantAccounts();

        function loadSettings() {
            $scope.loading++;
            $scope.error = null;

            AccountingSyncSettings.loadSettings(
                'quickbooks_online',
                (integration, syncProfile) => {
                    $scope.loading--;

                    $scope.connected = integration.connected;
                    $scope.integration = integration;
                    $scope.syncProfile = syncProfile;

                    if (integration.extra.uses_payments) {
                        $scope.connectUrl += '&payments=1';
                    }

                    angular.forEach(syncProfile.payment_accounts, function (rule) {
                        rule.quickbooks_no_account = !rule.account;
                    });

                    $scope.syncAllInvoices = !$scope.syncProfile.invoice_start_date;
                    $scope.mapToExistingCustomers = !$scope.syncProfile.namespace_customers;
                    $scope.mapToExistingInvoices = !$scope.syncProfile.namespace_invoices;
                    $scope.mapToExistingItems = !$scope.syncProfile.namespace_items;

                    if (!$scope.syncAllInvoices) {
                        $scope.syncProfile.invoice_start_date = moment
                            .unix($scope.syncProfile.invoice_start_date)
                            .toDate();
                    }

                    // parse custom fields mapping
                    for (let i = 0; i < $scope.numCustomFields; i++) {
                        let k = 'custom_field_' + (i + 1);
                        if ($scope.syncProfile[k]) {
                            let parts = $scope.syncProfile[k].split(customFieldToken);
                            $scope.customFieldsMapping.push({
                                invoiced: parts[0],
                                qbo: parts[1],
                            });
                        }
                    }
                },
                () => {
                    $scope.loading--;
                },
            );

            $scope.loading++;
            Integration.settings(
                {
                    id: 'quickbooks_online',
                },
                function (result) {
                    $scope.loading--;

                    // Deposit-To Account
                    $scope.select2Options.depositToAccount.data = result.deposit_to_accounts;
                    angular.forEach($scope.select2Options.depositToAccount.data, function (account) {
                        account.id = account.name;
                        account.text = account.fully_qualified_name;
                    });

                    // Income Account
                    $scope.select2Options.incomeAccount.data = result.income_accounts;
                    angular.forEach($scope.select2Options.incomeAccount.data, function (account) {
                        account.id = account.name;
                        account.text = account.fully_qualified_name;
                    });

                    // Tax Code
                    $scope.select2Options.taxCode.data = result.tax_codes;
                    angular.forEach($scope.select2Options.taxCode.data, function (account) {
                        account.id = account.name;
                        account.text = account.name;
                    });

                    // Custom Fields
                    $scope.select2Options.customFieldsQbo.data = result.custom_fields;
                    angular.forEach($scope.select2Options.customFieldsQbo.data, function (customField) {
                        customField.id = customField.id;
                        customField.text = customField.name;
                    });
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );

            loadCustomFields();
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

        function loadCustomFields() {
            $scope.loading++;
            CustomField.all(
                function (customFields) {
                    $scope.loading--;

                    $scope.select2Options.customFields.data = [];
                    angular.forEach(customFields, function (customField) {
                        if (customField.object === 'invoice') {
                            $scope.select2Options.customFields.data.push({
                                id: customField.id,
                                text: customField.name,
                                name: customField.name,
                            });
                        }
                    });
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
                read_invoices: syncProfile.read_invoices,
                read_payments: syncProfile.read_payments,
                read_invoices_as_drafts: syncProfile.read_invoices_as_drafts,
                read_pdfs: syncProfile.read_pdfs,
                undeposited_funds_account: syncProfile.undeposited_funds_account,
                discount_account: syncProfile.discount_account,
                match_tax_rates: syncProfile.match_tax_rates,
                tax_code: syncProfile.tax_code,
                invoice_start_date: syncProfile.invoice_start_date,
                namespace_customers: !$scope.mapToExistingCustomers,
                namespace_invoices: !$scope.mapToExistingInvoices,
                namespace_items: !$scope.mapToExistingItems,
                write_customers: syncProfile.write_customers,
                write_credit_notes: syncProfile.write_credit_notes,
                write_invoices: syncProfile.write_invoices,
                write_payments: syncProfile.write_payments,
                write_convenience_fees: syncProfile.write_convenience_fees,
                read_customers: syncProfile.read_customers,
                read_credit_notes: syncProfile.read_credit_notes,
                payment_accounts: AccountingSyncSettings.buildPaymentAccountsForSave(
                    'quickbooks_online',
                    syncProfile.payment_accounts,
                    err => {
                        $scope.saving = false;
                        $scope.error = err;
                    },
                ),
            };

            if (params.undeposited_funds_account !== null && typeof params.undeposited_funds_account === 'object') {
                params.undeposited_funds_account = params.undeposited_funds_account.name;
            }

            if (params.discount_account !== null && typeof params.discount_account === 'object') {
                params.discount_account = params.discount_account.name;
            }

            if (params.tax_code !== null && typeof params.tax_code === 'object') {
                params.tax_code = params.tax_code.name;
            }

            if ($scope.syncAllInvoices) {
                params.invoice_start_date = null;
            } else {
                params.invoice_start_date = moment(params.invoice_start_date).unix();
            }

            for (let i = 0; i < $scope.numCustomFields; i++) {
                let k = 'custom_field_' + (i + 1);
                if (typeof $scope.customFieldsMapping[i] === 'object') {
                    let mapping = $scope.customFieldsMapping[i];
                    params[k] = [mapping.invoiced.id, mapping.qbo.id, mapping.qbo.name].join(customFieldToken);
                } else {
                    params[k] = null;
                }
            }

            if ($scope.error) {
                return;
            }

            Integration.editSyncProfile(
                {
                    id: 'quickbooks_online',
                },
                params,
                function () {
                    $scope.saving = false;
                    Core.flashMessage('Your QuickBooks Online settings have been saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function formatAccountName(account) {
            return escapeHtml(account.name);
        }

        function formatAccountResult(account) {
            let html = "<div class='title'>" + escapeHtml(account.name) + '</div>';

            if (account.subaccount) {
                html += "<div class='details'>" + escapeHtml(account.fully_qualified_name) + '</div>';
            }

            return html;
        }

        function formatTaxCodeName(account) {
            return escapeHtml(account.name);
        }

        function formatTaxCodeResult(account) {
            let html = "<div class='title'>" + escapeHtml(account.name) + '</div>';

            if (account.description) {
                html += "<div class='details'>" + escapeHtml(account.description) + '</div>';
            }

            return html;
        }

        function formatQboCustomFieldName(customField) {
            return escapeHtml(customField.name);
        }

        function formatQboCustomFieldResult(customField) {
            return "<div class='title'>" + escapeHtml(customField.name) + '</div>';
        }

        function formatCustomFieldName(customField) {
            return escapeHtml(customField.name);
        }

        function formatCustomFieldResult(customField) {
            return "<div class='title'>" + escapeHtml(customField.name) + '</div>';
        }
    }
})();
