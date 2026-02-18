/* globals vex */
(function () {
    'use strict';

    angular.module('app.reports').controller('ReportsController', ReportsController);

    ReportsController.$inject = [
        '$scope',
        '$state',
        '$modal',
        'Core',
        'Report',
        'localStorageService',
        'Feature',
        'PresetReports',
        'ReportBuilder',
        'selectedCompany',
    ];

    function ReportsController(
        $scope,
        $state,
        $modal,
        Core,
        Report,
        lss,
        Feature,
        PresetReports,
        ReportBuilder,
        selectedCompany,
    ) {
        //
        // Settings
        //

        $scope.standardReports = PresetReports.all();

        //
        // Presets
        //

        $scope.search = '';
        $scope.savedReports = [];

        //
        // Methods
        //

        $scope.selectReport = selectReport;
        $scope.edit = editSavedReport;
        $scope.delete = deleteSavedReport;
        $scope.schedule = scheduleReport;

        //
        // Initialization
        //

        loadSavedReports();

        Core.setTitle('Reports');

        function loadSavedReports() {
            if (!Feature.hasFeature('report_builder')) {
                return;
            }

            $scope.loading = true;
            Report.findAllSaved(
                {
                    sort: 'name ASC',
                },
                function (savedReports) {
                    $scope.loading = false;
                    $scope.savedReports = savedReports;
                    loadScheduledReports();
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function editSavedReport(savedReport) {
            $scope.error = null;

            const modalInstance = $modal.open({
                templateUrl: 'reports/views/save.html',
                controller: 'SaveReportController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    report: function () {
                        return {
                            definition: savedReport.definition,
                            title: savedReport.name,
                        };
                    },
                    savedReport: function () {
                        return angular.copy(savedReport);
                    },
                },
            });

            modalInstance.result.then(
                function (_savedReport) {
                    Core.flashMessage('Your report has been saved', 'success');

                    if (savedReport) {
                        angular.extend(savedReport, _savedReport);
                    }
                },
                function () {
                    // canceled
                },
            );
        }

        function deleteSavedReport(savedReport, $index) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this report? This action cannot be undone.',
                callback: function (result) {
                    if (result) {
                        savedReport.deleting = true;
                        Report.deleteSavedReport(
                            {
                                id: savedReport.id,
                            },
                            function () {
                                savedReport.deleting = false;
                                $scope.savedReports.splice($index, 1);
                            },
                            function (result) {
                                savedReport.deleting = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        }

        function loadScheduledReports() {
            if (!Feature.hasFeature('report_builder')) {
                return;
            }

            $scope.loading = true;
            Report.findAllScheduled(
                function (scheduledReports) {
                    $scope.loading = false;
                    angular.forEach(scheduledReports, function (scheduledReport) {
                        angular.forEach($scope.savedReports, function (savedReport) {
                            if (savedReport.id === scheduledReport.saved_report) {
                                savedReport.schedule = scheduledReport;
                                return false;
                            }
                        });
                    });
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function scheduleReport(savedReport) {
            $scope.error = null;

            const modalInstance = $modal.open({
                templateUrl: 'reports/views/schedule.html',
                controller: 'ScheduleReportController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    schedule: function () {
                        return savedReport.schedule || null;
                    },
                    savedReport: function () {
                        return angular.copy(savedReport);
                    },
                },
            });

            modalInstance.result.then(
                function (schedule) {
                    Core.flashMessage('Your report schedule has been updated', 'success');
                    savedReport.schedule = schedule;
                },
                function () {
                    // canceled
                },
            );
        }

        function getPresets(reportId) {
            let defaults = {};
            if (reportId === 'cash_flow') {
                defaults.$dateRange = { period: 'next_90_days' };
            } else if (reportId === 'payment_statistics') {
                defaults.$dateRange = { period: ['years', 1] };
            }
            let presetStr = lss.get('reportPresets.' + selectedCompany.id + '.' + reportId);

            return presetStr ? angular.fromJson(presetStr) : defaults;
        }

        function savePresets(reportId, parameters) {
            lss.add('reportPresets.' + selectedCompany.id + '.' + reportId, angular.toJson(parameters));
        }

        function selectReport(report, $event) {
            let target = $($event.target);
            if (target.is('.hover-actions') || target.parents('.hover-actions').length > 0) {
                return;
            }

            if ($scope.building) {
                return;
            }

            if (report.definition) {
                buildSavedReport(report);
            } else {
                buildStandardReport(report);
            }
        }

        function buildSavedReport(savedReport) {
            let definition = angular.fromJson(savedReport.definition);
            let reportParameters = ReportBuilder.determineParameters(definition);
            if (Object.keys(reportParameters).length === 0) {
                buildReport({ definition: definition });
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
                        return angular.extend(reportParameters, getPresets(savedReport.id));
                    },
                },
            });

            modalInstance.result.then(
                function (parameters) {
                    savePresets(savedReport.id, parameters);
                    buildReport({
                        definition: definition,
                        parameters: parameters,
                    });
                },
                function () {
                    // canceled
                },
            );
        }

        function buildStandardReport(report) {
            let hasParameters = false;
            angular.forEach(report.availableParameters, function (value) {
                hasParameters = !!value || hasParameters;
            });
            if (!hasParameters) {
                buildReport({ type: report.id });
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
                        return report.availableParameters;
                    },
                    parameters: function () {
                        return getPresets(report.id);
                    },
                },
            });

            modalInstance.result.then(
                function (parameters) {
                    savePresets(report.id, parameters);
                    buildReport({
                        type: report.id,
                        parameters: parameters,
                    });
                },
                function () {
                    // canceled
                },
            );
        }

        function buildReport(params) {
            $scope.building = true;
            Report.create(
                params,
                function (_report) {
                    $scope.building = false;
                    $state.go('manage.report.view', { id: _report.id });
                },
                function (result) {
                    $scope.building = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
