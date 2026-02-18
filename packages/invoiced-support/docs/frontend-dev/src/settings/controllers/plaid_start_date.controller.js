/* globals moment */
(function () {
    'use strict';

    angular.module('app.settings').controller('PlaidStartDateController', PlaidStartDateController);

    PlaidStartDateController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'DatePickerService'];

    function PlaidStartDateController($scope, $modalInstance, selectedCompany, DatePickerService) {
        $scope.company = angular.copy(selectedCompany);
        $scope.date = moment().toDate();

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.save = function (date) {
            $modalInstance.close(moment(date).format('YYYY-MM-DD'));
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
