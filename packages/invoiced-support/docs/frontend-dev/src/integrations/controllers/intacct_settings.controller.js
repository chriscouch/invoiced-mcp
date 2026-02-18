/* globals moment */
(function () {
    'use strict';

    angular.module('app.integrations').controller('IntacctSettingsController', IntacctSettingsController);

    IntacctSettingsController.$inject = [
        '$scope',
        '$filter',
        '$modal',
        '$timeout',
        '$http',
        '$translate',
        'InvoicedConfig',
        'Integration',
        'Core',
        'Feature',
        'DatePickerService',
        'AccountingSyncSettings',
    ];

    function IntacctSettingsController(
        $scope,
        $filter,
        $modal,
        $timeout,
        $http,
        $translate,
        InvoicedConfig,
        Integration,
        Core,
        Feature,
        DatePickerService,
        AccountingSyncSettings,
    ) {
        let escapeHtml = $filter('escapeHtml');
        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');

        $scope.loading = 0;

        $scope.select2OptionsGlAccount = {
            data: [],
            formatSelection: formatAccountName,
            formatResult: formatAccountResult,
            width: '100%',
            placeholder: $translate.instant('intacct.select_account'),
        };

        $scope.select2OptionsBankAccount = {
            data: [],
            formatSelection: formatAccountName,
            formatResult: formatAccountResult,
            width: '100%',
            placeholder: $translate.instant('intacct.select_account'),
        };

        $scope.select2OptionsInvoice = {
            width: '100%',
            multiple: true,
            simple_tags: true,
            tags: [],
            placeholder: $translate.instant('intacct.select_document_type'),
        };

        $scope.select2OptionsCreditNote = {
            width: '100%',
            multiple: true,
            simple_tags: true,
            tags: [],
            placeholder: $translate.instant('intacct.select_document_type'),
        };

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.tab = 'behavior';
        $scope.merchantAccounts = [];

        $scope.addPaymentAccount = function () {
            AccountingSyncSettings.addPaymentAccount($scope.syncProfile.payment_accounts, 'undeposited_funds');
        };

        $scope.deletePaymentAccount = function (i) {
            AccountingSyncSettings.deletePaymentAccount($scope.syncProfile.payment_accounts, i);
        };

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
        $scope.connect = connectIntacct;

        loadSettings();
        loadMerchantAccounts();

        function loadSettings() {
            $scope.loading++;
            $scope.error = null;
            $scope.salesDocumentError = null;

            AccountingSyncSettings.loadSettings(
                'intacct',
                (integration, syncProfile) => {
                    $scope.loading--;

                    $scope.connected = integration.connected;
                    $scope.integration = integration;
                    $scope.syncProfile = syncProfile;

                    angular.forEach(syncProfile.payment_accounts, function (rule) {
                        if (rule.undeposited_funds) {
                            rule.intacct_undeposited_funds_account = rule.account;
                            rule.intacct_type = 'undeposited_funds';
                        } else {
                            rule.intacct_bank_account = rule.account;
                            rule.intacct_type = 'bank_account';
                        }
                    });

                    $scope.syncAllInvoices = !$scope.syncProfile.invoice_start_date;

                    if (!$scope.syncAllInvoices) {
                        $scope.syncProfile.invoice_start_date = moment
                            .unix($scope.syncProfile.invoice_start_date)
                            .toDate();
                    }

                    $scope.hasLineItemDimensions =
                        !!$scope.syncProfile.item_location_id || !!$scope.syncProfile.item_department_id;
                    $scope.hasLocationIdFilter = !!$scope.syncProfile.invoice_location_id_filter;
                    $scope.hasOverpaymentDimensions =
                        !!$scope.syncProfile.overpayment_location_id || !!$scope.syncProfile.overpayment_department_id;
                },
                () => {
                    $scope.loading--;
                },
            );

            $scope.loading++;
            Integration.settings(
                {
                    id: 'intacct',
                },
                function (result) {
                    $scope.loading--;

                    // G/L Accounts
                    $scope.select2OptionsGlAccount.data = result.gl_accounts;
                    angular.forEach($scope.select2OptionsGlAccount.data, function (account) {
                        account.id = account.code;
                        account.text = account.name;
                    });

                    // G/L Accounts
                    $scope.select2OptionsBankAccount.data = result.bank_accounts;
                    angular.forEach($scope.select2OptionsBankAccount.data, function (account) {
                        account.id = account.code;
                        account.text = account.name;
                    });
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );

            $scope.loading++;
            Integration.intacctSalesDocumentTypes(
                function (result) {
                    $scope.loading--;
                    $scope.select2OptionsInvoice.tags = result.invoice_types;
                    $scope.select2OptionsCreditNote.tags = result.return_types;
                },
                function (result) {
                    $scope.loading--;
                    $scope.salesDocumentError = result.data;
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
                credit_note_types: syncProfile.credit_note_types,
                invoice_types: syncProfile.invoice_types,
                read_ar_adjustments: syncProfile.read_ar_adjustments,
                read_credit_notes: syncProfile.read_credit_notes,
                write_credit_notes: syncProfile.write_credit_notes,
                read_customers: syncProfile.read_customers,
                write_customers: syncProfile.write_customers,
                customer_top_level: syncProfile.customer_top_level,
                read_invoices: syncProfile.read_invoices,
                read_invoices_as_drafts: syncProfile.read_invoices_as_drafts,
                write_invoices: syncProfile.write_invoices,
                read_payments: syncProfile.read_payments,
                write_payments: syncProfile.write_payments,
                write_convenience_fees: syncProfile.write_convenience_fees,
                read_pdfs: syncProfile.read_pdfs,
                auto_sync: syncProfile.auto_sync,
                undeposited_funds_account: syncProfile.undeposited_funds_account,
                item_account: syncProfile.item_account,
                convenience_fee_account: syncProfile.convenience_fee_account,
                bad_debt_account: syncProfile.bad_debt_account,
                invoice_start_date: syncProfile.invoice_start_date,
                map_catalog_item_to_item_id: syncProfile.map_catalog_item_to_item_id,
                payment_accounts: AccountingSyncSettings.buildPaymentAccountsForSave(
                    'intacct',
                    syncProfile.payment_accounts,
                    err => {
                        $scope.saving = false;
                        $scope.error = err;
                    },
                    true,
                ),
            };

            if (typeof syncProfile.undeposited_funds_account === 'object' && syncProfile.undeposited_funds_account) {
                params.undeposited_funds_account = syncProfile.undeposited_funds_account.code;
            } else {
                params.undeposited_funds_account = null;
            }

            if (typeof syncProfile.item_account === 'object' && syncProfile.item_account) {
                params.item_account = syncProfile.item_account.code;
            } else {
                params.item_account = null;
            }

            if (typeof syncProfile.convenience_fee_account === 'object' && syncProfile.convenience_fee_account) {
                params.convenience_fee_account = syncProfile.convenience_fee_account.code;
            } else {
                params.convenience_fee_account = null;
            }

            if (typeof syncProfile.bad_debt_account === 'object' && syncProfile.bad_debt_account) {
                params.bad_debt_account = syncProfile.bad_debt_account.code;
            } else {
                params.bad_debt_account = null;
            }

            if ($scope.syncAllInvoices) {
                params.invoice_start_date = null;
            } else {
                params.invoice_start_date = moment(params.invoice_start_date).unix();
            }

            if ($scope.hasLineItemDimensions) {
                params.item_location_id = syncProfile.item_location_id;
                params.item_department_id = syncProfile.item_department_id;
            } else {
                params.item_location_id = null;
                params.item_department_id = null;
            }

            if ($scope.hasOverpaymentDimensions) {
                params.overpayment_location_id = syncProfile.overpayment_location_id;
                params.overpayment_department_id = syncProfile.overpayment_department_id;
            } else {
                params.overpayment_location_id = null;
                params.overpayment_department_id = null;
            }

            if ($scope.hasLocationIdFilter) {
                params.invoice_location_id_filter = syncProfile.invoice_location_id_filter;
            } else {
                params.invoice_location_id_filter = null;
            }

            if ($scope.error) {
                return;
            }

            Integration.editSyncProfile(
                {
                    id: 'intacct',
                },
                params,
                function () {
                    $scope.saving = false;
                    Core.flashTranslatedMessage('settings.general.settings_have_been_saved', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function connectIntacct() {
            const modalInstance = $modal.open({
                templateUrl: 'integrations/views/connect-intacct.html',
                controller: 'ConnectIntacctController',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(function () {
                loadSettings();
            });
        }

        function formatAccountName(account) {
            return escapeHtml(account.code) + ' - ' + escapeHtml(account.name);
        }

        function formatAccountResult(account) {
            return (
                "<div class='title'>" +
                escapeHtml(account.name) +
                '</div>' +
                "<div class='details'>" +
                escapeHtml(account.code) +
                '</div>'
            );
        }
    }
})();
