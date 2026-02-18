(function () {
    'use strict';

    angular.module('app.settings').controller('PaymentsSettingsController', PaymentsSettingsController);

    PaymentsSettingsController.$inject = ['$scope', 'selectedCompany', 'Core'];

    function PaymentsSettingsController($scope, selectedCompany, Core) {
        $scope.company = angular.copy(selectedCompany);

        Core.setTitle('Payment Methods');
    }
})();
