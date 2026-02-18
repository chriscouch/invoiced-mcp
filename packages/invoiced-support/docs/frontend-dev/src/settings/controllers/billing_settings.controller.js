/* globals moment */
(function () {
    'use strict';

    angular.module('app.settings').controller('BillingSettingsController', BillingSettingsController);

    BillingSettingsController.$inject = [
        '$scope',
        '$modal',
        '$state',
        '$window',
        'Company',
        'CurrentUser',
        'CSRF',
        'selectedCompany',
        'Core',
        'Member',
        'Feature',
    ];

    function BillingSettingsController(
        $scope,
        $modal,
        $state,
        $window,
        Company,
        CurrentUser,
        CSRF,
        selectedCompany,
        Core,
        Member,
        Feature,
    ) {
        if (selectedCompany.test_mode) {
            $state.go('manage.settings.default');
        }

        let user = CurrentUser.profile;
        $scope.currentUser = angular.copy(user);
        $scope.company = angular.copy(selectedCompany);
        $scope.upgradeUrl = Core.upgradeUrl(selectedCompany, user, Feature.hasFeature('not_activated'));

        $scope.company.renews_next = moment.unix($scope.company.renews_next).toDate();
        $scope.card = {
            address_country: $scope.company.country,
        };
        $scope.billingInfo = {};
        $scope.userPricingPlan = null;
        $scope.show = {
            updateCard: false,
        };
        $scope.month = moment().format('MMMM');
        $scope.customerUsageOpts = {
            name: 'Customer',
            namePlural: 'Customers',
        };
        $scope.invoiceUsageOpts = {
            name: 'Invoice',
            namePlural: 'Invoices',
        };
        $scope.moneyBilledUsageOpts = {
            name: 'USD Billed',
            namePlural: 'USD Billed',
        };
        $scope.memberUsageOpts = {
            name: 'User',
            namePlural: 'Users',
        };

        $scope.load = load;

        // thresholds
        $scope.warningLow = 75;
        $scope.alarmHigh = 95;

        $scope.cardClean = function () {
            return angular.equals($scope.card, {});
        };

        $scope.reactivate = function () {
            $scope.reactivating = true;
            $scope.error = null;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                Company.reactivate(
                    {
                        id: $scope.company.id,
                    },
                    {},
                    function () {
                        $scope.reactivating = false;

                        Core.flashMessage('Your subscription has been reactivated.', 'success');
                        load();
                    },
                    function (result) {
                        $scope.reactivating = false;
                        $scope.error = result.data;
                    },
                );
            });
        };

        Core.setTitle('Billing Information');
        load();

        function load() {
            $scope.loading = true;

            Member.all(
                function (members) {
                    $scope.members = members;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );

            Company.billingInfo(
                {
                    id: $scope.company.id,
                },
                function (result) {
                    angular.forEach(result.billing_history, function (history) {
                        history.date = moment.unix(history.date).toDate();
                    });

                    $scope.billingInfo = result;

                    // ind the user pricing plan
                    angular.forEach(result.usage_pricing_plans, function (usagePricingPlan) {
                        if (usagePricingPlan.type === 'user') {
                            $scope.userPricingPlan = usagePricingPlan;
                        }
                    });

                    // humanize usage history months
                    let history = [];
                    angular.forEach(result.usage_history, function (volume, month) {
                        volume.month = moment(month, 'YYYYMM').format('MMMM YYYY');
                        volume.k = month;
                        history.push(volume);
                    });

                    // sort by month (descending order)
                    history.sort(function (a, b) {
                        return a.k > b.k ? -1 : 1;
                    });

                    result.usage_history = history;

                    $scope.loading = false;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.loading = false;
                },
            );
        }
    }
})();
