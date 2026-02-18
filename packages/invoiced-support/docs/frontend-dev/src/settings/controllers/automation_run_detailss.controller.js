(function () {
    'use strict';

    angular.module('app.settings').controller('AutomationRunDetailsController', AutomationRunDetailsController);

    AutomationRunDetailsController.$inject = [
        '$scope',
        '$modalInstance',
        'Core',
        'ObjectDeepLink',
        'AutomationWorkflow',
        'Event',
        'run',
    ];
    function AutomationRunDetailsController(
        $scope,
        $modalInstance,
        Core,
        ObjectDeepLink,
        AutomationWorkflow,
        Event,
        run,
    ) {
        $scope.run = run;
        $scope.event = null;
        $scope.trigger = null;
        $scope.object = null;
        $scope.steps = [];
        $scope.loading = 0;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        load();

        $scope.goToObject = function (type, id) {
            $scope.close();
            ObjectDeepLink.goTo(type, id);
        };

        function load() {
            if (run.event_id) {
                ++$scope.loading;

                Event.retrieve(
                    {
                        id: run.event_id,
                    },
                    function (event) {
                        --$scope.loading;
                        $scope.event = event;
                    },
                    function (result) {
                        --$scope.loading;
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            }
            ++$scope.loading;
            AutomationWorkflow.run(
                {
                    id: run.id,
                    include: 'object,steps',
                    expand: 'trigger',
                },
                function (run) {
                    --$scope.loading;
                    $scope.trigger = run.trigger;
                    $scope.steps = run.steps;
                    $scope.object = run.object;
                },
                function (result) {
                    --$scope.loading;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
