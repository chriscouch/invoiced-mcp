(function () {
    'use strict';

    angular.module('app.automations').directive('automateButton', automateButton);

    function automateButton() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="{{btnClass}}" ng-if="\'automations\'|hasFeature" ng-click="openModal()">' +
                '<span class="fas fa-bolt"></span>' +
                '<span class="hidden-xs">Automate</span>' +
                '</a>',
            scope: {
                objectType: '=',
                objectId: '=',
                btnClass: '=',
            },
            controller: [
                '$scope',
                '$modal',
                function ($scope, $modal) {
                    $scope.openModal = function () {
                        $modal.open({
                            templateUrl: 'automations/views/automate-object.html',
                            controller: 'AutomateObjectController',
                            resolve: {
                                objectType: function () {
                                    return $scope.objectType;
                                },
                                objectId: function () {
                                    return $scope.objectId;
                                },
                            },
                        });
                    };
                },
            ],
        };
    }
})();
