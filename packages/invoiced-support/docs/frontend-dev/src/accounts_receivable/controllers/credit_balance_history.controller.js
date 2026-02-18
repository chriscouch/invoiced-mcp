(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('CreditBalanceHistoryController', CreditBalanceHistoryController);

    CreditBalanceHistoryController.$inject = ['$scope', '$modalInstance', 'customer', 'balance', 'selectedCompany'];

    function CreditBalanceHistoryController($scope, $modalInstance, customer, balance, selectedCompany) {
        $scope.company = selectedCompany;

        $scope.history = angular.copy(balance.history);
        $scope.history.push({
            timestamp: customer.created_at,
            balance: 0,
            currency: balance.currency,
        });

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
