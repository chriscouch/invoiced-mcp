(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToLob', connectToLob);

    function connectToLob() {
        return {
            restrict: 'E',
            template:
                '<feature-upgrade upgrade-class="\'inline-upgrade\'" ng-if="!(\'letters\'|hasFeature)"></feature-upgrade>' +
                '<button type="button" class="btn btn-success" ng-click="connect()" ng-if="\'letters\'|hasFeature">' +
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
                            templateUrl: 'integrations/views/connect-lob.html',
                            controller: 'ConnectLobController',
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
