(function () {
    'use strict';

    angular.module('app.reports').controller('ViewReportController', ViewReportController);

    ViewReportController.$inject = [
        '$scope',
        '$rootScope',
        '$controller',
        '$state',
        '$window',
        '$filter',
        '$modal',
        '$timeout',
        'Report',
        'PresetReports',
        'Core',
        'BrowsingHistory',
        'ReportBuilder',
    ];

    function ViewReportController(
        $scope,
        $rootScope,
        $controller,
        $state,
        $window,
        $filter,
        $modal,
        $timeout,
        Report,
        PresetReports,
        Core,
        BrowsingHistory,
        ReportBuilder,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Report;
        $scope.modelTitleSingular = 'Report';

        $scope.hideSection = {};

        //
        // Methods
        //

        $scope.refresh = refresh;
        $scope.save = save;

        $scope.postFind = function (report) {
            // INVD-2918: This function becomes recursive when
            // $scope.find() is called again. $scope.find() should
            // not be called if the user has left the page.
            if ($scope.$$destroyed) {
                return;
            }

            // when the report is empty then it is still loading
            if (!report.title && !report.filename && report.data.length === 0) {
                // reload in 100ms
                $scope.building = true;
                $timeout(function () {
                    $scope.find(report.id);
                }, 100);
                return;
            }
            $scope.building = false;

            if (typeof report.data.error !== 'undefined') {
                $scope.error = report.data.error;
                report.data = [];
            }

            $scope.reportParameters = [];
            if (report.parameters) {
                angular.forEach(report.parameters, function (value, name) {
                    if (
                        name === '$dateRange' &&
                        typeof value.start !== 'undefined' &&
                        typeof value.end !== 'undefined'
                    ) {
                        value =
                            $filter('formatCompanyDate')(value.start) + ' - ' + $filter('formatCompanyDate')(value.end);
                    } else if (name === '$currency') {
                        value = value.toUpperCase();
                    } else if (name == '$startMonth' || name == '$endMonth') {
                        return;
                    }

                    if (typeof value === 'object') {
                        let values = [];
                        angular.forEach(value, function (subValue, subName) {
                            values.push(subName + ': ' + subValue);
                        });
                        value = values.join(', ');
                    }

                    $scope.reportParameters.push({
                        name: ReportBuilder.parameterName(name),
                        value: value,
                    });
                });
            }

            angular.forEach(report.data, function (section) {
                let firstGroup = section.groups[0];
                if (firstGroup.type === 'nested_table' || firstGroup.type === 'table') {
                    section.class = (section.class || '') + ' no-header-border';
                }

                if (firstGroup.type === 'nested_table' && firstGroup.rows.length > 0) {
                    let firstRow = firstGroup.rows[0];
                    if (typeof firstRow === 'object' && typeof firstRow.group === 'object') {
                        section.class = (section.class || '') + ' has-grouping';
                    }
                }
            });

            $scope.report = report;

            $rootScope.modelTitle = report.title;
            Core.setTitle(report.title);

            BrowsingHistory.push({
                id: report.id,
                type: 'report',
                title: report.title,
            });
        };

        $scope.toggleSection = function ($index) {
            $scope.hideSection[$index] = !$scope.hideSection[$index];
        };

        $scope.download = function (report, format) {
            let prop = format + '_url';
            if (report[prop]) {
                $window.open(report[prop]);
                return;
            }

            $scope.downloading = true;
            Report.download(
                {
                    id: report.id,
                },
                {
                    format: format,
                },
                function (result) {
                    $scope.downloading = false;
                    $window.open(result.url);
                },
                function (result) {
                    $scope.downloading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Report');

        function refresh(report) {
            if (report.type === 'custom') {
                refreshCustomReport(report);
            } else {
                refreshStandardReport(report);
            }
        }

        function refreshCustomReport(report) {
            if (Object.keys(report.parameters).length === 0) {
                performRefresh(report.id, {});
                return;
            }

            const modalInstance = $modal.open({
                templateUrl: 'reports/views/parameters.html',
                controller: 'ReportParametersController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    parameters: function () {
                        return report.parameters;
                    },
                },
            });

            modalInstance.result.then(
                function (parameters) {
                    performRefresh(report.id, parameters);
                },
                function () {
                    // canceled
                },
            );
        }

        function refreshStandardReport(report) {
            let standardReport = PresetReports.get(report.type);
            let hasParameters = false;
            angular.forEach(standardReport.availableParameters, function (value) {
                hasParameters = !!value || hasParameters;
            });
            if (!hasParameters) {
                performRefresh(report.id, report.parameters);
                return;
            }

            const modalInstance = $modal.open({
                templateUrl: 'reports/views/parameters-standard.html',
                controller: 'ReportStandardParametersController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                windowClass: 'report-standard-parameters-modal',
                resolve: {
                    availableParameters: function () {
                        return standardReport.availableParameters;
                    },
                    parameters: function () {
                        return report.parameters;
                    },
                },
            });

            modalInstance.result.then(
                function (parameters) {
                    performRefresh(report.id, parameters);
                },
                function () {
                    // canceled
                },
            );
        }

        function performRefresh(id, parameters) {
            $scope.refreshing = true;

            Report.refresh(
                {
                    id: id,
                },
                {
                    parameters: parameters,
                },
                function (_report) {
                    $scope.refreshing = false;
                    $state.go('manage.report.view', { id: _report.id });
                },
                function (result) {
                    $scope.refreshing = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function save(report) {
            const modalInstance = $modal.open({
                templateUrl: 'reports/views/save.html',
                controller: 'SaveReportController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    report: function () {
                        return {
                            definition: report.definition,
                            title: report.title,
                        };
                    },
                    savedReport: function () {
                        return null;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('Your report has been added to your Saved Reports list', 'success');
                    $scope.savedToReport = true;
                },
                function () {
                    // canceled
                },
            );
        }
    }
})();
