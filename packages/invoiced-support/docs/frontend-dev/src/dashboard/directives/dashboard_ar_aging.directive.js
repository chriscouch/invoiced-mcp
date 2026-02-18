/* globals moment */
(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardArAging', dashboardArAging);

    function dashboardArAging() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/ar-aging.html',
            scope: {
                context: '=',
            },
            controller: [
                '$scope',
                'Dashboard',
                function ($scope, Dashboard) {
                    let loadedCurrency;
                    $scope.aging = [];

                    $scope.dateRange = function (entry) {
                        let range = {};
                        let start = issuedAfter(entry);
                        let end = issuedBefore(entry);
                        if (start) {
                            range.start = start;
                        }
                        if (end) {
                            range.end = end;
                        }

                        return encodeURIComponent(angular.toJson(range));
                    };

                    function load(context) {
                        if (loadedCurrency === context.currency) {
                            return;
                        }

                        $scope.loading = true;
                        Dashboard.getMetricDebounced(
                            'ar_balance',
                            { currency: context.currency },
                            function (dashboard) {
                                $scope.loading = false;

                                $scope.agingMax = 0;
                                $scope.agingDate = dashboard.aging_date;
                                $scope.aging = [];
                                let daySuffix = dashboard.aging_date === 'due_date' ? 'Days Past Due' : 'Days Old';
                                let numBuckets = Object.keys(dashboard.aging).length;
                                let i = 0;
                                angular.forEach(dashboard.aging, function (row) {
                                    if (row.amount > $scope.agingMax) {
                                        $scope.agingMax = row.amount;
                                    }

                                    let upper = null;
                                    if (i < numBuckets - 1) {
                                        upper = parseInt(dashboard.aging[i + 1].age_lower) - 1;
                                    }

                                    // severity is a value 1 (lowest) - 6 (highest)
                                    // this maps an arbitrary number of aging buckets (not always 6)
                                    // onto this severity range
                                    let severity = Math.ceil(((i + 1) * 6) / numBuckets);

                                    let title;
                                    if (row.age_lower === -1) {
                                        title = 'Current';
                                    } else if (upper) {
                                        title = row.age_lower + ' - ' + upper + ' ' + daySuffix;
                                    } else {
                                        title = row.age_lower + '+ ' + daySuffix;
                                    }

                                    $scope.aging.push({
                                        lower: parseInt(row.age_lower),
                                        upper: upper,
                                        amount: row.amount,
                                        severity: severity,
                                        title: title,
                                        count: row.count,
                                    });
                                    i++;
                                });
                            },
                            function () {
                                $scope.loading = false;
                            },
                        );
                    }

                    $scope.$watch('context', load, true);

                    function issuedBefore(entry) {
                        if (entry.lower === -1) {
                            return null;
                        }

                        if (entry.lower === 0) {
                            return moment().format('YYYY-MM-DD');
                        }

                        return moment().subtract(entry.lower, 'days').format('YYYY-MM-DD');
                    }

                    function issuedAfter(entry) {
                        if (entry.upper === null) {
                            return null;
                        }

                        if (entry.upper === -1) {
                            return moment().add(1, 'day').format('YYYY-MM-DD');
                        }

                        return moment().subtract(entry.upper, 'days').format('YYYY-MM-DD');
                    }
                },
            ],
        };
    }
})();
