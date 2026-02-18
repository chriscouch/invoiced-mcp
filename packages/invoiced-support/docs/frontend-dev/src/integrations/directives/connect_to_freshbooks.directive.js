(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToFreshbooks', connectToFreshbooks);

    function connectToFreshbooks() {
        return {
            restrict: 'E',
            template:
                '<feature-upgrade upgrade-class="\'inline-upgrade\'" ng-if="!(\'accounting_sync\'|hasFeature)"></feature-upgrade>' +
                '<a href="{{connectUrl}}" class="btn btn-success" ng-if="\'accounting_sync\'|hasFeature">' +
                '<span class="fas fa-plus"></span> Install' +
                '</a>',
            controller: [
                '$scope',
                'AppDirectory',
                'selectedCompany',
                function ($scope, AppDirectory, selectedCompany) {
                    $scope.connectUrl = AppDirectory.get('freshbooks').connectUrl + '?company=' + selectedCompany.id;
                },
            ],
        };
    }
})();
