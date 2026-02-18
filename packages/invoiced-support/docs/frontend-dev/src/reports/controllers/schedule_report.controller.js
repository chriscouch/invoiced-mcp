(function () {
    'use strict';

    angular.module('app.reports').controller('ScheduleReportController', ScheduleReportController);

    ScheduleReportController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'ReportBuilder',
        'Report',
        'schedule',
        'savedReport',
    ];

    function ScheduleReportController($scope, $modal, $modalInstance, ReportBuilder, Report, schedule, savedReport) {
        $scope.savedReport = savedReport;
        if (schedule) {
            $scope.schedule = angular.copy(schedule);
        } else {
            $scope.schedule = {
                frequency: 'day_of_week',
                run_date: 1,
                time_of_day: 7,
                parameters: {},
            };
        }

        $scope.weekMapping = [
            { id: 1, name: 'Monday' },
            { id: 2, name: 'Tuesday' },
            { id: 3, name: 'Wednesday' },
            { id: 4, name: 'Thursday' },
            { id: 5, name: 'Friday' },
            { id: 6, name: 'Saturday' },
            { id: 7, name: 'Sunday' },
        ];

        $scope.monthMapping = [];
        for (let i = 1; i <= 31; i++) {
            $scope.monthMapping.push({
                id: i,
                name: ordinal_suffix_of(i),
            });
        }

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.save = function (schedule) {
            $scope.saving = true;
            let definition = angular.fromJson(savedReport.definition);
            let reportParameters = ReportBuilder.determineParameters(definition);
            if (Object.keys(reportParameters).length === 0) {
                saveSchedule(schedule);
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
                        return angular.extend(reportParameters, schedule.parameters);
                    },
                },
            });

            modalInstance.result.then(
                function (parameters) {
                    schedule.parameters = parameters;
                    saveSchedule(schedule);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.delete = deleteSchedule;

        function saveSchedule(schedule) {
            schedule.time_of_day = parseInt(schedule.time_of_day);
            if (schedule.id) {
                Report.editScheduledReport(
                    {
                        id: schedule.id,
                    },
                    {
                        parameters: schedule.parameters,
                        time_of_day: schedule.time_of_day,
                        frequency: schedule.frequency,
                        run_date: schedule.run_date,
                    },
                    function (result) {
                        $scope.saving = false;
                        $modalInstance.close(result);
                    },
                    function (error) {
                        $scope.saving = false;
                        $scope.error = error.data;
                    },
                );
            } else {
                schedule.saved_report = savedReport.id;
                Report.createScheduledReport(
                    schedule,
                    function (result) {
                        $scope.saving = false;
                        $modalInstance.close(result);
                    },
                    function (error) {
                        $scope.saving = false;
                        $scope.error = error.data;
                    },
                );
            }
        }

        function deleteSchedule(schedule) {
            $scope.saving = true;
            Report.deleteScheduledReport(
                {
                    id: schedule.id,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (error) {
                    $scope.saving = false;
                    $scope.error = error.data;
                },
            );
        }

        // Found on: http://stackoverflow.com/questions/13627308/add-st-nd-rd-and-th-ordinal-suffix-to-a-number#13627586
        function ordinal_suffix_of(i) {
            let j = i % 10,
                k = i % 100;
            if (j == 1 && k != 11) {
                return i + 'st';
            }
            if (j == 2 && k != 12) {
                return i + 'nd';
            }
            if (j == 3 && k != 13) {
                return i + 'rd';
            }
            return i + 'th';
        }
    }
})();
