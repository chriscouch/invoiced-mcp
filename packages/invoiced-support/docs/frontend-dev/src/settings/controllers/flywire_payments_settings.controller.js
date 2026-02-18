(function () {
    'use strict';
    angular.module('app.settings').controller('FlywirePaymentsSettingsController', FlywirePaymentsSettingsController);

    FlywirePaymentsSettingsController.$inject = [
        '$scope',
        '$modal',
        'Core',
        'FlywirePayments',
        'Money',
        'InvoicedConfig',
    ];

    function FlywirePaymentsSettingsController($scope, $modal, Core, FlywirePayments, Money, InvoicedConfig) {
        Core.setTitle('Flywire Payments');
        $scope.tab = 'transactions';
        $scope.changeTab = changeTab;

        loadAccount();

        function loadAccount() {
            $scope.loading = true;
            FlywirePayments.getAccount(
                async function (account) {
                    $scope.loading = false;
                    $scope.account = account;

                    angular.forEach(account.balances, function (balance) {
                        const balance1 = Money.normalizeToZeroDecimal(balance.currency, balance.balance);
                        const balance2 = Money.normalizeToZeroDecimal(balance.currency, balance.available);
                        balance.showAvailableBalance = balance1 !== balance2;
                        balance.showPendingBalance = balance.pending > 0;
                    });

                    // Render Adyen Platform components
                    if (account.component_session) {
                        const core = await makeCore(account.component_session);
                        showPayouts(core);
                        showReporting(core);
                        showTransactions(core);
                    }
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'danger');
                },
            );
        }

        async function makeCore(componentSession) {
            const { AdyenPlatformExperience } = require('@adyen/adyen-platform-experience-web');

            return await AdyenPlatformExperience({
                environment: InvoicedConfig.environment === 'production' ? 'live' : 'test',
                onSessionCreate: function () {
                    return componentSession;
                },
            });
        }

        async function showPayouts(core) {
            const { PayoutsOverview } = require('@adyen/adyen-platform-experience-web');
            const payoutsOverview = new PayoutsOverview({ core });
            payoutsOverview.mount('#payouts-overview-container');
        }

        async function showReporting(core) {
            const { ReportsOverview } = require('@adyen/adyen-platform-experience-web');
            const reportsOverview = new ReportsOverview({ core });
            reportsOverview.mount('#reports-overview-container');
        }

        async function showTransactions(core) {
            const { TransactionsOverview } = require('@adyen/adyen-platform-experience-web');
            const transactionsOverview = new TransactionsOverview({ core });
            transactionsOverview.mount('#transactions-overview-container');
        }

        function changeTab(tab) {
            $scope.tab = tab;
        }
    }
})();
