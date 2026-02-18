(function () {
    'use strict';

    angular.module('app.dashboard').factory('Dashboard', Dashboard);

    Dashboard.$inject = ['$resource', 'InvoicedConfig'];

    function Dashboard($resource, InvoicedConfig) {
        let Dashboard = $resource(
            InvoicedConfig.apiBaseUrl + '/dashboard',
            {},
            {
                get: {
                    method: 'GET',
                },
                getMetric: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/dashboard/metrics/:id',
                    params: {
                        id: '@id',
                    },
                },
                activityChart: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/dashboard/activity_chart',
                    cache: true,
                },
                findAll: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/ui/dashboards',
                    isArray: true,
                    cache: true,
                },
            },
        );

        // This function ensures that there is only one request at a time
        // to a specific dashboard metric endpoint.
        let waiters = {};
        Dashboard.getMetricDebounced = function (metric, options, success, failure) {
            let key = metric + '_' + JSON.stringify(options);
            let shouldFetch = false;
            if (typeof waiters[key] === 'undefined') {
                waiters[key] = [];
                shouldFetch = true;
            }

            waiters[key].push({ success: success, failure: failure });

            if (shouldFetch) {
                options.id = metric;
                Dashboard.getMetric(
                    options,
                    function (result) {
                        angular.forEach(waiters[key], function (waiter) {
                            waiter.success(result);
                        });
                        delete waiters[key];
                    },
                    function (result) {
                        angular.forEach(waiters[key], function (waiter) {
                            waiter.failure(result);
                        });
                        delete waiters[key];
                    },
                );
            }
        };

        return Dashboard;
    }
})();
