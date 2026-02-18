/* globals moment */
(function () {
    'use strict';

    angular.module('app.dashboard').controller('DashboardController', DashboardController);

    DashboardController.$inject = [
        '$scope',
        'localStorageService',
        'selectedCompany',
        'Core',
        'PresetDashboards',
        'Dashboard',
        'Feature',
    ];

    function DashboardController(
        $scope,
        localStorageService,
        selectedCompany,
        Core,
        PresetDashboards,
        Dashboard,
        Feature,
    ) {
        $scope.dashboards = angular.copy(PresetDashboards.all());
        $scope.availableCurrencies = angular.copy(selectedCompany.currencies);
        let dashboardKey = selectedCompany.id + '.selected_dashboard';
        let currencyKey = selectedCompany.id + '.dashboard_currency';
        let periodKey = selectedCompany.id + '.dashboard_period';

        //
        // Initialization
        //

        Core.setTitle('Dashboard');
        selectDashboardByName(loadSelectedDashboard());
        loadContext();
        loadDashboards();

        $scope.$watch(
            'context',
            function (context) {
                // Memorize selected currency in localStorage
                memorizeCurrency(context.currency);

                // Memorize selected time period in localStorage
                if (context.period && context.period.period) {
                    memorizePeriod(context.period.period);
                }
            },
            true,
        );

        $scope.selectDashboard = selectDashboard;

        //
        // Private methods
        //

        function loadContext() {
            // load currency from localStorage
            let currency = loadCurrency();
            if ($scope.availableCurrencies.indexOf(currency) === -1) {
                $scope.availableCurrencies.push(currency);
            }

            if (!Feature.hasFeature('multi_currency')) {
                currency = selectedCompany.currency;
            }

            // load period from localStorage
            let period = loadPeriod();

            // compute the date range based on the period
            let start = moment().subtract(period[1], period[0]).toDate();
            let end = moment().toDate();

            $scope.context = {
                currency: currency,
                period: {
                    start: start,
                    end: end,
                    period: period,
                },
            };
        }

        function loadSelectedDashboard() {
            return localStorageService.get(dashboardKey);
        }

        function memorizeSelectedDashboard(name) {
            localStorageService.add(dashboardKey, name);
        }

        function loadCurrency() {
            return localStorageService.get(currencyKey) || selectedCompany.currency;
        }

        function memorizeCurrency(currency) {
            localStorageService.add(currencyKey, currency);
        }

        function loadPeriod() {
            let periodStr = localStorageService.get(periodKey);
            if (periodStr) {
                // parses an encoded-array, i.e. "months,3"
                if (periodStr.indexOf(',') !== -1) {
                    return periodStr.split(',');
                }

                // handles shortcuts, i.e. "this_month"
                return periodStr;
            }

            // the default is the past week
            return ['weeks', 1];
        }

        function memorizePeriod(period) {
            // encoded arrays by joining with commas
            if (typeof period === 'object' && period instanceof Array) {
                localStorageService.add(periodKey, period.join(','));
            } else {
                // otherwise we are dealing with a shortcut,
                // i.e. "this_month"
                localStorageService.add(periodKey, period);
            }
        }

        function loadDashboards() {
            Dashboard.findAll(
                function (dashboards) {
                    angular.forEach(dashboards, function (dashboard) {
                        dashboard.definition.name = dashboard.name;
                        $scope.dashboards.push(dashboard.definition);
                    });
                    selectDashboardByName(loadSelectedDashboard());
                },
                function () {
                    // do nothing
                },
            );
        }

        function selectDashboardByName(name) {
            let dashboardToSelect = $scope.dashboards.length ? $scope.dashboards[0] : null;
            angular.forEach($scope.dashboards, function (dashboard) {
                if (dashboard.name === name) {
                    dashboardToSelect = dashboard;
                }
            });

            if (dashboardToSelect) {
                $scope.grid = dashboardToSelect;
            }
        }

        function selectDashboard(dashboard) {
            memorizeSelectedDashboard(dashboard.name);
            $scope.grid = dashboard;
        }
    }
})();
