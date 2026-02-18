(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('MergeCustomerController', MergeCustomerController);

    MergeCustomerController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        '$timeout',
        'Customer',
        'Core',
        'selectedCompany',
        'customer',
    ];

    function MergeCustomerController(
        $scope,
        $modal,
        $modalInstance,
        $timeout,
        Customer,
        Core,
        selectedCompany,
        customer,
    ) {
        $scope.customer = customer;
        $scope.customer2 = null;
        $scope.isPreviewing = false;
        $scope.excludeCustomerIds = [customer.id];

        $scope.preview = function (customer1, customer2) {
            $scope.isPreviewing = true;
            $scope.previewCustomer1 = customer1;
            $scope.previewCustomer2 = customer2;
        };

        $scope.cancelPreview = function () {
            $scope.isPreviewing = false;
        };

        $scope.save = function (customer1, customer2) {
            $scope.saving = true;
            Customer.merge(
                {
                    id: customer1.id,
                },
                {
                    customer: customer2.id,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (result) {
                    $scope.error = result.data.message;
                    $scope.saving = false;
                },
            );
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
