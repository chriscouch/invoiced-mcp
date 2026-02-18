(function () {
    'use strict';

    angular.module('app.integrations').controller('SaveReportController', SaveReportController);

    SaveReportController.$inject = ['$scope', '$modalInstance', 'Report', 'report', 'savedReport'];

    function SaveReportController($scope, $modalInstance, Report, report, savedReport) {
        $scope.savedReport = savedReport || {
            name: report.title,
            private: true,
        };
        $scope.updateExisting = false;
        $scope.savedReports = [];
        $scope.existingReport = null;

        $scope.save = function (savedReport, updateExisting, existingReport) {
            if (updateExisting) {
                savedReport = existingReport;
            }

            let params = {
                name: savedReport.name,
                definition: report.definition,
                private: savedReport.private,
            };

            $scope.saving = true;
            if (savedReport.id) {
                Report.editSavedReport(
                    {
                        id: savedReport.id,
                    },
                    params,
                    function (_savedReport) {
                        $scope.saving = false;
                        $modalInstance.close(_savedReport);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                Report.createSavedReport(
                    params,
                    function (_savedReport) {
                        $scope.saving = false;
                        $modalInstance.close(_savedReport);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadSavedReports();

        function loadSavedReports() {
            $scope.loading = true;
            Report.findAllSaved(
                {
                    sort: 'name ASC',
                },
                function (savedReports) {
                    $scope.loading = false;
                    $scope.savedReports = savedReports;
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
