/* globals moment */
(function () {
    'use strict';

    angular.module('app.settings').controller('GeneratePaymentFileController', GeneratePaymentFileController);

    GeneratePaymentFileController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'DatePickerService'];

    function GeneratePaymentFileController($scope, $modalInstance, selectedCompany, DatePickerService) {
        $scope.date = moment().add(1, 'day').toDate();

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.generate = function (date) {
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
