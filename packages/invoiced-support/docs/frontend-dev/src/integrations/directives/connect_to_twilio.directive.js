(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToTwilio', connectToTwilio);

    function connectToTwilio() {
        return {
            restrict: 'E',
            template:
                '<feature-upgrade upgrade-class="\'inline-upgrade\'" ng-if="!(\'sms\'|hasFeature)"></feature-upgrade>' +
                '<a href="" ng-click="connect()" class="twilio-connect-button" ng-if="\'sms\'|hasFeature"></a>',
            scope: {},
            controller: [
                '$scope',
                '$modal',
                '$window',
                function ($scope, $modal, $window) {
                    $scope.connect = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'integrations/views/connect-twilio.html',
                            controller: 'ConnectTwilioController',
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
