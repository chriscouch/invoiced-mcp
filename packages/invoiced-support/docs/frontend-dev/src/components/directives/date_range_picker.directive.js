/* globals moment */
(function () {
    'use strict';

    angular.module('app.components').directive('dateRangePicker', dateRangePicker);

    dateRangePicker.$inject = ['selectedCompany', 'DatePickerService'];

    function dateRangePicker(selectedCompany, DatePickerService) {
        return {
            restrict: 'E',
            templateUrl: 'components/views/date-range-picker.html',
            scope: {
                period: '=',
                options: '=?',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.customPeriod = !$scope.period.period;

                    $scope.dateOptions = DatePickerService.getOptions();

                    $scope.dateOptions = angular.extend($scope.dateOptions, $scope.options || {});

                    $scope.selectPeriod = function (period) {
                        $scope.period.period = period;

                        // compute the date range based on the period
                        $scope.period.start = moment().subtract(period[1], period[0]).toDate();
                        $scope.period.end = moment().toDate();
                    };

                    $scope.shortcut = function (period) {
                        $scope.period.period = period;
                        let quarter, quarterMonth;

                        switch (period) {
                            case 'today':
                                $scope.period.start = moment().startOf('day').toDate();
                                $scope.period.end = moment().endOf('day').toDate();
                                break;
                            case 'yesterday':
                                $scope.period.start = moment().subtract(1, 'days').startOf('day').toDate();
                                $scope.period.end = moment().subtract(1, 'days').endOf('day').toDate();
                                break;
                            case 'this_month':
                                $scope.period.start = moment().startOf('month').toDate();
                                $scope.period.end = moment().endOf('day').toDate();
                                break;
                            case 'last_month':
                                $scope.period.start = moment().subtract(1, 'months').startOf('month').toDate();
                                $scope.period.end = moment().subtract(1, 'months').endOf('month').toDate();
                                break;
                            case 'this_quarter':
                                quarter = Math.floor(moment().month() / 3.0) + 1;
                                quarterMonth = (quarter - 1) * 3;
                                $scope.period.start = moment().month(quarterMonth).startOf('month').toDate();
                                $scope.period.end = moment().endOf('day').toDate();
                                break;
                            case 'last_quarter':
                                let lastQuarter = moment().subtract(3, 'months');
                                quarter = Math.floor(lastQuarter.month() / 3.0) + 1;
                                quarterMonth = (quarter - 1) * 3;
                                $scope.period.start = moment()
                                    .month(quarterMonth)
                                    .year(lastQuarter.year())
                                    .startOf('month')
                                    .toDate();
                                $scope.period.end = moment()
                                    .month(quarterMonth)
                                    .year(lastQuarter.year())
                                    .add('months', 2)
                                    .endOf('month')
                                    .toDate();
                                break;
                            case 'this_year':
                                $scope.period.start = moment().startOf('year').toDate();
                                $scope.period.end = moment().endOf('day').toDate();
                                break;
                            case 'last_year':
                                $scope.period.start = moment().subtract(1, 'years').startOf('year').toDate();
                                $scope.period.end = moment().subtract(1, 'years').endOf('year').toDate();
                                break;
                            case 'all_time':
                                $scope.period.start = moment.unix(selectedCompany.created_at).toDate();
                                $scope.period.end = moment().endOf('day').toDate();
                                break;
                            case 'next_90_days':
                                $scope.period.start = moment().add(1, 'days').toDate();
                                $scope.period.end = moment().add(91, 'days').toDate();
                                break;
                        }
                    };

                    $scope.useCustomRange = function () {
                        $scope.customPeriod = true;
                        $scope.period.period = false;
                    };

                    $scope.noCustomRange = function () {
                        $scope.customPeriod = false;
                    };

                    // watch for changes to the selected date range
                    // (internal or external)
                    $scope.$watch('period', periodChanged, true);

                    if (typeof $scope.period.period === 'object' && $scope.period.period instanceof Array) {
                        $scope.selectPeriod($scope.period.period);
                    } else if (typeof $scope.period.period === 'string') {
                        $scope.shortcut($scope.period.period);
                    }

                    //
                    // Private methods
                    //

                    function periodChanged(period) {
                        if (!period) {
                            return;
                        }

                        // generate a human-readable version for the range
                        $scope.dateRangeName = dateRangeName($scope.period);
                    }

                    function dateRangeName(period) {
                        return (
                            moment(period.start).format('MMM D, YYYY') +
                            ' &mdash; ' +
                            moment(period.end).format('MMM D, YYYY')
                        );
                    }
                },
            ],
        };
    }
})();
