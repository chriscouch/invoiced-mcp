(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToEarthClassMail', connectToEarthClassMail);

    function connectToEarthClassMail() {
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
                            templateUrl: 'integrations/views/connect-earth-class-mail.html',
                            controller: 'ConnectEarthClassMailController',
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
