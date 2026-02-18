/* globals moment */
(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('SetDiscountExpirationController', SetDiscountExpirationController);

    SetDiscountExpirationController.$inject = ['$scope', '$modalInstance', 'appliedRate', 'DatePickerService'];

    function SetDiscountExpirationController($scope, $modalInstance, appliedRate, DatePickerService) {
        $scope.appliedRate = angular.copy(appliedRate);
        $scope.when = appliedRate.expires instanceof Date ? 'on_date' : 'never';

        $scope.dateOptions = DatePickerService.getOptions({
            // cannot select an expiration date in the past or today
            minDate: 1,
        });

        $scope.save = function (when, appliedRate) {
            if (when === 'never') {
                $modalInstance.close(null);
            } else {
                let date = moment(appliedRate.expires).startOf('day').toDate();
                $modalInstance.close(date);
            }
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
