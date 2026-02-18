/* globals Chart */
(function () {
    'use strict';

    angular.module('app.components').directive('reportChart', reportChart);

    reportChart.$inject = ['Money', 'selectedCompany'];

    function reportChart(Money, selectedCompany) {
        return {
            restrict: 'E',
            template:
                '<div class="report-chart-holder"><canvas class="report-chart" id="chart-{{chartId}}"></canvas></div>',
            scope: {
                type: '=type',
                data: '=data',
                options: '=options',
            },
            controller: [
                '$scope',
                '$timeout',
                function ($scope, $timeout) {
                    $scope.chartId = Math.floor(Math.random() * 10000);
                    let theChart;
                    $timeout(function () {
                        paint($scope.type, $scope.data, $scope.options);
                    });

                    function paint(type, data, options) {
                        if (typeof theChart !== 'undefined') {
                            theChart.destroy();
                        }

                        // The backend sometimes returns an empty array instead of a proper object
                        if (typeof options !== 'object' || angular.isArray(options)) {
                            options = {};
                        }
                        options.responsive = true;
                        options.maintainAspectRatio = false;

                        // Format Y axis values
                        if (typeof options.scales !== 'undefined' && typeof options.scales.yAxes !== 'undefined') {
                            angular.forEach(options.scales.yAxes, function (yAxis) {
                                if (typeof yAxis.ticks !== 'undefined' && yAxis.ticks.type !== 'undefined') {
                                    if (yAxis.ticks.type === 'money') {
                                        yAxis.ticks.callback = function (value) {
                                            return Money.currencyFormat(
                                                value,
                                                yAxis.ticks.currency,
                                                selectedCompany.moneyFormat,
                                                false,
                                            );
                                        };
                                    }
                                }
                            });
                        }

                        // Format tooltips
                        if (typeof options.tooltips !== 'undefined' && options.tooltips.type !== 'undefined') {
                            options.tooltips.callbacks = {
                                title: function (tooltipItems, data) {
                                    let titles = [];
                                    angular.forEach(tooltipItems, function (tooltipItem) {
                                        if (type === 'pie' || type === 'doughnut' || type === 'polarArea') {
                                            titles.push(data.datasets[tooltipItem.datasetIndex].label || '');
                                        } else {
                                            titles.push(data.labels[tooltipItem.index] || '');
                                        }
                                    });

                                    return titles;
                                },
                            };
                            if (options.tooltips.type === 'money') {
                                options.tooltips.callbacks.label = function (tooltipItem, data) {
                                    let datasetIndex = tooltipItem.datasetIndex;
                                    let index = tooltipItem.index;
                                    let datasetLabel, value;
                                    if (type === 'pie' || type === 'doughnut' || type === 'polarArea') {
                                        datasetLabel = data.labels[index] || '';
                                        value = data.datasets[datasetIndex].data[index];
                                    } else {
                                        datasetLabel = data.datasets[datasetIndex].label || '';
                                        value = tooltipItem.yLabel;
                                    }

                                    return (
                                        datasetLabel +
                                        ': ' +
                                        Money.currencyFormat(
                                            value,
                                            options.tooltips.currency,
                                            selectedCompany.moneyFormat,
                                            false,
                                        )
                                    );
                                };
                            }
                        }

                        let ctx = $('#chart-' + $scope.chartId);
                        theChart = new Chart(ctx, {
                            type: type,
                            data: data,
                            options: options,
                        });
                    }
                },
            ],
        };
    }
})();
