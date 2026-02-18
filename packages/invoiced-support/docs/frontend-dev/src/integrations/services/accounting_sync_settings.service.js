(function () {
    'use strict';

    angular.module('app.integrations').factory('AccountingSyncSettings', AccountingSyncSettings);

    AccountingSyncSettings.$inject = ['$translate', 'Integration', 'MerchantAccount', 'AccountingSyncProfile', 'Core'];

    function AccountingSyncSettings($translate, Integration, MerchantAccount, AccountingSyncProfile, Core) {
        return {
            loadSettings: loadSettings,
            loadMerchantAccounts: loadMerchantAccounts,
            addPaymentAccount: addPaymentAccount,
            deletePaymentAccount: deletePaymentAccount,
            buildPaymentAccountsForSave: buildPaymentAccountsForSave,
            saveSyncProfile: saveSyncProfile,
        };

        function loadSettings(id, success, err) {
            Integration.retrieve(
                {
                    id: id,
                },
                function (integration) {
                    let syncProfile;
                    if (integration.extra.sync_profile) {
                        syncProfile = integration.extra.sync_profile;
                    } else {
                        syncProfile = {
                            payment_accounts: [],
                        };
                    }

                    angular.forEach(syncProfile.payment_accounts, function (rule) {
                        rule.no_method = !rule.method || rule.method === '*';
                        rule.no_currency = !rule.currency || rule.currency === '*';
                        rule.no_merchant_account =
                            typeof rule.merchant_account === 'undefined' || rule.merchant_account === '*';
                    });

                    if (syncProfile.payment_accounts.length === 0) {
                        addPaymentAccount(syncProfile.payment_accounts);
                    }

                    success(integration, syncProfile);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    err();
                },
            );
        }

        function loadMerchantAccounts(success, err) {
            MerchantAccount.findAll(
                {
                    'filter[deleted]': false,
                    paginate: 'none',
                },
                function (result) {
                    angular.forEach(result, function (merchantAccount) {
                        merchantAccount.name =
                            $translate.instant('payment_gateways.' + merchantAccount.gateway) +
                            ': ' +
                            merchantAccount.name;
                    });
                    success(result);
                },
                function (result) {
                    err(result.data);
                },
            );
        }

        function addPaymentAccount(paymentAccounts) {
            paymentAccounts.push({
                no_method: true,
                method: null,
                no_currency: true,
                currency: null,
                no_merchant_account: true,
                merchant_account: null,
                account: null,
                // QuickBooks Online customization
                quickbooks_no_account: true,
                // Intacct customization
                intacct_type: 'undeposited_funds',
                intacct_bank_account: null,
                intacct_undeposited_funds_account: null,
            });
        }

        function deletePaymentAccount(paymentAccounts, index) {
            paymentAccounts.splice(index, 1);
        }

        function buildPaymentAccountsForSave(integrationId, input, err) {
            let paymentAccounts = [];
            let hashMap = {};
            angular.forEach(input, function (rule) {
                let line = {
                    method: rule.no_method ? '*' : rule.method,
                    currency: rule.no_currency ? '*' : rule.currency,
                    merchant_account: rule.no_merchant_account ? '*' : rule.merchant_account,
                    account: rule.account,
                };

                // QuickBooks Online customization
                if (integrationId === 'quickbooks_online') {
                    line.account = line.quickbooks_no_account ? null : line.account;
                }

                // Intacct customization
                if (integrationId === 'intacct') {
                    line.undeposited_funds = rule.intacct_type === 'undeposited_funds';
                    line.account =
                        rule.intacct_type === 'undeposited_funds'
                            ? rule.intacct_undeposited_funds_account
                            : rule.intacct_bank_account;
                }

                if (typeof line.account === 'object' && line.account) {
                    line.account = line.account.id;
                }

                paymentAccounts.push(line);

                let hashKey = line.method + line.currency + line.merchant_account;
                if (typeof hashMap[hashKey] !== 'undefined') {
                    err({
                        message: 'Duplicate payment account rule detected. Please correct and try again.',
                    });
                }
                hashMap[hashKey] = true;
            });

            return paymentAccounts;
        }

        function saveSyncProfile(syncProfile, params, success, err) {
            if (syncProfile.id) {
                AccountingSyncProfile.edit(
                    {
                        id: syncProfile.id,
                    },
                    params,
                    function (result) {
                        Core.flashMessage('Your integration settings have been saved', 'success');
                        success(result);
                    },
                    function (result) {
                        err(result.data);
                    },
                );
            } else {
                AccountingSyncProfile.create(
                    params,
                    function (result) {
                        Core.flashMessage('Your integration settings have been saved', 'success');
                        success(result);
                    },
                    function (result) {
                        err(result.data);
                    },
                );
            }
        }
    }
})();
