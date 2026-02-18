(function () {
    'use strict';

    angular.module('app.components').directive('columnArrangementButton', columnArrangementButton);

    function columnArrangementButton() {
        return {
            restrict: 'E',
            template:
                '<a role="button" class="column-arrangement-button px-2" ng-click="arrangeColumns()" tooltip="Edit Columns" tooltip-placement="left"><span class="fas fa-line-columns"></span></a>',
            scope: {
                type: '@',
                allColumns: '=',
                callback: '=',
            },
            controller: [
                '$scope',
                '$state',
                '$modal',
                'LeavePageWarning',
                function ($scope, $state, $modal, LeavePageWarning) {
                    $scope.arrangeColumns = function () {
                        LeavePageWarning.block();

                        const modalInstance = $modal.open({
                            templateUrl: 'ui/views/column-arrangement-modal.html',
                            controller: 'ColumnArrangementController',
                            backdrop: 'static',
                            keyboard: false,
                            resolve: {
                                type: function () {
                                    return $scope.type;
                                },
                                columns: function () {
                                    return $scope.allColumns;
                                },
                            },
                        });

                        modalInstance.result.then(
                            function () {
                                LeavePageWarning.unblock();

                                if (LeavePageWarning.canLeave()) {
                                    $scope.callback();
                                }
                            },
                            function () {
                                // canceled
                                LeavePageWarning.unblock();
                            },
                        );
                    };
                },
            ],
        };
    }
})();
