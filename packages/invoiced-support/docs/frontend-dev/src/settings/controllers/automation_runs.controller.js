(function () {
    'use strict';

    angular.module('app.settings').controller('AutomationRunsController', AutomationRunsController);

    AutomationRunsController.$inject = ['$scope', '$modal', 'Company', 'selectedCompany', 'Core', 'AutomationWorkflow'];

    function AutomationRunsController($scope, $modal, Company, selectedCompany, Core, AutomationWorkflow) {
        $scope.loading = true;
        $scope.page = 1;
        $scope.perPage = 100;

        Core.setTitle('Automations');

        $scope.details = details;

        load();

        $scope.prevPage = function () {
            $scope.page--;
            load();
        };

        $scope.nextPage = function () {
            $scope.page++;
            load();
        };

        function load() {
            $scope.loading = true;

            AutomationWorkflow.runs(
                {
                    per_page: $scope.perPage,
                    page: $scope.page,
                    paginate: 'none',
                },
                function (runs) {
                    $scope.loading = false;
                    $scope.runs = runs;
                },
                function (result) {
                    $scope.loading = false;
                    $scope.error = result.data;
                },
            );
        }

        function details(run) {
            $modal.open({
                templateUrl: 'settings/views/automation-run-details.html',
                controller: 'AutomationRunDetailsController',
                size: 'lg',
                resolve: {
                    run: function () {
                        return run;
                    },
                },
            });
        }
    }
})();
