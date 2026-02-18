(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToNetsuite', connectToNetsuite);

    function connectToNetsuite() {
        return {
            restrict: 'E',
            template:
                '<feature-upgrade upgrade-class="\'inline-upgrade\'" ng-if="!(\'netsuite\'|hasFeature)"></feature-upgrade>' +
                '<button type="button" class="btn btn-success" ng-click="connect()" ng-if="\'netsuite\'|hasFeature">' +
                '<span class="fas fa-plus"></span> Install' +
                '</button>',
            scope: {},
            controller: [
                '$scope',
                '$modal',
                '$window',
                function ($scope, $modal, $window) {
                    $scope.connect = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'integrations/views/connect-netsuite.html',
                            controller: 'ConnectNetSuiteController',
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
