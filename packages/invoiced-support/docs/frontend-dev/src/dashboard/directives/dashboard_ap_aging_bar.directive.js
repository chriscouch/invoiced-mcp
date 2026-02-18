(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardApAgingBar', dashboardApAgingBar);

    function dashboardApAgingBar() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/ar-aging-bar.html',
            scope: {
                context: '=',
            },
            controller: [
                '$scope',
                'Dashboard',
                function ($scope, Dashboard) {
                    $scope.aging = [];

                    function load() {
                        if ($scope.aging.length > 0) {
                            return;
                        }

                        $scope.loading = true;
                        Dashboard.getMetricDebounced(
                            'ap_aging',
                            {},
                            function (result) {
                                $scope.loading = false;

                                $scope.agingMax = 0;
                                $scope.agingDate = result.aging_date;
                                $scope.aging = [];
                                let daySuffix = result.aging_date === 'due_date' ? 'Days Past Due' : 'Days Old';
                                let numBuckets = Object.keys(result.aging).length;
                                let i = 0;
                                angular.forEach(result.aging, function (row) {
                                    if (row.amount > $scope.agingMax) {
                                        $scope.agingMax = row.amount;
                                    }

                                    let upper = null;
                                    if (i < numBuckets - 1) {
                                        upper = parseInt(result.aging[i + 1].age_lower) - 1;
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
                },
            ],
        };
    }
})();
