/* globals Chart */
(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardTopVendors', dashboardTopVendors);

    function dashboardTopVendors() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/top-vendors.html',
            scope: {
                context: '=',
                options: '=',
            },
            controller: [
                '$scope',
                '$filter',
                'Dashboard',
                'Money',
                'selectedCompany',
                function ($scope, $filter, Dashboard, Money, selectedCompany) {
                    let loadedCurrency;
                    $scope.topVendors = [];
                    let currencyOpts = angular.copy(selectedCompany.moneyFormat);
                    currencyOpts.precision = 0;

                    function load(context) {
                        if (loadedCurrency === context.currency) {
                            return;
                        }

                        let theChart;
                        $scope.loading = true;
                        Dashboard.getMetricDebounced(
                            'top_vendors',
                            { currency: context.currency },
                            function (result) {
                                $scope.loading = false;
                                $scope.currency = result.currency;
                                $scope.generatedAt = result.generated_at;
                                $scope.topVendors = result.top_vendors;

                                let labels = [];
                                let dataset = {
                                    label: 'Total Spend',
                                    data: [],
                                    backgroundColor: [
                                        '#10806F',
                                        '#B4D9BD',
                                        '#e66b55',
                                        '#ffbf3e',
                                        '#4b94d9',
                                        '#00529B',
                                        '#7AC142',
                                        '#F54F52',
                                        '#9552EA',
                                        '#FDBB2F',
                                        '#F47A1F',
                                        '#007CC3',
                                        '#377B2B',
                                    ],
                                };
                                let options = {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    legend: {
                                        display: false,
                                    },
                                    scales: {
                                        yAxes: [
                                            {
                                                barPercentage: 0.97,
                                                categoryPercentage: 0.9,
                                                gridLines: {
                                                    display: false,
                                                },
                                            },
                                        ],
                                        xAxes: [
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
                                            label: function (tooltipItem) {
                                                let vendorName = labels[tooltipItem.index] || '';
                                                let amount = dataset.data[tooltipItem.index] || 0;

                                                return (
                                                    vendorName +
                                                    ': ' +
                                                    Money.currencyFormat(amount, $scope.currency, currencyOpts, false)
                                                );
                                            },
                                        },
                                    },
                                };

                                angular.forEach(result.top_vendors, function (vendor) {
                                    labels.push(vendor.name);
                                    dataset.data.push(vendor.total_spend);
                                });

                                let ctx = $('#top-vendors-chart');
                                theChart = new Chart(ctx, {
                                    type: 'horizontalBar',
                                    data: {
                                        labels: labels,
                                        datasets: [dataset],
                                    },
                                    options: options,
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
