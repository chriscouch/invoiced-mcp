/* globals moment */
(function () {
    'use strict';

    angular.module('app.reports').controller('ReportParametersController', ReportParametersController);

    ReportParametersController.$inject = [
        '$scope',
        '$modalInstance',
        'ReportBuilder',
        'DatePickerService',
        'selectedCompany',
        'parameters',
    ];

    function ReportParametersController(
        $scope,
        $modalInstance,
        ReportBuilder,
        DatePickerService,
        selectedCompany,
        parameters,
    ) {
        $scope.reportParameters = [];
        $scope.availableCurrencies = selectedCompany.currencies;
        $scope.dateOptions = DatePickerService.getOptions();

        angular.forEach(parameters, function (value, id) {
            value = angular.copy(value);
            if (id === '$dateRange') {
                value.start = moment(value.start, 'YYYY-MM-DD').toDate();
                value.end = moment(value.end, 'YYYY-MM-DD').toDate();
                if (typeof value.period === 'undefined') {
                    value.period = 'custom';
                }
            }

            $scope.reportParameters.push({
                id: id,
                name: ReportBuilder.parameterName(id),
                value: value,
            });
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.generate = function (reportParameters) {
            let parameters = {};
            angular.forEach(reportParameters, function (parameter) {
                if (parameter.id === '$dateRange' && typeof parameter.value === 'object') {
                    parameter.value.start = moment(parameter.value.start).format('YYYY-MM-DD');
                    parameter.value.end = moment(parameter.value.end).format('YYYY-MM-DD');
                }

                parameters[parameter.id] = parameter.value;
            });

            $modalInstance.close(parameters);
        };
    }
})();
