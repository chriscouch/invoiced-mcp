(function () {
    'use strict';

    angular.module('app.core').controller('SwitchBusinessController', SwitchBusinessController);

    SwitchBusinessController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$timeout',
        '$state',
        'CurrentUser',
        'selectedCompany',
    ];

    function SwitchBusinessController($scope, $modalInstance, $modal, $timeout, $state, CurrentUser, selectedCompany) {
        $scope.companies = CurrentUser.companies;
        $scope.company = selectedCompany;

        $scope.switchCompany = function (company) {
            if (company.id == selectedCompany.id) {
                return;
            }
            if ($scope.timeoutHandler) {
                $timeout.cancel($scope.timeoutHandler);
            }
            CurrentUser.useCompany(company);
            $state.go('index');
        };

        $scope.addBusiness = function () {
            $modal.open({
                templateUrl: 'core/views/add-business.html',
                controller: 'AddBusinessController',
                backdrop: 'static',
                keyboard: false,
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
