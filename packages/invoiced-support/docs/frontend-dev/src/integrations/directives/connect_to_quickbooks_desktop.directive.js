(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToQuickbooksDesktop', connectToQuickbooksDesktop);

    function connectToQuickbooksDesktop() {
        return {
            restrict: 'E',
            template:
                '<feature-upgrade upgrade-class="\'inline-upgrade\'" ng-if="!(\'accounting_sync\'|hasFeature)"></feature-upgrade>\n' +
                '<a href="" ng-click="install()" class="btn btn-success" ng-disabled="saving" ng-if="\'accounting_sync\'|hasFeature">\n' +
                '<span class="fas fa-plus"></span> Install\n' +
                '</a>',
            scope: {
                callback: '&',
            },
            controller: [
                '$scope',
                'AccountingSyncProfile',
                function ($scope, AccountingSyncProfile) {
                    $scope.install = function () {
                        $scope.saving = true;
                        $scope.error = null;

                        AccountingSyncProfile.create(
                            { integration: 'quickbooks_desktop' },
                            function () {
                                $scope.saving = false;
                                $scope.callback({});
                            },
                            function (result) {
                                $scope.saving = false;
                                $scope.error = result.data;
                            },
                        );
                    };
                },
            ],
        };
    }
})();
