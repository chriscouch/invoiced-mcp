(function () {
    'use strict';

    angular.module('app.automations').directive('joinDropdown', joinDropdown);

    function joinDropdown() {
        return {
            restrict: 'E',
            template:
                '<div class="email-variable-selector dynamic-variable-selector ">' +
                '<select-customer ng-if="join([\'customer\', \'parent_customer\'])" watch="watch" ng-model="item" allow-new="false"></select-customer>' +
                '<select-user ng-if="join([\'member\', \'owner\'])" watch="watch" ng-model="item"></select-user>' +
                '<select-plan ng-if="join([\'plan\'])" watch="watch" ng-model="item"></select-plan>' +
                '</div>',
            scope: {
                field: '=',
                properties: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.watch = function (newVal) {
                        if (newVal) {
                            $scope.field.value = newVal.id;
                        }
                    };

                    $scope.join = function (objects) {
                        for (const i in objects) {
                            const object = objects[i];
                            if ($scope.field.name !== object) {
                                continue;
                            }
                            for (const i in $scope.properties) {
                                if ($scope.properties[i].id === $scope.field.name) {
                                    return $scope.properties[i].join;
                                }
                            }
                        }

                        return false;
                    };
                },
            ],
        };
    }
})();
