/* globals _, Chart, moment */
(function () {
    'use strict';

    angular.module('app.components').directive('activityChart', activityChart);

    activityChart.$inject = ['selectedCompany', '$timeout', '$window', 'Money', 'Core', 'Dashboard'];

    function activityChart(selectedCompany, $timeout, $window, Money, Core, Dashboard) {
        let datasetTemplates = {
            invoices: {
                label: 'Invoiced',
                backgroundColor: '#B4D9BD',
                hoverBackgroundColor: '#B4D9BD',
                borderWidth: 0,
            },
            payments: {
                label: 'Collected',
                backgroundColor: '#10806F',
                hoverBackgroundColor: '#10806F',
                borderWidth: 0,
            },
        };

        return {
            restrict: 'E',
            templateUrl: 'components/views/activity-chart.html',
            scope: {
                period: '=',
                style: '=?',
                customer: '=?',
                currency: '=?',
            },
            controller: [
                '$scope',
                function ($scope) {
                    if (!$scope.currency) {
                        $scope.currency = selectedCompany.currency;
                    }

                    $scope.style = $scope.style || 'line';

                    $scope.totals = {
                        invoices: 0,
                        payments: 0,
                    };

                    $scope.noChartData = function () {
                        return calcTotal($scope.totals) === 0;
                    };

                    // watch for changes to the selected date range
                    // (internal or external)
                    $scope.$watch('period', periodChanged, true);

                    // watch for changes to the currency
                    $scope.$watch('currency', currencyChanged);

                    //
                    // Private methods
                    //

                    let data;
                    let loadedPeriod;
                    let loadedCurrency;

                    function loadData() {
                        if ($scope.loading) {
                            return;
                        }

                        // only reload the data if the date range or currency has changed
                        let k = $scope.startTs + '_' + $scope.endTs;
                        if ($scope.currency == loadedCurrency && k == loadedPeriod) {
                            return;
                        }

                        $scope.loading = true;

                        let params = {
                            currency: $scope.currency,
                            start: $scope.startTs,
                            end: $scope.endTs,
                        };

                        if ($scope.customer) {
                            params.customer = $scope.customer.id;
                        }

                        Dashboard.activityChart(
                            params,
                            function (newData) {
                                $scope.loading = false;
                                data = newData;

                                // update the currently loaded currency / period
                                loadedCurrency = newData.currency;
                                loadedPeriod = $scope.startTs + '_' + $scope.endTs;

                                dataRefreshed();
                            },
                            function () {
                                $scope.loading = false;
                            },
                        );
                    }

                    function periodChanged(period) {
                        if (!period) {
                            return;
                        }

                        // compute start and end timestamps for the range
                        let start = moment(period.start);
                        let end = moment(period.end);
                        $scope.startTs = start.startOf('day').unix();
                        $scope.endTs = end.endOf('day').unix();
                        $scope.dateRangeQuery = encodeURIComponent(
                            angular.toJson({ start: start.format('YYYY-MM-DD'), end: end.format('YYYY-MM-DD') }),
                        );

                        // reload the chart data
                        loadData();
                    }

                    function currencyChanged() {
                        loadData();
                    }

                    function calcTotal(totals) {
                        let total = 0;
                        angular.forEach(totals, function (value) {
                            total += value;
                        });
                        return total;
                    }

                    function dataRefreshed() {
                        // compute the category totals
                        ['invoices', 'payments'].forEach(function (type) {
                            $scope.totals[type] = 0;
                            angular.forEach(data[type], function (value, ts) {
                                if (ts >= $scope.startTs && ts <= $scope.endTs) {
                                    $scope.totals[type] += value;
                                }
                            });
                        });

                        // draw (or re-draw) the chart
                        $scope.drawing = true;
                        $timeout(function () {
                            drawChart(data, $scope.style);
                            $scope.drawing = false;
                        });
                    }

                    function drawChart(data, style) {
                        let labels = buildChartLabels(data);
                        let datasets = buildChartDatasets(data);
                        paint(labels, datasets, style);
                    }

                    function buildChartLabels(data) {
                        let labels = [];
                        angular.forEach(_.keys(data.invoices), function (ts) {
                            if (data.labels && typeof data.labels[ts] !== 'undefined') {
                                labels.push(data.labels[ts]);
                                return;
                            }

                            ts = moment.unix(ts);
                            if (data.unit == 'month') {
                                labels.push(ts.format('MMM'));
                            } else if (data.unit == 'week') {
                                labels.push(ts.format('MMM D'));
                            } else if (data.unit == 'day') {
                                if (ts.diff(moment(), 'days') === 0) {
                                    labels.push('Today');
                                } else {
                                    labels.push(ts.format('MMM D'));
                                }
                            }
                        });

                        return labels;
                    }

                    function buildChartDatasets(data) {
                        let datasets = [];

                        ['invoices', 'payments'].forEach(function (type) {
                            let dataset = angular.copy(datasetTemplates[type]);

                            dataset.data = _.toArray(data[type]);

                            // clean up #s
                            for (let i in dataset.data) {
                                dataset.data[i] = Math.round(dataset.data[i] * 100) / 100;
                            }

                            datasets.push(dataset);
                        });

                        return datasets;
                    }

                    let theChart;

                    let currencyOpts = angular.copy(selectedCompany.moneyFormat);
                    currencyOpts.precision = 0;

                    function paint(labels, datasets, style) {
                        if (typeof theChart !== 'undefined') {
                            theChart.destroy();
                        }

                        let act = document.getElementById('activity-chart');
                        if (act) {
                            let options = {
                                responsive: true,
                                maintainAspectRatio: false,
                                legend: {
                                    display: false,
                                },
                                scales: {
                                    xAxes: [
                                        {
                                            barPercentage: 0.97,
                                            categoryPercentage: 0.9,
                                            gridLines: {
                                                display: false,
                                            },
                                        },
                                    ],
                                    yAxes: [
                                        {
                                            ticks: {
                                                suggestedMin: 0,
                                                maxTicksLimit: 5,
                                                callback: function (value) {
                                                    return Money.currencyFormat(
                                                        value,
                                                        $scope.currency,
                                                        currencyOpts,
                                                        false,
                                                    );
                                                },
                                            },
                                            gridLines: {
                                                color: '#EAEDEC',
                                            },
                                        },
                                    ],
                                },
                                tooltips: {
                                    callbacks: {
                                        label: function (tooltipItem, chart) {
                                            let datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                                            return (
                                                datasetLabel +
                                                ': ' +
                                                Money.currencyFormat(
                                                    tooltipItem.yLabel,
                                                    $scope.currency,
                                                    currencyOpts,
                                                    false,
                                                )
                                            );
                                        },
                                    },
                                },
                            };

                            let ctx = act.getContext('2d');
                            theChart = new Chart(ctx, {
                                type: style,
                                data: {
                                    labels: labels,
                                    datasets: datasets,
                                },
                                options: options,
                            });
                        }
                    }
                },
            ],
        };
    }
})();
