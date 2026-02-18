(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToChartmogul', connectToChartmogul);

    function connectToChartmogul() {
        return {
            restrict: 'E',
            template:
                '<button type="button" class="btn btn-success" ng-click="connect()"><span class="fas fa-plus"></span> Install</button>',
            scope: {},
            controller: [
                '$scope',
                '$modal',
                '$window',
                function ($scope, $modal, $window) {
                    $scope.connect = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'integrations/views/connect-chartmogul.html',
                            controller: 'ConnectChartMogulController',
                            backdrop: 'static',
                            keyboard: false,
                        });

                        modalInstance.result.then(function () {
                            $window.location.reload();
                        });
                    };
                },
            ],
        };
    }
})();
